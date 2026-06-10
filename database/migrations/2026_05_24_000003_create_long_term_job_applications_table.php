<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('long_term_job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('long_term_job_id')->constrained('long_term_jobs')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->text('application_message')->nullable();
            $table->enum('status', ['pending', 'interviewed', 'hired', 'rejected'])->default('pending');
            $table->timestamps();

            $table->unique(['long_term_job_id', 'candidate_id'], 'ltja_application_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('long_term_job_applications');
    }
};
