<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForeignKeysToInterwarehouseTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Foreign keys for interwarehouse_requests
        Schema::table('interwarehouse_requests', function (Blueprint $table) {
            $table->foreign('requester_warehouse_id', 'fk_iw_req_warehouse')
                ->references('id')->on('warehouses')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('supplier_warehouse_id', 'fk_iw_sup_warehouse')
                ->references('id')->on('warehouses')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('user_id', 'fk_iw_user')
                ->references('id')->on('users')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('proforma_by', 'fk_iw_proforma_user')
                ->references('id')->on('users')
                ->onDelete('set null')->onUpdate('restrict');

            $table->foreign('validated_by', 'fk_iw_validated_user')
                ->references('id')->on('users')
                ->onDelete('set null')->onUpdate('restrict');
        });

        // Foreign keys for interwarehouse_items
        Schema::table('interwarehouse_items', function (Blueprint $table) {
            $table->foreign('interwarehouse_request_id', 'fk_iw_items_request')
                ->references('id')->on('interwarehouse_requests')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->foreign('product_id', 'fk_iw_items_product')
                ->references('id')->on('products')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('product_variant_id', 'fk_iw_items_variant')
                ->references('id')->on('product_variants')
                ->onDelete('set null')->onUpdate('restrict');

            $table->foreign('unit_id', 'fk_iw_items_unit')
                ->references('id')->on('units')
                ->onDelete('set null')->onUpdate('restrict');
        });

        // Foreign keys for interwarehouse_payments
        Schema::table('interwarehouse_payments', function (Blueprint $table) {
            $table->foreign('interwarehouse_request_id', 'fk_iw_payments_request')
                ->references('id')->on('interwarehouse_requests')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->foreign('payment_method_id', 'fk_iw_payments_method')
                ->references('id')->on('payment_methods')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('account_id', 'fk_iw_payments_account')
                ->references('id')->on('accounts')
                ->onDelete('set null')->onUpdate('restrict');

            $table->foreign('user_id', 'fk_iw_payments_user')
                ->references('id')->on('users')
                ->onDelete('restrict')->onUpdate('restrict');
        });

        // Foreign keys for interwarehouse_deliveries
        Schema::table('interwarehouse_deliveries', function (Blueprint $table) {
            $table->foreign('interwarehouse_request_id', 'fk_iw_deliveries_request')
                ->references('id')->on('interwarehouse_requests')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->foreign('user_id', 'fk_iw_deliveries_user')
                ->references('id')->on('users')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('transfer_id', 'fk_iw_deliveries_transfer')
                ->references('id')->on('transfers')
                ->onDelete('set null')->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('interwarehouse_deliveries', function (Blueprint $table) {
            $table->dropForeign('fk_iw_deliveries_request');
            $table->dropForeign('fk_iw_deliveries_user');
            $table->dropForeign('fk_iw_deliveries_transfer');
        });

        Schema::table('interwarehouse_payments', function (Blueprint $table) {
            $table->dropForeign('fk_iw_payments_request');
            $table->dropForeign('fk_iw_payments_method');
            $table->dropForeign('fk_iw_payments_account');
            $table->dropForeign('fk_iw_payments_user');
        });

        Schema::table('interwarehouse_items', function (Blueprint $table) {
            $table->dropForeign('fk_iw_items_request');
            $table->dropForeign('fk_iw_items_product');
            $table->dropForeign('fk_iw_items_variant');
            $table->dropForeign('fk_iw_items_unit');
        });

        Schema::table('interwarehouse_requests', function (Blueprint $table) {
            $table->dropForeign('fk_iw_req_warehouse');
            $table->dropForeign('fk_iw_sup_warehouse');
            $table->dropForeign('fk_iw_user');
            $table->dropForeign('fk_iw_proforma_user');
            $table->dropForeign('fk_iw_validated_user');
        });
    }
}
