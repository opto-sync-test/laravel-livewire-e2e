<?php

declare(strict_types=1);

function requiredEnvironment(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || $value === '') {
        throw new RuntimeException("missing {$name}");
    }

    return $value;
}

/** @return array{status: int, json: mixed} */
function request(string $method, string $url, string $anonKey, ?array $body = null): array
{
    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('failed to initialize HTTP client');
    }
    $headers = [
        'apikey: ' . $anonKey,
        'authorization: Bearer ' . $anonKey,
        'accept: application/json',
        'content-type: application/json',
        'prefer: return=representation',
    ];
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($body !== null) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
    }
    $responseBody = curl_exec($handle);
    if (!is_string($responseBody)) {
        $message = curl_error($handle);
        curl_close($handle);
        throw new RuntimeException('Supabase request failed: ' . $message);
    }
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    return [
        'status' => $status,
        'json' => $responseBody === '' ? null : json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR),
    ];
}

$apiUrl = rtrim(getenv('SUPABASE_URL') ?: requiredEnvironment('API_URL'), '/');
$anonKey = getenv('SUPABASE_ANON_KEY') ?: requiredEnvironment('ANON_KEY');
$databaseUrl = getenv('SUPABASE_DB_URL') ?: requiredEnvironment('DB_URL');
$id = 'laravel-' . bin2hex(random_bytes(8));

$insert = request('POST', $apiUrl . '/rest/v1/opto_documents', $anonKey, [[
    'id' => $id,
    'payload' => ['title' => 'Laravel Livewire offline edit'],
    'updated_at' => 1,
    'client_id' => 'laravel-livewire-e2e',
]]);
if ($insert['status'] !== 201 || ($insert['json'][0]['id'] ?? null) !== $id) {
    throw new RuntimeException('anonymous PostgREST insert did not satisfy the RLS contract');
}

$selected = request(
    'GET',
    $apiUrl . '/rest/v1/opto_documents?id=eq.' . rawurlencode($id) . '&select=id,payload,client_id',
    $anonKey,
);
if (
    $selected['status'] !== 200
    || ($selected['json'][0]['payload']['title'] ?? null) !== 'Laravel Livewire offline edit'
    || ($selected['json'][0]['client_id'] ?? null) !== 'laravel-livewire-e2e'
) {
    throw new RuntimeException('Supabase round trip changed the offline payload');
}

$database = parse_url($databaseUrl);
if (!is_array($database) || !isset($database['host'], $database['path'], $database['user'])) {
    throw new RuntimeException('DB_URL is not a PostgreSQL connection URL');
}
$dsn = sprintf(
    'pgsql:host=%s;port=%d;dbname=%s',
    $database['host'],
    $database['port'] ?? 5432,
    ltrim($database['path'], '/'),
);
$pdo = new PDO(
    $dsn,
    rawurldecode($database['user']),
    rawurldecode($database['pass'] ?? ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$published = $pdo->query(<<<'SQL'
    select exists (
      select 1 from pg_publication_tables
      where pubname = 'supabase_realtime'
        and schemaname = 'public'
        and tablename = 'opto_documents'
    )
SQL)->fetchColumn();
if (!in_array($published, [true, 't', '1', 1], true)) {
    throw new RuntimeException('opto_documents is not in the Supabase Realtime publication');
}

$deleted = request(
    'DELETE',
    $apiUrl . '/rest/v1/opto_documents?id=eq.' . rawurlencode($id),
    $anonKey,
);
if ($deleted['status'] !== 200) {
    throw new RuntimeException('anonymous PostgREST cleanup did not satisfy the RLS contract');
}

fwrite(STDOUT, "Local Supabase PostgREST, RLS, and Realtime publication contract passed\n");
