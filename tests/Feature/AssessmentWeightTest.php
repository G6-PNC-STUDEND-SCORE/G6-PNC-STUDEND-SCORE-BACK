<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentWeightTest extends TestCase
{
    use RefreshDatabase;

    public function test_weights_endpoints_exist()
    {
        $response = $this->getJson('/api/subjects/1/weights');
        $response->assertStatus(200);
    }
}
