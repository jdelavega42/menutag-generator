<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MenuTagStatus;
use App\Models\MenuTag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Recovery of hung records (spec §7.4): a worker killed mid-run (container
 * restart, OOM, deploy) leaves the record in "processing" forever and the
 * UI polling an eternal spinner. Any record still processing after
 * config('product.engine.stuck_after_minutes') (15) is marked failed with a
 * readable Italian message. Scheduled every five minutes in
 * routes/console.php.
 *
 * The threshold compares updated_at — touched when the job flips the record
 * to processing (and again on its retry) — and stays far above the job
 * timeout (120 s), so no legitimately running job can be swept. The job
 * itself never resurrects a recovered record: it skips terminal states.
 */
final class RecoverStuckMenuTags extends Command
{
    protected $signature = 'menutag:recover-stuck';

    protected $description = 'Marca come fallite le generazioni ferme in processing oltre la soglia';

    /**
     * User-facing text: the crash detail belongs to the logs, the user only
     * needs to know the run died and what to do next.
     */
    private const string STUCK_MESSAGE = 'La generazione si è interrotta in modo imprevisto: '
        .'invia di nuovo la configurazione. Se il problema si ripete, contatta l’assistenza.';

    public function handle(): int
    {
        $threshold = now()->subMinutes((int) config('product.engine.stuck_after_minutes'));

        $stuck = MenuTag::query()
            ->where('status', MenuTagStatus::Processing)
            ->where('updated_at', '<=', $threshold)
            ->get();

        foreach ($stuck as $menuTag) {
            // Capture before update(): saving refreshes updated_at.
            $stuckSince = $menuTag->updated_at?->toIso8601String();

            $menuTag->update([
                'status' => MenuTagStatus::Failed,
                'error_message' => self::STUCK_MESSAGE,
            ]);

            // Technical trace for the operator: a stuck record is the
            // symptom of a dead worker or a restarted container, never a
            // user error (spec §7.4).
            Log::warning('Stuck MenuTag recovered: processing beyond the threshold, marked as failed.', [
                'menu_tag_id' => $menuTag->id,
                'stuck_since' => $stuckSince,
                'threshold_minutes' => (int) config('product.engine.stuck_after_minutes'),
            ]);
        }

        $this->info(sprintf(
            'Recupero generazioni: %d record fermi in processing da prima di %s marcati come falliti.',
            $stuck->count(),
            $threshold->toDateTimeString(),
        ));

        return self::SUCCESS;
    }
}
