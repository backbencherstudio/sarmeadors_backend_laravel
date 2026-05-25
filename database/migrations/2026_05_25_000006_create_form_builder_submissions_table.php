<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_builder_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_builder_id')->constrained()->cascadeOnDelete();
            // morphs to Client or Candidate after creation
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->index(['entity_type', 'entity_id']);
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_builder_submissions');
    }
};
