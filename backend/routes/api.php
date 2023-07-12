<?php

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserAuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::get('/tz',function(){
    
});

Route::controller(UserAuthController::class)->group(function(){
    Route::post('/confirm_otp','confirmOtp');
    Route::post('/forgot_password','forgotPassword');
    Route::post('/reset_password','resetPassword');
    // checked
    Route::post('/login','login');

    Route::post('/register','register');
    Route::post('/resend_otp','resendOtp');

});