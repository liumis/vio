<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center p-8">
        <h1 class="text-2xl font-semibold">{{ config('app.name') }}</h1>
        <p class="mt-4 text-gray-600">
            <a class="text-amber-600 underline" href="{{ url('/admin') }}">Open admin</a>
        </p>
    </div>
</body>
</html>
