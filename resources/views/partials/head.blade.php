<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

{{-- Visible product name is «MenuTag Studio» (surface rebrand only: code
     identifiers, routes and config keys stay untouched). --}}
<title>
    {{ filled($title ?? null) ? $title.' — MenuTag Studio' : 'MenuTag Studio' }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- No @fluxAppearance: single dark-native theme in v1 (restyle brief §3.1),
     <html class="dark"> is hardcoded in every layout. --}}
