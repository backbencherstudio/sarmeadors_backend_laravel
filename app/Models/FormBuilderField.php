<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormBuilderField extends Model
{
    protected $fillable = [
        'form_builder_section_id',
        'field_type',
        'field_label',
        'profile_label',
        'placeholder',
        'is_mandatory',
        'serial',
        'settings',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'settings'     => 'array',
    ];

    // All field types grouped by category
    public const FIELD_TYPES = [
        'basic'   => [
            'text_box', 'text_area', 'rating', 'rating_group',
            'password', 'rich_text_editor', 'language_input',
        ],
        'choice'  => [
            'dropdown', 'radio', 'radio_table',
            'single_checkbox', 'multi_select_checkbox', 'checkbox_table',
        ],
        'datetime' => [
            'time_picker', 'date_picker', 'month_picker', 'year_picker',
            'date_time_picker', 'multi_date_picker', 'time_availability',
            'booking_section',
        ],
        'media'   => [
            'file_upload', 'list_files', 'video_recorder',
            'file_with_additional_info',
        ],
        'signing' => ['signature', 'signature_field'],
        'layout'  => [
            'label', 'separator', 'html',
            'section', 'preset_section', 'preset_fields',
            'html_selection', 'display_value_by_expression',
        ],
        'advanced' => [
            'address_autocomplete', 'phone_country_code',
            'salary_range', 'evaluation', 'placement_job_selection',
            'payment', 'stripe_subscription',
        ],
    ];

    // Field types that have selectable items
    public const ITEM_BASED_TYPES = [
        'dropdown', 'radio', 'multi_select_checkbox',
        'rating_group', 'radio_table', 'checkbox_table',
        'stripe_subscription', 'address_autocomplete',
        'preset_section', 'preset_fields', 'placement_job_selection',
    ];

    public function section()
    {
        return $this->belongsTo(FormBuilderSection::class, 'form_builder_section_id');
    }

    public function items()
    {
        return $this->hasMany(FormBuilderFieldItem::class, 'form_builder_field_id')->orderBy('serial');
    }

    public function answers()
    {
        return $this->hasMany(FormBuilderAnswer::class, 'form_builder_field_id');
    }
}
