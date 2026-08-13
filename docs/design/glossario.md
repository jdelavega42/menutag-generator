# Glossario — gergo → linguaggio umano (Fase 0)

Estratto **con grep dalle viste reali** (`resources/views/`), non a memoria.
Regola: il termine tecnico può sopravvivere solo come dettaglio secondario
(testo minore, in mono se numerico), mai come etichetta primaria.

## Classe A — banditi ovunque, tooltip compresi

`mesh`, `watertight`, `manifold`, `winding`. Oggi già a zero nelle viste:
resta la guardia di regressione
`grep -riE 'watertight|manifold|mesh|winding' resources/views/` → 0 righe.

## Classe B — etichette da sostituire (audit: grep a zero a fine restyle)

| Oggi (vista) | Nuova etichetta primaria | Dettaglio secondario |
|---|---|---|
| Correzione d'errore (L/M/Q/H) | **Affidabilità di scansione** | «Massima (consigliata)» default; L/M/Q/H solo nelle impostazioni avanzate |
| Ugello (mm) | **Qualità di stampa** | «Standard (ugello 0.4)» / «Dettaglio fine (ugello 0.2)» |
| Altezza layer (mm) | **Strati di stampa** | valore mm in mono |
| Raggio smussatura angoli (mm) | **Angoli arrotondati** | valore mm in mono |
| Compensazione XY (mm per lato) | **Adattamento alla misura reale** | «una stampa esce ~0.1 mm più larga: compensiamo noi» |
| Piastra da N pezzi | **Pezzi per stampata** | ingombro piastra in mono |
| Spessore reale del tag (mm) | **Spessore del tag NFC** | «misuralo col calibro, adesivo incluso» |
| Triangoli | **Facce del modello** (solo dentro «Dettagli tecnici», in mono) | così il grep resta a zero senza whitelist |
| Ugello / layer (pagina di stato) | **Qualità di stampa / Strati** | valori in mono |
| Report di stampabilità | **Verifica di stampa** | semaforo + frasi (sotto) |

Denominazione unica per la resa bicolore: **«A filo bicolore»** ovunque
(mai «intarsio»/«inlay» come etichetta primaria — «(a filo bicolore)» come
chiarimento è ammesso).

Audit eseguibile: `grep -rn "Correzione d'errore\|Ugello (mm)\|Ugello / layer\|Altezza layer\|Triangoli\|Piastra da N" resources/views/` → 0 righe.
Whitelist: valori di enum nel markup (`engrave/relief/inlay` in `wire:model`,
chiavi config) sono legittimi.

## Classe C — riformulazioni non greppabili (revisione manuale, vista per vista)

| Oggi | Nuovo |
|---|---|
| Genera l'STL | **Crea il file di stampa** |
| Scarica STL / STL / Accento | **Scarica il file di stampa (STL)** alla prima occorrenza; poi «File di stampa» / «Secondo colore (accento)» |
| layer bicromatici | **strati a due colori** |
| hint «(inlay)» | «(a filo bicolore)» |
| Contenuto per faccia | **Fronte e retro** |
| Fasce di prodotto | **Disponibile a questa dimensione** |
| «pausa dopo il layer 19 (Z = 2.00 mm)» | **«Pausa per inserire il tag NFC: dopo lo strato `19` (quota `2.00 mm`)»** — numeri in mono |
| Incisa / Rilievo / A filo bicolore | invariati (già umani) — ma presentati come **card con anteprima della resa**, non radio nudi |

## Verifica di stampa — frasi del semaforo

| Esito | Titolo | Frase |
|---|---|---|
| ok | Verificato: pronto per la stampa | «Il QR è stato letto dalla geometria reale: si scansiona.» |
| warn | Stampabile, con un'attenzione | una frase per warning del motore, riscritta dal glossario (es. «Dettagli molto fini: stampa con almeno 2 contorni») |
| blocked | Sconsigliato così | «Puoi comunque scaricare il file, ma ti spieghiamo cosa rischia» + frasi |

I numeri (dettaglio minimo, residuo, moduli) restano visibili in un blocco
«Dettagli tecnici» richiudibile, in mono.
