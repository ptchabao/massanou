<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInterwarehouseItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('interwarehouse_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('interwarehouse_request_id')->index('iw_request_id_items');
            $table->integer('product_id')->index('product_id_iw_items');
            $table->integer('product_variant_id')->nullable()->index('product_variant_id_iw_items');
            $table->integer('unit_id')->nullable()->index('unit_id_iw_items');
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
        Schema::dropIfExists('interwarehouse_items');
    }
}
