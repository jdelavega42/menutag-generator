<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Aspetto')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Aspetto') }}</flux:heading>

    <x-pages::settings.layout heading="Aspetto" subheading="Il tema di MenuTag Studio">
        {{-- Dark-only v1 (restyle brief §3.1): the light/dark/system toggle the
             starter kit shipped here is removed on purpose — the light theme
             is a declared roadmap item, not a half-inverted dark theme. The
             route stays (existing route names are invariant). --}}
        <flux:text>
            MenuTag Studio usa un tema scuro nativo, progettato come un banco di
            lavoro: superfici scure, misure leggibili, anteprima 3D protagonista.
            Il tema chiaro è in roadmap.
        </flux:text>
    </x-pages::settings.layout>
</section>
