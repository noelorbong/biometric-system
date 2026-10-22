<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database setup — Biometric System</title>
    <style>
        body { font: 16px/1.6 system-ui, sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; padding: 36px; }
        main { max-width: 600px; margin: auto; }
        button { padding: 12px 24px; background: #0369a1; color: white; border: 0; border-radius: 8px; cursor: pointer; }
        code { overflow-wrap: anywhere; }
    </style>
</head>
<body>
<main>
    <h1>Database setup could not finish</h1>
    <p>The application cannot open until its database updates finish successfully.</p>
    @if (session('startup_failed'))
        <p role="alert">The retry failed. Please check the error log below.</p>
    @endif
    <p>Retry when ready. If another copy of the application is updating, let it finish first.</p>
    <form method="post" action="/desktop-startup">
        @csrf
        <button type="submit">Retry database setup</button>
    </form>
    <p>Error details are saved in:</p>
    <code>{{ storage_path('logs/laravel.log') }}</code>
</main>
</body>
</html>
