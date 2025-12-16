<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInterwarehousePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('interwarehouse_payments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->string('Ref', 192)->unique();
            $table->integer('interwarehouse_request_id')->index('iw_request_id_payments');
            $table->date('date');
            $table->float('montant', 10);
            $table->integer('payment_method_id')->index('payment_method_id_iw');
            $table->integer('account_id')->nullable()->index('account_id_iw_payments');
            $table->integer('user_id')->index('user_id_iw_payments');
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
        Schema::dropIfExists('interwarehouse_payments');
    }
}
