# Perimetro — file toccati e non toccati, per workstream (Fase 0)

## Non si toccano MAI (qualunque WS)

`engine/`, `app/DTOs/`, `app/Http/Requests/`, `app/Services/`, `app/Jobs/`,
`app/Policies/`, `app/Contracts/`, `app/Exceptions/`, `config/product.php`,
`config/printers.php`, `routes/api.php`, `app/Http/Controllers/Api/`,
`database/migrations/`, `docs/contracts/`. Gli **eventi** (nomi e payload,
contratto 04) e le rotte/nomi esistenti restano invariati.

## R-1 Fondamenta
- Tocca: `resources/css/app.css` (token→Tailwind `@theme`),
  `resources/views/layouts/` e `resources/views/partials/head.blade.php`
  (brand «MenuTag Studio», fondale, nav), `vite.config.js` +
  `resources/js/app.js` (code-splitting: `viewer.js` e three.js in chunk
  dinamico con `modulepreload`), tematizzazione Flux.
- Non tocca: `app/Livewire/`, viste dei componenti.

## R-2 Flusso ospite (dopo R-1)
- Tocca: `app/Livewire/PresetPicker.php`, **`Configurator.php` — solo gate e
  ramo ospite** (proprietà `isGuest`/step del wizard, blocco server-side di
  `select`/`fillFromExisting`/duplicazione per ospiti), `JobStatus.php`,
  `PrintabilityReport.php` (copy/stati), le viste corrispondenti in
  `resources/views/livewire/` e `resources/views/menu-tags/`,
  `routes/web.php` (nessuna rotta rimossa), test:
  `tests/Feature/Web/ConfiguratorSubmitTest.php` aggiornato + nuovo
  `GuestGatingTest`.
- Non tocca: le sezioni parametriche della vista configurator (restano
  funzionanti per i registrati, ristilate in R-3).

## R-3 Studio registrati (dopo R-2)
- Tocca: **`Configurator.php` — sezioni parametriche e progressive
  disclosure**, `resources/views/livewire/configurator.blade.php` (sezioni
  avanzate), `TagHistory.php`, `LogoLibrary.php`, `QrPresetManager.php`,
  viste dashboard (`resources/views/dashboard.blade.php`, livewire relativi).

## R-4 Promo + copy (dopo R-3)
- Tocca: nuova rotta `studio` in `routes/web.php` + vista promo, passata
  completa del glossario su TUTTE le viste in `resources/views/`
  (classe B e C), `lang/` se necessario.

## R-5 Verifica (= Fase 2, dopo tutti)
- Tocca: `docs/images/` (screenshot nuovi), `README.md` (brand, immagini,
  sezione flussi aggiornata).
- Esegue: audit classi A/B (grep), revisione classe C, contrasto misurato,
  a11y, manifest Vite + `npm run build` prima/dopo, `composer ci:check`,
  checklist §10 del prompt voce per voce.

## Nota sul parallelismo

R-2 e R-3 condividono `Configurator.php` e la sua vista: **sequenziali,
mai in parallelo**. R-1 è prerequisito di tutto.
