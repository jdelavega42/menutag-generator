# Design tokens — Cyber-Maker Studio (Fase 0)

Tema unico v1: **dark nativa** (il chiaro è roadmap dichiarata). Ogni valore
qui sotto è vincolante: R-1 li trasporta in `resources/css/app.css` come
custom properties/`@theme` Tailwind, i mockup li embeddano tali e quali.
Nessun colore o dimensione può vivere fuori da questa tabella.

## 1. Superfici e neutri

Scala blu-carbone (non nero puro): la gerarchia si legge per elevazione.

| Token | Hex | Ruolo | Perché |
|---|---|---|---|
| `--surface-0` | `#0B0F14` | fondale dell'app | scuro ma non #000: i pannelli hanno spazio per salire |
| `--surface-1` | `#11161D` | pannelli laterali, sezioni | +1 elevazione |
| `--surface-2` | `#171E27` | card, controlli, input | +2, il livello interattivo |
| `--surface-3` | `#1E2631` | hover, riga attiva | +3, feedback immediato |
| `--border-subtle` | `#232C38` | bordi di pannelli e card | in dark i bordi valgono più delle ombre |
| `--border-strong` | `#33404F` | bordi di input e focus ring di base | separa l'interattivo dal decorativo |
| `--text-primary` | `#E8EDF2` | testo principale | ~15:1 su surface-0 |
| `--text-secondary` | `#A7B2BE` | testo di supporto | ~7.5:1 |
| `--text-muted` | `#7B8A99` | didascalie, placeholder | 4.74:1 anche su surface-2 (gli input), AA ovunque |

## 2. Bicolore industriale — due accenti, due mestieri

L'eco dell'intarsio bicolore del prodotto: due colori, ciascuno con un compito.

| Token | Hex | Ruolo esclusivo | Perché |
|---|---|---|---|
| `--accent` | `#FF7A1A` | **azione**: CTA primarie, generazione, progresso | arancio industriale (banco CNC, Fusion 360); ~7.2:1 su surface-0 |
| `--accent-ink` | `#161006` | testo sopra `--accent` | ≥7:1 sull'arancio |
| `--tech` | `#2FD4E6` | **dato**: misure, readout, griglia blueprint, valori mono | ciano da blueprint; ~10:1 su surface-0 |

Qualunque altro uso di questi due colori è un errore di design. Link testuali:
`--tech`. Selezione/stato attivo delle card: bordo `--accent`.

## 3. Semafori (unica eccezione al bicolore)

Tre colori con un solo mestiere ciascuno: lo stato della verifica di stampa
— il momento più importante della UI, per questo tutti ≥ 7:1 (§3.4).

| Token | Hex | Uso | Perché |
|---|---|---|---|
| `--ok` | `#4ADE80` | verifica superata | ~9:1 su surface-0 |
| `--warn` | `#FBBF24` | attenzione | ~10:1 |
| `--blocked` | `#F98080` | esito bloccante | 7.71:1 su surface-0, 7.17:1 su blocked-surface |

Ognuno ha la variante `-surface` per gli sfondi dei badge: `#122117`,
`#231D0E`, `#231314` (colore pieno solo su testo/icona, mai come campitura
di grandi aree — un semaforo che urla su mezzo schermo non è Pro Tech).

## 4. Griglia blueprint

`--grid-line: rgba(47, 212, 230, 0.06)` — quadretti 24×24 px solo nel
viewport 3D e negli sfondi hero (linear-gradient doppio). Mai sotto il testo.

## 5. Tipografia

| Token | Valore | Uso |
|---|---|---|
| `--font-sans` | `'Instrument Sans', ui-sans-serif, system-ui` | interfaccia (già in bundle: 400/500/600 — zero dipendenze nuove) |
| `--font-mono` | `ui-monospace, 'SF Mono', 'Cascadia Code', Menlo, Consolas, monospace` | **ogni valore numerico dimensionale** (58.8 mm, strato 19/29); stack di sistema: tabular numbers gratis, zero font da scaricare |

Scala (px, equivalenti rem su base 16: 0.75/0.8125/0.875/1/1.125/1.375/1.75/2.25):
`12 / 13 / 14 (base) / 16 / 18 / 22 / 28 / 36`. Pesi: 400 corpo, 500
etichette, 600 titoli e CTA. Le misure in mono usano sempre
`font-variant-numeric: tabular-nums` — le cifre incolonnate sono metà della
credibilità di un readout.

## 6. Spazi, raggi, elevazione

- Spacing: scala 4 px (default Tailwind — zero attrito con le utility
  esistenti), pannelli con padding 20/24 px.
- Raggi: controlli `6px`, card `10px`, pannelli e viewport `12px`, badge
  `999px` — raggi piccoli e squadrati: un tool di precisione non è un
  giocattolo bombato.
- Elevazione: bordi prima delle ombre (in dark le ombre spariscono nel
  fondale); ombra solo per overlay/dropdown: `0 8px 24px rgba(0,0,0,.45)`.

## 7. Motion

- Durate: micro 140 ms, pannelli 200 ms — sotto la soglia del percepito come
  "lento", sopra quella del "saltato". Easing `cubic-bezier(0.2, 0, 0, 1)`.
- Nessuna animazione sul canvas 3D (ha le sue). `prefers-reduced-motion:
  reduce` → transizioni a 0 ms.

## 8. Blocco CSS canonico

Questo blocco è la fonte per i mockup e per R-1 (che lo porta in Tailwind):

```css
:root {
  --surface-0:#0B0F14; --surface-1:#11161D; --surface-2:#171E27; --surface-3:#1E2631;
  --border-subtle:#232C38; --border-strong:#33404F;
  --text-primary:#E8EDF2; --text-secondary:#A7B2BE; --text-muted:#7B8A99;
  --accent:#FF7A1A; --accent-ink:#161006; --tech:#2FD4E6;
  --ok:#4ADE80; --warn:#FBBF24; --blocked:#F98080;
  --ok-surface:#122117; --warn-surface:#231D0E; --blocked-surface:#231314;
  --grid-line:rgba(47,212,230,.06);
  --font-sans:'Instrument Sans', ui-sans-serif, system-ui;
  --font-mono:ui-monospace,'SF Mono','Cascadia Code',Menlo,Consolas,monospace;
  --r-control:6px; --r-card:10px; --r-panel:12px;
  --t-micro:140ms; --t-panel:200ms; --ease:cubic-bezier(.2,0,0,1);
}
body { background:var(--surface-0); color:var(--text-primary);
       font-family:var(--font-sans); font-size:14px; line-height:1.55; }
.mono { font-family:var(--font-mono); font-variant-numeric:tabular-nums;
        color:var(--tech); }
.blueprint { background-image:
  linear-gradient(var(--grid-line) 1px, transparent 1px),
  linear-gradient(90deg, var(--grid-line) 1px, transparent 1px);
  background-size:24px 24px; }
@media (prefers-reduced-motion: reduce) {
  * { transition-duration:0ms !important; animation:none !important; }
}
```
