<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>DinoRace - Authentication</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Press+Start+2P&display=swap" rel="stylesheet">
    <script>
        window.reverbConfig = {
            key: '{{ config("broadcasting.connections.reverb.key", env("REVERB_APP_KEY", "")) }}',
            wsHost: '{{ config("broadcasting.connections.reverb.options.host", env("REVERB_HOST", "")) }}',
            wsPort: +'{{ config("broadcasting.connections.reverb.options.port", env("REVERB_PORT", 80)) }}',
            wssPort: +'{{ config("broadcasting.connections.reverb.options.port", env("REVERB_PORT", 443)) }}',
            scheme: '{{ config("broadcasting.connections.reverb.options.scheme", env("REVERB_SCHEME", "https")) }}'
        };
    </script>
    @vite(['resources/css/app.css', 'resources/js/auth.js'])
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #0f172a;
            color: #f8fafc;
            font-family: 'Orbitron', sans-serif;
            overflow: hidden;
        }
        #app {
            height: 100vh;
            width: 100vw;
            display: flex;
            flex-direction: column;
        }
    </style>
</head>
<body>
    <div id="app"></div>
</body>
</html>
