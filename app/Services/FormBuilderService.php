<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Client;
use App\Models\LongTermJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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
     * The standard fields every entity's registration form collects in
     * addition to whatever custom fields the agency added in the builder.
     * These aren't part of the stored `schema` - they're always the same
     * columns on the target model, so the frontend can render them without
     * the agency having to define them.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private const BASE_FIELDS = [
        'client' => [
            ['name' => 'first_name', 'type' => 'text_box', 'label' => 'First Name', 'is_required' => true],
            ['name' => 'last_name', 'type' => 'text_box', 'label' => 'Last Name', 'is_required' => false],
            ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'is_required' => true],
            ['name' => 'password', 'type' => 'password', 'label' => 'Password', 'is_required' => false],
            ['name' => 'image', 'type' => 'file_upload', 'label' => 'Profile Image', 'is_required' => false],
            ['name' => 'type_id', 'type' => 'multi_select_checkbox', 'label' => 'User Type', 'is_required' => false],
            ['name' => 'location_id', 'type' => 'multi_select_checkbox', 'label' => 'Location', 'is_required' => false],
            ['name' => 'mobile', 'type' => 'text_box', 'label' => 'Phone Number', 'is_required' => false],
            ['name' => 'nationality', 'type' => 'text_box', 'label' => 'Nationality', 'is_required' => false],
            ['name' => 'street_address', 'type' => 'text_box', 'label' => 'Street Address', 'is_required' => false],
            ['name' => 'city', 'type' => 'text_box', 'label' => 'City', 'is_required' => false],
            ['name' => 'province', 'type' => 'text_box', 'label' => 'Province/State', 'is_required' => false],
            ['name' => 'postal_code', 'type' => 'text_box', 'label' => 'Postal Code', 'is_required' => false],
            ['name' => 'country', 'type' => 'text_box', 'label' => 'Country', 'is_required' => false],
        ],
        'candidate' => [
            ['name' => 'first_name', 'type' => 'text_box', 'label' => 'First Name', 'is_required' => true],
            ['name' => 'last_name', 'type' => 'text_box', 'label' => 'Last Name', 'is_required' => false],
            ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'is_required' => true],
            ['name' => 'password', 'type' => 'password', 'label' => 'Password', 'is_required' => false],
            ['name' => 'image', 'type' => 'file_upload', 'label' => 'Profile Image', 'is_required' => false],
            ['name' => 'type_id', 'type' => 'multi_select_checkbox', 'label' => 'Candidate Type', 'is_required' => false],
            ['name' => 'location_id', 'type' => 'multi_select_checkbox', 'label' => 'Location', 'is_required' => false],
            ['name' => 'mobile', 'type' => 'text_box', 'label' => 'Phone Number', 'is_required' => false],
            ['name' => 'nationality', 'type' => 'text_box', 'label' => 'Nationality', 'is_required' => false],
            ['name' => 'street_address', 'type' => 'text_box', 'label' => 'Street Address', 'is_required' => false],
            ['name' => 'city', 'type' => 'text_box', 'label' => 'City', 'is_required' => false],
            ['name' => 'province', 'type' => 'text_box', 'label' => 'Province/State', 'is_required' => false],
            ['name' => 'postal_code', 'type' => 'text_box', 'label' => 'Postal Code', 'is_required' => false],
            ['name' => 'country', 'type' => 'text_box', 'label' => 'Country', 'is_required' => false],
        ],
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
     * The standard, always-present fields for an entity's registration form.
     *
     * @return array<int, array<string, mixed>>
     */
    public function baseFields(string $entity): array
    {
        return self::BASE_FIELDS[$entity] ?? [];
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
     * Move the field with the given key to 1-based position $targetSerial
     * within its own section, shifting the fields in between and
     * renumbering the whole schema to match. Out-of-range positions clamp
     * to the start/end of the section instead of failing.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>|null null if no field with that key exists in the schema
     */
    public function moveField(array $schema, string $fieldKey, int $targetSerial): ?array
    {
        foreach ($schema['blocks'] ?? [] as $blockIndex => $block) {
            foreach ($block['sections'] ?? [] as $sectionIndex => $section) {
                $fields = array_values($section['fields'] ?? []);

                $currentIndex = null;

                foreach ($fields as $i => $field) {
                    if (($field['key'] ?? null) === $fieldKey) {
                        $currentIndex = $i;
                        break;
                    }
                }

                if ($currentIndex === null) {
                    continue;
                }

                $field = $fields[$currentIndex];
                unset($fields[$currentIndex]);
                $fields = array_values($fields);

                $insertAt = max(0, min($targetSerial - 1, count($fields)));
                array_splice($fields, $insertAt, 0, [$field]);

                $schema['blocks'][$blockIndex]['sections'][$sectionIndex]['fields'] = $fields;

                return $this->normalizeSchema($schema);
            }
        }

        return null;
    }

    /**
     * Move the block with the given key to 1-based position $targetSerial
     * within the schema, shifting the blocks in between and renumbering the
     * whole schema to match. Out-of-range positions clamp to the start/end
     * of the schema instead of failing.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>|null null if no block with that key exists in the schema
     */
    public function moveBlock(array $schema, string $blockKey, int $targetSerial): ?array
    {
        $blocks = array_values($schema['blocks'] ?? []);

        $currentIndex = null;

        foreach ($blocks as $i => $block) {
            if (($block['key'] ?? null) === $blockKey) {
                $currentIndex = $i;
                break;
            }
        }

        if ($currentIndex === null) {
            return null;
        }

        $block = $blocks[$currentIndex];
        unset($blocks[$currentIndex]);
        $blocks = array_values($blocks);

        $insertAt = max(0, min($targetSerial - 1, count($blocks)));
        array_splice($blocks, $insertAt, 0, [$block]);

        $schema['blocks'] = $blocks;

        return $this->normalizeSchema($schema);
    }

    /**
     * Move the section with the given key to 1-based position
     * $targetSerial within its own block, shifting the sections in between
     * and renumbering the whole schema to match. Out-of-range positions
     * clamp to the start/end of the block instead of failing.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>|null null if no section with that key exists in the schema
     */
    public function moveSection(array $schema, string $sectionKey, int $targetSerial): ?array
    {
        foreach ($schema['blocks'] ?? [] as $blockIndex => $block) {
            $sections = array_values($block['sections'] ?? []);

            $currentIndex = null;

            foreach ($sections as $i => $section) {
                if (($section['key'] ?? null) === $sectionKey) {
                    $currentIndex = $i;
                    break;
                }
            }

            if ($currentIndex === null) {
                continue;
            }

            $section = $sections[$currentIndex];
            unset($sections[$currentIndex]);
            $sections = array_values($sections);

            $insertAt = max(0, min($targetSerial - 1, count($sections)));
            array_splice($sections, $insertAt, 0, [$section]);

            $schema['blocks'][$blockIndex]['sections'] = $sections;

            return $this->normalizeSchema($schema);
        }

        return null;
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
                case 'file_upload':
                    $fieldRules[] = 'file';
                    $fieldRules[] = 'mimes:pdf,jpg,jpeg,png,doc,docx';
                    $fieldRules[] = 'max:5120';
                    break;
                case 'list_files':
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

            if (($field['type'] ?? null) === 'list_files') {
                $rules["answers.{$name}.*"] = 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120';
            }
        }

        return $rules;
    }

    /**
     * Columns a form submission must never set directly, regardless of the
     * target model's $fillable or how a field happens to be named in the
     * agency's schema — ownership, workflow and billing state are assigned
     * by the agency/system, never by whoever is filling out the form.
     *
     * @var array<int, string>
     */
    private const PROTECTED_COLUMNS = [
        'id', 'agency_id', 'user_id', 'client_id', 'candidate_id',
        'status_id', 'tag_id', 'checklist_id', 'payment_status', 'stripe_customer_id',
        'created_at', 'updated_at',
    ];

    /**
     * Keep only the answers whose keys are both a fillable column of the
     * target model AND actually part of this form's definition (its base
     * fields or its configured schema fields) - never an arbitrary fillable
     * column a submitter names that the form never asked for, and never a
     * protected/ownership column no matter what it's named.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function mapAnswersToColumns(string $modelClass, string $entity, array $schema, array $answers): array
    {
        $fillable = array_diff((new $modelClass)->getFillable(), self::PROTECTED_COLUMNS);

        $formFieldNames = collect($this->baseFields($entity))->pluck('name')
            ->merge(collect($this->flattenFields($schema))->pluck('name'))
            ->filter()
            ->unique();

        $allowedKeys = array_intersect($fillable, $formFieldNames->all());

        return array_intersect_key($answers, array_flip($allowedKeys));
    }

    /**
     * Field types whose answers arrive as uploaded files rather than plain
     * values, mapped to whether the field accepts more than one file.
     *
     * @var array<string, bool>
     */
    private const FILE_FIELD_TYPES = [
        'file_upload' => false,
        'list_files' => true,
    ];

    /**
     * Replace any uploaded files among a public registration submission's
     * "answers" (base fields + schema fields, posted namespaced under
     * "answers.*") with their saved storage path(s), so downstream code
     * (column mapping, the submission's stored JSON) only ever deals with
     * plain values instead of UploadedFile instances.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function storeFileAnswers(Request $request, string $entity, array $schema, array $answers): array
    {
        $fields = collect($this->baseFields($entity))->merge($this->flattenFields($schema));

        return $this->replaceUploadedFiles($request, $fields, $entity, $answers, 'answers.');
    }

    /**
     * Replace any uploaded files among a schema's dynamic answers - posted
     * flat, without an "answers." prefix, as by the agency-side profile
     * update endpoints - with their saved storage path(s), deleting
     * whatever file(s) previously occupied that field.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $existingAnswers
     * @return array<string, mixed>
     */
    public function storeDynamicFileAnswers(Request $request, string $entity, array $schema, array $answers, array $existingAnswers = []): array
    {
        return $this->replaceUploadedFiles($request, collect($this->flattenFields($schema)), $entity, $answers, '', $existingAnswers);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $existingAnswers
     * @return array<string, mixed>
     */
    private function replaceUploadedFiles(Request $request, $fields, string $entity, array $answers, string $prefix, array $existingAnswers = []): array
    {
        $fileFields = $fields
            ->filter(fn ($field) => array_key_exists($field['type'] ?? null, self::FILE_FIELD_TYPES))
            ->pluck('type', 'name');

        foreach ($fileFields as $name => $type) {
            if (! $request->hasFile("{$prefix}{$name}")) {
                continue;
            }

            $files = $request->file("{$prefix}{$name}");

            $answers[$name] = self::FILE_FIELD_TYPES[$type]
                ? collect(Arr::wrap($files))->map(fn ($file) => $file->store("form-submissions/{$entity}", 'public'))->all()
                : $files->store("form-submissions/{$entity}", 'public');

            if ($old = $existingAnswers[$name] ?? null) {
                Storage::disk('public')->delete((array) $old);
            }
        }

        return $answers;
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
