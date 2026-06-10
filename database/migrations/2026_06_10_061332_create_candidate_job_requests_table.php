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
        Schema::create('candidate_job_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('short_term_job_id')->nullable()->constrained('short_term_jobs')->cascadeOnDelete();
            $table->foreignId('long_term_job_id')->nullable()->constrained('long_term_jobs')->cascadeOnDelete();
            $table->foreignId('long_term_job_application_id')->nullable()->constrained('long_term_job_applications')->nullOnDelete();
            $table->enum('job_type', ['short_term', 'long_term']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['candidate_id', 'agency_id', 'status']);
            $table->index(['client_id', 'agency_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_job_requests');
    }
};
