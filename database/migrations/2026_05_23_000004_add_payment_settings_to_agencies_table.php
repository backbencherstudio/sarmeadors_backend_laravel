<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->boolean('short_term_payment_required')->default(false)->after('stripe_webhook_secret');
            $table->decimal('short_term_job_fee', 10, 2)->default(0)->after('short_term_payment_required');
            $table->string('short_term_job_fee_currency', 3)->default('usd')->after('short_term_job_fee');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn(['short_term_payment_required', 'short_term_job_fee', 'short_term_job_fee_currency']);
        });
    }
};
