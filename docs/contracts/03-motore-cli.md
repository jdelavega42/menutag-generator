# Contratto — Motore geometrico: interfaccia PHP e CLI (WS-2)

Nessun motore allegato → questo contratto è la fonte di verità (§2.1 del
prompt, ramo «se non è allegato»). Laravel orchestra, Python calcola: nessuna
geometria in PHP, nessun `Process` fuori da `PythonMenuTagEngine`.

## 1. Interfaccia di servizio

```php
// app/Contracts/MenuTagEngineContract.php
interface MenuTagEngineContract
{
    /**
     * @throws EngineValidationException exit code 2 — errore utente:
     *         messaggio human-readable da stderr, da mostrare così com'è
     * @throws EngineFailureException    qualunque altro esito anomalo —
     *         errore interno: si logga stderr, all'utente un messaggio generico
     */
    public function generate(EngineRequest $request): EngineResult;
}

// app/DTOs/EngineRequest.php (readonly)
final readonly class EngineRequest
{
    public function __construct(
        public MenuTagParameters $parameters,
        public string $outPath,          // assoluto, dentro il disco 'stl'
        public ?string $outAccentPath,   // obbligatorio ⇔ mode=inlay
        public ?string $logoPath,        // assoluto, dentro il disco 'assets'
    ) {}
}
```

Implementazioni: `PythonMenuTagEngine` (produzione) e `FakeMenuTagEngine`
(test — configurabile per simulare i tre esiti; **nessun test invoca Python**,
al più un test d'integrazione marcato `->group('integration')`).

`PythonMenuTagEngine`:

- facade **`Process`**, argomenti come **array** (`toCliArguments()`), mai
  stringa concatenata;
- binario: `config('product.engine.python')` (default
  `engine/.venv/bin/python3`), script `engine/menutag.py`, cwd = base path;
- timeout Process **60 s** (`config('product.engine.timeout_s')`) — il Job che
  lo chiama ha timeout **120 s** (deve sopravvivere al Process per registrare
  l'errore, §7.4);
- verifica preliminare: se `logoPath` è valorizzato ma il file non esiste,
  eccezione interna con messaggio esplicito (è il sintomo del volume Docker
  non montato, §7.1 — il messaggio deve dirlo);
- exit 0 → parse stdout in `EngineResult`; exit 2 → `EngineValidationException`
  con stderr; altro → `EngineFailureException`.

## 2. `EngineResult`

Readonly, costruito dal parser `CHIAVE=VALORE` (una per riga, ordine libero,
chiavi sconosciute conservate in `raw`). Campi tipizzati:

`ok(bool)`, `triangles(int)`, `volumeMm3(float)`, `weightG(float)`,
`pauseZ(?float)`, `pauseLayer(?int)`, `nozzle(string)`, `layerHeight(float)`,
`firstLayer(float)`, `bboxX/Y/Z(float)`, `fileSizeKb(int)`,
`qrVersion(?int)`, `qrEc(?string)`, `qrModules(?int)`, `qrPitchMm(?float)`,
`qrDecoded(?bool)`, `featureMinMm(?float)`, `featureLossPct(?float)`,
`voidMinMm(?float)`, `perimeterResiduePct(?float)`,
`perimeterResidueWidthMm(?float)` (nullable: presenti solo con grafica su
almeno una faccia, vedi §4), `volumeDeltaMm3(float)`,
`sizeMinFunctionalMm(float)`, `renderMode(string)`,
`accentTriangles(?int)`, `accentVolumeMm3(?float)`, `bicolorLayers(?int)`,
`rimWidth(?float)`, `recessDepth(?float)`, `capacityMl(?float)`,
`material(string)`, `plate(int)`, `xyCompMm(float)`,
`printability(Printability)`, `warnings(list<string>)`,
`raw(array<string,string>)` → salvato integro in `menu_tags.report`.

## 3. Contratto CLI

```
python3 engine/menutag.py
  --shape circle|square          obbligatorio
  --size FLOAT                   diametro (circle) o lato (square), mm — obbligatorio
  --fillet FLOAT                 raggio smusso angoli, solo square (default 0)
  --thickness FLOAT              default 4.0
  --base-profile flat|rimmed     rimmed = bordo antigoccia (default flat)
  --rim-width FLOAT              larghezza bordo, solo rimmed (default 5.0)
  --recess-depth FLOAT           profondità incavo, solo rimmed (default 1.2)
  --front none|logo|qr|qr_logo   default none
  --back  none|logo|qr|qr_logo   default none (grafica specularizzata dal motore)
  --mode engrave|relief|inlay    default engrave
  --depth FLOAT                  profondità/altezza grafica (default 0.8)
  --margin FLOAT|auto            default auto (quiet zone, formule §3.4 prompt)
  --logo PATH                    PNG o SVG (SVG: pipeline vettoriale; PNG: tracciamento)
  --logo-rotate FLOAT            gradi (default 0)
  --qr-data STRING               scorciatoia entrambe le facce (l'app NON la usa)
  --qr-data-front STRING         prevale su --qr-data
  --qr-data-back STRING          prevale su --qr-data
  --qr-ec L|M|Q|H                default H — byte mode forzato, no segmentazione
  --nfc                          flag
  --tag 22|25                    default 25
  --tag-thickness FLOAT          default 0.80
  --nozzle 0.2|0.4               default 0.4
  --layer-height FLOAT           default dal profilo stampante
  --printer a1mini               default
  --material pla-matte|petg      default pla-matte
  --plate INT                    default 1 — piastra N pezzi, passo bbox+5mm   [ESTENSIONE]
  --xy-comp FLOAT                default 0.0 — mm per lato, silhouette esterna [ESTENSIONE]
  --out PATH                     STL binario del corpo base — obbligatorio
  --out-accent PATH              STL accento — obbligatorio con --mode inlay,
                                 ignorato negli altri mode (come da specifica §2.2)
```

Requisiti pipeline (vincolanti, §2.3–§2.4 del prompt): geometria 2D esatta
(niente CSG per i moduli QR); moduli QR analitici mai rasterizzati, dilatati di
0.005 mm; SVG vettore→vettore, raster+tracciamento solo per PNG; libreria QR
con versione/EC/mask forzabili e rilettura del simbolo; STL binario
watertight; decimazione (corda < 0.05 mm) solo su archi piastra e contorni
logo, mai su moduli QR.

Quote sul reticolo dei layer: `(z − primo_layer) / layer_height` intero per
fondo incisione di ogni faccia, fondo e cima tasca NFC, fondo incavo, cima del
pezzo; **tolleranza esplicita 1e-9 su ogni divisione e confronto** (`floor`,
allineamento passo/estrusione, soglie). `PAUSE_Z` = quota della cima della
tasca; `PAUSE_LAYER = 1 + (PAUSE_Z − primo_layer)/layer_height`. Riferimento
validato (58.8 × 3.0, L=0.10, FL=0.20, tasca 1.0–2.0): `PAUSE_Z=2.0`,
`PAUSE_LAYER=19` di 29.

## 4. stdout — chiavi `CHIAVE=VALORE`

Sempre presenti: `OK=1`, `TRIANGLES`, `VOLUME_MM3`, `WEIGHT_G` (⚠ rinominata,
decisioni §2), `NOZZLE`, `LAYER_HEIGHT`, `FIRST_LAYER`, `BBOX_X`, `BBOX_Y`,
`BBOX_Z`, `FILE_SIZE_KB`, `VOLUME_DELTA_MM3`, `SIZE_MIN_FUNCTIONAL_MM`
(= minimo di prodotto quando non c'è contenuto), `RENDER_MODE`, `MATERIAL`,
`PLATE`, `XY_COMP_MM`, `PRINTABILITY=ok|warn|blocked`.

Condizionali: `FEATURE_MIN_MM`, `FEATURE_LOSS_PCT`, `VOID_MIN_MM`,
`PERIMETER_RESIDUE_PCT`, `PERIMETER_RESIDUE_WIDTH_MM` (grafica su almeno una
faccia — con `--front none --back none` le metriche artwork non esistono);
`PAUSE_Z`, `PAUSE_LAYER` (con `--nfc`); `QR_VERSION`, `QR_EC`,
`QR_MODULES`, `QR_PITCH_MM`, `QR_DECODED=yes|no` (facce QR);
`ACCENT_TRIANGLES`, `ACCENT_VOLUME_MM3`, `BICOLOR_LAYERS` (inlay);
`RIM_WIDTH`, `RECESS_DEPTH`, `CAPACITY_ML` (rimmed);
`WARNING=<testo>` ripetibile.

## 5. Exit code e partizione degli esiti

| Exit | Significato | STL | Chi lo vede |
|---|---|---|---|
| `0` | successo (anche con `PRINTABILITY=warn|blocked`) | sì | report in UI; download consentito, su `blocked` con avviso esplicito |
| `2` | errore utente parametrico/dimensionale, messaggio su stderr | no | mostrato all'utente così com'è, con come rientrare nei limiti |
| altro | errore interno (integrità mesh §8.2 inclusa) | no | loggato, mai esposto |

Dettaglio della partizione in `00-decisioni-fase0.md` §3. Controlli §8.2
obbligatori prima dell'export: watertight, manifold, winding, volume positivo,
nessuna faccia degenere/auto-intersezione, |volume mesh − volume analitico| <
10⁻³ mm³; in `inlay` entrambi i pezzi watertight e somma volumi = solido pieno
entro 10⁻³ mm³; in `rimmed` bordo continuo e `CAPACITY_ML` riportata; unità mm,
pezzo a Z=0 centrato in XY; bbox piastra: blocco > 180 mm, `WARNING` > 175 mm.
