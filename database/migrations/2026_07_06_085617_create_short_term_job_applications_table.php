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
        Schema::create('short_term_job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('short_term_job_id')->constrained('short_term_jobs')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->text('application_message')->nullable();
            $table->enum('status', ['pending', 'interviewed', 'hired', 'rejected'])->default('pending');
            $table->timestamps();

            $table->unique(['short_term_job_id', 'candidate_id'], 'stja_application_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('short_term_job_applications');
    }
};
