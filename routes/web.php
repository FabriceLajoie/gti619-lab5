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
Route::get('/login', [AuthController::class, 'showLogin'])->name('login.form');
Route::post('/login', [AuthController::class, 'login'])->name('login.post')->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/password/change', [App\Http\Controllers\PasswordController::class, 'showChangeForm'])
    ->middleware('auth')->name('password.change');

Route::post('/password/change', [App\Http\Controllers\PasswordController::class, 'change'])
    ->middleware('auth')->name('password.change.post');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [AuthController::class, 'showDashboard'])->name('dashboard');
    Route::get('/settings', [DashboardController::class, 'showSettings'])->name('settings');
    Route::get('/clients/residential', [DashboardController::class, 'showResidentialClients'])->name('clients.residential');
    Route::get('/clients/business', [DashboardController::class, 'showBusinessClients'])->name('clients.business');
    Route::resource('client', ClientController::class);
});
// Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
