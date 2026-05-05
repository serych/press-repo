# ToDo a fíčury

## Sprint 8: výstupní úložiště hotových fotografií - ukončeno

Cíl sprintu byl rozšířit presscentrum z jednosměrného vstupu fotek na kompletní workflow od pořízení snímku přes FTP upload, výběr fotoeditorem, nahrání hotové fotky a stažení žurnalistou. Sprint 8 považujeme za uzavřený.

### Databáze a migrace

- Doplněno `photos.captured_at` pro čas pořízení z EXIFu.
- Doplněno `photos.downloaded_by_user_id`; `downloaded_at` je čas prvního stažení originálu fotoeditorem a další downloady ho nepřepisují.
- Přidány tabulky `published_photos` a `published_photo_log`.
- Přidán počet stažení publikované fotky `published_photos.download_count`.
- Přidány sloupce pro preview publikovaných fotek `preview_filename`, `preview_filepath`.
- Přidána blokace nepoužitelných originálů:
  - `photos.is_blocked`,
  - `photos.blocked_by_user_id`,
  - `photos.blocked_at`.
- Aktualizováno `schema.sql`.
- Aplikované sprintové migrace:
  - `sql/sprint8_001_foundation.sql`,
  - `sql/sprint8_002_published_download_count.sql`,
  - `sql/sprint8_003_photo_blocking.sql`.

### Workflow Originálů

- Watcher ukládá čas pořízení `captured_at` z EXIFu.
- Watcher generuje dvě verze náhledů originálů:
  - detailní preview pro `photo.php`,
  - malé `-small` preview pro `photos.php` a `photos-status.php`.
- Existing preview soubory byly zpětně doplněny o malé náhledy.
- `photos.php` je bez paginace a zobrazuje kontinuální seznam fotek.
- `photos.php` umí řazení podle:
  - času uploadu do systému,
  - času pořízení z EXIFu.
- `photos.php` zobrazuje počet fotek:
  - celkem,
  - nebo `x z celkových y` při použití filtrů.
- Detail `photo.php` zobrazuje workflow časy ve formátu `mm:ss` / `hh:mm:ss`:
  - `Vyfocení -> Nahrátí`,
  - `Nahrátí -> Stažení`,
  - `Stažení -> Publikace`,
  - `Workflow celkem`.
- Detail `photo.php` má procházení předchozí/následující fotky podle aktuálních filtrů a řazení z `photos.php`.
- Kliknutí na fotku v `photo.php` otevírá čistý JPG náhled v nové kartě.
- Fotoeditor může v detailu `photo.php` fotku zablokovat/odblokovat.
- Zablokovaná fotka:
  - je viditelně označená jako `zablokováno`,
  - nejde zamknout k downloadu,
  - nejde stáhnout individuálně,
  - nejde stáhnout hromadným downloadem,
  - při zablokování se případný lock uvolní.

### Upload Hotových Fotek

- Vytvořena stránka `published-upload.php` pro upload hotových fotek fotoeditorem.
- Upload je dostupný také jako malé upload okno v pravém panelu `photos.php`.
- Upload podporuje:
  - výběr souborů tlačítkem,
  - drag & drop,
  - progress bar,
  - procenta,
  - počítadlo nahrávaných souborů typu `3/5`.
- Povolené jsou pouze JPG/JPEG soubory.
- Hotové fotky se ukládají do `/var/www/press/published/<event-slug>/`.
- Eventový adresář pro hotové fotky vzniká lazy až při prvním uploadu.
- Automatické párování funguje podle názvu:
  - nový název hotové fotky musí obsahovat původní název bez přípony,
  - kontrola je case insensitive,
  - jednoznačná shoda uloží `source_photo_id`,
  - nespárovaná fotka se uloží do DB se `source_photo_id = NULL`.
- Výsledky uploadu barevně rozlišují spárované a nespárované fotky.
- Při uploadu hotové fotky se generují dvě statické preview verze:
  - detailní `*-preview.jpg`,
  - malé `*-small.jpg` pro Galerii.
- Existující publikované fotky byly zpětně doplněny o preview.

### Galerie Pro Žurnalisty

- Stránka `published.php` je v menu jako `Galerie`.
- Galerie je dostupná všem přihlášeným rolím.
- Pro roli `journalist` je Galerie výchozí stránka po přihlášení.
- Žurnalista nemá přístup k chatu ani přes ikonu v hlavičce, ani přímým URL/API.
- Galerie má záhlaví:
  - `Galerie - <název eventu>`,
  - popis eventu,
  - výraznou licenční poznámku a odkaz na licenční podmínky.
- Fotky jsou řazené podle času pořízení z EXIFu.
- U každé fotky se zobrazuje autor z EXIFu ve formátu `autor / Člověk a Víra`.
- Individuální stažení je hotové.
- Hromadné stažení je hotové jako postupné spuštění individuálních downloadů.
- Tlačítko `Stáhnout vše` bylo odstraněno; používá se `Vybrat vše` + `Stáhnout vybrané`.
- `published_photos.download_count` počítá stažení.
- Stav `staženo v této relaci` je session-based a zobrazuje se v Galerii i detailu publikované fotky.
- Stažení se loguje jako `downloaded` bez vazby na konkrétního žurnalistu.
- Kliknutí na náhled v Galerii otevírá detail `published-photo.php`, nikoliv download.
- Detail publikované fotky obsahuje:
  - autora,
  - čas pořízení,
  - dobu úprav,
  - počet stažení,
  - stav stažení v této relaci,
  - šipky na předchozí/následující publikovanou fotku.

### Menu a Role

- Menu bylo sjednoceno a přejmenováno:
  - `Dashboard`,
  - `Galerie`,
  - `Fotograf přehled`,
  - `Foto editace`,
  - `Uživatelé`,
  - `Eventy`,
  - `Odhlásit`.
- `Publikace fotek` už není samostatná položka v menu; plná upload stránka je dostupná přes upload box ve `photos.php`.
- Aktivní položka menu je jemně zvýrazněná.
- U odhlášení se nenápadně zobrazuje jméno přihlášeného uživatele.
- Výchozí stránky podle role:
  - žurnalista: `published.php`,
  - fotograf: `photos-status.php`,
  - fotoeditor desktop: `photos.php`,
  - fotoeditor mobil: `photos-status.php`,
  - admin/superadmin: `dashboard.php`.
- `ongoing-event.php` používá přihlášenou hlavičku a pro žurnalistu ukazuje jeho dostupné menu.

### Úklid a Archivace

- Úklid pracovních/testovacích dat maže pouze pracovní část:
  - RAW/originály,
  - jejich detailní a malé náhledy,
  - související DB záznamy.
- Archivace eventu také nechává hotovou Galerii zachovanou.
- Přidána samostatná akce v editaci eventu:
  - `Smazání hotové galerie`.
- `Smazání hotové galerie` po potvrzení maže:
  - publikované JPG,
  - detailní preview,
  - malé preview,
  - DB záznamy `published_photos`,
  - DB logy `published_photo_log`.

### Technický Úklid

- `web/assets/style.css` byl opraven na validní UTF-8/ASCII, takže už ho lze normálně patchovat.
- Serverové limity uploadu byly sladěny pro větší JPG soubory.
- Apache/PHP upload problémy a práva adresářů publikované galerie byly dořešené.

### Co Zůstává Do Dalších Sprintů

- Souhrnné dashboardové statistiky hotových fotek:
  - počet publikovaných fotek,
  - medián/maximum celkového workflow času,
  - poslední publikované fotky.
- Ruční párování nespárovaných publikovaných fotek s originálem.
- Případné reporty/exporty po eventu.

## Nápady pro další sprinty
- seznam publikovaných fotek pro jednotlivé fotografy + skript windows/mac pro označení?
- GPS souřadnice eventu a update v EXIFu
- Seřizovač času foťáků
- menu pro soubory s návody
- vylepšení chatu (pár jednoduchých smajliků)
- API do Lightroomu nebo jiného exportního workflow.
- Tabulka akcí, název, odkazy na galerie, výběr fotografů a fotoeditorů, statistika.
- vyčištění kódu, dokumentace 

