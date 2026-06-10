<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('long_term_job_nanny_payments', function (Blueprint $table) {
            $table->string('invoice_number', 20)->nullable()->unique()->after('id');
            $table->string('payment_method', 100)->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('long_term_job_nanny_payments', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn(['invoice_number', 'payment_method']);
        });
    }
};
