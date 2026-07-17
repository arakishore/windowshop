<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>No Labels to Print</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f5f7fb;
            color: #111827;
            font-family: Arial, sans-serif;
        }

        .panel {
            width: min(420px, calc(100vw - 32px));
            padding: 24px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
        }

        a {
            display: inline-block;
            margin-top: 16px;
            color: #0d6efd;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main class="panel">
        <h1>No labels to print</h1>
        <p>{{ $message }}</p>
        <a href="{{ $backUrl }}">Back to Barcode Labels</a>
    </main>
</body>
</html>
