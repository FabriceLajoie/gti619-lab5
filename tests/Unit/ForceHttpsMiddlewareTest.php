<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Middleware\ForceHttps;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ForceHttpsMiddlewareTest extends TestCase
{
    /** @test */
    public function it_allows_https_requests_in_production()
    {
        // Set environmen prod
        $originalEnv = app()->environment();
        app()->detectEnvironment(function () {
            return 'production';
        });
        
        $middleware = new ForceHttps();
        $request = Request::create('https://example.com/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Test');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Test', $response->getContent());
        
        // remttre environment
        app()->detectEnvironment(function () use ($originalEnv) {
            return $originalEnv;
        });
    }

    /** @test */
    public function it_redirects_http_to_https_in_production()
    {
        // Set environment to production
        $originalEnv = app()->environment();
        app()->detectEnvironment(function () {
            return 'production';
        });
        
        $middleware = new ForceHttps();
        $request = Request::create('http://example.com/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Test');
        });

        $this->assertEquals(301, $response->getStatusCode());
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://', $location);
        $this->assertStringContainsString('/test', $location);
        
        // Restore environment
        app()->detectEnvironment(function () use ($originalEnv) {
            return $originalEnv;
        });
    }

    /** @test */
    public function it_allows_http_in_local_environment_by_default()
    {
        config(['app.env' => 'local']);
        config(['app.force_https' => false]);
        
        $middleware = new ForceHttps();
        $request = Request::create('http://localhost/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Test');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Test', $response->getContent());
    }

    /** @test */
    public function it_enforces_https_when_force_https_config_is_true()
    {
        config(['app.env' => 'local']);
        config(['app.force_https' => true]);
        
        $middleware = new ForceHttps();
        $request = Request::create('http://localhost/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Test');
        });

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('https://localhost/test', $response->headers->get('Location'));
    }

    /** @test */
    public function it_allows_http_in_testing_environment_by_default()
    {
        config(['app.env' => 'testing']);
        config(['app.force_https' => false]);
        
        $middleware = new ForceHttps();
        $request = Request::create('http://localhost/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Test');
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_preserves_query_parameters_in_redirect()
    {
        // Set environment to production
        $originalEnv = app()->environment();
        app()->detectEnvironment(function () {
            return 'production';
        });
        
        $middleware = new ForceHttps();
        $request = Request::create('http://example.com/test?foo=bar&baz=qux', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Test');
        });

        $this->assertEquals(301, $response->getStatusCode());
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://', $location);
        $this->assertStringContainsString('/test?foo=bar&baz=qux', $location);
        
        // Restore environment
        app()->detectEnvironment(function () use ($originalEnv) {
            return $originalEnv;
        });
    }

    /** @test */
    public function it_preserves_path_in_redirect()
    {
        // Set environment to production
        $originalEnv = app()->environment();
        app()->detectEnvironment(function () {
            return 'production';
        });
        
        $middleware = new ForceHttps();
        $request = Request::create('http://example.com/admin/users/123', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Test');
        });

        $this->assertEquals(301, $response->getStatusCode());
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://', $location);
        $this->assertStringContainsString('/admin/users/123', $location);
        
        // Restore environment
        app()->detectEnvironment(function () use ($originalEnv) {
            return $originalEnv;
        });
    }
}
