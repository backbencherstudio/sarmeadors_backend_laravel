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
        Schema::create('form_field_values', function (Blueprint $table) {

            $table->id();

            $table->foreignId('submission_id')
                ->constrained('form_submissions')
                ->cascadeOnDelete();

            $table->foreignId('form_field_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->longText('value')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_field_values');
    }
};
