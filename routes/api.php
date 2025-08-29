<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;

use \App\Http\Controllers\PaymentController;
use \App\Http\Controllers\LineAccountController;
use \App\Http\Controllers\User\SubscriptionController;
use \App\Http\Controllers\HomeController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/home', [HomeController::class, 'home'])->name('home');
Route::post('/fincode_success', [PaymentController::class, 'fincode_success'])->name('fincode_success');
Route::post('/fincode_cancel', [PaymentController::class, 'fincode_cancel'])->name('fincode_cancel');
Route::post('/purchase/card_register_callback', [PaymentController::class, 'card_register_callback'])->name('purchase.card_register_callback');
Route::post('/subscription/card_register_callback', [PaymentController::class, 'card_register_callback_subscription'])->name('subscription.card_register_callback');

Route::post('/applepay-session/validate', [PaymentController::class, 'apple_pay_validate'])->name('apple_pay_validate');
Route::post('/amazonpay/ipn', [PaymentController::class, 'handleAmazonpayIPN']);

Route::post('/webhook', [PaymentController::class, 'webhook'])->name('webhook');
Route::post('/webhook/gmo', [PaymentController::class, 'webhook_gmo'])->name('webhook_gmo');
Route::post('/line/webhook', [LineAccountController::class, 'friend_request'])->name('line_webhook');
Route::post('/tds2_ret_url', [PaymentController::class, 'tds2_ret_url'])->name('tds2_ret_url');
Route::post('/tds2_ret_url_googlepay', [PaymentController::class, 'tds2_ret_url_googlepay'])->name('tds2_ret_url_googlepay');