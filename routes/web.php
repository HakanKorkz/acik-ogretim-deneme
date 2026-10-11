<?php

use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseQuestionController;
use App\Http\Controllers\ExamSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CourseController::class, 'index'])->name('courses.index');

Route::post('/courses/{course}/questions', [CourseQuestionController::class, 'store'])
    ->middleware('throttle:question-generation')
    ->name('courses.questions.store');

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
