<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInterwarehouseDeliveriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('interwarehouse_deliveries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->string('Ref', 192)->unique();
            $table->integer('interwarehouse_request_id')->index('iw_request_id_deliveries');
            $table->date('date');
            $table->integer('user_id')->index('user_id_iw_deliveries');
            $table->enum('statut', ['pending', 'shipped', 'received'])->default('pending');
            $table->integer('transfer_id')->nullable()->index('transfer_id_iw_deliveries');
            $table->text('notes')->nullable();
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
        Schema::dropIfExists('interwarehouse_deliveries');
    }
}
