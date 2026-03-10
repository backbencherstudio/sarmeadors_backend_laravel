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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_type_id')->constrained('event_types')->cascadeOnDelete();

            $table->string('event_title');
            $table->date('event_date');
            $table->time('event_time');
            $table->string('event_time_zone')->default('UTC');

            // $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            // $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('candidate_id')->nullable();

            $table->string('location')->nullable();
            $table->string('interview_link')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_email_notify')->default(0);

            $table->foreignId('assign_user_id')->nullable()->constrained('users')->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
