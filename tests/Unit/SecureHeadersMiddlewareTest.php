<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Middleware\SecureHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SecureHeadersMiddlewareTest extends TestCase
{
    /** @test */
    public function it_adds_x_frame_options_header()
    {
        $middleware = new SecureHeaders();
        $request = Request::create('/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Test');
        });

        $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'));
    }

    /** @test */
    public function it_adds_x_content_type_options_header()
    {
        $middleware = new SecureHeaders();
        $request = Request::create('/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Test');
        });

        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    /** @test */
    public function it_adds_x_xss_protection_header()
    {
        $middleware = new SecureHeaders();
        $request = Request::create('/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Test');
        });

        $this->assertEquals('1; mode=block', $response->headers->get('X-XSS-Protection'));
    }

    /** @test */
    public function it_adds_strict_transport_security_header()
    {
        $middleware = new SecureHeaders();
        $request = Request::create('/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Test');
        });

        $this->assertEquals('max-age=31536000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
    }

    /** @test */
    public function it_adds_referrer_policy_header()
    {
        $middleware = new SecureHeaders();
        $request = Request::create('/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Test');
        });

        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }

    /** @test */
    public function it_adds_content_security_policy_header()
    {
        $middleware = new SecureHeaders();
        $request = Request::create('/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Test');
        });

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    /** @test */
    public function it_adds_permissions_policy_header()
    {
        $middleware = new SecureHeaders();
        $request = Request::create('/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return new Response('Test');
        });

        $permissionsPolicy = $response->headers->get('Permissions-Policy');
        $this->assertNotNull($permissionsPolicy);
        $this->assertStringContainsString('geolocation=()', $permissionsPolicy);
        $this->assertStringContainsString('camera=()', $permissionsPolicy);
    }

    /** @test */
    public function it_does_not_modify_request()
    {
        $middleware = new SecureHeaders();
        $request = Request::create('/test', 'GET');
        $originalUri = $request->getRequestUri();
        
        $middleware->handle($request, function ($req) {
            return new Response('Test');
        });

        $this->assertEquals($originalUri, $request->getRequestUri());
    }

    /** @test */
    public function it_preserves_response_content()
    {
        $middleware = new SecureHeaders();
        $request = Request::create('/test', 'GET');
        $expectedContent = 'Test Response Content';
        
        $response = $middleware->handle($request, function ($req) use ($expectedContent) {
            return new Response($expectedContent);
        });

        $this->assertEquals($expectedContent, $response->getContent());
    }
}
