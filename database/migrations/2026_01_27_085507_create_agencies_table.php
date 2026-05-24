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
            $table->string('name', 100);
            $table->string('subdomain', 100)->unique();
            $table->string('subdomain_prefix', 50)->unique();
            $table->string('email', 100)->unique();
            $table->string('default_from_email')->nullable();
            $table->string('default_reply_email')->nullable();
            $table->string('mobile', 20)->nullable();
            $table->text('address', 500)->nullable();
            $table->string('logo', 255)->nullable();
            $table->integer('logo_height')->nullable();
            $table->string('favicon', 255)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('font', 255)->nullable();
            $table->string('tax_id', 255)->nullable();
            $table->string('language', 20)->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->text('stripe_publishable_key')->nullable();
            $table->text('stripe_secret_key')->nullable();
            $table->text('stripe_webhook_secret')->nullable();
            $table->integer('max_users')->default(20);
            $table->integer('max_clients')->default(100);
            $table->integer('max_candidates')->default(1000);
            $table->integer('total_users')->default(0);
            $table->integer('total_clients')->default(0);
            $table->integer('total_candidates')->default(0);
            $table->string('currency', 10)->default('USD');
            $table->json('countries')->nullable();
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
