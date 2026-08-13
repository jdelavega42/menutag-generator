<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use finfo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;
use InvalidArgumentException;
use XMLReader;

/**
 * Content-based validation for logo uploads (spec §6 WS-5): only real PNG or
 * SVG files, max size from config, MIME decided by the CONTENT — never by
 * the client-declared extension or Content-Type.
 *
 * - PNG: `finfo` on the file content must report exactly `image/png`.
 *   A PHP script renamed `.png` is detected as text and rejected.
 * - SVG: `finfo` is unreliable (it returns sometimes `image/svg+xml`,
 *   sometimes `text/xml` or even `text/plain`), so the authoritative check
 *   is XML parsing with root element `<svg>`. Documents carrying a DOCTYPE
 *   are rejected outright (XXE / entity-expansion hygiene; legitimate logo
 *   SVGs do not need a DTD) and the parser runs with LIBXML_NONET.
 *
 * USAGE POINT for WS-4/WS-6 (server-side generated file names, spec §6):
 * after validation, never persist the client file name on disk — store with
 *
 *     $mime = CleanImageUpload::detectMime($file->getRealPath());
 *     $diskPath = $file->storeAs(
 *         'logos',
 *         CleanImageUpload::generatedFileName($mime),
 *         'assets',
 *     );
 *
 * keeping `$file->getClientOriginalName()` only in the display-only
 * `logo_assets.original_name` column and `$mime` in `logo_assets.mime`
 * (contract 01).
 */
final class CleanImageUpload implements ValidationRule
{
    public const string MIME_PNG = 'image/png';

    public const string MIME_SVG = 'image/svg+xml';

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('Il file caricato non è valido.');

            return;
        }

        $maxKb = (int) config('product.guests.upload_max_kb');

        if ($value->getSize() === false || $value->getSize() > $maxKb * 1024) {
            $fail(sprintf('Il logo supera la dimensione massima di %d KB.', $maxKb));

            return;
        }

        $path = $value->getRealPath();

        if ($path === false || self::detectMime($path) === null) {
            $fail('Il logo deve essere un file PNG o SVG valido: il contenuto del file non corrisponde a nessuno dei due formati.');
        }
    }

    /**
     * Detect the mime type from the file CONTENT. Returns 'image/png',
     * 'image/svg+xml' or null when the content is neither. Also the single
     * source for the `mime` value persisted on `logo_assets` (contract 01).
     */
    public static function detectMime(string $path): ?string
    {
        $detected = new finfo(FILEINFO_MIME_TYPE)->file($path);

        if ($detected === self::MIME_PNG) {
            return self::MIME_PNG;
        }

        // finfo is not trusted for SVG (spec §6 WS-5): whatever text-ish type
        // it reports, the XML parse with <svg> root decides.
        if (self::isSvgContent($path)) {
            return self::MIME_SVG;
        }

        return null;
    }

    /**
     * Server-side generated file name for the 'assets' disk: a random UUID
     * plus the extension derived from the VERIFIED mime — the client name
     * never reaches the filesystem (spec §6 WS-5).
     */
    public static function generatedFileName(string $mime): string
    {
        return match ($mime) {
            self::MIME_PNG => Str::uuid()->toString().'.png',
            self::MIME_SVG => Str::uuid()->toString().'.svg',
            default => throw new InvalidArgumentException(
                sprintf('Unsupported logo mime type [%s]: expected image/png or image/svg+xml.', $mime),
            ),
        };
    }

    /**
     * True when the file parses as XML with root element <svg>
     * (namespace-agnostic local name), without any DOCTYPE declaration.
     */
    private static function isSvgContent(string $path): bool
    {
        $previous = libxml_use_internal_errors(true);
        $reader = null;

        try {
            $reader = XMLReader::open($path, null, LIBXML_NONET);

            if (! $reader instanceof XMLReader) {
                return false;
            }

            while (@$reader->read()) {
                if ($reader->nodeType === XMLReader::DOC_TYPE) {
                    return false;
                }

                if ($reader->nodeType === XMLReader::ELEMENT) {
                    return $reader->localName === 'svg';
                }
            }

            return false;
        } finally {
            if ($reader instanceof XMLReader) {
                $reader->close();
            }

            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
