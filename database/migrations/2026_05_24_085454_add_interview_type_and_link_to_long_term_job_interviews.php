<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('long_term_job_interviews', function (Blueprint $table) {
            $table->enum('interview_type', ['in_person', 'zoom', 'google_meet'])->default('in_person')->after('special_note');
            $table->string('interview_link')->nullable()->after('interview_type');
            $table->text('description')->nullable()->after('interview_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('long_term_job_interviews', function (Blueprint $table) {
            $table->dropColumn(['interview_type', 'interview_link', 'description']);
        });
    }
};
