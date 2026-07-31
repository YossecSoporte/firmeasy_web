<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Headers: *");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$baseDir = __DIR__;
$uploadDir = $baseDir . '/uploads';
$samplesDir = $baseDir . '/samples';
$csvDir = $baseDir . '/samplescsv';
$dbFile = $baseDir . '/fake_db.json';


if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
if (!file_exists($samplesDir)) mkdir($samplesDir, 0755, true);
if (!file_exists($csvDir)) mkdir($csvDir, 0755, true);

$fakeDb = file_exists($dbFile) ? json_decode(file_get_contents($dbFile), true) : [];

$method = $_SERVER['REQUEST_METHOD'];
$op = $_GET['op'] ?? '';

// ====================================
// GET /api.php?op=sign_download&codigo=ID
// ====================================
if ($method === 'GET' && $op === 'sign_download' && isset($_GET['codigo'])) {
    $codigo = $_GET['codigo'];

    if (isset($fakeDb[$codigo])) {
        $archivo = __DIR__ . '/' . ltrim($fakeDb[$codigo], '/');
        if (file_exists($archivo)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($archivo) . '"');
            header('Content-Length: ' . filesize($archivo));
            readfile($archivo);
            exit;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Archivo firmado no encontrado']);
    exit;
}

// GET /api.php?op=download_signed&codigo=ID&user_id=USER
if ($method === 'GET' && $op === 'download_signed' && isset($_GET['codigo']) && isset($_GET['user_id'])) {
    $codigo = $_GET['codigo'];
    $userId = $_GET['user_id'];

    $signedDbFile = $baseDir . '/signed_docs.json';
    if (!file_exists($signedDbFile)) {
        http_response_code(404);
        exit('Archivo firmado no encontrado');
    }
    $signedDb = json_decode(file_get_contents($signedDbFile), true);
    $docs = $signedDb[$userId] ?? [];
    $found = null;
    foreach ($docs as $doc) {
        if ($doc['code'] === $codigo) {
            $found = $doc;
            break;
        }
    }
    if (!$found || !file_exists($found['filePath'])) {
        http_response_code(404);
        exit('Archivo firmado no disponible');
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($found['filePath']) . '"');
    readfile($found['filePath']);
    exit;
}

// ====================================
// GET /api.php?op=sample&codigo=ID
// ====================================
if ($method === 'GET' && $op === 'sample' && isset($_GET['codigo'])) {
    $codigo = $_GET['codigo'];

    // 1. Base de datos hardcodeada (legacy)
    $sampleDb = [
        'abc123' => "$samplesDir/pdfWeb.pdf",
        'd8b5a1f7-24c3-4346-b10c-76033d5c3446' => "$samplesDir/sample2.pdf",
        'abc256' => "$samplesDir/sample3.pdf",
    ];

    // 2. Buscar en documentos.json (documentos reales)
    $docsFile = __DIR__ . '/documentos.json';
    if (isset($sampleDb[$codigo])) {
        $archivo = $sampleDb[$codigo];
    } elseif (file_exists($docsFile)) {
        $docsData = json_decode(file_get_contents($docsFile), true);
        if (is_array($docsData)) {
            foreach ($docsData as $doc) {
                if (($doc['codePdf'] ?? '') === $codigo) {
                    $archivo = __DIR__ . '/samples/' . ($doc['fileName'] ?? '');
                    break;
                }
            }
        }
    }

    // 3. Buscar directamente en samples/ por nombre de archivo
    if (!isset($archivo) || !file_exists($archivo)) {
        $archivo = __DIR__ . '/samples/' . $codigo . '.pdf';
        if (!file_exists($archivo)) {
            $archivo = __DIR__ . '/samples/' . $codigo;
        }
    }

    if (isset($archivo) && file_exists($archivo) && is_readable($archivo)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($archivo) . '"');
        header('Content-Length: ' . filesize($archivo));
        readfile($archivo);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Archivo de muestra no encontrado']);
    exit;
}

// ====================================
// GET /api.php?op=csv&csv=ID
// ====================================
if ($method === 'GET' && $op === 'csv' && isset($_GET['csv'])) {
    $csvDb = [
        '12' => "$csvDir/prueba.csv",
        '456' => "$csvDir/ejemplo.csv",
        'tsp' => "$csvDir/tsp.csv",
    ];
    $csvId = $_GET['csv'];
    if (isset($csvDb[$csvId])) {
        $archivo = $csvDb[$csvId];
        if (file_exists($archivo) && is_readable($archivo)) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . basename($archivo) . '"');
            header('Content-Length: ' . filesize($archivo));
            readfile($archivo);
            exit;
        }
    }
    http_response_code(404);
    echo json_encode(['error' => 'CSV no encontrado']);
    exit;
}
// ====================================
// POST /api.php?op=sign_upload&codigo=ID
// ====================================

//modo post
// if ($method === 'POST' && $op === 'sign_upload' && isset($_GET['codigo']) && isset($_GET['user_id'])) {
//     $codigo = $_GET['codigo'];
//     $userId = $_GET['user_id'];
//     if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
//         http_response_code(400);
//         echo json_encode(['error' => 'Archivo no recibido']);
//         exit;
//     }
//     $finfo = new finfo(FILEINFO_MIME_TYPE);
//     $mime = $finfo->file($_FILES['pdf_file']['tmp_name']);
//     if ($mime !== 'application/pdf') {
//         http_response_code(415);
//         echo json_encode(['error' => 'Solo se permiten archivos PDF']);
//         exit;
//     }
//     $name = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', basename($_FILES['pdf_file']['name']));
//     $targetPath = "$uploadDir/" . uniqid() . "_$name";
//     if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $targetPath)) {
//         $signedDbFile = $baseDir . '/signed_docs.json';
//         $signedDb = file_exists($signedDbFile) ? json_decode(file_get_contents($signedDbFile), true) : [];
//         if (!isset($signedDb[$userId])) {
//             $signedDb[$userId] = [];
//         }
//         $signedDb[$userId][] = [
//             'code' => $codigo,
//             'filePath' => $targetPath,
//             'signedAt' => date('c') // ISO 8601
//         ];
//         file_put_contents($signedDbFile, json_encode($signedDb, JSON_PRETTY_PRINT));
//         http_response_code(201);
//         echo json_encode(['success' => true, 'path' => $targetPath]);
//     } else {
//         http_response_code(500);
//         echo json_encode(['error' => 'Error al guardar archivo']);
//     }
//     exit;
// }

//Modo binario
if ($method === 'POST' && $op === 'sign_upload' && isset($_GET['codigo']) && isset($_GET['user_id'])) {

    $codigo = $_GET['codigo'];
    $userId = $_GET['user_id'];

    // Leer binario crudo
    $rawData = file_get_contents('php://input');

    if ($rawData === false || strlen($rawData) === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Archivo no recibido (binario vacío)']);
        exit;
    }

    // Validar PDF por firma mágica
    if (substr($rawData, 0, 4) !== '%PDF') {
        http_response_code(415);
        echo json_encode(['error' => 'El archivo no es un PDF válido']);
        exit;
    }

    // Guardar archivo
    $fileName = uniqid('upload_') . '.pdf';
    $targetPath = $uploadDir . '/' . $fileName;

    if (file_put_contents($targetPath, $rawData) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al guardar archivo']);
        exit;
    }

    // Guardar en JSON
    $signedDbFile = $baseDir . '/signed_docs.json';
    $signedDb = file_exists($signedDbFile)
        ? json_decode(file_get_contents($signedDbFile), true)
        : [];

    $signedDb[$userId][] = [
        'code' => $codigo,
        'filePath' => $targetPath,
        'signedAt' => date('c')
    ];

    file_put_contents($signedDbFile, json_encode($signedDb, JSON_PRETTY_PRINT));

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'path' => $targetPath
    ]);
    exit;
}

// ====================================
// POST /api.php?op=reset
// Limpia signed_docs.json (estado de firmas)
// ====================================
if ($method === 'POST' && $op === 'reset') {
    $signedDbFile = $baseDir . '/signed_docs.json';
    file_put_contents($signedDbFile, json_encode([], JSON_PRETTY_PRINT));
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Sesión reiniciada']);
    exit;
}



// ====================================
// POST /api.php?op=csv_upload&csv=ID
// ====================================
// if ($method === 'POST' && $op === 'csv_upload' && isset($_GET['csv'])) {
//     $csvId = $_GET['csv'];
//     if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
//         http_response_code(400);
//         echo json_encode(['error' => 'Archivo no recibido']);
//         exit;
//     }
//     $finfo = new finfo(FILEINFO_MIME_TYPE);
//     $mime = $finfo->file($_FILES['csv_file']['tmp_name']);
//     if ($mime !== 'text/plain' && $mime !== 'text/csv') {
//         http_response_code(415);
//         echo json_encode(['error' => 'Solo se permiten archivos CSV']);
//         exit;
//     }
//     $name = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', basename($_FILES['csv_file']['name']));
//     $targetPath = "$csvDir/" . uniqid() . "_$name";
//     if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $targetPath)) {
//         $fakeDb["csv_$csvId"] = $targetPath;
//         file_put_contents($dbFile, json_encode($fakeDb, JSON_PRETTY_PRINT));
//         http_response_code(201);
//         echo json_encode([
//             'success' => true,
//             'message' => 'CSV subido exitosamente',
//             'path' => $targetPath,
//             'id' => $csvId,
//         ]);
//     } else {
//         http_response_code(500);
//         echo json_encode(['error' => 'Error al guardar el CSV']);
//     }
//     exit;
// }
if (
    $method === 'POST' &&
    $op === 'csv_upload' &&
    isset($_GET['csv'])
) {
    $codigo = basename((string) $_GET['csv']);

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $codigo)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Código CSV inválido'
        ]);
        exit;
    }

    if (
        !isset($_FILES['csv_file']) ||
        $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Archivo CSV no recibido'
        ]);
        exit;
    }

    if (!is_dir($csvDir)) {
        mkdir($csvDir, 0775, true);
    }

    $csvPath = $csvDir . '/' . $codigo . '.csv';

    if (!move_uploaded_file(
        $_FILES['csv_file']['tmp_name'],
        $csvPath
    )) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'No se pudo guardar el CSV'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'codigo' => $codigo,
        'file' => basename($csvPath)
    ]);
    exit;
}
// ====================================
// GET /api.php?op=csv_download&codigo=ID
// ====================================
// if ($method === 'GET' && $op === 'csv_download' && isset($_GET['codigo'])) {
//     $codigo = $_GET['codigo'];
//     $key = "csv_$codigo";
//     if (isset($fakeDb[$key])) {
//         $archivo = $fakeDb[$key];
//         if (file_exists($archivo) && is_readable($archivo)) {
//             header('Content-Type: text/csv');
//             header('Content-Disposition: attachment; filename="' . basename($archivo) . '"');
//             header('Content-Length: ' . filesize($archivo));
//             readfile($archivo);
//             exit;
//         }
//     }
//     http_response_code(404);
//     echo json_encode(['error' => 'CSV no encontrado']);
//     exit;
// }
if (
    $method === 'GET' &&
    $op === 'csv_download' &&
    isset($_GET['codigo'])
) {
    $codigo = basename(trim((string) $_GET['codigo']));

    if (
        $codigo === '' ||
        !preg_match('/^[a-zA-Z0-9_-]+$/', $codigo)
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Código CSV inválido',
        ]);
        exit;
    }

    $csvPath = $csvDir . DIRECTORY_SEPARATOR . $codigo . '.csv';

    clearstatcache(true, $csvPath);

    if (!is_file($csvPath) || !is_readable($csvPath)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'CSV no encontrado',
            'codigo' => $codigo,
        ]);
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename="' .
        basename($csvPath) .
        '"'
    );
    header('Content-Length: ' . filesize($csvPath));

    readfile($csvPath);
    exit;
}
// ====================================
// POST /api.php?op=csv_sign&codigo=ID
// Firma Ed25519 el contenido del CSV y retorna batch signature
// ====================================
// if ($method === 'POST' && $op === 'csv_sign' && isset($_GET['codigo'])) {
//     $codigo = $_GET['codigo'];
//     $key = "csv_$codigo";
//     if (!isset($fakeDb[$key]) || !file_exists($fakeDb[$key])) {
//         http_response_code(404);
//         echo json_encode(['error' => 'CSV no encontrado']);
//         exit;
//     }

//     $csvPath = $fakeDb[$key];
if ($method === 'POST' && $op === 'csv_sign' && isset($_GET['codigo'])) {
    header('Content-Type: application/json; charset=utf-8');

    $codigo = basename(trim((string) $_GET['codigo']));

    if (
        $codigo === '' ||
        !preg_match('/^[a-zA-Z0-9_-]+$/', $codigo)
    ) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Código CSV inválido',
        ]);
        exit;
    }

    $csvPath = $csvDir . DIRECTORY_SEPARATOR . $codigo . '.csv';

    clearstatcache(true, $csvPath);

    if (!is_file($csvPath) || !is_readable($csvPath)) {
        error_log('CSV no encontrado en: ' . $csvPath);

        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'CSV no encontrado',
            'codigo' => $codigo,
            'archivo' => basename($csvPath),
        ]);
        exit;
    }
    // $csvContent = file_get_contents($csvPath);
    // if ($csvContent === false) {
    //     http_response_code(500);
    //     echo json_encode(['error' => 'No se pudo leer el CSV']);
    //     exit;
    // }
    $csvContent = file_get_contents($csvPath);

    if ($csvContent === false) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'No se pudo leer el CSV',
        ]);
        exit;
    }
    // SHA-256 del contenido del CSV para firmar
    $csvHash = hash('sha256', $csvContent);

    // Base URL para download
    $batchScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $batchHost = $_SERVER['HTTP_HOST'];
    $batchBasePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $batchBaseUrl = $batchScheme . "://" . $batchHost . $batchBasePath;

    // Nonce, exp, kid para la firma del batch
    $batchNonce = bin2hex(random_bytes(16));
    $batchExp = time() + 30 * 60; // 30 min
    $batchKid = 'default';
    $privateKeyPath = __DIR__ . '/signer_keys/private.pem';

    if (!file_exists($privateKeyPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Clave privada no encontrada']);
        exit;
    }

    // Firmar con Ed25519
    $downloadUrl = $batchBaseUrl . "/api.php?op=csv_download&codigo=" . $codigo;
    $batchParams = [
        ['batch_csv', $downloadUrl],
        ['nonce', $batchNonce],
        ['exp', (string)$batchExp],
    ];
    $signResult = signUriWithNode($batchParams, $batchKid, $privateKeyPath);

if (
    !is_array($signResult) ||
    !empty($signResult['error']) ||
    empty($signResult['uri'])
) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'No se pudo firmar la URI del lote',
        'signer_error' =>
            $signResult['error'] ??
            'El firmador no devolvió una URI',
    ]);

    exit;
}

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'download_url' => $downloadUrl,
        'batch' => [
            'csv_hash' => $csvHash,
            'nonce' => $batchNonce,
            'exp' => $batchExp,
            'kid' => $batchKid,
            'signed_uri' => $signResult['uri'] ?? null,
        ]
    ]);
    exit;
}
if ($method === 'POST' && $op === 'csv_upload_tsa') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Archivo CSV no recibido"]);
        exit;
    }
    $file = $_FILES['csv_file'];
    $fileName = 'tsa_' . uniqid() . '.csv';
    $filePath = $csvDir . '/' . $fileName;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime !== 'text/plain' && $mime !== 'text/csv') {
        http_response_code(415);
        echo json_encode(["success" => false, "error" => "Formato no válido, solo CSV permitido"]);
        exit;
    }
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        $url = 'http://localhost:8080/' . $fileName;
        echo json_encode([
            "success" => true,
            "url" => $url,
            "path" => $filePath,
            "file" => $fileName,
            "id" => "tsa"
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Error al mover archivo"]);
    }
    exit;
}
if ($method === 'GET' && $op === 'csv_download' && isset($_GET['file'])) {
    $fileName = basename($_GET['file']); // Limpia el nombre
    $filePath = $csvDir . '/' . $fileName; // Corregido para leer desde samplescsv
    if (file_exists($filePath)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Archivo CSV no encontrado"]);
        exit;
    }
}

// Helper: firma una URI usando el signer Node.js (Ed25519)
function signUriWithNode($params, $kid = 'default', $privateKeyPath = null) {
    if ($privateKeyPath === null) {
        $privateKeyPath = __DIR__ . '/signer_keys/private.pem';
    }
    if (!file_exists($privateKeyPath)) {
        return ['error' => 'clave privada no encontrada: ' . $privateKeyPath];
    }
    $nodeScript = __DIR__ . '/signer/sign-cli.mjs';
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open(
        'node ' . escapeshellarg($nodeScript),
        $descriptors,
        $pipes
    );
    if (!is_resource($process)) {
        return ['error' => 'no se pudo iniciar signer'];
    }
    fwrite($pipes[0], json_encode(['mode' => 'sign', 'params' => $params, 'kid' => $kid, 'privateKeyPem' => file_get_contents($privateKeyPath)]));
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        return ['error' => 'error en signer', 'stderr' => $stderr];
    }
    $result = json_decode($output, true);
    if (!$result || !isset($result['uri'])) {
        return ['error' => 'respuesta inválida del signer', 'raw' => $output];
    }
    return ['uri' => $result['uri']];
}


// ============================= NUEVOS MÉTODOS JSON JOBS =============================

// ====================================
// GET /api.php?op=json_jobs
// ====================================
//
// Casos:
//
// ?case=visible
// ?case=invisible
// ?case=mixed
//
// Extras:
//
// &mode=0
// &mode=1
//
// &tsa=true
// &tsa=false
//
// &graphic=true
// &graphic=false
//
// ====================================
// GET /api.php?op=json_jobs
// ====================================

if ($method === 'GET' && $op === 'json_jobs') {

    header('Content-Type: application/json');

    // ======================================================
    // PARAMETROS
    // ======================================================

    $case = $_GET['case'] ?? 'visible';

    $mode = isset($_GET['mode'])
        ? intval($_GET['mode'])
        : 0;

    $useTsa = isset($_GET['tsa'])
        ? filter_var($_GET['tsa'], FILTER_VALIDATE_BOOLEAN)
        : true;

    $useGraphic = isset($_GET['graphic'])
        ? filter_var($_GET['graphic'], FILTER_VALIDATE_BOOLEAN)
        : false;

    // ======================================================
    // BASE URL AUTOMATICA
    // ======================================================

    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || $_SERVER['SERVER_PORT'] == 443)
        ? "https"
        : "http";

    $host = $_SERVER['HTTP_HOST'];

    $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

    $baseUrl = $scheme . "://" . $host . $basePath;

    // ======================================================
    // CARGAR DOCUMENTOS REALES DESDE documentos.json
    // ======================================================

    $docsFile = __DIR__ . '/documentos.json';
    $documents = [];
    if (file_exists($docsFile)) {
        $docsData = json_decode(file_get_contents($docsFile), true);
        if (is_array($docsData)) {
            $documents = $docsData;
        }
    }

    if (empty($documents)) {
        http_response_code(404);
        echo json_encode(["success" => false, "error" => "No hay documentos configurados en documentos.json"]);
        exit;
    }

    // ======================================================
    // GRAPHIC URL OPCIONAL
    // ======================================================

    $graphicUrl = $useGraphic
        ? "https://upload.wikimedia.org/wikipedia/commons/8/89/HD_transparent_picture.png"
        : null;

    // ======================================================
    // FIRMA DE URIS CON ED25519 (Node.js signer) - DINAMICO
    // ======================================================

    $kid = 'default';
    $privateKeyPath = __DIR__ . '/signer_keys/private.pem';
    $nonce = bin2hex(random_bytes(16));
    $exp = time() + 15 * 60; // 15 min

    // Parámetros comunes para firma
    $commonParams = [
        ['nonce', $nonce],
        ['exp', (string)$exp],
    ];

    // Firmar URI para cada documento real
    $signedDocuments = [];
    foreach ($documents as $doc) {
        $codePdf = $doc['codePdf'] ?? null;
        if (!$codePdf) continue;

        $docFrom = $baseUrl . "/api.php?op=sample&codigo=" . $codePdf;
        $docTo = $baseUrl . "/api.php?op=sign_upload&codigo=" . $codePdf . "&user_id=" . ($_GET['user_id'] ?? 'testuser');
        $docName = $doc['namePdf'] ?? "documento.pdf";

        // SHA256 del PDF (si existe localmente)
        $sha256 = 'demo_sha256';
        $localPdfPath = __DIR__ . "/samples/" . ($doc['fileName'] ?? '');
        if (file_exists($localPdfPath)) {
            $sha256 = hash_file('sha256', $localPdfPath);
        }

        // Configuración de firma del documento (del JSON)
        $sigCfg = $doc['signatureConfig'] ?? [];
        $posX = $sigCfg['positionx'] ?? 100;
        $posY = $sigCfg['positiony'] ?? 200;
        $width = $sigCfg['width'] ?? 150;
        $height = $sigCfg['height'] ?? 55;
        $page = $sigCfg['pageNumber'] ?? 1;
        $textSize = $sigCfg['textSize'] ?? 10;
        $sigText = $sigCfg['signatureText'] ?? "Firmado digitalmente por: \n<SIGNER>\nFecha: <DATE>\nOU: <OU>\nFirmado con FirmEasy";
        $useGraphic = $sigCfg['useGraphic'] ?? false;
        $graphicUrl = $useGraphic && !empty($sigCfg['graphic']) ? $sigCfg['graphic'] : null;

        // Parámetros para este documento
        $params = [
            ['from', $docFrom],
            ['to', $docTo],
            ['vis_sig_x', $posX],
            ['vis_sig_y', $posY],
            ['vis_sig_width', $width],
            ['vis_sig_height', $height],
            ['vis_sig_page', $page],
            ['vis_sig_text_size', $textSize],
            ['vis_sig_text', $sigText],
            ['doc_sha256', $sha256],
        ];
        if ($graphicUrl) {
            $params[] = ['vis_sig_graphic', $graphicUrl];
        }
        // TLV y upload_simple (según config del documento)
        $useTsp = !empty($sigCfg['useTsp']);
        $params[] = ['tlv', $useTsp ? '1' : '0'];
        $params[] = ['upload_simple', 'true'];
        $params = array_merge($params, $commonParams);

        $signResult = signUriWithNode($params, 'default', __DIR__ . '/signer_keys/private.pem');

        $signedDocuments[] = [
            "from" => $docFrom,
            "to" => $docTo,
            "name_pdf" => $docName,
            "sha256" => $sha256,
            "signed_uri" => $signResult['uri'] ?? null,
            "signature" => [
                "page" => $sigCfg['pageNumber'] ?? 1,
                "x" => $posX,
                "y" => $posY,
                "width" => $width,
                "height" => $height,
                "text" => $sigText,
                "text_size" => $textSize,
                "rotation" => $sigCfg['rotation'] ?? 0,
                "graphic_url" => $graphicUrl
            ]
        ];
    }

    // ======================================================
    // RESPONSE BASE
    // ======================================================

    $response = [
        "session_id" => "751ac6a9-7b9f-4da1-b406-394e2c47e849",
        "mode" => 0,
        "documents" => $signedDocuments
    ];

    // ======================================================
    // TSA OPCIONAL
    // ======================================================

    if ($useTsa) {

        $response["tsa"] = [
            "url" => "https://tsp.selloTiempo.com/tsa",
            "user" => "prueba@sello.com",
            "password" => "12345678"
        ];
    }

    // ======================================================
    // RESPONSE FINAL
    // ======================================================

    echo json_encode(
        $response,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );

    exit;
}



// ====================================
// POST /api.php?op=sign_uri
// ====================================
// Recibe JSON body con los parámetros de la URI y retorna la URI firmada con Ed25519.
// No modifica el comportamiento de ningún endpoint existente.

if ($method === 'POST' && $op === 'sign_uri') {
    $rawBody = file_get_contents('php://input');
    file_put_contents(__DIR__ . '/uploads/debug.log', date('c') . ' body=' . $rawBody . PHP_EOL, FILE_APPEND);
    $input = json_decode($rawBody, true);
    file_put_contents(__DIR__ . '/uploads/debug.log', date('c') . ' decoded=' . json_encode($input) . PHP_EOL, FILE_APPEND);
    if (!$input || !isset($input['params']) || !is_array($input['params'])) {
        http_response_code(400);
        echo json_encode(['error' => 'params incompletos, se espera array de pares [clave, valor]', 'raw' => $rawBody, 'decoded' => $input]);
        exit;
    }
    $kid = $input['kid'] ?? 'default';
    $privateKeyPath = $input['privateKeyPath'] ?? __DIR__ . '/signer_keys/private.pem';
    if (!file_exists($privateKeyPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'clave privada no encontrada: ' . $privateKeyPath]);
        exit;
    }
    $privateKeyPem = file_get_contents($privateKeyPath);
    $paramsJson = json_encode($input['params']);
    $nodeScript = __DIR__ . '/signer/sign-cli.mjs';
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open(
        'node ' . escapeshellarg($nodeScript),
        $descriptors,
        $pipes
    );
    if (!is_resource($process)) {
        http_response_code(500);
        echo json_encode(['error' => 'no se pudo iniciar signer']);
        exit;
    }
    fwrite($pipes[0], json_encode(['mode' => 'sign', 'params' => $input['params'], 'kid' => $kid, 'privateKeyPem' => $privateKeyPem]));
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        http_response_code(500);
        echo json_encode(['error' => 'error en signer', 'stderr' => $stderr]);
        exit;
    }
    $result = json_decode($output, true);
    if (!$result || !isset($result['uri'])) {
        http_response_code(500);
        echo json_encode(['error' => 'respuesta invalida del signer', 'raw' => $output]);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['signed_uri' => $result['uri'], 'kid' => $kid], JSON_PRETTY_PRINT);
    exit;
}


// ====================================
// Endpoints de auditoria/pruebas
// ====================================
require_once __DIR__ . '/api_test.php';

// ====================================
// Método o ruta no válida
// ====================================
http_response_code(405);
echo json_encode(['error' => 'Método o parámetro inválido']);
function wasDocumentSigned($userId, $documentCode) {
    $jsonPath = "firmas.json"; // o donde guardes las firmas
    if (!file_exists($jsonPath)) return false;
    $data = json_decode(file_get_contents($jsonPath), true);
    if (!isset($data[$userId])) return false;
    foreach ($data[$userId] as $entry) {
        if ($entry['code'] === $documentCode) {
            return true;
        }
    }
    return false;
}