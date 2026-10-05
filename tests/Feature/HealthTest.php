<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_live_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/v1/health/live')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_ready_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }
}
