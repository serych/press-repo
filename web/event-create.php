<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function events_list(): array
{
    $sql = "
        SELECT
            e.*,
            leader.jmeno AS leader_jmeno,
            leader.prijmeni AS leader_prijmeni,
            leader.mobile AS leader_mobile,
            creator.user AS created_by_user
        FROM events e
        LEFT JOIN users leader ON leader.id = e.leader_user_id
        LEFT JOIN users creator ON creator.id = e.created_by
        ORDER BY
            CASE e.status
                WHEN 'active' THEN 1
                WHEN 'planned' THEN 2
                WHEN 'finished' THEN 3
                ELSE 4
            END,
            e.starts_at IS NULL,
            e.starts_at DESC,
            e.id DESC
    ";

    return db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function events_get(int $id): ?array
{
    $sql = "
        SELECT
            e.*,
            leader.jmeno AS leader_jmeno,
            leader.prijmeni AS leader_prijmeni,
            leader.mobile AS leader_mobile,
            creator.user AS created_by_user
        FROM events e
        LEFT JOIN users leader ON leader.id = e.leader_user_id
        LEFT JOIN users creator ON creator.id = e.created_by
        WHERE e.id = :id
        LIMIT 1
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function events_get_active_regular(): ?array
{
    $sql = "
        SELECT *
        FROM events
        WHERE status = 'active'
          AND is_temporary = 0
        ORDER BY id DESC
        LIMIT 1
    ";

    $row = db()->query($sql)->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function events_get_active_temporary(): ?array
{
    $sql = "
        SELECT *
        FROM events
        WHERE status = 'active'
          AND is_temporary = 1
        ORDER BY id DESC
        LIMIT 1
    ";

    $row = db()->query($sql)->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function events_get_current_for_watcher(): ?array
{
    $event = events_get_active_regular();
    if ($event) {
        return $event;
    }

    return events_get_active_temporary();
}

function events_slug_exists(string $slug, ?int $excludeId = null): bool
{
    $pdo = db();

    if ($excludeId !== null) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM events
            WHERE slug = ?
              AND id <> ?
        ");
        $stmt->execute([$slug, $excludeId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM events
            WHERE slug = ?
        ");
        $stmt->execute([$slug]);
    }

    return (int)$stmt->fetchColumn() > 0;
}

function events_other_active_regular_exists(?int $excludeId = null): bool
{
    $pdo = db();

    if ($excludeId !== null) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM events
            WHERE status = 'active'
              AND is_temporary = 0
              AND id <> ?
        ");
        $stmt->execute([$excludeId]);
    } else {
        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM events
            WHERE status = 'active'
              AND is_temporary = 0
        ");
    }

    return (int)$stmt->fetchColumn() > 0;
}

function events_create(array $data): int
{
    $stmt = db()->prepare("
        INSERT INTO events (
            title,
            slug,
            description,
            starts_at,
            ends_at,
            cav_gallery_url,
            cloud_url,
            leader_user_id,
            status,
            is_public,
            is_temporary,
            created_by
        ) VALUES (
            :title,
            :slug,
            :description,
            :starts_at,
            :ends_at,
            :cav_gallery_url,
            :cloud_url,
            :leader_user_id,
            :status,
            :is_public,
            :is_temporary,
            :created_by
        )
    ");

    $stmt->execute([
        ':title'           => $data['title'],
        ':slug'            => $data['slug'],
        ':description'     => $data['description'] !== '' ? $data['description'] : null,
        ':starts_at'       => $data['starts_at'] !== '' ? $data['starts_at'] : null,
        ':ends_at'         => $data['ends_at'] !== '' ? $data['ends_at'] : null,
        ':cav_gallery_url' => $data['cav_gallery_url'] !== '' ? $data['cav_gallery_url'] : null,
        ':cloud_url'       => $data['cloud_url'] !== '' ? $data['cloud_url'] : null,
        ':leader_user_id'  => $data['leader_user_id'] ?: null,
        ':status'          => $data['status'],
        ':is_public'       => !empty($data['is_public']) ? 1 : 0,
        ':is_temporary'    => !empty($data['is_temporary']) ? 1 : 0,
        ':created_by'      => $data['created_by'] ?: null,
    ]);

    return (int)db()->lastInsertId();
}

function events_update(int $id, array $data): void
{
    $stmt = db()->prepare("
        UPDATE events
        SET
            title = :title,
            slug = :slug,
            description = :description,
            starts_at = :starts_at,
            ends_at = :ends_at,
            cav_gallery_url = :cav_gallery_url,
            cloud_url = :cloud_url,
            leader_user_id = :leader_user_id,
            status = :status,
            is_public = :is_public,
            is_temporary = :is_temporary
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id'              => $id,
        ':title'           => $data['title'],
        ':slug'            => $data['slug'],
        ':description'     => $data['description'] !== '' ? $data['description'] : null,
        ':starts_at'       => $data['starts_at'] !== '' ? $data['starts_at'] : null,
        ':ends_at'         => $data['ends_at'] !== '' ? $data['ends_at'] : null,
        ':cav_gallery_url' => $data['cav_gallery_url'] !== '' ? $data['cav_gallery_url'] : null,
        ':cloud_url'       => $data['cloud_url'] !== '' ? $data['cloud_url'] : null,
        ':leader_user_id'  => $data['leader_user_id'] ?: null,
        ':status'          => $data['status'],
        ':is_public'       => !empty($data['is_public']) ? 1 : 0,
        ':is_temporary'    => !empty($data['is_temporary']) ? 1 : 0,
    ]);
}

function events_delete(int $id): void
{
    $stmt = db()->prepare("
        DELETE FROM events
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
}

function events_users_for_picker(): array
{
    $sql = "
        SELECT
            u.id,
            u.jmeno,
            u.prijmeni,
            u.user,
            u.mobile,
            u.ftp_user,
            r.code AS role_code,
            r.name AS role_name
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        ORDER BY
            CASE r.code
                WHEN 'photographer' THEN 1
                WHEN 'press_operator' THEN 2
                WHEN 'admin' THEN 3
                WHEN 'superadmin' THEN 4
                ELSE 5
            END,
            u.prijmeni,
            u.jmeno,
            u.user
    ";

    return db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function events_participants_get(int $eventId): array
{
    $stmt = db()->prepare("
        SELECT
            eu.event_id,
            eu.user_id,
            eu.role_in_event,
            eu.runner,
            u.jmeno,
            u.prijmeni,
            u.user,
            u.mobile,
            u.ftp_user,
            r.code AS role_code,
            r.name AS role_name
        FROM event_users eu
        INNER JOIN users u ON u.id = eu.user_id
        INNER JOIN roles r ON r.id = u.role_id
        WHERE eu.event_id = ?
        ORDER BY eu.role_in_event, u.prijmeni, u.jmeno, u.user
    ");
    $stmt->execute([$eventId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function events_participants_get_ids_by_role(int $eventId, string $roleInEvent): array
{
    $stmt = db()->prepare("
        SELECT user_id
        FROM event_users
        WHERE event_id = ?
          AND role_in_event = ?
        ORDER BY user_id
    ");
    $stmt->execute([$eventId, $roleInEvent]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function events_participants_get_runner_user_id(int $eventId): int
{
    $stmt = db()->prepare("
        SELECT user_id
        FROM event_users
        WHERE event_id = ?
          AND role_in_event = 'photographer'
          AND runner = 1
        ORDER BY user_id
        LIMIT 1
    ");
    $stmt->execute([$eventId]);

    $value = $stmt->fetchColumn();

    return $value !== false ? (int)$value : 0;
}

function events_participants_save(int $eventId, array $photographerIds, array $editorIds, ?int $runnerUserId = null): void
{
    $pdo = db();

    $photographerIds = array_values(array_unique(array_map('intval', $photographerIds)));
    $editorIds = array_values(array_unique(array_map('intval', $editorIds)));
    $runnerUserId = $runnerUserId !== null ? (int)$runnerUserId : 0;

    if ($runnerUserId > 0 && !in_array($runnerUserId, $photographerIds, true)) {
        $runnerUserId = 0;
    }

    $pdo->beginTransaction();

    try {
        $delete = $pdo->prepare("
            DELETE FROM event_users
            WHERE event_id = ?
        ");
        $delete->execute([$eventId]);

        $insert = $pdo->prepare("
            INSERT INTO event_users (event_id, user_id, role_in_event, runner)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($photographerIds as $userId) {
            if ($userId > 0) {
                $insert->execute([
                    $eventId,
                    $userId,
                    'photographer',
                    $runnerUserId > 0 && $runnerUserId === $userId ? 1 : 0,
                ]);
            }
        }

        foreach ($editorIds as $userId) {
            if ($userId > 0) {
                $insert->execute([$eventId, $userId, 'editor', 0]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function events_stats_summary(int $eventId): array
{
    $stmt = db()->prepare("
        SELECT
            COUNT(*) AS uploaded_total,
            SUM(CASE WHEN status = 'downloaded' THEN 1 ELSE 0 END) AS downloaded_total
        FROM photos
        WHERE event_id = ?
    ");
    $stmt->execute([$eventId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'uploaded_total'   => (int)($row['uploaded_total'] ?? 0),
        'downloaded_total' => (int)($row['downloaded_total'] ?? 0),
    ];
}

function events_stats_photographers(int $eventId): array
{
    $stmt = db()->prepare("
        SELECT
            u.id,
            u.jmeno,
            u.prijmeni,
            u.mobile,
            u.user,
            u.ftp_user,
            eu.runner,
            COUNT(p.id) AS uploaded_count,
            SUM(CASE WHEN p.status = 'downloaded' THEN 1 ELSE 0 END) AS downloaded_count
        FROM event_users eu
        INNER JOIN users u ON u.id = eu.user_id
        LEFT JOIN photos p
            ON p.event_id = eu.event_id
           AND p.user_id = eu.user_id
        WHERE eu.event_id = ?
          AND eu.role_in_event = 'photographer'
        GROUP BY
            u.id, u.jmeno, u.prijmeni, u.mobile, u.user, u.ftp_user, eu.runner
        ORDER BY u.prijmeni, u.jmeno, u.user
    ");
    $stmt->execute([$eventId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function events_stats_counts_of_participants(int $eventId): array
{
    $stmt = db()->prepare("
        SELECT
            SUM(CASE WHEN role_in_event = 'photographer' THEN 1 ELSE 0 END) AS photographers_count,
            SUM(CASE WHEN role_in_event = 'editor' THEN 1 ELSE 0 END) AS editors_count
        FROM event_users
        WHERE event_id = ?
    ");
    $stmt->execute([$eventId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'photographers_count' => (int)($row['photographers_count'] ?? 0),
        'editors_count'       => (int)($row['editors_count'] ?? 0),
    ];
}

function events_slugify(string $value): string
{
    $value = trim($value);

    if (function_exists('mb_strtolower')) {
        $value = mb_strtolower($value, 'UTF-8');
    } else {
        $value = strtolower($value);
    }

    $map = [
        'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e',
        'ě' => 'e', 'ë' => 'e', 'í' => 'i', 'ľ' => 'l', 'ĺ' => 'l',
        'ň' => 'n', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ř' => 'r',
        'ŕ' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u',
        'ü' => 'u', 'ý' => 'y', 'ž' => 'z',
    ];

    $value = strtr($value, $map);
    $value = preg_replace('~[^a-z0-9]+~', '-', $value);
    $value = trim((string)$value, '-');

    return $value !== '' ? $value : 'event';
}

function events_get_current_dashboard_event(): ?array
{
    $sql = "
        SELECT
            e.*,
            leader.jmeno AS leader_jmeno,
            leader.prijmeni AS leader_prijmeni,
            leader.mobile AS leader_mobile
        FROM events e
        LEFT JOIN users leader ON leader.id = e.leader_user_id
        WHERE e.status = 'active'
        ORDER BY e.is_temporary ASC, e.id DESC
        LIMIT 1
    ";

    $row = db()->query($sql)->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function events_participants_by_role(int $eventId, string $roleInEvent): array
{
    $stmt = db()->prepare("
        SELECT
            u.id,
            u.jmeno,
            u.prijmeni,
            u.mobile,
            u.user,
            u.ftp_user,
            eu.role_in_event,
            eu.runner
        FROM event_users eu
        INNER JOIN users u ON u.id = eu.user_id
        WHERE eu.event_id = ?
          AND eu.role_in_event = ?
        ORDER BY u.prijmeni, u.jmeno, u.user
    ");
    $stmt->execute([$eventId, $roleInEvent]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}