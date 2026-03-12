<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/summaries', [\App\Http\Controllers\Api\SummaryController::class, 'index']);
    Route::post('/summaries/generate', [\App\Http\Controllers\Api\SummaryController::class, 'generate']);
    Route::get('/summaries/{summary}/export-pdf', [\App\Http\Controllers\Api\SummaryController::class, 'exportPdf']);

    Route::get('/quizzes', [\App\Http\Controllers\Api\QuizController::class, 'index']);
    Route::get('/quizzes/{quiz}', [\App\Http\Controllers\Api\QuizController::class, 'show']);
    Route::get('/quizzes/{quiz}/export-pdf', [\App\Http\Controllers\Api\QuizController::class, 'exportPdf']);
    Route::post('/quizzes/generate-from-upload', [\App\Http\Controllers\Api\QuizController::class, 'generateFromUpload']);
});
