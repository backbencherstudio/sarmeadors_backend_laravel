<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('long_term_job_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('long_term_job_id')->constrained('long_term_jobs')->cascadeOnDelete();
            // 0 = Sunday ... 6 = Saturday
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('long_term_job_schedules');
    }
};
