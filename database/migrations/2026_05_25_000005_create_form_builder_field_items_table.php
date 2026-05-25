<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Used for dropdown, radio, multi_select_checkbox, rating_group items,
    // radio_table / checkbox_table columns & rows, stripe_subscription plans, etc.
    public function up(): void
    {
        Schema::create('form_builder_field_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_builder_field_id')
                ->constrained('form_builder_fields')
                ->cascadeOnDelete();
            // 'option' | 'column' | 'row' | 'plan' | 'address_part'
            $table->string('item_type')->default('option');
            $table->string('label');
            $table->string('value')->nullable();
            $table->json('meta')->nullable(); // e.g. plan features, address toggle
            $table->integer('serial')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_builder_field_items');
    }
};
