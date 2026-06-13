<?php

namespace Tests\Feature;

use Tests\TestCase;

class DevelopmentPageTest extends TestCase
{
    public function test_home_page_shows_development_message(): void
    {
        $this
            ->get('/')
            ->assertOk()
            ->assertSee('Website is in development')
            ->assertSee('Coming Soon')
            ->assertSee('Sarmeadors');
    }
}
