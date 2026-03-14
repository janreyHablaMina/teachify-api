<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/register-student', [AuthController::class, 'registerStudent']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/users', [\App\Http\Controllers\Api\AdminUserController::class, 'index']);
    Route::put('/admin/users/{user}', [\App\Http\Controllers\Api\AdminUserController::class, 'update']);
    Route::delete('/admin/users/{user}', [\App\Http\Controllers\Api\AdminUserController::class, 'destroy']);

    Route::get('/summaries', [\App\Http\Controllers\Api\SummaryController::class, 'index']);
    Route::post('/summaries/generate', [\App\Http\Controllers\Api\SummaryController::class, 'generate']);
    Route::get('/summaries/{summary}/export-pdf', [\App\Http\Controllers\Api\SummaryController::class, 'exportPdf']);

    Route::get('/quizzes', [\App\Http\Controllers\Api\QuizController::class, 'index']);
    Route::get('/quizzes/{quiz}', [\App\Http\Controllers\Api\QuizController::class, 'show']);
    Route::delete('/quizzes/{quiz}', [\App\Http\Controllers\Api\QuizController::class, 'destroy']);
    Route::post('/quizzes/{quiz}/duplicate', [\App\Http\Controllers\Api\QuizController::class, 'duplicate']);
    Route::get('/quizzes/{quiz}/export-pdf', [\App\Http\Controllers\Api\QuizController::class, 'exportPdf']);
    Route::post('/quizzes/generate-from-upload', [\App\Http\Controllers\Api\QuizController::class, 'generateFromUpload']);

    Route::get('/classrooms', [\App\Http\Controllers\Api\ClassroomController::class, 'index']);
    Route::post('/classrooms', [\App\Http\Controllers\Api\ClassroomController::class, 'store']);
    Route::get('/classrooms/{classroom}', [\App\Http\Controllers\Api\ClassroomController::class, 'show']);
    Route::delete('/classrooms/{classroom}', [\App\Http\Controllers\Api\ClassroomController::class, 'destroy']);
    Route::patch('/classrooms/{classroom}/invite-expiration', [\App\Http\Controllers\Api\ClassroomController::class, 'updateInviteExpiration']);
    Route::post('/classrooms/join-by-code', [\App\Http\Controllers\Api\ClassroomController::class, 'joinByCode']);
});
