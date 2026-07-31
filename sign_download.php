<?php
// sign_download.php

$codigo = $_GET['codigo'] ?? null;
$userId = $_GET['user_id'] ?? null;

if (!$codigo || !$userId) {
    http_response_code(400);
    echo "⚠️ Faltan parámetros requeridos: 'codigo' y 'user_id'";
    exit;
}

// Ruta al JSON
$jsonPath = __DIR__ . "/signed_docs.json";

// 1. Buscar en signed_docs.json
if (file_exists($jsonPath)) {
    $jsonData = json_decode(file_get_contents($jsonPath), true);
    $userDocs = is_array($jsonData) ? ($jsonData[$userId] ?? []) : [];

    $matchingDocs = array_values(array_filter($userDocs, function ($doc) use ($codigo) {
        return isset($doc['code']) && $doc['code'] === $codigo;
    }));

    // Obtener el más reciente (por `signedAt`)
    usort($matchingDocs, function ($a, $b) {
        return strtotime((string) ($b['signedAt'] ?? '')) - strtotime((string) ($a['signedAt'] ?? ''));
    });

    $latest = $matchingDocs[0] ?? null;

    if ($latest) {
        if (!empty($latest['url'])) {
            header('Location: ' . $latest['url']);
            exit;
        }
        if (!empty($latest['filePath']) && file_exists($latest['filePath'])) {
            header("Content-Type: application/pdf");
            header("Content-Disposition: inline; filename=\"" . basename($latest['filePath']) . "\"");
            readfile($latest['filePath']);
            exit;
        }
    }
}

// 2. Buscar en Vercel Blob (producción)
$token = getenv('BLOB_READ_WRITE_TOKEN');
if (is_string($token) && $token !== '') {
    require_once __DIR__ . '/blob_storage.php';
    $blobUrl = blobFindLatestUrl('signed/' . $codigo . '_' . $userId);
    if ($blobUrl) {
        header('Location: ' . $blobUrl);
        exit;
    }
}

http_response_code(404);
echo "❌ Documento no encontrado o la ruta no es válida";
exit;
