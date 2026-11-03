{{-- Método de pago y caja para cobrar o reembolsar. Parámetros: $paymentMethods, $sessions, $cashEnabled, $withReference (bool). --}}
<select name="payment_method_id" class="form-select form-select-sm">
    @foreach($paymentMethods as $method)
        <option value="{{ $method->id }}">{{ $method->name }}</option>
    @endforeach
</select>
@if($withReference ?? false)
    <input type="text" name="reference" class="form-control form-control-sm" placeholder="Referencia (si aplica)" maxlength="80">
@endif
@if($cashEnabled)
    <select name="cash_session_id" class="form-select form-select-sm">
        @forelse($sessions as $session)
            <option value="{{ $session->id }}">Caja: {{ $session->caja?->name }}</option>
        @empty
            <option value="">No hay cajas abiertas</option>
        @endforelse
    </select>
@endif
