<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ExamSessionController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/sinav/{level}', [CourseController::class, 'index'])->name('courses.index');

Route::post('/exams', [ExamSessionController::class, 'store'])
    ->middleware('throttle:exam-start')
    ->name('exams.store');

Route::get('/exams/{examSession}', [ExamSessionController::class, 'show'])->name('exams.show');

Route::post('/exams/{examSession}/start', [ExamSessionController::class, 'start'])->name('exams.start');

Route::put('/exams/{examSession}/answer', [ExamSessionController::class, 'answer'])
    ->middleware('throttle:exam-answer')
    ->name('exams.answer');

Route::post('/exams/{examSession}/finish', [ExamSessionController::class, 'finish'])
    ->middleware('throttle:exam-submit')
    ->name('exams.finish');

Route::get('/exams/{examSession}/sections/{position}/result', [ExamSessionController::class, 'sectionResult'])
    ->whereNumber('position')
    ->name('exams.sections.result');

Route::get('/exams/{examSession}/result', [ExamSessionController::class, 'result'])->name('exams.result');

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

    Route::get('past-exams', [Admin\PastExamController::class, 'index'])->name('past-exams.index');
    Route::post('past-exams/sync', [Admin\PastExamController::class, 'sync'])
        ->middleware('throttle:6,1')
        ->name('past-exams.sync');
    Route::post('past-exams/{pastExam}/import', [Admin\PastExamController::class, 'import'])->name('past-exams.import');
    Route::post('past-exam-sections/{section}/retry', [Admin\PastExamController::class, 'retrySection'])->name('past-exam-sections.retry');

    Route::get('settings/ai', [Admin\AiSettingsController::class, 'edit'])->name('settings.ai.edit');
    Route::put('settings/ai', [Admin\AiSettingsController::class, 'update'])->name('settings.ai.update');
    Route::post('settings/ai/test', [Admin\AiSettingsController::class, 'test'])
        ->middleware('throttle:6,1')
        ->name('settings.ai.test');

    Route::get('profile', [Admin\ProfileController::class, 'edit'])->name('profile.edit');
});
