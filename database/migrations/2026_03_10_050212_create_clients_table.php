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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();

            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('mobile')->nullable();
            $table->text('hear_about_us')->nullable();
            $table->string('image')->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending');

            $table->json('type_id')->nullable();
            $table->json('location_id')->nullable();
            $table->json('checklist_id')->nullable();
            $table->json('tag_id')->nullable();
            $table->json('status_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
