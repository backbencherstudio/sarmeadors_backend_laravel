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
        Schema::table('agencies', function (Blueprint $table) {
            if (Schema::hasColumn('agencies', 'stripe_account_id')) {
                $table->dropColumn('stripe_account_id');
            }

            // Add agency's own Stripe keys (encrypted)
            $table->text('stripe_publishable_key')->nullable()->after('status');
            $table->text('stripe_secret_key')->nullable()->after('stripe_publishable_key');
            $table->text('stripe_webhook_secret')->nullable()->after('stripe_secret_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn(['stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret']);
            $table->string('stripe_account_id')->nullable()->after('status');
        });
    }
};
