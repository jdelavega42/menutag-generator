/**
 * Application entry point. Livewire 4 ships and boots its own Alpine: the
 * MenuTag Alpine components (configurator, viewer, toasts — contract 04)
 * are registered on `alpine:init`, which Livewire fires before starting
 * Alpine, so they are available on first render.
 *
 * Performance budget (restyle brief §6): three.js and viewer.js live in an
 * async chunk (dynamic import in menutag/configurator.js), keeping the
 * synchronous entry small. To keep the parametric preview immediate, the
 * chunk is warmed here on idle — same module URL as the component's dynamic
 * import, so the second import() resolves from the module cache. Pages
 * without the viewer (window.menuTagProduct is injected only by the public
 * layout) skip the warm-up entirely.
 */

import { registerMenuTagAlpine } from './menutag/configurator.js';

document.addEventListener('alpine:init', () => {
    registerMenuTagAlpine(window.Alpine);
});

const warmViewerChunk = () => {
    if (window.menuTagProduct) {
        import('./viewer.js');
    }
};

if (typeof requestIdleCallback === 'function') {
    requestIdleCallback(warmViewerChunk, { timeout: 2000 });
} else {
    setTimeout(warmViewerChunk, 300);
}
