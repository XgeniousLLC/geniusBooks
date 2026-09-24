<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Portal\AuthController as PortalAuthController;
use App\Http\Controllers\Portal\DashboardController as PortalDashboardController;
use App\Http\Controllers\Portal\ProfileController as PortalProfileController;

// Customer Portal
Route::prefix('portal')->name('portal.')->group(function () {
    // Guest routes
    Route::middleware('guest.customer')->group(function () {
        Route::get('/login', [PortalAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [PortalAuthController::class, 'login'])->middleware('throttle:portal-auth');
        Route::get('/register', [PortalAuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [PortalAuthController::class, 'register'])->middleware('throttle:portal-auth');
        Route::get('/forgot-password', [PortalAuthController::class, 'showForgotPassword'])->name('password.request');
        Route::post('/forgot-password', [PortalAuthController::class, 'sendResetLink'])->name('password.email');
        Route::get('/reset-password/{token}', [PortalAuthController::class, 'showResetPassword'])->name('password.reset');
        Route::post('/reset-password', [PortalAuthController::class, 'resetPassword'])->name('password.update');
    });

    // Authenticated customer routes
    Route::middleware('customer')->group(function () {
        Route::get('/', [PortalDashboardController::class, 'index'])->name('home');
        Route::post('/logout', [PortalAuthController::class, 'logout'])->name('logout');

        Route::get('/profile', [PortalProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [PortalProfileController::class, 'update'])->name('profile.update');
        Route::patch('/profile/password', [PortalProfileController::class, 'updatePassword'])->name('profile.password');
    });
});

Route::get('/', function () {
    return redirect()->route('portal.login');
});

// Frontend Routes
Route::get('/page/{page}', [PageController::class, 'show'])->name('page.show');

// Admin Authentication Routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Protected Admin Routes
    Route::middleware(['admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('pages', AdminPageController::class);
        Route::post('/pages/analyze-seo', [AdminPageController::class, 'analyzeSEO'])->name('pages.analyze-seo');

        // Admin Management
        Route::resource('admins', AdminController::class);
        Route::post('/admins/{admin}/change-password', [AdminController::class, 'changePassword'])->name('admins.change-password');

        // Profile Management for Current Admin
        Route::get('/profile/edit', [AdminController::class, 'editProfile'])->name('profile.edit');
        Route::post('/profile/update', [AdminController::class, 'updateProfile'])->name('profile.update');
        Route::get('/profile/change-password', [AdminController::class, 'showChangePassword'])->name('profile.change-password');
        Route::post('/profile/change-password', [AdminController::class, 'updatePassword'])->name('profile.update-password');

        // User Management
        Route::resource('users', UserController::class);
        Route::post('/users/{user}/change-password', [UserController::class, 'changePassword'])->name('users.change-password');
    });
});
