<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>S3 File Tool</title>
    <style>
        :root { --bg:#f4efe8; --panel:#fffaf2; --ink:#1f2937; --muted:#6b7280; --accent:#0f766e; --accent2:#d97706; --line:#e7dccd; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: var(--ink); background: radial-gradient(circle at top left, #fff6e7, var(--bg) 52%, #eadfce); }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 40px 18px 64px; }
        .hero { display: grid; gap: 12px; margin-bottom: 24px; }
        .hero h1 { margin: 0; font-size: clamp(32px, 5vw, 54px); letter-spacing: -0.04em; }
        .hero p { margin: 0; color: var(--muted); max-width: 780px; line-height: 1.6; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .card { background: rgba(255,255,255,.78); backdrop-filter: blur(8px); border: 1px solid var(--line); border-radius: 20px; padding: 20px; box-shadow: 0 18px 50px rgba(31,41,55,.08); }
        .card h2 { margin: 0 0 12px; font-size: 20px; }
        label { display:block; font-size: 14px; font-weight: 700; margin: 12px 0 6px; }
        input { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid #d9cdb9; background: #fff; font-size: 14px; }
        button, .btn { display:inline-block; padding: 12px 16px; border: 0; border-radius: 12px; background: linear-gradient(135deg, var(--accent), #115e59); color: #fff; font-weight: 700; cursor: pointer; text-decoration: none; }
        .btn.secondary { background: linear-gradient(135deg, var(--accent2), #b45309); }
        .hint { font-size: 13px; color: var(--muted); margin-top: 8px; line-height: 1.5; }
        .result { margin-top: 16px; padding: 14px; border-radius: 14px; background: #f8fafc; border: 1px solid #dbe4ee; overflow-wrap: anywhere; }
        .result strong { display:block; margin-bottom: 6px; }
        .error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .full { grid-column: 1 / -1; }
        @media (max-width: 820px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="hero">
            <h1>S3 File Tool</h1>
            <p>Use this page to upload a file to S3 under <strong>documents/YYYY/MM/</strong> and to generate a temporary public download URL from a path plus file name.</p>
        </div>

        <div class="grid">
            <section class="card">
                <h2>Upload to S3</h2>
                <form method="POST" action="{{ route('s3-files.upload') }}" enctype="multipart/form-data">
                    @csrf
                    <label for="file">File</label>
                    <input id="file" type="file" name="file" required>
                    <p class="hint">Files are saved to <code>documents/{{ now()->format('Y/m') }}/</code> in your S3 bucket.</p>
                    <button type="submit">Upload</button>
                </form>

                @if($uploadedUrl)
                    <div class="result">
                        <strong>Upload Result</strong>
                        <div>{{ $uploadedUrl }}</div>
                        @if($savedPath)
                            <div class="hint">Stored path: <code>{{ $savedPath }}</code></div>
                        @endif
                    </div>
                @endif
            </section>

            <section class="card">
                <h2>Temporary Download URL</h2>
                <form method="POST" action="{{ route('s3-files.temporary-url') }}">
                    @csrf
                    <label for="path">Path</label>
                    <input id="path" type="text" name="path" placeholder="documents/2026/06" required>

                    <label for="file_name">File Name</label>
                    <input id="file_name" type="text" name="file_name" placeholder="example.pdf" required>

                    <label for="expires_in">Expires In Minutes</label>
                    <input id="expires_in" type="number" name="expires_in" value="15" min="1" max="10080">

                    <p class="hint">Enter the folder path without the file name, then the exact file name stored in S3.</p>
                    <button type="submit" class="btn secondary">Generate URL</button>
                </form>

                @if($temporaryUrl)
                    <div class="result">
                        <strong>Temporary URL</strong>
                        <div><a href="{{ $temporaryUrl }}" target="_blank" rel="noopener">{{ $temporaryUrl }}</a></div>
                        @if($expiresAt)
                            <div class="hint">Expires at: <code>{{ $expiresAt }}</code></div>
                        @endif
                    </div>
                @endif
            </section>

            <section class="card full @if($errorMessage) error @endif">
                <h2>Environment Settings</h2>
                <p class="hint" style="margin-top:0;">
                    Put your AWS S3 credentials in <code>.env</code>. The important values are:
                </p>
                <div class="result">
                    <div><code>AWS_ACCESS_KEY_ID=your-key</code></div>
                    <div><code>AWS_SECRET_ACCESS_KEY=your-secret</code></div>
                    <div><code>AWS_DEFAULT_REGION=us-east-1</code></div>
                    <div><code>AWS_BUCKET=your-bucket-name</code></div>
                    <div><code>AWS_USE_PATH_STYLE_ENDPOINT=false</code></div>
                    <div><code>AWS_ENDPOINT=</code> if you use a custom S3-compatible provider</div>
                </div>
                @if($errorMessage)
                    <div class="result error">
                        <strong>Error</strong>
                        <div>{{ $errorMessage }}</div>
                    </div>
                @endif
            </section>
        </div>
    </div>
</body>
</html>
