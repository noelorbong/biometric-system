<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <!-- <meta http-equiv="Content-Security-Policy" content="worker-src 'self' http://localhost:8000"> -->
        <link rel="shortcut icon" href="/images/logo/logo.png" type="image/png">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>SSU DRRMC</title>
        @vite(['resources/js/app.js'])
    </head>
    <body class="dark:bg-gray-900">
        <div id="app">
            <router-view></router-view>
        </div>
    </body>
</html>