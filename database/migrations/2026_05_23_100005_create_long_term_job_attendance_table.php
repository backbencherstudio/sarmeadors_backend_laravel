<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('long_term_job_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('long_term_job_id')->constrained('long_term_jobs')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->boolean('is_absent')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['long_term_job_id', 'candidate_id', 'date'], 'ltja_job_candidate_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('long_term_job_attendance');
    }
};
