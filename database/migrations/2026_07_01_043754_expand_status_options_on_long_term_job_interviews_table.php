<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Interviews now move through an approval flow before they are confirmed:
     * requested (awaiting candidate) -> candidate_accepted (awaiting agency) ->
     * scheduled (agency confirmed with a link). "declined" ends a request the
     * candidate turned down. New interviews default to "requested".
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE long_term_job_interviews MODIFY status ENUM('requested', 'candidate_accepted', 'declined', 'scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'requested'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE long_term_job_interviews MODIFY status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled'");
    }
};
