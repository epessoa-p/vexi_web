<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Support\ClinicalCatalog;
use Illuminate\Database\Seeder;

/** Especies y razas base para cada empresa (idempotente). */
class ClinicalCatalogSeeder extends Seeder
{
    public function run(): void
    {
        Company::pluck('id')->each(fn (int $id) => ClinicalCatalog::seedFor($id));
    }
}
