<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_messages', function (Blueprint $table) {
            $table->foreignId('short_term_job_id')->nullable()->after('long_term_job_id')
                ->constrained('short_term_jobs')->cascadeOnDelete();

            $table->unsignedBigInteger('long_term_job_id')->nullable()->change();

            $table->index(['short_term_job_id', 'thread'], 'job_messages_short_term_job_id_thread_index');
        });
    }

    public function down(): void
    {
        Schema::table('job_messages', function (Blueprint $table) {
            $table->dropIndex('job_messages_short_term_job_id_thread_index');
            $table->dropForeign(['short_term_job_id']);
            $table->dropColumn('short_term_job_id');

            $table->unsignedBigInteger('long_term_job_id')->nullable(false)->change();
        });
    }
};
