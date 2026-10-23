@extends('layouts.panel', ['title' => 'Check-in'])
@section('panel')
<h1 class="mb-2" style="font-size: 2.4rem">Check-in</h1>
<p class="text-muted mb-4">Scan the guest’s QR code, or type the booking reference.</p>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="ex-panel">
            <h2 class="h5 mb-3"><i class="bi bi-qr-code-scan me-1"></i>Scan</h2>
            <div id="ex-scanner" class="ex-scanner" data-find="{{ route('host.check-in.find') }}"></div>
            <button type="button" class="btn btn-ex-primary mt-3" id="ex-scan-start"><i class="bi bi-camera me-1"></i>Start camera</button>
            <p class="small text-muted mt-2 mb-0" id="ex-scan-msg" role="status">Your browser will ask for camera permission. Nothing is recorded or uploaded.</p>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ex-panel mb-4">
            <h2 class="h5 mb-3"><i class="bi bi-keyboard me-1"></i>Enter code</h2>
            <form method="POST" action="{{ route('host.check-in.find') }}" id="ex-checkin-form">
                @csrf
                <label class="form-label" for="code">Booking reference</label>
                <div class="d-flex gap-2">
                    <input id="code" name="code" class="form-control @error('code') is-invalid @enderror" placeholder="EXC-ST-ABC123" value="{{ old('code') }}" autocomplete="off" required maxlength="200">
                    <button class="btn btn-ex-primary">Find</button>
                </div>
                @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </form>
        </div>
        <div class="ex-panel">
            <h2 class="h5 mb-3">Arriving soon</h2>
            @forelse ($today as $b)
                <div class="d-flex justify-content-between align-items-center border-top py-2 small gap-2">
                    <span><span class="fw-medium">{{ $b->guest_name }}</span> · {{ $b->listing->title }}<br><span class="text-muted">{{ $b->starts_at->format('D j M, H:i') }} · {{ $b->reference }}</span></span>
                    @if ($b->checked_in_at)
                        <span class="ex-badge ex-badge-success"><i class="bi bi-check2"></i>In</span>
                    @else
                        <a class="btn btn-sm btn-ex-outline" href="{{ route('host.check-in.show', $b->checkin_token) }}">Open</a>
                    @endif
                </div>
            @empty
                <p class="text-muted small mb-0">No confirmed arrivals in the next 36 hours.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('vendor/qrcode/html5-qrcode.min.js') }}" defer></script>
@endpush
