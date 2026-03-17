<?php

use App\Http\Controllers\Api\AdminUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update']);
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy']);
});
