<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->json('admin_view_ids')->nullable()->after('notification_emails');
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn('admin_view_ids');
        });
    }
};
