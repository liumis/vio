<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wheelsys sign-in failed</title>
    <style>
        body { font-family: system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0; background: #fef2f2; color: #7f1d1d; }
        .box { max-width: 32rem; padding: 2rem; background: #fff; border: 1px solid #fecaca; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Could not open Wheelsys</h1>
        <p>{{ $message }}</p>
        <p><a href="{{ url('/') }}">Back to VIO</a></p>
    </div>
</body>
</html>
