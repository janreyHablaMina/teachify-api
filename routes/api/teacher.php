<?php

use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\GenerationUsageController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\SummaryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/summaries', [SummaryController::class, 'index']);
    Route::post('/summaries', [SummaryController::class, 'store']);
    Route::delete('/summaries/{summary}', [SummaryController::class, 'destroy']);
    Route::post('/summaries/generate', [SummaryController::class, 'generate']);
    Route::get('/summaries/{summary}/export-pdf', [SummaryController::class, 'exportPdf']);
    Route::post('/generation-usage/consume', [GenerationUsageController::class, 'consume']);

    Route::get('/quizzes', [QuizController::class, 'index']);
    Route::post('/quizzes', [QuizController::class, 'store']);
    Route::get('/quizzes/{quiz}', [QuizController::class, 'show']);
    Route::delete('/quizzes/{quiz}', [QuizController::class, 'destroy']);
    Route::post('/quizzes/{quiz}/duplicate', [QuizController::class, 'duplicate']);
    Route::get('/quizzes/{quiz}/export-pdf', [QuizController::class, 'exportPdf']);
    Route::post('/quizzes/generate-from-upload', [QuizController::class, 'generateFromUpload']);

    Route::get('/classrooms', [ClassroomController::class, 'index']);
    Route::post('/classrooms', [ClassroomController::class, 'store']);
    Route::put('/classrooms/{classroom}', [ClassroomController::class, 'update']);
    Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show']);
    Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy']);
    Route::patch('/classrooms/{classroom}/invite-expiration', [ClassroomController::class, 'updateInviteExpiration']);
    Route::post('/classrooms/{classroom}/students/{student}/approve', [ClassroomController::class, 'approveStudent']);
    Route::post('/classrooms/{classroom}/students/{student}/reject', [ClassroomController::class, 'rejectStudent']);
    Route::patch('/classrooms/{classroom}/students/{student}/status', [ClassroomController::class, 'updateStudentStatus']);
    Route::post('/classrooms/{classroom}/assignments', [AssignmentController::class, 'store']);
    Route::post('/assignments', [AssignmentController::class, 'storeDirect']);
    Route::get('/assignments', [AssignmentController::class, 'index']);
    Route::get('/assignments/{assignment}', [AssignmentController::class, 'show']);
    Route::post('/assignments/{assignment}/submit', [SubmissionController::class, 'store']);
    Route::post('/classrooms/join-by-code', [ClassroomController::class, 'joinByCode']);
});

