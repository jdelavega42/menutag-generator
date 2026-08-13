# MenuTag — motore geometrico Python

`engine/menutag.py` genera gli STL binari del prodotto secondo il contratto
CLI di `docs/contracts/03-motore-cli.md` (fonte di verità: nessun motore era
allegato, vale il ramo «se non è allegato» di §2.1 del prompt). Laravel
orchestra, Python calcola.

## Esecuzione

```bash
engine/.venv/bin/python3 engine/menutag.py \
  --shape square --size 58.8 --fillet 4.0 --thickness 3.0 \
  --front qr --depth 0.6 --qr-data-front https://menu.example.it/demo \
  --nfc --tag 25 --layer-height 0.1 --out /percorso/out.stl
```

Exit code: `0` successo (anche con `PRINTABILITY=warn|blocked`), `2` errore
utente parametrico (messaggio in italiano su stderr, con come rientrare nei
limiti), `3` errore interno di integrità (loggato, mai esposto).

Virtualenv: `engine/.venv`, creato con `/opt/homebrew/bin/python3.12`;
dipendenze pinnate in `engine/requirements.txt`.

## Librerie scelte (motivazione, §2.3–§2.4)

| Libreria | Perché |
|---|---|
| `shapely` | algebra 2D esatta su poligoni (offset, contenimento, metriche): la pipeline è geometria 2D, mai CSG su mesh — un QR v6 ha ~1.700 moduli e le booleane 3D degenerano proprio sui contatti in un vertice. |
| `segno` | unica QR library che FORZA versione, livello EC e maschera in byte mode senza segmentazione e li rilegge dal simbolo prodotto. |
| `manifold3d` | triangolatore di poligoni esatto (nessun punto di Steiner, ogni lato di bordo coperto una volta sola): tappi orizzontali che combaciano con le pareti vertice per vertice. |
| `trimesh` | assemblaggio mesh, verifiche watertight/manifold/winding/volume e export STL binario. |
| `opencv-python-headless` | tracciamento contorni dei PNG (raster → vettore SOLO per i PNG) e decodifica geometrica del QR dalla faccia rasterizzata (§8.2). |
| `svgelements` | parsing SVG vettore→vettore (curve appiattite entro il budget di corda 0.05 mm): rasterizzare un SVG butterebbe via l'input migliore. |
| `numpy` | matematica array per mesh e raster. |

## Costanti di dominio

`mtengine/constants.py` è una **replica documentata** di
`config/product.php` e `config/printers.php` (contratto 05): ogni modifica lì
va specchiata qui; la parità è coperta dai test di confine WS-6 (verificata
localmente: URL da 64/65 byte → v7/v8 identici tra tabella ISO e segno).

## Architettura della pipeline

1. **`params.py`** — parsing CLI (argparse con errori in italiano, exit 2).
2. **`validation.py`** — riverifica V1–V12 del DTO (difesa in profondità,
   stesse formule e costanti del lato PHP): minimi di prodotto sul nominale,
   pavimenti QR per forma+URL, budget di spessore, tasca NFC calcolata
   (mai costante), pianta minima sulla dimensione **effettiva**
   `size + 2×xy_comp`, range layer per ugello, piastra e compensazione.
3. **`qrsym.py`** — versione byte-mode minima dalla tabella ISO, passo del
   modulo allineato per difetto alla larghezza di estrusione con **tolleranza
   esplicita 1e-9** (`floor(1.2/0.4) = 3`), moduli come **rettangoli
   analitici dilatati di 0.005 mm** (mai raster), margine auto dalle formule
   §3.4, finestra logo con canale di guardia ≥ 1.2 passate, decodifica
   geometrica con OpenCV (retro specularizzato → raster ribaltato).
4. **`logo.py`** — SVG vettore→vettore (fill even-odd via polygonize);
   PNG con soglia Otsu + `findContours` (gerarchia a due livelli → fori).
   Semplificazione con corda < 0.05 mm **solo** sui contorni del logo;
   chiusura morfologica da 0.005 mm contro i contatti in un solo vertice.
5. **`solid.py`** — il pezzo come pila di lastre prismatiche descritte per
   **composizione** (base − scavi + rilievi): ogni quota critica cade sul
   reticolo `(z − primo_layer)/layer_height ∈ ℤ` con tolleranza 1e-9; quote
   fuori reticolo vengono adeguate e segnalate con `WARNING`. Tasca NFC
   ancorata in alto (riferimento validato 58.8×3.0: tasca 1.0–2.0,
   `PAUSE_Z=2.0`, `PAUSE_LAYER=19` di 29).
6. **`meshing.py`** — pareti e tappi costruiti dagli **stessi anelli
   normalizzati** (niente overlay booleani nel percorso mesh: rinodano i
   vertici collineari e rompono il watertight). Triangolazione tappi con
   manifold3d; riparazione degli sliver collineari con edge-flip; verifica
   §8.2: watertight, manifold, winding, volume>0, facce degeneri, scarto
   |mesh − analitico| < 1e-3 mm³, sole facce prismatiche (regola sbalzi 45°
   soddisfatta per costruzione, soffitto tasca NFC escluso com'è inteso).
7. **`metrics.py`** — apertura morfologica su pieno E complemento (elemento
   quadrato/mitre: un elemento a disco conterebbe l'arrotondamento degli
   spigoli dei moduli come area persa), residuo dopo il primo perimetro con
   larghezza mediana; soglie: warn > 2 %, blocked > 10 % o `QR_DECODED=no`.
8. **`cli.py`** — orchestrazione, piastra `--plate N` (griglia quasi
   quadrata, passo bbox+5 mm, centrata, bbox verificato: blocco > 180 mm,
   warning > 175 mm), export STL binario, **rilettura da file** e riverifica
   watertight/manifold, report `CHIAVE=VALORE`.

## Scelte dichiarate (in aggiunta ai contratti)

- **Gioco assiale tasca NFC = 0.20 mm** (§3.3, adottata come richiesto): il
  tag non fa da pavimento al primo layer di chiusura; il layer sopra la tasca
  attraversa 0.20 mm d'aria. Costo: un layer interno irregolare e invisibile.
  Alternativa scartata: ugello che sbatte su un tag più spesso del dichiarato
  = stampa persa. La profondità della tasca è arrotondata per eccesso al
  layer, quindi il gioco reale è ≥ 0.20 mm.
- **Margine auto per facce solo-logo**: 5 % della dimensione utile della
  faccia, minimo 1.0 mm (le facce QR usano le formule quiet-zone di §3.4).
- **Riquadro logo nel QR**: lato = 24 % dello span del simbolo (EC H regge
  l'erosione; l'arbitro finale è la decodifica geometrica §8.2). I moduli
  toccati dal logo + canale vengono rimossi INTERI: nessuno sliver parziale.
- **Soglie inlay e QR**: l'apertura morfologica usa 4×ugello sul contenuto
  logo in `inlay` (parete base + riempimento accento, §3.6) ma 2×ugello sui
  moduli QR: §3.6 dichiara che la soglia dimensionale del QR resta quella di
  §3.2 (passo ≥ 1.2 mm, indipendente dal contrasto), e il passo è già
  vincolato dal pavimento di prodotto.
- **`WEIGHT_G`** = peso del solido pieno (base + accento) alla densità del
  materiale scelto (PLA 1.24, PETG 1.27 g/cm³): limite superiore rispetto
  alla stampa con infill.
- **Rilievo sul retro**: geometricamente supportato ma segnalato con
  `WARNING` (il pezzo appoggia sul piatto solo sulla grafica).
- **`--xy-comp`** sposta solo la silhouette esterna (cerchio: raggio ±c;
  quadrato: lato ± 2c con smusso adeguato); le validazioni NFC usano la
  dimensione effettiva, i minimi di prodotto e i pavimenti QR il nominale
  (decisioni §2).

## Roadmap (dichiarata, non implementata — §2.5)

Composizione logo + QR + testo sulla stessa faccia; etichette incise nel
margine (`--label-front` / `--label-back`); export 3MF con profilo di stampa.
