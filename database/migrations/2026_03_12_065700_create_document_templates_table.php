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
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id');
            $table->string('name');
            $table->enum('user_type', ['client', 'candidate']);
            $table->foreignId('category_id')->nullable(); // From 'types' table

            // Step 1: Triggers
            $table->integer('trigger_status_id')->nullable();
            $table->integer('post_sign_status_id')->nullable();

            // Step 2: Content
            $table->enum('content_type', ['text', 'image'])->default('text');
            $table->longText('content')->nullable();
            $table->string('file_path')->nullable();

            // Step 3: Org Settings
            $table->string('org_signer_name')->nullable();
            $table->string('org_name')->nullable();
            $table->json('notification_emails')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
