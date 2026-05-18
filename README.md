# PRESS centrum CaV

Webova aplikace a serverove skripty pro press centrum Clovek a Vira. System pokryva prijem fotek pres FTP, automaticke zpracovani originálu, praci fotoeditoru, publikaci hotovych fotek do galerie pro zurnalisty a poeventove exporty.

## Hlavni workflow

1. Fotograf nebo runner nahraje fotky pres FTP do `/var/www/press/ftp`.
2. `press-watcher` soubory zpracuje, zapise metadata do MariaDB a vytvori nahledy.
3. Fotoeditor ve webu vybira a stahuje originály, upravene JPG nahraje zpet do galerie.
4. Galerie nabizi hotove fotky zurnalistum ke stazeni a pocita downloady.
5. Po eventu lze exportovat prehled fotek a fotograf muze ziskat seznam pouzitych nazvu pro Lightroom.

## Role

- `superadmin` a `admin`: sprava uzivatelu, eventu, napovedy a systemovych vystupu.
- `press_operator`: fotoeditace, stahovani originálu a upload hotovych fotek.
- `photographer`: FTP upload a prehled vlastnich fotek vcetne seznamu pouzitych fotek.
- `journalist`: pristup do galerie hotovych fotek a informacni stranky eventu.

## Aktualni funkce

- Sprava uzivatelu vcetne weboveho a FTP prihlaseni.
- Sprava eventu vcetne fotografu, fotoeditoru, runneru, GPS metadat, nadmorske vysky a casoveho pasma.
- Kontrola, aby byl aktivni nejvyse jeden bezny event.
- Dashboard aktivniho eventu se statistikami, workflow casy a hodinami v casovem pasmu eventu.
- FTP watcher s EXIF ctenim, zapisovanim autora/copyrightu/GPS a generovanim detailnich i malych nahledu.
- HTTPS nahrada FTP uploadu na `ftp.php` pro vsechny prihlasene role mimo zurnalisty, ktera predava zdrojove fotky do stejneho watcher workflow.
- Runner workflow s dohledanim autora podle EXIF autora.
- Fotoeditacni prehled `photos.php`, detail `photo.php`, blokace fotek a hromadne stahovani.
- Fotograficky prehled `photos-status.php` se stavy a seznamem pouzitych fotek pro Lightroom.
- Galerie `galerie.php` a detail publikovane fotky pro zurnalisty.
- Upload hotovych JPG do galerie s automatickym parovanim na original podle nazvu.
- Poeventovy export z editace eventu do Excel-kompatibilniho `.xls` a CSV oddeleneho strednikem.
- Jednoducha PDF napoveda: admin pridava, nahrazuje, maze a radi PDF, ostatni role mimo zurnalistu je mohou zobrazit nebo stahnout.
- Chat pro interni role.

## Dulezite adresare

```text
web/                         PHP aplikace
web/inc/                     sdilena aplikační logika
web/api/                     AJAX/API endpointy
web/assets/                  CSS, JS, logo
scripts/press-watcher.sh     watcher FTP uploadu
scripts/press-backfill*.sh   pomocne backfill skripty
config/                      vsftpd, PAM a systemd konfigurace
sql/schema.sql               zakladni databazove schema
sql/sprint*.sql              migrace pro jednotlive sprinty
docs/ToDo_a_Ficury.md        stav produktu a backlog
```

Na serveru se pouzivaji hlavne:

```text
/var/www/press/web           zivy webroot
/var/www/press/ftp           FTP uploady
/var/www/press/upload-tmp    docasne soubory HTTPS nahrady FTP uploadu
/var/www/press/previews      nahledy originálu
/var/www/press/published     hotove fotky galerie
/var/www/press/help          PDF napoveda mimo webroot
/usr/local/bin/press-watcher.sh
```

## Databaze

Databaze je MariaDB `press`. Zaklad je v `sql/schema.sql`, pozdejsi zmeny jsou v migracich `sql/sprint*.sql`.

Hlavni tabulky:

- `users`, `roles`, `permissions`, `role_permissions`
- `events`, `event_participants`
- `photos`, `photo_log`
- `published_photos`, `published_photo_log`
- `download_jobs`, `download_job_items`
- `chat_messages`, `chat_reads`
- `help_documents`

FTP autentizace pouziva view `v_vsftpd_users`.

## Nasazeni

Z repozitare lze serverove soubory nasadit skriptem:

```bash
sudo install/deploy.sh
```

Skript kopiruje konfigurace, watcher a web do `/var/www/press/web`, reloadne systemd a restartuje `vsftpd` a `press-watcher`.

Databazi je potreba zakladat a migrovat samostatne, typicky:

```bash
mysql -u root -p < sql/schema.sql
mysql -u root -p < sql/sprint10_003_help_documents.sql
```

Pri rucnim nasazovani jednotlivych uprav se v produkci typicky pouziva `install -m 0644` pro PHP/CSS/JS soubory a `install -m 0755` pro shell skripty.

## Provozni poznamky

- Watcher je systemd sluzba `press-watcher`.
- Log watcheru je `/var/log/press-watcher.log`.
- PDF napoveda je ukladana mimo webroot do `/var/www/press/help`.
- CSV exporty jsou kvuli ceskemu Excelu ve Windows-1250 a obsahuji radek `sep=;`.
- Verejna galerie zustava zachovana i pri archivaci eventu; maze se pouze samostatnou akci v editaci eventu.
