<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cards a client chose to "securely store for future bookings". Only the
     * Stripe payment-method reference plus safe display details (brand, last4,
     * expiry) are stored — never the full card number or CVC.
     */
    public function up(): void
    {
        Schema::create('client_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('stripe_payment_method_id');
            $table->string('cardholder_name')->nullable();
            $table->string('brand')->nullable();
            $table->string('last4', 4)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['client_id', 'stripe_payment_method_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_payment_methods');
    }
};
