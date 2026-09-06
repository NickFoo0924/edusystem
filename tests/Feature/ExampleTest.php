<?php

/**
 * LearnSync -- Automated test
 *
 * Shared: project-wide infrastructure
 *
 * @author Serena Lim Sze Kee, Foo Chong Xian, Ong Shun Yan, Wong Siew Lam, Ong Kwong Wei
 */

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
