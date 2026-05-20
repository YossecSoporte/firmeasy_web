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
    $sampleDb = [
        'abc123' => "$samplesDir/pdfWeb.pdf",
        'd8b5a1f7-24c3-4346-b10c-76033d5c3446' => "$samplesDir/sample2.pdf",
        'abc256' => "$samplesDir/sample3.pdf",
    ];
    $codigo = $_GET['codigo'];
    if (isset($sampleDb[$codigo])) {
        $archivo = $sampleDb[$codigo];
        if (file_exists($archivo) && is_readable($archivo)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($archivo) . '"');
            header('Content-Length: ' . filesize($archivo));
            readfile($archivo);
            exit;
        }
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
// POST /api.php?op=csv_upload&csv=ID
// ====================================
if ($method === 'POST' && $op === 'csv_upload' && isset($_GET['csv'])) {
    $csvId = $_GET['csv'];
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Archivo no recibido']);
        exit;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($_FILES['csv_file']['tmp_name']);
    if ($mime !== 'text/plain' && $mime !== 'text/csv') {
        http_response_code(415);
        echo json_encode(['error' => 'Solo se permiten archivos CSV']);
        exit;
    }
    $name = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', basename($_FILES['csv_file']['name']));
    $targetPath = "$csvDir/" . uniqid() . "_$name";
    if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $targetPath)) {
        $fakeDb["csv_$csvId"] = $targetPath;
        file_put_contents($dbFile, json_encode($fakeDb, JSON_PRETTY_PRINT));
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'CSV subido exitosamente',
            'path' => $targetPath,
            'id' => $csvId,
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error al guardar el CSV']);
    }
    exit;
}
// ====================================
// GET /api.php?op=csv_download&codigo=ID
// ====================================
if ($method === 'GET' && $op === 'csv_download' && isset($_GET['codigo'])) {
    $codigo = $_GET['codigo'];
    $key = "csv_$codigo";
    if (isset($fakeDb[$key])) {
        $archivo = $fakeDb[$key];
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

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ? "https"
        : "http";

    $host = $_SERVER['HTTP_HOST'];

    $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

    $baseUrl = $scheme . "://" . $host . $basePath;

    // ======================================================
    // DOCUMENTOS DEMO
    // ======================================================

    $docVisible = "abc123";
    $docInvisible = "abc256";

    // ======================================================
    // URLS DOCUMENTO VISIBLE
    // ======================================================

    $fromVisible =
        $baseUrl .
        "/api.php?op=sample&codigo=" .
        $docVisible;

    $toVisible =
        $baseUrl .
        "/api.php?op=sign_upload&codigo=" .
        $docVisible .
        "&user_id=testuser";

    // ======================================================
    // URLS DOCUMENTO INVISIBLE
    // ======================================================

    $fromInvisible =
        $baseUrl .
        "/api.php?op=sample&codigo=" .
        $docInvisible;

    $toInvisible =
        $baseUrl .
        "/api.php?op=sign_upload&codigo=" .
        $docInvisible .
        "&user_id=testuser";

    // ======================================================
    // GRAPHIC URL OPCIONAL
    // ======================================================

    $graphicUrl = $useGraphic
        ? "https://upload.wikimedia.org/wikipedia/commons/8/89/HD_transparent_picture.png"
        : null;

    // ======================================================
    // RESPONSE BASE
    // ======================================================

    $response = [
        "session_id" => "751ac6a9-7b9f-4da1-b406-394e2c47e849",
        "mode" => $mode,
        "documents" => []
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
    // DOCUMENTO VISIBLE
    // ======================================================

    $visibleDocument = [
        "from" => $fromVisible,
        "to" => $toVisible,
        "name_pdf" => "contrato_visible.pdf",
        "signature" => [
            "page" => 1,
            "x" => 100,
            "y" => 200,
            "width" => 150,
            "height" => 60,
            "text" => "Firmado por: {name}\nFecha: {date}",
            "text_size" => 10,
            "rotation" => 0,
            "graphic_url" => $graphicUrl
        ]
    ];

    // ======================================================
    // DOCUMENTO INVISIBLE
    // ======================================================

    $invisibleDocument = [
        "from" => $fromInvisible,
        "to" => $toInvisible,
        "name_pdf" => "contrato_invisible.pdf"
    ];

    // ======================================================
    // CASES
    // ======================================================

    switch ($case) {

        // =========================
        // VISIBLE
        // =========================

        case 'visible':

            $response['documents'][] = $visibleDocument;

            break;

        // =========================
        // INVISIBLE
        // =========================

        case 'invisible':

            $response['documents'][] = $invisibleDocument;

            break;

        // =========================
        // MIXED
        // =========================

        case 'mixed':

            $response['documents'][] = $visibleDocument;

            $response['documents'][] = $invisibleDocument;

            break;

        // =========================
        // INVALIDO
        // =========================

        default:

            http_response_code(400);

            echo json_encode([
                "success" => false,
                "error" => "case inválido",
                "allowed" => [
                    "visible",
                    "invisible",
                    "mixed"
                ]
            ], JSON_PRETTY_PRINT);

            exit;
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
