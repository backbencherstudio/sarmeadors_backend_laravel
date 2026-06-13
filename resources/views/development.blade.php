<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Sarmeadors - In Development</title>

    <link rel="stylesheet" href="{{ asset('css/development.css') }}">
</head>

<body>
    <main class="development-page">
        <section class="development-panel" aria-labelledby="development-title">
            <div class="brand-mark" aria-hidden="true">S</div>

            <p class="eyebrow">Coming Soon</p>

            <h1 id="development-title">Website is in development</h1>

            <p class="intro">
                We are building a smoother Sarmeadors experience. Please check back soon.
            </p>

            <div class="progress-track" aria-hidden="true">
                <span></span>
            </div>
        </section>
    </main>
</body>

</html>
