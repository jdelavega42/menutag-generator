@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        @include('partials.menutag-config')

        {{--
            Performance budget (restyle brief §6): three.js + viewer.js live in
            an async chunk, reached only via dynamic import. Preloading it here
            (only the public layout hosts the 3D preview) keeps the parametric
            preview immediate. rescue(): a stale manifest without the chunk
            must degrade to "no preload", never to a 500.
        --}}
        @php($viewerChunk = rescue(fn () => Illuminate\Support\Facades\Vite::asset('resources/js/viewer.js'), null, false))
        @if ($viewerChunk)
            <link rel="modulepreload" href="{{ $viewerChunk }}" />
        @endif
    </head>
    <body class="min-h-screen bg-surface-0 text-text-primary antialiased">
        <flux:header container class="border-b border-border-subtle bg-surface-1">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-semibold text-text-primary" wire:navigate>
                {{-- Brand tile: stylized QR square, the bicolor echo of the product. --}}
                <x-app-logo-icon class="size-6 text-text-primary" aria-hidden="true" />
                <span>MenuTag Studio</span>
            </a>

            <flux:navbar class="-mb-px max-lg:hidden ms-4">
                <flux:navbar.item :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                    Configuratore
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            @auth
                <flux:navbar class="me-1.5">
                    <flux:navbar.item :href="route('dashboard')" wire:navigate>Dashboard</flux:navbar.item>
                </flux:navbar>
            @else
                <flux:navbar class="me-1.5">
                    <flux:navbar.item :href="route('login')">Accedi</flux:navbar.item>
                </flux:navbar>
                @if (Route::has('register'))
                    {{-- Registration is THE action for a guest → accent CTA (tokens §2). --}}
                    <flux:button variant="primary" size="sm" :href="route('register')">Registrati</flux:button>
                @endif
            @endauth
        </flux:header>

        <main class="mx-auto w-full max-w-7xl px-4 py-8 lg:px-8">
            {{ $slot }}
        </main>

        <footer class="mx-auto w-full max-w-7xl border-t border-border-subtle px-4 py-5 text-xs text-text-muted lg:px-8">
            <p>
                MenuTag Studio — targhette menù stampabili in 3D.
                I file di stampa generati da ospite restano disponibili per {{ (int) config('product.guests.retention_hours') }} ore.
            </p>
        </footer>

        {{--
            Toast host (contract 04): explicit, never-silent notifications.
            `size-adjusted` and `menutag-failed` are dispatched by Livewire
            as browser events and reach the window.
        --}}
        <div
            x-data="menuTagToasts()"
            x-on:size-adjusted.window="onSizeAdjusted($event.detail)"
            x-on:menutag-failed.window="onFailed($event.detail)"
            class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:inset-x-auto sm:right-6"
            aria-live="polite"
        >
            <template x-for="toast in toasts" :key="toast.id">
                <div
                    class="pointer-events-auto w-full max-w-sm rounded-card border p-4 shadow-lg"
                    :class="toast.tone === 'error'
                        ? 'border-blocked bg-blocked-surface text-blocked'
                        : 'border-border-strong bg-surface-2 text-text-primary'"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold" x-text="toast.title"></p>
                            <p class="mt-1 text-sm" x-text="toast.message"></p>
                        </div>
                        <button type="button" class="text-sm opacity-60 hover:opacity-100" x-on:click="remove(toast.id)" aria-label="Chiudi avviso">✕</button>
                    </div>
                </div>
            </template>
        </div>

        @fluxScripts
    </body>
</html>
