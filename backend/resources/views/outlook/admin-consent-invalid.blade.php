<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spacepad: consent could not be verified</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f9fafb;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 24px rgba(0,0,0,.06);
            max-width: 440px;
            width: 100%;
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #fee2e2;
            margin-bottom: 1.25rem;
        }
        .icon svg { width: 28px; height: 28px; }
        h1 { font-size: 1.25rem; font-weight: 600; color: #111827; margin-bottom: .5rem; }
        p  { font-size: .9375rem; color: #6b7280; line-height: 1.6; }
        .close-hint {
            margin-top: 1.75rem;
            font-size: .8125rem;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
        </div>
        <h1>Consent could not be verified</h1>
        <p>
            This approval link has expired, was already used, or does not belong to the
            Microsoft 365 organisation it was requested for.<br>
            Ask the person who set up Spacepad to start the admin consent step again.
        </p>
        <p class="close-hint">You can close this tab.</p>
    </div>
</body>
</html>
