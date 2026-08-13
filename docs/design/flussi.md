# Flussi — MenuTag Studio (Fase 0)

Tre flussi + un caso di transizione. Stato delle conferme richieste da §7 del
prompt: assunzioni **§5.1** (input essenziale: URL per MenuTag, logo per
Coaster/Coin Cart, lettura logo-ospite inclusa) e **§4.1** (rivenditori: vivi
ma non pubblico della home) **confermate dal committente il 2026-08-13**;
**token e flussi in attesa di conferma** al gate di chiusura della Fase 0.

**Misure canoniche** (fonte unica `config/product.php`, da usare in ogni
mockup e copy): MenuTag lato `58.8 mm` (pavimento dinamico), Coaster
`Ø 85 mm`, Coin Cart `Ø 25.75 mm`.

## 1. Ospite — «ready-to-go», zero manopole

```
Home (hero + 3 card preset, MenuTag in evidenza)
  └─ Passo 1 · Scegli il formato         [card visuali con anteprima resa]
      └─ Passo 2 · L'input essenziale
           MenuTag:            «L'indirizzo del tuo menù» (URL, validazione live,
                               consiglio URL-breve, anteprima QR immediata nel viewer)
           Coaster/Coin Cart:  «Il tuo logo» (upload PNG/SVG, anteprima immediata)
      └─ Passo 3 · Crea e scarica
           «Crea il file di stampa» → stati narrativi (In coda → Stiamo incidendo
           il tuo QR… → Verifica di stampa…) → risultato:
           viewer col pezzo REALE + «Verifica di stampa» (semaforo umanizzato)
           + Scarica il file di stampa (STL) + Guida per chi stampa
           + CTA: «Salva questo modello nel tuo archivio — registrati»
```

- Il viewer 3D è protagonista in tutti e tre i passi (≥ 50 % larghezza utile
  desktop, above-the-fold a 1280×800).
- Nessun parametro esposto oltre l'input essenziale: dimensioni, spessori,
  rese, NFC restano ai valori del preset. Quota 5/h e retention 24 h
  invariate; il limite, quando scatta, si comunica col linguaggio del
  glossario.
- Dove oggi c'è «Personalizza questo formato»: card **«Sblocca lo Studio
  completo»** con 2 benefici + link alla pagina promo. Mai modal, mai vicolo
  cieco.

## 2. Registrato — lo Studio completo

- Stesso ingresso; «Personalizza questo formato» attivo apre lo **Studio**:
  il parametrico esistente riorganizzato in sezioni con progressive
  disclosure — in vista: formato, dimensione, fronte/retro, resa (card),
  NFC; chiuso di default: **«Impostazioni di stampa avanzate»** (qualità di
  stampa/ugello, strati, adattamento misura reale, spessore tag) con nota
  «per chi stampa in proprio o per il service».
- Dashboard ristilata sui token: archivio, duplica, loghi, QR salvati.
  Le funzioni non cambiano.

## 3. Pagina promo — `/studio` (rotta nuova, R-4)

Benefici **veri**, niente contatori inventati: (1) archivio dei modelli,
(2) duplica in serie per chi produce per più clienti, (3) loghi e QR salvati,
(4) personalizzazione completa. Screenshot reale della dashboard. CTA
registrati/accedi. Raggiunta da: card «Sblocca lo Studio», CTA sul risultato,
nav.

## 4. Record ospite esistenti (creati prima del restyle)

- La pagina di stato (`menu-tags.show`) resta raggiungibile: viewer, verifica
  di stampa, download e guida **funzionano invariati** (link firmati 24 h).
- «Duplica e modifica» per l'ospite → CTA registrazione contestuale («per
  riaprire e modificare questo modello serve l'archivio: registrati — i tuoi
  modelli ti seguiranno»), mai errore. Gate server-side, test dedicato (R-2).
- Alla registrazione la migrazione ospite→utente esistente porta con sé i
  record: il copy la promette perché il codice la mantiene già.
