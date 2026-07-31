async function iniciarFirmaMasiva() {
  const firmas = prepararFirmasDesdeJSON();

  if (firmas.length === 0) {
    alert(
      "No hay documentos pendientes con una configuración válida."
    );
    return;
  }

  try {
    const csvContent = generarCSV(firmas);
    const signData = await guardarYFirmarCSV(csvContent);

    if (!signData.success) {
      throw new Error(
        signData.error || "No se pudo firmar el lote CSV"
      );
    }

    const uri = signData.batch?.signed_uri;

    if (!uri) {
      throw new Error(
        "El servidor no devolvió la URI de firma masiva"
      );
    }

    const userId = UserManager.getUserId();

    globalDocuments.forEach((doc) => {
      if (doc.status !== "signed") {
        startSignaturePollingMulti(
          doc.codePdf,
          userId
        );
      }
    });

    window.location.href = uri;
  } catch (error) {
    console.error("Error al firmar CSV:", error);

    alert(
      "Ocurrió un error al firmar el batch: " +
      error.message
    );
  }
}

window.iniciarFirmaMasiva = iniciarFirmaMasiva;

async function guardarYFirmarCSV(csvContent) {
  const timestamp = new Date()
    .toISOString()
    .replace(/[:.]/g, "-");

  const codigo = `firmas-${timestamp}`;

  const response = await fetch("/api/csv-sign", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      codigo,
      csvContent,
    }),
  });

  const responseText = await response.text();

  let data;

  try {
    data = JSON.parse(responseText);
  } catch {
    throw new Error(
      `Respuesta inválida del servidor: ${responseText}`
    );
  }

  if (!response.ok || !data.success) {
    throw new Error(
      data.error ||
      data.detail ||
      `Error HTTP ${response.status}`
    );
  }

  if (!data.batch?.signed_uri) {
    throw new Error(
      "El servidor no devolvió la URI firmada"
    );
  }

  return data;
}

function isLocalEnvironment() {
  return (
    window.location.hostname === "localhost" ||
    window.location.hostname === "127.0.0.1"
  );
}

function getSignUploadUrl(code, userId) {
  if (isLocalEnvironment()) {
    return (
      `${BASE_URL}?op=sign_upload` +
      `&codigo=${encodeURIComponent(code)}` +
      `&user_id=${encodeURIComponent(userId)}`
    );
  }

  return (
    `${window.location.origin}/api/sign-upload` +
    `?codigo=${encodeURIComponent(code)}` +
    `&user_id=${encodeURIComponent(userId)}`
  );
}

function getSignedDocumentUrl(code, userId) {
  if (isLocalEnvironment()) {
    return (
      `/sign_download.php` +
      `?codigo=${encodeURIComponent(code)}` +
      `&user_id=${encodeURIComponent(userId)}`
    );
  }

  return (
    `/api/sign-download` +
    `?codigo=${encodeURIComponent(code)}` +
    `&user_id=${encodeURIComponent(userId)}`
  );
}

function prepararFirmasDesdeJSON() {
  const firmas = [];

  (globalDocuments || []).forEach((doc) => {
    const cfg = doc.signatureConfig || {};

    if (
      cfg.positionx === undefined ||
      cfg.positiony === undefined ||
      !cfg.width ||
      !cfg.height
    ) {
      return;
    }

    if (doc.status === "signed") {
      return;
    }

    const code = doc.codePdf;
    const userId = UserManager.getUserId();

    const tspUrl = cfg.useTsp
      ? cfg.tsp?.url || ""
      : "";

    firmas.push({
      fileName: doc.fileName,
      urlDescarga: getPdfUrlFromCode(code),
      urlSubida: getSignUploadUrl(code, userId),
      x: cfg.positionx,
      y: cfg.positiony,
      width: cfg.width,
      height: cfg.height,
      sigText: DEFAULT_SIGNATURE_TEXT,
      graphic: cfg.useGraphic
        ? cfg.graphic || ""
        : "",
      page: cfg.pageNumber || DEFAULT_PAGE_NUMBER,
      textSize: DEFAULT_TEXT_SIZE,
      tsp: tspUrl,
      sha256: doc.sha256 || "",
    });
  });

  return firmas;
}

function generarCSV(firmas) {
  const escapeCsv = (value) => {
    return `"${String(value || "")
      .replace(/\r?\n/g, "\\n")
      .replace(/"/g, '""')}"`;
  };

  return firmas
    .map((sig) => {
      return [
        sig.urlDescarga,
        sig.urlSubida,
        sig.fileName,
        sig.x,
        sig.y,
        sig.width,
        sig.height,
        escapeCsv(sig.sigText),
        sig.graphic || "",
        sig.page,
        sig.textSize,
        sig.sha256 || "",
      ].join(",");
    })
    .join("\n");
}

function startSignaturePollingMulti(docId, userId) {
  let attempts = 0;
  const maxAttempts = 200;

  const interval = setInterval(async () => {
    attempts += 1;

    if (attempts > maxAttempts) {
      clearInterval(interval);

      console.warn(
        `Tiempo de espera agotado para ${docId}`
      );

      return;
    }

    try {
      const url =
        `/api/sign-status` +
        `?codigo=${encodeURIComponent(docId)}` +
        `&user_id=${encodeURIComponent(userId)}`;

      const response = await fetch(url, {
        cache: "no-store",
      });

      if (!response.ok) {
        throw new Error(
          `Error HTTP ${response.status}`
        );
      }

      const statusData = await response.json();

      if (!statusData.signed) {
        return;
      }

      clearInterval(interval);

      const doc = globalDocuments.find(
        (item) =>
          String(item.codePdf).trim() ===
          String(docId).trim()
      );

      if (doc) {
        doc.status = "signed";
      }

      await generateTableRows();

      showToast(
        "Documento firmado exitosamente",
        "success"
      );
    } catch (error) {
      console.error(
        `Error consultando estado de ${docId}:`,
        error
      );
    }
  }, 3000);
}

async function viewSignedDocument(docId) {
  try {
    const doc = globalDocuments.find(
      (item) =>
        String(item.id) === String(docId) ||
        String(item.codePdf) === String(docId)
    );

    if (!doc) {
      throw new Error("Documento no encontrado");
    }

    const userId = UserManager.getUserId();

    const signedDocUrl = getSignedDocumentUrl(
      doc.codePdf,
      userId
    );

    window.open(
      signedDocUrl,
      "_blank",
      "noopener,noreferrer"
    );
  } catch (error) {
    console.error(
      "Error al ver documento firmado:",
      error
    );

    showToast(
      "Error al mostrar el documento firmado",
      "error"
    );
  }
}

window.viewSignedDocument = viewSignedDocument;

function showToast(message, type = "info") {
  console.log(`${type.toUpperCase()}: ${message}`);
}