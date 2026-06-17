<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\DisciplineController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\VotingController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\ReferenceController;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('recovery', [AuthController::class, 'recovery']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

// Public pages
Route::prefix('pages')->group(function () {
    Route::get('privacy', [PageController::class, 'privacy']);
    Route::get('terms', [PageController::class, 'terms']);
    Route::get('about', [PageController::class, 'about']);
});

// Public FAQ
Route::get('faq', [FaqController::class, 'index']);
Route::get('faq/{group_id}/questions', [FaqController::class, 'questions']);
Route::post('faq/question', [FaqController::class, 'store']);

// Protected routes
Route::middleware(['auth:sanctum', 'student.active'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::put('auth/select-group', [AuthController::class, 'selectGroup']);

    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile/password', [ProfileController::class, 'changePassword']);
    Route::post('profile/avatar', [ProfileController::class, 'uploadAvatar']);

    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::prefix('schedule')->group(function () {
        Route::get('/', [ScheduleController::class, 'index']);
        Route::get('lesson/{id}', [ScheduleController::class, 'lesson']);
        Route::get('teachers', [ReferenceController::class, 'teachers']);
        Route::get('auditoriums', [ReferenceController::class, 'auditoriums']);
        Route::get('subjects', [ReferenceController::class, 'subjects']);
        Route::get('groups', [ReferenceController::class, 'groups']);
        Route::get('lesson-types', [ReferenceController::class, 'lessonTypes']);
    });

    Route::prefix('grades')->group(function () {
        Route::get('/', [GradeController::class, 'index']);
        Route::get('modules', [GradeController::class, 'modules']);
        Route::get('modules/{card_id}', [GradeController::class, 'moduleDetail']);
        Route::get('markbook', [GradeController::class, 'markbook']);
        Route::get('attendance', [GradeController::class, 'attendance']);
    });

    Route::prefix('disciplines')->group(function () {
        Route::get('electives', [DisciplineController::class, 'electives']);
        Route::get('electives/selected', [DisciplineController::class, 'selected']);
        Route::post('electives/save', [DisciplineController::class, 'save']);
        Route::delete('electives/{id}', [DisciplineController::class, 'destroy']);
        Route::get('electives/pdf', [DisciplineController::class, 'pdf']);
        Route::get('history', [DisciplineController::class, 'history']);
    });

    Route::prefix('voting')->group(function () {
        Route::get('/', [VotingController::class, 'index']);
        Route::post('vote', [VotingController::class, 'vote']);
        Route::get('results', [VotingController::class, 'results']);
    });

    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index']);
        Route::put('notifications', [SettingsController::class, 'notifications']);
    });
});
