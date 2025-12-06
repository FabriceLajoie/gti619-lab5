<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Middleware\RequireReauthentication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;
use Mockery;

class ReauthMiddlewareTest extends TestCase
{
    /** @test */
    public function it_redirects_unauthenticated_users_to_login()
    {
        Auth::shouldReceive('check')->andReturn(false);

        $request = Request::create('/test', 'GET');
        $middleware = new RequireReauthentication();

        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    /** @test */
    public function it_redirects_when_no_reauth_timestamp_exists()
    {
        Auth::shouldReceive('check')->andReturn(true);
        Session::shouldReceive('get')->with('last_reauth_at')->andReturn(null);
        Session::shouldReceive('put')->with('url.intended', 'http://localhost/test');

        $request = Request::create('/test', 'GET');
        $middleware = new RequireReauthentication();

        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/reauth', $response->headers->get('Location'));
    }

    /** @test */
    public function it_redirects_when_reauth_timestamp_is_too_old()
    {
        Auth::shouldReceive('check')->andReturn(true);
        Session::shouldReceive('get')
            ->with('last_reauth_at')
            ->andReturn(Carbon::now()->subMinutes(20)->toISOString());
        Session::shouldReceive('put')->with('url.intended', 'http://localhost/test');

        $request = Request::create('/test', 'GET');
        $middleware = new RequireReauthentication();

        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        }, 15); // 15 minute max age

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/reauth', $response->headers->get('Location'));
    }

    /** @test */
    public function it_allows_request_when_recently_authenticated()
    {
        Auth::shouldReceive('check')->andReturn(true);
        Session::shouldReceive('get')
            ->with('last_reauth_at')
            ->andReturn(Carbon::now()->subMinutes(5)->toISOString());

        $request = Request::create('/test', 'GET');
        $middleware = new RequireReauthentication();

        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        }, 15); // 15 minute max age

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function it_respects_custom_max_age_parameter()
    {
        Auth::shouldReceive('check')->andReturn(true);
        
        // 8 minutes ago
        $timestamp = Carbon::now()->subMinutes(8)->toISOString();
        Session::shouldReceive('get')->with('last_reauth_at')->andReturn($timestamp);

        $request = Request::create('/test', 'GET');
        $middleware = new RequireReauthentication();

        // Should allow with 10 minute max age
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        }, 10);
        $this->assertEquals(200, $response->getStatusCode());

        // Reset mocks for second test
        Auth::shouldReceive('check')->andReturn(true);
        Session::shouldReceive('get')->with('last_reauth_at')->andReturn($timestamp);
        Session::shouldReceive('put')->with('url.intended', 'http://localhost/test');

        // Should redirect with 5 minute max age
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        }, 5);
        $this->assertEquals(302, $response->getStatusCode());
    }
}