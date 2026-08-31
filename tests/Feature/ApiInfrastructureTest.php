<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiInfrastructureTest extends TestCase
{
    public function test_health_check_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_root_route_is_not_exposed(): void
    {
        $this->get('/')->assertNotFound();
    }

    public function test_api_errors_are_returned_as_json(): void
    {
        $this->get('/api/v1/non-existent-route')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message'])
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('trace');
    }
}
