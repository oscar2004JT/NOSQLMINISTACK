<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Mercado Global</title>
    <link rel="stylesheet" href="{{ asset('css/mercado.css') }}">
</head>
<body>
    <div id="root"></div>

    <script>
        window.APP_CONFIG = {
            userId: "{{ $userId }}",
            featuredOrderId: "{{ $featuredOrderId }}",
            apiBaseUrl: "{{ $apiBaseUrl }}"
        };
    </script>
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script crossorigin src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script type="text/babel" src="{{ asset('js/mercado-app.jsx') }}"></script>
</body>
</html>
