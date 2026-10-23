@props(['id', 'type' => 'bar', 'title', 'labels' => [], 'series' => [], 'height' => 260, 'format' => 'number', 'note' => null])
{{--
    One chart = a Chart.js canvas + the same numbers as a table (for screen readers, print and
    anyone who prefers figures). series: [['name' => …, 'color' => …, 'data' => [...]], …]
--}}
@php
    $fmt = fn ($v) => match ($format) { 'money' => money((int) $v), 'percent' => $v.'%', default => number_format((float) $v) };
    $config = ['type' => $type, 'labels' => array_values($labels), 'series' => array_values($series), 'format' => $format];
@endphp
<figure class="ex-panel ex-chart mb-0 h-100" aria-labelledby="{{ $id }}-title">
    <figcaption class="d-flex justify-content-between align-items-baseline gap-2 mb-2">
        <h2 class="h6 mb-0" id="{{ $id }}-title">{{ $title }}</h2>
        @if ($note)<small class="text-muted">{{ $note }}</small>@endif
    </figcaption>
    @if (collect($series)->flatMap(fn ($s) => $s['data'])->sum() == 0)
        <p class="text-muted small mb-0 py-4 text-center">No data yet.</p>
    @else
        <div class="ex-chart-box" style="height: {{ $height }}px">
            <canvas id="{{ $id }}" role="img" aria-label="{{ $title }}. The same figures are in the table below."></canvas>
        </div>
        <script type="application/json" data-chart-for="{{ $id }}">@json($config)</script>
        <details class="ex-chart-table mt-2">
            <summary class="small">View as table</summary>
            <div class="table-responsive mt-2">
                <table class="table table-sm small mb-0">
                    <thead><tr><th scope="col">{{ $type === 'doughnut' ? 'Category' : '' }}</th>@foreach ($series as $s)<th scope="col" class="text-end">{{ $s['name'] }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach (array_values($labels) as $i => $label)
                            <tr><th scope="row" class="fw-normal">{{ $label }}</th>@foreach ($series as $s)<td class="text-end">{{ $fmt($s['data'][$i] ?? 0) }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</figure>
