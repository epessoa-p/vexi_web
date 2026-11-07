<?php

namespace App\Support;

use App\Models\Breed;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\LabStudyType;
use App\Models\PaymentMethod;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\Species;
use App\Models\Vaccine;

/**
 * Catálogo base con el que arranca cada veterinaria nueva: especies, razas,
 * vacunas, servicios, categorías de productos y de gastos, y métodos de pago.
 *
 * Es idempotente: solo agrega lo que falte, nunca borra ni renombra lo que la
 * veterinaria haya editado. Se llama al dar de alta una empresa y desde los
 * seeders.
 */
class ClinicalCatalog
{
    /** @var array<string, array<int, string>> especie => razas */
    public const SPECIES = [
        'Canino' => [
            'Mestizo', 'Beagle', 'Bichón Frisé', 'Border Collie', 'Boxer', 'Bulldog Francés',
            'Bulldog Inglés', 'Chihuahua', 'Cocker Spaniel', 'Dachshund', 'Dóberman',
            'Golden Retriever', 'Husky Siberiano', 'Labrador Retriever', 'Maltés',
            'Pastor Alemán', 'Pekinés', 'Pinscher', 'Pitbull', 'Pomerania', 'Poodle',
            'Rottweiler', 'Schnauzer', 'Shih Tzu', 'Yorkshire Terrier',
        ],
        'Felino' => [
            'Mestizo', 'Angora', 'Bengalí', 'British Shorthair', 'Maine Coon', 'Persa',
            'Ragdoll', 'Siamés', 'Esfinge',
        ],
        'Ave' => ['Canario', 'Loro', 'Periquito', 'Cacatúa', 'Gallina'],
        'Conejo' => ['Mestizo', 'Belier', 'Cabeza de León', 'Enano'],
        'Roedor' => ['Hámster', 'Cobayo', 'Chinchilla', 'Rata', 'Ratón'],
        'Reptil' => ['Tortuga', 'Iguana', 'Serpiente', 'Gecko'],
        'Equino' => ['Criollo', 'Pura Sangre', 'Árabe', 'Cuarto de Milla'],
        'Bovino' => ['Criollo', 'Holstein', 'Nelore', 'Brahman'],
        'Otro' => [],
    ];

    /**
     * Servicios base: [nombre, categoría, duración en minutos].
     * El precio arranca en 0: cada veterinaria pone el suyo.
     */
    public const SERVICES = [
        ['Consulta general', 'consulta', 30],
        ['Consulta especializada', 'consulta', 45],
        ['Control / revisión', 'consulta', 20],
        ['Vacunación', 'vacunacion', 15],
        ['Desparasitación', 'desparasitacion', 15],
        ['Cirugía', 'cirugia', 120],
        ['Esterilización', 'cirugia', 90],
        ['Procedimiento', 'procedimiento', 30],
        ['Toma de muestras', 'laboratorio', 15],
        ['Baño', 'estetica', 60],
        ['Peluquería', 'estetica', 90],
        ['Guardería (día)', 'guarderia', 480],
        ['Hospedaje (noche)', 'hospedaje', 720],
    ];

    /**
     * Vacunas base: [nombre, especies, dosis de la serie, días entre dosis,
     * días hasta el refuerzo, edad mínima en semanas]. Son un punto de partida
     * habitual: cada veterinaria ajusta el protocolo a su criterio.
     */
    public const VACCINES = [
        ['Polivalente canina (séxtuple)', ['Canino'], 3, 21, 365, 6],
        ['Antirrábica', ['Canino', 'Felino'], 1, null, 365, 12],
        ['Tos de las perreras (Bordetella)', ['Canino'], 1, null, 365, 8],
        ['Triple felina (RCP)', ['Felino'], 2, 21, 365, 8],
        ['Leucemia felina (FeLV)', ['Felino'], 2, 21, 365, 8],
    ];

    /**
     * Estudios base: nombre => [categoría, parámetros [nombre, unidad]]. Los
     * valores de referencia quedan vacíos a propósito: dependen de la especie,
     * del método y del laboratorio, y los define cada veterinaria.
     */
    public const LAB_STUDIES = [
        'Hemograma' => ['hematology', [
            ['Hematocrito', '%'], ['Hemoglobina', 'g/dL'], ['Eritrocitos', '×10⁶/µL'], ['Leucocitos', '×10³/µL'],
            ['Neutrófilos', '%'], ['Linfocitos', '%'], ['Monocitos', '%'], ['Eosinófilos', '%'], ['Plaquetas', '×10³/µL'],
        ]],
        'Bioquímica sanguínea' => ['biochemistry', [
            ['Glucosa', 'mg/dL'], ['Urea (BUN)', 'mg/dL'], ['Creatinina', 'mg/dL'], ['ALT', 'U/L'], ['Fosfatasa alcalina', 'U/L'],
            ['Proteínas totales', 'g/dL'], ['Albúmina', 'g/dL'], ['Bilirrubina total', 'mg/dL'],
        ]],
        'Análisis de orina' => ['urine', [
            ['Densidad', null], ['pH', null], ['Proteínas', null], ['Glucosa', null], ['Cetonas', null], ['Sangre', null], ['Sedimento', null],
        ]],
        'Coproparasitológico' => ['parasitology', [['Método', null], ['Parásitos observados', null]]],
        'Radiografía' => ['imaging', []],
        'Ecografía' => ['imaging', []],
    ];

    /** Categorías de productos base (§7.2). */
    public const PRODUCT_CATEGORIES = [
        'Medicamentos', 'Antibióticos', 'Antiparasitarios', 'Vacunas', 'Alimentos',
        'Accesorios', 'Higiene y cuidado', 'Productos veterinarios', 'Insumos clínicos', 'Otros',
    ];

    /** Métodos de pago base: [nombre, tipo, exige referencia]. */
    public const PAYMENT_METHODS = [
        ['Efectivo', 'cash', false],
        ['Transferencia bancaria', 'transfer', true],
        ['QR', 'qr', false],
        ['Tarjeta', 'card', false],
    ];

    public const EXPENSE_CATEGORIES = [
        'Alquiler', 'Servicios básicos', 'Sueldos y honorarios', 'Insumos y materiales',
        'Mantenimiento', 'Impuestos', 'Publicidad', 'Otros',
    ];

    public static function seedFor(Company|int $company): void
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        foreach (self::LAB_STUDIES as $name => [$category, $parameters]) {
            $study = LabStudyType::forCompany($companyId)->firstOrCreate(
                ['company_id' => $companyId, 'name' => $name],
                ['category' => $category, 'active' => true]
            );

            // Solo al crearlo: no se pisan los parámetros que la veterinaria editó.
            if ($study->wasRecentlyCreated) {
                foreach ($parameters as $sort => [$parameter, $unit]) {
                    $study->parameters()->create(['company_id' => $companyId, 'name' => $parameter, 'unit' => $unit, 'sort' => $sort]);
                }
            }
        }

        foreach (self::PAYMENT_METHODS as $sort => [$name, $type, $reference]) {
            PaymentMethod::forCompany($companyId)->firstOrCreate(
                ['company_id' => $companyId, 'name' => $name],
                ['type' => $type, 'requires_reference' => $reference, 'active' => true, 'sort' => $sort]
            );
        }

        foreach (self::EXPENSE_CATEGORIES as $name) {
            ExpenseCategory::forCompany($companyId)->firstOrCreate(['company_id' => $companyId, 'name' => $name], ['active' => true]);
        }

        foreach (self::PRODUCT_CATEGORIES as $name) {
            ProductCategory::forCompany($companyId)->firstOrCreate(['company_id' => $companyId, 'name' => $name], ['active' => true]);
        }

        // Primero las especies: las vacunas se vinculan a ellas.
        foreach (self::SPECIES as $speciesName => $breeds) {
            $species = Species::forCompany($companyId)->firstOrCreate(
                ['company_id' => $companyId, 'name' => $speciesName],
                ['active' => true]
            );

            foreach ($breeds as $breedName) {
                Breed::forCompany($companyId)->firstOrCreate(
                    ['company_id' => $companyId, 'species_id' => $species->id, 'name' => $breedName],
                    ['active' => true]
                );
            }
        }

        foreach (self::VACCINES as [$name, $speciesNames, $doses, $interval, $booster, $minAge]) {
            $vaccine = Vaccine::forCompany($companyId)->withTrashed()->firstOrCreate(
                ['company_id' => $companyId, 'name' => $name],
                [
                    'doses_in_series' => $doses,
                    'series_interval_days' => $interval,
                    'booster_interval_days' => $booster,
                    'min_age_weeks' => $minAge,
                    'active' => true,
                ]
            );

            // Las especies se asignan solo al crearla: no se pisan ediciones.
            if ($vaccine->wasRecentlyCreated) {
                $vaccine->species()->sync(
                    Species::forCompany($companyId)->whereIn('name', $speciesNames)->pluck('id')
                );
            }
        }

        foreach (self::SERVICES as [$name, $category, $minutes]) {
            Service::forCompany($companyId)->withTrashed()->firstOrCreate(
                ['company_id' => $companyId, 'name' => $name],
                ['category' => $category, 'duration_minutes' => $minutes, 'price' => 0, 'active' => true]
            );
        }
    }
}
