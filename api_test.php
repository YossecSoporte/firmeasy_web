<?php
// ====================================
// POST /api.php?op=test_sign
// Genera URIs de prueba para escenarios de firma individual
// ====================================
if ($method === 'POST' && $op === 'test_sign') {
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody, true);
    $scenario = $input['scenario'] ?? 'valid';
    $docIndex = intval($input['docIndex'] ?? 0);

    $docsFile = __DIR__ . '/documentos.json';
    $documents = [];
    if (file_exists($docsFile)) {
        $docsData = json_decode(file_get_contents($docsFile), true);
        if (is_array($docsData)) $documents = $docsData;
    }
    if (empty($documents)) {
        http_response_code(404);
        echo json_encode(['error' => 'No hay documentos configurados']);
        exit;
    }

    $doc = $documents[min($docIndex, count($documents) - 1)];
    $codePdf = $doc['codePdf'] ?? 'test-doc';
    $sigCfg = $doc['signatureConfig'] ?? [];

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $baseUrl = "$scheme://$host$basePath";

    $docFrom = "$baseUrl/api.php?op=sample&codigo=$codePdf";
    $docTo = "$baseUrl/api.php?op=sign_upload&codigo=" . urlencode($codePdf) . "&user_id=testuser";

    $sha256 = 'demo_sha256';
    $localPdfPath = __DIR__ . "/samples/" . ($doc['fileName'] ?? '');
    if (file_exists($localPdfPath)) {
        $sha256 = hash_file('sha256', $localPdfPath);
    }

    $posX = $sigCfg['positionx'] ?? 100;
    $posY = $sigCfg['positiony'] ?? 200;
    $width = $sigCfg['width'] ?? 150;
    $height = $sigCfg['height'] ?? 55;
    $page = $sigCfg['pageNumber'] ?? 1;
    $textSize = $sigCfg['textSize'] ?? 10;
    $sigText = $sigCfg['signatureText'] ?? "Firmado digitalmente por: \n<SIGNER>\nFecha: <DATE>\nOU: <OU>\nFirmado con FirmEasy";

    $privateKeyPath = __DIR__ . '/signer_keys/private.pem';
    if (!file_exists($privateKeyPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Clave privada no encontrada']);
        exit;
    }

    $nonce = bin2hex(random_bytes(16));
    $exp = time() + 15 * 60;

    $result = ['success' => true, 'scenario' => $scenario];

    switch ($scenario) {
        case 'valid':
            $params = [
                ['from', $docFrom],
                ['to', $docTo],
                ['vis_sig_x', (string)$posX],
                ['vis_sig_y', (string)$posY],
                ['vis_sig_width', (string)$width],
                ['vis_sig_height', (string)$height],
                ['vis_sig_page', (string)$page],
                ['vis_sig_text_size', (string)$textSize],
                ['vis_sig_text', $sigText],
                ['doc_sha256', $sha256],
                ['nonce', $nonce],
                ['exp', (string)$exp],
            ];
            $signResult = signUriWithNode($params, 'default', $privateKeyPath);
            $result['uri'] = $signResult['uri'] ?? null;
            $result['expected'] = 'exito';
            $result['reason'] = 'URI firmada correctamente con todos los parametros validos';
            break;

        case 'invalid_sig':
            $params = [
                ['from', $docFrom],
                ['to', $docTo],
                ['vis_sig_x', (string)$posX],
                ['vis_sig_y', (string)$posY],
                ['vis_sig_width', (string)$width],
                ['vis_sig_height', (string)$height],
                ['vis_sig_page', (string)$page],
                ['vis_sig_text_size', (string)$textSize],
                ['vis_sig_text', $sigText],
                ['doc_sha256', $sha256],
                ['nonce', $nonce],
                ['exp', (string)$exp],
            ];
            $signResult = signUriWithNode($params, 'default', $privateKeyPath);
            $uri = $signResult['uri'] ?? '';
            if ($uri) {
                $uri = substr_replace($uri, 'X', -8, 1);
            }
            $result['uri'] = $uri;
            $result['expected'] = 'error';
            $result['reason'] = 'Firma Ed25519 corrupta (1 byte alterado al final)';
            break;

        case 'expired':
            $exp = time() - 3600;
            $params = [
                ['from', $docFrom],
                ['to', $docTo],
                ['vis_sig_x', (string)$posX],
                ['vis_sig_y', (string)$posY],
                ['vis_sig_width', (string)$width],
                ['vis_sig_height', (string)$height],
                ['vis_sig_page', (string)$page],
                ['vis_sig_text_size', (string)$textSize],
                ['vis_sig_text', $sigText],
                ['doc_sha256', $sha256],
                ['nonce', $nonce],
                ['exp', (string)$exp],
            ];
            $signResult = signUriWithNode($params, 'default', $privateKeyPath);
            $result['uri'] = $signResult['uri'] ?? null;
            $result['expected'] = 'error';
            $result['reason'] = 'URI expirada (exp = hora actual - 1 hora)';
            break;

        case 'modified_text':
            $params = [
                ['from', $docFrom],
                ['to', $docTo],
                ['vis_sig_x', (string)$posX],
                ['vis_sig_y', (string)$posY],
                ['vis_sig_width', (string)$width],
                ['vis_sig_height', (string)$height],
                ['vis_sig_page', (string)$page],
                ['vis_sig_text_size', (string)$textSize],
                ['vis_sig_text', $sigText . ' TAMPERED'],
                ['doc_sha256', $sha256],
                ['nonce', $nonce],
                ['exp', (string)$exp],
            ];
            $signResult = signUriWithNode($params, 'default', $privateKeyPath);
            $result['uri'] = $signResult['uri'] ?? null;
            $result['expected'] = 'error';
            $result['reason'] = 'vis_sig_text alterada despues de firmar (firma no coincide)';
            break;

        case 'missing_params':
            $params = [
                ['from', $docFrom],
                ['to', $docTo],
                ['vis_sig_x', (string)$posX],
                ['vis_sig_y', (string)$posY],
                ['vis_sig_width', (string)$width],
                ['vis_sig_height', (string)$height],
                ['vis_sig_page', (string)$page],
                ['vis_sig_text_size', (string)$textSize],
                ['vis_sig_text', $sigText],
                ['doc_sha256', $sha256],
            ];
            $signResult = signUriWithNode($params, 'default', $privateKeyPath);
            $result['uri'] = $signResult['uri'] ?? null;
            $result['expected'] = 'error';
            $result['reason'] = 'Faltan parametros nonce y exp (sin firma Ed25519)';
            break;

        case 'no_pdf':
            $docFromBad = "$baseUrl/api.php?op=sample&codigo=nonexistent-pdf-id-999";
            $params = [
                ['from', $docFromBad],
                ['to', $docTo],
                ['vis_sig_x', (string)$posX],
                ['vis_sig_y', (string)$posY],
                ['vis_sig_width', (string)$width],
                ['vis_sig_height', (string)$height],
                ['vis_sig_page', (string)$page],
                ['vis_sig_text_size', (string)$textSize],
                ['vis_sig_text', $sigText],
                ['doc_sha256', $sha256],
                ['nonce', $nonce],
                ['exp', (string)$exp],
            ];
            $signResult = signUriWithNode($params, 'default', $privateKeyPath);
            $result['uri'] = $signResult['uri'] ?? null;
            $result['expected'] = 'error';
            $result['reason'] = 'PDF no encontrado en URL de origen (404)';
            break;

        case 'sha256_mismatch':
            $params = [
                ['from', $docFrom],
                ['to', $docTo],
                ['vis_sig_x', (string)$posX],
                ['vis_sig_y', (string)$posY],
                ['vis_sig_width', (string)$width],
                ['vis_sig_height', (string)$height],
                ['vis_sig_page', (string)$page],
                ['vis_sig_text_size', (string)$textSize],
                ['vis_sig_text', $sigText],
                ['doc_sha256', '0000000000000000000000000000000000000000000000000000000000000000'],
                ['nonce', $nonce],
                ['exp', (string)$exp],
            ];
            $signResult = signUriWithNode($params, 'default', $privateKeyPath);
            $result['uri'] = $signResult['uri'] ?? null;
            $result['expected'] = 'error';
            $result['reason'] = 'SHA-256 del PDF no coincide (ceroes en doc_sha256)';
            break;

        case 'unsigned_doc':
            $result['uri'] = null;
            $result['expected'] = 'normal';
            $result['reason'] = 'Documento sin firma: el boton "Firmar" debe estar habilitado';
            $result['doc'] = [
                'codePdf' => $codePdf,
                'fileName' => $doc['fileName'] ?? 'unknown.pdf',
                'from' => $docFrom,
                'to' => $docTo,
            ];
            break;

        case 'already_signed':
            $result['uri'] = null;
            $result['expected'] = 'disabled';
            $result['reason'] = 'Documento ya firmado: el boton "Firmar" debe estar deshabilitado';
            $result['doc'] = [
                'codePdf' => $codePdf,
                'fileName' => $doc['fileName'] ?? 'unknown.pdf',
            ];
            break;

        default:
            $result = ['error' => "Escenario desconocido: $scenario"];
            break;
    }

    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ====================================
// POST /api.php?op=test_batch
// Genera URIs de prueba para escenarios de firma en lote
// ====================================
if ($method === 'POST' && $op === 'test_batch') {
    $rawBody = file_get_contents('php://input');
    $input = json_decode($rawBody, true);
    $scenario = $input['scenario'] ?? 'valid_batch';

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $baseUrl = "$scheme://$host$basePath";

    $privateKeyPath = __DIR__ . '/signer_keys/private.pem';
    if (!file_exists($privateKeyPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Clave privada no encontrada']);
        exit;
    }

    $docsFile = __DIR__ . '/documentos.json';
    $documents = [];
    if (file_exists($docsFile)) {
        $docsData = json_decode(file_get_contents($docsFile), true);
        if (is_array($docsData)) $documents = $docsData;
    }

    // Generar CSV de ejemplo con los documentos configurados
    $csvRows = [];
    foreach ($documents as $d) {
        $code = $d['codePdf'] ?? '';
        $fileName = $d['fileName'] ?? '';
        $sha256 = 'demo_sha256';
        $localPath = __DIR__ . "/samples/" . $fileName;
        if (file_exists($localPath)) {
            $sha256 = hash_file('sha256', $localPath);
        }
        $csvRows[] = "$code,$fileName,$sha256";
    }

    $csvContent = "codePdf,fileName,sha256\n" . implode("\n", $csvRows) . "\n";
    $csvHash = hash('sha256', $csvContent);

    $result = ['success' => true, 'scenario' => $scenario];

    $batchNonce = bin2hex(random_bytes(16));
    $batchExp = time() + 30 * 60;

    switch ($scenario) {
        case 'valid_batch':
            $tmpFile = tempnam(sys_get_temp_dir(), 'test_batch_');
            file_put_contents($tmpFile, $csvContent);
            $csvId = 'test_batch_' . bin2hex(random_bytes(4));
            $fakeDb["csv_$csvId"] = $tmpFile;
            file_put_contents($dbFile, json_encode($fakeDb, JSON_PRETTY_PRINT));

            $downloadUrl = "$baseUrl/api.php?op=csv_download&codigo=$csvId";
            $batchParams = [
                ['batch_csv', $downloadUrl],
                ['nonce', $batchNonce],
                ['exp', (string)$batchExp],
            ];
            $signResult = signUriWithNode($batchParams, 'default', $privateKeyPath);
            $result['uri'] = $signResult['uri'] ?? null;
            $result['download_url'] = $downloadUrl;
            $result['csv_hash'] = $csvHash;
            $result['csv_content'] = $csvContent;
            $result['csv_id'] = $csvId;
            $result['expected'] = 'exito';
            $result['reason'] = 'Batch URI firmado + CSV valido';
            break;

        case 'corrupted_batch_uri':
            $tmpFile = tempnam(sys_get_temp_dir(), 'test_batch_');
            file_put_contents($tmpFile, $csvContent);
            $csvId = 'test_batch_corrupt_' . bin2hex(random_bytes(4));
            $fakeDb["csv_$csvId"] = $tmpFile;
            file_put_contents($dbFile, json_encode($fakeDb, JSON_PRETTY_PRINT));

            $downloadUrl = "$baseUrl/api.php?op=csv_download&codigo=$csvId";
            $batchParams = [
                ['batch_csv', $downloadUrl],
                ['nonce', $batchNonce],
                ['exp', (string)$batchExp],
            ];
            $signResult = signUriWithNode($batchParams, 'default', $privateKeyPath);
            $uri = $signResult['uri'] ?? '';
            if ($uri) {
                $uri = substr_replace($uri, 'X', -8, 1);
            }
            $result['uri'] = $uri;
            $result['download_url'] = $downloadUrl;
            $result['expected'] = 'error';
            $result['reason'] = 'Firma del batch corrupta (1 byte alterado)';
            break;

        case 'expired_batch':
            $batchExp = time() - 3600;
            $tmpFile = tempnam(sys_get_temp_dir(), 'test_batch_');
            file_put_contents($tmpFile, $csvContent);
            $csvId = 'test_batch_expired_' . bin2hex(random_bytes(4));
            $fakeDb["csv_$csvId"] = $tmpFile;
            file_put_contents($dbFile, json_encode($fakeDb, JSON_PRETTY_PRINT));

            $downloadUrl = "$baseUrl/api.php?op=csv_download&codigo=$csvId";
            $batchParams = [
                ['batch_csv', $downloadUrl],
                ['nonce', $batchNonce],
                ['exp', (string)$batchExp],
            ];
            $signResult = signUriWithNode($batchParams, 'default', $privateKeyPath);
            $result['uri'] = $signResult['uri'] ?? null;
            $result['download_url'] = $downloadUrl;
            $result['expected'] = 'error';
            $result['reason'] = 'Batch expirado (exp = hora actual - 1 hora)';
            break;

        case 'csv_sha256_mismatch':
            $tmpFile = tempnam(sys_get_temp_dir(), 'test_batch_');
            file_put_contents($tmpFile, $csvContent);
            $csvId = 'test_batch_sha_mismatch_' . bin2hex(random_bytes(4));
            $fakeDb["csv_$csvId"] = $tmpFile;
            file_put_contents($dbFile, json_encode($fakeDb, JSON_PRETTY_PRINT));

            $downloadUrl = "$baseUrl/api.php?op=csv_download&codigo=$csvId";
            $batchParams = [
                ['batch_csv', $downloadUrl],
                ['nonce', $batchNonce],
                ['exp', (string)$batchExp],
            ];
            $signResult = signUriWithNode($batchParams, 'default', $privateKeyPath);
            $result['uri'] = $signResult['uri'] ?? null;
            $result['download_url'] = $downloadUrl;
            $result['csv_hash'] = '0000000000000000000000000000000000000000000000000000000000000000';
            $result['expected'] = 'error';
            $result['reason'] = 'CSV hash reportado no coincide con el CSV real';
            break;

        case 'pdf_sha256_mismatch':
            $csvBadRows = [];
            foreach ($documents as $d) {
                $code = $d['codePdf'] ?? '';
                $fileName = $d['fileName'] ?? '';
                $csvBadRows[] = "$code,$fileName,0000000000000000000000000000000000000000000000000000000000000000";
            }
            $badCsvContent = "codePdf,fileName,sha256\n" . implode("\n", $csvBadRows) . "\n";
            $tmpFile = tempnam(sys_get_temp_dir(), 'test_batch_');
            file_put_contents($tmpFile, $badCsvContent);
            $csvId = 'test_batch_pdf_sha_' . bin2hex(random_bytes(4));
            $fakeDb["csv_$csvId"] = $tmpFile;
            file_put_contents($dbFile, json_encode($fakeDb, JSON_PRETTY_PRINT));

            $downloadUrl = "$baseUrl/api.php?op=csv_download&codigo=$csvId";
            $batchParams = [
                ['batch_csv', $downloadUrl],
                ['nonce', $batchNonce],
                ['exp', (string)$batchExp],
            ];
            $signResult = signUriWithNode($batchParams, 'default', $privateKeyPath);
            $result['uri'] = $signResult['uri'] ?? null;
            $result['download_url'] = $downloadUrl;
            $result['csv_hash'] = hash('sha256', $badCsvContent);
            $result['csv_content'] = $badCsvContent;
            $result['expected'] = 'error';
            $result['reason'] = 'CSV contiene SHA-256 incorrectos para los PDFs (ceroes)';
            break;

        case 'missing_batch_sig':
            $tmpFile = tempnam(sys_get_temp_dir(), 'test_batch_');
            file_put_contents($tmpFile, $csvContent);
            $csvId = 'test_batch_nosig_' . bin2hex(random_bytes(4));
            $fakeDb["csv_$csvId"] = $tmpFile;
            file_put_contents($dbFile, json_encode($fakeDb, JSON_PRETTY_PRINT));

            $downloadUrl = "$baseUrl/api.php?op=csv_download&codigo=$csvId";
            $result['uri'] = "firmeasy:?batch_csv=" . urlencode($downloadUrl) . "&nonce=$batchNonce&exp=$batchExp";
            $result['download_url'] = $downloadUrl;
            $result['expected'] = 'error';
            $result['reason'] = 'Sin parametre sig (URI sin firma Ed25519)';
            break;

        case 'partial_signed':
            $tmpFile = tempnam(sys_get_temp_dir(), 'test_batch_');
            file_put_contents($tmpFile, $csvContent);
            $csvId = 'test_batch_partial_' . bin2hex(random_bytes(4));
            $fakeDb["csv_$csvId"] = $tmpFile;
            file_put_contents($dbFile, json_encode($fakeDb, JSON_PRETTY_PRINT));

            $downloadUrl = "$baseUrl/api.php?op=csv_download&codigo=$csvId";
            $batchParams = [
                ['batch_csv', $downloadUrl],
                ['nonce', $batchNonce],
                ['exp', (string)$batchExp],
            ];
            $signResult = signUriWithNode($batchParams, 'default', $privateKeyPath);
            $result['uri'] = $signResult['uri'] ?? null;
            $result['download_url'] = $downloadUrl;
            $result['csv_hash'] = $csvHash;
            $result['csv_content'] = $csvContent;
            $result['csv_id'] = $csvId;
            $result['expected'] = 'mixed';
            $result['reason'] = 'Batch con mix de docs (algunos ya firmados, otros pendientes)';
            break;

        case 'empty_batch':
            $emptyCsv = "codePdf,fileName,sha256\n";
            $tmpFile = tempnam(sys_get_temp_dir(), 'test_batch_');
            file_put_contents($tmpFile, $emptyCsv);
            $csvId = 'test_batch_empty_' . bin2hex(random_bytes(4));
            $fakeDb["csv_$csvId"] = $tmpFile;
            file_put_contents($dbFile, json_encode($fakeDb, JSON_PRETTY_PRINT));

            $downloadUrl = "$baseUrl/api.php?op=csv_download&codigo=$csvId";
            $batchParams = [
                ['batch_csv', $downloadUrl],
                ['nonce', $batchNonce],
                ['exp', (string)$batchExp],
            ];
            $signResult = signUriWithNode($batchParams, 'default', $privateKeyPath);
            $result['uri'] = $signResult['uri'] ?? null;
            $result['download_url'] = $downloadUrl;
            $result['csv_hash'] = hash('sha256', $emptyCsv);
            $result['csv_content'] = $emptyCsv;
            $result['csv_id'] = $csvId;
            $result['expected'] = 'empty';
            $result['reason'] = 'CSV vacio (solo headers, 0 documentos)';
            break;

        default:
            $result = ['error' => "Escenario desconocido: $scenario"];
            break;
    }

    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
