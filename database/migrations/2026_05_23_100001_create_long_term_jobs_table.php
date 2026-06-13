<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('long_term_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained('candidates')->nullOnDelete();

            // Basic info
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();

            // Address
            $table->string('job_address');
            $table->string('home_city');
            $table->string('home_province');
            $table->string('home_postal_code');
            $table->string('country');

            // Primary contact (First Parent)
            $table->string('primary_first_name')->nullable();
            $table->string('primary_last_name')->nullable();
            $table->string('primary_email')->nullable();
            $table->string('primary_phone')->nullable();

            // Secondary contact (Second Parent)
            $table->string('secondary_first_name')->nullable();
            $table->string('secondary_last_name')->nullable();
            $table->string('secondary_email')->nullable();
            $table->string('secondary_phone')->nullable();

            // Schedule window
            $table->date('start_date');
            $table->date('end_date')->nullable();

            // Compensation
            $table->decimal('compensation_amount', 10, 2)->default(0);
            $table->string('compensation_currency', 3)->default('cad');
            $table->enum('compensation_type', ['per_hour', 'per_week', 'per_month', 'flat'])->default('per_hour');

            // Requirements
            $table->boolean('bilingual_preference')->default(false);
            $table->boolean('special_needs')->default(false);
            $table->boolean('school_activity')->default(false);
            $table->boolean('has_housekeeper')->default(false);
            $table->boolean('live_in_required')->default(false);
            $table->enum('paid_vacation', ['vacation', 'holidays', 'vacation_and_holidays', 'none'])->nullable();
            $table->enum('accommodation_quality', ['excellent', 'not_bad', 'somewhat_comfortable', 'none'])->default('none');
            $table->enum('tips_policy', ['yes', 'no', 'sometimes', 'none'])->default('none');
            $table->boolean('room_with_wifi')->default(false);
            $table->enum('negotiable_salary', ['yes', 'no', 'open'])->default('open');
            $table->boolean('prepare_meals')->default(false);
            $table->boolean('travel_with_family')->default(false);
            $table->enum('spouse_work_from_home', ['yes_i_do', 'yes_spouse', 'yes_both', 'no'])->default('no');
            $table->enum('nanny_own_car', ['yes', 'no', 'other'])->default('no');

            // Additional information
            $table->text('family_schedule')->nullable();
            $table->text('household_tasks')->nullable();
            $table->text('family_philosophy')->nullable();
            $table->text('play_dates')->nullable();
            $table->text('addons')->nullable();
            $table->text('home_description')->nullable();
            $table->text('neighborhood_description')->nullable();
            $table->text('nanny_experience')->nullable();
            $table->text('payment_norms')->nullable();
            $table->text('consent_description')->nullable();
            $table->text('child_attachment')->nullable();

            // Status
            $table->enum('status', [
                'pending_approval', 'marketplace', 'running',
                'completed', 'cancelled', 'rejected',
            ])->default('pending_approval');

            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('broadcast_requested')->default(false);
            $table->timestamp('broadcast_requested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('long_term_jobs');
    }
};
