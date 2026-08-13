/**
 * Alpine components for the configurator page (contract 04).
 *
 * - menuTagConfigurator: holds the PreviewParams entangled with Livewire.
 *   Every change updates the three.js preview DIRECTLY (a `menutag-preview`
 *   window event) with ZERO server requests; Livewire only receives the
 *   values through a single debounced runLiveValidation() call and at
 *   submit. Product bands (QR availability, functional minimum, NFC plan)
 *   are computed live in the browser with the SAME config values as PHP.
 * - menuTagViewer: owns the three.js canvas behind wire:ignore — scene
 *   created once in init (x-init), disposed in destroy().
 * - menuTagToasts: explicit, never-silent notifications for size-adjusted /
 *   menutag-failed browser events.
 */

import { createMenuTagViewer } from '../viewer.js';
import {
    EPS,
    bicolorLayers,
    byteLength,
    minQrVersion,
    minSizeForQr,
    roundUpToTenth,
    squareModuleAdvantagePct,
} from './qr-rules.js';

const num = (value, fallback = 0) => {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : fallback;
};

const product = () => window.menuTagProduct ?? null;

export function registerMenuTagAlpine(Alpine) {
    Alpine.data('menuTagConfigurator', (entangled) => ({
        ...entangled,

        _lastPreviewJson: null,
        _validateTimer: null,

        init() {
            // First paint: push the initial params to the viewer so the demo
            // QR is visible immediately, without any server round trip.
            this.$nextTick(() => this.broadcastPreview());

            // Switching to inlay proposes the contained depth from config
            // (spec §3.6: keep the bichromatic layer count low) — but never
            // over a depth the user set by hand. Same rule as the Form
            // Request normalization, same config value, applied live.
            this.$watch('mode', (mode) => {
                if (mode === 'inlay' && !this.depthTouched) {
                    this.depth = product().inlay.default_depth_mm;
                }
            });
        },

        destroy() {
            if (this._validateTimer) {
                clearTimeout(this._validateTimer);
            }
        },

        /** PreviewParams — the exact contract-04 shape consumed by the viewer. */
        get previewParams() {
            return {
                shape: this.shape,
                size: num(this.size),
                fillet: num(this.fillet),
                thickness: num(this.thickness),
                baseProfile: this.baseProfile,
                rimWidth: num(this.rimWidth),
                recessDepth: num(this.recessDepth),
                front: this.front,
                back: this.back,
                mode: this.mode,
                depth: num(this.depth),
                qrDataFront: this.qrDataFront || null,
                qrDataBack: this.qrDataBack || null,
                qrEc: this.qrEc,
                nfc: Boolean(this.nfc),
                tagDiameter: num(this.tagDiameter, 25),
                // Extra hint for the preview texture, not part of the DTO.
                logoPreviewUrl: this.logoPreviewUrl || null,
            };
        },

        /**
         * Called from x-effect: Alpine tracks every property read inside
         * previewParams, so any change lands here. The viewer is updated
         * immediately; Livewire is only pinged with a debounced validation.
         */
        syncPreview(json) {
            if (json === this._lastPreviewJson) {
                return;
            }

            const isFirstRun = this._lastPreviewJson === null;
            this._lastPreviewJson = json;
            this.broadcastPreview();

            if (isFirstRun) {
                return;
            }

            if (this._validateTimer) {
                clearTimeout(this._validateTimer);
            }

            this._validateTimer = setTimeout(() => {
                this.$wire.runLiveValidation();
            }, 800);
        },

        broadcastPreview() {
            window.dispatchEvent(new CustomEvent('menutag-preview', {
                detail: { params: this.previewParams },
            }));
        },

        // --- Live product bands (spec §3.2 / §8.8), config-driven ---------

        /** URL used to size the QR: the typed one, or the demo URL. */
        get qrReferenceUrl() {
            const front = String(this.front ?? '');
            const back = String(this.back ?? '');
            const candidates = [];

            if (front.includes('qr') && this.qrDataFront) candidates.push(this.qrDataFront);
            if (back.includes('qr') && this.qrDataBack) candidates.push(this.qrDataBack);
            if (this.qrDataFront) candidates.push(this.qrDataFront);
            if (this.qrDataBack) candidates.push(this.qrDataBack);

            return candidates[0] || product().qr.demo_url;
        },

        get qrUrlBytes() {
            return byteLength(this.qrReferenceUrl);
        },

        get qrVersion() {
            return minQrVersion(this.qrReferenceUrl, this.qrEc, product().qr);
        },

        /** Minimum sizes for the CURRENT url on both shapes (V5 message). */
        get qrMinSquare() {
            return minSizeForQr(this.qrReferenceUrl, this.qrEc, 'square', product().qr);
        },

        get qrMinCircle() {
            return minSizeForQr(this.qrReferenceUrl, this.qrEc, 'circle', product().qr);
        },

        get qrMinCurrent() {
            return this.shape === 'square' ? this.qrMinSquare : this.qrMinCircle;
        },

        /** QR options are DISABLED below the shape-dependent threshold. */
        get qrAvailable() {
            return this.qrMinCurrent !== null && num(this.size) + EPS >= this.qrMinCurrent;
        },

        get squareAdvantagePct() {
            return Math.round(squareModuleAdvantagePct(this.qrReferenceUrl, this.qrEc, product().qr));
        },

        /** As-printed size: nominal + 2 × per-side XY compensation (V8). */
        get effectiveSize() {
            return num(this.size) + 2 * num(this.xyComp);
        },

        nfcPlanMin(tagDiameter) {
            const nfc = product().nfc;

            return roundUpToTenth(
                tagDiameter + 2 * nfc.radial_clearance_mm + 2 * nfc.radial_wall_min_mm,
            );
        },

        nfcTagAvailable(tagDiameter) {
            return this.effectiveSize + EPS >= this.nfcPlanMin(tagDiameter);
        },

        /**
         * Functional minimum for the CURRENT configuration, shown next to
         * the product minimum with its reason (spec §8.8).
         */
        get minFunctional() {
            const reasons = [{
                size: product().size_min_mm,
                reason: 'minimo di prodotto (moneta da 2 €)',
            }];

            const front = String(this.front ?? '');
            const back = String(this.back ?? '');

            if ((front.includes('qr') || back.includes('qr')) && this.qrMinCurrent !== null) {
                reasons.push({
                    size: this.qrMinCurrent,
                    reason: `codice QR sull'indirizzo inserito (${this.shape === 'square' ? 'lato' : 'diametro'})`,
                });
            }

            if (this.nfc) {
                reasons.push({
                    size: this.nfcPlanMin(num(this.tagDiameter, 25)),
                    reason: `tasca NFC Ø${this.tagDiameter} (parete radiale minima)`,
                });
            }

            return reasons.reduce((max, entry) => (entry.size > max.size ? entry : max), reasons[0]);
        },

        /** Resolved layer height: explicit value or printer profile default. */
        get resolvedLayerHeight() {
            const explicit = num(this.layerHeight, 0);

            if (explicit > 0) {
                return explicit;
            }

            const profile = product().printers?.[this.printer ?? 'a1mini'];

            return profile?.nozzles?.[this.nozzle]?.layer_default ?? 0.1;
        },

        /** Bichromatic layers for inlay: ceil(depth / layer) with 1e-9 tolerance. */
        get inlayBicolorLayers() {
            return bicolorLayers(num(this.depth), this.resolvedLayerHeight);
        },

        /** Nozzle range hint for the layer height field. */
        get nozzleRange() {
            const profile = product().printers?.[this.printer ?? 'a1mini'];

            return profile?.nozzles?.[this.nozzle] ?? null;
        },

        /** Liquid capacity of the rimmed recess (Coaster), computed live. */
        get capacityMl() {
            if (this.baseProfile !== 'rimmed') {
                return null;
            }

            const recess = num(this.recessDepth);
            const rim = num(this.rimWidth);
            const size = num(this.size);
            let areaMm2;

            if (this.shape === 'circle') {
                const innerRadius = Math.max(0, size / 2 - rim);
                areaMm2 = Math.PI * innerRadius * innerRadius;
            } else {
                const innerSide = Math.max(0, size - 2 * rim);
                areaMm2 = innerSide * innerSide;
            }

            return Math.round((areaMm2 * recess) / 100) / 10; // mm³ → ml, 1 decimal
        },

        formatMm(value) {
            return value === null || value === undefined
                ? '—'
                : (Math.round(value * 10) / 10).toLocaleString('it-IT', {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1,
                });
        },
    }));

    Alpine.data('menuTagViewer', (options) => ({
        viewer: null,
        _listeners: [],

        // Scene initialized ONCE here (x-init); the container carries
        // wire:ignore so Livewire morphing never touches the canvas.
        init() {
            this.viewer = createMenuTagViewer(this.$refs.canvasHost, options.params, product());

            if (options.stlUrl) {
                this.viewer.loadStl(options.stlUrl, options.accentStlUrl ?? null);
            }

            const onPreview = (event) => this.viewer?.update(event.detail.params);
            // Server-side mutations only (preset change, size adjustment).
            const onUpdated = (event) => this.viewer?.update(event.detail.params);
            const onCompleted = (event) => {
                const { stlUrl, accentStlUrl } = event.detail;

                if (stlUrl) {
                    this.viewer?.loadStl(stlUrl, accentStlUrl ?? null);
                }
            };

            window.addEventListener('menutag-preview', onPreview);
            window.addEventListener('menutag-updated', onUpdated);
            window.addEventListener('menutag-completed', onCompleted);

            this._listeners = [
                ['menutag-preview', onPreview],
                ['menutag-updated', onUpdated],
                ['menutag-completed', onCompleted],
            ];
        },

        // Mandatory cleanup: browsers cap WebGL contexts (~16).
        destroy() {
            for (const [name, handler] of this._listeners) {
                window.removeEventListener(name, handler);
            }

            this._listeners = [];
            this.viewer?.dispose();
            this.viewer = null;
        },
    }));

    Alpine.data('menuTagToasts', () => ({
        toasts: [],
        _next: 1,

        push(tone, title, message) {
            const id = this._next++;
            this.toasts.push({ id, tone, title, message });
            setTimeout(() => this.remove(id), 8000);
        },

        remove(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },

        // size-adjusted: the size is NEVER adjusted silently (spec §5.2).
        onSizeAdjusted(detail) {
            this.push(
                'info',
                'Dimensione adeguata',
                `${detail.reason} La dimensione passa da ${detail.oldSize} a ${detail.newSize} mm.`,
            );
        },

        onFailed(detail) {
            this.push('error', 'Generazione non riuscita', detail.message);
        },
    }));
}
