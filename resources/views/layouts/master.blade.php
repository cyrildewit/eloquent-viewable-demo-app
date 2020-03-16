<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <title>@yield('title') - {{ config('app.name') }}</title>

    <link rel="stylesheet" href="{{ asset('css/main.css') }}">
</head>
<body class="bg-gray-100">
    @yield('content')
</body>
</html>
