<?php

use App\Http\Controllers\StripePayGateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::controller(StripePayGateController::class)->group(function () {
    Route::post('/gate', 'makePayment')->name('makePayment')->middleware('signed');//signed url, where payment and connection with stripe is create.
    Route::post('/update', 'updateEndpoint');//stripe endpoint for update payment and log status
    Route::get('/pay-expiration/{id}', 'payExpiration');//for verification, if payment is not expired
});