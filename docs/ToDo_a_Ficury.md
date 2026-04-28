# ToDo a fíčury

## Sprint 8: výstupní úložiště hotových fotografií

Cíl sprintu: rozšířit presscentrum z jednosměrného vstupu fotek na kompletní workflow od pořízení snímku přes FTP upload, výběr fotoeditorem, nahrání upravené hotové fotky a stažení žurnalistou.

### Databáze

- Přidat čas pořízení originálu do `photos`:
  - `captured_at DATETIME NULL` načtený z EXIFu (`DateTimeOriginal`, případně záložní tagy).
  - Index podle `event_id, captured_at` kvůli statistikám průběhu eventu.
- Zpřesnit existující časové body u originálu:
  - `uploaded_at` ponechat jako čas nahrání originálu do presscentra.
  - `downloaded_at` používat jako čas prvního stažení originálu fotoeditorem.
  - Doplnit `downloaded_by_user_id INT UNSIGNED NULL`, aby bylo jasné, který fotoeditor originál převzal.
- Přidat tabulku hotových fotografií, např. `published_photos`:
  - `id`, `event_id`, `source_photo_id NULL`, `uploaded_by_user_id`, `filename`, `filepath`, `filesize`, `width`, `height`, `checksum`, `captured_at NULL`, `source_uploaded_at NULL`, `editor_downloaded_at NULL`, `published_at`, `status`.
  - `source_photo_id` propojí hotovou fotku s originálem, pokud ji dokážeme spárovat podle názvu nebo metadat.
  - `status`: minimálně `ready`, `hidden`, `deleted`.
- Přidat log pro hotové fotky, např. `published_photo_log`:
  - akce `uploaded`, `downloaded`, `hidden`, `deleted`.
- Přidat oprávnění:
  - `published_photos.upload` pro fotoeditory.
  - `published_photos.view` a `published_photos.download` pro žurnalisty.
  - admin/superadmin vše.

### Párování hotové fotky s originálem

- Primární pravidlo: hotový soubor může mít upravený název, ale měl by zachovat rozpoznatelný základ originálu.
- Navržený postup pro první verzi:
  - při uploadu hotové fotky číst EXIF `DateTimeOriginal` a autora, pokud existují;
  - z názvu odstranit běžné suffixy typu `_edit`, `_upraveno`, `-final`, exportní číslování apod.;
  - hledat kandidáty mezi fotkami stejného eventu podle podobného názvu, stejného fotografa a blízkého času pořízení;
  - pokud je shoda jednoznačná, uložit `source_photo_id`;
  - pokud není shoda jistá, uložit hotovou fotku bez vazby a nabídnout ruční spárování později.

### Backend a logika

- Watcher originálů:
  - doplnit načtení `captured_at` z EXIFu při prvním zpracování originálu.
- Stažení originálu fotoeditorem:
  - při prvním stažení uložit `downloaded_at` a `downloaded_by_user_id`.
  - další stažení už nemá přepsat první čas převzetí.
- Upload hotových fotek:
  - nová stránka/API pro fotoeditory v rámci eventu.
  - ukládat do adresáře např. `/var/www/press/published/<event_id>/`.
  - vygenerovat náhled hotové fotky, ideálně do samostatného stromu preview.
- Stažení hotových fotek žurnalistou:
  - nová galerie hotových fotek dostupná roli `journalist`.
  - logovat stažení kvůli auditu.
- Statistiky:
  - `captured_at -> uploaded_at`: cesta od foťáku/SD karty do presscentra.
  - `uploaded_at -> downloaded_at`: čekání na převzetí fotoeditorem.
  - `downloaded_at -> published_at`: práce fotoeditora.
  - `captured_at -> published_at`: celkový čas od vyfocení po publikaci.

### UI

- Do detailu originální fotky přidat čas pořízení a čas převzetí fotoeditorem.
- Do dashboardu eventu přidat blok „Hotové fotografie“:
  - počet nahraných hotových fotek;
  - medián/maximum celkového času zpracování;
  - poslední nahrané hotové fotky.
- Přidat stránku pro fotoeditory: upload hotových fotek.
- Přidat stránku pro žurnalisty: přehled a stažení hotových fotek.
- V administraci eventu ponechat možnost přiřazovat žurnalisty, až bude role aktivně používaná.

### Navržené menší kroky implementace

1. Databázový základ a oprávnění - hotovo
   - Přidat `captured_at`, `downloaded_by_user_id`, tabulky `published_photos` a `published_photo_log`, nová oprávnění.
   - Migrovat DB a doplnit `schema.sql`.

2. Čas pořízení u originálů - hotovo
   - Upravit watcher, aby ukládal `captured_at`.
   - Doplnit backfill skript pro existující fotky.
   - Zobrazit čas pořízení v detailu fotky.

3. Převzetí originálu fotoeditorem - hotovo
   - Upravit download originálu tak, aby uložil první `downloaded_at` a `downloaded_by_user_id`.
   - Ošetřit hromadný download stejně jako jednotlivý.

4. Upload hotových fotek
   - Vytvořit stránku/API pro upload hotových fotek fotoeditorem.
   - Uložit soubor, zapsat DB, vygenerovat náhled, zkusit automatické spárování s originálem.

5. Galerie hotových fotek pro žurnalisty
   - Zapnout práva `published_photos.view/download` pro roli `journalist`.
   - Přidat přehled hotových fotek, detail a stažení.
   - Logovat stažení.

6. Statistiky a dashboard
   - Dopočítat časové metriky po fotkách a souhrnně po eventu.
   - Doplnit dashboard eventu a případně export/report.

7. Ruční párování a korekce
   - Přidat jednoduchý admin/fotoeditor nástroj pro ruční propojení hotové fotky s originálem, pokud automatika selže.

## Obecné nápady

- Úklid presscentra a rozhodnutí, jak dlouho držet originály a hotové fotky.
- Jednoduchý chat v rámci presscentra.
- API do Lightroomu nebo jiného exportního workflow.
- Tabulka akcí, název, odkazy na galerie, výběr fotografů a fotoeditorů, statistika.
- Přidat telefon a další kontaktní kanály do záznamů uživatelů a zobrazovat je na dashboardu eventu.

## Starší TODO

- Runner v eventu jako checkbox místo radio buttonu.
- Tabulky fotografů a fotoeditorů v eventu, filtr podle rolí a výběr našeptávačem.
- Trvalé přihlášení, zejména na telefonu.
