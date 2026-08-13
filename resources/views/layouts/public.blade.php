@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        @include('partials.menutag-config')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100">
        <flux:header container class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold" wire:navigate>
                <x-app-logo-icon class="size-6 fill-current" />
                <span>MenuTag Generator</span>
            </a>

            <flux:navbar class="-mb-px max-lg:hidden ms-4">
                <flux:navbar.item :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                    Configuratore
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="me-1.5">
                @auth
                    <flux:navbar.item :href="route('dashboard')" wire:navigate>Dashboard</flux:navbar.item>
                @else
                    <flux:navbar.item :href="route('login')">Accedi</flux:navbar.item>
                    @if (Route::has('register'))
                        <flux:navbar.item :href="route('register')">Registrati</flux:navbar.item>
                    @endif
                @endauth
            </flux:navbar>
        </flux:header>

        <main class="mx-auto w-full max-w-7xl px-4 py-8 lg:px-8">
            {{ $slot }}
        </main>

        <footer class="mx-auto w-full max-w-7xl px-4 pb-8 text-xs text-zinc-500 lg:px-8 dark:text-zinc-400">
            <p>
                MenuTag Generator — targhette menù stampabili in 3D.
                Gli STL degli ospiti restano disponibili per {{ (int) config('product.guests.retention_hours') }} ore.
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
                    class="pointer-events-auto w-full max-w-sm rounded-lg border p-4 shadow-lg"
                    :class="toast.tone === 'error'
                        ? 'border-red-300 bg-red-50 text-red-900 dark:border-red-700 dark:bg-red-950 dark:text-red-100'
                        : 'border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-700 dark:bg-sky-950 dark:text-sky-100'"
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
