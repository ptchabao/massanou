<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInterwarehouseRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('interwarehouse_requests', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id', true);
            $table->string('Ref', 192)->unique();
            $table->date('date');
            $table->integer('requester_warehouse_id')->index('requester_warehouse_id_iw');
            $table->integer('supplier_warehouse_id')->index('supplier_warehouse_id_iw');
            $table->integer('user_id')->index('user_id_iw');
            $table->enum('statut', [
                'draft',           // Brouillon
                'sent',            // Envoyée
                'proforma_sent',   // Proforma reçu
                'rejected',        // Refusée
                'validated',       // Validée
                'in_payment',      // En cours de paiement
                'paid',            // Soldée
                'delivered',       // Livrée
                'closed'           // Clôturée
            ])->default('draft');
            $table->float('items', 10)->default(0);
            $table->float('tax_rate', 10)->nullable()->default(0);
            $table->float('TaxNet', 10)->nullable()->default(0);
            $table->float('discount', 10)->nullable()->default(0);
            $table->string('discount_type', 50)->nullable()->default('fixed');
            $table->float('shipping', 10)->nullable()->default(0);
            $table->float('GrandTotal', 10)->default(0);
            $table->float('paid_amount', 10)->default(0);
            $table->float('payment_threshold', 10)->default(100.00);
            $table->date('desired_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('proforma_notes')->nullable();
            $table->integer('proforma_by')->nullable()->index('proforma_by_iw');
            $table->timestamp('proforma_at')->nullable();
            $table->integer('validated_by')->nullable()->index('validated_by_iw');
            $table->timestamp('validated_at')->nullable();
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
        Schema::dropIfExists('interwarehouse_requests');
    }
}
