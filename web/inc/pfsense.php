<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$pfsenseLocalConfig = __DIR__ . '/../config/pfsense.local.php';
if (is_readable($pfsenseLocalConfig)) {
    require_once $pfsenseLocalConfig;
}

const PFSENSE_FTP_FIREWALL_RULE_DESCRIPTIONS = [
    'FTP control press centrum',
    'FTP passive press centrum',
];

const PFSENSE_FTP_NAT_RULE_DESCRIPTIONS = [
    'NAT FTP control press centrum',
    'NAT FTP passive press centrum',
];

const PFSENSE_FTP_NAT_PORT_FORWARD_DESCRIPTIONS = [
    'FTP control press centrum',
    'FTP passive press centrum',
];

function pfsense_is_configured(): bool
{
    return defined('PFSENSE_API_BASE_URL')
        && defined('PFSENSE_API_KEY')
        && trim((string)PFSENSE_API_BASE_URL) !== ''
        && trim((string)PFSENSE_API_KEY) !== '';
}

function pfsense_api_request(string $method, string $path, array $query = [], ?array $payload = null): array
{
    if (!pfsense_is_configured()) {
        throw new RuntimeException('pfSense API není nakonfigurované.');
    }

    $baseUrl = rtrim((string)PFSENSE_API_BASE_URL, '/');
    $url = $baseUrl . '/' . ltrim($path, '/');
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    $headers = [
        'Accept: application/json',
        'x-api-key: ' . (string)PFSENSE_API_KEY,
    ];

    $content = null;
    if ($payload !== null) {
        $content = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($content)) {
            throw new RuntimeException('Nepodařilo se připravit pfSense API požadavek.');
        }
        $headers[] = 'Content-Type: application/json';
    }

    $context = stream_context_create([
        'http' => [
            'method' => strtoupper($method),
            'header' => implode("\r\n", $headers),
            'content' => $content,
            'ignore_errors' => true,
            'timeout' => 8,
        ],
        'ssl' => [
            'verify_peer' => defined('PFSENSE_API_VERIFY_TLS') ? (bool)PFSENSE_API_VERIFY_TLS : true,
            'verify_peer_name' => defined('PFSENSE_API_VERIFY_TLS') ? (bool)PFSENSE_API_VERIFY_TLS : true,
        ],
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    $statusCode = 0;
    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~', $header, $matches)) {
            $statusCode = (int)$matches[1];
            break;
        }
    }

    if (!is_string($responseBody)) {
        throw new RuntimeException('pfSense API není dostupné.');
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('pfSense API vrátilo nečitelnou odpověď.');
    }

    $apiCode = (int)($decoded['code'] ?? $statusCode);
    if ($apiCode < 200 || $apiCode >= 300) {
        $message = trim((string)($decoded['message'] ?? ''));
        throw new RuntimeException($message !== '' ? $message : 'pfSense API vrátilo chybu ' . $apiCode . '.');
    }

    return $decoded;
}

function pfsense_get_firewall_rules(): array
{
    $response = pfsense_api_request('GET', '/firewall/rules');
    return is_array($response['data'] ?? null) ? $response['data'] : [];
}

function pfsense_find_rules_by_description(array $rules, array $descriptions): array
{
    $found = [];

    foreach ($rules as $rule) {
        if (!is_array($rule)) {
            continue;
        }

        $description = (string)($rule['descr'] ?? '');
        if (in_array($description, $descriptions, true)) {
            $found[$description] = $rule;
        }
    }

    return $found;
}

function pfsense_ftp_status(): array
{
    if (!pfsense_is_configured()) {
        return [
            'configured' => false,
            'state' => 'unconfigured',
            'message' => 'pfSense API nenakonfigurováno',
            'rules' => [],
            'missing' => array_merge(PFSENSE_FTP_FIREWALL_RULE_DESCRIPTIONS, PFSENSE_FTP_NAT_RULE_DESCRIPTIONS),
        ];
    }

    try {
        $expectedDescriptions = array_merge(PFSENSE_FTP_FIREWALL_RULE_DESCRIPTIONS, PFSENSE_FTP_NAT_RULE_DESCRIPTIONS);
        $rules = pfsense_get_firewall_rules();
        $found = pfsense_find_rules_by_description($rules, $expectedDescriptions);
        $missing = array_values(array_diff($expectedDescriptions, array_keys($found)));

        if ($missing !== []) {
            return [
                'configured' => true,
                'state' => 'missing',
                'message' => 'Chybí pravidla: ' . implode(', ', $missing),
                'rules' => $found,
                'missing' => $missing,
            ];
        }

        $disabledCount = 0;
        foreach ($found as $rule) {
            if (!empty($rule['disabled'])) {
                $disabledCount++;
            }
        }

        if ($disabledCount === 0) {
            $state = 'enabled';
            $message = 'FTP přístup je zapnutý';
        } elseif ($disabledCount === count($found)) {
            $state = 'disabled';
            $message = 'FTP přístup je vypnutý';
        } else {
            $state = 'mixed';
            $message = 'FTP pravidla nejsou v jednotném stavu';
        }

        return [
            'configured' => true,
            'state' => $state,
            'message' => $message,
            'rules' => $found,
            'missing' => [],
        ];
    } catch (Throwable $e) {
        return [
            'configured' => true,
            'state' => 'error',
            'message' => 'pfSense API chyba: ' . $e->getMessage(),
            'rules' => [],
            'missing' => [],
        ];
    }
}

function pfsense_find_nat_port_forwards(array $descriptions): array
{
    $found = [];

    for ($id = 0; $id < 50; $id++) {
        try {
            $response = pfsense_api_request('GET', '/firewall/nat/port_forward', ['id' => $id]);
        } catch (Throwable) {
            continue;
        }

        $rule = $response['data'] ?? null;
        if (!is_array($rule)) {
            continue;
        }

        $description = (string)($rule['descr'] ?? '');
        if (in_array($description, $descriptions, true)) {
            $found[$description] = $rule;
        }
    }

    return $found;
}

function pfsense_set_ftp_enabled(bool $enabled): void
{
    $targetDisabled = !$enabled;

    $rules = pfsense_get_firewall_rules();
    $expectedDescriptions = array_merge(PFSENSE_FTP_FIREWALL_RULE_DESCRIPTIONS, PFSENSE_FTP_NAT_RULE_DESCRIPTIONS);
    $ftpRules = pfsense_find_rules_by_description($rules, $expectedDescriptions);

    $missing = array_values(array_diff($expectedDescriptions, array_keys($ftpRules)));

    if ($missing !== []) {
        throw new RuntimeException('Chybí pravidla: ' . implode(', ', $missing));
    }

    foreach ($ftpRules as $description => $rule) {
        $payload = [
            'id' => (int)$rule['id'],
            'disabled' => $targetDisabled,
        ];

        if (str_contains($description, 'passive')) {
            $payload['destination_port'] = '40000:40100';
        }

        pfsense_api_request('PATCH', '/firewall/rule', [], $payload);
    }

    pfsense_api_request('POST', '/firewall/apply');
}
