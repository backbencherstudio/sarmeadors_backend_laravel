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
            $table->dropForeign('candidates_type_id_foreign');
            $table->dropForeign('candidates_location_id_foreign');
            $table->dropColumn(['type_id', 'location_id']);
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->json('type_id')->nullable()->after('image');
            $table->json('location_id')->nullable()->after('type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['type_id', 'location_id']);
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->foreignId('type_id')->after('image');
            $table->foreignId('location_id')->after('type_id');
        });
    }
};
