# Changelog

## 2026-06-08 [2.0.0]

- Finální vydání po provozních opravách z B+D.
- Přidáno statické zobrazení aktuální verze na stránce `help.php`.
- Verze v nápovědě je zvýrazněná jako samostatný štítek.
- Na `photos.php` je hlavním nadpisem název aktuálního eventu.
- Původní text `Fotografie download RAW, upload hotových` je přesunutý do menšího popisu pod názvem eventu.

## 2026-06-07 [1.9.0]

- Oprava nepoznaných poškozených RAW uploadů po výpadku spojení.
- Validace integrity RAW/JPG souborů ve watcheru pro `NEF`, `CR2`, `CR3`, `ARW` a další podporované formáty.
- Volnější párování publikovaných JPG s RAW originály při opakovaném uploadu stejné fotky.
- Podpora suffixů `-1`, `_1`, ` (1)` a dalších pořadových variant při párování a stackování.
- Stacky opakovaně nahraných fotek ve `photos.php` a `photos-status.php`.
- Detail fotky zobrazuje jednotlivé varianty stacku.
- Jednorázová oprava nespárovaných publikovaných fotek podle nových pravidel.
- Reverzní řazení galerie podle EXIF času pořízení: nejnovější fotky nahoře.
- Vlastní časová osa galerie s hodinami a čtvrthodinami.
- Časová osa galerie respektuje časové pásmo eventu.
- Mobilní časová osa galerie s kompaktními hodinovými značkami.
- QR kód a krátká URL galerie na dashboardu.
- Na mobilu se QR kód na dashboardu zobrazuje jako první.
- `photos.php` zobrazuje plné jméno fotografa místo FTP username.
- `photos.php` zobrazuje jméno fotoeditora u stavů `staženo` a `publikováno`.
- Blokace a odblokace fotky přímo z přehledu `photos.php`.
- Zrušena samostatná stránka `published-upload.php`.
- Malý upload hotových JPG v `photos.php` používá nový endpoint `POST /api/published-upload.php`.
- Po smazání hotové galerie se zachovává poslední počet publikovaných fotek ve statistikách eventu.
- `Vyčistit testovací data` v `event-edit.php` nově smaže RAW, náhledy, testovací galerii, náhledy galerie, chat a vynuluje statistiky.
- `Archivovat po eventu` zachovává statistiky a maže jen pracovní RAW/náhledy.
- `Smazání hotové galerie` zachovává statistiku publikovaných fotek.

## 2026-05-31 [1.8.0]

- API pro upload hotových JPG prostřednictvím Lightroom Classic pluginu.
- Endpoint `POST /api/published-upload-client.php`.
- Endpoint `GET /api/client-active-event.php` pro zjištění aktuálního eventu pluginem.
- Správa klientských upload tokenů.
- Generování tokenů pro fotoeditory.
- Tokeny jsou uložené pouze jako hash a plná hodnota se zobrazí jen při vytvoření.
- Tokeny se zneplatní při odebrání oprávnění nebo downgradu role.
- Dokumentace API v `docs/lightroom_upload_api.md`.
- Přidán filtr `publikováno` do fotoeditorského přehledu.
- Opraven filtr `připraveno`, aby neukazoval fotky, které už mají publikovanou hotovou verzi.
- Přidán filtr `ke zpracování`.
- Opraveno odskakování dlouhého seznamu `photos.php` při AJAX refreshi.
- Zamykání a odemykání fotek v `photos.php` probíhá bez přenačtení celé stránky.
- Přidán počet publikovaných fotek vedle nadpisu galerie.
- Přidány statistiky fotoeditorů na dashboardu.
- Zlepšené UX uploadu hotových JPG.
- Zlepšené UX NeFTP uploadu.
- Vícesouborové uploady se posílají po jednotlivých souborech kvůli serverovým limitům.
- Opraveno překračování upload limitu hotových JPG.
- Opraven počet nepřečtených zpráv v chatu pro aktuální event.
- Opraveno UX hromadného stahování RAW fotek.
- Po dokončení NeFTP uploadu se seznam nahraných souborů automaticky uklidí.

## 2026-05-28 [1.7.1]

- Opraveno klouzavé přihlášení s cílem držet session přibližně 24 hodin.
- Opraven krátký odkaz na galerii, aby vždy otevíral správný event, ne právě aktivní event.
- Opraveny našeptávače při výběru fotografů a fotoeditorů do eventu.
- Nepřidatelné role se v našeptávači zobrazí s vysvětlením a nejdou vybrat.
- Proveden první konzervativní úklid `web/assets/style.css`.
- Doplněna debugging sekce do ToDo.

## 2026-05-23 [1.7.0]

- Krátký přístup do galerie pro žurnalisty přes `/g/<token>`.
- Volitelný PIN/heslo pro krátký galerijní přístup.
- Generování QR kódu pro galerijní odkaz.
- Evidence žurnalistických sessionů a stažení přes krátký odkaz.
- Krátký odkaz používá stejnou galerii jako přihlášený uživatel.
- `g-photo.php`, `g-preview.php` a `g-download.php` přesměrovávají na standardní stránky.
- Galerie zobrazuje upozornění, kolik fotek právě fotoeditoři zpracovávají.
- Oznámení o zpracovávaných fotkách se nezobrazuje při nule.
- QR kód má vyšší rozlišení.
- Opraveno zdvojené zapisování jména autora v EXIF/XMP.
- Implementován vypínač FTP přístupu přes pfSense API.
- Přidán panel `Vypínač press centra` v `events.php`.
- Hlavička se při vypnutém FTP přepne do červeného stavu `PRESS centrum vypnuto`.
- Přehled eventů má sloupec `Vystaveno`.
- Proveden provozní úklid serverového disku.

## 2026-05-20 [1.6.1]

- Přidán NeFTP upload přes HTTPS.
- NeFTP upload podporuje RAW formáty i JPG/JPEG.
- Upload přes NeFTP ukládá soubory do FTP adresáře uživatele a dál je zpracuje watcher.
- Přidána reverzace řazení fotek na `photos.php`.
- Upload více souborů se posílá postupně po jednom souboru.
- Formulář eventu varuje před neuloženými změnami.
- Upraven formulář vytváření uživatele.
- Přidána průběžná kontrola obsazenosti loginu.

## 2026-05-17 [1.6.0]

- Generování seznamu použitých fotek pro fotografa.
- Seznam použitých fotek lze kopírovat pro použití ve filtru Lightroomu.
- Sjednocené zobrazování stavů fotek napříč `photos.php`, `photo.php`, `photos-status.php` a API feedy.
- Přidán stav `publikováno`.
- Přidán export přehledu fotek eventu do Excel-kompatibilního `.xls`.
- Přidán export přehledu fotek eventu do CSV pro český Excel.
- Export obsahuje RAW fotky, publikované fotky, autora, fotoeditora, časy workflow, EXIF problém a počet stažení.
- Přidáno časové pásmo eventu.
- Dashboardové hodiny používají časové pásmo eventu.
- Ošetřeno, aby mohl být aktivní jen jeden běžný event.
- Přidán systém PDF nápovědy.
- PDF nápověda je uložená mimo webroot a dostupná přes `help.php` / `help-download.php`.
- Admin může dokumenty nápovědy přidávat, nahrazovat, mazat a řadit.
- Přidána statistika fotografů na dashboardu.

## 2026-05-16 [1.5.0]

- Přidána GPS metadata eventu.
- Event umí uložit GPS souřadnice a nadmořskou výšku.
- Watcher doplňuje GPS do EXIFu, pokud fotka GPS nemá.
- Při editaci eventu se GPS převádí mezi mapovým zápisem a EXIF formátem.
- Přidána nadmořská výška do EXIFu.
- Runner workflow bylo upravené tak, aby zachovalo zápis autora a GPS podle skutečného autora.
- Přidáno zrychlení galerie pomocí uloženého `author_label`.
- Dashboard má workflow statistiky a seřizovací hodiny.
- Hlavička zobrazuje jméno uživatele jen v tooltipu u odhlášení.

## 2026-05-09 [1.4.1]

- Původní `published.php` bylo přejmenováno na `galerie.php`.
- Galerie byla zrychlena.
- Upraven dashboard pro žurnalisty.
- Přidány velké seřizovací hodiny na dashboardu.
- Upravená hlavička systému.

## 2026-05-05 [1.4.0]

- Kompletní workflow hotových fotografií.
- Přidán upload hotových JPG do galerie.
- Přidáno automatické párování hotových JPG na RAW originály.
- Přidána galerie hotových fotek pro žurnalisty.
- Přidány detailní a malé náhledy publikovaných fotek.
- Přidány malé náhledy pro `photos.php` a `photos-status.php`.
- `photos.php` a `photos-status.php` podporují řazení a jsou bez paginace.
- Detail `photo.php` umožňuje procházet předchozí/následující fotky podle aktuálního filtru.
- Kliknutí na náhled v detailu otevírá čistý JPG náhled v nové kartě.
- Fotoeditor může fotku zablokovat/odblokovat v detailu.
- Zablokovaná fotka nejde zamknout ani stáhnout.
- Přidán samostatný cleanup hotové galerie.
- Upraveno menu a role žurnalisty.
- Žurnalista nemá přístup k chatu.
- Aktivní položka menu je zvýrazněná.
- Opraveno kódování `style.css`.

## 2026-04-29 [1.3.0]

- Watcher načítá `captured_at` z EXIFu.
- Přidána databázová struktura pro workflow hotových fotek.
- Zaznamenává se první upload fotoeditorem.
- Přidána stránka/formulář pro upload hotových JPG.
- Přidán upload box přímo do `photos.php`.
- Přidány workflow časy.
- Přidána první galerie hotových fotek pro žurnalisty.
- Žurnalista má jako výchozí stránku galerii.
- Upraven pravý panel na `photos.php`.

## 2026-04-27 [1.0.0]

- Tag `v1.0`.
- Opraveno přidávání fotoeditora/redaktora do eventu.
- Opraven kritický bug ve watcheru, kdy se mohl zacyklit při runner uploadu vlastní fotky.
- Dashboard zobrazuje vedoucího eventu.
- Přidána role `Žurnalista`.
- Role `Redaktor` přejmenována na `Fotoeditor`.
- Ošetřen fotograf mimo event.
- Opraven deploy script.

## 2026-04-07 [0.7.0]

- Přidán eventový chat.
- Chat umí přepínat poslední zprávu a historii.
- Chat lze smazat ve správě eventu.
- Přidána funkcionalita čištění press centra.
- Vylepšené UX vytváření a editace eventů.
- Opraveno odhlašování.
- Založen ToDo dokument a návrh dalších funkcí.

## 2026-04-04 [0.6.0]

- Přidána funkcionalita runnera.
- Runner může nahrávat fotky a systém podle EXIF autora dohledá skutečného fotografa.
- Přidán veřejný dashboard s údaji o aktivní události.
- Upravená práva k editaci eventů.
- Přidány notifikace uploadu a processingu včetně zvukového pípnutí.
- Větší náhledy pro mobilní zobrazení.

## 2026-04-02 [0.5.0]

- Zásadní oprava generování náhledů z RAWů.
- Opraven watcher pro generování náhledů z `CR3`.
- Přidán skript na vymazání datacentra před akcí.

## 2026-03-31 [0.4.0]

- Implementován download workflow.
- Přidáno zamykání fotek pro fotoeditora.
- Vynuceno, aby zamčenou fotku nemohl stáhnout jiný uživatel.
- Implementován základ hromadného downloadu.
- Odladěn user management.
- Přidána stránka `photos-status.php` pro mobilní přehled fotografů.
- Upravené výchozí stránky podle role.
- Přidáno logo a úpravy login stránky.

## 2026-03-30 [0.1.0]

- Založen repozitář.
- První funkční web s loginem.
- První funkční FTP upload, watcher a tvorba preview.
- Přidán přehled fotografií s jednoduchým filtrem.
- Přidán detail fotky.
- Přidán základ výběru fotek.
