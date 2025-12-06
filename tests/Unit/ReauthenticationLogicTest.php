<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\ReauthenticationController;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReauthenticationLogicTest extends TestCase
{
    /** @test */
    public function it_detects_when_reauth_is_needed_without_timestamp()
    {
        Auth::shouldReceive('check')->andReturn(true);
        Session::shouldReceive('get')
            ->with('last_reauth_at')
            ->andReturn(null);

        $this->assertTrue(ReauthenticationController::needsReauth());
    }

    /** @test */
    public function it_detects_when_reauth_is_not_needed_with_recent_timestamp()
    {
        Auth::shouldReceive('check')->andReturn(true);
        Session::shouldReceive('get')
            ->with('last_reauth_at')
            ->andReturn(Carbon::now()->toISOString());

        $this->assertFalse(ReauthenticationController::needsReauth());
    }

    /** @test */
    public function it_detects_when_reauth_is_needed_with_old_timestamp()
    {
        Auth::shouldReceive('check')->andReturn(true);
        Session::shouldReceive('get')
            ->with('last_reauth_at')
            ->andReturn(Carbon::now()->subMinutes(20)->toISOString());

        $this->assertTrue(ReauthenticationController::needsReauth(15));
    }

    /** @test */
    public function it_forces_reauth_by_clearing_timestamp()
    {
        Session::shouldReceive('forget')
            ->with('last_reauth_at')
            ->once();

        ReauthenticationController::forceReauth();
        
        // The method should have called Session::forget
        $this->assertTrue(true); // Assert that no exception was thrown
    }

    /** @test */
    public function it_respects_custom_max_age_parameter()
    {
        // 8 minutes ago
        $timestamp = Carbon::now()->subMinutes(8)->toISOString();
        
        Auth::shouldReceive('check')->andReturn(true);
        Session::shouldReceive('get')
            ->with('last_reauth_at')
            ->andReturn($timestamp);

        // Should not need reauth with 10 minute max age
        $this->assertFalse(ReauthenticationController::needsReauth(10));

        Auth::shouldReceive('check')->andReturn(true);
        Session::shouldReceive('get')
            ->with('last_reauth_at')
            ->andReturn($timestamp);

        // Should need reauth with 5 minute max age
        $this->assertTrue(ReauthenticationController::needsReauth(5));
    }

    /** @test */
    public function it_returns_true_when_user_not_authenticated()
    {
        Auth::shouldReceive('check')->andReturn(false);

        $this->assertTrue(ReauthenticationController::needsReauth());
    }
}