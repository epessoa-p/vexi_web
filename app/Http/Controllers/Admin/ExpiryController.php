<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductBatch;
use App\Support\Stock;

/** Lotes vencidos y próximos a vencer (§7.4), con baja del lote vencido. */
class ExpiryController extends Controller
{
    public function index()
    {
        $tab = request('tab') === 'expired' ? 'expired' : 'expiring';
        $today = now()->startOfDay();
        $until = now()->addDays(config('vexi.expiry_alert_days'))->endOfDay();

        $base = fn () => ProductBatch::with(['product.category', 'branch'])->available()->whereNotNull('expires_at');

        return view('admin.expiry.index', [
            'tab' => $tab,
            'batches' => $tab === 'expired'
                ? $base()->where('expires_at', '<', $today)->orderBy('expires_at')->get()
                : $base()->whereBetween('expires_at', [$today, $until])->orderBy('expires_at')->get(),
            'counts' => [
                'expired' => $base()->where('expires_at', '<', $today)->count(),
                'expiring' => $base()->whereBetween('expires_at', [$today, $until])->count(),
            ],
        ]);
    }

    /** Da de baja todo lo que queda de un lote vencido. */
    public function writeOff(ProductBatch $batch)
    {
        if (! $batch->isExpired()) {
            return back()->withErrors(['error' => 'Solo se dan de baja lotes ya vencidos.']);
        }

        if ((float) $batch->quantity <= 0) {
            return back()->withErrors(['error' => 'El lote ya no tiene existencias.']);
        }

        Stock::consume($batch->product, $batch->branch, (float) $batch->quantity, 'expired', [
            'batch' => $batch,
            'notes' => "Baja del lote {$batch->lot} vencido el {$batch->expires_at->format('d/m/Y')}",
        ]);

        return back()->with('success', "Lote {$batch->lot} de {$batch->product->name} dado de baja.");
    }
}
