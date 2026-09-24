<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" href="/favicon.ico">
    <title>{{ config('app.name') }} - Accounting</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/portal.tsx'])
    @inertiaHead
</head>
<body class="antialiased bg-gray-50">
    @inertia
</body>
</html>
