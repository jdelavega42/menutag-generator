/**
 * QR sizing rules shared with PHP (MenuTagParameters::minQrVersion /
 * minSizeForQr) and the Python engine — contract 04.
 *
 * PARITY REQUIREMENT: every constant (byte capacities, minimum pitch, shape
 * floors) comes from the `cfg` object injected by Blade from
 * config/product.php — never duplicated here. Byte mode is forced, no
 * segmentation, so the version predicted from the URL length matches the
 * symbol the engine actually produces.
 *
 * All floating point comparisons use the explicit 1e-9 tolerance (spec §8.3).
 */

export const EPS = 1e-9;

/**
 * Byte length of the payload (UTF-8), matching PHP's strlen() on the raw
 * string and Python's len(data.encode()).
 */
export function byteLength(data) {
    return new TextEncoder().encode(data ?? '').length;
}

/**
 * Smallest byte-mode QR version (1..20) able to hold `data` at the given EC
 * level. `cfg.byte_capacity` is the ISO/IEC 18004 table from config
 * (capacity in characters, mode+count header already subtracted).
 * Returns null when the payload exceeds version 20.
 */
export function minQrVersion(data, ec, cfg) {
    const capacities = cfg.byte_capacity[ec];

    if (!capacities) {
        return null;
    }

    const bytes = byteLength(data);

    for (const [version, capacity] of Object.entries(capacities)) {
        if (bytes <= capacity) {
            return Number(version);
        }
    }

    return null;
}

/** Modules per side for a QR version: n = 17 + 4 × version. */
export function modulesForVersion(version) {
    return 17 + 4 * version;
}

/** Round UP to the next 0.1 mm with the explicit tolerance (mirrors PHP). */
export function roundUpToTenth(value) {
    return Math.ceil(value * 10 - EPS) / 10;
}

/**
 * Minimum size (mm) at which `data` fits as a scannable QR on the given
 * shape (V5): pitch_min × (n + 8) for squares, pitch_min × (n·√2 + 8) for
 * circles (the symbol is inscribed on the diagonal), clamped to the shape's
 * product floor (58.8 square / 79.2 circle from config). Null when the
 * payload exceeds version 20.
 */
export function minSizeForQr(data, ec, shape, cfg) {
    const version = minQrVersion(data, ec, cfg);

    if (version === null) {
        return null;
    }

    const modules = modulesForVersion(version);
    const pitch = cfg.min_pitch_mm;

    const raw = shape === 'square'
        ? pitch * (modules + 8)
        : pitch * (modules * Math.SQRT2 + 8);

    const floor = shape === 'square' ? cfg.floor_square_mm : cfg.floor_circle_mm;

    return Math.max(floor, roundUpToTenth(raw));
}

/**
 * How much larger the module is on a square than on a circle of the same
 * overall size (~35 % at version 6): (n·√2 + 8) / (n + 8) − 1.
 */
export function squareModuleAdvantagePct(data, ec, cfg) {
    const version = minQrVersion(data, ec, cfg) ?? cfg.floor_version;
    const modules = modulesForVersion(version);

    return ((modules * Math.SQRT2 + 8) / (modules + 8) - 1) * 100;
}

/**
 * Bichromatic layer count for an inlay: every layer inside the inlay depth
 * prints in two colors. ceil(depth / layerHeight) with the explicit 1e-9
 * tolerance so 0.5 / 0.1 counts 5 layers, not 6 (contract 04).
 */
export function bicolorLayers(depth, layerHeight) {
    if (!(layerHeight > 0)) {
        return 0;
    }

    return Math.max(0, Math.ceil(depth / layerHeight - EPS));
}
