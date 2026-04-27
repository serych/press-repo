<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function photos_get_current_event(): ?array
{
    $sql = "
        SELECT *
        FROM events
        WHERE status = 'active'
        ORDER BY is_temporary ASC, id DESC
        LIMIT 1
    ";

    $row = db()->query($sql)->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function photos_get_photographers(array $filters = []): array
{
    [$where, $params] = photos_build_where($filters);

    $sql = "
        SELECT DISTINCT p.ftp_user
        FROM photos p
        $where
        AND p.ftp_user IS NOT NULL
        ORDER BY p.ftp_user
    ";

    if ($where === '') {
        $sql = "
            SELECT DISTINCT p.ftp_user
            FROM photos p
            WHERE p.ftp_user IS NOT NULL
            ORDER BY p.ftp_user
        ";
        return db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function photos_count(array $filters): int
{
    [$where, $params] = photos_build_where($filters);

    $sql = "SELECT COUNT(*) FROM photos p $where";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
}

function photos_list(array $filters, int $limit, int $offset): array
{
    [$where, $params] = photos_build_where($filters);

    $sql = "
        SELECT
            p.*,
            lu.user AS locked_by_user,
            lu.jmeno AS locked_jmeno,
            lu.prijmeni AS locked_prijmeni
        FROM photos p
        LEFT JOIN users lu ON lu.id = p.locked_by_user_id
        $where
        ORDER BY p.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = db()->prepare($sql);

    foreach ($params as $k => $v) {
        if (is_int($v)) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($k, $v);
        }
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function photos_feed(array $filters, int $limit, int $offset): array
{
    return photos_list($filters, $limit, $offset);
}

function photos_is_event_photographer_allowed(array $photo): bool
{
    return !array_key_exists('event_photographer_allowed', $photo)
        || (int)($photo['event_photographer_allowed'] ?? 1) === 1;
}

function photos_build_where(array $filters): array
{
    $where = [];
    $params = [];

    if (!empty($filters['event_id'])) {
        $where[] = 'p.event_id = :event_id';
        $params[':event_id'] = (int)$filters['event_id'];
    }

    if (!empty($filters['ftp_user'])) {
        $where[] = 'p.ftp_user = :ftp_user';
        $params[':ftp_user'] = $filters['ftp_user'];
    }

    if (!empty($filters['status'])) {
        $where[] = 'p.status = :status';
        $params[':status'] = $filters['status'];
    }

    if (!$where) {
        return ['', []];
    }

    return ['WHERE ' . implode(' AND ', $where), $params];
}

function photos_get_by_id(int $id): ?array
{
    $sql = "
        SELECT
            p.*,
            u.jmeno,
            u.prijmeni,
            u.user AS web_user,
            lu.user AS locked_by_user,
            lu.jmeno AS locked_jmeno,
            lu.prijmeni AS locked_prijmeni
        FROM photos p
        LEFT JOIN users u ON u.id = p.user_id
        LEFT JOIN users lu ON lu.id = p.locked_by_user_id
        WHERE p.id = :id
        LIMIT 1
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $row['exif_author'] = '';
    $row['exif_copyright'] = '';

    $filepath = (string)($row['filepath'] ?? '');
    if ($filepath !== '' && is_file($filepath)) {
        $exif = photos_read_exif_summary($filepath);
        $row['exif_author'] = $exif['author'];
        $row['exif_copyright'] = $exif['copyright'];
    }

    return $row;
}

function photos_read_exif_summary(string $filepath): array
{
    if (!is_file($filepath)) {
        return [
            'author' => '',
            'copyright' => '',
        ];
    }

    $escaped = escapeshellarg($filepath);
    $cmd = "exiftool -s3 -Artist -Author -Creator -XMP-dc:Creator -IPTC:By-line -Copyright -XMP-dc:Rights -IPTC:CopyrightNotice $escaped 2>/dev/null";

    $output = shell_exec($cmd);
    if (!is_string($output) || trim($output) === '') {
        return [
            'author' => '',
            'copyright' => '',
        ];
    }

    $lines = preg_split('~\R~u', trim($output)) ?: [];

    $author = '';
    $copyright = '';

    if (isset($lines[0])) {
        $author = trim((string)$lines[0]);
    }
    if ($author === '' && isset($lines[1])) {
        $author = trim((string)$lines[1]);
    }
    if ($author === '' && isset($lines[2])) {
        $author = trim((string)$lines[2]);
    }
    if ($author === '' && isset($lines[3])) {
        $author = trim((string)$lines[3]);
    }
    if ($author === '' && isset($lines[4])) {
        $author = trim((string)$lines[4]);
    }

    if (isset($lines[5])) {
        $copyright = trim((string)$lines[5]);
    }
    if ($copyright === '' && isset($lines[6])) {
        $copyright = trim((string)$lines[6]);
    }
    if ($copyright === '' && isset($lines[7])) {
        $copyright = trim((string)$lines[7]);
    }

    return [
        'author' => $author,
        'copyright' => $copyright,
    ];
}
