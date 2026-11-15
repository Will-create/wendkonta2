<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class OperationalViewsTest extends TestCase
{
    public function test_operational_templates_compile_to_valid_php(): void
    {
        config(['view.compiled' => sys_get_temp_dir()]);
        foreach (['backend/customer/account', 'backend/customer/ledger', 'backend/customer/statement',
            'backend/customer/receipt', 'backend/purchase/defaults', 'backend/purchase/quick-product',
            'backend/purchase/create', 'backend/quotation/create_purchase', 'backend/layout/mobile-navigation',
            'backend/layout/mobile-menu-tools', 'backend/sale/create', 'backend/layout/main',
            'backend/account/index', 'backend/report/profit_loss', 'backend/product/index',
            'backend/customer/accounts', 'backend/index'] as $view) {
            $compiled = Blade::compileString(file_get_contents(resource_path('views/'.$view.'.blade.php')));
            token_get_all($compiled, TOKEN_PARSE);
            $this->assertNotEmpty($compiled, $view);
        }
    }
}
