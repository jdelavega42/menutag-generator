# Verifica — Definition of Done (§10 del prompt), compilata da R-5

Data: 2026-08-14 · branch `restyle/cyber-maker` · esiti REALI dei comandi.
Screenshot di verifica: scratchpad R-5 (`restyle/`), immagini pubblicate in
`docs/images/`.

## Estetica

- [x] **Colori/spacing/type dai token; zero valori sparsi nelle viste.**
  `grep -rniE '#[0-9a-f]{3,8}\b' resources/views/ --include='*.blade.php'`
  → 0 righe (l'unico file con hex era `welcome.blade.php`, vista morta dello
  starter kit non referenziata da alcuna rotta: rimossa da R-5). Nessun
  colore Tailwind fuori token nelle viste di prodotto; le pagine
  auth/impostazioni dello scaffold usano `zinc`, rimappato sui token in
  `app.css` (`--color-zinc-*`) e dichiarato nel README come limite noto.
  Residuano valori `px` arbitrari solo dimensionali/layout (grid template,
  `tracking-[0.08em]`, `size-[18px]`), nessuno di colore o tipografia.
- [x] **Contrasto misurato** (WCAG, calcolo su hex dei token in `app.css`,
  identici a `tokens.md` §8):
  testo `text-primary/surface-0` **16.32:1**, `text-secondary/surface-0`
  **8.93:1**, `text-muted/surface-2` **4.74:1** (AA);
  misure `tech/surface-0` **10.69:1**, `tech/surface-2` **9.33:1** (≥7:1);
  CTA `accent-ink/accent` **7.25:1**;
  semafori `ok` **11.03:1** (9.60 su ok-surface), `warn` **11.51:1**
  (10.04), `blocked` **7.71:1** (7.17) — tutti ≥7:1.
  **Deviazione trovata e corretta**: il focus ring di base usava
  `--border-strong` (**1.82:1** su surface-0, sotto il 3:1 non-text) →
  portato a `--accent` (**7.37:1**) in `app.css`; `tokens.md` aggiornato.
- [x] **Monospace su ogni quota/misura** (classe `.mono`, tabular-nums:
  readout viewer, fasce, report, dashboard — verificato negli screenshot).
  **Viewer ≥ 50% e above-the-fold a 1280×800**: colonna viewer 627px su
  1216px utili (51.6%) nel wizard, 7fr/12fr (58%) nella pagina del modello;
  visibile dal primo paint in tutti gli screenshot a 1280×900.

## UX e copy

- [x] **Audit gergo.**
  Classe A: `grep -riE 'watertight|manifold|mesh|winding' resources/views/`
  → **0 righe**.
  Classe B: `grep -rn "Correzione d'errore\|Ugello (mm)\|Ugello / layer\|
  Altezza layer\|Triangoli\|Piastra da N" resources/views/` → **0 righe**.
  Classe C (revisione manuale col glossario, vista per vista):
  `preset-picker`, `configurator` (wizard + parametrico), `job-status`,
  `printability-report`, `preview-viewer`, `menu-tags/show`, `dashboard`/
  `tag-history`, `studio-promo`, layout — conformi («Crea il file di
  stampa», «file di stampa (STL)» alla prima occorrenza, «A filo bicolore»,
  «Strati a due colori», «Facce del modello», «Affidabilità di scansione»
  con default Massima, pausa NFC con strato/quota in mono).
- [x] **Formato e resa come card con anteprima** (card preset nel wizard e
  nello Studio; card di resa con spaccato SVG nello Studio).
- [x] **Verifica di stampa in linguaggio umano** con «Dettagli tecnici»
  richiudibile in mono (screenshot `06-report-stampabilita.png`).
- [x] **Stati di generazione narrativi** agganciati agli stati reali:
  «Stiamo incidendo il tuo QR…» osservato live su un record `processing`
  (poll attivo), esiti `completed`/`failed` dedicati in `job-status`.

## Flussi e gating

- [x] **Ospite end-to-end** (motore VERO, non fake): wizard formato → URL →
  «Crea il file di stampa» → job processato dal motore Python
  (`queue:work`, 872 ms) → «Verificato: pronto per la stampa» → download STL
  firmato (HTTP 200, 114 284 byte) + guida (HTTP 200). Variante logo
  (Coaster/Coin Cart) verificata fino al passo 2 con anteprima (il ramo
  submit-con-logo è coperto dalla suite; la generazione reale e2e è stata
  eseguita sulla variante URL).
- [x] **Parametrico irraggiungibile server-side per l'ospite**: deep-link
  `/?duplica=12` (record ospite esistente) → CTA registrazione, nessuna
  sezione parametrica nel DOM (`parametricLeaked:false`); guard `abort_if`
  403 in `Configurator::customize()`/`updating()`; test `GuestGatingTest`
  nella suite.
- [x] **CTA contestuali + pagina promo** `/studio` raggiungibile (nav,
  card «Sblocca lo Studio», risultato, pagina record ospite); benefici
  reali, **zero contatori/testimonial** (probe `fakeCounters:false`; lo
  schema della dashboard è dichiarato «illustrativo, non uno screenshot»).
  Nota: `flussi.md` §3 prevedeva uno screenshot reale della dashboard —
  R-4 ha scelto lo schema illustrativo etichettato, conforme a §9.
- [x] **Registrato**: login demo → home con «Personalizza questo formato» →
  Studio parametrico con «Impostazioni di stampa avanzate» **chiuse di
  default** (probe: contenuto assente prima del click, presente dopo);
  dashboard sui token (screenshot `05-dashboard.png`).
- [x] **Quote/retention/signed URL/migrazione invariati**: quota 5/h e
  retention 24 h mostrate nel wizard e lette da `config/product.php`;
  download ospite via `signed`; suite completa verde (test aggiornati da
  R-2, nessuno rimosso).

## Tecnica

- [x] **`composer ci:check` verde**: pint passed, phpstan 0 errori,
  **pest 154/154** (512 assertion) — era 133 all'avvio del restyle (≥138
  richiesti).
- [x] **Perf**: `npm run build` →
  entry sincrona `app-*.js` **7.16 kB (2.92 kB gzip)**;
  `viewer-*.js` (three.js + STLLoader + qrcode) **603.74 kB (156.66 kB
  gzip)** come **chunk dinamico** (`isDynamicEntry`, unico chunk contenente
  `WebGLRenderer`); CSS 35.64 kB gzip; `passkeys.js` entry separata
  3.90 kB gzip. Somma chunk sincroni della home: **2.92 kB gzip JS**
  (38.56 kB includendo il CSS) — **< 60 kB**. Prima: entry unica ~609 kB
  (~153 kB gzip) con three.js. Viewer **precaricato su idle**
  (`requestIdleCallback` in `app.js`, stesso URL modulo → cache hit).
- [x] **Contratto 04 intatto / viewer sopravvive al polling**: su un record
  `processing` reale il `wire:poll.2500ms` è presente SOLO in `job-status`
  (fuori da `section[aria-label="Anteprima 3D"]`), canvas dentro
  `wire:ignore`; dopo >2 cicli di poll il canvas è ancora vivo
  (`canvasStillAlive:true`). Poll condizionale: assente sugli stati
  terminali.
- [x] **A11y**: `:focus-visible` visibile ovunque (outline `--accent` 2px,
  screenshot da navigazione Tab reale); canvas con `role="img"` +
  `aria-label`; wizard navigabile da tastiera (bottoni/link nativi);
  `prefers-reduced-motion` azzera le transizioni.
- [x] **Screenshot e GIF del README rigenerati** con la UI nuova (stessa
  tecnica CDP/headless dei vecchi, 1280 di larghezza; GIF 420×420 da 37
  frame di trascinamento reale del viewer su una generazione vera).
- [x] **Brand «MenuTag Studio»** in layout/nav/titoli e README; rotte,
  tabelle, identificatori e nome repo invariati. Eccezione dichiarata: la
  guida di stampa generata cita «MenuTag Generator» perché il testo vive in
  `app/Services/PrintGuideGenerator.php` (perimetro intoccabile).

## Non implementato (e perché)

- **Tema chiaro**: fuori scope v1 per brief §3.1 — in roadmap README.
- **Rebrand della guida di stampa generata**: richiederebbe di toccare
  `app/Services/` (intoccabile da perimetro) — dichiarato nel README.
- **Restyle vista-per-vista di auth/impostazioni scaffold**: coperte dal
  remap `zinc→token` (dark coerente), non dal glossario — dichiarato nel
  README.
- **E2e con upload logo reale via browser**: il passo 2 variante logo è
  verificato visivamente e da suite; l'automazione dell'upload file in
  headless è rimandata (il flusso URL è coperto end-to-end col motore vero).
