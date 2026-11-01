<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConnectivityTest extends TestCase
{
    public function test_connectivity_check_endpoint_is_accessible_without_auth(): void
    {
        $response = $this->getJson('/api/connectivity-check');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'online',
            'timestamp',
        ]);
        $this->assertIsBool($response->json('online'));
    }
}

