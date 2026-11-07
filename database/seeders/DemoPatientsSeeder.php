<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Breed;
use App\Models\Client;
use App\Models\Company;
use App\Models\Consultation;
use App\Models\Deworming;
use App\Models\Vaccination;
use App\Models\Vaccine;
use App\Models\Hospitalization;
use App\Models\Kennel;
use App\Models\LabRequest;
use App\Models\LabStudyParameter;
use App\Models\LabStudyType;
use App\Models\Pet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\Species;
use App\Models\User;
use App\Models\Caja;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Support\Cash;
use App\Support\Hospitalizations;
use App\Support\Purchases;
use App\Support\Sales;
use App\Support\Stock;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/**
 * Propietarios y mascotas de ejemplo en Veterinaria Demo, para recorrer las
 * pantallas con datos. La otra veterinaria queda vacía a propósito.
 *
 * company_id va explícito: DatabaseSeeder desactiva los eventos de modelo y
 * BelongsToCompany no lo autocompleta durante el seeding.
 */
class DemoPatientsSeeder extends Seeder
{
    public function run(): void
    {
        $demo = Company::where('tax_id', '1023456789')->first();

        if (! $demo) {
            return;
        }

        app(Tenancy::class)->runFor($demo->id, function () use ($demo) {
            $owners = [
                [
                    'client' => ['first_name' => 'María', 'last_name' => 'Gutiérrez Rojas', 'document_type' => 'CI', 'document_number' => '6543210',
                        'phone' => '+591 71234567', 'whatsapp' => '+591 71234567', 'email' => 'maria.gutierrez@example.test', 'address' => 'Barrio Equipetrol, calle 4 #12'],
                    'pets' => [
                        ['name' => 'Luna', 'species' => 'Canino', 'breed' => 'Labrador Retriever', 'sex' => 'female', 'sterilized' => true,
                            'birth_date' => now()->subYears(4)->subMonths(2), 'weight' => 28.4, 'color' => 'Dorado', 'size' => 'large', 'microchip' => '985112003456789',
                            'allergies' => 'Amoxicilina', 'clinical_alerts' => 'Alérgica a la amoxicilina.'],
                        ['name' => 'Michi', 'species' => 'Felino', 'breed' => 'Siamés', 'sex' => 'male', 'sterilized' => true,
                            'birth_date' => now()->subYears(2), 'weight' => 4.2, 'color' => 'Crema y marrón', 'size' => 'small'],
                    ],
                ],
                [
                    'client' => ['first_name' => 'Carlos', 'last_name' => 'Mendoza', 'document_type' => 'CI', 'document_number' => '4567891',
                        'phone' => '+591 76543210', 'whatsapp' => '+591 76543210', 'address' => 'Av. Banzer 3er anillo'],
                    'pets' => [
                        ['name' => 'Rocky', 'species' => 'Canino', 'breed' => 'Pastor Alemán', 'sex' => 'male',
                            'birth_date' => now()->subYears(7), 'weight' => 34.0, 'color' => 'Negro y fuego', 'size' => 'large',
                            'chronic_conditions' => 'Displasia de cadera', 'usual_medications' => 'Carprofeno 75 mg cada 24 h'],
                    ],
                ],
                [
                    'client' => ['first_name' => 'Ana', 'last_name' => 'Suárez Vaca', 'document_type' => 'NIT', 'document_number' => '1029384756',
                        'phone' => '+591 3 3345678', 'email' => 'ana.suarez@example.test'],
                    'pets' => [
                        ['name' => 'Kiwi', 'species' => 'Ave', 'breed' => 'Periquito', 'sex' => 'unknown',
                            'birth_date' => now()->subMonths(8), 'weight' => 0.04, 'color' => 'Verde', 'size' => 'mini'],
                        ['name' => 'Copito', 'species' => 'Conejo', 'breed' => 'Belier', 'sex' => 'female',
                            'birth_date' => now()->subMonths(14), 'weight' => 1.8, 'color' => 'Blanco', 'size' => 'small'],
                    ],
                ],
            ];

            foreach ($owners as $owner) {
                $client = Client::updateOrCreate(
                    ['company_id' => $demo->id, 'document_number' => $owner['client']['document_number']],
                    $owner['client'] + ['company_id' => $demo->id, 'active' => true]
                );

                foreach ($owner['pets'] as $data) {
                    $species = Species::where('name', $data['species'])->first();

                    if (! $species) {
                        continue;
                    }

                    $breed = Breed::where('species_id', $species->id)->where('name', $data['breed'])->first();

                    $pet = Pet::updateOrCreate(
                        ['client_id' => $client->id, 'name' => $data['name']],
                        collect($data)->except(['species', 'breed'])->all() + [
                            'company_id' => $demo->id,
                            'species_id' => $species->id,
                            'breed_id' => $breed?->id,
                            'status' => 'active',
                        ]
                    );

                    if (! $pet->weights()->exists()) {
                        // Dos mediciones para que el historial de peso no salga vacío.
                        $pet->weights()->create(['company_id' => $demo->id, 'weight' => round($data['weight'] * 0.93, 2), 'recorded_at' => now()->subMonths(3)->toDateString()]);
                        $pet->weights()->create(['company_id' => $demo->id, 'weight' => $data['weight'], 'recorded_at' => now()->subDays(5)->toDateString()]);
                    }
                }
            }

            $this->seedAppointments($demo);
            $this->seedConsultation($demo);
            $this->seedHealth($demo);
            $this->seedInventory($demo);
            $this->seedSales($demo);
            $this->seedPurchases($demo);
            $this->seedLab($demo);
            $this->seedHospital($demo);
            $this->seedReportHistory($demo);
        });
    }

    /** Espacios y un paciente internado con tratamiento en curso y una toma atrasada. */
    /**
     * Seis meses de actividad para que los reportes se puedan leer en la demo.
     * Es historia ya cerrada: las ventas no generan kardex (el stock de la demo
     * no cambia) y sus consultas ya están cobradas, así no aparecen por cobrar.
     */
    protected function seedReportHistory(Company $demo): void
    {
        mt_srand(20261106);

        $branch = Branch::where('name', 'Sede Central')->first() ?? Stock::defaultBranch($demo->id);
        $pets = Pet::get(['id', 'client_id', 'species_id']);
        $staff = User::whereIn('email', ['vet@veterinariademo.test', 'admin@veterinariademo.test'])->pluck('id')->values();
        $seller = User::where('email', 'cajero@veterinariademo.test')->value('id') ?? $staff->first();
        $methods = PaymentMethod::whereIn('type', ['cash', 'qr', 'transfer', 'card'])->get();
        $consultation = Service::where('category', 'consulta')->first();
        $services = Service::where('price', '>', 0)->get();
        $products = Product::where('sale_price', '>', 0)->get();
        $vaccines = Vaccine::get();
        $categories = ExpenseCategory::get();
        $diagnoses = ['Otitis externa', 'Dermatitis alérgica', 'Gastroenteritis aguda', 'Parasitosis intestinal', 'Control de rutina', 'Conjuntivitis', 'Dermatitis alérgica', 'Control de rutina'];
        $number = (int) Sale::max('number');

        $sell = function (array $lines, $at, Pet $pet) use ($demo, $branch, $seller, $methods, &$number) {
            $total = round(collect($lines)->sum(fn ($l) => $l['quantity'] * $l['unit_price']), 2);
            $sale = new Sale(['company_id' => $demo->id, 'branch_id' => $branch->id, 'number' => ++$number, 'client_id' => $pet->client_id, 'pet_id' => $pet->id,
                'user_id' => $seller, 'status' => 'completed', 'subtotal' => $total, 'discount' => 0, 'total' => $total, 'paid' => $total, 'balance' => 0]);
            $sale->created_at = $sale->updated_at = $at;
            $sale->save();

            foreach ($lines as $line) {
                $sale->items()->create($line + ['company_id' => $demo->id, 'discount' => 0, 'total' => round($line['quantity'] * $line['unit_price'], 2), 'affects_stock' => false]);
            }

            $sale->payments()->create(['company_id' => $demo->id, 'payment_method_id' => $methods[mt_rand(0, $methods->count() - 1)]->id, 'kind' => 'sale',
                'amount' => $total, 'user_id' => $seller, 'paid_at' => $at]);

            return $sale;
        };

        // De menos a más, con los últimos días incluidos: se ve una tendencia.
        for ($back = 180; $back >= 1; $back--) {
            $day = now()->subDays($back)->startOfDay();

            if ($day->isSunday()) {
                continue;
            }

            $busy = (int) round(1 + (180 - $back) / 70) + mt_rand(0, 2);

            for ($i = 0; $i < $busy; $i++) {
                $at = $day->copy()->addHours(mt_rand(9, 18))->addMinutes(mt_rand(0, 59));
                $pet = $pets[mt_rand(0, $pets->count() - 1)];
                $sold = $products->count() && mt_rand(0, 2) === 0 ? $products[mt_rand(0, $products->count() - 1)] : null;
                $productLines = $sold ? [['type' => 'product', 'product_id' => $sold->id, 'description' => $sold->label, 'quantity' => mt_rand(1, 2), 'unit_price' => (float) $sold->sale_price]] : [];

                if ($consultation && mt_rand(0, 2) > 0) {
                    $record = new Consultation(['company_id' => $demo->id, 'branch_id' => $branch->id, 'pet_id' => $pet->id, 'client_id' => $pet->client_id,
                        'veterinarian_id' => $staff[mt_rand(0, 3) === 0 ? $staff->count() - 1 : 0], 'status' => 'finished', 'started_at' => $at, 'finished_at' => $at->copy()->addMinutes(25),
                        'reason' => 'Atención de rutina']);
                    $record->created_at = $record->updated_at = $at;
                    $record->save();
                    $record->diagnoses()->create(['company_id' => $demo->id, 'type' => 'primary', 'description' => $diagnoses[mt_rand(0, count($diagnoses) - 1)]]);

                    $sale = $sell(array_merge([['type' => 'consultation', 'consultation_id' => $record->id, 'service_id' => $consultation->id,
                        'description' => 'Consulta '.$at->format('d/m').' · '.$consultation->name, 'quantity' => 1, 'unit_price' => (float) $consultation->price]], $productLines), $at, $pet);
                    $record->update(['sale_id' => $sale->id]);
                } elseif ($services->count()) {
                    $service = $services[mt_rand(0, $services->count() - 1)];
                    $sell(array_merge([['type' => 'service', 'service_id' => $service->id, 'description' => $service->name, 'quantity' => 1, 'unit_price' => (float) $service->price]], $productLines), $at, $pet);
                }
            }

            if ($vaccines->count() && mt_rand(0, 3) === 0) {
                $pet = $pets[mt_rand(0, $pets->count() - 1)];
                Vaccination::create(['company_id' => $demo->id, 'pet_id' => $pet->id, 'vaccine_id' => $vaccines[mt_rand(0, $vaccines->count() - 1)]->id,
                    'applied_at' => $day, 'dose_number' => 1, 'veterinarian_id' => $staff->first()]);
            }

            // Gastos: un par de pagos por semana.
            if ($categories->count() && mt_rand(0, 2) === 0) {
                $expense = new Expense(['company_id' => $demo->id, 'branch_id' => $branch->id, 'expense_category_id' => $categories[mt_rand(0, $categories->count() - 1)]->id,
                    'concept' => 'Gasto del '.$day->format('d/m'), 'amount' => mt_rand(40, 320), 'spent_at' => $day, 'user_id' => $staff->first()]);
                $expense->created_at = $expense->updated_at = $day;
                $expense->save();
            }
        }

        mt_srand();
    }

    protected function seedHospital(Company $demo): void
    {
        if (Hospitalization::exists()) {
            return;
        }

        $branch = Branch::where('name', 'Sede Central')->first();
        foreach (['Canil 1', 'Canil 2', 'Jaula gatos 1', 'Aislamiento'] as $name) {
            Kennel::create(['company_id' => $demo->id, 'branch_id' => $branch?->id, 'name' => $name, 'active' => true]);
        }

        $vet = User::where('email', 'vet@veterinariademo.test')->first();
        $michi = Pet::where('name', 'Michi')->firstOrFail();
        $admitted = now()->subDay()->startOfHour();

        $stay = Hospitalizations::admit([
            'pet_id' => $michi->id,
            'kennel_id' => Kennel::where('name', 'Jaula gatos 1')->value('id'),
            'veterinarian_id' => $vet?->id,
            'admitted_at' => $admitted,
            'condition' => 'improving',
            'reason' => 'Anorexia y deshidratación',
            'admission_diagnosis' => 'Gastroenteritis aguda',
            'daily_rate' => 150,
        ]);

        $treatment = Hospitalizations::addTreatment($stay, [
            'product_id' => Product::where('name', 'Amoxicilina 250 mg')->value('id'),
            'medication' => 'Amoxicilina 250 mg',
            'dose' => '1/2 comp.',
            'stock_quantity' => 0.5,
            'route' => 'oral',
            'frequency_hours' => 12,
            'starts_at' => $admitted,
            'ends_at' => $admitted->copy()->addDays(5),
        ]);
        Hospitalizations::giveDose($treatment->doses()->first());

        $stay->records()->create(['company_id' => $demo->id, 'type' => 'vitals', 'recorded_at' => $admitted, 'temperature' => 39.4,
            'heart_rate' => 190, 'respiratory_rate' => 36, 'hydration' => 'moderate', 'notes' => 'Al ingreso.', 'recorded_by' => $vet?->id]);
        $stay->records()->create(['company_id' => $demo->id, 'type' => 'evolution', 'recorded_at' => now()->subHours(2), 'condition' => 'improving',
            'notes' => 'Comió una porción pequeña. Sin vómitos desde anoche.', 'recorded_by' => $vet?->id]);

        // Una internación ya cerrada, pendiente de cobro en el punto de venta.
        $closed = Hospitalizations::admit([
            'pet_id' => Pet::where('name', 'Rocky')->firstOrFail()->id,
            'veterinarian_id' => $vet?->id,
            'admitted_at' => now()->subDays(6),
            'condition' => 'stable',
            'reason' => 'Observación posquirúrgica',
            'daily_rate' => 120,
        ]);
        $closed->charges()->create(['company_id' => $demo->id, 'description' => 'Fluidoterapia', 'quantity' => 1, 'unit_price' => 80, 'total' => 80, 'created_by' => $vet?->id]);
        Hospitalizations::discharge($closed, 'discharged', [
            'discharged_at' => now()->subDays(4),
            'final_diagnosis' => 'Evolución favorable',
            'home_treatment' => 'Reposo y control de la herida.',
        ]);
    }

    /**
     * Un estudio completado con un valor fuera de rango, uno en proceso y uno
     * solicitado. Las referencias del hemograma son ilustrativas, solo para
     * esta veterinaria de demostración: el catálogo base las deja vacías.
     */
    protected function seedLab(Company $demo): void
    {
        if (LabRequest::exists()) {
            return;
        }

        $vet = User::where('email', 'vet@veterinariademo.test')->first();
        $study = fn (string $name) => LabStudyType::where('name', $name)->firstOrFail();
        $pet = fn (string $name) => Pet::where('name', $name)->firstOrFail();

        $hemogram = $study('Hemograma');
        foreach (['Hematocrito' => [37, 55], 'Hemoglobina' => [12, 18], 'Leucocitos' => [6, 17], 'Plaquetas' => [200, 500]] as $name => [$min, $max]) {
            LabStudyParameter::where('lab_study_type_id', $hemogram->id)->where('name', $name)->update(['reference_min' => $min, 'reference_max' => $max]);
        }

        foreach (['Hemograma' => 90, 'Coproparasitológico' => 40, 'Radiografía' => 150] as $name => $price) {
            $study($name)->update(['price' => $price]);
        }

        $base = ['company_id' => $demo->id, 'requested_by' => $vet?->id];
        $luna = $pet('Luna');

        $completed = LabRequest::create($base + [
            'pet_id' => $luna->id,
            'consultation_id' => Consultation::where('pet_id', $luna->id)->value('id'),
            'lab_study_type_id' => $hemogram->id,
            'requested_at' => now()->subDay(),
            'status' => 'completed',
            'result_date' => now()->toDateString(),
            'responsible_id' => $vet?->id,
            'result_notes' => 'Leucocitosis leve. Correlacionar con la otitis en tratamiento.',
            'started_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
        foreach ([['Hematocrito', '44', '%', 37, 55], ['Hemoglobina', '15.1', 'g/dL', 12, 18], ['Leucocitos', '19.2', '×10³/µL', 6, 17], ['Plaquetas', '310', '×10³/µL', 200, 500]] as $sort => [$name, $value, $unit, $min, $max]) {
            $completed->results()->create(['company_id' => $demo->id, 'name' => $name, 'value' => $value, 'unit' => $unit,
                'reference_min' => $min, 'reference_max' => $max, 'sort' => $sort]);
        }

        LabRequest::create($base + ['pet_id' => $pet('Rocky')->id, 'lab_study_type_id' => $study('Coproparasitológico')->id,
            'requested_at' => now()->subHours(3), 'status' => 'in_process', 'started_at' => now()->subHours(2)]);
        LabRequest::create($base + ['pet_id' => $pet('Rocky')->id, 'lab_study_type_id' => $study('Radiografía')->id,
            'requested_at' => now()->subHour(), 'status' => 'requested', 'notes' => 'Cadera, proyección ventrodorsal.']);
    }

    /** Proveedores, una compra recibida con saldo por pagar y un pedido pendiente de recibir. */
    protected function seedPurchases(Company $demo): void
    {
        if (Purchase::exists()) {
            return;
        }

        $branch = Branch::where('name', 'Sede Central')->first() ?? Stock::defaultBranch($demo->id);
        $transfer = PaymentMethod::where('type', 'transfer')->first();
        $product = fn (string $name) => Product::where('name', $name)->firstOrFail();

        $vetsur = Supplier::create(['company_id' => $demo->id, 'name' => 'Distribuidora VetSur S.R.L.', 'tax_id' => '1029384011',
            'contact_name' => 'Jorge Arteaga', 'phone' => '+591 3 3456789', 'email' => 'ventas@vetsur.example.test',
            'address' => 'Parque Industrial, Santa Cruz', 'notes' => 'Entrega en 48 h. Crédito a 30 días.', 'active' => true]);
        $andinos = Supplier::create(['company_id' => $demo->id, 'name' => 'Laboratorios Andinos', 'tax_id' => '2045678019',
            'contact_name' => 'Lucía Paredes', 'phone' => '+591 2 2789012', 'active' => true]);

        // Recibida: entró al stock con su lote; se pagó una parte por transferencia.
        Purchases::create([
            'supplier_id' => $vetsur->id, 'branch' => $branch, 'purchased_at' => now()->subDays(3)->toDateString(),
            'document_number' => '000154', 'tax_rate' => 13, 'due_date' => now()->addDays(27)->toDateString(), 'receive_now' => true,
            'items' => [
                ['product_id' => $product('Amoxicilina 250 mg')->id, 'quantity' => 100, 'unit_cost' => 1.1,
                    'lot' => 'AMX-2502', 'expires_at' => now()->addMonths(18)->toDateString()],
                ['product_id' => $product('Jeringa descartable 3 ml')->id, 'quantity' => 200, 'unit_cost' => 0.75],
            ],
            'payments' => [['method' => $transfer, 'amount' => 150, 'reference' => 'TRX-88231']],
        ]);

        // Pedido: todavía no llegó.
        Purchases::create([
            'supplier_id' => $andinos->id, 'branch' => $branch, 'purchased_at' => now()->toDateString(),
            'items' => [
                ['product_id' => $product('Bravecto 20–40 kg')->id, 'quantity' => 6, 'unit_cost' => 155],
                ['product_id' => $product('Carprofeno 75 mg')->id, 'quantity' => 56, 'unit_cost' => 4.3],
            ],
        ]);
    }

    /**
     * Caja abierta con una venta al contado, una a crédito con abono parcial
     * y un gasto pagado desde la caja. Todo pasa por Sales y Cash, como en la app.
     */
    protected function seedSales(Company $demo): void
    {
        if (Sale::exists()) {
            return;
        }

        $branch = Branch::where('name', 'Sede Central')->first() ?? Stock::defaultBranch($demo->id);
        $caja = Caja::create(['company_id' => $demo->id, 'branch_id' => $branch->id, 'name' => 'Caja principal', 'code' => 'CAJA-1', 'active' => true]);
        $session = Cash::open($caja, 200);

        $cash = PaymentMethod::where('type', 'cash')->first();
        $qr = PaymentMethod::where('type', 'qr')->first();
        $product = fn (string $name) => Product::where('name', $name)->first();
        $bath = Service::where('name', 'Baño')->first();
        $bath?->update(['price' => 60]);
        Service::where('category', 'consulta')->update(['price' => 120]);

        $maria = Client::where('document_number', '6543210')->first();
        $carlos = Client::where('document_number', '4567891')->first();

        // Venta al contado: baño + shampoo, pago mixto efectivo y QR.
        Sales::create([
            'client_id' => $maria?->id, 'pet_id' => Pet::where('name', 'Luna')->value('id'),
            'branch' => $branch, 'session' => $session,
            'items' => array_values(array_filter([
                $bath ? ['type' => 'service', 'service_id' => $bath->id, 'description' => $bath->name, 'quantity' => 1, 'unit_price' => 60] : null,
                ['type' => 'product', 'product_id' => $product('Shampoo clorhexidina 2 %')->id, 'description' => 'Shampoo clorhexidina 2 %', 'quantity' => 1, 'unit_price' => 70, 'affects_stock' => true],
            ])),
            'payments' => [['method' => $cash, 'amount' => 100], ['method' => $qr, 'amount' => 30]],
        ]);

        // Venta a crédito con abono parcial: queda en cuentas por cobrar.
        Sales::create([
            'client_id' => $carlos?->id, 'pet_id' => Pet::where('name', 'Rocky')->value('id'),
            'branch' => $branch, 'session' => $session, 'due_date' => now()->addDays(15)->toDateString(),
            'items' => [
                ['type' => 'product', 'product_id' => $product('Carprofeno 75 mg')->id, 'description' => 'Carprofeno 75 mg', 'quantity' => 14, 'unit_price' => 9, 'affects_stock' => true],
                ['type' => 'product', 'product_id' => $product('Royal Canin Gastrointestinal')->id, 'description' => 'Royal Canin Gastrointestinal', 'quantity' => 1, 'unit_price' => 260, 'affects_stock' => true],
            ],
            'payments' => [['method' => $cash, 'amount' => 150]],
        ]);

        $expense = Expense::create([
            'company_id' => $demo->id, 'branch_id' => $branch->id,
            'expense_category_id' => ExpenseCategory::where('name', 'Insumos y materiales')->value('id'),
            'concept' => 'Compra de algodón y gasas', 'amount' => 45, 'spent_at' => now()->toDateString(),
            'payment_method_id' => $cash->id, 'cash_session_id' => $session->id,
        ]);
        Cash::record($session, 'expense', $cash, -45, "Gasto: {$expense->concept}", $expense);
    }

    /**
     * Productos con existencias que cubren cada alerta: stock mínimo, agotado,
     * lote por vencer y lote vencido. Las entradas pasan por Stock para que
     * el kardex quede completo desde el inicio.
     */
    protected function seedInventory(Company $demo): void
    {
        if (Product::exists()) {
            return;
        }

        $branch = Branch::create(['company_id' => $demo->id, 'name' => 'Sede Central', 'code' => 'CENTRAL', 'active' => true]);
        $category = fn (string $name) => ProductCategory::where('name', $name)->value('id');

        $products = [
            // nombre, presentación, categoría, unidad, compra, venta, mínimo, lotes [lote, días hasta vencer, cantidad] | cantidad sin lote
            ['Amoxicilina 250 mg', 'Caja x 10 comp.', 'Antibióticos', 'comprimido', 1.2, 3, 20, [['AMX-2401', 240, 60], ['AMX-2311', 40, 10]]],
            ['Meloxicam suspensión 1,5 mg/ml', 'Frasco 10 ml', 'Medicamentos', 'frasco', 45, 85, 3, [['MLX-099', -10, 2], ['MLX-120', 365, 4]]],
            ['Carprofeno 75 mg', 'Caja x 14 comp.', 'Medicamentos', 'comprimido', 4.5, 9, 30, [['CRP-551', 300, 25]]],
            ['Drontal Plus', 'Comprimido 10 kg', 'Antiparasitarios', 'comprimido', 12, 25, 10, [['DRP-310', 500, 40]]],
            ['Bravecto 20–40 kg', 'Comprimido masticable', 'Antiparasitarios', 'comprimido', 160, 260, 2, []],
            ['Vacuna antirrábica', 'Frasco 1 dosis', 'Vacunas', 'dosis', 18, 60, 10, [['RAB-7781', 300, 25]]],
            ['Jeringa descartable 3 ml', 'Unidad', 'Insumos clínicos', 'unidad', 0.8, 2, 50, 200],
            ['Royal Canin Gastrointestinal', 'Bolsa 2 kg', 'Alimentos', 'unidad', 180, 260, 2, 6],
            ['Shampoo clorhexidina 2 %', 'Frasco 250 ml', 'Higiene y cuidado', 'frasco', 35, 70, 3, 8],
        ];

        foreach ($products as [$name, $presentation, $categoryName, $unit, $cost, $price, $min, $stock]) {
            $product = Product::create([
                'company_id' => $demo->id,
                'product_category_id' => $category($categoryName),
                'name' => $name,
                'presentation' => $presentation,
                'unit' => $unit,
                'purchase_price' => $cost,
                'sale_price' => $price,
                'min_stock' => $min,
                'track_batches' => is_array($stock),
                'active' => true,
            ]);

            foreach (is_array($stock) ? $stock : [[null, null, $stock]] as [$lot, $days, $quantity]) {
                Stock::receive($product, $branch, $quantity, 'initial', [
                    'lot' => $lot,
                    'expires_at' => $days !== null ? now()->addDays($days)->toDateString() : null,
                    'unit_cost' => $cost,
                ]);
            }
        }

        // La antirrábica descuenta una dosis del inventario al aplicarse.
        Vaccine::where('name', 'Antirrábica')->update(['product_id' => Product::where('name', 'Vacuna antirrábica')->value('id')]);
    }

    /** Vacunas y desparasitaciones con algo atrasado y algo próximo, para el calendario. */
    protected function seedHealth(Company $demo): void
    {
        if (Vaccination::exists()) {
            return;
        }

        $vet = User::where('email', 'vet@veterinariademo.test')->first();
        $pet = fn (string $name) => Pet::where('name', $name)->first();
        $vaccine = fn (string $name) => Vaccine::where('name', $name)->first();

        $vaccinations = [
            // mascota, vacuna, aplicada, dosis, próxima
            ['Luna', 'Antirrábica', now(), 4, now()->addYear()],
            ['Luna', 'Polivalente canina (séxtuple)', now()->subMonths(11), 5, now()->addMonth()],
            ['Rocky', 'Polivalente canina (séxtuple)', now()->subMonths(13), 6, now()->subMonth()],   // atrasada
            ['Michi', 'Triple felina (RCP)', now()->subYear()->addDays(5), 3, now()->addDays(5)],      // próxima
        ];

        foreach ($vaccinations as [$petName, $vaccineName, $applied, $dose, $next]) {
            if (! ($patient = $pet($petName)) || ! ($v = $vaccine($vaccineName))) {
                continue;
            }

            Vaccination::create([
                'company_id' => $demo->id, 'pet_id' => $patient->id, 'vaccine_id' => $v->id,
                'applied_at' => $applied->toDateString(), 'dose_number' => $dose,
                'lot' => 'L' . random_int(10000, 99999), 'lot_expires_at' => $applied->copy()->addMonths(18)->toDateString(),
                'veterinarian_id' => $vet?->id, 'next_due_at' => $next->toDateString(),
            ]);
        }

        $dewormings = [
            // mascota, tipo, producto, dosis, aplicada, próxima
            ['Rocky', 'internal', 'Drontal Plus', '1 comp. 35 kg', now()->subDays(100), now()->subDays(10)],   // atrasada
            ['Luna', 'external', 'Bravecto 20–40 kg', '1 comp.', now()->subDays(80), now()->addDays(10)],      // próxima
            ['Michi', 'both', 'Broadline gato 2,5–7,5 kg', '1 pipeta', now()->subDays(20), now()->addDays(10)],
        ];

        foreach ($dewormings as [$petName, $type, $product, $dose, $applied, $next]) {
            if (! ($patient = $pet($petName))) {
                continue;
            }

            Deworming::create([
                'company_id' => $demo->id, 'pet_id' => $patient->id, 'type' => $type, 'product' => $product,
                'dose' => $dose, 'weight' => $patient->weight, 'applied_at' => $applied->toDateString(),
                'next_due_at' => $next->toDateString(), 'veterinarian_id' => $vet?->id,
            ]);
        }
    }

    /** Consulta finalizada de la cita atendida de Luna, con diagnóstico y receta. */
    protected function seedConsultation(Company $demo): void
    {
        $vet = User::where('email', 'vet@veterinariademo.test')->first();
        $vet?->update(['professional_license' => 'COLVET-SCZ 1234', 'specialty' => 'Médica veterinaria']);

        $appointment = Appointment::where('status', 'attended')->with('pet')->first();

        if (! $appointment || Consultation::where('appointment_id', $appointment->id)->exists()) {
            return;
        }

        $consultation = Consultation::create([
            'company_id' => $demo->id,
            'pet_id' => $appointment->pet_id,
            'client_id' => $appointment->client_id,
            'appointment_id' => $appointment->id,
            'veterinarian_id' => $vet?->id,
            'status' => 'finished',
            'started_at' => $appointment->started_at,
            'finished_at' => $appointment->finished_at,
            'reason' => 'Refuerzo anual y control general',
            'anamnesis' => 'Propietaria refiere prurito en oreja derecha desde hace 4 días. Apetito y deposiciones normales.',
            'physical_exam' => 'Otitis externa derecha con secreción ceruminosa. Resto del examen sin alteraciones.',
            'temperature' => 38.6,
            'weight' => $appointment->pet?->weight,
            'heart_rate' => 96,
            'respiratory_rate' => 24,
            'hydration' => 'normal',
            'body_condition' => 5,
            'mucous_membranes' => 'pink',
            'instructions' => "Limpiar el oído con el limpiador ótico antes de aplicar las gotas.\nVolver si hay dolor, inclinación de la cabeza o mal olor.",
            'recommendations' => 'Evitar baños durante el tratamiento.',
            'next_review_at' => now()->addDays(10)->toDateString(),
        ]);

        $consultation->diagnoses()->create(['company_id' => $demo->id, 'type' => 'primary', 'description' => 'Otitis externa', 'sort' => 0]);
        $consultation->prescriptionItems()->create([
            'company_id' => $demo->id, 'medication' => 'Gotas óticas (gentamicina + betametasona)', 'presentation' => 'Frasco 15 ml',
            'dose' => '5 gotas', 'route' => 'otic', 'frequency' => 'cada 12 h', 'duration' => '7 días', 'quantity' => '1', 'sort' => 0,
        ]);
        $consultation->followups()->create([
            'company_id' => $demo->id, 'recorded_at' => now(), 'treatment_status' => 'in_course',
            'notes' => 'Se entrega receta e indicaciones a la propietaria.', 'recorded_by' => $vet?->id,
        ]);
    }

    /** Citas de hoy y mañana en distintos estados, para la agenda y la sala de espera. */
    protected function seedAppointments(Company $demo): void
    {
        if (Appointment::exists()) {
            return;
        }

        $vet = User::where('email', 'vet@veterinariademo.test')->first();
        $service = fn (string $name) => Service::where('name', $name)->first();
        $pet = fn (string $name) => Pet::where('name', $name)->first();
        $today = now()->startOfDay();

        $plan = [
            // mascota, servicio, inicio, estado, motivo
            ['Luna', 'Vacunación', $today->copy()->setTime(9, 0), 'attended', 'Refuerzo anual'],
            ['Rocky', 'Control / revisión', $today->copy()->setTime(10, 30), 'waiting', 'Control de displasia'],
            ['Michi', 'Consulta general', $today->copy()->setTime(15, 0), 'confirmed', 'No come desde ayer'],
            ['Copito', 'Baño', $today->copy()->setTime(17, 0), 'pending', null],
            ['Kiwi', 'Consulta especializada', $today->copy()->addDay()->setTime(11, 0), 'pending', 'Pérdida de plumas'],
        ];

        foreach ($plan as [$petName, $serviceName, $start, $status, $reason]) {
            $patient = $pet($petName);
            $srv = $service($serviceName);

            if (! $patient) {
                continue;
            }

            Appointment::create([
                'company_id' => $demo->id,
                'client_id' => $patient->client_id,
                'pet_id' => $patient->id,
                'service_id' => $srv?->id,
                'veterinarian_id' => $vet?->id,
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMinutes($srv?->duration_minutes ?? 30),
                'reason' => $reason,
                'status' => $status,
                'confirmed_at' => in_array($status, ['confirmed', 'attended'], true) ? $start->copy()->subDay() : null,
                'checked_in_at' => in_array($status, ['waiting', 'attended'], true) ? $start->copy()->subMinutes(10) : null,
                'started_at' => $status === 'attended' ? $start : null,
                'finished_at' => $status === 'attended' ? $start->copy()->addMinutes(20) : null,
            ]);
        }
    }
}
