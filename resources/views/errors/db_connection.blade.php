<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Database Error | {{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" href="/favicon.jpg" type="image/jpeg">
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f5f7fa; margin: 0; padding: 0; }
        .container { max-width: 700px; margin: 60px auto; padding: 1rem; }
        .card { background: #fff; border: 1px solid #f1f3f5; border-radius: 14px; box-shadow: 0 8px 20px rgba(0,0,0,.06); padding: 1.25rem; }
        .title { color: #d97706; margin-bottom: .75rem; }
        .message { margin-bottom: 1rem; }
        .btn { display: inline-block; background: #334155; color: #fff; padding: .55rem .95rem; border-radius: 8px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1 class="title">Database Connection Error</h1>
            <p class="message">{{ $message }}</p>
            <p>Please verify that your database server is reachable and that your .env database settings are correct:</p>
            <ul>
                <li>DB_HOST</li>
                <li>DB_PORT</li>
                <li>DB_DATABASE</li>
                <li>DB_USERNAME</li>
                <li>DB_PASSWORD</li>
            </ul>
            <a href="{{ url('/') }}" class="btn">Retry</a>
        </div>
    </div>
</body>
</html>
