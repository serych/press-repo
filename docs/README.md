# Press centrum – technická dokumentace (stav: MVP)

Tento dokument shrnuje aktuální stav implementace press centra pro fotografy.

---

# Přehled architektury

Workflow:

```
Fotoaparát → FTP upload → vsftpd → /var/www/press/ftp/
                                   ↓
                              watcher (inotify)
                                   ↓
                          generování preview JPG
                                   ↓
                            databáze (MariaDB)
                                   ↓
                           webové rozhraní (PHP)
```

---

# Struktura adresářů

Root projektu:

```
/var/www/press/
```

Struktura:

```
/var/www/press/
│
├── ftp/                 # uploady z fotoaparátů
│   └── <ftp_user>/
│       └── RAW / JPG
│
├── previews/            # generované náhledy
│   └── <ftp_user>/
│       └── preview.jpg
│
├── web/ (budoucí)       # webová aplikace
│
└── README.md            # tato dokumentace
```

---

# Konfigurační soubory

## vsftpd

Soubor:

```
/etc/vsftpd.conf
```

Popis:

* FTP server
* pasivní porty 40000–40100
* chroot do user directory
* virtual users
* root: `/var/www/press/ftp/$USER`

---

## PAM autentizace FTP

Soubor:

```
/etc/pam.d/vsftpd
```

Použití:

* autentizace přes MariaDB
* bcrypt hash
* tabulka `v_vsftpd_users`

---

## systemd service watcher

Soubor:

```
/etc/systemd/system/press-watcher.service
```

Popis:

* spouští watcher
* restart při pádu
* běží trvale

---

# Watcher script

Soubor:

```
/usr/local/bin/press-watcher.sh
```

Funkce:

* sleduje `/var/www/press/ftp`
* detekuje nové soubory
* podporuje RAW i JPEG
* čeká na dokončení uploadu
* generuje preview JPG
* zapisuje do DB
* zapisuje log

Log:

```
/var/log/press-watcher.log
```

---

# Databáze

Database:

```
press
```

---

# Tabulka users

Uživatelé systému

Obsahuje:

* web login
* ftp login
* role
* home directory

Použití:

* autentizace web
* mapování FTP uploadů

---

# Tabulka roles

Definice rolí:

* superadmin
* admin
* press_operator (Redaktor)
* photographer
* journalist (Žurnalista)

---

# Tabulka permissions

Seznam oprávnění

Používáno pro RBAC

---

# Tabulka role_permissions

Mapování:

```
role → permission
```

---

# Tabulka photos

Evidence fotografií

Obsahuje:

* filename
* filepath
* preview
* user
* status
* dimensions
* timestamps

Stavy:

```
uploaded
processing
ready
selected
deleted
error
```

---

# Tabulka photo_log

Audit log akcí

Akce:

```
upload
preview_generated
selected
downloaded
deleted
```

---

# View v_vsftpd_users

Používá vsftpd

Obsah:

```
username
password
```

Zdroj:

```
users.ftp_user
users.ftp_pass_hash
```

---

# Spuštění watcheru

```
systemctl start press-watcher
```

Status:

```
systemctl status press-watcher
```

Log:

```
tail -f /var/log/press-watcher.log
```

---

# Podporované formáty

RAW:

```
CR2
CR3
NEF
ARW
RAF
DNG
RW2
ORF
PEF
IIQ
3FR
```

JPEG:

```
JPG
JPEG
```

---

# Git repository doporučená struktura

Doporučuji vytvořit repo např.:

```
press-center/
```

Struktura:

```
press-center/
│
├── README.md
├── docs/
│
├── config/
│   ├── vsftpd.conf
│   ├── pam.vsftpd
│   └── press-watcher.service
│
├── scripts/
│   └── press-watcher.sh
│
├── sql/
│   ├── schema.sql
│   ├── roles.sql
│   └── permissions.sql
│
├── web/
│   └── (budoucí aplikace)
│
└── install/
    └── install.sh
```

---

# Jak verzovat systémové soubory

Repo obsahuje **kopie**, ne originály:

| Systém             | Repo                         |
| ------------------ | ---------------------------- |
| /etc/vsftpd.conf   | config/vsftpd.conf           |
| /etc/pam.d/vsftpd  | config/pam.vsftpd            |
| /etc/systemd/...   | config/press-watcher.service |
| /usr/local/bin/... | scripts/press-watcher.sh     |

---

# Deploy skript (doporučeno)

install/install.sh

```
cp config/vsftpd.conf /etc/vsftpd.conf
cp config/pam.vsftpd /etc/pam.d/vsftpd
cp config/press-watcher.service /etc/systemd/system/

cp scripts/press-watcher.sh /usr/local/bin/
chmod +x /usr/local/bin/press-watcher.sh

systemctl daemon-reload
systemctl restart press-watcher
```

---

# Co je hotovo

✓ FTP server
✓ FTPS připraveno
✓ virtual users
✓ MariaDB auth
✓ role system
✓ upload RAW
✓ upload JPG
✓ watcher
✓ preview generování
✓ DB evidence
✓ audit log
✓ systemd service

---

# Co zbývá

* web galerie
* výběr fotografií
* download UI
* user management UI
* realtime refresh
* FTPS zapnutí
* API ovládání
* auto cleanup

---

# Stav projektu

MVP backend hotov.

Následuje:

Web UI + selection workflow.
