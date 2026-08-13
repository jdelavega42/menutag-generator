<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateMenuTagJob;
use App\Models\LogoAsset;
use App\Models\MenuTag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Guest retention (spec §5.1, contract 05): guest records, their STL files
 * and orphan guest logos older than config('product.guests.retention_hours')
 * (24 h) are removed. Scheduled hourly in routes/console.php — the signed
 * download URLs expire on the same clock, so nothing reachable is deleted
 * early and nothing expired survives longer than one schedule tick.
 *
 * "Guest" means user_id NULL + guest_token set: a record migrated to an
 * account at registration (spec §7.2) gains a user_id and leaves the
 * retention scope entirely.
 */
final class PruneGuestMenuTags extends Command
{
    protected $signature = 'menutag:prune-guests';

    protected $description = 'Elimina record, STL e loghi degli ospiti oltre la retention di 24 ore';

    public function handle(): int
    {
        $cutoff = now()->subHours((int) config('product.guests.retention_hours'));

        $stlDisk = Storage::disk('stl');
        $assetsDisk = Storage::disk('assets');

        // 1) Expired guest records: STL artifacts first, then the row.
        $expiredTags = MenuTag::query()
            ->whereNull('user_id')
            ->whereNotNull('guest_token')
            ->where('created_at', '<=', $cutoff)
            ->get();

        foreach ($expiredTags as $menuTag) {
            // The recorded paths, plus the whole per-record directory (the
            // job's path convention): a failed record has no stl_path but
            // its directory may exist.
            foreach ([$menuTag->stl_path, $menuTag->stl_accent_path] as $path) {
                if ($path !== null) {
                    $stlDisk->delete($path);
                }
            }

            $stlDisk->deleteDirectory(GenerateMenuTagJob::stlDirectory($menuTag->id));

            $menuTag->delete();
        }

        // 2) Orphan guest logos: normally the job removes a guest logo when
        // it concludes (spec §7.1); this catches uploads never used in a
        // generation, leftovers of crashed jobs and logos whose records were
        // pruned in step 1. A logo still referenced by a SURVIVING record
        // (necessarily younger than the cutoff) is kept until that record
        // expires too.
        $orphanLogos = LogoAsset::query()
            ->whereNull('user_id')
            ->whereNotNull('guest_token')
            ->where('created_at', '<=', $cutoff)
            ->whereDoesntHave('menuTags')
            ->get();

        foreach ($orphanLogos as $logo) {
            $assetsDisk->delete($logo->disk_path);
            $logo->delete();
        }

        $this->info(sprintf(
            'Retention ospiti: eliminati %d record (con relativi STL) e %d loghi orfani più vecchi di %s.',
            $expiredTags->count(),
            $orphanLogos->count(),
            $cutoff->toDateTimeString(),
        ));

        return self::SUCCESS;
    }
}
