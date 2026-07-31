<?php
header('Content-Type: application/json');

// Ruta absoluta o relativa al archivo JSON
$path = __DIR__ . '/signed_docs.json';

$result = [];
if (file_exists($path)) {
    $data = json_decode(file_get_contents($path), true);
    if (is_array($data)) $result = $data;
}

// Fusionar con los PDFs firmados en Vercel Blob (producción)
$token = getenv('BLOB_READ_WRITE_TOKEN');
if (is_string($token) && $token !== '') {
    require_once __DIR__ . '/blob_storage.php';
    $res = blobList('signed/', 1000);
    if ($res['ok']) {
        // Agrupar por usuario y código, extrayendo la información del pathname:
        // signed/{codigo}_{userId}_{timestamp}.pdf
        $blobRecords = [];
        foreach ($res['blobs'] as $b) {
            $p = $b['pathname'] ?? '';
            if (strpos($p, 'signed/') !== 0) continue;
            $name = preg_replace('/\.pdf$/i', '', substr($p, strlen('signed/')));
            $parts = explode('_', $name);
            if (count($parts) < 3) continue;
            $userId = $parts[count($parts) - 2];
            $codigo = implode('_', array_slice($parts, 0, count($parts) - 2));
            $blobRecords[$userId][$codigo] = [
                'code' => $codigo,
                'filePath' => $b['url'] ?? '',
                'url' => $b['url'] ?? '',
                'signedAt' => $b['uploadedAt'] ?? date('c'),
            ];
        }

        foreach ($blobRecords as $userId => $records) {
            // Mezclar con los registros locales; Blob tiene prioridad.
            // En producción los paths locales no existen, así que se descartan.
            $byCode = [];
            foreach (($result[$userId] ?? []) as $old) {
                if (isset($old['url'])) {
                    $byCode[$old['code']] = $old;
                } elseif (isset($old['filePath']) && file_exists($old['filePath'])) {
                    $byCode[$old['code']] = $old;
                }
            }
            foreach ($records as $r) $byCode[$r['code']] = $r;
            $result[$userId] = array_values($byCode);
        }
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
