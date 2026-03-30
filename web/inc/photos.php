<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function photos_get_photographers(): array
{
    $sql = "
        SELECT DISTINCT ftp_user
        FROM photos
        WHERE ftp_user IS NOT NULL
        ORDER BY ftp_user
    ";

    return db()->query($sql)->fetchAll();
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
            u.jmeno,
            u.prijmeni
        FROM photos p
        LEFT JOIN users u ON u.id = p.user_id
        $where
        ORDER BY p.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = db()->prepare($sql);

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    return $stmt->fetchAll();
}

function photos_build_where(array $filters): array
{
    $where = [];
    $params = [];

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
            u.user AS web_user
        FROM photos p
        LEFT JOIN users u ON u.id = p.user_id
        WHERE p.id = :id
        LIMIT 1
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $id]);

    $row = $stmt->fetch();
    return $row ?: null;
}