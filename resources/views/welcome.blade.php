<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Laravel</title>
        <style>
            body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f3f4f6; color: #111827; }
            main { text-align: center; padding: 2rem; }
            h1 { font-size: 2rem; font-weight: 600; margin: 0 0 0.5rem; }
            p { margin: 0; color: #6b7280; font-size: 0.95rem; }
            a { color: #ef4444; }
        </style>
    </head>
    <body>
        <main>
            <h1>Laravel</h1>
            <p>v{{ Illuminate\Foundation\Application::VERSION }} · PHP {{ PHP_VERSION }}</p>
            <p style="margin-top:1rem;"><a href="https://laravel.com/docs">Documentation</a></p>
        </main>
    </body>
</html>
