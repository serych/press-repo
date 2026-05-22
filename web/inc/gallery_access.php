<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/events.php';
require_once __DIR__ . '/auth.php';

const GALLERY_ACCESS_TOKEN_ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';
const GALLERY_ACCESS_DEFAULT_CLOSE_DAYS = 3;

function gallery_access_get(int $eventId): ?array
{
    $stmt = db()->prepare("
        SELECT *
        FROM event_gallery_access
        WHERE event_id = ?
        LIMIT 1
    ");
    $stmt->execute([$eventId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function gallery_access_find_by_token(string $token): ?array
{
    $token = strtolower(trim($token));
    if (!preg_match('~^[a-z0-9]{3}$~', $token)) {
        return null;
    }

    $stmt = db()->prepare("
        SELECT
            ga.*,
            e.title AS event_title,
            e.description AS event_description,
            e.status AS event_status
        FROM event_gallery_access ga
        INNER JOIN events e ON e.id = ga.event_id
        WHERE ga.token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function gallery_access_find_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $stmt = db()->prepare("
        SELECT
            ga.*,
            e.title AS event_title,
            e.description AS event_description,
            e.status AS event_status
        FROM event_gallery_access ga
        INNER JOIN events e ON e.id = ga.event_id
        WHERE ga.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function gallery_access_unavailable_reason(?array $access): ?string
{
    if (!$access) {
        return 'Tento odkaz do galerie neexistuje.';
    }

    if (empty($access['is_enabled'])) {
        return 'Přístup do této galerie je vypnutý.';
    }

    if (!empty($access['expires_at'])) {
        try {
            $expiresAt = new DateTimeImmutable((string)$access['expires_at']);
            if ($expiresAt <= new DateTimeImmutable('now')) {
                return 'Přístup do této galerie už byl uzavřen.';
            }
        } catch (Throwable) {
            return 'Přístup do této galerie není správně nastavený.';
        }
    }

    return null;
}

function gallery_access_public_url_token(): string
{
    $token = (string)($_GET['token'] ?? '');
    if ($token !== '') {
        return strtolower(trim($token));
    }

    $path = trim((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
    if (preg_match('~^g/([a-z0-9]{3})$~', $path, $matches)) {
        return $matches[1];
    }

    return '';
}

function gallery_access_client_ip(): ?string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return null;
    }

    $packed = inet_pton($ip);
    return $packed === false ? null : $packed;
}

function gallery_access_session_key(int $accessId): string
{
    return 'journalist_gallery_access_' . $accessId;
}

function gallery_access_active_session_key(): string
{
    return 'journalist_gallery_active_access_id';
}

function gallery_access_current_session(array $access): ?array
{
    start_session_if_needed();

    $accessId = (int)$access['id'];
    $eventId = (int)$access['event_id'];
    $sessionToken = (string)($_SESSION[gallery_access_session_key($accessId)] ?? '');
    if ($sessionToken === '') {
        return null;
    }

    $stmt = db()->prepare("
        SELECT *
        FROM journalist_gallery_sessions
        WHERE event_gallery_access_id = ?
          AND event_id = ?
          AND session_token = ?
        LIMIT 1
    ");
    $stmt->execute([$accessId, $eventId, $sessionToken]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function gallery_access_start_public_session(array $access): array
{
    start_session_if_needed();

    $existing = gallery_access_current_session($access);
    if ($existing) {
        db()->prepare("
            UPDATE journalist_gallery_sessions
            SET last_seen_at = NOW()
            WHERE id = ?
        ")->execute([(int)$existing['id']]);

        $_SESSION[gallery_access_active_session_key()] = (int)$access['id'];
        return $existing;
    }

    $sessionToken = bin2hex(random_bytes(32));

    $stmt = db()->prepare("
        INSERT INTO journalist_gallery_sessions (
            event_gallery_access_id,
            event_id,
            session_token,
            ip,
            user_agent,
            started_at,
            last_seen_at
        ) VALUES (
            :event_gallery_access_id,
            :event_id,
            :session_token,
            :ip,
            :user_agent,
            NOW(),
            NOW()
        )
    ");
    $stmt->execute([
        ':event_gallery_access_id' => (int)$access['id'],
        ':event_id' => (int)$access['event_id'],
        ':session_token' => $sessionToken,
        ':ip' => gallery_access_client_ip(),
        ':user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    ]);

    $_SESSION[gallery_access_session_key((int)$access['id'])] = $sessionToken;
    $_SESSION[gallery_access_active_session_key()] = (int)$access['id'];

    return gallery_access_current_session($access) ?: [];
}

function gallery_access_is_public_session_allowed(array $access): bool
{
    if (gallery_access_unavailable_reason($access) !== null) {
        return false;
    }

    if (empty($access['pin_hash'])) {
        gallery_access_start_public_session($access);
        return true;
    }

    return gallery_access_current_session($access) !== null;
}

function gallery_access_current_public_access(): ?array
{
    start_session_if_needed();

    $accessId = (int)($_SESSION[gallery_access_active_session_key()] ?? 0);
    if ($accessId <= 0) {
        return null;
    }

    $access = gallery_access_find_by_id($accessId);
    if (!$access || gallery_access_unavailable_reason($access) !== null || !gallery_access_current_session($access)) {
        unset($_SESSION[gallery_access_active_session_key()]);
        return null;
    }

    return $access;
}

function gallery_access_require_login_or_public_access(): ?array
{
    if (is_logged_in()) {
        return null;
    }

    $access = gallery_access_current_public_access();
    if ($access) {
        return $access;
    }

    redirect('/login.php');
}

function gallery_access_public_access_allows_photo(?array $access, ?array $photo): bool
{
    if (!$access || !$photo) {
        return false;
    }

    return (int)($photo['event_id'] ?? 0) === (int)$access['event_id'];
}

function gallery_access_record_download(array $access, int $publishedPhotoId): void
{
    $session = gallery_access_start_public_session($access);
    $sessionId = !empty($session['id']) ? (int)$session['id'] : null;

    if ($sessionId !== null) {
        db()->prepare("
            UPDATE journalist_gallery_sessions
            SET last_seen_at = NOW(),
                first_downloaded_at = COALESCE(first_downloaded_at, NOW()),
                download_count = download_count + 1
            WHERE id = ?
        ")->execute([$sessionId]);
    }

    db()->prepare("
        INSERT INTO journalist_gallery_downloads (
            journalist_gallery_session_id,
            event_id,
            published_photo_id,
            ip,
            downloaded_at
        ) VALUES (?, ?, ?, ?, NOW())
    ")->execute([
        $sessionId,
        (int)$access['event_id'],
        $publishedPhotoId,
        gallery_access_client_ip(),
    ]);
}

function gallery_access_token_exists(string $token, ?int $excludeId = null): bool
{
    if ($excludeId !== null) {
        $stmt = db()->prepare("
            SELECT COUNT(*)
            FROM event_gallery_access
            WHERE token = ?
              AND id <> ?
        ");
        $stmt->execute([$token, $excludeId]);
    } else {
        $stmt = db()->prepare("
            SELECT COUNT(*)
            FROM event_gallery_access
            WHERE token = ?
        ");
        $stmt->execute([$token]);
    }

    return (int)$stmt->fetchColumn() > 0;
}

function gallery_access_generate_token(?int $excludeId = null): string
{
    $alphabet = GALLERY_ACCESS_TOKEN_ALPHABET;
    $max = strlen($alphabet) - 1;

    for ($attempt = 0; $attempt < 500; $attempt++) {
        $token = '';
        for ($i = 0; $i < 3; $i++) {
            $token .= $alphabet[random_int(0, $max)];
        }

        if (!gallery_access_token_exists($token, $excludeId)) {
            return $token;
        }
    }

    throw new RuntimeException('Nepodařilo se vygenerovat volný krátký odkaz.');
}

function gallery_access_normalize_close_days($value): int
{
    $days = (int)$value;
    if ($days < 0) {
        return 0;
    }
    if ($days > 365) {
        return 365;
    }
    return $days;
}

function gallery_access_calculate_expires_at(array $event, int $closeDays): ?string
{
    $base = (string)($event['ends_at'] ?? '');
    if ($base === '') {
        return null;
    }

    try {
        $timezone = new DateTimeZone(events_normalize_timezone((string)($event['timezone'] ?? '')));
        $date = new DateTimeImmutable($base, $timezone);
        return $date->modify('+' . $closeDays . ' days')->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

function gallery_access_url(array $access): string
{
    $base = rtrim((string)APP_URL, '/');
    return $base . '/g/' . rawurlencode((string)$access['token']);
}

function gallery_access_save(int $eventId, array $event, array $data, int $userId): array
{
    $existing = gallery_access_get($eventId);
    $id = $existing ? (int)$existing['id'] : null;
    $token = $existing ? (string)$existing['token'] : gallery_access_generate_token();

    $enabled = !empty($data['is_enabled']) ? 1 : 0;
    $closeDays = gallery_access_normalize_close_days($data['close_days_after_event'] ?? GALLERY_ACCESS_DEFAULT_CLOSE_DAYS);
    $pin = trim((string)($data['pin'] ?? ''));
    $pinHash = $pin === '' ? null : password_hash($pin, PASSWORD_BCRYPT);
    $expiresAt = gallery_access_calculate_expires_at($event, $closeDays);

    if ($existing) {
        $stmt = db()->prepare("
            UPDATE event_gallery_access
            SET is_enabled = :is_enabled,
                pin_hash = :pin_hash,
                close_days_after_event = :close_days_after_event,
                expires_at = :expires_at,
                updated_by_user_id = :updated_by_user_id,
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $id,
            ':is_enabled' => $enabled,
            ':pin_hash' => $pinHash,
            ':close_days_after_event' => $closeDays,
            ':expires_at' => $expiresAt,
            ':updated_by_user_id' => $userId > 0 ? $userId : null,
        ]);
    } else {
        $stmt = db()->prepare("
            INSERT INTO event_gallery_access (
                event_id,
                token,
                is_enabled,
                pin_hash,
                close_days_after_event,
                expires_at,
                created_by_user_id,
                updated_by_user_id,
                created_at,
                updated_at
            ) VALUES (
                :event_id,
                :token,
                :is_enabled,
                :pin_hash,
                :close_days_after_event,
                :expires_at,
                :created_by_user_id,
                :updated_by_user_id,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            ':event_id' => $eventId,
            ':token' => $token,
            ':is_enabled' => $enabled,
            ':pin_hash' => $pinHash,
            ':close_days_after_event' => $closeDays,
            ':expires_at' => $expiresAt,
            ':created_by_user_id' => $userId > 0 ? $userId : null,
            ':updated_by_user_id' => $userId > 0 ? $userId : null,
        ]);
    }

    return gallery_access_get($eventId) ?: [];
}

function gallery_access_regenerate_token(int $eventId, int $userId): array
{
    $existing = gallery_access_get($eventId);
    if (!$existing) {
        throw new RuntimeException('Nejdřív ulož nastavení žurnalistického přístupu.');
    }

    $token = gallery_access_generate_token((int)$existing['id']);
    $stmt = db()->prepare("
        UPDATE event_gallery_access
        SET token = :token,
            updated_by_user_id = :updated_by_user_id,
            updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => (int)$existing['id'],
        ':token' => $token,
        ':updated_by_user_id' => $userId > 0 ? $userId : null,
    ]);

    return gallery_access_get($eventId) ?: [];
}

function gallery_access_stats(int $eventId): array
{
    $stmt = db()->prepare("
        SELECT
            COUNT(*) AS sessions_total,
            SUM(CASE WHEN first_downloaded_at IS NOT NULL THEN 1 ELSE 0 END) AS sessions_with_download,
            COALESCE(SUM(download_count), 0) AS downloads_total
        FROM journalist_gallery_sessions
        WHERE event_id = ?
    ");
    $stmt->execute([$eventId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'sessions_total' => (int)($row['sessions_total'] ?? 0),
        'sessions_with_download' => (int)($row['sessions_with_download'] ?? 0),
        'downloads_total' => (int)($row['downloads_total'] ?? 0),
    ];
}
