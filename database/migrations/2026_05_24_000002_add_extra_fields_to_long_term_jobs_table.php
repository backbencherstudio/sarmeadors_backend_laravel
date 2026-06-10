<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('long_term_jobs', function (Blueprint $table) {
            // Primary contact (First Parent)
            $table->string('primary_first_name')->nullable()->after('country');
            $table->string('primary_last_name')->nullable()->after('primary_first_name');
            $table->string('primary_email')->nullable()->after('primary_last_name');
            $table->string('primary_phone')->nullable()->after('primary_email');

            // Secondary contact (Second Parent)
            $table->string('secondary_first_name')->nullable()->after('primary_phone');
            $table->string('secondary_last_name')->nullable()->after('secondary_first_name');
            $table->string('secondary_email')->nullable()->after('secondary_last_name');
            $table->string('secondary_phone')->nullable()->after('secondary_email');

            // Additional requirement fields
            $table->boolean('prepare_meals')->default(false)->after('has_housekeeper');
            $table->boolean('travel_with_family')->default(false)->after('prepare_meals');
            $table->enum('spouse_work_from_home', ['yes_i_do', 'yes_spouse', 'yes_both', 'no'])->default('no')->after('travel_with_family');
            $table->enum('nanny_own_car', ['yes', 'no', 'other'])->default('no')->after('spouse_work_from_home');

            // Cancellation
            $table->text('cancellation_reason')->nullable()->after('rejection_reason');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');

            // Broadcast request
            $table->boolean('broadcast_requested')->default(false)->after('cancelled_at');
            $table->timestamp('broadcast_requested_at')->nullable()->after('broadcast_requested');
        });
    }

    public function down(): void
    {
        Schema::table('long_term_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'primary_first_name', 'primary_last_name', 'primary_email', 'primary_phone',
                'secondary_first_name', 'secondary_last_name', 'secondary_email', 'secondary_phone',
                'prepare_meals', 'travel_with_family', 'spouse_work_from_home', 'nanny_own_car',
                'cancellation_reason', 'cancelled_at', 'broadcast_requested', 'broadcast_requested_at',
            ]);
        });
    }
};
