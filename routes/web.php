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
Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');

// Dashboard routes
Route::get('/dashboard', [AuthController::class, 'showDashboard'])->name('dashboard');
Route::get('/settings', [DashboardController::class, 'showSettings'])->name('settings');
Route::get('/clients/residential', [DashboardController::class, 'showResidentialClients'])->name('clients.residential');
Route::get('/clients/business', [DashboardController::class, 'showBusinessClients'])->name('clients.business');

// Client resource routes
Route::resource('client', ClientController::class);
Route::get('client/{id}/edit', 'ClientController@edit')->name('client.edit');
Route::put('client/{id}', 'ClientController@update')->name('client.update');
Route::delete('client/{id}', 'ClientController@destroy')->name('client.destroy');