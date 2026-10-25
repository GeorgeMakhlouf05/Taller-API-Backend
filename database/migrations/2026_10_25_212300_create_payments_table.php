<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Único: evita registrar dos veces el mismo pago si Stripe reintenta el webhook
            $table->string('stripe_payment_intent_id')->unique();
            $table->string('stripe_event_id')->nullable();
            // Monto en la unidad mínima de la moneda (centavos), igual que lo maneja Stripe
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->string('status', 30);
            $table->string('description')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};