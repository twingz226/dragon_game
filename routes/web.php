<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Health check is handled by the built-in Laravel health endpoint (see bootstrap/app.php)

// Authentication routes for guests only
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
});

// Logout route (accessible by authenticated users)
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// API routes
Route::get('/api/user', [AuthController::class, 'user'])->middleware('auth');

// Redirect root to login for unauthenticated users
Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// Protected game routes — catch-all MUST be last
Route::middleware(['auth'])->group(function () {
    // Serve the Vue SPA shell for all authenticated routes
    Route::get('/{any}', function () {
        return view('game');
    })->where('any', '.+');
});
