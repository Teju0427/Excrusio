/* Excursio dashboards — Chart.js setup shared by every chart.
   Config comes from <script type="application/json" data-chart-for="canvasId"> written by the server. */
(function () {
    'use strict';
    if (typeof Chart === 'undefined') return;

    var INK = '#292D29', MUTED = '#5F635C', GRID = 'rgba(41,45,41,.08)', SURFACE = '#FFFFFF';
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = MUTED;
    Chart.defaults.animation.duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 400;

    var inr = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 });
    function fmt(format, v) {
        if (format === 'money') return '₹' + inr.format(v / 100);
        if (format === 'percent') return v + '%';
        return inr.format(v);
    }

    document.querySelectorAll('script[data-chart-for]').forEach(function (node) {
        var canvas = document.getElementById(node.dataset.chartFor);
        if (!canvas) return;
        var c = JSON.parse(node.textContent), horizontal = c.type === 'hbar', isPie = c.type === 'doughnut';
        var many = c.series.length > 1;

        var datasets = c.series.map(function (s) {
            if (isPie) {
                return { label: s.name, data: s.data, backgroundColor: s.colors, borderColor: SURFACE, borderWidth: 2, hoverOffset: 4 };
            }
            if (c.type === 'line') {
                return { label: s.name, data: s.data, borderColor: s.color, backgroundColor: s.color, borderWidth: 2,
                         pointRadius: 0, pointHoverRadius: 5, pointHoverBorderColor: SURFACE, pointHoverBorderWidth: 2, tension: .3 };
            }
            return { label: s.name, data: s.data, backgroundColor: s.color, borderRadius: 4, borderSkipped: 'start',
                     maxBarThickness: 28, borderColor: SURFACE, borderWidth: { top: 0, right: 0, bottom: 0, left: 0 } };
        });

        var axis = function (isValue) {
            return {
                beginAtZero: true,
                grid: { color: isValue ? GRID : 'transparent', drawTicks: false },
                border: { display: false },
                ticks: isValue ? { padding: 6, precision: 0, maxTicksLimit: 5, callback: function (v) { return fmt(c.format, v); } }
                               : { padding: 6, autoSkip: true, maxRotation: 0, callback: function (v) {
                                   var l = this.getLabelForValue(v); return typeof l === 'string' && l.length > 20 ? l.slice(0, 19) + '…' : l; } }
            };
        };

        new Chart(canvas, {
            type: isPie ? 'doughnut' : (horizontal ? 'bar' : c.type),
            data: { labels: c.labels, datasets: datasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                indexAxis: horizontal ? 'y' : 'x',
                cutout: isPie ? '62%' : undefined,
                interaction: c.type === 'line' ? { mode: 'index', intersect: false } : { mode: 'nearest', intersect: true },
                plugins: {
                    // A legend for 2+ series (and for doughnut slices); a single series is named by the title.
                    legend: { display: many || isPie, position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, color: INK, padding: 14 } },
                    tooltip: {
                        backgroundColor: INK, padding: 10, cornerRadius: 6, boxPadding: 4, usePointStyle: true,
                        callbacks: { label: function (ctx) { return ' ' + (many || isPie ? (isPie ? ctx.label : ctx.dataset.label) + ': ' : '') + fmt(c.format, ctx.parsed.y !== undefined && !horizontal && !isPie ? ctx.parsed.y : (horizontal ? ctx.parsed.x : ctx.parsed)); } }
                    }
                },
                scales: isPie ? {} : (horizontal ? { x: axis(true), y: axis(false) } : { x: axis(false), y: axis(true) })
            }
        });
    });
})();
