<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Opening Wheelsys…</title>
    <style>
        body { font-family: system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0; background: #f8fafc; color: #223a4e; }
        .box { text-align: center; padding: 2rem; }
        .muted { color: #64748b; font-size: 0.9rem; margin-top: 0.5rem; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Signing in to Wheelsys…</h1>
        <p class="muted">If nothing happens, use the button below.</p>
        <form id="wheelsys-sso" method="post" action="{{ $action }}">
            @foreach ($fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <button type="submit">Continue to Wheelsys</button>
        </form>
    </div>
    <script>
        document.getElementById('wheelsys-sso').submit();
    </script>
</body>
</html>
