<?php

use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PracticeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', [PracticeController::class, 'index'])->name('practice');
    Route::post('/evaluate', [PracticeController::class, 'evaluate'])->middleware('throttle:20,1')->name('evaluate');
    Route::get('/results/{attempt}', [PracticeController::class, 'results'])->name('results.show');
    Route::get('/history', [PracticeController::class, 'history'])->name('history');
    Route::get('/resources', [PracticeController::class, 'resources'])->name('resources');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::middleware('can:manage-question-bank')->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/questions', [QuestionController::class, 'index'])->name('questions.index');
        Route::post('/questions', [QuestionController::class, 'store'])->name('questions.store');
        Route::put('/questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
        Route::patch('/questions/{question}/status', [QuestionController::class, 'toggleStatus'])->name('questions.status');
    });
});
