<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('required_key')->nullable();
            $table->enum('category', ['agreement', 'required', 'additional']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->enum('status', ['pending', 'uploaded', 'signed'])->default('pending');
            $table->string('signature')->nullable();
            $table->longText('signed_content')->nullable();
            $table->string('signed_content_type')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['candidate_id', 'document_template_id', 'category'], 'candidate_template_document_unique');
            $table->unique(['candidate_id', 'required_key', 'category'], 'candidate_required_document_unique');
            $table->index(['candidate_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_documents');
    }
};
