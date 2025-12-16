<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToInternalOrdersTables extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('internal_orders', function (Blueprint $table) {
            $table->foreign('user_id', 'user_id_internal_orders')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('from_warehouse_id', 'from_warehouse_id_internal_orders')->references('id')->on('warehouses')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('to_warehouse_id', 'to_warehouse_id_internal_orders')->references('id')->on('warehouses')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('approved_by', 'approved_by_internal_orders')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('transfer_id', 'transfer_id_internal_orders')->references('id')->on('transfers')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });

        Schema::table('internal_order_details', function (Blueprint $table) {
            $table->foreign('internal_order_id', 'internal_order_id')->references('id')->on('internal_orders')->onUpdate('RESTRICT')->onDelete('CASCADE');
            $table->foreign('product_id', 'product_id_internal_orders')->references('id')->on('products')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('product_variant_id', 'product_variant_id_internal_orders')->references('id')->on('product_variants')->onUpdate('RESTRICT')->onDelete('RESTRICT');
            $table->foreign('purchase_unit_id', 'purchase_unit_id_internal_orders')->references('id')->on('units')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('internal_orders', function (Blueprint $table) {
            $table->dropForeign('user_id_internal_orders');
            $table->dropForeign('from_warehouse_id_internal_orders');
            $table->dropForeign('to_warehouse_id_internal_orders');
            $table->dropForeign('approved_by_internal_orders');
            $table->dropForeign('transfer_id_internal_orders');
        });

        Schema::table('internal_order_details', function (Blueprint $table) {
            $table->dropForeign('internal_order_id');
            $table->dropForeign('product_id_internal_orders');
            $table->dropForeign('product_variant_id_internal_orders');
            $table->dropForeign('purchase_unit_id_internal_orders');
        });
    }

}
