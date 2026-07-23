@extends('user-manual::pdf.layout')

@section('content')
    @php
        use MuhammadMahediHasan\UserManual\Support\ManualNumber;

        $locale = $locale ?? app()->getLocale();
    @endphp

    @if(config('user-manual.pdf.cover_page.enabled', true))
        @include(config('user-manual.pdf.cover_page.view', 'user-manual::pdf.cover'), ['locale' => $locale])
        <div class="page-break"></div>
    @endif

    <div class="toc-container">
        <tocpagebreak links="1" font="{{ config('user-manual.pdf.default_font', 'sans-serif') }}" toc-font="{{ config('user-manual.pdf.default_font', 'sans-serif') }}" toc-margin-top="10" toc-margin-bottom="10"></tocpagebreak>
    </div>

    @foreach($pages as $index => $item)
        @php
            $displayIndex = ManualNumber::formatDigits($item['index'], $locale);
            $displayTitle = "{$displayIndex} {$item['title']}";
        @endphp
        <div class="manual-page-section @if(!$loop->first) page-break @endif">
            <tocentry content="{{ $displayTitle }}" level="{{ $item['level'] }}"></tocentry>
            {!! $item['content'] !!}
        </div>
    @endforeach
@endsection
