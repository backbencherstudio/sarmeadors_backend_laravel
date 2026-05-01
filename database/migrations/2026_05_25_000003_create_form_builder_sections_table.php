<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_builder_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_builder_block_id')
                ->constrained('form_builder_blocks')
                ->cascadeOnDelete();
            $table->string('name')->default('Untitled Section');
            $table->integer('serial')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_builder_sections');
    }
};
