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
        Schema::table('form_submissions', function (Blueprint $table) {
            // client | candidate | long_term_job
            $table->string('entity_type')->nullable()->after('entity_id');

            // full answers payload keyed by field name
            $table->json('data')->nullable()->after('entity_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropColumn(['entity_type', 'data']);
        });
    }
};
