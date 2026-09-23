<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Murojaat')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>body{background:transparent}</style>
</head>
<body class="p-0">
<div class="mx-auto max-w-lg p-2">@yield('content')</div>
</body>
</html>
