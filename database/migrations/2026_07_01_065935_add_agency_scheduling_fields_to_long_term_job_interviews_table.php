<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The agency can schedule interviews from scratch and manage them, so the
     * table gains an event title/timezone/location, a cancellation reason, the
     * assigned agency admins, and the client's *proposed* reschedule slot (kept
     * separate so the confirmed schedule is not overwritten until the agency
     * approves it).
     */
    public function up(): void
    {
        Schema::table('long_term_job_interviews', function (Blueprint $table) {
            $table->string('title')->nullable()->after('client_id');
            $table->string('timezone')->nullable()->after('available_to');
            $table->string('location')->nullable()->after('timezone');
            $table->date('reschedule_date')->nullable()->after('reschedule_requested_at');
            $table->time('reschedule_from')->nullable()->after('reschedule_date');
            $table->time('reschedule_to')->nullable()->after('reschedule_from');
            $table->text('cancellation_reason')->nullable()->after('reschedule_to');
            $table->json('assigned_to')->nullable()->after('cancellation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('long_term_job_interviews', function (Blueprint $table) {
            $table->dropColumn([
                'title',
                'timezone',
                'location',
                'reschedule_date',
                'reschedule_from',
                'reschedule_to',
                'cancellation_reason',
                'assigned_to',
            ]);
        });
    }
};
