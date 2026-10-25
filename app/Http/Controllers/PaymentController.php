<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PaymentController extends Controller
{
    /**
     * Muestra la vista del formulario de checkout.
     */
    public function showCheckout()
    {
        return view('checkout');
    }

    /**
     * Crea un PaymentIntent (delegando en StripeService) y devuelve el clientSecret en JSON.
     */
    public function createPaymentIntent(Request $request, StripeService $stripeService): JsonResponse
    {
        // El controlador solo valida la forma de la petición
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'description' => 'nullable|string|max:255',
        ], [
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser un número.',
            'amount.min' => 'El monto debe ser mayor que cero.',
            'currency.required' => 'La moneda es obligatoria.',
            'currency.size' => 'La moneda debe tener 3 letras (por ejemplo, usd).',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        try {
            // La comunicación con Stripe ocurre dentro del servicio
            $clientSecret = $stripeService->createPaymentIntent(
                $request->input('amount'),
                $request->input('currency'),
                $request->input('description')
            );

            return response()->json(['clientSecret' => $clientSecret], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Recibe las notificaciones de Stripe, verifica la firma y registra los pagos exitosos.
     */
    public function handleWebhook(Request $request, StripeService $stripeService, PaymentService $paymentService): JsonResponse
    {
        // 1. Verificar que el mensaje realmente viene de Stripe
        try {
            $event = $stripeService->constructWebhookEvent(
                $request->getContent(),
                $request->header('Stripe-Signature')
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        // 2. Registrar el pago solo si el evento es de pago exitoso
        if ($event->type === 'payment_intent.succeeded') {
            try {
                $payment = $paymentService->registerSuccessfulPayment($event->data->object, $event->id);

                Log::info($payment->wasRecentlyCreated
                    ? "Pago registrado vía webhook: {$payment->stripe_payment_intent_id}"
                    : "Webhook repetido, el pago ya existía: {$payment->stripe_payment_intent_id}");
            } catch (Throwable $e) {
                // Respondemos 500 para que Stripe reintente el envío más tarde
                Log::error('No se pudo registrar el pago del webhook: ' . $e->getMessage());

                return response()->json(['error' => 'No se pudo registrar el pago.'], 500);
            }
        }

        // 3. Todo en orden (o evento que no nos interesa): 200 OK
        return response()->json(['status' => 'success'], 200);
    }
}