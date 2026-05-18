<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (!defined('FTP_REPLACEMENT_ROOT')) {
    define('FTP_REPLACEMENT_ROOT', '/var/www/press/ftp');
}

if (!defined('FTP_REPLACEMENT_TMP_ROOT')) {
    define('FTP_REPLACEMENT_TMP_ROOT', '/var/www/press/upload-tmp');
}

function ftp_replacement_allowed_extensions(): array
{
    return [
        'cr2',
        'cr3',
        'nef',
        'nrw',
        'arw',
        'sr2',
        'srf',
        'raf',
        'rw2',
        'orf',
        'dng',
        'pef',
        'iiq',
        '3fr',
        'jpg',
        'jpeg',
    ];
}

function ftp_replacement_upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Soubor je příliš velký.',
        UPLOAD_ERR_PARTIAL => 'Soubor se nahrál jen částečně.',
        UPLOAD_ERR_NO_FILE => 'Vyber alespoň jeden soubor.',
        default => 'Upload se nepodařilo dokončit.',
    };
}

function ftp_replacement_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float)$value;

    return match ($unit) {
        'g' => (int)($number * 1024 * 1024 * 1024),
        'm' => (int)($number * 1024 * 1024),
        'k' => (int)($number * 1024),
        default => (int)$number,
    };
}

function ftp_replacement_format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024 * 1024) {
        return number_format($bytes / 1024 / 1024 / 1024, 1, ',', ' ') . ' GB';
    }

    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / 1024 / 1024, 0, ',', ' ') . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', ' ') . ' kB';
    }

    return $bytes . ' B';
}

function ftp_replacement_effective_upload_limit(): int
{
    $uploadMaxBytes = ftp_replacement_ini_bytes((string)ini_get('upload_max_filesize'));
    $postMaxBytes = ftp_replacement_ini_bytes((string)ini_get('post_max_size'));

    return min(
        $uploadMaxBytes > 0 ? $uploadMaxBytes : PHP_INT_MAX,
        $postMaxBytes > 0 ? $postMaxBytes : PHP_INT_MAX
    );
}

function ftp_replacement_user_storage(?array $user = null): array
{
    $user = $user ?? current_user();
    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) {
        throw new RuntimeException('Uživatel není přihlášený.');
    }

    $stmt = db()->prepare("
        SELECT id, user, ftp_user, homedir
        FROM users
        WHERE id = ?
          AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || trim((string)($row['ftp_user'] ?? '')) === '') {
        throw new RuntimeException('Uživatel nemá nastavený FTP účet.');
    }

    $ftpUser = trim((string)$row['ftp_user']);
    $homedir = trim((string)($row['homedir'] ?? ''));
    $fallbackDir = rtrim(FTP_REPLACEMENT_ROOT, '/') . '/' . $ftpUser;
    $targetDir = $homedir !== '' ? $homedir : $fallbackDir;
    $root = rtrim(FTP_REPLACEMENT_ROOT, '/') . '/';

    if (strncmp($targetDir . '/', $root, strlen($root)) !== 0) {
        $targetDir = $fallbackDir;
    }

    return [
        'ftp_user' => $ftpUser,
        'target_dir' => rtrim($targetDir, '/'),
    ];
}

function ftp_replacement_prepare_dir(string $dir, string $errorMessage): void
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException($errorMessage);
    }

    if (!is_writable($dir)) {
        throw new RuntimeException($errorMessage);
    }
}

function ftp_replacement_safe_filename(string $filename): string
{
    $filename = basename(str_replace('\\', '/', $filename));
    $filename = preg_replace('~[\x00-\x1F\x7F]+~', '', $filename) ?? '';
    $filename = trim($filename);

    if ($filename === '' || $filename === '.' || $filename === '..') {
        throw new RuntimeException('Soubor nemá platný název.');
    }

    if (str_starts_with($filename, '.')) {
        throw new RuntimeException('Skryté soubory nelze nahrávat.');
    }

    return $filename;
}

function ftp_replacement_unique_path(string $dir, string $filename): string
{
    $path = rtrim($dir, '/') . '/' . $filename;
    if (!file_exists($path)) {
        return $path;
    }

    $info = pathinfo($filename);
    $base = (string)($info['filename'] ?? 'soubor');
    $extension = isset($info['extension']) && $info['extension'] !== ''
        ? '.' . (string)$info['extension']
        : '';

    for ($i = 1; $i < 1000; $i++) {
        $candidate = rtrim($dir, '/') . '/' . $base . '-' . $i . $extension;
        if (!file_exists($candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException('Nepodařilo se najít volný název souboru.');
}

function ftp_replacement_validate_upload(array $file): string
{
    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        throw new RuntimeException(ftp_replacement_upload_error_message($errorCode));
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Uploadovaný soubor se nepodařilo načíst.');
    }

    $filename = ftp_replacement_safe_filename((string)($file['name'] ?? ''));
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($extension, ftp_replacement_allowed_extensions(), true)) {
        throw new RuntimeException('Tento typ souboru není podporovaný.');
    }

    return $filename;
}

function ftp_replacement_store_completed_file(array $user, array $file): array
{
    $filename = ftp_replacement_validate_upload($file);
    $storage = ftp_replacement_user_storage($user);
    $targetDir = (string)$storage['target_dir'];

    ftp_replacement_prepare_dir(FTP_REPLACEMENT_TMP_ROOT, 'Nepodařilo se připravit dočasný upload adresář.');
    ftp_replacement_prepare_dir($targetDir, 'Nepodařilo se připravit cílový FTP adresář.');

    $tmpPath = ftp_replacement_unique_path(
        FTP_REPLACEMENT_TMP_ROOT,
        bin2hex(random_bytes(8)) . '-' . $filename . '.part'
    );

    if (!move_uploaded_file((string)$file['tmp_name'], $tmpPath)) {
        throw new RuntimeException('Soubor se nepodařilo uložit.');
    }

    @chmod($tmpPath, 0664);

    $targetPath = ftp_replacement_unique_path($targetDir, $filename);
    if (!rename($tmpPath, $targetPath)) {
        @unlink($tmpPath);
        throw new RuntimeException('Soubor se nepodařilo přesunout do FTP adresáře.');
    }

    @chmod($targetPath, 0664);

    return [
        'filename' => basename($targetPath),
        'ftp_user' => (string)$storage['ftp_user'],
        'size' => is_file($targetPath) ? (int)filesize($targetPath) : (int)($file['size'] ?? 0),
    ];
}
