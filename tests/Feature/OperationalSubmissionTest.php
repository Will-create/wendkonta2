<?php

namespace Tests\Feature;

use App\Http\Middleware\ProtectOperationalSubmission;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OperationalSubmissionTest extends TestCase
{
    private const TOKEN = '11111111-1111-4111-8111-111111111111';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('s', 32))]);
        (require database_path('migrations/2026_11_14_200000_create_operation_receipts_table.php'))->up();
        Schema::create('test_ledger', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 14, 2);
        });
        Schema::create('permissions', function (Blueprint $table) { $table->id(); $table->string('name'); });
        Schema::create('role_has_permissions', function (Blueprint $table) { $table->unsignedBigInteger('role_id'); $table->unsignedBigInteger('permission_id'); });
        DB::table('permissions')->insert(['id' => 1, 'name' => 'sale-payment-add']);
        DB::table('role_has_permissions')->insert(['role_id' => 1, 'permission_id' => 1]);
    }

    private function operationRequest(array $data = [], int $user = 7): Request
    {
        $request = Request::create('/sales/add_payment', 'POST', array_merge(['amount' => '12.50', 'operation_token' => self::TOKEN], $data));
        $route = new Route('POST', '/sales/add_payment', ['uses' => 'App\Http\Controllers\SaleController@addPayment']);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => new GenericUser(['id' => $user, 'role_id' => 1, 'warehouse_id' => null]));
        return $request;
    }

    private function save(Request $request)
    {
        return app(ProtectOperationalSubmission::class)->handle($request, function ($request) {
            $this->assertFalse($request->has('operation_token'));
            DB::table('test_ledger')->insert(['amount' => $request->amount]);
            return response('42');
        });
    }

    public function test_retry_returns_original_result_and_changes_ledger_only_once(): void
    {
        $this->assertSame('42', $this->save($this->operationRequest())->getContent());
        $replay = $this->save($this->operationRequest());
        $this->assertSame('42', $replay->getContent());
        $this->assertSame('true', $replay->headers->get('X-SalePro-Replayed'));
        $this->assertSame(1, DB::table('test_ledger')->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_same_token_cannot_be_reused_for_changed_amount(): void
    {
        $this->save($this->operationRequest());
        try {
            $this->save($this->operationRequest(['amount' => '99']));
            $this->fail('Conflicting submission was accepted.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        $this->assertSame(1, DB::table('test_ledger')->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_tokens_are_scoped_to_authenticated_user(): void
    {
        $this->save($this->operationRequest());
        $this->save($this->operationRequest([], 8));
        $this->assertSame(2, DB::table('test_ledger')->count());
    }

    public function test_failed_response_rolls_back_ledger_and_receipt_for_safe_retry(): void
    {
        $result = app(ProtectOperationalSubmission::class)->handle($this->operationRequest(), function () {
            DB::table('test_ledger')->insert(['amount' => 12.5]);
            return response('failure', 500);
        });
        $this->assertSame(500, $result->getStatusCode());
        $this->assertSame(0, DB::table('test_ledger')->count());
        $this->assertSame(0, DB::table('operation_receipts')->count());
        $this->assertSame('42', $this->save($this->operationRequest())->getContent());
    }

    public function test_integration_without_token_keeps_existing_response(): void
    {
        $request = $this->operationRequest();
        $request->request->remove('operation_token');
        $this->assertSame('42', $this->save($request)->getContent());
        $this->assertSame(0, DB::table('operation_receipts')->count());
    }

    public function test_unclosed_nested_transaction_cannot_commit_partial_changes(): void
    {
        try {
            app(ProtectOperationalSubmission::class)->handle($this->operationRequest(), function () {
                DB::beginTransaction();
                DB::table('test_ledger')->insert(['amount' => 12.5]);
                return response('42');
            });
            $this->fail('Unclosed transaction was accepted.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('transaction', $error->getMessage());
        }
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(0, DB::table('test_ledger')->count());
    }

    public function test_payment_permission_is_enforced_on_the_server(): void
    {
        DB::table('role_has_permissions')->delete();
        try {
            $this->save($this->operationRequest());
            $this->fail('Payment without permission was accepted.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertSame(0, DB::table('test_ledger')->count());
    }

    public function test_legacy_error_flash_rolls_back_writes_and_allows_retry(): void
    {
        $request = $this->operationRequest();
        $session = new Store('operation-test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);
        app(ProtectOperationalSubmission::class)->handle($request, function () use ($session) {
            DB::table('test_ledger')->insert(['amount' => 12.5]);
            $session->flash('not_permitted', 'The transaction failed.');
            return response('', 302, ['Location' => '/sales']);
        });
        $this->assertSame(0, DB::table('test_ledger')->count());
        $this->assertSame(0, DB::table('operation_receipts')->count());
        $this->assertSame('42', $this->save($this->operationRequest())->getContent());
    }

    public function test_success_flash_commits_writes_and_returns_replay_marker(): void
    {
        $request = $this->operationRequest();
        $session = new Store('operation-test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);
        $response = app(ProtectOperationalSubmission::class)->handle($request, function () use ($session) {
            DB::table('test_ledger')->insert(['amount' => 12.5]);
            $session->flash('message', 'Payment deleted successfully.');
            return response('', 302, ['Location' => '/sales']);
        });
        $this->assertSame(1, DB::table('test_ledger')->count());
        $this->assertSame('complete', $response->headers->get('X-SalePro-Operation'));
        $this->assertSame('/sales', $this->save($this->operationRequest())->headers->get('Location'));
        $this->assertSame(1, DB::table('test_ledger')->count());
    }
}
