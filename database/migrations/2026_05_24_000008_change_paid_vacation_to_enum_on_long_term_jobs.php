<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert boolean (0/1) values before changing column type
        DB::statement("UPDATE long_term_jobs SET paid_vacation = NULL WHERE paid_vacation = '0'");
        DB::statement("UPDATE long_term_jobs SET paid_vacation = 'vacation' WHERE paid_vacation = '1'");

        DB::statement("ALTER TABLE long_term_jobs MODIFY paid_vacation ENUM('vacation','holidays','vacation_and_holidays','none') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE long_term_jobs MODIFY paid_vacation TINYINT(1) NOT NULL DEFAULT 0');
    }
};
