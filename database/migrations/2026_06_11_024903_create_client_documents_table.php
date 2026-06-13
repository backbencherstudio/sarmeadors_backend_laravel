<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'signed'])->default('pending');
            $table->string('signature')->nullable();
            $table->longText('signed_content')->nullable();
            $table->string('signed_content_type')->nullable();
            $table->string('file_path')->nullable();
            $table->string('signed_ip', 45)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'document_template_id'], 'client_template_document_unique');
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_documents');
    }
};
