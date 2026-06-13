<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_documents', function (Blueprint $table) {
            $table->longText('signed_content')->nullable()->after('signature');
            $table->string('signed_content_type')->nullable()->after('signed_content');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_documents', function (Blueprint $table) {
            $table->dropColumn(['signed_content', 'signed_content_type']);
        });
    }
};
