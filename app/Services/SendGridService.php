<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendGridService
{
    private const API_URL = 'https://api.sendgrid.com/v3/mail/send';

    /**
     * Envía un correo electrónico utilizando la API REST v3 de SendGrid.
     *
     * @return bool true si SendGrid aceptó el correo
     * @throws RuntimeException con un mensaje claro si algo falla
     */
    public function sendEmail(string $to, string $subject, string $content): bool
    {
        $apiKey = config('services.sendgrid.key');
        $fromAddress = config('mail.from.address');

        if (empty($apiKey)) {
            throw new RuntimeException('Falta configurar SENDGRID_API_KEY en el archivo .env.');
        }

        if (empty($fromAddress)) {
            throw new RuntimeException('Falta configurar MAIL_FROM_ADDRESS en el archivo .env.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(15)
                ->post(self::API_URL, [
                    'personalizations' => [
                        [
                            'to' => [
                                ['email' => $to],
                            ],
                        ],
                    ],
                    'from' => [
                        'email' => $fromAddress,
                        'name' => config('mail.from.name'),
                    ],
                    'subject' => $subject,
                    'content' => [
                        [
                            'type' => 'text/html',
                            'value' => $content,
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::error('No se pudo conectar con SendGrid: ' . $e->getMessage());
            throw new RuntimeException('No se pudo conectar con SendGrid. Revisa tu conexión a internet.');
        }

        // SendGrid responde 202 cuando acepta el correo
        if ($response->successful()) {
            return true;
        }

        // Guardamos el detalle técnico en el log, no se lo mostramos al usuario
        Log::error('SendGrid respondió con un error', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new RuntimeException(match ($response->status()) {
            400 => 'SendGrid rechazó los datos del correo. Revisa el destinatario, el asunto y el mensaje.',
            401 => 'La API key de SendGrid no es válida.',
            403 => 'SendGrid rechazó la petición: el remitente no está verificado o la API key no tiene permiso para enviar correos.',
            429 => 'Se superó el límite de envíos de SendGrid. Intenta más tarde.',
            default => 'SendGrid no pudo enviar el correo (código ' . $response->status() . ').',
        });
    }
}