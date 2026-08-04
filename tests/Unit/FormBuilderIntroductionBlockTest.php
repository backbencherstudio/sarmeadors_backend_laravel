<?php

namespace Tests\Unit;

use App\Services\FormBuilderService;
use Tests\TestCase;

class FormBuilderIntroductionBlockTest extends TestCase
{
    public function test_default_introduction_block_matches_builder_shape(): void
    {
        $block = (new FormBuilderService)->defaultIntroductionBlock('Family Form - Nanny');

        $this->assertSame('Introduction', $block['name']);
        $this->assertSame('introduction', $block['type']);
        $this->assertNull($block['description']);
        $this->assertNull($block['service_id']);
        $this->assertSame([], $block['sections']);
        $this->assertSame([
            'title' => 'Family Form - Nanny',
            'logo_url' => null,
            'button_label' => null,
            'button_link' => null,
        ], $block['config']);
    }

    public function test_ensure_introduction_block_prepends_when_missing(): void
    {
        $schema = (new FormBuilderService)->ensureIntroductionBlock([
            'blocks' => [[
                'name' => 'Contact & Address',
                'type' => 'standard',
                'sections' => [],
            ]],
        ], 'Client Registration');

        $this->assertCount(2, $schema['blocks']);
        $this->assertSame('introduction', $schema['blocks'][0]['type']);
        $this->assertSame('Client Registration', $schema['blocks'][0]['config']['title']);
        $this->assertSame('Contact & Address', $schema['blocks'][1]['name']);
    }

    public function test_ensure_introduction_block_does_not_duplicate_existing(): void
    {
        $schema = (new FormBuilderService)->ensureIntroductionBlock([
            'blocks' => [[
                'name' => 'Introduction',
                'type' => 'introduction',
                'description' => 'Not sure if you are ready to go ahead?',
                'config' => [
                    'title' => 'Family Form - Nanny',
                    'logo_url' => null,
                    'button_label' => 'Book a call with us',
                    'button_link' => 'https://calendly.com/example',
                ],
                'sections' => [],
            ]],
        ], 'Should Not Replace');

        $this->assertCount(1, $schema['blocks']);
        $this->assertSame('Family Form - Nanny', $schema['blocks'][0]['config']['title']);
        $this->assertSame('Book a call with us', $schema['blocks'][0]['config']['button_label']);
    }

    public function test_normalize_schema_shapes_introduction_config(): void
    {
        $schema = (new FormBuilderService)->normalizeSchema([
            'blocks' => [[
                'name' => 'Introduction',
                'type' => 'introduction',
                'description' => 'Welcome',
                'config' => [
                    'title' => 'Family Form - Nanny',
                    'button_label' => 'Button Text',
                    'extra_ignored' => 'drop-me',
                ],
                'sections' => [],
            ]],
        ]);

        $block = $schema['blocks'][0];

        $this->assertSame('introduction', $block['type']);
        $this->assertSame(1, $block['serial']);
        $this->assertNotEmpty($block['key']);
        $this->assertSame([
            'title' => 'Family Form - Nanny',
            'logo_url' => null,
            'button_label' => 'Button Text',
            'button_link' => null,
        ], $block['config']);
        $this->assertArrayNotHasKey('extra_ignored', $block['config']);
    }

    public function test_set_introduction_logo_writes_path_onto_config(): void
    {
        $builder = new FormBuilderService;

        $schema = $builder->setIntroductionLogo(
            ['blocks' => []],
            'forms/logos/1/logo.png',
            'Family Form - Nanny'
        );

        $this->assertSame('introduction', $schema['blocks'][0]['type']);
        $this->assertSame('forms/logos/1/logo.png', $schema['blocks'][0]['config']['logo_url']);
        $this->assertSame('Family Form - Nanny', $schema['blocks'][0]['config']['title']);
    }

    public function test_clear_introduction_logo_returns_previous_path(): void
    {
        $builder = new FormBuilderService;

        $schema = $builder->setIntroductionLogo(
            ['blocks' => []],
            'forms/logos/1/logo.png'
        );

        [$cleared, $previous] = $builder->clearIntroductionLogo($schema);

        $this->assertSame('forms/logos/1/logo.png', $previous);
        $this->assertNull($cleared['blocks'][0]['config']['logo_url']);
    }

    public function test_present_schema_converts_logo_path_to_public_url(): void
    {
        $schema = (new FormBuilderService)->presentSchema([
            'blocks' => [[
                'type' => 'introduction',
                'config' => ['logo_url' => 'forms/logos/1/logo.png'],
            ]],
        ]);

        $this->assertSame(
            asset('storage/forms/logos/1/logo.png'),
            $schema['blocks'][0]['config']['logo_url']
        );
    }

    public function test_normalize_introduction_logo_path_strips_storage_url(): void
    {
        $builder = new FormBuilderService;

        $this->assertSame(
            'forms/logos/1/logo.png',
            $builder->normalizeIntroductionLogoPath(asset('storage/forms/logos/1/logo.png'))
        );
        $this->assertSame(
            'forms/logos/1/logo.png',
            $builder->normalizeIntroductionLogoPath('forms/logos/1/logo.png')
        );
        $this->assertNull($builder->normalizeIntroductionLogoPath(null));
    }

    public function test_normalize_schema_preserves_agency_service_id_on_blocks(): void
    {
        $schema = (new FormBuilderService)->normalizeSchema([
            'blocks' => [
                [
                    'name' => 'Introduction',
                    'type' => 'introduction',
                    'service_id' => 99,
                    'sections' => [],
                ],
                [
                    'name' => 'Nanny Details',
                    'type' => 'standard',
                    'service_id' => '5',
                    'sections' => [],
                ],
                [
                    'name' => 'Job Details',
                    'type' => 'standard',
                    'service_id' => null,
                    'sections' => [],
                ],
            ],
        ]);

        $this->assertNull($schema['blocks'][0]['service_id']);
        $this->assertSame(5, $schema['blocks'][1]['service_id']);
        $this->assertNull($schema['blocks'][2]['service_id']);
    }

    public function test_normalize_block_service_id_casts_empty_to_null(): void
    {
        $builder = new FormBuilderService;

        $this->assertNull($builder->normalizeBlockServiceId(null));
        $this->assertNull($builder->normalizeBlockServiceId(''));
        $this->assertSame(12, $builder->normalizeBlockServiceId('12'));
    }

    public function test_normalize_schema_stores_options_source_without_snapshotting_options(): void
    {
        $schema = (new FormBuilderService)->normalizeSchema([
            'blocks' => [[
                'name' => 'Select Services',
                'sections' => [[
                    'name' => 'Services',
                    'fields' => [[
                        'type' => 'multi_select_checkbox',
                        'label' => 'Which services do you need?',
                        'name' => 'selected_services',
                        'options_source' => 'agency_services',
                        'allowed_service_ids' => ['7', 3, 3, ''],
                        'options' => ['Stale Name'],
                    ]],
                ]],
            ]],
        ]);

        $field = $schema['blocks'][0]['sections'][0]['fields'][0];

        $this->assertSame('agency_services', $field['options_source']);
        $this->assertNull($field['options']);
        $this->assertSame([7, 3], $field['allowed_service_ids']);
    }

    public function test_normalize_allowed_service_ids_defaults_to_empty_array(): void
    {
        $builder = new FormBuilderService;

        $this->assertSame([], $builder->normalizeAllowedServiceIds(null));
        $this->assertSame([1, 3], $builder->normalizeAllowedServiceIds([1, '3', 1]));
    }

    public function test_agency_service_options_returns_empty_when_allow_list_empty(): void
    {
        $this->assertSame([], (new FormBuilderService)->agencyServiceOptions(1, []));
        $this->assertSame([], (new FormBuilderService)->agencyServiceOptions(1, null));
    }

    public function test_normalize_schema_rejects_unknown_options_source(): void
    {
        $schema = (new FormBuilderService)->normalizeSchema([
            'blocks' => [[
                'name' => 'Select Services',
                'sections' => [[
                    'fields' => [[
                        'type' => 'multi_select_checkbox',
                        'label' => 'Services',
                        'name' => 'selected_services',
                        'options_source' => 'not_a_real_source',
                        'options' => ['A', 'B'],
                    ]],
                ]],
            ]],
        ]);

        $field = $schema['blocks'][0]['sections'][0]['fields'][0];

        $this->assertNull($field['options_source']);
        $this->assertSame(['A', 'B'], $field['options']);
    }

    public function test_submission_blocks_attach_answers_and_filter_by_selected_services(): void
    {
        $schema = [
            'blocks' => [
                [
                    'name' => 'Introduction',
                    'type' => 'introduction',
                    'service_id' => null,
                    'sections' => [],
                ],
                [
                    'name' => 'Contact & Address',
                    'type' => 'standard',
                    'service_id' => null,
                    'sections' => [[
                        'name' => 'Parent',
                        'fields' => [
                            ['type' => 'text_box', 'label' => 'Title', 'name' => 'title'],
                            ['type' => 'single_checkbox', 'label' => 'Agree', 'name' => 'agree'],
                        ],
                    ]],
                ],
                [
                    'name' => 'Nanny Only',
                    'type' => 'standard',
                    'service_id' => 7,
                    'sections' => [[
                        'name' => 'Extra',
                        'fields' => [
                            ['type' => 'text_box', 'label' => 'Hours', 'name' => 'hours'],
                        ],
                    ]],
                ],
                [
                    'name' => 'Chef Only',
                    'type' => 'standard',
                    'service_id' => 3,
                    'sections' => [[
                        'name' => 'Extra',
                        'fields' => [
                            ['type' => 'text_box', 'label' => 'Meals', 'name' => 'meals'],
                        ],
                    ]],
                ],
            ],
        ];

        $blocks = (new FormBuilderService)->submissionBlocks($schema, [
            'title' => 'After School Nanny',
            'agree' => true,
            'hours' => '20',
            'meals' => 'ignored',
            'selected_services' => [7],
        ]);

        $this->assertCount(2, $blocks);
        $this->assertSame('contact-address', $blocks[0]['slug']);
        $this->assertSame('After School Nanny', $blocks[0]['sections'][0]['fields'][0]['value']);
        $this->assertSame('Yes', $blocks[0]['sections'][0]['fields'][1]['display_value']);
        $this->assertSame('nanny-only', $blocks[1]['slug']);
        $this->assertSame('20', $blocks[1]['sections'][0]['fields'][0]['value']);
    }
}
