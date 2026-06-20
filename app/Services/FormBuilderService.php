<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Client;
use App\Models\LongTermJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FormBuilderService
{
    /**
     * Target entity keys mapped to their Eloquent models.
     *
     * @var array<string, class-string<Model>>
     */
    private const ENTITY_MODELS = [
        'client' => Client::class,
        'candidate' => Candidate::class,
        'long_term_job' => LongTermJob::class,
    ];

    /**
     * Field types the builder accepts, mirroring the Application Builder palette.
     *
     * @var array<int, string>
     */
    private const FIELD_TYPES = [
        // Basic fields
        'text_box', 'text_area', 'email', 'number', 'rating', 'rating_group',
        'password', 'rich_text_editor', 'language_input',
        // Basic choice inputs
        'dropdown', 'radio', 'radio_table', 'single_checkbox',
        'multi_select_checkbox', 'checkbox_table',
        // Time & date
        'time_picker', 'date_picker', 'month_picker', 'year_picker',
        'date_time_picker', 'multi_date_picker', 'time_availability', 'booking_section',
        // File & media inputs
        'file_upload', 'list_files', 'video_recorder', 'video_file',
        'file_with_additional_information',
        // Signing & agreements
        'signature', 'signature_file',
        // Layout & display
        'label', 'separator', 'html', 'display_value_by_expression',
        // Advanced inputs
        'address_autocomplete', 'phone_country_code', 'evaluation',
        'placement_job_selection', 'salary_range', 'payment',
        'stripe_subscription_selection',
    ];

    /**
     * The list of field types the builder allows.
     *
     * @return array<int, string>
     */
    public function allowedFieldTypes(): array
    {
        return self::FIELD_TYPES;
    }

    /**
     * Resolve the target entity key from the builder's selected types.
     */
    public function resolveEntity(?string $applicationType, ?string $userType, ?string $jobType): ?string
    {
        if ($applicationType === 'registration') {
            return in_array($userType, ['client', 'candidate'], true) ? $userType : null;
        }

        if ($applicationType === 'job_posting') {
            return $jobType === 'long_term' ? 'long_term_job' : null;
        }

        return null;
    }

    /**
     * Resolve the Eloquent model class for an entity key.
     *
     * @return class-string<Model>|null
     */
    public function modelClassFor(string $entity): ?string
    {
        return self::ENTITY_MODELS[$entity] ?? null;
    }

    /**
     * Normalize an incoming builder definition into a predictable
     * blocks -> sections -> fields tree with stable keys, machine names and serials.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function normalizeSchema(array $schema): array
    {
        $blocks = [];

        foreach (array_values($schema['blocks'] ?? []) as $blockIndex => $block) {
            $sections = [];

            foreach (array_values($block['sections'] ?? []) as $sectionIndex => $section) {
                $fields = [];

                foreach (array_values($section['fields'] ?? []) as $fieldIndex => $field) {
                    $label = $field['label'] ?? 'Untitled Field';

                    $fields[] = [
                        'key' => $field['key'] ?? (string) Str::uuid(),
                        'type' => $field['type'] ?? 'text_box',
                        'label' => $label,
                        'name' => $field['name'] ?? Str::slug($label, '_'),
                        'profile_label' => $field['profile_label'] ?? null,
                        'placeholder' => $field['placeholder'] ?? null,
                        'is_required' => (bool) ($field['is_required'] ?? false),
                        'width' => (int) ($field['width'] ?? 12),
                        'serial' => $fieldIndex + 1,
                        'options' => $field['options'] ?? null,
                        'validation_rules' => $field['validation_rules'] ?? null,
                        'config' => $field['config'] ?? null,
                    ];
                }

                $sections[] = [
                    'key' => $section['key'] ?? (string) Str::uuid(),
                    'name' => $section['name'] ?? 'Untitled Section',
                    'serial' => $sectionIndex + 1,
                    'fields' => $fields,
                ];
            }

            $blocks[] = [
                'key' => $block['key'] ?? (string) Str::uuid(),
                'name' => $block['name'] ?? 'Untitled Block',
                'description' => $block['description'] ?? null,
                'serial' => $blockIndex + 1,
                'sections' => $sections,
            ];
        }

        $schema['blocks'] = $blocks;

        return $schema;
    }

    /**
     * Flatten every field across the blocks/sections tree.
     *
     * @param  array<string, mixed>  $schema
     * @return array<int, array<string, mixed>>
     */
    public function flattenFields(array $schema): array
    {
        $fields = [];

        foreach ($schema['blocks'] ?? [] as $block) {
            foreach ($block['sections'] ?? [] as $section) {
                foreach ($section['fields'] ?? [] as $field) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Build Laravel validation rules for an "answers" payload from the schema.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, string>
     */
    public function validationRules(array $schema): array
    {
        $rules = [];

        foreach ($this->flattenFields($schema) as $field) {
            $name = $field['name'] ?? null;

            if (! $name) {
                continue;
            }

            $fieldRules = [($field['is_required'] ?? false) ? 'required' : 'nullable'];

            switch ($field['type'] ?? null) {
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'date_picker':
                case 'month_picker':
                case 'year_picker':
                    $fieldRules[] = 'date';
                    break;
                case 'salary_range':
                    $fieldRules[] = 'array';
                    break;
                case 'multi_select_checkbox':
                case 'checkbox_table':
                case 'radio_table':
                    $fieldRules[] = 'array';
                    break;
            }

            $custom = $field['validation_rules'] ?? null;

            if (is_string($custom) && $custom !== '') {
                $fieldRules = array_merge($fieldRules, explode('|', $custom));
            } elseif (is_array($custom)) {
                $fieldRules = array_merge($fieldRules, $custom);
            }

            $rules["answers.{$name}"] = implode('|', array_values(array_unique($fieldRules)));
        }

        return $rules;
    }

    /**
     * Keep only the answers whose keys map to fillable columns of the target model.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function mapAnswersToColumns(string $modelClass, array $answers): array
    {
        $fillable = (new $modelClass)->getFillable();

        return array_intersect_key($answers, array_flip($fillable));
    }

    /**
     * Fallback values for NOT NULL columns without a database default, so a
     * dynamic submission stays insertable even when the builder omits them.
     *
     * @return array<string, mixed>
     */
    public function requiredColumnDefaults(string $entity): array
    {
        return match ($entity) {
            'long_term_job' => [
                'title' => '',
                'job_address' => '',
                'home_city' => '',
                'home_province' => '',
                'home_postal_code' => '',
                'country' => '',
                'start_date' => now()->toDateString(),
            ],
            default => [],
        };
    }
}
