<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Forex Web Trader') }}@hasSection('title') — @yield('title')@endif</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ink: '#0b0e11',
                        panel: '#151a21',
                        panel2: '#1c232c',
                        edge: '#2a323d',
                        up: '#26a69a',
                        down: '#ef5350',
                        accent: '#3b82f6',
                    },
                },
            },
        };
    </script>
</head>
<body class="h-full bg-ink text-gray-200 antialiased">
    @yield('body')
</body>
</html>
