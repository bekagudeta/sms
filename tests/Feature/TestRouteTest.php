<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestRouteTest extends TestCase
{
    public function test_test_route_works()
    {
        $request = \Illuminate\Http\Request::create('/test4', 'GET');
        $response = $this->app['router']->dispatchToRoute($request);
        dd($response->getStatusCode());
        $response = $this->app->handle($request);
        dd($response->getStatusCode());
        $response = $this->get('/test4');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContains('test4', $response->getContent());
    }
}