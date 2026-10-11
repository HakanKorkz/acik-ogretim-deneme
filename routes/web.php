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

    Route::post('courses/{course}/questions', [Admin\CourseQuestionController::class, 'store'])
        ->middleware('throttle:question-generation')
        ->name('courses.questions.store');

    Route::get('profile', [Admin\ProfileController::class, 'edit'])->name('profile.edit');
});
