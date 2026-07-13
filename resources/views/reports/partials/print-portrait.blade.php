<style>
@media print {
    @page { size: A4 portrait; margin: 12mm; }

    /* Simple black & white — strip all colours, shadows and rounded corners */
    *, *::before, *::after {
        color: #000 !important;
        background: transparent !important;
        box-shadow: none !important;
        text-shadow: none !important;
        border-radius: 0 !important;
    }

    body, html { background: #fff !important; }

    /* Show the simple print-only header, hide KPI stat boxes */
    .print-only { display: block !important; }
    .report-kpis { display: none !important; }

    /* Hide app chrome and interactive-only bits */
    .admin-topbar,
    .no-print,
    .noprint,
    .breadcrumb,
    .btn,
    button,
    .pagination,
    .alert,
    canvas,
    .chart,
    .apexcharts-canvas,
    form {
        display: none !important;
    }

    .app-shell, main, .admin-main { padding: 0 !important; margin: 0 !important; }

    /* Fit everything inside the A4 page width — no horizontal clipping */
    html, body { width: 100% !important; }
    .app-shell, main, .admin-main, .container, .container-fluid, .card, .card-body, .row, [class^="col"], [class*=" col"] {
        max-width: 100% !important;
        width: auto !important;
        flex: 0 0 100% !important;
    }
    .table-responsive { overflow: visible !important; width: 100% !important; }

    /* Flatten cards */
    .card, .card-header, .card-body, .card-footer {
        border: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin: 0 0 8px !important;
    }
    .card-header { font-weight: bold; padding-bottom: 4px !important; }

    /* Plain B&W tables — sized to always fit the A4 width */
    table {
        width: 100% !important;
        table-layout: fixed !important;
        border-collapse: collapse !important;
        font-size: 8.5pt !important;
    }
    table th, table td {
        border: 1px solid #000 !important;
        padding: 3px 4px !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
        word-break: break-word !important;
    }
    thead th { font-weight: bold !important; }
    /* Neutralise no-wrap helpers that would push columns off the page */
    .text-nowrap, td.text-nowrap, th.text-nowrap { white-space: normal !important; }

    /* Kill Bootstrap coloured helpers */
    .badge, .text-bg-primary, .text-bg-success, .text-bg-danger, .text-bg-warning, .text-bg-info {
        border: 1px solid #000 !important;
        padding: 0 4px !important;
        font-weight: normal !important;
    }
    .table-striped > tbody > tr:nth-of-type(odd) > *,
    .table-hover > tbody > tr:hover > * { background: #fff !important; }
}
</style>
