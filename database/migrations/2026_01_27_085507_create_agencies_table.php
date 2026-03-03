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
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('subdomain', 100)->unique();
            $table->string('subdomain_prefix', 50)->unique();
            $table->string('email',100)->nullable();
            $table->string('phone',20)->nullable();
            $table->string('logo',255)->nullable();
            $table->string('primary_color',50)->nullable();
            $table->string('secondary_color',50)->nullable();
            $table->string('favicon',255)->nullable();
            $table->enum('status', ['active','inactive','suspended'])->default('active');
            $table->string('stripe_account_id')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agencies');
    }
};
