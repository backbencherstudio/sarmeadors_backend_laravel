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
            // slug is unique per agency, not globally, so every agency can
            // create e.g. a "Client Registration" form with the same slug.
            $table->dropUnique('forms_slug_unique');
            $table->unique(['agency_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropUnique(['agency_id', 'slug']);
            $table->unique('slug');
        });
    }
};
