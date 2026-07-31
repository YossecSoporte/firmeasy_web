document.addEventListener("DOMContentLoaded", () => {
  const btnMasiva = document.querySelector(".action-button button");

  if (btnMasiva) {
    btnMasiva.addEventListener("click", async () => {
      const firmas = prepararFirmasDesdeJSON();

      if (firmas.length === 0) {
        alert("No hay documentos válidos para firmar.");
        return;
      }

      const csvContent = generarCSV(firmas);

      try {
        const { urlDescarga, csvId } = await guardarCSVEnServidor(csvContent);

        // Firmar el batch con Ed25519
        const signResponse = await fetch(`api.php?op=csv_sign&codigo=${csvId}`, { method: 'POST' });
        const signData = await signResponse.json();

        if (!signData.success || !signData.batch?.signed_uri) {
          throw new Error('No se pudo firmar el batch CSV');
        }

        // Usar la URI firmada del batch
        const uri = signData.batch.signed_uri;

        window.location.href = uri;
        const userId = UserManager.getUserId();
        globalDocuments.forEach((doc) => {
          if (doc.status !== "signed") {
            startSignaturePollingMulti(doc.codePdf, userId);
          }
        });
      } catch (err) {
        console.error("Error al firmar CSV:", err);
        alert("Ocurrió un error al firmar el batch: " + err.message);
      }
    });
  }
});

function prepararFirmasDesdeJSON() {
  const firmas = [];

  (globalDocuments || []).forEach((doc) => {
    const cfg = doc.signatureConfig || {};
    if (!cfg.positionx || !cfg.positiony || !cfg.width || !cfg.height) return;
    if (doc.status === "signed") return;

    const code = doc.codePdf;
    const tspUrl = cfg.useTsp ? cfg.tsp?.url || "" : "";
    const userId = UserManager.getUserId();
    
    firmas.push({
      fileName: doc.fileName,
      urlDescarga: getPdfUrlFromCode(code),
      urlSubida: `${BASE_URL}?op=sign_upload&codigo=${encodeURIComponent(code)}&user_id=${userId}`,
      x: cfg.positionx,
      y: cfg.positiony,
      width: cfg.width,
      height: cfg.height,
      sigText: DEFAULT_SIGNATURE_TEXT,
      graphic: cfg.useGraphic ? cfg.graphic : "",
      page: cfg.pageNumber || DEFAULT_PAGE_NUMBER,
      textSize: DEFAULT_TEXT_SIZE,
      tsp: tspUrl,
      sha256: doc.sha256 || "",
    });
  });

  return firmas;
}

function generarCSV(firmas) {
  const escapeCsv = (text) =>
    `"${(text || "")
      .replace(/\n/g, "\\n")       // Escapar saltos de línea reales
      .replace(/"/g, '""')}"`;     // Escapar comillas dobles

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


async function guardarCSVEnServidor(csvContent) {
  const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
  const codigo = `firmas-${timestamp}`;
  const uploadUrl = `${BASE_URL}?op=csv_upload&csv=${codigo}`;
  const downloadUrl = `${BASE_URL}?op=csv_download&codigo=${codigo}`;

  const formData = new FormData();
  formData.append(
    "csv_file",
    new Blob([csvContent], { type: "text/csv" }),
    "firmas.csv"
  );

  const response = await fetch(uploadUrl, {
    method: "POST",
    body: formData,
  });

  if (!response.ok) {
    throw new Error("No se pudo subir el CSV al servidor");
  }

  console.log(`CSV guardado como ${codigo}.csv`);

  return {
    nombreArchivo: `${codigo}.csv`,
    urlDescarga: downloadUrl,
    rutaLocal: `/samplescsv/${codigo}.csv`,
    csvId: codigo,
  };
}

function startSignaturePollingMulti(docId, userId) {
  const interval = setInterval(async () => {
    try {
      globalSignedDocs = await loadSignedDocuments();
      const userSignedDocs = globalSignedDocs[userId] || [];
      const isSigned = userSignedDocs.some(
        (entry) => entry.code.trim() === String(docId).trim()
      );

      if (isSigned) {
        clearInterval(interval);

        // Actualizar estado local del documento
        const doc = globalDocuments.find(
          (d) => String(d.codePdf).trim() === String(docId).trim()
        );
        if (doc) doc.status = "signed";

        await generateTableRows(); // refresca la tabla
        showToast("Documento firmado exitosamente", "success");
      }
    } catch (error) {
      clearInterval(interval);
    }
  }, 3000);
}

async function viewSignedDocument(docId) {
  try {
    const doc = globalDocuments.find((d) => d.id == docId);
    if (!doc) throw new Error("Documento no encontrado");
    const userId = UserManager.getUserId();
    const signedDocUrl = `/sign_download.php?codigo=${encodeURIComponent(
      doc.codePdf
    )}&user_id=${userId}`;
    window.open(signedDocUrl, "_blank");
  } catch (error) {
    console.error("Error al ver documento:", error);
    showToast("Error al mostrar documento firmado", "error");
  }
}

async function loadSignedDocuments() {
  try {
    const response = await fetch("/api_signed_docs.php");

    if (!response.ok) throw new Error("No se pudo cargar signed_docs.json");
    return await response.json();
  } catch (error) {
    console.error("Error al cargar signed_docs.json:", error);
    return {};
  }
}
function showToast(message, type = "info") {
  console.log(`${type.toUpperCase()}: ${message}`);
}
