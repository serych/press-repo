# Lightroom Upload API

Serverová API část Sprintu 12 pro budoucí Lightroom Classic plugin. API slouží k uploadu hotových JPG do galerie právě aktivního eventu.

## Autentizace

Klient posílá token v HTTP hlavičce:

```http
Authorization: Bearer <token>
```

Token se spravuje v press centru v sekci `Uživatelé -> Upload tokeny`.

Server ověřuje:

- token existuje a není zneplatněný,
- token neexpiroval,
- vlastník tokenu je aktivní uživatel,
- vlastník tokenu má oprávnění `published_photos.upload`.

## Aktivní Event

Upload vždy míří do právě aktivního eventu. Plugin neposílá `event_id`.

Pro kontrolní zobrazení v pluginu slouží:

```http
GET /api/client-active-event.php
Authorization: Bearer <token>
```

Úspěšná odpověď:

```json
{
  "ok": true,
  "event": {
    "id": 123,
    "title": "Noc kostelů",
    "slug": "noc-kostelu",
    "status": "active"
  }
}
```

Pokud není aktivní event, server vrací `409`.

## Upload Jednoho JPG

Endpoint:

```http
POST /api/published-upload-client.php
Authorization: Bearer <token>
Content-Type: multipart/form-data
```

Pole:

- `photo`: JPG/JPEG soubor.

Volitelná metadata pro budoucí rozšíření klienta:

- `original_filename`
- `client_name`
- `client_version`

Aktuální serverová implementace páruje hotový JPG podle názvu souboru stejně jako webový upload.

## Úspěšná Odpověď

```json
{
  "ok": true,
  "event": {
    "id": 123,
    "title": "Noc kostelů",
    "slug": "noc-kostelu",
    "status": "active"
  },
  "errors": [],
  "uploaded": [
    {
      "id": 456,
      "filename": "DSC_1234-uprava.jpg",
      "paired": true,
      "source_photo_id": 321,
      "source_filename": "DSC_1234.CR3"
    }
  ]
}
```

Pokud se soubor uloží, ale nepodaří se ho spárovat s originálem, `paired` bude `false` a `source_photo_id` / `source_filename` budou `null`.

## Chybové Odpovědi

Všechny chyby vrací JSON.

```json
{
  "ok": false,
  "error": "Neplatný token."
}
```

nebo u uploadu:

```json
{
  "ok": false,
  "event": {
    "id": 123,
    "title": "Noc kostelů",
    "slug": "noc-kostelu",
    "status": "active"
  },
  "errors": [
    "fotka.tif: Nahrát lze pouze platný JPG soubor."
  ],
  "uploaded": []
}
```

Typické HTTP kódy:

- `200`: upload nebo dotaz na event proběhl úspěšně,
- `400`: neplatný upload request nebo soubor,
- `401`: chybí token, token je neplatný, expirovaný nebo zneplatněný,
- `403`: token patří uživateli bez potřebného oprávnění,
- `405`: špatná HTTP metoda,
- `409`: není aktivní event.

## Curl Příklady

Zjištění aktivního eventu:

```bash
curl -s \
  -H "Authorization: Bearer pcup_..." \
  https://press.clovekavira.cz/api/client-active-event.php
```

Upload JPG:

```bash
curl -s \
  -H "Authorization: Bearer pcup_..." \
  -F "photo=@/cesta/k/fotce.jpg" \
  -F "client_name=Lightroom Classic plugin" \
  -F "client_version=0.1" \
  https://press.clovekavira.cz/api/published-upload-client.php
```

## Doporučené Chování Klienta

- Před exportem nebo před prvním uploadem ověřit aktivní event a zobrazit jeho název uživateli.
- Uploadovat soubory po jednom, stejně jako webové rozhraní.
- Při síťové chybě opakovat upload až po jasném potvrzení, že server soubor nepřijal.
- Při chybě `401` vyžádat nový token.
- Při chybě `409` zastavit upload a upozornit, že v press centru není aktivní event.
- V lokálním logu držet alespoň název souboru, čas pokusu, HTTP kód a JSON odpověď serveru.
