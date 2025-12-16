<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalOrderDetailsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('internal_order_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('internal_order_id')->index('internal_order_id');
            $table->integer('product_id')->index('product_id_internal_orders');
            $table->integer('product_variant_id')->nullable()->index('product_variant_id_internal_orders');
            $table->integer('purchase_unit_id')->nullable()->index('purchase_unit_id_internal_orders');
            $table->float('quantity', 10);
            $table->float('cost', 10)->default(0);
            $table->float('TaxNet', 10)->nullable()->default(0);
            $table->string('tax_method', 50)->nullable()->default('1');
            $table->float('discount', 10)->nullable()->default(0);
            $table->string('discount_method', 50)->nullable()->default('1');
            $table->float('total', 10)->default(0);
            $table->timestamps(6);
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('internal_order_details');
    }

}
