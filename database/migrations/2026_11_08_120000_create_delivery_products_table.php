<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('delivery_id');
            $table->unsignedInteger('product_sale_id');
            $table->unsignedInteger('product_id')->nullable();
            $table->double('qty');
            $table->timestamps();

            $table->unique(['delivery_id', 'product_sale_id'], 'delivery_products_line_unique');
            $table->index('product_sale_id');
            $table->foreign('delivery_id')
                ->references('id')
                ->on('deliveries')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_products');
    }
};
