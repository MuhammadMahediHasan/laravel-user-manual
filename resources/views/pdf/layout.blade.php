<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'User Manual' }}</title>
    <style>
        @page {
            margin-top: {{ config('user-manual.pdf.margins.top', 16) }}mm;
            margin-bottom: {{ config('user-manual.pdf.margins.bottom', 16) }}mm;
            margin-left: {{ config('user-manual.pdf.margins.left', 15) }}mm;
            margin-right: {{ config('user-manual.pdf.margins.right', 15) }}mm;
            header: html_manual-header;
            footer: html_manual-footer;
        }

        body {
            font-family: {{ config('user-manual.pdf.default_font', 'sans-serif') }};
            color: #1e293b;
            line-height: 1.6;
            font-size: 10.5pt;
        }

        h1, h2, h3, h4, h5, h6 {
            color: #0f172a;
            font-weight: 700;
            margin-top: 1.2em;
            margin-bottom: 0.5em;
            page-break-after: avoid;
        }

        h1 {
            font-size: 18pt;
            border-bottom: 2px solid {{ config('user-manual.ui.primary_color', '#FF2D20') }};
            padding-bottom: 6px;
        }

        h2 {
            font-size: 14pt;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }

        h3 { font-size: 12pt; }
        h4 { font-size: 10.5pt; }

        p { margin-bottom: 1em; }

        code {
            background-color: #f1f5f9;
            color: #0f172a;
            padding: 2px 4px;
            border-radius: 4px;
            font-family: {{ config('user-manual.pdf.default_font', 'sans-serif') }};
            font-size: 9.5pt;
        }

        pre {
            background-color: #0f172a;
            color: #f8fafc;
            padding: 12px;
            border-radius: 6px;
            font-family: {{ config('user-manual.pdf.default_font', 'sans-serif') }};
            font-size: 9pt;
            line-height: 1.4;
            margin-bottom: 1.2em;
        }

        pre code {
            background-color: transparent;
            color: inherit;
            padding: 0;
        }

        blockquote {
            border-left: 4px solid {{ config('user-manual.ui.primary_color', '#FF2D20') }};
            margin: 1em 0;
            padding: 8px 16px;
            background-color: #f8fafc;
            color: #475569;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.2em;
        }

        th, td {
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            text-align: left;
        }

        th {
            background-color: #f1f5f9;
            font-weight: 600;
        }

        ul, ol {
            margin-bottom: 1em;
            padding-left: 1.5em;
        }

        li { margin-bottom: 0.3em; }

        .page-break {
            page-break-after: always;
        }

        .cover-container {
            text-align: center;
            padding-top: 100px;
        }

        .cover-logo {
            max-height: 60px;
            margin-bottom: 30px;
        }

        .cover-title {
            font-size: 26pt;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 12px;
        }

        .cover-subtitle {
            font-size: 15pt;
            color: {{ config('user-manual.ui.primary_color', '#FF2D20') }};
            margin-bottom: 40px;
        }

        .cover-meta {
            font-size: 11pt;
            color: #64748b;
            margin-top: 150px;
        }

        .document-chapter-title {
            font-size: 20pt;
            color: {{ config('user-manual.ui.primary_color', '#FF2D20') }};
            margin-top: 20px;
            margin-bottom: 16px;
            border-bottom: 2px solid {{ config('user-manual.ui.primary_color', '#FF2D20') }};
            padding-bottom: 8px;
        }

        div.mpdf_toc, div.mpdf_toc_level_0, div.mpdf_toc_level_1, div.mpdf_toc_level_2, a.mpdf_toc_a, span.mpdf_toc_t, span.mpdf_toc_p {
            font-family: {{ config('user-manual.pdf.default_font', 'sans-serif') }};
        }

        div.mpdf_toc_level_0 {
            font-weight: bold;
            margin-top: 8px;
            margin-bottom: 4px;
            font-size: 11pt;
        }

        div.mpdf_toc_level_1 {
            margin-left: 16px;
            font-weight: normal;
            font-size: 10pt;
        }

        div.mpdf_toc_level_2 {
            margin-left: 32px;
            font-weight: normal;
            font-size: 9.5pt;
        }
    </style>
</head>
<body>
    @if(config('user-manual.pdf.header.show', true))
        <htmlpageheader name="manual-header">
            @include(config('user-manual.pdf.header.view', 'user-manual::pdf.header'))
        </htmlpageheader>
    @endif

    @if(config('user-manual.pdf.footer.show', true))
        <htmlpagefooter name="manual-footer">
            @include(config('user-manual.pdf.footer.view', 'user-manual::pdf.footer'))
        </htmlpagefooter>
    @endif

    @yield('content')
</body>
</html>
