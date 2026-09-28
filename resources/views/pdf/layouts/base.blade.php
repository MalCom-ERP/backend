<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@yield('title', 'Document Officiel')</title>
    <style>
        @page {
            margin: @yield('page_margin', '16mm 18mm 46mm 18mm');
            size: A4 @yield('orientation', 'portrait');
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.4;
            color: #1e293b;
            background-color: #ffffff;
        }

        h1, h2, h3, h4, h5, h6, p, ul, ol, dl, dd {
            margin: 0;
            padding: 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            border: none;
        }

        /* Typography */
        h1, h2, h3, h4, h5, h6 {
            color: #0f172a;
            font-weight: bold;
        }

        .text-xs { font-size: 6.5pt; }
        .text-sm { font-size: 7.5pt; }
        .text-base { font-size: 8pt; }
        .text-md { font-size: 9pt; }
        .text-lg { font-size: 11pt; }
        .text-xl { font-size: 13pt; }
        .text-2xl { font-size: 15pt; }

        .font-normal { font-weight: normal; }
        .font-medium { font-weight: 500; }
        .font-bold { font-weight: bold; }
        .font-black { font-weight: 900; }
        .italic { font-style: italic; }
        .uppercase { text-transform: uppercase; }

        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        /* Ink-friendly colors */
        .text-dark { color: #0f172a; }
        .text-slate { color: #334155; }
        .text-muted { color: #64748b; }
        .text-light { color: #94a3b8; }
        .text-emerald { color: #047857; }
        .text-rose { color: #be123c; }
        .text-amber { color: #b45309; }

        .border-slate { border-color: #cbd5e1; }
        .border-dark { border-color: #334155; }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2.5px 7px;
            font-size: 6.8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 3px;
        }
        .badge-success { background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .badge-warning { background-color: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .badge-danger { background-color: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-info { background-color: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }

        /* Header Layout (100% full width, clean & airy) */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            border-bottom: 1.5px solid #e2e8f0;
            padding-bottom: 10px;
        }

        .header-table td {
            vertical-align: top;
            border: none;
            padding: 0;
        }

        @php
            $brandPrimary = !empty($boutique->couleur_principale) ? $boutique->couleur_principale : '#0f172a';
            $brandSecondary = !empty($boutique->couleur_secondaire) ? $boutique->couleur_secondaire : '#4f46e5';
        @endphp

        .doc-title-badge {
            font-size: 14pt;
            font-weight: 900;
            letter-spacing: 1px;
            color: {{ $brandPrimary }};
            text-transform: uppercase;
            text-align: right;
            margin-bottom: 5px;
            border: none;
            background: transparent;
            padding: 0;
        }

        .brand-primary-text { color: {{ $brandPrimary }}; }
        .brand-secondary-text { color: {{ $brandSecondary }}; }

        /* Parties info (Clean 2-column layout without heavy box borders) */
        .info-card-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .info-card-table td {
            vertical-align: top;
            border: none;
            padding: 0;
        }

        .info-card {
            background-color: transparent;
            border: none;
            padding: 0;
        }

        .info-card-title {
            font-size: 6.5pt;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 3px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2px;
        }

        /* Data Table (International corporate standard - NO vertical grid lines) */
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 14px;
        }

        .table-data th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 7pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 7px 8px;
            border-top: 1.5px solid #cbd5e1;
            border-bottom: 1.5px solid #cbd5e1;
            border-left: none;
            border-right: none;
        }

        .table-data td {
            padding: 7px 8px;
            font-size: 8pt;
            border-bottom: 1px solid #f1f5f9;
            border-left: none;
            border-right: none;
            vertical-align: middle;
        }

        .table-data tr:nth-child(even) td {
            background-color: #fafbfc;
        }

        /* Totals Block (Clean right-aligned without cell boxes) */
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
        }

        .totals-table td {
            padding: 4px 6px;
            font-size: 8pt;
            border: none;
        }

        .totals-table .total-label {
            color: #64748b;
            text-align: right;
            padding-right: 10px;
            font-size: 7.5pt;
        }

        .totals-table .total-amount {
            color: #0f172a;
            text-align: right;
            font-weight: 600;
            width: 42%;
        }

        .totals-table .highlight-row td,
        .totals-table .grand-total td {
            border-top: 1.5px solid #0f172a;
            padding-top: 6px;
            padding-bottom: 6px;
        }

        .totals-table .highlight-row .total-label,
        .totals-table .grand-total .total-label {
            font-size: 8.5pt;
            font-weight: 900;
            color: #0f172a;
        }

        .totals-table .highlight-row .total-amount,
        .totals-table .grand-total .total-amount {
            font-size: 10pt;
            font-weight: 900;
            color: #0f172a;
        }

        /* Signatures block pinned at bottom of the last page */
        .signatures-pinned-bottom {
            position: absolute;
            bottom: -36mm;
            left: 0;
            right: 0;
            width: 100%;
        }

        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
            page-break-inside: avoid;
        }

        .signatures-table td {
            width: 50%;
            border: none;
            padding: 0 15px;
            text-align: center;
            vertical-align: top;
            background-color: transparent;
        }

        .signature-title {
            font-size: 7pt;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
            letter-spacing: 0.5px;
        }

        .signature-line {
            margin: 24px auto 0;
            width: 160px;
            border-bottom: 1px solid #cbd5e1;
        }

        /* Fixed footer on all pages */
        .doc-footer-fixed {
            position: fixed;
            bottom: -44mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 6.5pt;
            color: #94a3b8;
            line-height: 1.4;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }

        /* Standard flow footer */
        .doc-footer {
            margin-top: 18px;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            text-align: center;
            font-size: 6.5pt;
            color: #94a3b8;
            line-height: 1.4;
            page-break-inside: avoid;
        }

        .tabular-nums {
            font-variant-numeric: tabular-nums;
        }

        .no-break { page-break-inside: avoid; }
    </style>
    @yield('styles')
</head>
<body>
    @yield('fixed_footer')
    <div class="document-container">
        @yield('content')
    </div>
</body>
</html>
