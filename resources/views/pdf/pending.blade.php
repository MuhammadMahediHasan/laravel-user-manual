<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        use MuhammadMahediHasan\UserManual\Support\Config;
        use MuhammadMahediHasan\UserManual\Support\ManualAssets;

        $appName = Config::string('user-manual.ui.app_name', (string) config('app.name', 'Laravel'));
        $primaryColor = Config::string('user-manual.ui.primary_color', '#FF2D20');
    @endphp
    <title>{{ __('user-manual::messages.pdf_generating_title') }} | {{ $appName }}</title>
    <link rel="stylesheet" href="{{ ManualAssets::url('css/user-manual.css') }}">
    <style>
        :root { --user-manual-primary: {{ $primaryColor }}; }
        .user-manual-pending { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .user-manual-pending__card { max-width: 460px; text-align: center; }
        .user-manual-pending__spinner { width: 40px; height: 40px; margin: 0 auto 20px; border-radius: 50%; border: 4px solid rgba(0, 0, 0, .1); border-top-color: var(--user-manual-primary); animation: user-manual-spin 1s linear infinite; }
        .user-manual-pending__title { font-size: 20px; font-weight: 600; margin: 0 0 8px; }
        .user-manual-pending__message { margin: 0 0 20px; opacity: .8; line-height: 1.5; }
        .user-manual-pending__retry { display: inline-block; padding: 8px 16px; border-radius: 6px; background: var(--user-manual-primary); color: #fff; text-decoration: none; }
        @keyframes user-manual-spin { to { transform: rotate(360deg); } }
    </style>
</head>

<body class="user-manual">
    <main class="user-manual-pending">
        <div class="user-manual-pending__card">
            <div class="user-manual-pending__spinner" aria-hidden="true"></div>
            <h1 class="user-manual-pending__title">{{ __('user-manual::messages.pdf_generating_title') }}</h1>
            <p class="user-manual-pending__message">{{ $message }}</p>
            <a href="{{ request()->fullUrl() }}" class="user-manual-pending__retry">{{ __('user-manual::messages.pdf_try_again') }}</a>
        </div>
    </main>
</body>

</html>
