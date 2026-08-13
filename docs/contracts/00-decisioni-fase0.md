# Fase 0 — Decisioni, deviazioni dichiarate e assunzioni

Data: 2026-08-12. Questo documento fissa le scelte trasversali; i contratti di
dettaglio sono nei file `01`–`05` e in `docs/openapi.yaml`. Ogni workstream li
tratta come vincolanti: una modifica a un contratto richiede di riaprire la
Fase 0.

## 1. Stack verificato (laravel.com, 2026-08-12)

| Componente | Versione fissata | Nota |
|---|---|---|
| Laravel | **13.x** (ultima major stabile) | verificato su laravel.com/docs |
| Livewire | **4.x** — via starter kit ufficiale Livewire | ⚠ deviazione dichiarata, vedi sotto |
| Autenticazione | **Starter kit Livewire ufficiale (Fortify)** | ⚠ Breeze non esiste più per Laravel 13 |
| Flux UI (free) | incluso nello starter kit | giustificazione: dipendenza dello starter kit ufficiale, non aggiunta nostra |
| MariaDB | driver nativo `DB_CONNECTION=mariadb` | Sail service `mariadb` |
| Sanctum | API a token `/api/v1` | |
| Tailwind CSS | via Vite (starter kit) | |
| three.js | anteprima 3D | richiesto dal prompt |
| qrcode (npm) | simbolo QR **client-side** nell'anteprima | giustificazione: §5.2 chiede l'anteprima del simbolo sull'URL inserito e §5.3 vieta chiamate al server per l'anteprima; la libreria permette di forzare versione/EC in byte mode, in parità con PHP e motore |
| Pest | test | |
| Python | 3.12 in virtualenv `engine/.venv` | il container app lo installa nel Dockerfile |

**Deviazione dichiarata — Livewire 4 al posto di Livewire 3, starter kit al
posto di Breeze.** Il prompt chiede «Laravel ultima major stabile» e «Breeze,
starter kit Livewire». Le due richieste oggi sono incompatibili: Breeze è stato
ritirato dagli starter kit ufficiali e la strada documentata per Laravel 13 +
Livewire è lo starter kit ufficiale, che monta Livewire 4 e Fortify. Diamo la
precedenza al requisito «ultima major stabile», esplicito e con istruzione di
verifica. Tutti i vincoli di §7.3 del prompt (`wire:ignore`, `@entangle`,
`wire:key`, `wire:poll`, `$wire.on`, dispose in `destroy()`) esistono invariati
in Livewire 4. I componenti saranno **class-based in `app/Livewire/`**, come
richiedono i confini di file di WS-4. Alternativa scartata: Laravel 12 + Breeze
+ Livewire 3 (rispetterebbe la lettera dello stack ma non «ultima major
stabile»).

## 2. Motore: non allegato → si implementa il contratto §2.2, con estensioni

Nessun motore Python è stato allegato né trovato sul disco: vale il ramo «se
non è allegato» di §2.1. Il contratto CLI applicativo (file `03`) è quindi la
fonte di verità per WS-2.

**Estensioni al contratto §2.2** (richieste da §5.2 ma non esprimibili con gli
argomenti elencati lì — è esattamente il tipo di lacuna che la Fase 0 deve
chiudere):

- `--plate INT` (default 1): piastra da N pezzi, array già spaziato nell'STL,
  passo = ingombro pezzo + 5.0 mm, griglia il più quadrata possibile, centrata,
  bounding box verificato sull'intera piastra. In `inlay` anche l'accento è
  replicato con le stesse trasformazioni.
- `--xy-comp FLOAT` (default 0.0): compensazione XY in mm **per lato**
  applicata alla sola silhouette esterna (negativa = pezzo più piccolo).
  **Quale dimensione usa ciascuna validazione:** le verifiche geometriche
  della tasca NFC (parete radiale, pianta minima — V8 del DTO) usano la
  **dimensione effettiva** `size + 2 × xy_comp`, perché misurano il pezzo che
  esce davvero dalla stampante; i minimi/massimi di prodotto (V1) e i
  pavimenti QR (V5) si applicano alla dimensione **nominale**: sono quote di
  progetto, e la compensazione corregge la deriva di stampa, non cambia il
  prodotto (il Coin Cart a 25.75 nominali con xy-comp −0.10 resta valido).

**Rinomina dichiarata — `WEIGHT_PLA_G` → `WEIGHT_G`** (e colonna `weight_g`):
con il PETG in catalogo il peso va calcolato con la densità del materiale
scelto (PLA 1.24 g/cm³, PETG 1.27 g/cm³) e un nome che promette PLA mentirebbe
sul Coaster. Il valore è il peso del solido pieno: limite superiore rispetto
alla stampa con infill, annotato nella guida di stampa.

## 3. Partizione degli esiti del motore (riconcilia §8.2, §8.4, §8.8)

Il prompt chiede insieme «exit 2 per fallimento di stampabilità» (§8.2) e «con
`PRINTABILITY=blocked` il download resta possibile» (§8.8). La partizione che
tiene insieme le due richieste, vincolante per WS-2 e WS-3:

| Classe | Esempi | Esito |
|---|---|---|
| **Errore utente parametrico** — verificabile dai soli parametri, prima di costruire la geometria | dimensione sotto il pavimento QR, parete NFC insufficiente, budget di spessore violato, bounding box (piastra inclusa) oltre il piano, layer height fuori range | **exit 2**, nessun STL, messaggio human-readable su stderr mostrato all'utente così com'è, con l'indicazione di come rientrare nei limiti |
| **Errore interno di integrità** — la mesh prodotta non supera §8.2 | non watertight, non manifold, scarto volume analitico > 10⁻³ mm³, somma volumi inlay incoerente | **exit ≠ 0 e ≠ 2**, nessun STL, loggato e mai esposto |
| **Stampabilità del contenuto grafico** — dipende dall'artwork, misurata sulla geometria prodotta | apertura morfologica (pieno e complemento), residuo dopo il primo perimetro, decodifica del QR | **exit 0 + STL esportato**, `PRINTABILITY=ok|warn|blocked` e righe `WARNING=`; la UI mostra il report e consente il download anche su `blocked`, con avviso esplicito |

Soglie: `warn` sopra il 2 % di area a rischio, `blocked` sopra il 10 % o
`QR_DECODED=no`.

## 4. Scelte di dominio dichiarate

- **Gioco assiale tasca NFC = 0.20 mm**: il tag non fa da pavimento al primo
  layer di chiusura; il layer sopra la tasca attraversa 0.20 mm d'aria. Costo:
  un layer interno irregolare e invisibile. Alternativa scartata: ugello che
  sbatte su un tag più spesso del dichiarato = stampa persa. (§3.3 del prompt,
  adottata come richiesto; va ribadita in codice e README.)
- **Passo minimo del modulo QR = 1.200 mm, indipendente dall'ugello**: i
  pavimenti 58.8 / 79.2 mm restano validi anche con ugello 0.2. È una scelta di
  prodotto (scansionabilità garantita), configurata in `product.qr.min_pitch_mm`,
  non una costante sparsa.
- **QR: modo byte forzato, nessuna segmentazione.** La UI (PHP) deve predire la
  versione QR dalla lunghezza dell'URL con la stessa regola del motore
  (Python). Con la segmentazione mista la predizione divergerebbe. Tabella
  capacità byte-mode (ISO/IEC 18004) in `config/product.php`; il motore forza
  versione minima in byte mode, EC richiesta, e rilegge il simbolo prodotto.
- **QR con logo centrale → EC forzata a H** (Form Request la impone; la UI lo
  comunica).
- **Coin Cart: `--xy-comp -0.10` di default** (per lato, ⇒ −0.20 sul
  diametro): una stampa FDM di 25.75 nominali esce a 25.85–25.95; con la
  compensazione il nominale scende a 25.55 e l'atteso reale a 25.65–25.75.
  Con Ø22 in tasca la parete radiale resta 1.575 mm ≥ 1.50. **Da tarare col
  calibro sul primo pezzo** (obbligo in guida di stampa). Assunzione dichiarata:
  il valore esatto dipende dalla calibrazione della macchina.
- **Guida di stampa generata on-demand dal `report` JSON** salvato sul record,
  non come file su disco: evita un secondo artefatto da versionare/ripulire e
  resta sempre coerente coi metadati. Endpoint dedicato (web + API).
- **Default `mode` dei preset**: MenuTag `engrave` 0.6 (riproduce l'unica
  configurazione validata in stampa reale: 58.8 × 3.0, passo 1.200, pausa layer
  19/29 con NFC), con la UI che raccomanda `inlay` dichiarando il requisito
  multicolore; Coaster `relief` (nessun requisito hardware; `inlay` consigliato
  se AMS presente; `engrave` rifiutato); Coin Cart `relief`.

## 5. Assunzioni

- Densità: PLA (matte) 1.24 g/cm³, PETG 1.27 g/cm³.
- Spaziatura piastra 5.0 mm (coerente col ragionamento Ø85 di §5.2).
- Capacità QR byte-mode a EC H (v6 = 58 byte, **v7 = 64, v8 = 84, v9 = 98**,
  v10 = 119 — capacità di caratteri, non codeword dati): i valori
  «~52/~65/~78 caratteri» del prompt sono indicativi; fa fede la tabella ISO
  nel config, identica su PHP e Python, coperta da un **test di parità con
  casi al confine** (URL da 64 e 65 byte → v7/v8) che codifica davvero il
  payload con la libreria QR e verifica la versione risultante.
- Parametri slicer della guida di stampa (temperature, ventola, pareti,
  shell, infill, spurgo): valori di config per materiale
  (`product.print_profiles`), assunzioni dichiarate da tarare — non escono
  dal motore, che produce solo geometria.
- Rate limit API autenticata: 30 generazioni/ora per utente (valore di
  config, non richiesto dal prompt che fissa solo il limite ospiti 5/h/IP).
- Range `layer_height`: 0.2 → [0.05, 0.15], primo layer 0.15; 0.4 →
  [0.08, 0.30], primo layer 0.20. Default 0.10 per entrambi.

## 6. Roadmap dichiarata (non si implementa ora)

- Composizione logo + QR + testo sulla stessa faccia (§2.5).
- Etichette incise nel margine `--label-front` / `--label-back` (§2.5).
- Export 3MF con profilo di stampa incorporato (§8.9).
- Profili per stampanti oltre la A1 mini (la struttura `config/printers.php`
  è già pronta a riceverli).
- Verifica di `sail up` e della generazione end-to-end nei container: la
  macchina di sviluppo non ha Docker; Dockerfile/docker-compose auditati a
  mano, da provare su un host con Docker.
