<?php

declare(strict_types=1);

use App\Rules\CleanImageUpload;
use Illuminate\Support\Facades\File;

/**
 * Content-based SVG validation (WS-5, contract 05): a DOCTYPE is only a
 * threat when it carries an internal subset (custom entity declarations —
 * the XXE / entity-expansion vector). A bare PUBLIC/SYSTEM reference, the
 * legacy boilerplate many export tools still emit, must be accepted.
 */
function writeTempSvg(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'svg_test_').'.svg';
    File::put($path, $contents);

    return $path;
}

it('accepts an SVG with no DOCTYPE at all', function (): void {
    $path = writeTempSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');

    expect(CleanImageUpload::detectMime($path))->toBe(CleanImageUpload::MIME_SVG);

    File::delete($path);
});

it('accepts a legacy DOCTYPE with a bare PUBLIC reference and no internal subset', function (): void {
    $path = writeTempSvg(<<<'SVG'
        <?xml version="1.0" standalone="no"?>
        <!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 20010904//EN"
         "http://www.w3.org/TR/2001/REC-SVG-20010904/DTD/svg10.dtd">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>
        SVG);

    expect(CleanImageUpload::detectMime($path))->toBe(CleanImageUpload::MIME_SVG);

    File::delete($path);
});

it('rejects a DOCTYPE with an internal subset declaring an XXE entity', function (): void {
    $path = writeTempSvg(<<<'SVG'
        <?xml version="1.0"?>
        <!DOCTYPE svg [
          <!ENTITY xxe SYSTEM "file:///etc/passwd">
        ]>
        <svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>
        SVG);

    expect(CleanImageUpload::detectMime($path))->toBeNull();

    File::delete($path);
});

it('rejects a DOCTYPE with an internal subset declaring nested "billion laughs" entities', function (): void {
    $path = writeTempSvg(<<<'SVG'
        <?xml version="1.0"?>
        <!DOCTYPE svg [
          <!ENTITY a "1234567890">
          <!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;&a;&a;">
          <!ENTITY c "&b;&b;&b;&b;&b;&b;&b;&b;&b;&b;">
        ]>
        <svg xmlns="http://www.w3.org/2000/svg"><text>&c;</text></svg>
        SVG);

    expect(CleanImageUpload::detectMime($path))->toBeNull();

    File::delete($path);
});

it('rejects a PHP script renamed with an .svg extension', function (): void {
    $path = writeTempSvg('<?php system($_GET["cmd"]); ?>');

    expect(CleanImageUpload::detectMime($path))->toBeNull();

    File::delete($path);
});
