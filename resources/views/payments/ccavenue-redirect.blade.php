<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redirecting to Payment</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: Georgia, serif;
            background: linear-gradient(160deg, #f7f2e8 0%, #f3efe6 50%, #e6efe7 100%);
            color: #1f2937;
        }

        .panel {
            width: min(92vw, 520px);
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            text-align: center;
        }

        .spinner {
            width: 44px;
            height: 44px;
            margin: 0 auto 20px;
            border: 4px solid rgba(14, 116, 144, 0.16);
            border-top-color: #0f766e;
            border-radius: 999px;
            animation: spin 0.8s linear infinite;
        }

        button {
            margin-top: 18px;
            border: 0;
            border-radius: 999px;
            background: #0f766e;
            color: #fff;
            padding: 12px 20px;
            font: inherit;
            cursor: pointer;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="panel">
        <div class="spinner" aria-hidden="true"></div>
        <h1>Redirecting to CCAvenue</h1>
        <p>Please wait while we open the secure payment page.</p>
        <form id="ccavenue-form" method="POST" action="{{ $gatewayUrl }}">
            <input type="hidden" name="encRequest" value="{{ $encRequest }}">
            <input type="hidden" name="access_code" value="{{ $accessCode }}">
            <noscript>
                <button type="submit">Continue to payment</button>
            </noscript>
        </form>
    </div>

    <script>
        document.getElementById('ccavenue-form').submit();
    </script>
</body>
</html>