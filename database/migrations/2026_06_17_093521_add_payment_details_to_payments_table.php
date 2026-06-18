<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist the agency-fee payment screen fields (cardholder name, billing
     * address, additional note, tax). Raw card numbers / CVC are never stored
     * — Stripe tokenises those and we only keep references.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('client_payment_method_id')->nullable()->after('short_term_job_id')->constrained('client_payment_methods')->nullOnDelete();
            $table->string('cardholder_name')->nullable()->after('stripe_payment_intent_id');
            $table->string('billing_country', 100)->nullable()->after('cardholder_name');
            $table->string('billing_postal_code', 20)->nullable()->after('billing_country');
            $table->decimal('tax', 10, 2)->default(0)->after('amount');
            $table->text('note')->nullable()->after('tax');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_payment_method_id');
            $table->dropColumn(['cardholder_name', 'billing_country', 'billing_postal_code', 'tax', 'note']);
        });
    }
};
