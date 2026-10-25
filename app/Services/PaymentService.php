<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Database\UniqueConstraintViolationException;

class PaymentService
{
    /**
     * Registra un pago exitoso en MySQL. Es idempotente: si el pago ya existe, devuelve el registro existente.
     */
    public function registerSuccessfulPayment(object $paymentIntent, string $eventId): Payment
    {
        $datos = [
            'stripe_event_id' => $eventId,
            'amount' => $paymentIntent->amount,
            'currency' => strtolower($paymentIntent->currency),
            'status' => $paymentIntent->status ?? 'succeeded',
            'description' => $paymentIntent->metadata['description'] ?? null,
            'paid_at' => now(),
        ];

        try {
            // Si ya existe un registro con este PaymentIntent, no se crea otro
            return Payment::firstOrCreate(
                ['stripe_payment_intent_id' => $paymentIntent->id],
                $datos
            );
        } catch (UniqueConstraintViolationException $e) {
            // Dos reintentos simultáneos: el otro ganó la carrera, devolvemos su registro
            return Payment::where('stripe_payment_intent_id', $paymentIntent->id)->firstOrFail();
        }
    }
}