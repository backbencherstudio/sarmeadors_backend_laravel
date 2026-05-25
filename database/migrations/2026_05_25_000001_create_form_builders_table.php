<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_builders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->enum('form_type', ['section', 'application'])->default('application');
            $table->enum('application_type', ['registration', 'job_posting'])->nullable();
            $table->enum('user_type', ['client', 'candidate'])->nullable();
            $table->enum('job_type', ['shift_job', 'long_term_job'])->nullable();
            $table->enum('select_type', ['add_user', 'long_term_job', 'schedule_interview_form', 'review_user'])->nullable();
            // Introduction block settings
            $table->string('logo')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('button_label')->nullable();
            $table->string('button_link')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_builders');
    }
};
