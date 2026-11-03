@extends('layouts.app')

@section('title', 'Cuentas por cobrar')

@section('page')
@php $money = fn ($v) => \App\Support\Money::format($v, $tenantCompany); @endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-hourglass"></i> Cuentas por cobrar</h1>
            <p class="text-muted mb-0">{{ $debtors->count() }} clientes deben {{ $money($total) }}.</p>
        </div>
        <div class="btn-group">
            <a href="{{ route('receivables.index') }}" class="btn btn-sm {{ request('overdue') ? 'btn-outline-dark' : 'btn-dark' }}">Todas</a>
            <a href="{{ route('receivables.index', ['overdue' => 1]) }}" class="btn btn-sm {{ request('overdue') ? 'btn-dark' : 'btn-outline-dark' }}">Vencidas</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            @forelse($debtors as $debtor)
                <div class="card border-0 shadow-sm mb-3 {{ $debtor['overdue'] ? 'border-start border-danger border-4' : '' }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <a href="{{ $debtor['client'] ? route('clients.show', $debtor['client']) : '#' }}" class="fw-semibold text-decoration-none">{{ $debtor['client']?->full_name ?? 'Sin cliente' }}</a>
                                @if($debtor['overdue'])<span class="badge bg-danger">Con deuda vencida</span>@endif
                                <div class="small text-muted">
                                    {{ $debtor['client']?->phone }}
                                    @if($debtor['client']?->whatsapp_url)
                                        <a href="{{ $debtor['client']->whatsapp_url }}?text={{ rawurlencode('Hola ' . $debtor['client']->first_name . ', le recordamos que tiene un saldo pendiente de ' . $money($debtor['balance']) . ' en ' . $tenantCompany?->name . '. ¡Gracias!') }}" target="_blank" rel="noopener" class="text-success text-decoration-none ms-1"><i class="bi bi-whatsapp"></i> Recordar</a>
                                    @endif
                                </div>
                            </div>
                            <div class="fs-5 fw-bold text-danger">{{ $money($debtor['balance']) }}</div>
                        </div>
                        <table class="table table-sm align-middle mb-0 mt-2">
                            <tbody>
                                @foreach($debtor['sales'] as $sale)
                                    <tr>
                                        <td><a href="{{ route('sales.show', $sale) }}" class="text-decoration-none">{{ $sale->code }}</a></td>
                                        <td class="small text-muted">{{ $sale->created_at->format('d/m/Y') }}</td>
                                        <td class="small {{ $sale->isOverdue() ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $sale->due_date ? 'vence ' . $sale->due_date->format('d/m/Y') : 'sin vencimiento' }}</td>
                                        <td class="text-end small">de {{ $money($sale->total) }}</td>
                                        <td class="text-end fw-semibold">{{ $money($sale->balance) }}</td>
                                        <td class="text-end"><a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-outline-success">Cobrar</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">No hay cuentas pendientes. 🎉</div></div>
            @endforelse
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history"></i> Últimos cobros</h6>
                    @forelse($collections as $payment)
                        <div class="d-flex justify-content-between border-bottom py-1 small">
                            <span>
                                {{ $payment->paid_at->format('d/m H:i') }} · <a href="{{ route('sales.show', $payment->sale_id) }}" class="text-decoration-none">{{ $payment->sale?->code }}</a>
                                <div class="text-muted">{{ $payment->sale?->client?->full_name }} · {{ $payment->paymentMethod?->name }}</div>
                            </span>
                            <span class="fw-semibold text-success">{{ $money($payment->amount) }}</span>
                        </div>
                    @empty
                        <p class="small text-muted mb-0">Sin abonos registrados.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
