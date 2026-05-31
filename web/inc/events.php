<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const EVENTS_DEFAULT_TIMEZONE = 'Europe/Prague';

function events_default_timezone(): string
{
    return EVENTS_DEFAULT_TIMEZONE;
}

function events_timezone_identifiers(): array
{
    return DateTimeZone::listIdentifiers();
}

function events_timezone_is_valid(string $timezone): bool
{
    return in_array($timezone, events_timezone_identifiers(), true);
}

function events_normalize_timezone(?string $timezone): string
{
    $timezone = trim((string)$timezone);
    return events_timezone_is_valid($timezone) ? $timezone : events_default_timezone();
}

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

function events_get_other_active(?int $excludeId = null): ?array
{
    $sql = "
        SELECT *
        FROM events
        WHERE status = 'active'
    ";
    $params = [];

    if ($excludeId !== null) {
        $sql .= " AND id <> ?";
        $params[] = $excludeId;
    }

    $sql .= "
        ORDER BY is_temporary ASC, id DESC
        LIMIT 1
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function events_deactivate_other_active(?int $excludeId = null): int
{
    $sql = "
        UPDATE events
        SET status = 'finished'
        WHERE status = 'active'
    ";
    $params = [];

    if ($excludeId !== null) {
        $sql .= " AND id <> ?";
        $params[] = $excludeId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount();
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
            timezone,
            cav_gallery_url,
            gps_latitude,
            gps_latitude_ref,
            gps_longitude,
            gps_longitude_ref,
            gps_altitude,
            gps_altitude_ref,
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
            :timezone,
            :cav_gallery_url,
            :gps_latitude,
            :gps_latitude_ref,
            :gps_longitude,
            :gps_longitude_ref,
            :gps_altitude,
            :gps_altitude_ref,
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
        ':timezone'        => events_normalize_timezone($data['timezone'] ?? ''),
        ':cav_gallery_url' => $data['cav_gallery_url'] !== '' ? $data['cav_gallery_url'] : null,
        ':gps_latitude'    => $data['gps_latitude'] !== '' ? $data['gps_latitude'] : null,
        ':gps_latitude_ref' => $data['gps_latitude_ref'] !== '' ? $data['gps_latitude_ref'] : null,
        ':gps_longitude'   => $data['gps_longitude'] !== '' ? $data['gps_longitude'] : null,
        ':gps_longitude_ref' => $data['gps_longitude_ref'] !== '' ? $data['gps_longitude_ref'] : null,
        ':gps_altitude'    => $data['gps_altitude'] !== '' ? $data['gps_altitude'] : null,
        ':gps_altitude_ref' => $data['gps_altitude_ref'] !== '' ? $data['gps_altitude_ref'] : null,
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
            timezone = :timezone,
            cav_gallery_url = :cav_gallery_url,
            gps_latitude = :gps_latitude,
            gps_latitude_ref = :gps_latitude_ref,
            gps_longitude = :gps_longitude,
            gps_longitude_ref = :gps_longitude_ref,
            gps_altitude = :gps_altitude,
            gps_altitude_ref = :gps_altitude_ref,
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
        ':timezone'        => events_normalize_timezone($data['timezone'] ?? ''),
        ':cav_gallery_url' => $data['cav_gallery_url'] !== '' ? $data['cav_gallery_url'] : null,
        ':gps_latitude'    => $data['gps_latitude'] !== '' ? $data['gps_latitude'] : null,
        ':gps_latitude_ref' => $data['gps_latitude_ref'] !== '' ? $data['gps_latitude_ref'] : null,
        ':gps_longitude'   => $data['gps_longitude'] !== '' ? $data['gps_longitude'] : null,
        ':gps_longitude_ref' => $data['gps_longitude_ref'] !== '' ? $data['gps_longitude_ref'] : null,
        ':gps_altitude'    => $data['gps_altitude'] !== '' ? $data['gps_altitude'] : null,
        ':gps_altitude_ref' => $data['gps_altitude_ref'] !== '' ? $data['gps_altitude_ref'] : null,
        ':leader_user_id'  => $data['leader_user_id'] ?: null,
        ':status'          => $data['status'],
        ':is_public'       => !empty($data['is_public']) ? 1 : 0,
        ':is_temporary'    => !empty($data['is_temporary']) ? 1 : 0,
    ]);
}

function events_gps_format_seconds(float $seconds): string
{
    $formatted = number_format($seconds, 4, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
}

function events_gps_decimal_to_exif(float $decimal): string
{
    $absolute = abs($decimal);
    $degrees = (int)floor($absolute);
    $minutesFull = ($absolute - $degrees) * 60;
    $minutes = (int)floor($minutesFull);
    $seconds = ($minutesFull - $minutes) * 60;

    return sprintf('%d deg %d\' %s"', $degrees, $minutes, events_gps_format_seconds($seconds));
}

function events_gps_parse_part(string $value, string $axis): ?array
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (!preg_match('~^([NSEW])?\s*([+-]?\d+(?:\.\d+)?)\s*([NSEW])?$~i', $value, $matches)) {
        return null;
    }

    $prefixRef = strtoupper((string)($matches[1] ?? ''));
    $decimal = (float)$matches[2];
    $suffixRef = strtoupper((string)($matches[3] ?? ''));
    $ref = $suffixRef !== '' ? $suffixRef : $prefixRef;

    $validRefs = $axis === 'lat' ? ['N', 'S'] : ['E', 'W'];
    if ($ref !== '' && !in_array($ref, $validRefs, true)) {
        return null;
    }

    if ($ref === '') {
        $ref = $axis === 'lat'
            ? ($decimal < 0 ? 'S' : 'N')
            : ($decimal < 0 ? 'W' : 'E');
    }

    $absolute = abs($decimal);
    $max = $axis === 'lat' ? 90 : 180;
    if ($absolute > $max) {
        return null;
    }

    return [
        'value' => events_gps_decimal_to_exif($absolute),
        'ref' => $ref,
    ];
}

function events_gps_parse_coordinates(string $value): ?array
{
    $value = trim($value);
    if ($value === '') {
        return [
            'gps_latitude' => '',
            'gps_latitude_ref' => '',
            'gps_longitude' => '',
            'gps_longitude_ref' => '',
        ];
    }

    $parts = preg_split('~\s*[,;]\s*~', $value);
    if (!is_array($parts) || count($parts) !== 2) {
        return null;
    }

    $first = events_gps_parse_part((string)$parts[0], 'lat');
    $second = events_gps_parse_part((string)$parts[1], 'lon');
    if (!$first || !$second) {
        return null;
    }

    return [
        'gps_latitude' => $first['value'],
        'gps_latitude_ref' => $first['ref'],
        'gps_longitude' => $second['value'],
        'gps_longitude_ref' => $second['ref'],
    ];
}

function events_gps_exif_to_decimal(string $value): ?float
{
    if (!preg_match('~^\s*(\d+(?:\.\d+)?)\s*deg\s*(\d+(?:\.\d+)?)\'\s*(\d+(?:\.\d+)?)"?\s*$~i', $value, $matches)) {
        return null;
    }

    $degrees = (float)$matches[1];
    $minutes = (float)$matches[2];
    $seconds = (float)$matches[3];

    return $degrees + ($minutes / 60) + ($seconds / 3600);
}

function events_gps_format_decimal(float $value): string
{
    $formatted = number_format($value, 7, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
}

function events_gps_coordinates_input(array $event): string
{
    $latitude = trim((string)($event['gps_latitude'] ?? ''));
    $latitudeRef = trim((string)($event['gps_latitude_ref'] ?? ''));
    $longitude = trim((string)($event['gps_longitude'] ?? ''));
    $longitudeRef = trim((string)($event['gps_longitude_ref'] ?? ''));

    if ($latitude === '' && $latitudeRef === '' && $longitude === '' && $longitudeRef === '') {
        return '';
    }

    $latitudeDecimal = events_gps_exif_to_decimal($latitude);
    $longitudeDecimal = events_gps_exif_to_decimal($longitude);
    if ($latitudeDecimal !== null && $longitudeDecimal !== null) {
        return events_gps_format_decimal($latitudeDecimal) . $latitudeRef . ', '
            . events_gps_format_decimal($longitudeDecimal) . $longitudeRef;
    }

    return trim($latitude . $latitudeRef . ', ' . $longitude . $longitudeRef);
}

function events_gps_format_altitude(float $altitude): string
{
    $formatted = number_format(abs($altitude), 2, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
}

function events_gps_parse_altitude(string $value): ?array
{
    $value = trim($value);
    if ($value === '') {
        return [
            'gps_altitude' => '',
            'gps_altitude_ref' => '',
        ];
    }

    $value = str_replace(',', '.', $value);
    $value = preg_replace('~\s*m$~i', '', $value) ?? $value;
    $value = trim($value);

    if (!preg_match('~^[+-]?\d+(?:\.\d+)?$~', $value)) {
        return null;
    }

    $altitude = (float)$value;
    return [
        'gps_altitude' => events_gps_format_altitude($altitude),
        'gps_altitude_ref' => $altitude < 0 ? '1' : '0',
    ];
}

function events_gps_altitude_input(array $event): string
{
    $altitude = trim((string)($event['gps_altitude'] ?? ''));
    if ($altitude === '') {
        return '';
    }

    $ref = trim((string)($event['gps_altitude_ref'] ?? '0'));
    $prefix = $ref === '1' ? '-' : '';

    return $prefix . $altitude;
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

function events_users_for_picker(?string $roleInEvent = null): array
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
        WHERE u.is_active = 1
        ORDER BY
            u.prijmeni,
            u.jmeno,
            u.user
    ";

    $users = db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    if ($roleInEvent === 'editor') {
        $users = array_values(array_filter(
            $users,
            static fn(array $user): bool => (string)($user['role_code'] ?? '') !== 'photographer'
        ));
    }

    return $users;
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

function events_participants_get_runner_user_ids(int $eventId): array
{
    $stmt = db()->prepare("
        SELECT user_id
        FROM event_users
        WHERE event_id = ?
          AND role_in_event = 'photographer'
          AND runner = 1
        ORDER BY user_id
    ");
    $stmt->execute([$eventId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function events_filter_editor_ids(array $userIds): array
{
    $userIds = array_values(array_unique(array_map('intval', $userIds)));
    $userIds = array_values(array_filter($userIds, static fn(int $id): bool => $id > 0));

    if ($userIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));

    $stmt = db()->prepare("
        SELECT u.id
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE u.id IN ($placeholders)
          AND u.is_active = 1
          AND r.code <> 'photographer'
    ");
    $stmt->execute($userIds);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function events_filter_photographer_ids(array $userIds): array
{
    $userIds = array_values(array_unique(array_map('intval', $userIds)));
    $userIds = array_values(array_filter($userIds, static fn(int $id): bool => $id > 0));

    if ($userIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));

    $stmt = db()->prepare("
        SELECT u.id
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE u.id IN ($placeholders)
          AND u.is_active = 1
          AND r.code <> 'journalist'
    ");
    $stmt->execute($userIds);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function events_participants_save(int $eventId, array $photographerIds, array $editorIds, array $runnerUserIds = []): void
{
    $pdo = db();

    $photographerIds = events_filter_photographer_ids($photographerIds);
    $editorIds = events_filter_editor_ids($editorIds);

    $runnerUserIds = array_values(array_unique(array_map('intval', $runnerUserIds)));
    $runnerUserIds = array_values(array_filter(
        $runnerUserIds,
        static fn(int $id): bool => $id > 0 && in_array($id, $photographerIds, true)
    ));

    $runnerLookup = array_fill_keys($runnerUserIds, true);

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
            $insert->execute([
                $eventId,
                $userId,
                'photographer',
                isset($runnerLookup[$userId]) ? 1 : 0,
            ]);
        }

        foreach ($editorIds as $userId) {
            $insert->execute([
                $eventId,
                $userId,
                'editor',
                0,
            ]);
        }

        events_refresh_photo_assignment_flags($eventId, $pdo);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function events_refresh_photo_assignment_flags(int $eventId, ?PDO $pdo = null): void
{
    if ($eventId <= 0) {
        return;
    }

    $pdo = $pdo ?? db();

    $stmt = $pdo->prepare("
        UPDATE photos p
        LEFT JOIN event_users eu
            ON eu.event_id = p.event_id
           AND eu.user_id = p.user_id
           AND eu.role_in_event = 'photographer'
        SET
            p.event_photographer_allowed = IF(eu.user_id IS NULL, 0, 1),
            p.status = IF(eu.user_id IS NULL AND p.status = 'locked', 'ready', p.status),
            p.locked_by_user_id = IF(eu.user_id IS NULL, NULL, p.locked_by_user_id),
            p.locked_at = IF(eu.user_id IS NULL, NULL, p.locked_at)
        WHERE p.event_id = ?
    ");
    $stmt->execute([$eventId]);
}

function events_stats_summary(int $eventId): array
{
    $event = events_get($eventId);

    $stmt = db()->prepare("
        SELECT
            COUNT(*) AS uploaded_total,
            SUM(CASE WHEN status = 'downloaded' THEN 1 ELSE 0 END) AS downloaded_total
        FROM photos
        WHERE event_id = ?
    ");
    $stmt->execute([$eventId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $uploadedTotal = (int)($row['uploaded_total'] ?? 0);
    $downloadedTotal = (int)($row['downloaded_total'] ?? 0);

    $publishedStmt = db()->prepare("
        SELECT COUNT(*) AS published_total
        FROM published_photos
        WHERE event_id = ?
          AND status = 'ready'
    ");
    $publishedStmt->execute([$eventId]);
    $publishedRow = $publishedStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $publishedTotal = (int)($publishedRow['published_total'] ?? 0);

    $workflowStmt = db()->prepare("
        SELECT TIMESTAMPDIFF(SECOND, captured_at, published_at) AS workflow_seconds
        FROM published_photos
        WHERE event_id = ?
          AND status = 'ready'
          AND captured_at IS NOT NULL
          AND published_at IS NOT NULL
        ORDER BY workflow_seconds ASC
    ");
    $workflowStmt->execute([$eventId]);
    $workflowSeconds = array_map(
        'intval',
        array_column($workflowStmt->fetchAll(PDO::FETCH_ASSOC), 'workflow_seconds')
    );
    $workflowCount = count($workflowSeconds);
    $workflowMin = $workflowCount > 0 ? $workflowSeconds[0] : null;
    $workflowMax = $workflowCount > 0 ? $workflowSeconds[$workflowCount - 1] : null;
    $workflowMedian = null;

    if ($workflowCount > 0) {
        $middle = intdiv($workflowCount, 2);
        if ($workflowCount % 2 === 1) {
            $workflowMedian = $workflowSeconds[$middle];
        } else {
            $workflowMedian = (int)round(($workflowSeconds[$middle - 1] + $workflowSeconds[$middle]) / 2);
        }
    }

    if ($uploadedTotal === 0 && $event && !empty($event['archived_at'])) {
        return [
            'uploaded_total'   => (int)($event['archived_uploaded_total'] ?? 0),
            'downloaded_total' => (int)($event['archived_downloaded_total'] ?? 0),
            'published_total'  => $publishedTotal,
            'workflow_min'     => $workflowMin,
            'workflow_max'     => $workflowMax,
            'workflow_median'  => $workflowMedian,
        ];
    }

    return [
        'uploaded_total'   => $uploadedTotal,
        'downloaded_total' => $downloadedTotal,
        'published_total'  => $publishedTotal,
        'workflow_min'     => $workflowMin,
        'workflow_max'     => $workflowMax,
        'workflow_median'  => $workflowMedian,
    ];
}

function events_format_duration(?int $seconds): string
{
    if ($seconds === null) {
        return '—';
    }

    $prefix = '';
    if ($seconds < 0) {
        $prefix = '-';
        $seconds = abs($seconds);
    }

    $hours = intdiv($seconds, 3600);
    $seconds %= 3600;
    $minutes = intdiv($seconds, 60);
    $seconds %= 60;

    if ($hours > 0) {
        return sprintf('%s%d:%02d:%02d', $prefix, $hours, $minutes, $seconds);
    }

    return sprintf('%s%02d:%02d', $prefix, $minutes, $seconds);
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
            COALESCE(uploaded.uploaded_count, 0) AS uploaded_count,
            COALESCE(published.published_count, 0) AS published_count
        FROM event_users eu
        INNER JOIN users u ON u.id = eu.user_id
        LEFT JOIN (
            SELECT
                user_id,
                COUNT(*) AS uploaded_count
            FROM photos
            WHERE event_id = ?
              AND user_id IS NOT NULL
            GROUP BY user_id
        ) uploaded ON uploaded.user_id = eu.user_id
        LEFT JOIN (
            SELECT
                p.user_id,
                COUNT(pp.id) AS published_count
            FROM published_photos pp
            INNER JOIN photos p ON p.id = pp.source_photo_id
            WHERE pp.event_id = ?
              AND pp.status = 'ready'
              AND p.event_id = ?
              AND p.user_id IS NOT NULL
            GROUP BY p.user_id
        ) published ON published.user_id = eu.user_id
        WHERE eu.event_id = ?
          AND eu.role_in_event = 'photographer'
        GROUP BY
            u.id,
            u.jmeno,
            u.prijmeni,
            u.mobile,
            u.user,
            u.ftp_user,
            eu.runner,
            uploaded.uploaded_count,
            published.published_count
        ORDER BY u.prijmeni, u.jmeno, u.user
    ");
    $stmt->execute([$eventId, $eventId, $eventId, $eventId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function events_stats_editors(int $eventId): array
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
            eu.runner,
            COALESCE(downloaded.downloaded_count, 0) AS downloaded_count,
            COALESCE(published.published_count, 0) AS published_count
        FROM event_users eu
        INNER JOIN users u ON u.id = eu.user_id
        LEFT JOIN (
            SELECT
                downloaded_by_user_id AS user_id,
                COUNT(*) AS downloaded_count
            FROM photos
            WHERE event_id = ?
              AND downloaded_by_user_id IS NOT NULL
            GROUP BY downloaded_by_user_id
        ) downloaded ON downloaded.user_id = eu.user_id
        LEFT JOIN (
            SELECT
                uploaded_by_user_id AS user_id,
                COUNT(*) AS published_count
            FROM published_photos
            WHERE event_id = ?
              AND status = 'ready'
              AND uploaded_by_user_id IS NOT NULL
            GROUP BY uploaded_by_user_id
        ) published ON published.user_id = eu.user_id
        WHERE eu.event_id = ?
          AND eu.role_in_event = 'editor'
        GROUP BY
            u.id,
            u.jmeno,
            u.prijmeni,
            u.mobile,
            u.user,
            u.ftp_user,
            eu.role_in_event,
            eu.runner,
            downloaded.downloaded_count,
            published.published_count
        ORDER BY u.prijmeni, u.jmeno, u.user
    ");
    $stmt->execute([$eventId, $eventId, $eventId]);

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

function events_report_rows(int $eventId): array
{
    if ($eventId <= 0) {
        return [];
    }

    $stmt = db()->prepare("
        SELECT
            p.id AS photo_id,
            p.filename AS source_filename,
            p.ftp_user,
            p.status AS source_status,
            p.captured_at,
            p.uploaded_at,
            p.downloaded_at,
            p.exif_problem,
            p.exif_problem_note,
            p.uploaded_by_role,
            author.user AS author_user,
            author.jmeno AS author_jmeno,
            author.prijmeni AS author_prijmeni,
            editor.user AS editor_user,
            editor.jmeno AS editor_jmeno,
            editor.prijmeni AS editor_prijmeni,
            pp.id AS published_photo_id,
            pp.filename AS published_filename,
            pp.published_at,
            pp.download_count
        FROM photos p
        LEFT JOIN users author ON author.id = p.user_id
        LEFT JOIN users editor ON editor.id = p.downloaded_by_user_id
        LEFT JOIN published_photos pp
            ON pp.source_photo_id = p.id
           AND pp.event_id = p.event_id
           AND pp.status = 'ready'
        WHERE p.event_id = :event_id
        ORDER BY
            p.captured_at IS NULL,
            p.captured_at ASC,
            p.uploaded_at ASC,
            p.id ASC,
            pp.published_at ASC,
            pp.id ASC
    ");
    $stmt->execute([
        ':event_id' => $eventId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = db()->prepare("
        SELECT
            NULL AS photo_id,
            '' AS source_filename,
            '' AS ftp_user,
            '' AS source_status,
            pp.captured_at,
            pp.source_uploaded_at AS uploaded_at,
            pp.editor_downloaded_at AS downloaded_at,
            0 AS exif_problem,
            '' AS exif_problem_note,
            '' AS uploaded_by_role,
            pp.author_label AS author_user,
            '' AS author_jmeno,
            '' AS author_prijmeni,
            '' AS editor_user,
            '' AS editor_jmeno,
            '' AS editor_prijmeni,
            pp.id AS published_photo_id,
            pp.filename AS published_filename,
            pp.published_at,
            pp.download_count
        FROM published_photos pp
        WHERE pp.event_id = :event_id
          AND pp.status = 'ready'
          AND pp.source_photo_id IS NULL
        ORDER BY
            pp.captured_at IS NULL,
            pp.captured_at ASC,
            pp.published_at ASC,
            pp.id ASC
    ");
    $stmt->execute([
        ':event_id' => $eventId,
    ]);

    return array_merge($rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function events_report_person_label(array $row, string $prefix, string $fallbackKey = ''): string
{
    $name = trim(
        ((string)($row[$prefix . '_jmeno'] ?? '')) . ' ' .
        ((string)($row[$prefix . '_prijmeni'] ?? ''))
    );

    if ($name !== '') {
        return $name;
    }

    if ($fallbackKey !== '') {
        return (string)($row[$fallbackKey] ?? '');
    }

    return (string)($row[$prefix . '_user'] ?? '');
}

function events_report_download_filename(array $event, string $extension): string
{
    $slug = trim((string)($event['slug'] ?? ''));
    if ($slug === '') {
        $slug = 'event-' . (int)($event['id'] ?? 0);
    }

    $slug = preg_replace('~[^a-zA-Z0-9_-]+~', '-', $slug) ?? $slug;
    $slug = trim($slug, '-_');
    if ($slug === '') {
        $slug = 'event';
    }

    return $slug . '-prehled-fotek.' . $extension;
}

function events_report_photo_status_label(string $status): string
{
    return match ($status) {
        'uploaded' => 'nahráno',
        'processing' => 'zpracování',
        'ready' => 'připraveno',
        'selected' => 'ke stažení',
        'locked' => 'zamknuto',
        'downloaded' => 'staženo',
        'deleted' => 'smazáno',
        'error' => 'chyba',
        default => $status,
    };
}

function events_report_table(array $rows): array
{
    $header = [
        'ID fotky',
        'Původní název',
        'Autor',
        'FTP účet autora',
        'Nahráno',
        'Stav fotky',
        'Čas pořízení (EXIF)',
        'Čas nahrání do press centra',
        'Čas stažení fotoeditorem',
        'Fotoeditor',
        'Čas publikace do galerie',
        'Název publikované fotky',
        'Celkový počet stažení z galerie',
        'EXIF problém',
        'Poznámka EXIF',
    ];

    $table = [$header];

    foreach ($rows as $row) {
        $table[] = [
            (string)($row['photo_id'] ?? ''),
            (string)($row['source_filename'] ?? ''),
            events_report_person_label($row, 'author', 'ftp_user'),
            (string)($row['ftp_user'] ?? ''),
            (string)($row['uploaded_by_role'] ?? '') === 'runner' ? 'runner' : 'autor',
            $row['published_photo_id'] !== null
                ? 'publikováno'
                : events_report_photo_status_label((string)($row['source_status'] ?? '')),
            (string)($row['captured_at'] ?? ''),
            (string)($row['uploaded_at'] ?? ''),
            (string)($row['downloaded_at'] ?? ''),
            events_report_person_label($row, 'editor'),
            (string)($row['published_at'] ?? ''),
            (string)($row['published_filename'] ?? ''),
            $row['published_photo_id'] !== null ? (string)(int)($row['download_count'] ?? 0) : '',
            !empty($row['exif_problem']) ? 'ano' : 'ne',
            (string)($row['exif_problem_note'] ?? ''),
        ];
    }

    return $table;
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

function events_get_cleanup_files(int $eventId): array
{
    $stmt = db()->prepare("
        SELECT id, filepath, preview_filepath
        FROM photos
        WHERE event_id = ?
    ");
    $stmt->execute([$eventId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function events_get_cleanup_published_files(int $eventId): array
{
    $stmt = db()->prepare("
        SELECT id, filepath, preview_filepath
        FROM published_photos
        WHERE event_id = ?
    ");
    $stmt->execute([$eventId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function events_delete_file_if_exists(?string $path): void
{
    if ($path === null || trim($path) === '') {
        return;
    }

    if (is_file($path)) {
        @unlink($path);
    }
}

function events_overview_preview_path(string $previewPath): string
{
    $dir = dirname($previewPath);
    $filename = basename($previewPath);
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $base = pathinfo($filename, PATHINFO_FILENAME);

    if ($ext === '') {
        $ext = 'jpg';
    }

    return $dir . '/' . $base . '-small.' . $ext;
}

function events_published_small_preview_path(string $filepath): string
{
    $dir = dirname($filepath);
    $filename = basename($filepath);
    $base = pathinfo($filename, PATHINFO_FILENAME);

    return $dir . '/' . $base . '-small.jpg';
}

function events_delete_empty_parent_dirs(array $paths): void
{
    $dirs = [];

    foreach ($paths as $path) {
        if ($path === null || trim((string)$path) === '') {
            continue;
        }

        $dir = dirname((string)$path);
        if ($dir !== '' && $dir !== '.' && $dir !== DIRECTORY_SEPARATOR) {
            $dirs[$dir] = true;
        }
    }

    $dirList = array_keys($dirs);
    usort(
        $dirList,
        static fn(string $a, string $b): int => strlen($b) <=> strlen($a)
    );

    foreach ($dirList as $dir) {
        if (@is_dir($dir)) {
            $items = @scandir($dir);
            if (is_array($items) && count($items) === 2) {
                @rmdir($dir);
            }
        }
    }
}

function events_delete_download_jobs_without_items(int $eventId): void
{
    $stmt = db()->prepare("
        DELETE dj
        FROM download_jobs dj
        LEFT JOIN download_job_items dji ON dji.job_id = dj.id
        WHERE dj.user_id IN (
            SELECT DISTINCT eu.user_id
            FROM event_users eu
            WHERE eu.event_id = ?
        )
          AND dji.id IS NULL
    ");
    $stmt->execute([$eventId]);
}

function events_cleanup_photos_of_event(int $eventId): array
{
    $pdo = db();
    $files = events_get_cleanup_files($eventId);

    $deletedPhotos = count($files);
    $deletedFiles = 0;
    $deletedPreviews = 0;
    $pathsForDirCleanup = [];

    foreach ($files as $file) {
        $filepath = (string)($file['filepath'] ?? '');
        $previewPath = (string)($file['preview_filepath'] ?? '');
        $overviewPreviewPath = $previewPath !== '' ? events_overview_preview_path($previewPath) : '';

        if ($filepath !== '' && is_file($filepath)) {
            @unlink($filepath);
            $deletedFiles++;
        }

        if ($previewPath !== '' && is_file($previewPath)) {
            @unlink($previewPath);
            $deletedPreviews++;
        }

        if ($overviewPreviewPath !== '' && is_file($overviewPreviewPath)) {
            @unlink($overviewPreviewPath);
            $deletedPreviews++;
        }

        if ($filepath !== '') {
            $pathsForDirCleanup[] = $filepath;
        }
        if ($previewPath !== '') {
            $pathsForDirCleanup[] = $previewPath;
        }
        if ($overviewPreviewPath !== '') {
            $pathsForDirCleanup[] = $overviewPreviewPath;
        }
    }

    $pdo->beginTransaction();

    try {
        $deletePhotoLogs = $pdo->prepare("
            DELETE pl
            FROM photo_log pl
            INNER JOIN photos p ON p.id = pl.photo_id
            WHERE p.event_id = ?
        ");
        $deletePhotoLogs->execute([$eventId]);

        $deleteDownloadJobItems = $pdo->prepare("
            DELETE dji
            FROM download_job_items dji
            INNER JOIN photos p ON p.id = dji.photo_id
            WHERE p.event_id = ?
        ");
        $deleteDownloadJobItems->execute([$eventId]);

        $deletePhotos = $pdo->prepare("
            DELETE FROM photos
            WHERE event_id = ?
        ");
        $deletePhotos->execute([$eventId]);

        events_delete_download_jobs_without_items($eventId);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    events_delete_empty_parent_dirs($pathsForDirCleanup);

    return [
        'deleted_photos'   => $deletedPhotos,
        'deleted_files'    => $deletedFiles,
        'deleted_previews' => $deletedPreviews,
    ];
}

function events_cleanup_published_gallery(int $eventId): array
{
    $pdo = db();
    $files = events_get_cleanup_published_files($eventId);

    $deletedPublishedPhotos = count($files);
    $deletedFiles = 0;
    $deletedPreviews = 0;
    $pathsForDirCleanup = [];

    foreach ($files as $file) {
        $filepath = (string)($file['filepath'] ?? '');
        $previewPath = (string)($file['preview_filepath'] ?? '');
        $smallPreviewPath = $filepath !== '' ? events_published_small_preview_path($filepath) : '';

        if ($filepath !== '' && is_file($filepath)) {
            @unlink($filepath);
            $deletedFiles++;
        }

        if ($previewPath !== '' && is_file($previewPath)) {
            @unlink($previewPath);
            $deletedPreviews++;
        }

        if ($smallPreviewPath !== '' && is_file($smallPreviewPath)) {
            @unlink($smallPreviewPath);
            $deletedPreviews++;
        }

        if ($filepath !== '') {
            $pathsForDirCleanup[] = $filepath;
        }
        if ($previewPath !== '') {
            $pathsForDirCleanup[] = $previewPath;
        }
        if ($smallPreviewPath !== '') {
            $pathsForDirCleanup[] = $smallPreviewPath;
        }
    }

    $pdo->beginTransaction();

    try {
        $deletePublishedLogs = $pdo->prepare("
            DELETE ppl
            FROM published_photo_log ppl
            INNER JOIN published_photos pp ON pp.id = ppl.published_photo_id
            WHERE pp.event_id = ?
        ");
        $deletePublishedLogs->execute([$eventId]);

        $deletePublishedPhotos = $pdo->prepare("
            DELETE FROM published_photos
            WHERE event_id = ?
        ");
        $deletePublishedPhotos->execute([$eventId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    events_delete_empty_parent_dirs($pathsForDirCleanup);

    return [
        'deleted_published_photos' => $deletedPublishedPhotos,
        'deleted_files'            => $deletedFiles,
        'deleted_previews'         => $deletedPreviews,
    ];
}

function events_cleanup_test_data(int $eventId): array
{
    $event = events_get($eventId);
    if (!$event) {
        throw new RuntimeException('Event nebyl nalezen.');
    }

    return events_cleanup_photos_of_event($eventId);
}

function events_archive(int $eventId): array
{
    $event = events_get($eventId);
    if (!$event) {
        throw new RuntimeException('Event nebyl nalezen.');
    }

    $summary = events_stats_summary($eventId);
    $cleanup = events_cleanup_photos_of_event($eventId);

    $stmt = db()->prepare("
        UPDATE events
        SET
            archived_uploaded_total = :uploaded,
            archived_downloaded_total = :downloaded,
            archived_at = NOW(),
            status = 'finished'
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':uploaded'   => (int)$summary['uploaded_total'],
        ':downloaded' => (int)$summary['downloaded_total'],
        ':id'         => $eventId,
    ]);

    return [
        'archived_uploaded_total'   => (int)$summary['uploaded_total'],
        'archived_downloaded_total' => (int)$summary['downloaded_total'],
        'deleted_photos'            => (int)$cleanup['deleted_photos'],
        'deleted_files'             => (int)$cleanup['deleted_files'],
        'deleted_previews'          => (int)$cleanup['deleted_previews'],
    ];
}
