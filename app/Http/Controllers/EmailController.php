<?php

namespace App\Http\Controllers;

use App\Services\SendGridService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class EmailController extends Controller
{
    /**
     * Muestra la vista del formulario de envío de correo.
     */
    public function index()
    {
        return view('envio_email');
    }

    /**
     * Valida el formulario y envía el correo mediante el servicio de SendGrid.
     */
    public function send(Request $request, SendGridService $sendGridService): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ], [
            'email.required' => 'El correo destino es obligatorio.',
            'email.email' => 'El correo destino no tiene un formato válido.',
            'subject.required' => 'El asunto es obligatorio.',
            'subject.max' => 'El asunto no puede superar los 255 caracteres.',
            'message.required' => 'El mensaje es obligatorio.',
        ]);

        try {
            // La comunicación con SendGrid ocurre dentro del servicio
            $sendGridService->sendEmail(
                $request->input('email'),
                $request->input('subject'),
                nl2br(e($request->input('message')))
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('status', 'Correo enviado con éxito a través de la API de SendGrid.');
    }
}