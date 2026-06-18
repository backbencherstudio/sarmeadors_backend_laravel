<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Interviews requested straight from a candidate's profile are not yet tied
     * to a posted job or application, so the job/application links become
     * optional and the requesting client is stored directly.
     */
    public function up(): void
    {
        Schema::table('long_term_job_interviews', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('candidate_id')->constrained('clients')->nullOnDelete();
        });

        Schema::table('long_term_job_interviews', function (Blueprint $table) {
            $table->foreignId('long_term_job_id')->nullable()->change();
            $table->foreignId('long_term_job_application_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('long_term_job_interviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
