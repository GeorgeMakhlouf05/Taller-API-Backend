<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Checkout - CloudSpace Solutions</title>
    <!-- Tailwind CSS CDN para estilos rápidos -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Stripe.js SDK -->
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-lg shadow-md p-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Checkout de Prueba</h2>

        <form id="payment-form">
            <div class="mb-4">
                <label for="amount" class="block text-gray-700 font-medium mb-2">Monto a pagar</label>
                <div class="flex">
                    <span class="inline-flex items-center px-3 text-sm text-gray-900 bg-gray-200 border border-r-0 border-gray-300 rounded-l-md">$</span>
                    <input type="number" id="amount" name="amount" step="0.01" min="0.5" value="10.00" class="rounded-none rounded-r-md bg-gray-50 border border-gray-300 text-gray-900 block flex-1 min-w-0 w-full text-sm p-2.5" required>
                </div>
            </div>

            <div class="mb-6">
                <label for="currency" class="block text-gray-700 font-medium mb-2">Moneda</label>
                <select id="currency" name="currency" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg block w-full p-2.5">
                    <option value="usd">USD - Dólares estadounidenses</option>
                    <option value="eur">EUR - Euros</option>
                </select>
            </div>

            <button type="button" id="init-payment-btn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-200 mb-4">
                Generar Formulario de Pago
            </button>

            <!-- Aquí Stripe dibuja el formulario seguro de tarjeta -->
            <div id="payment-element-container" class="hidden my-4">
                <div id="payment-element" class="mb-4"></div>

                <button type="submit" id="submit-btn" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 px-4 rounded transition duration-200">
                    Pagar Ahora
                </button>
            </div>

            <div id="payment-message" class="hidden mt-4 p-3 rounded text-sm text-center"></div>
        </form>

        <p class="text-center text-sm mt-4"><a href="/checkout" class="text-blue-600 hover:underline">Nuevo pago</a></p>
    </div>

    <script>
        // Solo se usa la clave PÚBLICA en el navegador (la secreta nunca sale del servidor)
        const stripe = Stripe("{{ config('services.stripe.key') }}");
        let elements;

        const amountInput = document.getElementById('amount');
        const currencySelect = document.getElementById('currency');
        const initBtn = document.getElementById('init-payment-btn');
        const paymentElementContainer = document.getElementById('payment-element-container');
        const paymentForm = document.getElementById('payment-form');
        const paymentMessage = document.getElementById('payment-message');
        const submitBtn = document.getElementById('submit-btn');

        // Paso 1: pedir al backend que cree el PaymentIntent y mostrar Stripe Elements
        initBtn.addEventListener('click', async () => {
            const amount = amountInput.value;
            const currency = currencySelect.value;

            if (!amount || parseFloat(amount) <= 0) {
                showMessage('Por favor ingrese un monto válido.', true);
                return;
            }

            initBtn.disabled = true;
            initBtn.innerText = 'Cargando...';

            try {
                const response = await fetch('/create-payment-intent', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ amount, currency })
                });

                const data = await response.json();

                if (!response.ok || data.error) {
                    showMessage(data.error || 'No se pudo crear el pago.', true);
                    initBtn.disabled = false;
                    initBtn.innerText = 'Generar Formulario de Pago';
                    return;
                }

                // Inicializar Stripe Elements con el clientSecret recibido
                elements = stripe.elements({ clientSecret: data.clientSecret });
                const paymentElement = elements.create('payment');
                paymentElement.mount('#payment-element');

                // El PaymentIntent ya tiene un monto fijo: bloqueamos los campos
                amountInput.disabled = true;
                currencySelect.disabled = true;

                paymentElementContainer.classList.remove('hidden');
                initBtn.classList.add('hidden');
                paymentMessage.classList.add('hidden');

            } catch (err) {
                showMessage('Error de conexión con el servidor.', true);
                initBtn.disabled = false;
                initBtn.innerText = 'Generar Formulario de Pago';
            }
        });

        // Paso 2: confirmar el pago directamente con Stripe
        paymentForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            submitBtn.disabled = true;
            submitBtn.innerText = 'Procesando...';

            const { error, paymentIntent } = await stripe.confirmPayment({
                elements,
                confirmParams: {
                    return_url: window.location.href, // Solo se usa si el método de pago exige redirección
                },
                redirect: 'if_required' // Confirma sin recargar la página cuando es posible
            });

            if (error) {
                showMessage(error.message, true);
                submitBtn.disabled = false;
                submitBtn.innerText = 'Pagar Ahora';
            } else if (paymentIntent && paymentIntent.status === 'succeeded') {
                // Este mensaje es solo informativo: el registro oficial lo hace el webhook en el servidor
                showMessage('¡Pago enviado con éxito! El servidor registrará la confirmación de Stripe.', false);
                paymentElementContainer.classList.add('hidden');
            } else {
                showMessage('Tu pago se está procesando. Estado: ' + (paymentIntent ? paymentIntent.status : 'desconocido'), false);
                paymentElementContainer.classList.add('hidden');
            }
        });

        function showMessage(messageText, isError = false) {
            paymentMessage.classList.remove('hidden', 'bg-red-100', 'text-red-700', 'bg-green-100', 'text-green-700');
            paymentMessage.classList.add(...(isError ? ['bg-red-100', 'text-red-700'] : ['bg-green-100', 'text-green-700']));
            paymentMessage.innerText = messageText;
        }
    </script>
</body>
</html>