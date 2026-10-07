<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Read by the raw `fetch` uploads (onboarding files, gallery, video). --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title data-inertia>{{ config('app.name', 'Miocoach') }}</title>

    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.ts'])
    @inertiaHead
</head>

<body class="antialiased bg-background text-foreground dark:bg-background dark:text-foreground">
    @inertia
</body>

</html>