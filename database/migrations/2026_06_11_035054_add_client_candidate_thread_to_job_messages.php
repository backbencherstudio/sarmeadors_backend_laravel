<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_messages', function (Blueprint $table) {
            $table->enum('thread', ['client', 'candidate', 'client_candidate'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('job_messages', function (Blueprint $table) {
            $table->enum('thread', ['client', 'candidate'])->change();
        });
    }
};
