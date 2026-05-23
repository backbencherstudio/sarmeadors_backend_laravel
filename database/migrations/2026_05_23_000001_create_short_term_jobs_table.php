<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_term_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();

            $table->string('job_address')->nullable();
            $table->string('home_city')->nullable();
            $table->string('home_province')->nullable();
            $table->string('home_postal_code')->nullable();
            $table->string('country')->nullable();

            $table->decimal('compensation_amount', 10, 2)->nullable();
            $table->string('compensation_currency', 3)->default('usd');
            $table->enum('compensation_type', ['per_hour', 'per_day', 'per_week', 'flat'])->default('per_hour');

            $table->enum('status', ['draft', 'pending_payment', 'pending_approval', 'marketplace', 'running', 'completed', 'cancelled', 'rejected'])->default('draft');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_term_jobs');
    }
};
