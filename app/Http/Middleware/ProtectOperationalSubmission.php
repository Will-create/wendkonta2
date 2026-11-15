<?php

namespace App\Http\Middleware;

use App\Services\OperationalInput;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProtectOperationalSubmission
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->input('operation_token');
        $request->request->remove('operation_token');
        if ($request->isJson()) {
            $request->json()->remove('operation_token');
        }
        if ($request->isMethodSafe() || !$request->user()) {
            $response = $next($request);
            if ($request->user()) {
                $response->headers->set('Cache-Control', 'private, no-store');
            }
            return $response;
        }
        [$class, $method] = array_pad(explode('@', $this->actionName($request)), 2, '');
        $controller = class_basename($class);
        if (!in_array($method, config('mobile.operations.'.$controller, []))) {
            return $next($request);
        }
        $permission = config('mobile.permissions.'.$controller.'@'.$method);
        if ($permission) {
            abort_unless(DB::table('permissions')->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_has_permissions.role_id', $request->user()->role_id)->where('permissions.name', $permission)->exists(), 403);
        }
        if ($request->user()->role_id > 2 && $request->user()->warehouse_id && $request->filled('warehouse_id')) {
            abort_unless((int) $request->warehouse_id === (int) $request->user()->warehouse_id, 403);
        }
        if ($controller === 'TransferController' && $request->user()->role_id > 2 && $request->user()->warehouse_id && $request->filled('from_warehouse_id')) {
            abort_unless((int) $request->from_warehouse_id === (int) $request->user()->warehouse_id, 403);
        }
        if ($token !== null) {
            validator(['operation_token' => $token], ['operation_token' => 'required|uuid'])->validate();
            abort_unless(Schema::hasTable('operation_receipts'), 503, 'Operational safety upgrade is not ready. Please try again shortly.');
        }
        app(OperationalInput::class)->validate($request, $controller, $method);
        $operation = $controller.'@'.$method;
        $payload = $request->except(['_token']);
        foreach ($request->allFiles() as $key => $files) {
            $payload[$key] = $this->fileFingerprint($files);
        }
        $fingerprint = hash('sha256', $request->method().'|'.$request->path().'|'.json_encode($this->canonical($payload), JSON_THROW_ON_ERROR));
        $connection = DB::connection();
        $level = $connection->transactionLevel();
        if ($level === 0 && $connection->getDriverName() === 'mysql') {
            // Reads after waiting on stock locks must see the latest committed quantities.
            $connection->statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }
        $connection->beginTransaction();
        try {
            if ($token) {
                $created = DB::table('operation_receipts')->insertOrIgnore([
                    'user_id' => $request->user()->id, 'token' => $token,
                    'operation' => $operation, 'fingerprint' => $fingerprint,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $receipt = DB::table('operation_receipts')->where('user_id', $request->user()->id)->where('token', $token)->lockForUpdate()->first();
                if (!$created) {
                    abort_unless($receipt && hash_equals($receipt->fingerprint, $fingerprint) && $receipt->operation === $operation, 409, 'This submission was already used for different values. Review the saved record before submitting again.');
                    abort_unless($receipt->response_metadata, 409, 'This operation is still being processed. Please check its status.');
                    $metadata = json_decode(Crypt::decryptString($receipt->response_metadata), true, 512, JSON_THROW_ON_ERROR);
                    $connection->commit();
                    return response($metadata['body'], $metadata['status'], $metadata['headers'])
                        ->header('X-SalePro-Operation', 'complete')->header('X-SalePro-Replayed', 'true');
                }
                $request->attributes->set('salepro.operation_key', hash('sha256', $request->user()->id.'|'.$token));
            }
            $this->lockRows($request, $controller);
            $response = $next($request);
            $flashed = $request->hasSession() ? $request->session()->get('_flash.new', []) : [];
            $failed = $response->getStatusCode() >= 400 || array_intersect(['errors', 'error', 'not_permitted'], $flashed);
            if ($failed) {
                $connection->rollBack($level);
                return $response;
            }
            if ($connection->transactionLevel() !== $level + 1) {
                throw new \RuntimeException('The operation did not close its database transaction. No partial changes were saved.');
            }
            if ($token) {
                $type = $response->headers->get('Content-Type', 'text/html; charset=UTF-8');
                $body = $response->getContent();
                $headers = ['Content-Type' => $type, 'Cache-Control' => 'no-store'];
                if ($response->headers->has('Location')) {
                    $headers['Location'] = $response->headers->get('Location');
                    $body = '';
                } elseif (!str_contains($type, 'json') && !ctype_digit(trim($body))) {
                    // Do not persist rendered customer/financial documents in the receipt table.
                    $body = 'This operation has already completed. Review the saved record before starting another.';
                    $type = $headers['Content-Type'] = 'text/plain; charset=UTF-8';
                }
                $metadata = ['status' => $response->getStatusCode(), 'body' => $body, 'headers' => $headers];
                DB::table('operation_receipts')->where('id', $receipt->id)->update([
                    'response_metadata' => Crypt::encryptString(json_encode($metadata, JSON_THROW_ON_ERROR)), 'updated_at' => now(),
                ]);
            }
            $connection->commit();
            $response->headers->set('X-SalePro-Operation', 'complete');
            $response->headers->set('Cache-Control', 'no-store');
            return $response;
        } catch (\Throwable $error) {
            if ($connection->transactionLevel() > $level) {
                $connection->rollBack($level);
            }
            throw $error;
        }
    }

    private function lockRows(Request $request, string $controller): void
    {
        $routeId = collect($request->route()->parameters())->first();
        $actionName = $this->actionName($request);
        $method = substr($actionName, strrpos($actionName, '@') + 1);
        $paymentId = $request->input('payment_id', $method === 'deletePayment' ? $request->input('id', $routeId) : null);
        $payment = is_numeric($paymentId) ? DB::table('payments')->where('id', $paymentId)->first() : null;
        if (in_array($method, ['updatePayment', 'deletePayment'])) {
            abort_unless($payment, 404);
            abort_unless($controller === 'SaleController' ? $payment->sale_id : $payment->purchase_id, 404);
        }
        $ids = ['sales' => $payment->sale_id ?? $request->input('sale_id', $controller === 'SaleController' ? $routeId : null),
            'purchases' => $payment->purchase_id ?? $request->input('purchase_id', $controller === 'PurchaseController' ? $routeId : null)];
        // Customer credit operations and ordinary POS writes share this lock order.
        $saleIds = array_filter(array_merge((array) $ids['sales'], (array) $request->input('saleIdArray', [])), 'is_numeric');
        $customerIds = array_filter((array) $request->input('customer_id'), 'is_numeric');
        if ($saleIds) {
            $customerIds = array_merge($customerIds, DB::table('sales')->whereIn('id', $saleIds)->pluck('customer_id')->all());
            if ($controller === 'SaleController' && in_array($method, ['update', 'destroy', 'deleteBySelection']) && Schema::hasTable('customer_deposit_allocations')) {
                abort_if(DB::table('customer_deposit_allocations')->whereIn('sale_id', $saleIds)->exists(), 422, __('credit.immutable_receipt'));
            }
        }
        if ($customerIds) DB::table('customers')->whereIn('id', $customerIds)->orderBy('id')->lockForUpdate()->get();
        $productIds = array_filter((array) $request->input('product_id', []), 'is_numeric');
        $catalogId = $request->input('id', $routeId);
        if ($controller === 'ProductController' && is_numeric($catalogId)) {
            $productIds[] = $catalogId;
        }
        foreach (['sales' => ['product_sales', 'sale_id'], 'purchases' => ['product_purchases', 'purchase_id']] as $table => [$lines, $foreignKey]) {
            if (is_numeric($ids[$table]) && in_array($method, ['update', 'destroy'])) {
                $productIds = array_merge($productIds, DB::table($lines)->where($foreignKey, $ids[$table])->pluck('product_id')->all());
            }
        }
        if ($request->filled('product_code')) {
            $productIds = array_merge($productIds, DB::table('products')->whereIn('code', (array) $request->input('product_code'))->pluck('id')->all());
        }
        if ($productIds) {
            $combos = DB::table('products')->whereIn('id', $productIds)->where('type', 'combo')->pluck('product_list');
            foreach ($combos as $children) {
                $productIds = array_merge($productIds, array_filter(explode(',', $children), 'is_numeric'));
            }
        }
        if ($productIds) {
            DB::table('products')->whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get();
            DB::table('product_warehouse')->whereIn('product_id', $productIds)->orderBy('id')->lockForUpdate()->get();
        }
        foreach ($ids as $table => $id) {
            if (is_numeric($id)) {
                $record = DB::table($table)->where('id', $id)->lockForUpdate()->first();
                if ($record) {
                    if ($request->user()->role_id > 2) {
                        abort_if($request->user()->warehouse_id && (int) $record->warehouse_id !== (int) $request->user()->warehouse_id, 403);
                        abort_if(config('staff_access') === 'own' && (int) $record->user_id !== (int) $request->user()->id, 403);
                    }
                    $request->attributes->set('salepro.'.$table, $record);
                }
            }
        }
        if ($payment) {
            abort_unless(DB::table('payments')->where('id', $paymentId)->lockForUpdate()->first(), 404);
        }
        $deposit = null;
        $depositId = $request->input('deposit_id', $controller === 'CustomerController' && $method === 'deleteDeposit' ? $request->input('id') : null);
        if ($depositId) {
            $deposit = DB::table('deposits')->where('id', $depositId)->lockForUpdate()->first();
            abort_unless($deposit, 404);
        }
        if ($request->input('customer_id') && str_ends_with($actionName, '@clearDue')) {
            DB::table('sales')->where('customer_id', $request->customer_id)->orderBy('id')->lockForUpdate()->get();
        }
        if ($request->input('supplier_id') && str_ends_with($actionName, '@clearDue')) {
            DB::table('purchases')->where('supplier_id', $request->supplier_id)->orderBy('id')->lockForUpdate()->get();
        }
        foreach (['customers' => 'customer_id', 'suppliers' => 'supplier_id', 'accounts' => 'account_id', 'gift_cards' => 'gift_card_id'] as $table => $field) {
            $id = $request->input($field);
            if ($table === 'customers' && !$id) {
                $id = $deposit?->customer_id ?? $request->attributes->get('salepro.sales')?->customer_id;
            }
            if ($table === 'suppliers' && !$id) {
                $id = $request->attributes->get('salepro.purchases')?->supplier_id;
            }
            if ($id) {
                DB::table($table)->whereIn('id', (array) $id)->orderBy('id')->lockForUpdate()->get();
            }
        }
    }

    private function actionName(Request $request): string
    {
        $actionName = $request->route()->getActionName();
        if (!str_contains($actionName, '@')) {
            $uses = $request->route()->getAction('uses');
            if (is_string($uses) && str_contains($uses, '@')) {
                return $uses;
            }
        }
        return $actionName;
    }

    private function canonical($value)
    {
        if (is_array($value)) {
            if (!array_is_list($value)) {
                ksort($value);
            }
            return array_map(fn ($item) => $this->canonical($item), $value);
        }
        return $value;
    }

    private function fileFingerprint($files)
    {
        if (is_array($files)) {
            return array_map(fn ($file) => $this->fileFingerprint($file), $files);
        }
        return ['name' => $files->getClientOriginalName(), 'sha256' => hash_file('sha256', $files->getRealPath())];
    }
}
