<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('status_id');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending')->after('stripe_customer_id');
            $table->boolean('is_active')->default(false)->after('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['stripe_customer_id', 'payment_status', 'is_active']);
        });
    }
};
