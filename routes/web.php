<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ExamSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CourseController::class, 'index'])->name('courses.index');

Route::post('/courses/{course}/exams', [ExamSessionController::class, 'store'])
    ->middleware('throttle:exam-start')
    ->name('exams.store');

Route::get('/exams/{examSession}', [ExamSessionController::class, 'show'])
    ->name('exams.show');

Route::post('/exams/{examSession}', [ExamSessionController::class, 'submit'])
    ->middleware('throttle:exam-submit')
    ->name('exams.submit');

Route::get('/exams/{examSession}/result', [ExamSessionController::class, 'result'])
    ->name('exams.result');

/*
 * Yönetim paneli. Giriş Fortify ile yapılır (/login); kayıt kapalıdır,
 * yönetici hesapları `php artisan admin:create` ile açılır.
 */
Route::middleware(['auth', 'can:access-admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('courses', [Admin\CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{course}/edit', [Admin\CourseController::class, 'edit'])->name('courses.edit');
    Route::put('courses/{course}', [Admin\CourseController::class, 'update'])->name('courses.update');

    Route::post('catalog/sync', [Admin\CatalogSyncController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('catalog.sync');

    Route::post('courses/{course}/text', [Admin\CourseTextController::class, 'store'])
        ->middleware('throttle:question-generation')
        ->name('courses.text.store');

    Route::post('courses/{course}/questions', [Admin\CourseQuestionController::class, 'store'])
        ->middleware('throttle:question-generation')
        ->name('courses.questions.store');

    Route::get('settings/ai', [Admin\AiSettingsController::class, 'edit'])->name('settings.ai.edit');
    Route::put('settings/ai', [Admin\AiSettingsController::class, 'update'])->name('settings.ai.update');
    Route::post('settings/ai/test', [Admin\AiSettingsController::class, 'test'])
        ->middleware('throttle:6,1')
        ->name('settings.ai.test');

    Route::get('profile', [Admin\ProfileController::class, 'edit'])->name('profile.edit');
});
