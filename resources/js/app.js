/**
 * Application entry point. Livewire 4 ships and boots its own Alpine: the
 * MenuTag Alpine components (configurator, viewer, toasts — contract 04)
 * are registered on `alpine:init`, which Livewire fires before starting
 * Alpine, so they are available on first render.
 */

import { registerMenuTagAlpine } from './menutag/configurator.js';

document.addEventListener('alpine:init', () => {
    registerMenuTagAlpine(window.Alpine);
});
