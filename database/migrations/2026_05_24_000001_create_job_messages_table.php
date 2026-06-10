<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('long_term_job_id')->constrained('long_term_jobs')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            // thread: which conversation thread this message belongs to
            // 'client'    = agency <-> client
            // 'candidate' = agency <-> candidate
            $table->enum('thread', ['client', 'candidate']);
            $table->text('message')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['long_term_job_id', 'thread']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_messages');
    }
};
