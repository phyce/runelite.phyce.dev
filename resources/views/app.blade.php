<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title data-inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/img/favicon.png" sizes="any">

        <link rel="preload" as="font" type="font/woff2" href="/fonts/poppins-400.woff2" crossorigin>
        <link rel="preload" as="font" type="font/woff2" href="/fonts/poppins-500.woff2" crossorigin>

        {!! SEOMeta::generate(false) !!}
        {!! OpenGraph::generate() !!}
        {!! Twitter::generate() !!}
        {!! JsonLd::generate() !!}

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
