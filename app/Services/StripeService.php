<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeService
{
    /**
     * Monedas permitidas (coinciden con las opciones del formulario de checkout).
     */
    private const MONEDAS_PERMITIDAS = ['usd', 'eur'];

    /**
     * Crea un PaymentIntent en Stripe y devuelve su clientSecret.
     *
     * @throws InvalidArgumentException si los datos no son válidos (-> HTTP 400)
     * @throws RuntimeException si falla la comunicación con Stripe (-> HTTP 500)
     */
    public function createPaymentIntent(float|int|string $amount, string $currency, ?string $description = null): string
    {
        // 1. Validar los datos antes de llamar a Stripe
        if (!is_numeric($amount) || (float) $amount <= 0) {
            throw new InvalidArgumentException('El monto debe ser un número mayor que cero.');
        }

        $currency = strtolower(trim($currency));

        if (!in_array($currency, self::MONEDAS_PERMITIDAS, true)) {
            throw new InvalidArgumentException('Moneda no permitida. Use: ' . implode(', ', self::MONEDAS_PERMITIDAS) . '.');
        }

        $secret = config('services.stripe.secret');

        if (empty($secret)) {
            throw new RuntimeException('Falta configurar STRIPE_SECRET en el archivo .env.');
        }

        // 2. Stripe trabaja con la unidad mínima de la moneda (centavos)
        $amountInCents = (int) round((float) $amount * 100);

        try {
            Stripe::setApiKey($secret);

            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => $currency,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'description' => $description ?: 'Pago de prueba',
                ],
            ]);

            return $paymentIntent->client_secret;
        } catch (InvalidRequestException $e) {
            // Stripe rechazó los datos (por ejemplo, un monto por debajo de su mínimo)
            throw new InvalidArgumentException($e->getMessage());
        } catch (ApiErrorException $e) {
            // Cualquier otro error de la API de Stripe (red, autenticación, etc.)
            Log::error('Error de Stripe al crear el PaymentIntent: ' . $e->getMessage());
            throw new RuntimeException('No se pudo crear el pago con Stripe.');
        }
    }

    /**
     * Verifica la firma del webhook y devuelve el evento de Stripe.
     *
     * @throws InvalidArgumentException si falta la firma o es inválida (-> HTTP 400)
     * @throws RuntimeException si falta configurar el secreto del webhook (-> HTTP 500)
     */
    public function constructWebhookEvent(string $payload, ?string $signatureHeader): Event
    {
        $endpointSecret = config('services.stripe.webhook_secret');

        if (empty($endpointSecret)) {
            throw new RuntimeException('Falta configurar STRIPE_WEBHOOK_SECRET en el archivo .env.');
        }

        if (empty($signatureHeader)) {
            throw new InvalidArgumentException('Falta la cabecera Stripe-Signature.');
        }

        try {
            return Webhook::constructEvent($payload, $signatureHeader, $endpointSecret);
        } catch (UnexpectedValueException | SignatureVerificationException $e) {
            throw new InvalidArgumentException('Webhook inválido: ' . $e->getMessage());
        }
    }
}