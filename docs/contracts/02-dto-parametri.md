# Contratto — DTO `MenuTagParameters` (WS-1)

Classe `readonly` in `app/DTOs/MenuTagParameters.php`. È lo snapshot completo
della configurazione: viene serializzato in `menu_tags.parameters`, validato
alla costruzione (invarianti sotto) e tradotto in argomenti CLI dal solo
`toCliArguments()`. Il motore Python riverifica gli stessi vincoli (difesa in
profondità): la coppia di implementazioni deve usare le **stesse formule e le
stesse costanti di config**.

## Campi

| Campo | Tipo PHP | Default | Flag CLI | Note |
|---|---|---|---|---|
| `shape` | `Shape` | — | `--shape` | |
| `size` | `float` | — | `--size` | mm; diametro (circle) o lato (square) |
| `fillet` | `float` | `0.0` | `--fillet` | solo square |
| `thickness` | `float` | `4.0` | `--thickness` | |
| `baseProfile` | `BaseProfile` | `flat` | `--base-profile` | |
| `rimWidth` | `float` | `5.0` | `--rim-width` | solo rimmed |
| `recessDepth` | `float` | `1.2` | `--recess-depth` | solo rimmed |
| `front` | `FaceContent` | `none` | `--front` | |
| `back` | `FaceContent` | `none` | `--back` | il motore specularizza il retro |
| `mode` | `RenderMode` | `engrave` | `--mode` | |
| `depth` | `float` | `0.8` | `--depth` | profondità incisione / altezza rilievo / profondità intarsio. Con `mode=inlay` la UI/Form Request propone `config('product.inlay.default_depth_mm')` (0.5) se l'utente non ha toccato il campo (§3.6: contenere i layer bicromatici); il default CLI resta 0.8 |
| `margin` | `?float` | `null` | `--margin` | null → si emette letterale `auto` |
| `logoAssetId` | `?int` | `null` | — | il path assoluto lo risolve il Job (vedi firma sotto) |
| `logoRotate` | `float` | `0.0` | `--logo-rotate` | gradi |
| `qrDataFront` | `?string` | `null` | `--qr-data-front` | il DTO è sempre esplicito per faccia: la scorciatoia `--qr-data` non viene mai emessa |
| `qrDataBack` | `?string` | `null` | `--qr-data-back` | |
| `qrEc` | `QrEcLevel` | `H` | `--qr-ec` | |
| `nfc` | `bool` | `false` | `--nfc` (flag) | |
| `tagDiameter` | `TagDiameter` | `25` | `--tag` | |
| `tagThickness` | `float` | `0.80` | `--tag-thickness` | |
| `nozzle` | `Nozzle` | `'0.4'` | `--nozzle` | |
| `layerHeight` | `?float` | `null` | `--layer-height` | null → default dal profilo stampante (flag omesso) |
| `printer` | `string` | `'a1mini'` | `--printer` | |
| `material` | `Material` | `pla-matte` | `--material` | |
| `plate` | `int` | `1` | `--plate` | estensione, decisioni §2 |
| `xyComp` | `float` | `0.0` | `--xy-comp` | mm per lato; estensione, decisioni §2 |

Derivata usata dalle validazioni: **dimensione effettiva**
`effectiveSize() = size + 2 × xyComp`. Vale **solo** per le verifiche
geometriche della tasca NFC (V8): misura il pezzo che esce davvero dalla
stampante. I limiti di prodotto (V1) e i pavimenti QR (V5) usano la dimensione
**nominale**: sono quote di progetto, la compensazione corregge la deriva di
stampa (decisioni §2).

## Invarianti (eccezione `InvalidMenuTagParameters` con messaggio per campo)

Le costanti citate vivono in `config/product.php` / `config/printers.php`
(contratto `05`), mai inline. `L` = layer height risolto (esplicito o default
del profilo), `FL` = primo layer del profilo per l'ugello scelto.

- **V1 — Minimi e massimi di prodotto**: `25.75 ≤ size ≤ 200` (su `size`
  **nominale**, non su `effectiveSize()`); `2.20 ≤ thickness ≤ 20`. (Il blocco a 180 mm/avviso a 175 mm è del profilo
  stampante e si applica al bounding box dell'intera piastra: pre-verifica PHP
  + verifica vincolante del motore.)
- **V2 — Fillet**: `fillet > 0` solo con `shape=square`; `0 ≤ fillet ≤ size/2`.
- **V3 — Profilo rimmed**: `rimWidth`/`recessDepth` solo con `rimmed`;
  `rimWidth ≥ 3 × nozzle`; `recessDepth` entro il budget di V6.
- **V4 — Facce QR**: se `front ∈ {qr, qr_logo}` allora `qrDataFront`
  obbligatorio (idem back). Se nessuna faccia è QR, i `qrData*` devono essere
  null. Se una faccia è `qr_logo` o `logo`, `logoAssetId` obbligatorio.
- **V5 — Pavimento QR dipendente da forma e URL**: per ogni faccia QR si
  calcola `version = minima versione byte-mode che contiene l'URL a qrEc`
  (tabella ISO in config), `n = 17 + 4 × version`, quindi
  `size_min_qr = pitch_min × (n + 8)` (square) oppure
  `pitch_min × (n × √2 + 8)` (circle), con `pitch_min = 1.200 mm` (policy,
  indipendente dall'ugello). Vincolo: `size ≥ max(size_min_qr, pavimento di
  forma)` con pavimenti 58.8 (square) / 79.2 (circle). Il messaggio d'errore
  riporta la dimensione minima calcolata **per l'URL inserito e la forma
  scelta**, es. «con questo indirizzo il QR richiede almeno 63.6 mm di lato,
  oppure 86.0 mm di diametro». Anche V5 opera su `size` nominale.
- **V6 — Budget di spessore (nucleo residuo)**:
  `core = thickness − Σ profondità facce incise (mode=engrave|inlay) − (rimmed ? recessDepth : 0)`
  (il `relief` non consuma budget). Vincolo: `core ≥ 1.00 mm` **e**
  `core ≥ 4 × L`.
- **V7 — Spessore minimo con NFC (calcolato, mai costante)**:
  `thickness ≥ tagThickness + 0.20 + 2 × 0.40 + Σ profondità incise + (rimmed ? recessDepth : 0)`,
  e ogni parete assiale ≥ `2 × L`. Con NFC il nucleo di V6 deve contenere
  tasca + 2 pareti assiali.
- **V8 — Pianta minima con NFC**: `effectiveSize() ≥ tag + 2×0.20 + 2×1.50`,
  cioè **25.4 mm** (Ø22) / **28.4 mm** (Ø25). Per il quadrato il riferimento è
  il lato (la tasca è circolare, la parete minima è verso il bordo più vicino).
- **V9 — Profondità grafica**: `0.2 ≤ depth ≤ 2.0`. In `relief` l'altezza con
  profilo `rimmed` deve restare **sotto il bordo**: `depth < recessDepth`.
- **V10 — QR con logo centrale**: se una faccia è `qr_logo`, `qrEc = H`
  (la Form Request la forza a monte e la UI lo dichiara).
- **V11 — Layer height**: `L` entro il range dell'ugello nel profilo stampante
  (0.2 → [0.05, 0.15]; 0.4 → [0.08, 0.30]).
- **V12 — Piastra e compensazione**: `1 ≤ plate ≤ 100`;
  `−0.30 ≤ xyComp ≤ 0.30`; `0.30 ≤ tagThickness ≤ 1.60`.

Fuori dal DTO (regole **di preset**, in Form Request / componente Livewire):
`mode=engrave` rifiutato sul preset Coaster; warning (non blocco) su
custom + `rimmed` + `engrave`; avvertenza normativa Coin Cart; dichiarazione
requisito multicolore per `inlay`.

## `toCliArguments()`

```php
/** @return list<string> argomenti per la facade Process — MAI stringa concatenata */
public function toCliArguments(
    string $outPath,
    ?string $outAccentPath = null,   // obbligatorio se mode=inlay (invariante)
    ?string $logoPath = null,        // assoluto, risolto dal Job dentro il worker
): array;
```

Regole di emissione (deterministiche: i test di mapping DTO → CLI di WS-6
hanno un atteso univoco):

- valori numerici formattati con punto decimale, senza notazione scientifica;
- flag omessi quando il campo è null e il default è del motore
  (`--layer-height`); `--margin auto` emesso letteralmente quando `margin`
  è null;
- **flag condizionali omessi quando la condizione non è attiva**:
  `--fillet` solo con `shape=square`; `--rim-width` e `--recess-depth` solo
  con `baseProfile=rimmed`; `--logo` e `--logo-rotate` solo quando
  `$logoPath` è presente; `--qr-data-front`/`--qr-data-back` solo per le
  facce con contenuto QR;
- `--nfc` emesso solo se `nfc=true`, e allora sempre con `--tag` e
  `--tag-thickness`;
- `--out-accent` emesso se e solo se `mode=inlay`;
- mai `--qr-data`: sempre le varianti per faccia;
- tutti gli altri flag sempre emessi, anche al valore di default
  (espliciti > impliciti nel log del job);
- ordine deterministico (quello della tabella campi) per testabilità.

Esempio (preset MenuTag, NFC attivo):

```
--shape square --size 58.8 --fillet 4.0 --thickness 3.0
--base-profile flat --front qr --back none --mode engrave --depth 0.6
--margin auto --qr-data-front https://menu.example.it/demo --qr-ec H
--nfc --tag 25 --tag-thickness 0.80 --nozzle 0.4 --layer-height 0.1
--printer a1mini --material pla-matte --plate 1 --xy-comp 0.0
--out /abs/path/out.stl
```

## Dove valida chi (tre livelli, stesse costanti)

| Livello | Cosa | Esito |
|---|---|---|
| Form Request (`StoreMenuTagRequest`) | tipi, range grezzi, regole di preset, messaggi in italiano | 422 |
| DTO (costruttore) | invarianti V1–V12 | eccezione → 422 (non deve mai arrivare a runtime se la Form Request è corretta: è la rete di sicurezza per API e usi programmatici) |
| Motore Python | riverifica V1–V12 + geometria §8.2 + metriche artwork §8.4 | exit 2 / interno / report (decisioni §3) |
