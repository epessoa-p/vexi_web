<style>
    /* Reportes. Colores de datos: paleta validada para daltonismo (ver ReportData::SLOTS). */
    .rpt-root {
        --rpt-ink: #1f2937;
        --rpt-ink-2: #4b5563;
        --rpt-muted: #6b7280;
        --rpt-grid: #e5e7eb;
        --rpt-axis: #c9ccd1;
        --rpt-good: #006300;
        --rpt-bad: #b42318;
        --rpt-warn: #92590a;
        --rpt-accent: #2a78d6;
    }

    .rpt-print-head { display: none; }

    /* Navegación: siempre a la vista, se desliza en el teléfono. */
    .rpt-tabs { display: flex; gap: .25rem; overflow-x: auto; border-bottom: 1px solid var(--rpt-grid); scrollbar-width: none; }
    .rpt-tabs::-webkit-scrollbar { display: none; }
    .rpt-tab { padding: .6rem 1rem; color: var(--rpt-ink-2); text-decoration: none; white-space: nowrap; border-bottom: 2px solid transparent; margin-bottom: -1px; font-weight: 500; }
    .rpt-tab:hover { color: var(--rpt-ink); background: rgba(0,0,0,.03); border-radius: .375rem .375rem 0 0; }
    .rpt-tab.is-active { color: var(--rpt-ink); border-bottom-color: var(--rpt-ink); font-weight: 600; }

    /* Filtros */
    .rpt-filters { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: .75rem; }
    .rpt-presets { display: flex; flex-wrap: wrap; gap: .375rem; align-items: center; }
    .rpt-pill { display: inline-block; padding: .3rem .75rem; border-radius: 2rem; background: #fff; border: 1px solid var(--rpt-grid); color: var(--rpt-ink-2); font-size: .875rem; text-decoration: none; cursor: pointer; list-style: none; }
    .rpt-pill::-webkit-details-marker { display: none; }
    .rpt-pill:hover { border-color: var(--rpt-axis); color: var(--rpt-ink); }
    .rpt-pill.is-active { background: var(--rpt-ink); border-color: var(--rpt-ink); color: #fff; }
    .rpt-custom { display: contents; }
    .rpt-custom-body { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; flex-basis: 100%; padding-top: .25rem; }
    .rpt-custom-body input[type=date] { width: auto; }
    .rpt-filter-side { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; }
    .rpt-segmented { display: inline-flex; align-items: center; gap: .125rem; }
    .rpt-segmented a { padding: .2rem .625rem; border-radius: .375rem; font-size: .875rem; color: var(--rpt-ink-2); text-decoration: none; }
    .rpt-segmented a:hover { background: rgba(0,0,0,.05); }
    .rpt-segmented a.is-active { background: #fff; color: var(--rpt-ink); font-weight: 600; box-shadow: 0 0 0 1px var(--rpt-grid); }
    .rpt-branch { width: auto; }
    .rpt-range { font-size: .875rem; margin: -.75rem 0 1rem; }

    /* Tarjetas */
    .rpt-card { background: #fff; border-radius: .75rem; box-shadow: 0 1px 2px rgba(16,24,40,.06), 0 1px 3px rgba(16,24,40,.08); padding: 1.25rem; height: 100%; }
    .rpt-card-title { font-size: 1rem; font-weight: 600; margin: 0; color: var(--rpt-ink); }
    .rpt-card-sub { font-size: .8125rem; color: var(--rpt-muted); margin: .125rem 0 0; }
    .rpt-head { margin-bottom: 1rem; }
    .rpt-head-row { display: flex; justify-content: space-between; gap: .75rem; align-items: flex-start; flex-wrap: wrap; }
    .rpt-heading { font-size: 1.0625rem; font-weight: 600; margin: 1.75rem 0 .75rem; color: var(--rpt-ink); }

    /* Cifra protagonista */
    .rpt-hero-label { color: var(--rpt-ink-2); font-size: .9375rem; }
    .rpt-hero { font-size: 3rem; line-height: 1.1; font-weight: 600; color: var(--rpt-ink); letter-spacing: -.02em; margin: .25rem 0; word-break: break-word; }
    .rpt-hero.is-negative { color: var(--rpt-bad); }
    .rpt-sum { margin: 1rem 0 0; border-top: 1px solid var(--rpt-grid); padding-top: .75rem; font-size: .9375rem; }
    .rpt-sum div { display: flex; justify-content: space-between; gap: 1rem; padding: .15rem 0; }
    .rpt-sum dt { font-weight: 400; color: var(--rpt-ink-2); }
    .rpt-sum dd { margin: 0; font-variant-numeric: tabular-nums; }
    .rpt-sum .is-total { border-top: 1px solid var(--rpt-grid); margin-top: .35rem; padding-top: .5rem; font-weight: 600; }
    .rpt-sum .is-total dt { color: var(--rpt-ink); font-weight: 600; }
    .rpt-how { font-size: .8125rem; color: var(--rpt-muted); margin-top: .75rem; }
    .rpt-how summary { cursor: pointer; }
    .rpt-how p { margin: .375rem 0 0; }

    /* Indicadores */
    .rpt-tile { display: block; color: inherit; text-decoration: none; }
    a.rpt-tile:hover { box-shadow: 0 2px 8px rgba(16,24,40,.14); color: inherit; }
    .rpt-tile-label { font-size: .875rem; color: var(--rpt-ink-2); display: flex; justify-content: space-between; gap: .5rem; }
    .rpt-tile-label i.rpt-go { color: var(--rpt-axis); }
    a.rpt-tile:hover .rpt-go { color: var(--rpt-ink); }
    .rpt-tile-value { font-size: 1.625rem; font-weight: 600; color: var(--rpt-ink); margin: .25rem 0 .125rem; line-height: 1.2; word-break: break-word; }
    .rpt-tile-hint { font-size: .8125rem; color: var(--rpt-muted); }

    /* Variación: dirección con flecha y signo, además del color. */
    .rpt-delta { font-size: .8125rem; display: inline-flex; align-items: center; gap: .25rem; font-weight: 600; }
    .rpt-delta small { font-weight: 400; color: var(--rpt-muted); font-size: 1em; }
    .rpt-delta.is-good { color: var(--rpt-good); }
    .rpt-delta.is-bad { color: var(--rpt-bad); }
    .rpt-delta.is-neutral { color: var(--rpt-muted); }

    /* Lectura en lenguaje claro */
    .rpt-insights { list-style: none; padding: 0; margin: 0; display: grid; gap: .875rem; }
    .rpt-insights li { display: flex; gap: .75rem; align-items: flex-start; }
    .rpt-insights i { width: 2rem; height: 2rem; flex: none; border-radius: .5rem; background: #f0f5fc; color: #1c5cab; display: inline-flex; align-items: center; justify-content: center; }
    .rpt-insights span { padding-top: .2rem; }

    /* Lista para revisar */
    .rpt-attn { list-style: none; padding: 0; margin: 0; }
    .rpt-attn li + li { border-top: 1px solid var(--rpt-grid); }
    .rpt-attn a, .rpt-attn .rpt-attn-row { display: flex; align-items: center; gap: .625rem; padding: .625rem 0; color: var(--rpt-ink); text-decoration: none; font-size: .9375rem; }
    .rpt-attn a:hover { color: #1c5cab; }
    .rpt-attn .bi-chevron-right { margin-left: auto; color: var(--rpt-axis); }
    .rpt-attn .is-critical { color: var(--rpt-bad); }
    .rpt-attn .is-warning { color: var(--rpt-warn); }

    /* Barras ordenadas */
    .rpt-rank { list-style: none; padding: 0; margin: 0; display: grid; gap: .75rem; }
    .rpt-rank-row { display: flex; justify-content: space-between; gap: 1rem; font-size: .875rem; margin-bottom: .25rem; }
    .rpt-rank-label { color: var(--rpt-ink); min-width: 0; overflow-wrap: anywhere; }
    .rpt-rank-label small { color: var(--rpt-muted); }
    .rpt-rank-value { font-weight: 600; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .rpt-rank-value small { font-weight: 400; color: var(--rpt-muted); margin-left: .25rem; }
    .rpt-rank-bar { height: 8px; min-width: 2px; background: var(--rpt-accent); border-radius: 0 4px 4px 0; }
    .rpt-more { margin-top: .75rem; }
    .rpt-more summary, .rpt-table-toggle summary { font-size: .8125rem; color: var(--rpt-ink-2); cursor: pointer; }
    .rpt-more .rpt-rank { margin-top: .75rem; }

    /* Una parte de un todo */
    .rpt-segments { display: flex; gap: 2px; height: 28px; }
    .rpt-segment { border-radius: 4px; color: #fff; font-size: .75rem; font-weight: 600; line-height: 28px; padding-left: .375rem; white-space: nowrap; min-width: 3px; overflow: hidden; }
    .rpt-segment.is-dark { color: #0b0b0b; }
    .rpt-legend { display: flex; flex-wrap: wrap; gap: .5rem 1rem; list-style: none; padding: 0; margin: .75rem 0 0; font-size: .875rem; }
    .rpt-legend li { display: inline-flex; align-items: center; gap: .375rem; color: var(--rpt-ink-2); }
    .rpt-legend strong { color: var(--rpt-ink); font-weight: 600; }
    .rpt-key { width: 10px; height: 10px; border-radius: 3px; flex: none; }
    .rpt-key-line { width: 14px; height: 2px; flex: none; border-radius: 2px; }

    /* Gráfico */
    .rpt-chart { position: relative; touch-action: pan-y; outline-offset: 4px; }
    .rpt-chart svg { display: block; width: 100%; height: auto; overflow: visible; }
    .rpt-chart text { font: 12px 'Segoe UI', Tahoma, sans-serif; fill: var(--rpt-muted); font-variant-numeric: tabular-nums; }
    .rpt-chart .rpt-peak { fill: var(--rpt-ink); font-weight: 600; }
    .rpt-tip { position: absolute; top: 0; left: 0; pointer-events: none; background: #fff; border-radius: .5rem; box-shadow: 0 4px 16px rgba(16,24,40,.18); padding: .5rem .75rem; font-size: .8125rem; white-space: nowrap; z-index: 5; visibility: hidden; }
    .rpt-tip-title { color: var(--rpt-muted); margin-bottom: .125rem; }
    .rpt-tip-row { display: flex; align-items: center; gap: .375rem; }
    .rpt-tip-row strong { color: var(--rpt-ink); }
    .rpt-tip-row span:last-child { color: var(--rpt-ink-2); }
    .rpt-table-toggle { margin-top: .75rem; }
    .rpt-table { font-size: .8125rem; margin: .5rem 0 0; font-variant-numeric: tabular-nums; }
    .rpt-table th { font-weight: 600; color: var(--rpt-ink-2); }

    .rpt-empty { text-align: center; color: var(--rpt-muted); padding: 1.5rem .5rem; font-size: .875rem; }
    .rpt-empty i { display: block; font-size: 1.5rem; color: var(--rpt-axis); margin-bottom: .25rem; }

    @media (max-width: 575.98px) {
        .rpt-hero { font-size: 2.5rem; }
        .rpt-card { padding: 1rem; }
    }

    /* Papel y PDF: solo el contenido, sin menús ni controles. */
    @media print {
        @page { size: A4 portrait; margin: 12mm; }
        body { background: #fff !important; }
        .app-sidebar, .app-topbar, .offcanvas, .rpt-noprint, .rpt-table-toggle, .alert { display: none !important; }
        .app-shell, .app-main { display: block !important; margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: none !important; }
        .rpt-root { max-width: none !important; padding: 0 !important; }
        .rpt-print-head { display: block; border-bottom: 2px solid #1f2937; padding-bottom: .375rem; margin-bottom: .75rem; font-size: .875rem; }
        .rpt-card { box-shadow: none; border: 1px solid var(--rpt-grid); break-inside: avoid; height: auto; }
        .rpt-heading { break-after: avoid; }
        .rpt-root * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
