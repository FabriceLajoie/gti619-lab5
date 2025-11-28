<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
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
Route::get('/', function() {
    return view('welcome');
});
// Route::get('/', [AuthController::class, 'showLogin']);
// Route::post('/login', [AuthController::class, 'login']);
// Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');

// Dashboard routes
Route::get('/dashboard', [AuthController::class, 'showDashboard'])->name('dashboard');
Route::get('/settings', [DashboardController::class, 'showSettings'])->name('settings');
Route::get('/clients/residential', [DashboardController::class, 'showResidentialClients'])->name('clients.residential');
Route::get('/clients/business', [DashboardController::class, 'showBusinessClients'])->name('clients.business');

// Client resource routes
Route::resource('client', ClientController::class);
Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
