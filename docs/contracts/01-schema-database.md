# Contratto — Schema del database (WS-1)

MariaDB, driver `mariadb`. Migration di framework (users di base, `jobs`,
`failed_jobs`, `job_batches`, `sessions`, `cache`, `personal_access_tokens`)
arrivano dallo scaffold Laravel 13 + starter kit (incluse le colonne 2FA di
Fortify su `users`): qui si elencano solo le aggiunte e le tabelle di dominio.

## users (estensione dello scaffold)

| Colonna | Tipo | Note |
|---|---|---|
| `company_name` | `string` nullable | ragione sociale per il B2B |

## logo_assets

| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK `users` nullable, `cascadeOnDelete` | null = ospite |
| `guest_token` | `uuid` nullable, **indicizzata** | null = autenticato; xor con `user_id` (invariante applicativa) |
| `disk_path` | `string` | path **relativo** sul disco `assets` (mai assoluto: app e worker sono container diversi) |
| `original_name` | `string` | nome originale, solo display; il nome file su disco è generato dal server |
| `mime` | `string` | solo `image/png` o `image/svg+xml`, verificato dal contenuto (WS-5) |
| `size_bytes` | `unsignedInteger` | ≤ 2 MB |
| `created_at` / `updated_at` | timestamps | i loghi ospite si cancellano a job concluso o via retention |

Indici: `user_id`, `guest_token`.

## qr_presets (solo utenti autenticati — dashboard)

| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK `users`, `cascadeOnDelete` | obbligatoria |
| `name` | `string` | etichetta («Menù EN», «Recensioni») |
| `data` | `text` | URL / contenuto del QR |
| timestamps | | |

Indici: `user_id`; unique (`user_id`, `name`).

## menu_tags

| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK `users` nullable, `cascadeOnDelete` | |
| `guest_token` | `uuid` nullable, **indicizzata** | migrata su `user_id` alla registrazione dell'ospite (§7.2) |
| `label` | `string` nullable | nome leggibile per lo storico rivenditori |
| `preset` | `string` enum-cast `Preset` (`menutag`,`coaster`,`coin_cart`) | preset di origine — il custom nasce sempre da un preset |
| `customized` | `boolean` default false | true se l'utente ha sbloccato i parametri |
| `logo_asset_id` | FK `logo_assets` nullable, `nullOnDelete` | |
| `parameters` | `json`, cast a `MenuTagParameters` (DTO) | snapshot completo, fonte per la duplicazione |
| `status` | `string` enum-cast `MenuTagStatus` (`queued`,`processing`,`completed`,`failed`) default `queued` | |
| `stl_path` | `string` nullable | relativo al disco `stl` |
| `stl_accent_path` | `string` nullable | solo `mode=inlay` |
| `report` | `json` nullable | tutte le chiavi stdout del motore (§8.5) — alimenta report di stampabilità e guida di stampa |
| `triangles` | `unsignedInteger` nullable | |
| `volume_mm3` | `decimal(12,3)` nullable | |
| `weight_g` | `decimal(8,2)` nullable | ⚠ rinominata da `weight_pla_g`, vedi decisioni §2 |
| `pause_z` | `decimal(6,3)` nullable | solo con NFC |
| `pause_layer` | `unsignedSmallInteger` nullable | solo con NFC |
| `printability` | `string` enum-cast `Printability` (`ok`,`warn`,`blocked`) nullable | |
| `error_message` | `text` nullable | messaggio leggibile (exit 2 = stderr del motore; interno = messaggio generico) |
| timestamps | | `created_at` guida la retention 24 h ospiti |

Indici: `user_id`, `guest_token`, `status`, (`user_id`, `created_at`).

## Enum PHP (`app/Enums/`, tutti string-backed salvo indicazione)

`Shape` (`circle`,`square`) · `BaseProfile` (`flat`,`rimmed`) ·
`FaceContent` (`none`,`logo`,`qr`,`qr_logo`) · `RenderMode`
(`engrave`,`relief`,`inlay`) · `QrEcLevel` (`L`,`M`,`Q`,`H`) ·
`TagDiameter` (int-backed: `22`,`25`) · `Nozzle` (string-backed `'0.2'`,`'0.4'`
— mai float: i valori vanno al CLI testuali ed esatti) · `Material`
(`pla-matte`,`petg`, stessi valori del CLI) · `MenuTagStatus` ·
`Preset` (`menutag`,`coaster`,`coin_cart`) · `Printability` (`ok`,`warn`,`blocked`).

## Casts sul model `MenuTag`

`preset`, `status`, `printability` → enum nativi; `parameters` → cast custom
al DTO `MenuTagParameters`; `report` → `array`; `customized` → `boolean`.

## Relazioni

`User hasMany MenuTag, LogoAsset, QrPreset` · `MenuTag belongsTo User,
LogoAsset` · scope `forOwner()` che filtra per `user_id` **oppure**
`guest_token`, mai entrambi.
