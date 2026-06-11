<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('long_term_job_interviews', function (Blueprint $table) {
            $table->text('reschedule_reason')->nullable()->after('special_note');
        });
    }

    public function down(): void
    {
        Schema::table('long_term_job_interviews', function (Blueprint $table) {
            $table->dropColumn('reschedule_reason');
        });
    }
};
