<?php

use App\Http\Controllers\Api\GoogleMeet\GoogleMeetController;
use App\Http\Controllers\Ui\FrontendController;
use Illuminate\Support\Facades\Route;


Route::get('/sign-in', [FrontendController::class, 'signIn']);
// Route::get('/register', [FrontendController::class, 'register']);
Route::get('/forgot-password', [FrontendController::class, 'forgotPassword']);
Route::get('/reset-password/{token}', [FrontendController::class, 'resetPassword']);
Route::get('/', [FrontendController::class, 'index'])->defaults('layoutContext', 'frontend');

