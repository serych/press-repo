<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function chat_event_exists(int $eventId): bool
{
    $stmt = db()->prepare('SELECT id FROM events WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $eventId]);
    return (bool)$stmt->fetchColumn();
}

function chat_message_create(int $eventId, int $userId, string $message): int
{
    $message = trim($message);

    if ($message === '') {
        throw new RuntimeException('Prázdná zpráva.');
    }

    if (mb_strlen($message) > 2000) {
        $message = mb_substr($message, 0, 2000);
    }

    $stmt = db()->prepare('
        INSERT INTO event_chat_messages (event_id, user_id, message)
        VALUES (:event_id, :user_id, :message)
    ');
    $stmt->execute([
        'event_id' => $eventId,
        'user_id' => $userId,
        'message' => $message,
    ]);

    return (int)db()->lastInsertId();
}

function chat_messages_list(int $eventId, int $limit = 50, int $afterId = 0): array
{
    $limit = max(1, min(200, $limit));

    if ($afterId > 0) {
        $stmt = db()->prepare('
            SELECT
                m.id,
                m.event_id,
                m.user_id,
                m.message,
                m.created_at,
                u.jmeno,
                u.prijmeni,
                u.user
            FROM event_chat_messages m
            INNER JOIN users u ON u.id = m.user_id
            WHERE m.event_id = :event_id
              AND m.id > :after_id
            ORDER BY m.id ASC
            LIMIT ' . $limit
        );
        $stmt->execute([
            'event_id' => $eventId,
            'after_id' => $afterId,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    $stmt = db()->prepare('
        SELECT *
        FROM (
            SELECT
                m.id,
                m.event_id,
                m.user_id,
                m.message,
                m.created_at,
                u.jmeno,
                u.prijmeni,
                u.user
            FROM event_chat_messages m
            INNER JOIN users u ON u.id = m.user_id
            WHERE m.event_id = :event_id
            ORDER BY m.id DESC
            LIMIT ' . $limit . '
        ) t
        ORDER BY id ASC
    ');
    $stmt->execute([
        'event_id' => $eventId,
    ]);

    return $stmt->fetchAll() ?: [];
}

function chat_mark_read(int $eventId, int $userId, int $lastMessageId): void
{
    if ($eventId <= 0 || $userId <= 0 || $lastMessageId <= 0) {
        return;
    }

    $stmt = db()->prepare('
        INSERT INTO event_chat_reads (event_id, user_id, last_read_message_id)
        VALUES (:event_id, :user_id, :last_read_message_id)
        ON DUPLICATE KEY UPDATE
            last_read_message_id = GREATEST(last_read_message_id, VALUES(last_read_message_id)),
            updated_at = CURRENT_TIMESTAMP
    ');
    $stmt->execute([
        'event_id' => $eventId,
        'user_id' => $userId,
        'last_read_message_id' => $lastMessageId,
    ]);
}

function chat_unread_count_for_event(int $eventId, int $userId): int
{
    if ($eventId <= 0 || $userId <= 0) {
        return 0;
    }

    $stmt = db()->prepare('
        SELECT last_read_message_id
        FROM event_chat_reads
        WHERE event_id = :event_id
          AND user_id = :user_id
        LIMIT 1
    ');
    $stmt->execute([
        'event_id' => $eventId,
        'user_id' => $userId,
    ]);

    $lastReadId = (int)($stmt->fetchColumn() ?: 0);

    $stmt = db()->prepare('
        SELECT COUNT(*)
        FROM event_chat_messages
        WHERE event_id = :event_id
          AND id > :last_read_id
          AND user_id <> :user_id
    ');
    $stmt->execute([
        'event_id' => $eventId,
        'last_read_id' => $lastReadId,
        'user_id' => $userId,
    ]);

    return (int)$stmt->fetchColumn();
}

function chat_unread_summary_for_user(int $userId): array
{
    if ($userId <= 0) {
        return [
            'total' => 0,
            'events' => [],
        ];
    }

    $eventsStmt = db()->prepare('
        SELECT id, title
        FROM events
        ORDER BY id DESC
    ');
    $eventsStmt->execute();

    $events = $eventsStmt->fetchAll() ?: [];
    $resultEvents = [];
    $total = 0;

    foreach ($events as $event) {
        $eventId = (int)$event['id'];
        $count = chat_unread_count_for_event($eventId, $userId);

        if ($count > 0) {
            $resultEvents[] = [
                'event_id' => $eventId,
                'title' => (string)$event['title'],
                'unread_count' => $count,
            ];
            $total += $count;
        }
    }

    return [
        'total' => $total,
        'events' => $resultEvents,
    ];
}

function chat_delete_all_for_event(int $eventId): void
{
    if ($eventId <= 0) {
        return;
    }

    $stmt = db()->prepare('DELETE FROM event_chat_reads WHERE event_id = :event_id');
    $stmt->execute([
        'event_id' => $eventId,
    ]);

    $stmt = db()->prepare('DELETE FROM event_chat_messages WHERE event_id = :event_id');
    $stmt->execute([
        'event_id' => $eventId,
    ]);
}