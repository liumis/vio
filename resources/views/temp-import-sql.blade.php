<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Temporary SQL Import</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; max-width: 680px; }
        .ok { background: #ecfdf5; border: 1px solid #10b981; padding: 10px; margin-bottom: 16px; }
        .err { background: #fef2f2; border: 1px solid #ef4444; padding: 10px; margin-bottom: 16px; }
        label { display: block; margin: 10px 0 6px; }
        input[type="file"] { width: 100%; padding: 8px; }
        button { margin-top: 14px; padding: 8px 14px; }
    </style>
</head>
<body>
    <h1>Temporary SQL Import</h1>
    <p>Upload <code>vio_full.sql</code> and import into the current app database.</p>

    @if (session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="err">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="post" enctype="multipart/form-data">
        @csrf
        <label for="sql_file">SQL file</label>
        <input id="sql_file" name="sql_file" type="file" accept=".sql,.txt" required>

        <button type="submit">Import now</button>
    </form>
</body>
</html>
