<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Authentication routes
Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login.form');
Route::post('/login', [AuthController::class, 'login'])->name('login.post')->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/password/change', [App\Http\Controllers\PasswordController::class, 'showChangeForm'])
    ->middleware('auth')->name('password.change');

Route::post('/password/change', [App\Http\Controllers\PasswordController::class, 'change'])
    ->middleware('auth')->name('password.change.post');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [AuthController::class, 'showDashboard'])->name('dashboard');
    
    // Admin-only routes
    Route::middleware(['role:Administrateur'])->group(function () {
        Route::get('/settings', [DashboardController::class, 'showSettings'])->name('settings');
        
        // Security configuration routes
        Route::get('/admin/security-config', [AdminController::class, 'securityConfig'])->name('admin.security-config');
        Route::post('/admin/security-config', [AdminController::class, 'updateSecurityConfig'])->name('admin.security-config.update');
        Route::post('/admin/security-config/reset', [AdminController::class, 'resetSecurityConfig'])->name('admin.security-config.reset');
        
        // User management routes
        Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
        Route::get('/admin/users/create', [AdminController::class, 'createUser'])->name('admin.users.create');
        Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
        Route::get('/admin/users/{user}', [AdminController::class, 'userDetails'])->name('admin.users.details');
        Route::get('/admin/users/{user}/edit', [AdminController::class, 'editUser'])->name('admin.users.edit');
        Route::put('/admin/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
        Route::get('/admin/users/{user}/activity', [AdminController::class, 'userActivity'])->name('admin.users.activity');
        Route::post('/admin/users/{user}/unlock', [AdminController::class, 'unlockUser'])->name('admin.users.unlock');
        Route::post('/admin/users/{user}/reset-password', [AdminController::class, 'resetUserPassword'])->name('admin.users.reset-password');
        Route::post('/admin/users/{user}/terminate-sessions', [AdminController::class, 'terminateUserSessions'])->name('admin.users.terminate-sessions');
        
        // Audit logging routes
        Route::get('/admin/audit-logs', [AdminController::class, 'auditLogs'])->name('admin.audit-logs');
        Route::get('/admin/audit-logs/{auditLog}', [AdminController::class, 'auditLogDetails'])->name('admin.audit-log-details');
        Route::get('/admin/audit-logs-export', [AdminController::class, 'exportAuditLogs'])->name('admin.audit-logs.export');
        Route::get('/admin/audit-statistics', [AdminController::class, 'auditStatistics'])->name('admin.audit-statistics');
    });
    
    // Residential clients - accessible by Administrateur and Préposé aux clients résidentiels
    Route::middleware(['role:Administrateur,Préposé aux clients résidentiels'])->group(function () {
        Route::get('/clients/residential', [DashboardController::class, 'showResidentialClients'])->name('clients.residential');
    });
    
    // Business clients - accessible by Administrateur and Préposé aux clients d'affaire
    Route::middleware(['role:Administrateur,Préposé aux clients d\'affaire'])->group(function () {
        Route::get('/clients/business', [DashboardController::class, 'showBusinessClients'])->name('clients.business');
    });
    
    // Client resource routes with role-based access
    Route::resource('client', ClientController::class)->middleware('role:Administrateur,Préposé aux clients résidentiels,Préposé aux clients d\'affaire');
});
// Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
