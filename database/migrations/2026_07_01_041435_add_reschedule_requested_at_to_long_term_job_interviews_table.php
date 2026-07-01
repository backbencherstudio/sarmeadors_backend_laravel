<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A client reschedule is a request the agency must confirm: this timestamp
     * marks a reschedule awaiting the agency to accept it and re-issue a link,
     * and is cleared once the agency confirms.
     */
    public function up(): void
    {
        Schema::table('long_term_job_interviews', function (Blueprint $table) {
            $table->timestamp('reschedule_requested_at')->nullable()->after('reschedule_reason');
        });
    }

    public function down(): void
    {
        Schema::table('long_term_job_interviews', function (Blueprint $table) {
            $table->dropColumn('reschedule_requested_at');
        });
    }
};
