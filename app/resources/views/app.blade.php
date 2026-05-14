<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'Vora') }}</title>

    <link rel="preconnect" href="https://rsms.me/">
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">

    @routes
    @php
        $__pusher = [
            'key'     => env('PUSHER_APP_KEY', 'helpdeskkey'),
            'host'    => env('VITE_PUSHER_HOST', 'localhost'),
            'port'    => (int) env('VITE_PUSHER_PORT', 6001),
            'scheme'  => env('VITE_PUSHER_SCHEME', 'http'),
            'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
            'enabled' => env('BROADCAST_CONNECTION') !== 'log',
        ];
    @endphp
    <script>window.__pusher__ = {!! json_encode($__pusher) !!};</script>
    @vite('resources/js/app.js')
    @inertiaHead
</head>
<body class="font-sans antialiased h-full bg-background text-foreground">
    @inertia
</body>
</html>
