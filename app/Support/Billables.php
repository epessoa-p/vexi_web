<?php

namespace App\Support;

use App\Models\Consultation;
use App\Models\Hospitalization;
use App\Models\LabRequest;
use App\Models\Pet;
use App\Models\Service;
use Illuminate\Support\Collection;

/**
 * Lo atendido a una mascota que todavía no se cobró. Cada registro se cobra una
 * sola vez: su `sale_id` lo enlaza a la venta y anularla lo deja otra vez pendiente.
 */
class Billables
{
    /** Columna de sale_items => [modelo, nombre para los mensajes]. */
    public const LINKS = [
        'consultation_id' => [Consultation::class, 'consultas'],
        'lab_request_id' => [LabRequest::class, 'estudios'],
        'hospitalization_id' => [Hospitalization::class, 'internaciones'],
    ];

    /** @return Collection<int, array{key: string, label: string, lines: Collection}> */
    public static function forPet(Pet $pet): Collection
    {
        $withInventory = (bool) app(Tenancy::class)->company()?->planAllows('inventario');

        return self::consultations($pet, $withInventory)
            ->concat(self::labRequests($pet))
            ->concat(self::hospitalizations($pet, $withInventory))
            ->values();
    }

    /** El servicio de la consulta, sus procedimientos, insumos y lo dispensado (ya salieron del stock). */
    protected static function consultations(Pet $pet, bool $withInventory): Collection
    {
        $default = Service::where('active', true)->where('category', 'consulta')->orderBy('id')->first();

        return Consultation::with(['appointment.service', 'procedures', 'supplies.product', 'prescriptionItems.product', 'diagnoses'])
            ->where('pet_id', $pet->id)->where('status', 'finished')->whereNull('sale_id')
            ->latest('started_at')->get()
            ->map(function (Consultation $c) use ($default, $withInventory) {
                $service = $c->appointment?->service ?? $default;
                $lines = collect([[
                    'type' => 'consultation',
                    'consultation_id' => $c->id,
                    'service_id' => $service?->id,
                    'description' => 'Consulta '.$c->started_at->format('d/m').($service ? " · {$service->name}" : ''),
                    'quantity' => 1,
                    'unit_price' => (float) ($service?->price ?? 0),
                ]]);

                foreach ($c->procedures as $procedure) {
                    $lines->push(['type' => 'consultation', 'consultation_id' => $c->id, 'description' => "Procedimiento: {$procedure->name}",
                        'quantity' => 1, 'unit_price' => (float) $procedure->cost]);
                }

                if ($withInventory) {
                    foreach ($c->supplies as $supply) {
                        $lines->push(['type' => 'product', 'consultation_id' => $c->id, 'product_id' => $supply->product_id,
                            'description' => $supply->product?->label, 'quantity' => (float) $supply->quantity, 'unit_price' => (float) $supply->product?->sale_price]);
                    }
                    foreach ($c->prescriptionItems->whereNotNull('dispensed_at') as $item) {
                        $lines->push(['type' => 'product', 'consultation_id' => $c->id, 'product_id' => $item->product_id,
                            'description' => $item->product?->label ?? $item->medication, 'quantity' => (float) $item->dispensed_quantity, 'unit_price' => (float) $item->product?->sale_price]);
                    }
                }

                return [
                    'key' => "consultation_id:{$c->id}",
                    'label' => 'Consulta '.$c->started_at->format('d/m/Y').' · '.($c->primary_diagnosis?->description ?? $c->reason ?? 'sin diagnóstico'),
                    'lines' => $lines->values(),
                ];
            });
    }

    /** Un estudio se puede cobrar desde que se solicita, sin esperar el resultado. */
    protected static function labRequests(Pet $pet): Collection
    {
        return LabRequest::with('studyType')
            ->where('pet_id', $pet->id)->where('status', '!=', 'cancelled')->whereNull('sale_id')
            ->latest('requested_at')->get()
            ->map(fn (LabRequest $l) => [
                'key' => "lab_request_id:{$l->id}",
                'label' => "Estudio {$l->code} · {$l->studyType?->name} · {$l->status_label}",
                'lines' => collect([[
                    'type' => 'lab',
                    'lab_request_id' => $l->id,
                    'description' => "Estudio: {$l->studyType?->name}",
                    'quantity' => 1,
                    'unit_price' => (float) $l->studyType?->price,
                ]]),
            ]);
    }

    /** Solo internaciones cerradas: mientras siguen activas, el total sigue creciendo. */
    protected static function hospitalizations(Pet $pet, bool $withInventory): Collection
    {
        return Hospitalization::with('charges.dose.treatment.product')
            ->where('pet_id', $pet->id)->whereIn('status', ['discharged', 'deceased'])->whereNull('sale_id')
            ->latest('discharged_at')->get()
            ->map(function (Hospitalization $h) use ($withInventory) {
                $days = $h->stayDays();
                $lines = collect();

                if ((float) $h->daily_rate > 0) {
                    $lines->push(['type' => 'hospitalization', 'hospitalization_id' => $h->id,
                        'description' => "Estadía {$h->code} · {$days} ".($days === 1 ? 'día' : 'días'),
                        'quantity' => $days, 'unit_price' => (float) $h->daily_rate]);
                }

                // Las tomas de un mismo producto se agrupan en una línea. Ya salieron
                // del stock al aplicarse: solo se cobran.
                $doses = [];
                foreach ($h->charges as $charge) {
                    $product = $charge->dose?->treatment?->product;

                    if ($withInventory && $product && ! $product->trashed()) {
                        $doses[$product->id.'|'.(float) $charge->unit_price][] = $charge;

                        continue;
                    }

                    $lines->push(['type' => 'hospitalization', 'hospitalization_id' => $h->id, 'description' => $charge->description,
                        'quantity' => (float) $charge->quantity, 'unit_price' => (float) $charge->unit_price]);
                }

                foreach ($doses as $charges) {
                    $product = $charges[0]->dose->treatment->product;
                    $count = count($charges);
                    $lines->push(['type' => 'product', 'hospitalization_id' => $h->id, 'product_id' => $product->id,
                        'description' => "{$product->label} · {$count} ".($count === 1 ? 'toma' : 'tomas'),
                        'quantity' => round(collect($charges)->sum(fn ($c) => (float) $c->quantity), 2),
                        'unit_price' => (float) $charges[0]->unit_price]);
                }

                return [
                    'key' => "hospitalization_id:{$h->id}",
                    'label' => "Internación {$h->code} · {$h->status_label} ".$h->discharged_at?->format('d/m/Y'),
                    'lines' => $lines->values(),
                ];
            })
            // Sin tarifa ni cargos no hay nada que cobrar.
            ->filter(fn (array $row) => $row['lines']->isNotEmpty());
    }
}
