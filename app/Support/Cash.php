<?php

namespace App\Support;

use App\Models\Caja;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turnos de caja (§9.4): apertura, movimientos y cierre con arqueo.
 * Todo cobro o pago que pasa por una caja queda como CashMovement.
 */
class Cash
{
    /** ¿La veterinaria trabaja con caja? (módulo `caja` del plan) */
    public static function enabled(): bool
    {
        return (bool) app(Tenancy::class)->company()?->planAllows('caja');
    }

    public static function open(Caja $caja, float $openingAmount, ?string $notes = null): CashSession
    {
        return DB::transaction(function () use ($caja, $openingAmount, $notes) {
            // Bloquea la caja para que dos personas no la abran a la vez.
            Caja::whereKey($caja->id)->lockForUpdate()->first();

            if ($caja->openSession()) {
                throw ValidationException::withMessages(['caja_id' => "La caja {$caja->name} ya está abierta."]);
            }

            return CashSession::create([
                'company_id' => $caja->company_id,
                'caja_id' => $caja->id,
                'status' => 'open',
                'opened_by' => auth()->id(),
                'opened_at' => now(),
                'opening_amount' => $openingAmount,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Movimiento de dinero del turno. `amount` con signo: + entra, − sale.
     */
    public static function record(CashSession $session, string $type, ?PaymentMethod $method, float $amount, string $description, ?Model $source = null): CashMovement
    {
        if (! $session->isOpen()) {
            throw ValidationException::withMessages(['cash_session_id' => 'La caja está cerrada.']);
        }

        return CashMovement::create([
            'company_id' => $session->company_id,
            'cash_session_id' => $session->id,
            'type' => $type,
            'payment_method_id' => $method?->id,
            'amount' => round($amount, 2),
            'description' => $description,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'user_id' => auth()->id(),
        ]);
    }

    /** Cierre con arqueo: compara lo contado con el efectivo esperado. */
    public static function close(CashSession $session, float $countedCash, ?string $notes = null): CashSession
    {
        if (! $session->isOpen()) {
            throw ValidationException::withMessages(['counted_cash' => 'Este turno ya está cerrado.']);
        }

        $expected = $session->expectedCash();

        $session->update([
            'status' => 'closed',
            'closed_by' => auth()->id(),
            'closed_at' => now(),
            'expected_cash' => $expected,
            'counted_cash' => $countedCash,
            'difference' => round($countedCash - $expected, 2),
            'notes' => trim(($session->notes ? $session->notes . "\n" : '') . ($notes ?? '')) ?: null,
        ]);

        return $session;
    }

    /**
     * Turno que debe usar una operación con dinero. Con el módulo de caja es
     * obligatorio y debe estar abierto; sin él, no se usa caja.
     */
    public static function sessionFor(?int $sessionId): ?CashSession
    {
        if (! self::enabled()) {
            return null;
        }

        $session = $sessionId ? CashSession::find($sessionId) : null;

        if (! $session || ! $session->isOpen()) {
            throw ValidationException::withMessages(['cash_session_id' => 'Abre una caja para registrar cobros y pagos.']);
        }

        return $session;
    }

    /** Turnos abiertos de la veterinaria, para elegir dónde cobrar. */
    public static function openSessions()
    {
        return CashSession::with('caja')->where('status', 'open')->orderBy('opened_at')->get();
    }
}
