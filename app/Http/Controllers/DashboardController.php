<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Personal;
use App\Models\Branch;
use App\Models\Caja;
use App\Models\Appointment;
use App\Models\Deworming;
use App\Models\Hospitalization;
use App\Models\HospitalizationDose;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\ProductBatch;
use App\Models\Vaccination;
use App\Support\Reports\ReportData;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $company = request()->attributes->get('tenant_company');

        // Personal, Branch y Caja se filtran solos por la empresa activa
        // (CompanyScope). En Modo Global el superadmin ve el total real.
        $totalPersonal = Personal::count();
        $totalBranches = Branch::count();
        $totalCajas    = Caja::count();

        // User es un modelo global: aquí sí hay que acotar a mano.
        $totalUsers = $company
            ? User::whereHas('companies', fn ($q) => $q->where('companies.id', $company->id))->count()
            : User::count();

        // Agenda de hoy: solo con una veterinaria activa que tenga el módulo.
        $today = null;
        if ($company && $company->planAllows('agenda')
            && $user->hasPermissionInCompany('appointments.view', $company)) {
            $appointments = Appointment::with(['pet', 'veterinarian'])->onDate(now())->orderBy('starts_at')->get();
            $today = [
                'total' => $appointments->whereNotIn('status', ['cancelled'])->count(),
                'pending' => $appointments->where('status', 'pending')->count(),
                'waiting' => $appointments->where('status', 'waiting')->count(),
                'in_progress' => $appointments->where('status', 'in_progress')->count(),
                'attended' => $appointments->where('status', 'attended')->count(),
                'next' => $appointments->whereIn('status', ['pending', 'confirmed'])
                    ->where('starts_at', '>=', now()->subMinutes(30))->take(6),
            ];
        }

        // Alertas sanitarias: vacunas y desparasitaciones atrasadas o próximas.
        $health = null;
        if ($company && $company->planAllows('vacunas')
            && $user->hasPermissionInCompany('vaccinations.view', $company)) {
            $from = now()->startOfDay();
            $until = $from->copy()->addDays(config('vexi.health_alert_days'))->endOfDay();
            $health = [
                'vaccines_overdue' => Vaccination::pendingDue()->where('next_due_at', '<', $from)->count(),
                'vaccines_upcoming' => Vaccination::pendingDue()->whereBetween('next_due_at', [$from, $until])->count(),
                'dewormings_overdue' => Deworming::pendingDue()->where('next_due_at', '<', $from)->count(),
                'dewormings_upcoming' => Deworming::pendingDue()->whereBetween('next_due_at', [$from, $until])->count(),
            ];
        }

        // Hospitalización: pacientes internados y tomas atrasadas.
        $hospital = null;
        if ($company && $company->planAllows('hospitalizacion')
            && $user->hasPermissionInCompany('hospitalizations.view', $company)) {
            $pendingDoses = fn () => HospitalizationDose::where('status', 'pending')
                ->whereHas('treatment.hospitalization', fn ($q) => $q->where('status', 'active'));
            $hospital = [
                'active' => Hospitalization::where('status', 'active')->count(),
                'critical' => Hospitalization::where('status', 'active')->where('condition', 'critical')->count(),
                'overdue' => $pendingDoses()->where('scheduled_at', '<', now())->count(),
                'next' => $pendingDoses()->whereBetween('scheduled_at', [now(), now()->addHours(6)])->count(),
            ];
        }

        // Alertas de inventario: stock mínimo, agotados y lotes por vencer o vencidos.
        $inventory = null;
        if ($company && $company->planAllows('inventario')
            && $user->hasPermissionInCompany('products.view', $company)) {
            $stockSum = '(select coalesce(sum(quantity), 0) from product_stocks where product_stocks.product_id = products.id)';
            $batches = fn () => ProductBatch::available()->whereNotNull('expires_at');
            $inventory = [
                'low' => Product::where('active', true)->lowStock()->whereRaw("$stockSum > 0")->count(),
                'out' => Product::where('active', true)->whereRaw("$stockSum <= 0")->count(),
                'expiring' => $batches()->whereBetween('expires_at', [now()->startOfDay(), now()->addDays(config('vexi.expiry_alert_days'))->endOfDay()])->count(),
                'expired' => $batches()->where('expires_at', '<', now()->startOfDay())->count(),
            ];
        }

        // Indicadores económicos (§1.2), con criterio de caja: lo cobrado menos lo
        // devuelto. Las ventas anuladas no cuentan.
        $finance = null;
        if ($company && $company->planAllows('pos') && $user->hasPermissionInCompany('sales.view', $company)) {
            $collected = fn ($from, $to) => ReportData::income($from, $to);

            $month = [now()->startOfMonth(), now()->endOfMonth()];
            $finance = [
                'today' => $collected(now()->startOfDay(), now()->endOfDay()),
                'month' => $collected(...$month),
                'sales_today' => Sale::where('status', 'completed')->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->count(),
                'sales_month' => Sale::where('status', 'completed')->whereBetween('created_at', $month)->count(),
                'receivable' => (float) Sale::receivable()->sum('balance'),
                'expenses_month' => $company->planAllows('caja') ? ReportData::expenses(...$month) : null,
            ];
            $finance['profit_month'] = $finance['month'] - ReportData::productCost(...$month) - ($finance['expenses_month'] ?? 0);
            $finance['payable'] = $company->planAllows('compras') ? (float) Purchase::payable()->sum('balance') : null;
        }

        return view('dashboard.index', compact(
            'totalUsers',
            'totalPersonal',
            'totalBranches',
            'totalCajas',
            'company',
            'today',
            'health',
            'inventory',
            'finance',
            'hospital'
        ));
    }
}
