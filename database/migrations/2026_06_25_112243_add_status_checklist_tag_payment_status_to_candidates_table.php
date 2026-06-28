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
        Schema::table('candidates', function (Blueprint $table) {
            $table->json('checklist_id')->nullable()->after('location_id');
            $table->json('tag_id')->nullable()->after('checklist_id');
            $table->json('status_id')->nullable()->after('tag_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['checklist_id', 'tag_id', 'status_id']);
        });
    }
};
