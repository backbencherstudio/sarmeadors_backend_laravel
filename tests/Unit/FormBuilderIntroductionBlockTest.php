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
}
