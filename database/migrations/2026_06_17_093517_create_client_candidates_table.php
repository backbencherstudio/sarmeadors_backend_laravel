<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('status')->default('interested');
            $table->text('notes')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_candidates');
    }
};
