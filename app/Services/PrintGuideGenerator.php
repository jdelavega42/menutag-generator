<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BaseProfile;
use App\Enums\Material;
use App\Enums\Preset;
use App\Enums\Printability;
use App\Enums\RenderMode;
use App\Models\MenuTag;
use LogicException;

/**
 * Print guide (spec §8.7): an Italian Markdown document generated ON DEMAND
 * from the REAL values of the record — the parameter snapshot and the engine
 * report saved on it (decisions §4: no second artifact on disk, always
 * coherent with the metadata).
 *
 * The audience is the END CUSTOMER, who often hands the file to a print
 * service: every instruction must be understandable by someone who never saw
 * the configurator. An STL carries geometry only — this guide carries the
 * slicer parameters that make it print correctly.
 *
 * Slicer values (walls, shells, infill, temperatures, fan) come from
 * config/product.php `print_profiles` per material: DECLARED ASSUMPTIONS to
 * be calibrated (contract 05) — the engine produces geometry, never slicer
 * settings. Geometry-derived facts (nozzle, layer heights, pause, bicolor
 * layers, capacity, warnings) are quoted verbatim from the engine report.
 */
final class PrintGuideGenerator
{
    /**
     * @throws LogicException when the record has no engine report yet — the
     *                        caller must answer 409 before `completed` (openapi contract).
     */
    public function generate(MenuTag $menuTag): string
    {
        $report = $menuTag->report;

        if ($report === null || $report === []) {
            throw new LogicException(sprintf(
                'MenuTag %d has no engine report: the print guide can only be generated once the record is completed.',
                $menuTag->id,
            ));
        }

        $p = $menuTag->parameters;

        /** @var array{nozzle_temp_c: int, bed_temp_c: int, fan_pct: int} $materialProfile */
        $materialProfile = (array) config('product.print_profiles.'.$p->material->value);

        /** @var array<string, mixed> $common */
        $common = (array) config('product.print_profiles.common');

        /** @var array<string, mixed> $printer */
        $printer = (array) config('printers.profiles.'.$p->printer);

        // Geometry facts: the engine report is authoritative (it echoes the
        // grid it actually used); the DTO is the fallback for older records.
        $nozzle = $this->str($report, 'NOZZLE', $p->nozzle->value);
        $layerHeight = $this->str($report, 'LAYER_HEIGHT', $this->mm($p->resolvedLayerHeight()));

        // Nozzle keys contain a dot ('0.2'/'0.4'): index the nozzles array,
        // never a dotted config path (data_get would split the key).
        /** @var array<string, array{first_layer?: float}> $nozzles */
        $nozzles = (array) ($printer['nozzles'] ?? []);
        $firstLayer = $this->str(
            $report,
            'FIRST_LAYER',
            $this->mm((float) ($nozzles[$p->nozzle->value]['first_layer'] ?? 0.20)),
        );

        $materialName = $p->material === Material::Petg ? 'PETG' : 'PLA matte';

        $lines = [];

        $lines[] = sprintf('# Guida di stampa — %s%s (targhetta #%d)', $this->presetLabel($menuTag->preset), $menuTag->label !== null ? ' «'.$menuTag->label.'»' : '', $menuTag->id);
        $lines[] = '';
        $lines[] = 'Questa guida accompagna il file STL generato da MenuTag Generator ed è pensata per chi';
        $lines[] = 'stampa il pezzo: un service di stampa 3D o chi dispone di una stampante propria.';
        $lines[] = 'Il file STL contiene **solo la geometria**: i parametri qui sotto vanno impostati nello';
        $lines[] = 'slicer (es. Bambu Studio, OrcaSlicer) prima di avviare la stampa.';
        $lines[] = '';

        $lines = [...$lines, ...$this->filesSection($menuTag)];
        $lines = [...$lines, ...$this->printerSection($printer, $p->plate, $report)];
        $lines = [...$lines, ...$this->slicerSection($nozzle, $layerHeight, $firstLayer, $materialName, $materialProfile, $common, $printer)];
        $lines = [...$lines, ...$this->materialSection($menuTag, $materialName, $report)];

        if ($p->baseProfile === BaseProfile::Rimmed) {
            $lines = [...$lines, ...$this->rimmedSection($common, $report)];
        }

        if ($p->mode === RenderMode::Inlay) {
            $lines = [...$lines, ...$this->inlaySection($report)];
        }

        if ($p->nfc) {
            $lines = [...$lines, ...$this->nfcSection($menuTag, $report, $layerHeight)];
        }

        $lines = [...$lines, ...$this->xyCompSection($menuTag, $report)];
        $lines = [...$lines, ...$this->warningsSection($menuTag, $report)];

        $lines[] = '---';
        $lines[] = '';
        $lines[] = sprintf(
            '*Guida generata automaticamente da MenuTag Generator il %s dai parametri reali della configurazione #%d.*',
            now()->format('d/m/Y H:i'),
            $menuTag->id,
        );
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function filesSection(MenuTag $menuTag): array
    {
        $lines = ['## File da stampare', ''];

        if ($menuTag->parameters->mode === RenderMode::Inlay) {
            $lines[] = 'La modalità **intarsio bicolore (inlay)** produce **due file STL complanari** da caricare';
            $lines[] = 'nello slicer **insieme, senza spostarli** (in Bambu Studio: importali come oggetti di una';
            $lines[] = 'stessa piastra e non toccare la posizione — combaciano già in modo esatto):';
            $lines[] = '';
            $lines[] = sprintf('| File | Contenuto | Filamento |');
            $lines[] = '|---|---|---|';
            $lines[] = sprintf('| `%s-%d.stl` | corpo base | **colore principale** (slot AMS 1) |', $menuTag->preset->value, $menuTag->id);
            $lines[] = sprintf('| `%s-%d-accento.stl` | grafica a filo | **colore di contrasto** (slot AMS 2) |', $menuTag->preset->value, $menuTag->id);
        } else {
            $lines[] = sprintf('Un solo file: `%s-%d.stl` (corpo e grafica nello stesso pezzo).', $menuTag->preset->value, $menuTag->id);
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $printer
     * @param  array<string, mixed>  $report
     * @return list<string>
     */
    private function printerSection(array $printer, int $plate, array $report): array
    {
        /** @var array{x: int, y: int, z: int} $bed */
        $bed = (array) ($printer['bed_mm'] ?? ['x' => 180, 'y' => 180, 'z' => 180]);

        $lines = ['## Stampante e piatto', ''];
        $lines[] = sprintf('- **Stampante di riferimento:** %s (piano %d × %d mm).', (string) ($printer['name'] ?? 'Bambu Lab A1 mini'), (int) $bed['x'], (int) $bed['y']);
        $lines[] = sprintf('- **Piatto:** %s — nessun adesivo aggiuntivo necessario.', (string) ($printer['plate_surface'] ?? 'PEI testurizzato'));

        if (isset($report['BBOX_X'], $report['BBOX_Y'])) {
            $lines[] = sprintf(
                '- **Ingombro del file:** %s × %s mm%s.',
                $this->str($report, 'BBOX_X'),
                $this->str($report, 'BBOX_Y'),
                $plate > 1 ? sprintf(' (piastra già disposta da %d pezzi, non separarli nello slicer)', $plate) : '',
            );
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array{nozzle_temp_c: int, bed_temp_c: int, fan_pct: int}  $materialProfile
     * @param  array<string, mixed>  $common
     * @param  array<string, mixed>  $printer
     * @return list<string>
     */
    private function slicerSection(
        string $nozzle,
        string $layerHeight,
        string $firstLayer,
        string $materialName,
        array $materialProfile,
        array $common,
        array $printer,
    ): array {
        $lines = ['## Parametri slicer', ''];
        $lines[] = '| Parametro | Valore |';
        $lines[] = '|---|---|';
        $lines[] = sprintf('| Ugello | **%s mm** (il pezzo è validato per questo diametro: non cambiarlo) |', $nozzle);
        $lines[] = sprintf('| Altezza layer | **%s mm** |', $layerHeight);
        $lines[] = sprintf('| Primo layer | **%s mm** |', $firstLayer);
        $lines[] = sprintf('| Pareti (wall loops) | %d — **mai attivare "only one wall"** |', (int) ($common['wall_loops'] ?? 2));
        $lines[] = sprintf('| Gusci alto/basso (top/bottom shells) | %d |', (int) ($common['top_bottom_shells'] ?? 4));
        $lines[] = sprintf('| Riempimento (infill) | %d %% |', (int) ($common['infill_pct'] ?? 15));
        $lines[] = sprintf('| Temperatura ugello | %d °C (%s) |', (int) $materialProfile['nozzle_temp_c'], $materialName);
        $lines[] = sprintf('| Temperatura piatto | %d °C |', (int) $materialProfile['bed_temp_c']);
        $lines[] = sprintf('| Ventola | %d %% |', (int) $materialProfile['fan_pct']);
        $lines[] = sprintf('| Brim | %s |', ($common['brim'] ?? true) ? 'sì' : 'no');
        $lines[] = '| Supporti | **NO** — la geometria non ne ha bisogno |';
        $lines[] = '';
        $lines[] = sprintf(
            '> Le quote critiche del pezzo (fondo delle incisioni, tasca NFC, cima) sono già allineate al reticolo dei layer per **primo layer %s mm + layer %s mm**: cambiare questi due valori invalida l\'allineamento.',
            $firstLayer,
            $layerHeight,
        );
        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<string>
     */
    private function materialSection(MenuTag $menuTag, string $materialName, array $report): array
    {
        $lines = ['## Materiale', ''];
        $lines[] = sprintf('- **Materiale richiesto: %s.**', $materialName);

        if ($menuTag->parameters->material === Material::Petg) {
            $lines[] = '- Il PETG è richiesto perché il pezzo va lavato (anche in lavastoviglie): il PLA si deforma già a ~60 °C.';
        } else {
            $lines[] = '- La finitura opaca del PLA matte nasconde le linee di layer; il pezzo non è adatto alla lavastoviglie.';
        }

        if (isset($report['WEIGHT_G'])) {
            $lines[] = sprintf(
                '- Peso stimato: **%s g** — calcolato sul solido pieno, quindi è un limite superiore: con il riempimento al %d %% il consumo reale di filamento sarà inferiore.',
                $this->str($report, 'WEIGHT_G'),
                (int) config('product.print_profiles.common.infill_pct', 15),
            );
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $common
     * @param  array<string, mixed>  $report
     * @return list<string>
     */
    private function rimmedSection(array $common, array $report): array
    {
        $lines = ['## Bordo antigoccia (profilo "rimmed")', ''];
        $lines[] = 'Il pezzo ha un bordo perimetrale in rilievo con il campo interno incavato: trattiene la';
        $lines[] = 'condensa di un bicchiere freddo. Una stampa FDM **non è impermeabile per costruzione**,';
        $lines[] = 'quindi per la tenuta del fondo dell\'incavo servono due impostazioni dedicate:';
        $lines[] = '';
        $lines[] = sprintf(
            '- **Layer solidi sul fondo dell\'incavo: almeno %d** (nello slicer: aumenta i "top shell layers" oppure usa un modificatore sull\'area incavata).',
            (int) ($common['rimmed_recess_solid_layers'] ?? 6),
        );

        if (($common['rimmed_ironing'] ?? true) === true) {
            $lines[] = '- **Ironing (stiratura) attivo sulle superfici superiori**: chiude i micro-pori del fondo e migliora la tenuta.';
        }

        if (isset($report['CAPACITY_ML'])) {
            $lines[] = sprintf(
                '- Capacità di ritenzione calcolata: **%s ml** — trattiene la condensa, non un bicchiere rovesciato.',
                $this->str($report, 'CAPACITY_ML'),
            );
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<string>
     */
    private function inlaySection(array $report): array
    {
        $lines = ['## Intarsio bicolore (inlay)', ''];
        $lines[] = 'La grafica è **a filo della superficie**, in un secondo colore: servono una stampante';
        $lines[] = 'multicolore (AMS) oppure un cambio filamento manuale ai layer indicati dallo slicer.';
        $lines[] = '';
        $lines[] = '- **Assegnazione filamenti:** corpo base → colore principale; STL "accento" → colore di contrasto. Per un QR usa un contrasto netto (es. base chiara, accento scuro).';

        if (isset($report['BICOLOR_LAYERS'])) {
            $lines[] = sprintf(
                '- **Layer bicromatici: %s** — solo questi layer stampano entrambi i colori; il resto del pezzo è monocolore.',
                $this->str($report, 'BICOLOR_LAYERS'),
            );
        }

        if ((bool) config('product.print_profiles.common.inlay_purge_generous', true)) {
            $lines[] = '- **Volumi di spurgo generosi:** il primo layer di ogni colore trascina residui del precedente; aumenta il volume di spurgo (purge/flush) rispetto al default dello slicer, altrimenti il contrasto del primo layer bicolore risulta sporco.';
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<string>
     */
    private function nfcSection(MenuTag $menuTag, array $report, string $layerHeight): array
    {
        $p = $menuTag->parameters;

        $lines = ['## Tag NFC — pausa a metà stampa', ''];
        $lines[] = 'Il pezzo contiene una tasca chiusa per un tag NFC: la stampa va **messa in pausa** alla';
        $lines[] = 'quota indicata, il tag va appoggiato nella tasca e la stampa riprende chiudendolo dentro.';
        $lines[] = '';

        if (isset($report['PAUSE_Z'], $report['PAUSE_LAYER'])) {
            $lines[] = sprintf(
                '1. **Imposta la pausa a Z = %s mm, cioè dopo il layer %s** (in Bambu Studio / OrcaSlicer: tasto destro sulla barra dei layer → "Add pause"). Verifica che il layer indicato dallo slicer corrisponda: se hai cambiato altezza layer (%s mm) o primo layer, la quota non è più valida.',
                $this->str($report, 'PAUSE_Z'),
                $this->str($report, 'PAUSE_LAYER'),
                $layerHeight,
            );
        } else {
            $lines[] = '1. **Imposta la pausa alla quota indicata dal report di generazione** (chiavi PAUSE_Z / PAUSE_LAYER).';
        }

        $lines[] = sprintf(
            '2. Alla pausa, appoggia nella tasca un **tag NFC Ø%d mm**, con spessore dichiarato di **%s mm**. **Prima di stampare, misura col calibro lo spessore reale del tag** (adesivo incluso): se supera il valore dichiarato, l\'ugello ci sbatte contro alla ripresa e la stampa è persa. In quel caso rigenera il pezzo indicando lo spessore misurato.',
            $p->tagDiameter->value,
            $this->mm($p->tagThickness),
        );
        $lines[] = sprintf(
            '3. La tasca è **%s mm più profonda del tag** (gioco assiale di progetto): il tag NON deve fare da appoggio al layer di chiusura. Non riempire il gioco con nastro o colla.',
            $this->mm((float) config('product.nfc.axial_clearance_mm', 0.20)),
        );
        $lines[] = '4. Riprendi la stampa. Il primo layer sopra la tasca attraversa una piccola luce d\'aria: un aspetto irregolare di quel layer **interno** è previsto e invisibile a pezzo finito.';
        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<string>
     */
    private function xyCompSection(MenuTag $menuTag, array $report): array
    {
        $p = $menuTag->parameters;
        $xyComp = isset($report['XY_COMP_MM']) ? (float) $report['XY_COMP_MM'] : $p->xyComp;

        $lines = ['## Compensazione XY — misura il primo pezzo', ''];

        if (abs($xyComp) > 1e-9) {
            $lines[] = sprintf(
                'La geometria include già una **compensazione XY di %s mm per lato** (%s mm sulla dimensione totale): il nominale del file è ridotto perché una stampa FDM esce leggermente abbondante per sovrapposizione degli estrusi.',
                $this->mm($xyComp),
                $this->mm(2 * $xyComp),
            );

            if ($menuTag->preset === Preset::CoinCart) {
                $lines[] = '';
                $lines[] = '**Obbligatorio per il gettone carrello:** un gettone appena fuori misura non entra nella fessura ed è invendibile.';
            }
        } else {
            $lines[] = 'Nessuna compensazione XY è applicata al file: il nominale è quello di progetto.';
        }

        $lines[] = '';
        $lines[] = sprintf(
            '**Misura col calibro il primo pezzo stampato** e confronta la dimensione con il nominale di progetto (%s mm). Se la differenza supera qualche centesimo di millimetro, rigenera il file correggendo la compensazione XY (ogni 0.05 mm per lato spostano la misura di 0.1 mm) — la deriva dipende dalla calibrazione della singola stampante.',
            $this->mm($p->size),
        );
        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<string>
     */
    private function warningsSection(MenuTag $menuTag, array $report): array
    {
        $warnings = [];

        if (isset($report['WARNING']) && is_string($report['WARNING']) && trim($report['WARNING']) !== '') {
            $warnings = array_values(array_filter(array_map('trim', explode("\n", $report['WARNING']))));
        }

        $printability = $menuTag->printability;

        if ($warnings === [] && ($printability === null || $printability === Printability::Ok)) {
            return [];
        }

        $lines = ['## Avvisi del controllo di stampabilità', ''];

        if ($printability === Printability::Warn) {
            $lines[] = '> **Esito: attenzione (warn).** Il pezzo è stampabile, ma le verifiche geometriche hanno rilevato dettagli al limite: leggi gli avvisi qui sotto prima di avviare la stampa.';
            $lines[] = '';
        }

        if ($printability === Printability::Blocked) {
            $lines[] = '> **Esito: sconsigliato (blocked).** Le verifiche geometriche indicano che parte della grafica rischia di non essere stampata correttamente con l\'ugello scelto. Il download è stato consentito con avviso esplicito: valuta gli avvisi e considera una dimensione maggiore, un ugello più fine o una grafica più semplice.';
            $lines[] = '';
        }

        foreach ($warnings as $warning) {
            $lines[] = '- '.$warning;
        }

        if ($warnings !== []) {
            $lines[] = '';
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function str(array $report, string $key, string $fallback = ''): string
    {
        $value = $report[$key] ?? null;

        return is_scalar($value) ? (string) $value : $fallback;
    }

    /**
     * Deterministic mm formatting: decimal point, trailing zeros trimmed,
     * always at least one decimal (3.0 → "3.0").
     */
    private function mm(float $value): string
    {
        $formatted = rtrim(number_format($value, 6, '.', ''), '0');

        if (str_ends_with($formatted, '.')) {
            $formatted .= '0';
        }

        return $formatted;
    }

    private function presetLabel(Preset $preset): string
    {
        return match ($preset) {
            Preset::MenuTag => 'MenuTag',
            Preset::Coaster => 'Coaster',
            Preset::CoinCart => 'Coin Cart',
        };
    }
}
