<?php
// blob_storage.php
// Integración con Vercel Blob vía REST API (sin SDK, sin dependencias).
// Requiere la variable de entorno BLOB_READ_WRITE_TOKEN (la agrega Vercel al crear el Blob store).

function blobToken() {
    $t = getenv('BLOB_READ_WRITE_TOKEN');
    return is_string($t) && $t !== '' ? $t : '';
}

function blobStoreIdFromToken($token) {
    $parts = explode('_', $token);
    return isset($parts[3]) ? $parts[3] : '';
}

function blobApiBase() {
    $base = getenv('VERCEL_BLOB_API_URL');
    if (is_string($base) && $base !== '') return rtrim($base, '/');
    return 'https://vercel.com/api/blob';
}

function blobRequest($method, $query, $headers = [], $body = null) {
    $token = blobToken();
    if ($token === '') {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'BLOB_READ_WRITE_TOKEN no configurado'];
    }
    $url = blobApiBase() . '/?' . $query;
    $allHeaders = [
        'authorization' => 'Bearer ' . $token,
        'x-api-version' => '12',
        'x-vercel-blob-store-id' => blobStoreIdFromToken($token),
    ];
    foreach ($headers as $k => $v) $allHeaders[strtolower($k)] = $v;
    if ($body !== null) $allHeaders['x-content-length'] = (string) strlen($body);

    $lines = [];
    foreach ($allHeaders as $k => $v) $lines[] = $k . ': ' . $v;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 90,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $resp = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $resp === false ? '' : $resp, 'error' => $err];
    }

    $ctx = [
        'method' => $method,
        'header' => $lines,
        'ignore_errors' => true,
        'timeout' => 90,
    ];
    if ($body !== null) $ctx['content'] = $body;
    $resp = @file_get_contents($url, false, stream_context_create(['http' => $ctx]));
    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) $status = (int) $m[1];
        }
    }
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'body' => $resp === false ? '' : $resp, 'error' => ''];
}

// Sube un PDF a Blob y devuelve la URL pública (store público) o null.
function blobUploadPdf($pathname, $content) {
    $r = blobRequest('PUT', 'pathname=' . rawurlencode($pathname), [
        'x-vercel-blob-access' => 'public',
        'x-content-type' => 'application/pdf',
        'content-type' => 'application/pdf',
        'x-allow-overwrite' => '1',
    ], $content);
    $data = json_decode($r['body'], true);
    return [
        'ok' => $r['ok'],
        'status' => $r['status'],
        'url' => (is_array($data) && isset($data['url'])) ? $data['url'] : null,
        'pathname' => (is_array($data) && isset($data['pathname'])) ? $data['pathname'] : null,
        'error' => $r['error'],
        'raw' => $r['body'],
    ];
}

// Lista blobs con un prefijo. Devuelve ['ok' => bool, 'blobs' => array].
function blobList($prefix = '', $limit = 1000) {
    $query = 'limit=' . (int) $limit;
    if ($prefix !== '') $query .= '&prefix=' . rawurlencode($prefix);
    $r = blobRequest('GET', $query);
    $data = json_decode($r['body'], true);
    return [
        'ok' => $r['ok'],
        'status' => $r['status'],
        'blobs' => (is_array($data) && isset($data['blobs'])) ? $data['blobs'] : [],
        'raw' => $r['body'],
    ];
}

// Busca el blob más reciente cuyo pathname comienza con $prefix y devuelve su URL pública (o null).
function blobFindLatestUrl($prefix) {
    $res = blobList($prefix, 1000);
    if (!$res['ok']) return null;
    $best = null;
    foreach ($res['blobs'] as $b) {
        if (!isset($b['pathname']) || strpos($b['pathname'], $prefix) !== 0) continue;
        $ts = isset($b['uploadedAt']) ? strtotime((string) $b['uploadedAt']) : 0;
        if ($best === null || $ts >= $best['ts']) {
            $best = ['ts' => $ts, 'url' => isset($b['url']) ? $b['url'] : null];
        }
    }
    return $best ? $best['url'] : null;
}
