<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('short_term_jobs', function (Blueprint $table) {
            $table->foreignId('candidate_id')->nullable()->constrained('candidates')->nullOnDelete()->after('client_id');
            $table->text('cancellation_reason')->nullable()->after('rejection_reason');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
            $table->boolean('broadcast_requested')->default(false)->after('cancelled_at');
            $table->timestamp('broadcast_requested_at')->nullable()->after('broadcast_requested');
        });
    }

    public function down(): void
    {
        Schema::table('short_term_jobs', function (Blueprint $table) {
            $table->dropForeign(['candidate_id']);
            $table->dropColumn(['candidate_id', 'cancellation_reason', 'cancelled_at', 'broadcast_requested', 'broadcast_requested_at']);
        });
    }
};
