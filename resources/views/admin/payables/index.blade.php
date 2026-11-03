@extends('layouts.app')

@section('title', 'Cuentas por pagar')

@section('page')
@php $money = fn ($v) => \App\Support\Money::format($v, $tenantCompany); @endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-hourglass-split"></i> Cuentas por pagar</h1>
            <p class="text-muted mb-0">Se debe {{ $money($total) }} a {{ $suppliers->count() }} proveedores.</p>
        </div>
        <div class="btn-group">
            <a href="{{ route('payables.index') }}" class="btn btn-sm {{ request('overdue') ? 'btn-outline-dark' : 'btn-dark' }}">Todas</a>
            <a href="{{ route('payables.index', ['overdue' => 1]) }}" class="btn btn-sm {{ request('overdue') ? 'btn-dark' : 'btn-outline-dark' }}">Vencidas</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            @forelse($suppliers as $row)
                <div class="card border-0 shadow-sm mb-3 {{ $row['overdue'] ? 'border-start border-danger border-4' : '' }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <a href="{{ route('suppliers.show', $row['supplier']) }}" class="fw-semibold text-decoration-none">{{ $row['supplier']?->name }}</a>
                                @if($row['overdue'])<span class="badge bg-danger">Con deuda vencida</span>@endif
                                <div class="small text-muted">{{ collect([$row['supplier']?->contact_name, $row['supplier']?->phone])->filter()->implode(' · ') }}</div>
                            </div>
                            <div class="fs-5 fw-bold text-danger">{{ $money($row['balance']) }}</div>
                        </div>
                        <table class="table table-sm align-middle mb-0 mt-2">
                            <tbody>
                                @foreach($row['purchases'] as $purchase)
                                    <tr>
                                        <td><a href="{{ route('purchases.show', $purchase) }}" class="text-decoration-none">{{ $purchase->code }}</a></td>
                                        <td class="small text-muted">{{ $purchase->purchased_at->format('d/m/Y') }}{{ $purchase->document_number ? " · Fact. {$purchase->document_number}" : '' }}</td>
                                        <td class="small {{ $purchase->isOverdue() ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $purchase->due_date ? 'vence ' . $purchase->due_date->format('d/m/Y') : 'sin vencimiento' }}</td>
                                        <td class="text-end small">de {{ $money($purchase->total) }}</td>
                                        <td class="text-end fw-semibold">{{ $money($purchase->balance) }}</td>
                                        <td class="text-end"><a href="{{ route('purchases.show', $purchase) }}" class="btn btn-sm btn-outline-success">Pagar</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">No hay deudas con proveedores.</div></div>
            @endforelse
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history"></i> Últimos pagos</h6>
                    @forelse($payments as $payment)
                        <div class="d-flex justify-content-between border-bottom py-1 small">
                            <span>
                                {{ $payment->paid_at->format('d/m/Y') }} · <a href="{{ route('purchases.show', $payment->purchase_id) }}" class="text-decoration-none">{{ $payment->purchase?->code }}</a>
                                <div class="text-muted">{{ $payment->purchase?->supplier?->name }} · {{ $payment->paymentMethod?->name }}</div>
                            </span>
                            <span class="fw-semibold">{{ $money($payment->amount) }}</span>
                        </div>
                    @empty
                        <p class="small text-muted mb-0">Sin pagos registrados.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
