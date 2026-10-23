@props(['label', 'value', 'icon' => null, 'hint' => null])
<div class="ex-stat">
    <div class="d-flex justify-content-between align-items-start">
        <span class="ex-stat-label">{{ $label }}</span>
        @if ($icon)<i class="bi {{ $icon }} ex-stat-icon"></i>@endif
    </div>
    <div class="ex-stat-value">{{ $value }}</div>
    @if ($hint)<div class="small text-muted">{{ $hint }}</div>@endif
</div>
