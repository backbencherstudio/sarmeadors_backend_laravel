<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            // Personal info
            $table->date('date_of_birth')->nullable()->after('mobile');
            $table->string('nationality')->nullable()->after('date_of_birth');

            // Address
            $table->string('street_address')->nullable()->after('nationality');
            $table->string('city')->nullable()->after('street_address');
            $table->string('province')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('province');
            $table->string('country')->nullable()->after('postal_code');

            // Professional info
            $table->string('hours_per_week')->nullable()->after('country');
            $table->string('bilingual')->nullable()->after('hours_per_week');
            $table->string('pay_range_per_hour')->nullable()->after('bilingual');
            $table->date('start_date')->nullable()->after('pay_range_per_hour');
            $table->text('last_position_end_reason')->nullable()->after('start_date');

            // Reference
            $table->string('reference_first_name')->nullable()->after('last_position_end_reason');
            $table->string('reference_last_name')->nullable()->after('reference_first_name');
            $table->string('reference_phone')->nullable()->after('reference_last_name');
            $table->string('reference_email')->nullable()->after('reference_phone');
            $table->string('reference_relation')->nullable()->after('reference_email');
            $table->text('reference_description')->nullable()->after('reference_relation');

            // Additional information
            $table->boolean('interested_in_iowa')->nullable()->after('reference_description');
            $table->enum('years_of_experience', ['2-5', '5-10', '10+'])->nullable()->after('interested_in_iowa');
            $table->enum('commitment', ['long_term', 'short_term', 'temporary'])->nullable()->after('years_of_experience');
            $table->json('available_for')->nullable()->after('commitment');
            $table->enum('drivers_license', ['dl_and_car', 'dl_only', 'neither'])->nullable()->after('available_for');
            $table->enum('cpr_first_aid', ['yes', 'willing', 'no'])->nullable()->after('drivers_license');
            $table->enum('vaccinations', ['yes', 'willing', 'no'])->nullable()->after('cpr_first_aid');
            $table->enum('ok_with_pets', ['dog', 'cat', 'neither'])->nullable()->after('vaccinations');
            $table->enum('ok_with_travel', ['domestic', 'international', 'no_travel'])->nullable()->after('ok_with_pets');
            $table->boolean('work_legally_in_us')->nullable()->after('ok_with_travel');
            $table->boolean('comfortable_paid_legally')->nullable()->after('work_legally_in_us');
            $table->boolean('has_ssn')->nullable()->after('comfortable_paid_legally');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn([
                'date_of_birth', 'nationality', 'street_address', 'city', 'province',
                'postal_code', 'country', 'hours_per_week', 'bilingual', 'pay_range_per_hour',
                'start_date', 'last_position_end_reason', 'reference_first_name', 'reference_last_name',
                'reference_phone', 'reference_email', 'reference_relation', 'reference_description',
                'interested_in_iowa', 'years_of_experience', 'commitment', 'available_for',
                'drivers_license', 'cpr_first_aid', 'vaccinations', 'ok_with_pets',
                'ok_with_travel', 'work_legally_in_us', 'comfortable_paid_legally', 'has_ssn',
            ]);
        });
    }
};
