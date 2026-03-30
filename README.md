# Press center repo skeleton

Obsahuje:
- `sql/schema.sql` – databázové schema a výchozí role/oprávnění
- `install/deploy.sh` – deployment konfigurací a watcheru na server
- `scripts/sync-from-system.sh` – stažení aktuálních systémových souborů do repozitáře před commitem

Doporučený workflow:
1. upravit konfiguraci na serveru
2. spustit `scripts/sync-from-system.sh`
3. zkontrolovat změny přes `git diff`
4. commitnout
5. na jiném serveru nasadit přes `install/deploy.sh`
