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
        Schema::table('forms', function (Blueprint $table) {
            // registration | job_posting
            $table->string('application_type')->nullable()->after('entity');

            // client | candidate (registration only)
            $table->string('user_type')->nullable()->after('application_type');

            // long_term (job_posting only)
            $table->string('job_type')->nullable()->after('user_type');

            // full blocks -> sections -> fields builder definition
            $table->json('schema')->nullable()->after('job_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['application_type', 'user_type', 'job_type', 'schema']);
        });
    }
};
