# Contratto — Componenti ed eventi Livewire (WS-4, definiti in Fase 0)

I nomi degli eventi e i payload sono vincolanti: WS-3 (job → stato) e WS-4
(UI) si incontrano qui. Nomenclatura eventi dal prompt §7.3.

## Componenti (`app/Livewire/`)

| Componente | Responsabilità | Note DOM |
|---|---|---|
| `PresetPicker` | scelta fra i TRE preset (MenuTag preselezionato); il parametrico NON è una quarta carta | |
| `Configurator` | stato del form (preset bloccato o «personalizza questo formato»); **fasce di prodotto live**: opzioni QR disabilitate sotto soglia con spiegazione e dimensione minima calcolata su URL e forma correnti, minimo funzionale mostrato accanto al minimo di prodotto con la ragione (§3.2, §8.8); alla scelta di `inlay` dichiara il requisito multicolore e **mostra i layer bicromatici** `depth / layerHeight` (tolleranza 1e-9) e propone `depth` 0.5; validazione live non bloccante; submit → dispatch job | i parametri di anteprima sono **entangled** con Alpine; la maggior parte dei cambi non genera richieste Livewire |
| `PreviewViewer` | contenitore del canvas three.js | `wire:ignore` sul div del canvas; scena inizializzata una sola volta in `x-init`; **nessun `wire:poll` in questo sottoalbero**; ascolta solo eventi browser |
| `JobStatus` | polling dello stato del record | componente **separato** dal viewer; `wire:poll.2500ms` reso **condizionale**: presente solo con status `queued|processing` |
| `PrintabilityReport` | report §8.8 a job completato | |
| Dashboard: `LogoLibrary`, `QrPresetManager`, `TagHistory` | libreria loghi, QR salvati, storico + duplicazione | ogni query passa dalle Policy / scope `forOwner()` |

`wire:key` stabili su ogni componente ripetuto o condizionale.

## Eventi

Tipo **L** = Livewire (`$this->dispatch()->to(...)`, ascolto `#[On]`);
tipo **B** = browser (`$this->dispatch(...)` ascoltato in JS con
`$wire.on(...)` / listener window).

| Evento | Tipo | Payload | Emittente → Ascoltatori |
|---|---|---|---|
| `preset-selected` | L | `{preset: 'menutag'\|'coaster'\|'coin_cart'}` | PresetPicker → Configurator |
| `menutag-updated` | B | `{params: PreviewParams}` | Configurator (solo mutazioni lato server: cambio preset, adeguamento dimensione) → viewer JS |
| `size-adjusted` | B | `{oldSize: number, newSize: number, reason: string}` | Configurator → toast UI (avviso esplicito §5.2: mai adeguare in silenzio; mai ridurre una dimensione impostata a mano) |
| `menutag-queued` | L+B | `{menuTagId: number}` | Configurator → JobStatus (avvia il poll) |
| `menutag-completed` | L+B | `{menuTagId: number, stlUrl: string, accentStlUrl: string\|null, report: object}` | JobStatus → viewer JS (`$wire.on`/STLLoader) + PrintabilityReport (`#[On]`) |
| `menutag-failed` | B | `{menuTagId: number, message: string}` | JobStatus → UI errore |
| `logo-uploaded` | L | `{logoAssetId: number, previewUrl: string}` | LogoLibrary/uploader → Configurator |

`stlUrl`/`accentStlUrl`: per gli ospiti sono **signed URL temporanei** (24 h),
per gli autenticati rotte protette da Policy (WS-5).

## `PreviewParams` (stato Alpine entangled, contratto col modulo viewer)

```ts
{
  shape: 'circle'|'square', size: number, fillet: number, thickness: number,
  baseProfile: 'flat'|'rimmed', rimWidth: number, recessDepth: number,
  front: 'none'|'logo'|'qr'|'qr_logo', back: 'none'|'logo'|'qr'|'qr_logo',
  mode: 'engrave'|'relief'|'inlay', depth: number,
  qrDataFront: string|null, qrDataBack: string|null, qrEc: 'L'|'M'|'Q'|'H',
  nfc: boolean, tagDiameter: 22|25
}
```

## Modulo viewer (`resources/js/viewer.js`)

- Esporta `createMenuTagViewer(el, initialParams)` → oggetto con
  `update(params)` (anteprima parametrica, geometria liscia ricalcolata
  client-side, **zero richieste al server**), `loadStl(url, accentUrl?)`,
  `dispose()`.
- `dispose()` obbligatorio nel `destroy()` di Alpine: geometrie, materiali,
  renderer (limite ~16 contesti WebGL).
- **Simbolo QR client-side** con la libreria npm `qrcode` (giustificata in
  decisioni §1): il viewer genera la matrice dall'URL corrente
  (`qrDataFront`/`qrDataBack`) forzando **byte mode, EC e versione** con le
  stesse regole del config (parità con PHP e motore) e la applica come
  testura alla faccia. L'anteprima iniziale mostra così un **QR reale e
  scansionabile** (URL demo da config) fin dal primo caricamento, e resta
  reale mentre l'utente digita il proprio URL — nessuna chiamata al server.

## Regole di flusso (riepilogo §5.2/§7.3/§7.4)

1. Ingresso: PresetPicker con MenuTag attivo e anteprima con QR scansionabile.
2. Modifiche parametriche → Alpine aggiorna il viewer direttamente; Livewire
   riceve i valori solo alla validazione live (debounced) e al submit.
3. URL più lungo della versione 6 → il Configurator ricalcola `size_min_qr`
   (stessa tabella byte-mode del config), adegua verso l'alto ed emette
   `size-adjusted`; se la dimensione è stata impostata a mano, non la
   sovrascrive: segnala l'incompatibilità e propone l'adeguamento.
4. **Cambio forma con faccia QR attiva** (quadrato → cerchio): alla
   validazione live il Configurator ricalcola il pavimento dipendente dalla
   forma (58.8 → 79.2) e `size_min_qr`, segnala che la soglia sale e che il
   modulo si riduce (~35 % a parità di ingombro); se la dimensione non basta
   più, propone l'adeguamento riusando `size-adjusted` con `reason` dedicata —
   mai disabilitare in silenzio, mai sovrascrivere una dimensione manuale.
5. Submit → `menutag-queued` → JobStatus polla; agli stati terminali il poll si
   ferma (attributo assente) ed emette `menutag-completed` o `menutag-failed`.
6. Il viewer non si azzera mai durante il polling (poll fuori dal sottoalbero
   del canvas, `wire:ignore` sul contenitore).
