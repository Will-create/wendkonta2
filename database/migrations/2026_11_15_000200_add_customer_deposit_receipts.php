<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('customer_credit_transfer', 24, 6)->default(0);
            $table->index(['sale_id', 'created_at'], 'payments_customer_account_date');
            $table->index(['account_id', 'created_at'], 'payments_account_cash_date');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['customer_id', 'sale_status', 'created_at'], 'sales_customer_credit_date');
        });
        Schema::table('deposits', function (Blueprint $table) {
            $table->uuid('receipt_token')->nullable()->unique();
            $table->string('request_hash', 64)->nullable();
            $table->dateTime('transaction_date')->nullable()->index();
            $table->string('payment_method', 30)->nullable();
            $table->string('reference', 191)->nullable();
            $table->unsignedInteger('account_id')->nullable();
            $table->unsignedInteger('cash_register_id')->nullable();
            $table->unsignedInteger('reversal_cash_register_id')->nullable();
            $table->dateTime('reversed_at')->nullable();
            $table->unsignedInteger('reversed_by')->nullable();
            $table->text('reversal_note')->nullable();
            $table->index(['customer_id', 'created_at']);
            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
        });
        Schema::create('customer_deposit_allocations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('deposit_id');
            $table->unsignedInteger('sale_id');
            $table->unsignedInteger('payment_id');
            $table->unsignedInteger('reversal_payment_id')->nullable()->unique();
            $table->decimal('amount', 24, 6);
            $table->timestamps();
            $table->unique(['deposit_id', 'payment_id']);
            $table->foreign('deposit_id')->references('id')->on('deposits')->restrictOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->restrictOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
            $table->foreign('reversal_payment_id')->references('id')->on('payments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_deposit_allocations');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_customer_account_date');
            $table->dropIndex('payments_account_cash_date');
            $table->dropColumn('customer_credit_transfer');
        });
        Schema::table('sales', fn (Blueprint $table) => $table->dropIndex('sales_customer_credit_date'));
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropIndex(['customer_id', 'created_at']);
            $table->dropColumn(['receipt_token', 'request_hash', 'transaction_date', 'payment_method',
                'reference', 'account_id', 'cash_register_id', 'reversal_cash_register_id', 'reversed_at', 'reversed_by', 'reversal_note']);
        });
    }
};
