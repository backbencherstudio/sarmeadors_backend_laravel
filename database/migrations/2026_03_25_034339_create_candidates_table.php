<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('mobile')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('street_address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('hours_per_week')->nullable();
            $table->string('bilingual')->nullable();
            $table->string('pay_range_per_hour')->nullable();
            $table->date('start_date')->nullable();
            $table->text('last_position_end_reason')->nullable();
            $table->string('reference_first_name')->nullable();
            $table->string('reference_last_name')->nullable();
            $table->string('reference_phone')->nullable();
            $table->string('reference_email')->nullable();
            $table->string('reference_relation')->nullable();
            $table->text('reference_description')->nullable();
            $table->boolean('interested_in_iowa')->nullable();
            $table->enum('years_of_experience', ['2-5', '5-10', '10+'])->nullable();
            $table->enum('commitment', ['long_term', 'short_term', 'temporary'])->nullable();
            $table->json('available_for')->nullable();
            $table->enum('drivers_license', ['dl_and_car', 'dl_only', 'neither'])->nullable();
            $table->enum('cpr_first_aid', ['yes', 'willing', 'no'])->nullable();
            $table->enum('vaccinations', ['yes', 'willing', 'no'])->nullable();
            $table->enum('ok_with_pets', ['dog', 'cat', 'neither'])->nullable();
            $table->enum('ok_with_travel', ['domestic', 'international', 'no_travel'])->nullable();
            $table->boolean('work_legally_in_us')->nullable();
            $table->boolean('comfortable_paid_legally')->nullable();
            $table->boolean('has_ssn')->nullable();
            $table->text('hear_about_us')->nullable();
            $table->string('image')->nullable();
            $table->json('type_id')->nullable();
            $table->json('location_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
