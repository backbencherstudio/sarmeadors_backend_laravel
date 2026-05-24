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
        Schema::create('candidate_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('timezone')->default('UTC');
            $table->timestamps();
        });

        Schema::create('candidate_availability_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_availability_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('day_of_week'); // 0=Sunday, 6=Saturday
            $table->boolean('is_available')->default(false);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();

            $table->unique(['candidate_availability_id', 'day_of_week'], 'avail_day_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_availability_days');
        Schema::dropIfExists('candidate_availabilities');
    }
};
