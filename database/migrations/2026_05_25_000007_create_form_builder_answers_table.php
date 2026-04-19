<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_builder_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_builder_submission_id')
                ->constrained('form_builder_submissions')
                ->cascadeOnDelete();
            $table->foreignId('form_builder_field_id')
                ->constrained('form_builder_fields')
                ->cascadeOnDelete();
            // stored as JSON to support all value types (string, array, object)
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_builder_answers');
    }
};
