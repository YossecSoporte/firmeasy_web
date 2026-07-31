const TEST_SCENARIOS = {
    individual: [
        { value: 'valid', label: 'Valido - URI firmada correcta' },
        { value: 'invalid_sig', label: 'Firma invalida - Ed25519 corrupta' },
        { value: 'expired', label: 'Expirada - nonce/exp vencido' },
        { value: 'modified_text', label: 'Texto alterado - vis_sig_text modificado' },
        { value: 'missing_params', label: 'Parametros faltantes - sin nonce/exp' },
        { value: 'no_pdf', label: 'PDF inexistente - 404 en origen' },
        { value: 'sha256_mismatch', label: 'SHA-256 incorrecto - hash no coincide' },
        { value: 'unsigned_doc', label: 'Sin firmar - documento pendiente' },
        { value: 'already_signed', label: 'Ya firmado - boton deshabilitado' },
    ],
    batch: [
        { value: 'valid_batch', label: 'Valido - batch completo' },
        { value: 'corrupted_batch_uri', label: 'URI corrupta - firma batch alterada' },
        { value: 'expired_batch', label: 'Expirado - batch vencido' },
        { value: 'csv_sha256_mismatch', label: 'CSV hash - SHA-256 del CSV no coincide' },
        { value: 'pdf_sha256_mismatch', label: 'PDF hash - SHA-256 de PDFs incorrectos' },
        { value: 'missing_batch_sig', label: 'Sin firma - batch sin sig' },
        { value: 'partial_signed', label: 'Parcial - mix de firmados/pendientes' },
        { value: 'empty_batch', label: 'Vacio - CSV sin documentos' },
    ],
};

document.addEventListener('DOMContentLoaded', () => {
    const modeSelect = document.getElementById('test-mode');
    const scenarioSelect = document.getElementById('test-scenario');

    function loadScenarios(mode) {
        scenarioSelect.innerHTML = '';
        const scenarios = TEST_SCENARIOS[mode] || [];
        scenarios.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.value;
            opt.textContent = s.label;
            scenarioSelect.appendChild(opt);
        });
    }

    modeSelect.addEventListener('change', () => {
        loadScenarios(modeSelect.value);
        document.getElementById('test-result').style.display = 'none';
    });

    loadScenarios('individual');
});

async function generateTestUrl() {
    const mode = document.getElementById('test-mode').value;
    const scenario = document.getElementById('test-scenario').value;
    const isBatch = mode === 'batch';
    const endpoint = isBatch ? 'test_batch' : 'test_sign';

    const resultDiv = document.getElementById('test-result');
    const statusSpan = document.getElementById('test-status');
    const reasonDiv = document.getElementById('test-reason');
    const uriTextarea = document.getElementById('test-uri');
    const metaDiv = document.getElementById('test-meta');

    resultDiv.style.display = 'none';

    try {
        const response = await fetch(`${BASE_URL}?op=${endpoint}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ scenario }),
        });

        const data = await response.json();

        if (data.error) {
            statusSpan.textContent = 'ERROR';
            statusSpan.style.background = '#fee2e2';
            statusSpan.style.color = '#991b1b';
            reasonDiv.textContent = data.error;
            uriTextarea.value = '';
            metaDiv.innerHTML = '';
            resultDiv.style.display = 'block';
            lucide.createIcons();
            return;
        }

        const uri = data.uri || '(sin URI - estado del lado del cliente)';
        uriTextarea.value = uri;

        const expectedColors = {
            exito: { bg: '#dcfce7', fg: '#166534', label: 'EXITO ESPERADO' },
            error: { bg: '#fee2e2', fg: '#991b1b', label: 'ERROR ESPERADO' },
            normal: { bg: '#dbeafe', fg: '#1e40af', label: 'ESTADO NORMAL' },
            disabled: { bg: '#fef3c7', fg: '#92400e', label: 'BOTON DESHABILITADO' },
            mixed: { bg: '#e0e7ff', fg: '#3730a3', label: 'MIXTO' },
            empty: { bg: '#f3f4f6', fg: '#374151', label: 'VACIO' },
        };

        const ec = expectedColors[data.expected] || { bg: '#f3f4f6', fg: '#374151', label: data.expected };
        statusSpan.textContent = ec.label;
        statusSpan.style.background = ec.bg;
        statusSpan.style.color = ec.fg;
        reasonDiv.textContent = data.reason || '';

        let metaHtml = '';
        if (data.download_url) metaHtml += `<div><strong>CSV URL:</strong> ${data.download_url}</div>`;
        if (data.csv_hash) metaHtml += `<div><strong>CSV Hash:</strong> ${data.csv_hash}</div>`;
        if (data.csv_id) metaHtml += `<div><strong>CSV ID:</strong> ${data.csv_id}</div>`;
        if (data.doc) {
            metaHtml += `<div><strong>Doc:</strong> ${data.doc.fileName || data.doc.codePdf}</div>`;
        }
        if (data.csv_content) {
            metaHtml += `<details style="margin-top:4px;"><summary style="cursor:pointer; font-size:0.75rem;">Ver CSV</summary><pre style="font-size:0.7rem; background:#f8fafc; padding:4px; border-radius:4px; overflow-x:auto; max-height:120px;">${data.csv_content}</pre></details>`;
        }
        metaDiv.innerHTML = metaHtml;

        resultDiv.style.display = 'block';
        lucide.createIcons();
    } catch (err) {
        statusSpan.textContent = 'ERROR DE RED';
        statusSpan.style.background = '#fee2e2';
        statusSpan.style.color = '#991b1b';
        reasonDiv.textContent = err.message;
        uriTextarea.value = '';
        metaDiv.innerHTML = '';
        resultDiv.style.display = 'block';
        lucide.createIcons();
    }
}

function copyTestUri() {
    const textarea = document.getElementById('test-uri');
    if (!textarea.value) return;
    navigator.clipboard.writeText(textarea.value).then(() => {
        const btn = document.querySelector('#test-result .btn-secondary');
        const original = btn.innerHTML;
        btn.innerHTML = '<i data-lucide="check" style="width:12px; height:12px;"></i> Copiado!';
        lucide.createIcons();
        setTimeout(() => { btn.innerHTML = original; lucide.createIcons(); }, 1500);
    });
}
