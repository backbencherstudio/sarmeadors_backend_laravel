<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The column was a boolean; map existing 0/1 values onto the new enum.
        DB::table('long_term_jobs')->where('paid_vacation', 0)->update(['paid_vacation' => null]);
        DB::table('long_term_jobs')->where('paid_vacation', 1)->update(['paid_vacation' => 'vacation']);

        Schema::table('long_term_jobs', function (Blueprint $table) {
            $table->enum('paid_vacation', ['vacation', 'holidays', 'vacation_and_holidays', 'none'])
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('long_term_jobs', function (Blueprint $table) {
            $table->boolean('paid_vacation')->default(false)->change();
        });
    }
};
