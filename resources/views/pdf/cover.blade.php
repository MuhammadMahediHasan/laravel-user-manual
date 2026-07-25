@php
    use Illuminate\Support\Carbon;
    use MuhammadMahediHasan\UserManual\Support\ManualNumber;

    $locale = $locale ?? app()->getLocale();

    $rawVersion = config('user-manual.pdf.cover_page.version') ?? config('user-manual.version', '1.0');
    $versionText = ManualNumber::formatDigits($rawVersion, $locale);

    $dateFormat = config('user-manual.pdf.cover_page.date_format', 'F Y');
    $rawDate = Carbon::now()->locale($locale)->translatedFormat($dateFormat);
    $formattedDate = ManualNumber::formatDigits($rawDate, $locale);
@endphp

<div class="cover-container">
    @if($logoUrl = config('user-manual.pdf.cover_page.logo_url') ?? config('user-manual.ui.logo_url'))
        <img src="{{ $logoUrl }}" class="cover-logo" alt="Logo" />
    @endif

    @if(!$logoUrl)
    <div class="cover-title">
        {{ config('app.name', 'Application') }}
    </div>
    @endif

    <div class="cover-subtitle">
        @php
            $configuredSubtitle = config('user-manual.pdf.cover_page.subtitle');
        @endphp
        {{ ($configuredSubtitle && $configuredSubtitle !== 'Official User Documentation') ? $configuredSubtitle : __('user-manual::messages.pdf_cover_subtitle') }}
    </div>

    <div class="cover-meta">
        <div>{{ __('user-manual::messages.version_label') }} {{ $versionText }}</div>
        <div>{{ __('user-manual::messages.generated_label') }} {{ $formattedDate }}</div>
    </div>
</div>
