<?php

use App\Http\Controllers\EmailController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Stripe: checkout y creación del PaymentIntent
Route::get('/checkout', [PaymentController::class, 'showCheckout'])->name('checkout');
Route::post('/create-payment-intent', [PaymentController::class, 'createPaymentIntent'])->name('payment-intent.create');

// Stripe: webhook (excluido de CSRF en bootstrap/app.php)
Route::post('/stripe/webhook', [PaymentController::class, 'handleWebhook'])->name('stripe.webhook');

// SendGrid: formulario y envío de correo
Route::get('/email', [EmailController::class, 'index'])->name('email.index');
Route::post('/email', [EmailController::class, 'send'])->name('email.send');