<div
    style="
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 4px;
        margin-bottom: 10px;
        font-size: 8.5pt;
        color: #64748b; font-family: {{ config('user-manual.pdf.default_font', 'sans-serif') }};
    ">
    <table style="width: 100%; border: none; margin: 0;">
        <tr>
            <td style="border: none; padding: 0;">
            </td>
            <td style="border: none; padding: 0; text-align: right;">
                {{ config('user-manual.pdf.cover_page.title') ?? (config('app.name', 'Application') . ' ' . __('user-manual::messages.title')) }}
            </td>
        </tr>
    </table>
</div>
