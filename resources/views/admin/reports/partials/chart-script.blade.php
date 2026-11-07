<script>
// Gráficos de tiempo de los reportes: SVG propio, sin librerías. Marcas finas,
// una sola escala, y la lectura exacta con el puntero o el teclado.
(function () {
    const SVG = 'http://www.w3.org/2000/svg';
    const meta = @json($chartMeta);
    const HEIGHT = 260;
    const M = { top: 22, right: 12, bottom: 26 };

    const full = (v, format) => format === 'money'
        ? meta.symbol + ' ' + Number(v).toLocaleString('en-US', { minimumFractionDigits: meta.decimals, maximumFractionDigits: meta.decimals })
        : Number(v).toLocaleString('en-US');

    // Eje: completo hasta 9.999, compacto desde 10K. Un solo estilo por eje.
    const tick = (v, top) => {
        if (top >= 1000000) return (v / 1000000).toLocaleString('en-US', { maximumFractionDigits: 1 }) + 'M';
        if (top >= 10000) return (v / 1000).toLocaleString('en-US', { maximumFractionDigits: 1 }) + 'K';
        return Number(v).toLocaleString('en-US');
    };

    function niceStep(range, count) {
        if (range <= 0) return count ? 1 : 1;
        const rough = range / 4;
        const power = Math.pow(10, Math.floor(Math.log10(rough)));
        const f = rough / power;
        const step = (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * power;
        return count ? Math.max(1, Math.ceil(step)) : step;
    }

    function make(name, attrs, parent, text) {
        const node = document.createElementNS(SVG, name);
        Object.keys(attrs).forEach((k) => node.setAttribute(k, attrs[k]));
        if (text !== undefined) node.textContent = text;
        if (parent) parent.appendChild(node);
        return node;
    }

    function render(host) {
        const chart = JSON.parse(host.dataset.chart);
        const width = Math.max(host.clientWidth, 280);
        const all = chart.series.flatMap((s) => s.values);
        const isCount = chart.format === 'count';
        const step = niceStep(Math.max(...all, 0) - Math.min(...all, 0), isCount);
        const top = Math.max(Math.ceil(Math.max(...all, 0) / step) * step, step);
        const bottom = Math.min(Math.floor(Math.min(...all, 0) / step) * step, 0);

        const ticks = [];
        for (let v = bottom; v <= top + step / 2; v += step) ticks.push(Math.round(v * 100) / 100);

        const left = 14 + Math.max(...ticks.map((v) => tick(v, top).length)) * 7;
        const plotW = width - left - M.right;
        const plotH = HEIGHT - M.top - M.bottom;
        const y = (v) => M.top + plotH - (v - bottom) / (top - bottom) * plotH;
        const n = chart.labels.length;
        const column = chart.kind === 'column';
        const band = plotW / n;
        const x = (i) => column ? left + band * (i + 0.5) : (n === 1 ? left + plotW / 2 : left + plotW * i / (n - 1));

        host.querySelector('svg')?.remove();
        const svg = make('svg', { viewBox: '0 0 ' + width + ' ' + HEIGHT, 'aria-hidden': 'true' });
        host.prepend(svg);

        // Rejilla discreta y línea base.
        ticks.forEach((v) => {
            make('line', { x1: left, x2: width - M.right, y1: y(v), y2: y(v), stroke: v === 0 ? 'var(--rpt-axis)' : 'var(--rpt-grid)', 'stroke-width': 1 }, svg);
            make('text', { x: left - 8, y: y(v) + 4, 'text-anchor': 'end' }, svg, tick(v, top));
        });

        // Solo las etiquetas de fecha que caben.
        const every = Math.max(1, Math.ceil(n / Math.floor(plotW / 62)));
        chart.labels.forEach((label, i) => {
            if (i % every === 0) make('text', { x: x(i), y: HEIGHT - 7, 'text-anchor': column || n === 1 ? 'middle' : (i === 0 ? 'start' : 'middle') }, svg, label);
        });

        const bars = [];
        const dots = [];

        if (column) {
            const serie = chart.series[0];
            const barW = Math.min(24, Math.max(band - 2, 2));
            const peak = serie.values.indexOf(Math.max(...serie.values));
            serie.values.forEach((v, i) => {
                if (v === 0) { bars.push(null); return; }
                const h = Math.abs(y(v) - y(0));
                const r = Math.min(4, h, barW / 2);
                const x0 = x(i) - barW / 2;
                const y0 = y(0);
                const dir = v > 0 ? -1 : 1;
                const sweep = v > 0 ? 1 : 0;
                const path = 'M' + x0 + ' ' + y0 + 'V' + (y0 + dir * (h - r))
                    + 'A' + r + ' ' + r + ' 0 0 ' + sweep + ' ' + (x0 + r) + ' ' + (y0 + dir * h)
                    + 'H' + (x0 + barW - r)
                    + 'A' + r + ' ' + r + ' 0 0 ' + sweep + ' ' + (x0 + barW) + ' ' + (y0 + dir * (h - r))
                    + 'V' + y0 + 'Z';
                bars.push(make('path', { d: path, fill: serie.color }, svg));
            });
            if (serie.values[peak] > 0) {
                const label = make('text', { class: 'rpt-peak', x: x(peak), y: y(serie.values[peak]) - 6, 'text-anchor': 'middle' }, svg, full(serie.values[peak], chart.format));
                // Sin salirse del gráfico por los costados.
                const half = label.getComputedTextLength() / 2;
                label.setAttribute('x', Math.min(Math.max(x(peak), left + half), width - M.right - half));
            }
        } else {
            const alone = chart.series.length === 1;
            chart.series.forEach((serie) => {
                const points = serie.values.map((v, i) => x(i) + ' ' + y(v));
                if (alone && n > 1) {
                    make('path', { d: 'M' + x(0) + ' ' + y(0) + 'L' + points.join('L') + 'L' + x(n - 1) + ' ' + y(0) + 'Z', fill: serie.color, 'fill-opacity': 0.1 }, svg);
                }
                if (n > 1) {
                    make('path', { d: 'M' + points.join('L'), fill: 'none', stroke: serie.color, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }, svg);
                }
                // Punto final, con anillo blanco para distinguirlo donde se cruzan.
                make('circle', { cx: x(n - 1), cy: y(serie.values[n - 1]), r: 4, fill: serie.color, stroke: '#fff', 'stroke-width': 2 }, svg);
            });
        }

        // Capa de lectura: el puntero y las flechas buscan el tramo, no la marca.
        const cross = make('line', { y1: M.top, y2: M.top + plotH, stroke: 'var(--rpt-axis)', 'stroke-width': 1, visibility: 'hidden' }, svg);
        if (!column) {
            chart.series.forEach((serie) => dots.push(make('circle', { r: 4, fill: serie.color, stroke: '#fff', 'stroke-width': 2, visibility: 'hidden' }, svg)));
        }

        const tip = host.querySelector('.rpt-tip');
        let current = -1;

        function show(i) {
            current = i;
            if (column) {
                bars.forEach((bar, b) => bar && bar.setAttribute('fill-opacity', b === i ? 1 : 0.45));
            } else {
                cross.setAttribute('x1', x(i));
                cross.setAttribute('x2', x(i));
                cross.setAttribute('visibility', 'visible');
                dots.forEach((dot, s) => {
                    dot.setAttribute('cx', x(i));
                    dot.setAttribute('cy', y(chart.series[s].values[i]));
                    dot.setAttribute('visibility', 'visible');
                });
            }

            tip.replaceChildren();
            const title = document.createElement('div');
            title.className = 'rpt-tip-title';
            title.textContent = chart.labels[i];
            tip.appendChild(title);
            chart.series.forEach((serie) => {
                const row = document.createElement('div');
                row.className = 'rpt-tip-row';
                const key = document.createElement('span');
                key.className = 'rpt-key-line';
                key.style.background = serie.color;
                const value = document.createElement('strong');
                value.textContent = full(serie.values[i], chart.format);
                const name = document.createElement('span');
                name.textContent = serie.name;
                row.append(key, value, name);
                tip.appendChild(row);
            });

            // A escala del SVG, y sin salirse del gráfico.
            const scale = host.clientWidth / width;
            const tipW = tip.offsetWidth;
            let px = x(i) * scale + 12;
            if (px + tipW > host.clientWidth) px = x(i) * scale - tipW - 12;
            tip.style.transform = 'translate(' + Math.max(px, 0) + 'px, ' + (M.top * scale) + 'px)';
            tip.style.visibility = 'visible';
        }

        function hide() {
            current = -1;
            bars.forEach((bar) => bar && bar.removeAttribute('fill-opacity'));
            cross.setAttribute('visibility', 'hidden');
            dots.forEach((dot) => dot.setAttribute('visibility', 'hidden'));
            tip.style.visibility = 'hidden';
        }

        function indexAt(clientX) {
            const box = svg.getBoundingClientRect();
            const px = (clientX - box.left) * (width / box.width);
            const raw = column ? Math.floor((px - left) / band) : Math.round(n === 1 ? 0 : (px - left) / plotW * (n - 1));
            return Math.min(Math.max(raw, 0), n - 1);
        }

        host.onpointermove = (event) => show(indexAt(event.clientX));
        host.onpointerdown = (event) => show(indexAt(event.clientX));
        host.onpointerleave = hide;
        host.onblur = hide;
        host.onfocus = () => { if (host.matches(':focus-visible')) show(n - 1); };
        host.onkeydown = (event) => {
            if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                event.preventDefault();
                const base = current < 0 ? n - 1 : current;
                show(Math.min(Math.max(base + (event.key === 'ArrowRight' ? 1 : -1), 0), n - 1));
            } else if (event.key === 'Escape') {
                hide();
            }
        };
    }

    const hosts = document.querySelectorAll('.rpt-chart[data-chart]');
    hosts.forEach(render);

    if (window.ResizeObserver) {
        const widths = new WeakMap();
        const observer = new ResizeObserver((entries) => entries.forEach((entry) => {
            const width = Math.round(entry.contentRect.width);
            if (widths.get(entry.target) !== width) {
                if (widths.has(entry.target)) render(entry.target);
                widths.set(entry.target, width);
            }
        }));
        hosts.forEach((host) => observer.observe(host));
    }

    // En papel se ven las listas completas.
    window.addEventListener('beforeprint', () => document.querySelectorAll('details.rpt-more').forEach((d) => { d.dataset.wasOpen = d.open ? '1' : ''; d.open = true; }));
    window.addEventListener('afterprint', () => document.querySelectorAll('details.rpt-more').forEach((d) => { d.open = d.dataset.wasOpen === '1'; }));
})();
</script>
