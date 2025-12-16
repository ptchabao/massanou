<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalOrdersTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('internal_orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->integer('user_id')->index('user_id_internal_orders');
            $table->string('Ref', 192);
            $table->date('date');
            $table->integer('from_warehouse_id')->index('from_warehouse_id_internal_orders');
            $table->integer('to_warehouse_id')->index('to_warehouse_id_internal_orders');
            $table->float('items', 10)->default(0);
            $table->float('tax_rate', 10)->nullable()->default(0);
            $table->float('TaxNet', 10)->nullable()->default(0);
            $table->float('discount', 10)->nullable()->default(0);
            $table->string('discount_type', 50)->nullable()->default('fixed'); // 'fixed' or 'percent'
            $table->float('shipping', 10)->nullable()->default(0);
            $table->float('GrandTotal', 10)->default(0);
            $table->string('statut', 192); // pending, approved, rejected, shipped, received
            $table->integer('approved_by')->nullable()->index('approved_by_internal_orders');
            $table->timestamp('approved_at')->nullable();
            $table->integer('transfer_id')->nullable()->index('transfer_id_internal_orders');
            $table->text('notes')->nullable();
            $table->timestamps(6);
            $table->softDeletes();
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('internal_orders');
    }

}
