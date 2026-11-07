<?php

namespace App\Support;

use App\Models\Hospitalization;
use App\Models\HospitalizationDose;
use App\Models\HospitalizationTreatment;
use App\Models\Kennel;
use App\Models\Pet;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hospitalización (§10): admisión, tomas programadas y alta. Mantiene las
 * reglas que cruzan tablas: un paciente y un espacio por internación activa,
 * y cada toma con producto descuenta stock y deja su costo.
 */
class Hospitalizations
{
    /** Tope de tomas por tratamiento: evita programar por error miles de filas. */
    public const MAX_DOSES = 200;

    /** @param  array<string, mixed>  $data */
    public static function admit(array $data): Hospitalization
    {
        return DB::transaction(function () use ($data) {
            $pet = Pet::lockForUpdate()->findOrFail($data['pet_id']);

            if (Hospitalization::where('pet_id', $pet->id)->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['pet_id' => "{$pet->name} ya está internado."]);
            }

            self::ensureKennelFree($data['kennel_id'] ?? null);

            return Hospitalization::create($data + [
                'company_id' => $pet->company_id,
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);
        });
    }

    public static function moveToKennel(Hospitalization $hospitalization, ?int $kennelId): void
    {
        self::ensureActive($hospitalization);

        DB::transaction(function () use ($hospitalization, $kennelId) {
            self::ensureKennelFree($kennelId, $hospitalization->id);
            $hospitalization->update(['kennel_id' => $kennelId]);
        });
    }

    /**
     * Programa el medicamento y genera sus tomas: desde el inicio, cada
     * `frequency_hours`, hasta el fin inclusive.
     *
     * @param  array<string, mixed>  $data
     */
    public static function addTreatment(Hospitalization $hospitalization, array $data): HospitalizationTreatment
    {
        self::ensureActive($hospitalization);

        $slots = self::schedule($data['starts_at'], $data['ends_at'], (int) $data['frequency_hours']);

        if (count($slots) > self::MAX_DOSES) {
            throw ValidationException::withMessages(['ends_at' => 'El tratamiento generaría más de ' . self::MAX_DOSES . ' tomas: revisa la frecuencia y las fechas.']);
        }

        return DB::transaction(function () use ($hospitalization, $data, $slots) {
            $treatment = $hospitalization->treatments()->create($data + [
                'company_id' => $hospitalization->company_id,
                'created_by' => auth()->id(),
            ]);

            foreach ($slots as $slot) {
                $treatment->doses()->create([
                    'company_id' => $hospitalization->company_id,
                    'scheduled_at' => $slot,
                    'status' => 'pending',
                ]);
            }

            return $treatment;
        });
    }

    /** Suspende el tratamiento: lo aplicado queda, lo pendiente se cancela. */
    public static function stopTreatment(HospitalizationTreatment $treatment): void
    {
        self::ensureActive($treatment->hospitalization);

        DB::transaction(function () use ($treatment) {
            $treatment->update(['stopped_at' => now()]);
            $treatment->doses()->reorder()->where('status', 'pending')->update(['status' => 'cancelled']);
        });
    }

    /**
     * Registra la toma aplicada. Con producto vinculado sale del inventario:
     * ya se aplicó, así que un faltante se registra y queda en negativo.
     */
    public static function giveDose(HospitalizationDose $dose, ?string $notes = null): void
    {
        DB::transaction(function () use ($dose, $notes) {
            $dose = HospitalizationDose::lockForUpdate()->with('treatment.hospitalization.pet', 'treatment.product')->findOrFail($dose->id);
            $treatment = $dose->treatment;
            $hospitalization = $treatment->hospitalization;

            self::ensureActive($hospitalization);
            self::ensurePending($dose);

            $dose->update(['status' => 'given', 'done_at' => now(), 'done_by' => auth()->id(), 'notes' => $notes]);

            $product = $treatment->product;
            $quantity = (float) $treatment->stock_quantity;

            if ($product && $quantity > 0 && app(Tenancy::class)->company()?->planAllows('inventario')) {
                $branch = $hospitalization->kennel?->branch ?? Stock::defaultBranch($hospitalization->company_id);

                Stock::consume($product, $branch, $quantity, 'hospitalization', [
                    'source' => $dose,
                    'allow_negative' => true,
                    'notes' => "{$hospitalization->code} · {$hospitalization->pet?->name}",
                ]);

                $hospitalization->charges()->create([
                    'company_id' => $hospitalization->company_id,
                    'hospitalization_dose_id' => $dose->id,
                    'description' => "{$treatment->medication} · toma del " . $dose->scheduled_at->format('d/m H:i'),
                    'quantity' => $quantity,
                    'unit_price' => $product->sale_price,
                    'total' => round($quantity * (float) $product->sale_price, 2),
                    'created_by' => auth()->id(),
                ]);
            }
        });
    }

    public static function skipDose(HospitalizationDose $dose, string $reason): void
    {
        self::ensureActive($dose->treatment->hospitalization);
        self::ensurePending($dose);

        $dose->update(['status' => 'skipped', 'done_at' => now(), 'done_by' => auth()->id(), 'notes' => $reason]);
    }

    /**
     * Alta médica o fallecimiento: cierra la internación, cancela lo pendiente
     * y libera el espacio (solo cuentan las internaciones activas).
     *
     * @param  array<string, mixed>  $data
     */
    public static function discharge(Hospitalization $hospitalization, string $outcome, array $data): void
    {
        self::ensureActive($hospitalization);

        DB::transaction(function () use ($hospitalization, $outcome, $data) {
            self::cancelPendingDoses($hospitalization);

            $hospitalization->update($data + ['status' => $outcome, 'discharged_by' => auth()->id()]);

            if ($outcome === 'deceased') {
                $hospitalization->pet?->update(['status' => 'deceased']);
            }
        });
    }

    /** Anula una admisión hecha por error: solo si todavía no se atendió al paciente. */
    public static function cancel(Hospitalization $hospitalization, string $reason): void
    {
        self::ensureActive($hospitalization);

        $worked = $hospitalization->records()->exists()
            || $hospitalization->doses()->where('hospitalization_doses.status', 'given')->exists();

        if ($worked) {
            throw ValidationException::withMessages(['cancel_reason' => 'La internación ya tiene registros o tomas aplicadas: corresponde darle el alta.']);
        }

        DB::transaction(function () use ($hospitalization, $reason) {
            self::cancelPendingDoses($hospitalization);
            $hospitalization->update(['status' => 'cancelled', 'cancel_reason' => $reason]);
        });
    }

    /** @return array<int, CarbonInterface> */
    protected static function schedule(CarbonInterface $start, CarbonInterface $end, int $frequencyHours): array
    {
        $slots = [];

        for ($slot = $start->copy(); $slot->lte($end) && count($slots) <= self::MAX_DOSES; $slot = $slot->copy()->addHours($frequencyHours)) {
            $slots[] = $slot;
        }

        return $slots;
    }

    protected static function cancelPendingDoses(Hospitalization $hospitalization): void
    {
        HospitalizationDose::whereIn('hospitalization_treatment_id', $hospitalization->treatments()->reorder()->pluck('id'))
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    protected static function ensureKennelFree(?int $kennelId, ?int $exceptId = null): void
    {
        if (! $kennelId) {
            return;
        }

        $kennel = Kennel::lockForUpdate()->findOrFail($kennelId);
        $occupied = Hospitalization::where('kennel_id', $kennelId)->where('status', 'active')
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists();

        if ($occupied) {
            throw ValidationException::withMessages(['kennel_id' => "{$kennel->name} está ocupado."]);
        }
    }

    protected static function ensureActive(Hospitalization $hospitalization): void
    {
        if (! $hospitalization->isActive()) {
            throw ValidationException::withMessages(['error' => 'La internación está cerrada: ya no admite cambios.']);
        }
    }

    protected static function ensurePending(HospitalizationDose $dose): void
    {
        if ($dose->status !== 'pending') {
            throw ValidationException::withMessages(['error' => "Esta toma ya está {$dose->status_label}."]);
        }
    }
}
