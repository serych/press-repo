<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (!defined('HELP_DOCUMENTS_ROOT')) {
    define('HELP_DOCUMENTS_ROOT', '/var/www/press/help');
}

function help_can_view(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user !== null && (string)($user['role_code'] ?? '') !== 'journalist';
}

function help_can_manage(?array $user = null): bool
{
    $user = $user ?? current_user();
    return $user !== null && has_permission('users.manage');
}

function help_storage_dir(): string
{
    return HELP_DOCUMENTS_ROOT;
}

function help_prepare_storage(): void
{
    $dir = help_storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Nepodařilo se vytvořit adresář pro nápovědu.');
    }
}

function help_documents_list(): array
{
    $stmt = db()->query("
        SELECT
            hd.*,
            u.user AS uploaded_by_user,
            u.jmeno AS uploaded_jmeno,
            u.prijmeni AS uploaded_prijmeni
        FROM help_documents hd
        LEFT JOIN users u ON u.id = hd.uploaded_by_user_id
        ORDER BY hd.sort_order ASC, hd.title ASC, hd.id ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function help_document_get(int $id): ?array
{
    $stmt = db()->prepare("
        SELECT *
        FROM help_documents
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function help_upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Soubor je příliš velký.',
        UPLOAD_ERR_PARTIAL => 'Soubor se nahrál jen částečně.',
        UPLOAD_ERR_NO_FILE => 'Vyber PDF soubor.',
        default => 'Upload se nepodařilo dokončit.',
    };
}

function help_validate_pdf_upload(array $file): void
{
    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        throw new RuntimeException(help_upload_error_message($errorCode));
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Uploadovaný soubor se nepodařilo načíst.');
    }

    $originalName = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        throw new RuntimeException('Nahrát lze pouze PDF soubor.');
    }
}

function help_safe_filename(string $title, int $id): string
{
    $base = trim($title);
    if ($base === '') {
        $base = 'napoveda';
    }

    $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
    if (!is_string($base) || trim($base) === '') {
        $base = 'napoveda';
    }

    $base = strtolower($base);
    $base = preg_replace('~[^a-z0-9]+~', '-', $base) ?? $base;
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'napoveda';
    }

    return 'help-' . $id . '-' . $base . '.pdf';
}

function help_next_sort_order(): int
{
    $value = db()->query("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM help_documents")->fetchColumn();
    return (int)$value;
}

function help_store_uploaded_pdf(array $file, int $id, string $title): array
{
    help_validate_pdf_upload($file);
    help_prepare_storage();

    $filename = help_safe_filename($title, $id);
    $filepath = help_storage_dir() . '/' . $filename;

    if (!move_uploaded_file((string)$file['tmp_name'], $filepath)) {
        throw new RuntimeException('PDF se nepodařilo uložit.');
    }

    return [
        'filename' => $filename,
        'filepath' => $filepath,
        'filesize' => is_file($filepath) ? filesize($filepath) : null,
    ];
}

function help_create_document(string $title, array $file, int $userId): void
{
    help_validate_pdf_upload($file);

    $title = trim($title);
    if ($title === '') {
        $title = pathinfo((string)($file['name'] ?? ''), PATHINFO_FILENAME);
    }
    $title = trim($title);
    if ($title === '') {
        throw new RuntimeException('Vyplň název nápovědy.');
    }

    $pdo = db();
    $stmt = $pdo->prepare("
        INSERT INTO help_documents (title, filename, filepath, filesize, sort_order, uploaded_by_user_id, uploaded_at, updated_at)
        VALUES (:title, '', '', NULL, :sort_order, :uploaded_by_user_id, NOW(), NOW())
    ");
    $stmt->execute([
        ':title' => $title,
        ':sort_order' => help_next_sort_order(),
        ':uploaded_by_user_id' => $userId > 0 ? $userId : null,
    ]);

    $id = (int)$pdo->lastInsertId();
    try {
        $stored = help_store_uploaded_pdf($file, $id, $title);
    } catch (Throwable $e) {
        $stmt = $pdo->prepare("DELETE FROM help_documents WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        throw $e;
    }

    $stmt = $pdo->prepare("
        UPDATE help_documents
        SET filename = :filename,
            filepath = :filepath,
            filesize = :filesize,
            updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        ':id' => $id,
        ':filename' => $stored['filename'],
        ':filepath' => $stored['filepath'],
        ':filesize' => $stored['filesize'],
    ]);
}

function help_update_title(int $id, string $title): void
{
    $title = trim($title);
    if ($title === '') {
        throw new RuntimeException('Vyplň název nápovědy.');
    }

    $stmt = db()->prepare("
        UPDATE help_documents
        SET title = :title,
            updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $id,
        ':title' => $title,
    ]);
}

function help_replace_document_file(int $id, array $file, int $userId): void
{
    $document = help_document_get($id);
    if (!$document) {
        throw new RuntimeException('Dokument nápovědy nebyl nalezen.');
    }

    $oldPath = (string)($document['filepath'] ?? '');
    $stored = help_store_uploaded_pdf($file, $id, (string)$document['title']);

    if ($oldPath !== '' && $oldPath !== $stored['filepath'] && is_file($oldPath)) {
        @unlink($oldPath);
    }

    $stmt = db()->prepare("
        UPDATE help_documents
        SET filename = :filename,
            filepath = :filepath,
            filesize = :filesize,
            uploaded_by_user_id = :uploaded_by_user_id,
            updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $id,
        ':filename' => $stored['filename'],
        ':filepath' => $stored['filepath'],
        ':filesize' => $stored['filesize'],
        ':uploaded_by_user_id' => $userId > 0 ? $userId : null,
    ]);
}

function help_delete_document(int $id): void
{
    $document = help_document_get($id);
    if (!$document) {
        return;
    }

    $filepath = (string)($document['filepath'] ?? '');

    $stmt = db()->prepare("DELETE FROM help_documents WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);

    if ($filepath !== '' && is_file($filepath)) {
        @unlink($filepath);
    }
}

function help_move_document(int $id, string $direction): void
{
    $document = help_document_get($id);
    if (!$document) {
        return;
    }

    $operator = $direction === 'up' ? '<' : '>';
    $order = $direction === 'up' ? 'DESC' : 'ASC';

    $stmt = db()->prepare("
        SELECT id, sort_order
        FROM help_documents
        WHERE sort_order $operator :sort_order
        ORDER BY sort_order $order, id $order
        LIMIT 1
    ");
    $stmt->execute([
        ':sort_order' => (int)$document['sort_order'],
    ]);
    $neighbor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$neighbor) {
        return;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare("UPDATE help_documents SET sort_order = :sort_order WHERE id = :id");
        $update->execute([
            ':id' => (int)$document['id'],
            ':sort_order' => (int)$neighbor['sort_order'],
        ]);
        $update->execute([
            ':id' => (int)$neighbor['id'],
            ':sort_order' => (int)$document['sort_order'],
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function help_format_filesize(?int $bytes): string
{
    if ($bytes === null || $bytes <= 0) {
        return '—';
    }

    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / 1024 / 1024, 1, ',', ' ') . ' MB';
    }

    return number_format($bytes / 1024, 0, ',', ' ') . ' kB';
}
