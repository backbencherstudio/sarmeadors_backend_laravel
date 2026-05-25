<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * field_type values:
         *
         * Basic Fields:
         *   text_box, text_area, rating, rating_group, password,
         *   rich_text_editor, language_input
         *
         * Basic Choice Inputs:
         *   dropdown, radio, radio_table, single_checkbox,
         *   multi_select_checkbox, checkbox_table
         *
         * Time & Date:
         *   time_picker, date_picker, month_picker, year_picker,
         *   date_time_picker, multi_date_picker, time_availability,
         *   booking_section
         *
         * File & Media:
         *   file_upload, list_files, video_recorder, file_with_additional_info
         *
         * Signing & Agreements:
         *   signature, signature_field
         *
         * Layout & Display:
         *   label, separator, html, address_autocomplete,
         *   phone_country_code, salary_range
         *
         * Payment:
         *   payment, stripe_subscription
         */
        Schema::create('form_builder_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_builder_section_id')
                ->constrained('form_builder_sections')
                ->cascadeOnDelete();
            $table->string('field_type');
            $table->string('field_label');
            $table->string('profile_label')->nullable();
            $table->string('placeholder')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->integer('serial')->default(0);
            // stores type-specific config (max_rating, columns, rows, layout, etc.)
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_builder_fields');
    }
};
