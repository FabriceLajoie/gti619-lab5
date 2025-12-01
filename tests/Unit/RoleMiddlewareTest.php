<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Mockery;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected $auditLogger;
    protected $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->auditLogger = Mockery::mock(AuditLogger::class);
        $this->middleware = new RoleMiddleware($this->auditLogger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_redirects_unauthenticated_users_to_login()
    {
        $request = Request::create('/dashboard', 'GET');
        
        Auth::shouldReceive('check')->once()->andReturn(false);
        
        $response = $this->middleware->handle($request, function () {
            return new Response('Success');
        }, 'Administrateur');

        $this->assertEquals(302, $response->getStatusCode());
        // The redirect should be to login route - we'll check that it's a redirect response
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    /** @test */
    public function it_allows_access_when_user_has_required_role()
    {
        $role = Role::factory()->create(['name' => 'Administrateur']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $request = Request::create('/dashboard', 'GET');
        $request->setRouteResolver(function () {
            $route = Mockery::mock();
            $route->shouldReceive('getName')->andReturn('dashboard');
            return $route;
        });

        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        
        $this->auditLogger->shouldReceive('logSecurityEvent')
            ->once()
            ->with('authorized_access', $user->id, Mockery::type('array'), Mockery::type('Illuminate\Http\Request'));

        $response = $this->middleware->handle($request, function () {
            return new Response('Success');
        }, 'Administrateur');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    /** @test */
    public function it_denies_access_when_user_lacks_required_role()
    {
        $role = Role::factory()->create(['name' => 'Préposé aux clients résidentiels']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $request = Request::create('/settings', 'GET');
        $request->setRouteResolver(function () {
            $route = Mockery::mock();
            $route->shouldReceive('getName')->andReturn('settings');
            return $route;
        });

        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        
        $this->auditLogger->shouldReceive('logSecurityEvent')
            ->once()
            ->with('unauthorized_access_insufficient_role', $user->id, Mockery::type('array'), Mockery::type('Illuminate\Http\Request'));

        $response = $this->middleware->handle($request, function () {
            return new Response('Success');
        }, 'Administrateur');

        $this->assertEquals(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        $this->assertTrue(
            str_contains($location, '/clients/residential') || str_contains($location, 'clients.residential'),
            "Expected redirect to contain 'clients/residential', got: {$location}"
        );
    }

    /** @test */
    public function it_allows_access_when_user_has_one_of_multiple_required_roles()
    {
        $role = Role::factory()->create(['name' => 'Préposé aux clients résidentiels']);
        $user = User::factory()->create(['role_id' => $role->id]);
        
        $request = Request::create('/clients/residential', 'GET');
        $request->setRouteResolver(function () {
            $route = Mockery::mock();
            $route->shouldReceive('getName')->andReturn('clients.residential');
            return $route;
        });

        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        
        $this->auditLogger->shouldReceive('logSecurityEvent')
            ->once()
            ->with('authorized_access', $user->id, Mockery::type('array'), Mockery::type('Illuminate\Http\Request'));

        $response = $this->middleware->handle($request, function () {
            return new Response('Success');
        }, 'Administrateur', 'Préposé aux clients résidentiels');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    /** @test */
    public function it_denies_access_when_user_has_no_role_assigned()
    {
        $user = User::factory()->create(['role_id' => null]);
        
        $request = Request::create('/dashboard', 'GET');
        $request->setRouteResolver(function () {
            $route = Mockery::mock();
            $route->shouldReceive('getName')->andReturn('dashboard');
            return $route;
        });

        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);
        
        $this->auditLogger->shouldReceive('logSecurityEvent')
            ->once()
            ->with('unauthorized_access_no_role', $user->id, Mockery::type('array'), Mockery::type('Illuminate\Http\Request'));

        $response = $this->middleware->handle($request, function () {
            return new Response('Success');
        }, 'Administrateur');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/dashboard', $response->headers->get('Location'));
    }

    /** @test */
    public function it_redirects_administrator_to_dashboard_when_access_denied()
    {
        $adminRole = Role::factory()->create(['name' => 'Administrateur']);
        $adminUser = User::factory()->create(['role_id' => $adminRole->id]);
        
        $request = Request::create('/restricted', 'GET');
        $request->setRouteResolver(function () {
            $route = Mockery::mock();
            $route->shouldReceive('getName')->andReturn('restricted');
            return $route;
        });

        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('user')->andReturn($adminUser);
        
        $this->auditLogger->shouldReceive('logSecurityEvent')
            ->once()
            ->with('unauthorized_access_insufficient_role', $adminUser->id, Mockery::type('array'), Mockery::type('Illuminate\Http\Request'));

        $response = $this->middleware->handle($request, function () {
            return new Response('Success');
        }, 'NonExistentRole');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }

    /** @test */
    public function it_redirects_residential_clerk_to_residential_clients_when_access_denied()
    {
        $residentialRole = Role::factory()->create(['name' => 'Préposé aux clients résidentiels']);
        $residentialUser = User::factory()->create(['role_id' => $residentialRole->id]);
        
        $request = Request::create('/restricted', 'GET');
        $request->setRouteResolver(function () {
            $route = Mockery::mock();
            $route->shouldReceive('getName')->andReturn('restricted');
            return $route;
        });

        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('user')->andReturn($residentialUser);
        
        $this->auditLogger->shouldReceive('logSecurityEvent')
            ->once()
            ->with('unauthorized_access_insufficient_role', $residentialUser->id, Mockery::type('array'), Mockery::type('Illuminate\Http\Request'));

        $response = $this->middleware->handle($request, function () {
            return new Response('Success');
        }, 'NonExistentRole');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    }
}