@php $e = $expense; @endphp
<div class="container-fluid" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0">{{ $e ? 'Editar gasto' : 'Registrar gasto' }}</h1>
        <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <form action="{{ $action }}" method="POST" enctype="multipart/form-data" class="card border-0 shadow-sm">
        @csrf
        @if($method !== 'POST') @method($method) @endif
        <div class="card-body p-4 row g-3">
            <div class="col-md-8">
                <label class="form-label">Concepto <span class="text-danger">*</span></label>
                <input type="text" name="concept" class="form-control @error('concept') is-invalid @enderror" value="{{ old('concept', $e?->concept) }}" maxlength="255" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Categoría</label>
                <select name="expense_category_id" class="form-select">
                    <option value="">—</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('expense_category_id', $e?->expense_category_id) === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Monto ({{ \App\Support\Money::symbol($tenantCompany) }}) <span class="text-danger">*</span></label>
                <input type="number" name="amount" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $e?->amount) }}" required @disabled($e?->cash_session_id)>
                @if($e?->cash_session_id)<div class="form-text">Salió de la caja: para cambiar el monto elimínalo y regístralo de nuevo.</div>@endif
            </div>
            <div class="col-md-4">
                <label class="form-label">Fecha <span class="text-danger">*</span></label>
                <input type="date" name="spent_at" class="form-control @error('spent_at') is-invalid @enderror" value="{{ old('spent_at', ($e?->spent_at ?? now())->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Responsable</label>
                <select name="user_id" class="form-select">
                    @foreach($professionals as $professional)
                        <option value="{{ $professional->id }}" @selected((string) old('user_id', $e?->user_id ?? auth()->id()) === (string) $professional->id)>{{ $professional->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Método de pago</label>
                <select name="payment_method_id" class="form-select @error('payment_method_id') is-invalid @enderror" @disabled($e?->cash_session_id)>
                    <option value="">—</option>
                    @foreach($paymentMethods as $paymentMethod)
                        <option value="{{ $paymentMethod->id }}" @selected((string) old('payment_method_id', $e?->payment_method_id) === (string) $paymentMethod->id)>{{ $paymentMethod->name }}</option>
                    @endforeach
                </select>
            </div>
            @if(! $e && $sessions->isNotEmpty())
                <div class="col-md-6">
                    <label class="form-label">¿Se pagó desde una caja?</label>
                    <select name="cash_session_id" class="form-select @error('cash_session_id') is-invalid @enderror">
                        <option value="">No (pago externo, banco…)</option>
                        @foreach($sessions as $session)
                            <option value="{{ $session->id }}" @selected((string) old('cash_session_id') === (string) $session->id)>Sí, de {{ $session->caja?->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-6">
                <label class="form-label">Comprobante <small class="text-muted">(imagen o PDF)</small></label>
                <input type="file" name="receipt" accept="image/*,application/pdf" class="form-control @error('receipt') is-invalid @enderror">
                @if($e?->receipt_url)<div class="form-text"><a href="{{ $e->receipt_url }}" target="_blank">Ver comprobante actual</a></div>@endif
            </div>
            <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea name="notes" rows="2" class="form-control">{{ old('notes', $e?->notes) }}</textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Guardar</button>
                <a href="{{ route('expenses.index') }}" class="btn btn-light border">Cancelar</a>
            </div>
        </div>
    </form>
</div>
