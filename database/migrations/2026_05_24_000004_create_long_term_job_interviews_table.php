<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('long_term_job_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('long_term_job_id')->constrained('long_term_jobs')->cascadeOnDelete();
            $table->foreignId('long_term_job_application_id')->constrained('long_term_job_applications')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->date('scheduled_date');
            $table->time('available_from');
            $table->time('available_to');
            $table->text('special_note')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('long_term_job_interviews');
    }
};
