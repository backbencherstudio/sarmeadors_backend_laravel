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
        Schema::create('form_fields', function (Blueprint $table) {

            $table->id();

            $table->foreignId('form_id')->constrained()->cascadeOnDelete();

            $table->string('label');

            $table->string('name')->nullable();

            $table->string('type');
            // text, email, select, checkbox, radio, date, file, textarea

            $table->json('options')->nullable();

            $table->string('placeholder')->nullable();

            $table->boolean('is_required')->default(false);

            $table->integer('serial')->default(0);

            $table->integer('width')->default(12);

            $table->json('validation_rules')->nullable();

            $table->boolean('status')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
