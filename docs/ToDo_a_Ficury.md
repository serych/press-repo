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

- Stránka `galerie.php` je v menu jako `Galerie`.
- Galerie je dostupná všem přihlášeným rolím.
- Pro roli `journalist` je Galerie výchozí stránka po přihlášení.
- Žurnalista nemá přístup k chatu ani přes ikonu v hlavičce, ani přímým URL/API.
- Galerie má záhlaví:
  - `Galerie - <název eventu>`,
  - popis eventu,
  - výraznou licenční poznámku a odkaz na licenční podmínky.
- Fotky jsou řazené podle času pořízení z EXIFu.
- U každé fotky se zobrazuje autor ve formátu `autor / Člověk a Víra`.
- Autor publikované fotky je uložený v `published_photos.author_label`, aby Galerie při vykreslení nespouštěla `exiftool` pro každou fotku.
- Galerie nezobrazuje pracovní stavy RAW uploadu/zpracování z editor části systému.
- V záhlaví Galerie se zobrazuje výrazná informační věta `Fotoeditoři právě pracují na X fotografiích`, ale jen pokud je `X > 0`.
- `X` znamená počet zdrojových RAW/JPG fotek, které:
  - si fotoeditor stáhl,
  - ještě k nim není publikované hotové JPG ve stavu `ready`,
  - stažení proběhlo během posledních 15 minut.
- Pro jednu fotku se používá tvar `Fotoeditoři právě pracují na 1 fotografii`.
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
- Jméno přihlášeného uživatele se nezobrazuje trvale; je dostupné jako bublina při hover/focus na `Odhlásit`.
- Výchozí stránky podle role:
  - žurnalista: `galerie.php`,
  - fotograf: `photos-status.php`,
  - fotoeditor desktop: `photos.php`,
  - fotoeditor mobil: `photos-status.php`,
  - admin/superadmin: `dashboard.php`.
- `info.php` používá přihlášenou hlavičku a pro žurnalistu ukazuje jeho dostupné menu.

## Sprint 9: drobné opravy, dashboard, galerie, GPS metadata - ukončeno

Sprint 9 řešil hlavně zpřehlednění žurnalistických a dashboardových obrazovek, zrychlení Galerie, GPS metadata a bezpečnější správu eventu. Sprint 9 považujeme za uzavřený.

### Žurnalistické Info

- Původní `ongoing-event.php` bylo přejmenováno na `info.php`.
- Ze stránky byly odstraněny položky:
  - `Cloudový disk`,
  - `Veřejný dashboard`,
  - samostatný nadpis `Probíhající událost`.
- Položka `Galerie Člověk a Víra` zůstává.
- Opraveno zobrazení `Vedoucí eventu` včetně telefonu; problém byl v kolizi proměnné s hlavičkou.
- Odkazy v loginu a hlavičce vedou na nové `info.php`.

### Galerie

- Původní `published.php` bylo přejmenováno na `galerie.php`.
- Starý produkční vstup `published.php` byl odstraněn.
- Menu, výchozí stránka žurnalisty a zpětný odkaz z detailu publikované fotky používají `galerie.php`.
- Výkon Galerie byl optimalizován:
  - dříve se autor každé fotky načítal při renderu přes `exiftool`,
  - nově se autor ukládá do `published_photos.author_label`,
  - existující publikované fotky byly zpětně doplněny.
- Přidána migrace `sql/sprint9_002_published_author_label.sql`.

### Dashboard

- Samostatný nadpis `Dashboard` byl odstraněn.
- Název eventu se zobrazuje jako `<název eventu> - dashboard`.
- Souhrn používá nové popisky:
  - `Upload od fotografů`,
  - `Publikováno`.
- `Publikováno` ukazuje počet hotových fotek v Galerii.
- Přidány workflow statistiky z publikovaných fotek:
  - minimum,
  - medián,
  - maximum.
- Přidány velké digitální hodiny ve formátu `HH:mm:ss`.
- Hodiny jsou inicializované ze serverového času a zpřesněné pomocí `performance.navigation.responseStart`.
- Přidáno volitelné pípání hodin:
  - zapnutí vyžaduje potvrzení,
  - pípá 5 sekund před hranami `00`, `15`, `30`, `45` sekund,
  - krátké pípnutí má 250 ms,
  - poslední pípnutí na hraně má 500 ms,
  - při opuštění dashboardu se checkbox automaticky vypne.

### Hlavička

- Text značky změněn na `PRESS centrum ČaV`.
- Jméno přihlášeného uživatele je schované v tooltipu odhlašovacího odkazu.

### Eventy a Cloud

- Položka `Cloudový disk` byla odstraněna z dashboardů, create/edit formulářů i z databáze.
- Sloupec `events.cloud_url` byl odstraněn.
- Přidána migrace `sql/sprint9_001_remove_event_cloud_url.sql`.

### GPS a EXIF

- Do tabulky `events` byly přidány GPS hodnoty pro zápis do EXIFu:
  - `gps_latitude`,
  - `gps_latitude_ref`,
  - `gps_longitude`,
  - `gps_longitude_ref`,
  - `gps_altitude`,
  - `gps_altitude_ref`.
- Přidána migrace `sql/sprint9_003_event_gps_exif.sql`.
- Přidána migrace `sql/sprint9_004_event_gps_altitude.sql`.
- `event-create.php` a `event-edit.php` používají jedno pole `GPS souřadnice`.
- Formulář přijímá mapový formát, např. `49.1896308N, 16.5751786E`.
- Nadmořská výška se zadává samostatně v metrech; záporná hodnota se uloží jako hodnota pod hladinou moře.
- Hodnota se při uložení převádí na EXIF DMS formát:
  - `GPSLatitude`,
  - `GPSLatitudeRef`,
  - `GPSLongitude`,
  - `GPSLongitudeRef`,
  - `GPSAltitude`,
  - `GPSAltitudeRef`.
- Při editaci se uložený EXIF formát převádí zpět na mapový desetinný zápis.
- Watcher při prvním zpracování nové fotky:
  - přepisuje autora podle uživatele,
  - přepisuje copyright podle uživatele,
  - nastavuje copyright status,
  - zapisuje `Title = via PRESS centrum`,
  - pokud fotka nemá GPS a event GPS má, doplní GPS do EXIFu,
  - pokud fotka nemá nadmořskou výšku a event ji má, doplní `GPSAltitude` a `GPSAltitudeRef`.
- Pokud fotka GPS v EXIFu už má, watcher ji nepřepisuje.
- Opravena duplikace autora v EXIF/XMP:
  - watcher už nezapisuje autora současně přes obecný `Creator` i `XMP-dc:Creator`,
  - před zápisem čistí listová pole `XMP-dc:Creator` a `IPTC:By-line`,
  - nové a znovu zpracované soubory tak nedostanou autora ve tvaru `Autor; Autor`.
- Runner workflow bylo zkontrolováno:
  - runner upload hledá autora podle EXIF autora a pole `users.exif_author`,
  - po nalezení autora přesune soubor z adresáře runnera do FTP adresáře autora,
  - následně provede standardní EXIF zápis podle nalezeného autora.
- Porovnání runner autora je po normalizaci volnější, aby prošel i širší EXIF text obsahující očekávaný autor string.

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

- Ruční párování nespárovaných publikovaných fotek s originálem.
- Přehled posledních publikovaných fotek na dashboardu.
- Další ruční nástroje pro práci se soubory a eventem.

## Sprint 10: seznamy použitých fotek a eventové exporty - ukončeno

Cíl sprintu byl doplnit výstupy po eventu: seznam použitých fotografií pro jednotlivé fotografy a celkovou přehledovou tabulku eventu. Sprint 10 považujeme za uzavřený.

### Seznam Použitých Fotek Pro Fotografa

- Na `photos-status.php?scope=mine` byl přidán blok `Použité fotografie`.
- Fotograf vidí počet svých publikovaných fotek v aktuální galerii.
- Tlačítko `Generovat seznam použitých fotografií` načte aktuální data z endpointu `api/used-photos.php`.
- Výstup je čárkou oddělený seznam původních názvů bez přípon, např. `DSC_2654, JSZ_5813`.
- Seznam obsahuje jen původní FTP uploady, které mají spárovanou publikovanou fotku ve stavu `ready`.
- Tlačítko `Kopírovat` ukládá seznam do schránky pro vložení do Lightroom filtru.
- Logika je v `photos_used_original_basenames_for_photographer()`.

### Konzistentní Stavy Fotek

- Statusy fotek byly sjednoceny pro:
  - `photos.php`,
  - `photo.php`,
  - `photos-status.php`,
  - API feedy.
- Česká pojmenování stavů:
  - `uploaded` -> `nahráno`,
  - `processing` -> `zpracování`,
  - `ready` -> `připraveno`,
  - `locked` -> `zamknuto`,
  - `downloaded` -> `staženo`,
  - `error` -> `chyba`.
- Pokud má původní fotka publikovanou verzi v Galerii, zobrazuje se stav `publikováno`.
- Pro `publikováno` byla přidána samostatná barva `status-published`.
- Společná logika je v `photos_display_status()`.

### Přehledová Tabulka Eventu

- Na `event-edit.php` byla přidána tlačítka:
  - `Stáhnout přehled fotek Excel`,
  - `Stáhnout přehled fotek CSV`.
- Export obsluhuje endpoint `event-report-download.php`.
- Excel export je generovaný jako Excel-kompatibilní `.xls` HTML tabulka.
- CSV export je oddělený středníkem a uložený ve `Windows-1250`, aby ho český Excel otevřel se správnou diakritikou.
- CSV obsahuje řádek `sep=;`.
- Přehled obsahuje všechny z databáze známé údaje o fotkách eventu:
  - ID fotky,
  - původní název,
  - autor,
  - FTP účet autora,
  - zda nahrál autor nebo runner,
  - stav fotky,
  - čas pořízení z EXIFu,
  - čas nahrání do press centra,
  - čas stažení fotoeditorem,
  - jméno fotoeditora,
  - čas publikace do galerie,
  - název publikované fotky,
  - celkový počet stažení z galerie,
  - EXIF problém,
  - poznámka EXIF.
- Pokud má jedna původní fotka více publikovaných verzí, export obsahuje více řádků, aby nezanikly názvy a počty stažení jednotlivých publikovaných fotek.
- Pokud je publikovaná fotka nespárovaná s originálem, export ji přesto zahrne s dostupnými údaji z `published_photos`.
- Sloupec `Stav fotky` vypisuje `publikováno`, pokud řádek obsahuje publikovanou fotku.
- Exportní logika je v `events_report_rows()` a `events_report_table()`.

### Jednoznačné Rozlišení Autor / Runner

- Do tabulky `photos` byl přidán sloupec `uploaded_by_role ENUM('author', 'runner')`.
- Přidána migrace `sql/sprint10_001_photo_upload_origin.sql`.
- Watcher nově zapisuje `uploaded_by_role` přímo při ingestu.
- Běžný FTP upload se ukládá jako `author`.
- Upload přes runnera se ukládá jako `runner`, i když watcher následně podle EXIF autora přesune soubor do adresáře skutečného fotografa.
- Starší data byla zpětně doplněna nejlepším dostupným odhadem podle toho, zda byl aktuálně přiřazený fotograf označený jako runner.
- Přehledový export eventu používá `photos.uploaded_by_role`, nikoliv zpětné hádání přes soupisku eventu.

### Časové Pásmo Eventu

- Do tabulky `events` byl přidán sloupec `timezone`.
- Přidána migrace `sql/sprint10_002_event_timezone.sql`.
- Výchozí časové pásmo je `Europe/Prague`.
- `event-create.php` a `event-edit.php` mají volbu `Jiné časové pásmo` s výběrem ze světových PHP timezone identifikátorů.
- Dashboardové hodiny se inicializují podle časového pásma nastaveného u aktuálního eventu.
- Na dashboardu je časové pásmo zobrazené u hodin, aby bylo jasné, podle čeho se seřizují foťáky.

### Jeden Aktivní Event

- Při zakládání nebo editaci eventu se kontroluje, zda už není aktivní jiný běžný event.
- Pokud jiný aktivní event existuje, formulář zobrazí potvrzení:
  - `V současnosti je aktivní event ... Mám ho deaktivovat?`
- Po potvrzení se původní aktivní event nastaví na `finished` a ukládaný event se může stát aktivním.
- Společná logika je v:
  - `events_get_other_active()`,
  - `events_deactivate_other_active()`.

### Nápověda

- Přidán jednoduchý systém PDF nápovědy bez redakčního systému.
- Přidána migrace `sql/sprint10_003_help_documents.sql`.
- PDF soubory se ukládají mimo webroot do `/var/www/press/help`.
- Metadata dokumentů jsou v tabulce `help_documents`.
- Tabulka obsahuje i `sort_order`, podle kterého se řídí pořadí zobrazení.
- Stránka `help.php` je dostupná v menu jako `Nápověda`.
- Nápověda je viditelná všem přihlášeným rolím kromě `journalist`.
- Správa nápovědy je dostupná adminům přes oprávnění `users.manage`.
- Admin může:
  - přidat PDF,
  - přejmenovat záznam,
  - nahradit PDF novou verzí,
  - smazat dokument,
  - posunout dokument nahoru/dolů.
- Uživatelé mohou PDF:
  - zobrazit v browseru přes `Content-Disposition: inline`,
  - stáhnout přes `Content-Disposition: attachment`.
- Stahování i zobrazování obsluhuje `help-download.php`, takže soubory zůstávají chráněné mimo webroot.

### Náhrada FTP Uploadu Přes HTTPS

- Přidána stránka `ftp.php` s názvem `Náhrada FTP uploadu`.
- Stránka je dostupná všem přihlášeným rolím mimo žurnalisty; role `press_operator` má nově doplněné i oprávnění `ftp.upload`.
- Upload je graficky odlišený od uploadu hotových fotek do galerie.
- Podporuje stejné zdrojové formáty jako watcher:
  - RAW formáty `CR2`, `CR3`, `NEF`, `NRW`, `ARW`, `SR2`, `SRF`, `RAF`, `RW2`, `ORF`, `DNG`, `PEF`, `IIQ`, `3FR`,
  - `JPG` a `JPEG`.
- Soubory se nejdřív ukládají do `/var/www/press/upload-tmp`.
- Po dokončení uploadu se soubor atomicky přesune do FTP adresáře aktuálního uživatele.
- Watcher pak soubor zpracuje stejnou cestou jako běžný FTP upload.
- Pokud je uživatel v aktuálním eventu označený jako runner, zůstává zachovaná runner logika watcheru.
- Společná logika je ve `web/inc/ftp_replacement.php`.
- Implementace je připravená tak, aby šlo později nahradit jednoduchý POST upload chunkovaným uploadem bez změny watcher workflow.
- Položka v menu se jmenuje `NeFTP upload`, aby bylo jasné, že nejde o klasický FTP protokol.
- Vícenásobný upload se v browseru posílá postupně po jednom souboru, takže limit se vztahuje na jednotlivou fotku a ne na součet celé dávky.
- Produkční upload limity byly nastaveny na:
  - `upload_max_filesize = 96M`,
  - `post_max_size = 120M`.

### Drobné Provozní Úpravy Po Sprintu 10

- Dashboard `dashboard.php`:
  - u tabulky fotografů byly sloupce přejmenovány na `Nahráno` a `Publikováno`,
  - `Nahráno` počítá fotky autora v daném eventu včetně fotek nahraných přes runnera,
  - `Publikováno` počítá hotové fotky ve stavu `ready` napojené na originály daného fotografa.
- Přehled fotek `photos.php`:
  - řazení podle uploadu i podle času pořízení má jako výchozí nejnovější fotky nahoře,
  - přidán přepínač `reverzně (nejnovější dole)`,
  - reverzní řazení se drží i v AJAX feedu a v detailu `photo.php` pro předchozí/následující fotku.
- Editace eventu `event-edit.php`:
  - hlavní nadpis je nově `<název eventu> - úprava`,
  - při odchodu ze stránky s neuloženými změnami se zobrazí potvrzení `Odejít bez uložení změn?`,
  - při zavření nebo reloadu panelu funguje standardní browserové upozornění.
- Zakládání uživatele `user-create.php`:
  - hesla se po chybě formuláře nevrací do HTML hodnoty pole,
  - citlivá pole mají potlačené autocomplete pro nové přihlašovací údaje,
  - přidán endpoint `api/user-login-check.php` pro průběžnou kontrolu obsazenosti loginu,
  - login se při psaní barevně označuje podle dostupnosti,
  - mobilní číslo se při psaní automaticky zpřehledňuje mezerami bez automatického doplňování `+420`.

## Sprint 11: krátký přístup do galerie a zabezpečení press centra - ukončeno

Cílem Sprintu 11 bylo zjednodušit přístup žurnalistů k hotové galerii konkrétního eventu a doplnit provozní vypínač FTP části press centra přes pfSense. Sprint 11 považujeme za uzavřený.

### Krátký Přístup Pro Žurnalisty

- Role `journalist` zůstává zachovaná, ale praktický provozní přístup pro lidi na eventu nově řeší krátký eventový odkaz.
- V systému může zůstat jeden běžný uživatel s rolí `journalist` jako fallback klasického loginu.
- Pro každý event lze vygenerovat krátký galerijní odkaz ve tvaru:
  - `https://press.clovekavira.cz/g/a7c`
- Prefix `/g/` znamená galerie.
- Token má 3 znaky z malé abecedy a číslic.
- Generování hlídá unikátnost tokenu.
- Odkaz je určený pro vytištění, opsání a QR kód.
- Na serveru byl zapnutý Apache modul `rewrite`, aby krátká URL `/g/<token>` fungovala přímo.

### Databáze Pro Galerijní Přístupy

- Přístupy mají samostatnou tabulku, nikoliv jen další sloupce v `events`.
- Přidána a aplikována migrace `sql/sprint11_001_gallery_access.sql`.
- Vytvořena tabulka `event_gallery_access`, která uchovává:
  - `event_id`,
  - krátký `token`,
  - zda je přístup povolený,
  - volitelný PIN/heslo uložené jako hash,
  - počet dní po eventu, kdy se galerie automaticky uzavře,
  - datum/čas expirace,
  - metadata vytvoření a poslední změny.
- Vytvořena tabulka `journalist_gallery_sessions` pro samostatnou evidenci žurnalistických návštěv galerie.
- Vytvořena tabulka `journalist_gallery_downloads` pro log stažení publikovaných fotek přes krátký galerijní přístup.
- Výchozí uzavření galerie je 3 dny po skončení eventu.
- V editaci eventu je jednoduchá správa:
  - zapnout/vypnout žurnalistický přístup,
  - vygenerovat krátký odkaz při prvním uložení,
  - přegenerovat krátký odkaz,
  - nastavit PIN/heslo nebo nechat přístup bez PINu,
  - nastavit počet dní po eventu,
  - zobrazit URL,
  - zobrazit nebo stáhnout QR kód,
  - zobrazit základní statistiky krátkého přístupu.
- QR kód generuje server pomocí nástroje `qrencode` jako vysoké PNG rozlišení vhodné pro tisk kartiček.

### Session A Statistiky Žurnalistů

- Přístup přes `/g/<token>` vytváří speciální žurnalistickou session svázanou s konkrétním eventem.
- Galerie a detail publikované fotky u takové session zobrazují galerii eventu z tokenu, ne nutně aktuálně aktivní event.
- Implementované statistiky umožňují po eventu sledovat:
  - počet žurnalistických sessionů, které si stáhly alespoň jednu fotku,
  - celkový počet stažení fotek žurnalisty z dané galerie.
- Žurnalistické sessiony se evidují samostatně, aby šlo poznat:
  - kdy session vznikla,
  - ke kterému eventu patřila,
  - zda stáhla alespoň jednu fotku,
  - kolik stažení v ní proběhlo.
- Stávající `published_photos.download_count` zůstává jako počet stažení konkrétní fotky.
- Nová session statistika doplňuje pohled na reálný počet aktivních žurnalistických návštěv.

### Přístupová Obrazovka

- Po otevření `/g/<token>`:
  - pokud token neexistuje, je vypnutý nebo expirovaný, zobrazí se jednoduchá informace o nedostupné galerii,
  - pokud je nastavený PIN/heslo, zobrazí se minimalistický formulář pouze pro jeho zadání,
  - pokud PIN/heslo nastavené není, galerie se otevře rovnou,
  - po ověření tokenu/PINu krátký vstup nastaví anonymní žurnalistickou session a přesměruje na běžnou `galerie.php`.
- Krátký žurnalistický přístup používá stejné stránky jako klasická galerie:
  - `galerie.php`,
  - `published-photo.php`,
  - `published-preview.php`,
  - `published-download.php`.
- Veřejná žurnalistická session má v hlavičce malé menu `Dashboard` a `Galerie`.
- `Dashboard` pro krátkou session vede na `info.php` a zobrazuje event z tokenu, i když není běžně veřejný přes `is_public`.
- Kompatibilitní endpointy `g-photo.php`, `g-preview.php` a `g-download.php` už jen přesměrovávají na standardní stránky.
- Galerie umí přehled, detail, náhledy, individuální stažení a hromadné stažení přes výběr fotek.
- Stažení přes krátký galerijní přístup zvyšuje `published_photos.download_count` a zároveň zapisuje session statistiku do `journalist_gallery_sessions` a log do `journalist_gallery_downloads`.
- Stránka je maximálně jednoduchá a použitelná na mobilu.

### Provozní Úklid Serveru Během Sprintu 11

- Kořenový disk serveru byl vyčištěn z kritického stavu:
  - původně měl `/` přibližně 265 MB volných a využití kolem 94 %,
  - po úklidu má `/` přibližně 1.6 GB volných a využití kolem 63-64 %.
- Vyčištěno ve `/root/.vscode-server`:
  - staré nepoužívané verze VS Code serveru,
  - cache stažených VSIX balíčků,
  - stará duplicitní verze rozšíření GitHub Pull Request.
- Proveden standardní systémový úklid:
  - `apt autoremove` odstranil starou kernel meziverzi,
  - `apt clean`,
  - purge zbytků balíků ve stavu `rc`.
- Server byl rebootován a běží na kernelu `6.1.0-48-amd64`.
- Starší kernel `6.1.0-44-amd64` zůstává jako fallback.

### Vypínač FTP Přístupu Přes pfSense

- Druhá část Sprintu 11 doplnila provozní zabezpečení FTP přístupu přes pfSense firewall.
- Cíl: možnost centrálně zapínat/vypínat dostupnost FTP části press centra prostřednictvím API pfSense.
- Na `events.php` je přidaný horní panel `Vypínač press centra`:
  - čte reálný stav FTP pravidel přes pfSense REST API,
  - zobrazuje stav `FTP přístup je zapnutý` / `FTP přístup je vypnutý` / chybový nebo nejednotný stav,
  - nabízí potvrzovací tlačítko `Vypnout FTP` nebo `Zapnout FTP`,
  - reálné vypnutí i zapnutí bylo otestováno na živém provozu.
- pfSense REST API je dostupné na `https://192.168.50.1:10443/api/v2`.
- Lokální konfigurace API je v ignorovaném souboru `web/config/pfsense.local.php`; v repozitáři je jen šablona `web/config/pfsense.local.example.php`.
- Ovládání FTP pracuje s pravidly podle popisu, ne podle proměnlivých číselných ID.
- Přepínají se čtyři firewall rules:
  - firewall rule `FTP control press centrum`,
  - firewall rule `FTP passive press centrum`,
  - auto rule `NAT FTP control press centrum`,
  - auto rule `NAT FTP passive press centrum`.
- Stav v kombinovaném seznamu firewall rules hlídá všechna čtyři pravidla.
- Kód záměrně nepřepisuje NAT port-forward objekty, protože pfSense REST API při jejich PATCH přegenerovalo pasivní auto pravidlo jen pro port `40000`.
- Přepínač nově přepíná pouze firewall rules a u pasivních pravidel zároveň hlídá rozsah:
  - `40000:40100`.
- Ověřený výsledný zapnutý stav:
  - `FTP passive press centrum` enabled, port `40000:40100`,
  - `FTP control press centrum` enabled, port `21`,
  - `NAT FTP control press centrum` enabled, port `21`,
  - `NAT FTP passive press centrum` enabled, port `40000:40100`.
- Přepnutí probíhá přes `PATCH /firewall/rule` a následné `POST /firewall/apply`.
- V přehledu eventů `events.php` byl za sloupec `Staženo` přidán sloupec `Vystaveno`.
- `Vystaveno` ukazuje `published_total`, tedy počet publikovaných hotových fotek ve stavu `ready`.
- Stav `FTP přístup je vypnutý` je na `events.php` zobrazený červeně.
- Při vypnutém FTP se globální hlavička systému přepne na červené pozadí a text značky se změní na `PRESS centrum vypnuto`.
- Při zapnutém FTP se hlavička vrátí do běžného vzhledu `PRESS centrum ČaV`.
- Vypínač zatím řeší FTP protokol; NeFTP upload a webová galerie zůstávají dostupné.

## Debugging a opravy před B+D

Tato sekce shrnuje provozní chyby a drobné opravy vyřešené během přípravy před B+D.

### Session a dlouhé přihlášení

- Přihlášení v press centru je nastavené jako klouzavá session s cílem udržet uživatele přihlášené 24 hodin i při delší nečinnosti.
- Oprava cílila hlavně na mobilní použití fotografů, kde docházelo k rychlému odhlašování.
- Požadované chování: aktivní login má vydržet přibližně 24 hodin a session se při běžném používání prodlužuje.

### Krátké odkazy na galerii

- Opraveno chování krátkých odkazů na galerii:
  - krátký odkaz už neotevírá galerii právě aktivního eventu,
  - vždy otevírá event, pro který byl daný odkaz vygenerován,
  - funguje i pro event, který ještě není aktivní.
- Galerie nově zobrazuje stav daného eventu, například `Plánovaný`, aby bylo jasné, proč se obsah může lišit od aktivního eventu.

### Našeptávače účastníků eventu

- U výběru fotoeditorů v `event-create.php` a `event-edit.php` už našeptávač neschovává uživatele s rolí `Fotograf` úplně potichu.
- Pokud hledanému textu odpovídá fotograf, zobrazí se na konci návrhů červeně jako nepřidatelný s vysvětlením, že je nutné nejdřív změnit roli uživatele.
- U výběru fotografů je stejným způsobem ošetřený uživatel s rolí `Žurnalista`.
- Nepřidatelné položky nejdou kliknutím vybrat.
- Serverová validace zároveň brání obejití UI ručně upraveným POSTem:
  - fotograf nesmí být uložen jako fotoeditor,
  - žurnalista nesmí být uložen jako fotograf eventu.

### CSS úklid

- Proveden první konzervativní refaktoring `web/assets/style.css`.
- Soubor byl zmenšen zhruba o 271 řádků odstraněním duplicit, přepsaných pravidel a nepoužívaných starších stylů.
- Úklid byl ověřen screenshotovým porovnáním vybraných desktopových a mobilních stránek.
- Záměrně nešlo o velké přeskupení CSS, ale o bezpečnou první etapu bez změny vzhledu.

### Provozní úklid serveru

- Kořenový disk byl vyčištěn z kriticky zaplněného stavu a následně byl serverový disk navýšen.
- Odstraněno bylo zejména:
  - Chromium použité pouze pro vizuální testování,
  - starý neběžící kernel,
  - osiřelé balíčky po Chromiu,
  - dočasná screenshotová data,
  - apt cache,
  - neaktivní historické kopie VS Code serveru a rozšíření.
- Po aktualizaci VS Code byla provedena ještě jedna kontrola a odstraněny nové historické kopie VS Code serveru a cache.
- Produkční služby Apache a MariaDB byly po úklidu ověřené jako běžící.

## Opravy po Noci kostelů

Tato sekce shrnuje opravy a drobná vylepšení realizované po provozní zkušenosti z Noci kostelů.

### Chat a nepřečtené zprávy

- Opraveno číslo nepřečtených zpráv u ikonky chatu v hlavičce.
- Počet nepřečtených zpráv se nově počítá pro aktuální event, ne napříč staršími eventy.
- Endpoint pro počet nepřečtených zpráv posílá no-cache hlavičky, aby v hlavičce nezůstávalo zamrzlé staré číslo.
- Oprava řeší hlavně situace při přepínání eventů.

### Upload hotových JPG do galerie

- Hromadný upload hotových JPG ve `photos.php` a `published-upload.php` se posílá postupně po jednotlivých souborech.
- Serverový datový limit se tak vztahuje na jednu fotku, ne na součet celé dávky.
- Při výběru nebo přetažení souborů se upload tlačítko opticky rozsvítí; bez souborů je ztlumené a neaktivní.
- Po doběhnutí uploadu na 100 % se zobrazuje stav `Tvořím náhledy a páruji fotky...`, aby nebylo matoucí ticho během serverového zpracování.
- Po dokončení se progress bar resetuje a upload okno se vrací do čistého stavu.
- Informace o spárování se po úspěšném uploadu automaticky schová po krátké prodlevě.
- Při odchodu ze stránky během uploadu se zobrazí varování, že odchod upload přeruší.

### NeFTP upload

- Stejné UX chování bylo doplněno i na `ftp.php`:
  - ztlumené/aktivní upload tlačítko podle vybraných souborů,
  - reset progress baru po dokončení,
  - varování při pokusu odejít ze stránky během uploadu,
  - automatické schování seznamu úspěšně nahraných souborů po krátké prodlevě.
- Zůstává zachované blokování uploadu v situaci, kdy není připravené úložiště.

### Dashboard

- V tabulce fotoeditorů na `dashboard.php` byly doplněny statistiky:
  - kolik fotek si každý fotoeditor stáhl,
  - kolik hotových fotek publikoval.
- Statistiky navazují na již existující podobný přehled u fotografů.

### Hromadné stažení zamčených fotek

- Tlačítko `Hromadné stažení zamčených` na `photos.php` je nově stylované konzistentně se zbytkem webu.
- Pokud uživatel nemá v aktuálním přehledu žádné své zamčené fotky, tlačítko je neaktivní a nejde omylem spustit.
- Opravena stránka `bulk-download-create.php`:
  - texty jsou v UTF-8,
  - fallback hláška používá běžnou hlavičku/patičku a zapadá do vzhledu webu.

### Galerie

- V `galerie.php` se vedle nadpisu galerie zobrazuje počet právě publikovaných fotek.
- Počet vychází ze stejného seznamu publikovaných fotek, který se na stránce vykresluje.
- Funguje i pro žurnalistický přístup přes krátký odkaz, protože po token/PIN vstupu se používá stejná `galerie.php`.

### Filtry ve fotoeditaci

- Opraven filtr `připraveno` na `photos.php`, aby nezobrazoval fotky, které už mají publikovanou hotovou verzi v galerii.
- Stavové filtry zároveň nevrací vyřazené fotky ani fotky od fotografa nepřiřazeného k eventu.
- Přidán nový filtr `ke zpracování`, který ukazuje pracovní fotky ve všech stavech mimo:
  - publikované,
  - zablokované/vyřazené,
  - smazané.
- Stejná logika se používá při prvotním načtení stránky i při AJAX obnovování feedu.

### Dlouhý seznam fotek bez odskakování

- Na `photos.php` už kliknutí na stav `připraveno` / `ke stažení` nepřenačítá celou stránku.
- Zamknutí a odemknutí fotky probíhá přes AJAX volání `select.php`.
- `select.php` zůstává zpětně kompatibilní:
  - běžný odkaz dál přesměrovává jako dřív,
  - AJAX požadavek vrací JSON.
- Při automatickém obnovování dlouhého seznamu si stránka pamatuje fotku u horní části viewportu a po překreslení vrátí uživatele na stejné místo.
- Oprava řeší i případ, kdy někdo nahraje nové fotky a ty se objeví výše v seznamu.

## Nápady pro další sprinty

- Ruční párování nespárovaných publikovaných fotek s originálem.
- Přehled posledních publikovaných fotek na dashboardu.
- Další ruční nástroje pro práci se soubory a eventem.
- Zpřesnit produkční instalační/migrační postup tak, aby `schema.sql` a všechny sprintové migrace byly jednoznačně sladěné pro čistou instalaci.
- vylepšení chatu (pár jednoduchých smajliků)
- API do Lightroomu nebo jiného exportního workflow.
- vyčištění kódu, dokumentace 
