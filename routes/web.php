<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Central\DashboardController;
use App\Http\Controllers\Central\StoreController;
use App\Http\Controllers\Central\StoreProvisionController;
use App\Http\Controllers\Central\StoreStatusController;
use App\Http\Controllers\Central\UserController;
use App\Http\Controllers\Central\UserStatusController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->middleware(['auth', 'shift.none-open'])->name('logout');
Route::prefix('pusat')
    ->name('central.')
    ->middleware(['auth', 'super.admin', 'shift.none-open'])
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::resource('stores', StoreController::class)->except(['show', 'destroy']);
        Route::patch('stores/{store}/status', StoreStatusController::class)->name('stores.status');
        Route::post('stores/{store}/provision', StoreProvisionController::class)->name('stores.provision');

        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::patch('users/{user}/status', UserStatusController::class)->name('users.status');
    });
