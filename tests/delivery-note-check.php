<?php

/**
 * Blank delivery notes are accepted on add and update, and every add-delivery
 * form shows an optional note field.
 *
 * Run from the repo root: php tests/delivery-note-check.php
 */

use App\Http\Controllers\DeliveryController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$failures = 0;

function check(string $name, bool $condition, string $detail = ''): void
{
    global $failures;
    if ($condition) {
        echo "PASS {$name}\n";
        return;
    }
    $failures++;
    echo "FAIL {$name}".($detail !== '' ? " {$detail}" : '')."\n";
}

function bootRequest(Request $request): Request
{
    if (!session()->isStarted()) {
        session()->start();
    }
    $request->setLaravelSession(session()->driver());
    app()->instance('request', $request);
    session()->forget('not_permitted');
    session()->forget('message');

    return $request;
}

function flashError(): string
{
    return (string) (session('not_permitted') ?: '');
}

function storeDelivery(array $payload): RedirectResponse
{
    $response = app(DeliveryController::class)->store(bootRequest(Request::create('/delivery/store', 'POST', $payload)));
    if (!$response instanceof RedirectResponse) {
        throw new RuntimeException('store did not redirect');
    }

    return $response;
}

function updateDelivery(array $payload): RedirectResponse
{
    $response = app(DeliveryController::class)->update(bootRequest(Request::create('/delivery/update', 'POST', $payload)));
    if (!$response instanceof RedirectResponse) {
        throw new RuntimeException('update did not redirect');
    }

    return $response;
}

function latestDelivery(int $saleId): ?object
{
    return DB::table('deliveries')->where('sale_id', $saleId)->orderByDesc('id')->first();
}

function noteIsEmpty(?object $delivery): bool
{
    return $delivery !== null && (string) ($delivery->note ?? '') === '';
}

function addDeliveryForms(): array
{
    $forms = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
    foreach ($iterator as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }
        $text = file_get_contents($file->getPathname());
        $offset = 0;
        while (($marker = strpos($text, "['route' => 'delivery.store'", $offset)) !== false) {
            $end = strpos($text, 'Form::close', $marker);
            if ($end === false) {
                break;
            }
            $forms[$file->getPathname().'#'.$marker] = substr($text, $marker, $end - $marker);
            $offset = $end + 10;
        }
    }

    return $forms;
}

function fieldTag(string $form, string $name): ?string
{
    if (!preg_match_all('/<textarea\b[^>]*>/i', $form, $tags)) {
        return null;
    }
    foreach ($tags[0] as $tag) {
        if (preg_match('/\bname=["\']'.preg_quote($name, '/').'["\']/', $tag)) {
            return $tag;
        }
    }

    return null;
}

function noteTag(string $form): ?string
{
    return fieldTag($form, 'note');
}

$beforeLines = (int) DB::table('delivery_products')->count();
$beforeDeliveries = (int) DB::table('deliveries')->count();
$open = false;

try {
    Mail::fake();
    Auth::loginUsingId(1);
    check('admin session', Auth::check() && (int) Auth::id() === 1);

    $forms = addDeliveryForms();
    check('at least one add-delivery form is shipped', $forms !== []);
    foreach ($forms as $name => $form) {
        $tag = noteTag($form);
        check('add form shows a note field', $tag !== null, $name);
        check('add form note is not required', $tag !== null && !preg_match('/\brequired\b/i', $tag), $tag ?? $name);
        $address = fieldTag($form, 'address');
        check('add form shows an address field', $address !== null, $name);
        check('add form address is not required', $address !== null && !preg_match('/\brequired\b/i', $address), $address ?? $name);
    }

    $updateText = file_get_contents(resource_path('views/backend/delivery/index.blade.php'));
    $updateStart = strpos($updateText, "['route' => 'delivery.update'");
    $updateEnd = $updateStart === false ? false : strpos($updateText, 'Form::close', $updateStart);
    $updateForm = ($updateStart !== false && $updateEnd !== false) ? substr($updateText, $updateStart, $updateEnd - $updateStart) : '';
    $updateNote = noteTag($updateForm);
    check('update form shows a note field', $updateNote !== null);
    check('update form note is not required', $updateNote !== null && !preg_match('/\brequired\b/i', $updateNote), $updateNote ?? '');

    DB::beginTransaction();
    $open = true;

    $candidate = DB::table('sales as s')
        ->join('product_sales as ps', 'ps.sale_id', '=', 's.id')
        ->whereNull('s.deleted_at')
        ->whereRaw('(ps.qty - IFNULL(ps.return_qty, 0)) >= 3')
        ->whereRaw('(SELECT COUNT(*) FROM product_sales ps2 WHERE ps2.sale_id = s.id) = 1')
        ->whereNotExists(function ($query) {
            $query->select(DB::raw(1))->from('deliveries as d')->whereColumn('d.sale_id', 's.id');
        })
        ->orderByDesc('s.id')
        ->first(['s.id as sale_id', 'ps.id as product_sale_id']);

    if (!$candidate) {
        throw new RuntimeException('No single-line sale with remaining quantity of at least 3.');
    }

    $saleId = (int) $candidate->sale_id;
    $lineId = (int) $candidate->product_sale_id;
    $base = [
        'sale_id' => $saleId,
        'address' => 'Note check address',
        'status' => '1',
        'delivered_by' => 'note-check',
        'recieved_by' => 'note-check',
        'deliver_qty' => [$lineId => 1],
    ];

    $withoutAddress = $base;
    unset($withoutAddress['address']);
    storeDelivery($withoutAddress);
    $omitted = latestDelivery($saleId);
    check('add without a note succeeds', $omitted !== null && flashError() === '', flashError());
    check('add without a note stores an empty note', noteIsEmpty($omitted), var_export($omitted->note ?? null, true));
    check('add without an address succeeds', flashError() === '', flashError());
    check('add without an address stores an empty address', $omitted !== null && (string) ($omitted->address ?? '') === '', var_export($omitted->address ?? null, true));

    $header = [
        'delivery_id' => $omitted->id,
        'reference_no' => $omitted->reference_no,
        'address' => $omitted->address,
        'status' => '2',
        'delivered_by' => $omitted->delivered_by,
        'recieved_by' => $omitted->recieved_by,
    ];

    updateDelivery($header + ['note' => 'filled-note']);
    $filled = DB::table('deliveries')->where('id', $omitted->id)->first();
    check('update can still save a note', $filled && $filled->note === 'filled-note' && flashError() === '', flashError().' note='.var_export($filled->note ?? null, true));

    updateDelivery($header);
    $cleared = DB::table('deliveries')->where('id', $omitted->id)->first();
    check('update without a note succeeds', flashError() === '', flashError());
    check('update without a note stores an empty note', noteIsEmpty($cleared), var_export($cleared->note ?? null, true));

    updateDelivery($header + ['note' => 'second-note']);
    updateDelivery($header + ['note' => '']);
    $blanked = DB::table('deliveries')->where('id', $omitted->id)->first();
    check('update with a blank note succeeds', flashError() === '', flashError());
    check('update with a blank note stores an empty note', noteIsEmpty($blanked), var_export($blanked->note ?? null, true));

    updateDelivery($header + ['note' => '   ']);
    $spaces = DB::table('deliveries')->where('id', $omitted->id)->first();
    check('update with a whitespace note stores an empty note', noteIsEmpty($spaces), var_export($spaces->note ?? null, true));

    storeDelivery(array_merge($base, ['note' => '', 'address' => '']));
    $blankAdd = latestDelivery($saleId);
    check('add with a blank note succeeds', (int) $blankAdd->id !== (int) $omitted->id && flashError() === '', flashError());
    check('add with a blank note stores an empty note', noteIsEmpty($blankAdd), var_export($blankAdd->note ?? null, true));
    check('add with a blank address stores an empty address', $blankAdd !== null && (string) ($blankAdd->address ?? '') === '', var_export($blankAdd->address ?? null, true));

    DB::rollBack();
    $open = false;

    check('rollback leaves deliveries unchanged', (int) DB::table('deliveries')->count() === $beforeDeliveries);
    check('rollback leaves delivery lines unchanged', (int) DB::table('delivery_products')->count() === $beforeLines);
} catch (Throwable $e) {
    $failures++;
    echo 'FAIL uncaught '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n";
} finally {
    if ($open && DB::transactionLevel() > 0) {
        DB::rollBack();
    }
}

check('delivery count matches the pre-check snapshot', (int) DB::table('deliveries')->count() === $beforeDeliveries);
check('delivery line count matches the pre-check snapshot', (int) DB::table('delivery_products')->count() === $beforeLines);

echo $failures === 0 ? "ALL CHECKS PASSED\n" : "FAILED {$failures} CHECKS\n";
exit($failures === 0 ? 0 : 1);
