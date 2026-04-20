<?php

namespace Tests\Feature;

use App\Services\RuneliteApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response()
    {
        $mock = Mockery::mock(RuneliteApiService::class);
        $mock->shouldReceive('getPlugins')->atLeast()->once()->andReturn([]);
        app()->instance(RuneliteApiService::class, $mock);

        $response = $this->get(route('home'), [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();
    }
}
