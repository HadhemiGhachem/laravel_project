<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\QrExcelController;

Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LogoutController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

Route::get('/qr-code', [QrCodeController::class, 'show']);

Route::get('/import-qrcode', [QrExcelController::class, 'import']);


Route::get('/dechiffrer', [QrExcelController::class, 'formDechiffrement']);
Route::post('/dechiffrer', [QrExcelController::class, 'dechiffrer']);


Route::get('/auth/google', [LoginController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [LoginController::class, 'handleGoogleCallback']);



