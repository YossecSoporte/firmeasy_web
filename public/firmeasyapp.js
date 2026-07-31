let globalDocuments = [];
let globalSignedDocs = {};

function getPdfUrlFromCode(codePdf) {
    const map = {
        //'a1f45880-81f4-4e2c-9fed-3c0a098d8cf2': '1p.pdf',
        //'bb59a806-f5f1-44b5-a629-87d5e2f0af76': 'Prueba_firmado.pdf',
      //   //'5a5e91a4-c5ce-4cad-8ad9-fdafff8d1148': 'Test_PDF_1.5_-_conversion_Ghostscript.pdf',
       //'031a0d34-9748-421c-a033-4dc71eb68b57': 'Prueva_2.pdf',
      //'14767618-de96-4f5e-ab1d-bb5116a5b251': 'TEST_BENDER.pdf',
      //'9dba24f5-cc00-47fa-9046-97872f38c8f4': 'doc_firmado.pdf',
    //   '74e88259-6db1-48fa-87be-5a21e31e8d58': 'doc_firmado2.pdf',
       '346db3fb-9c32-4af7-a44e-9aa741699d19': 'doc_firmado3.pdf',
       '8ebe7192-4239-41fc-9dae-5ef40b747167': 'doc_prueba4.pdf',
       'f4a2b8c1-3d5e-4a6f-8b7c-9d0e1f2a3b4c': 'doc_prueba1.pdf',
      //'c41034f1-d875-4f2e-ad8a-38eceed3925b': 'pdf_horizontal_vertical_firmado.pdf',
      //'ff496616-54cc-42ef-a353-acc31fb1884e': 'pdf_horizontal.pdf',
      //'9ef9de8e-7d35-4c0b-b810-ed9165a7eef3': 'doc_firma_h2.pdf',
      //'e64b02fb-92d2-4919-8b85-2134627b2607': 'A3-A4.pdf',
        //"950c1b45-ce88-4857-b184-a5e933f7ff35": "sin_firma.pdf",
        
      // "03ec0a0d-28ba-405d-9c2f-92ca70074bde": "pdf_contrasenia.pdf"
      // Agrega más códigos y archivos según tus necesidades
    };

    const fileName = map[codePdf];
    if (!fileName) {
        throw new Error("No hay PDF asignado para este código.");
    }

    return `https://raw.githubusercontent.com/YossecSoporte/DOC-PDF/main/${fileName}`;
    //return `https://raw.githubusercontent.com/Yossec/pdf-descarga/main/${fileName}`;
}




document.addEventListener("DOMContentLoaded", async () => {
    globalDocuments = await loadDocuments();
    globalSignedDocs = await loadSignedDocuments();

    await generateTableRows();
});

async function loadDocuments() {
    try {
        const userId = UserManager.getUserId();
        const [docsResponse, jobsResponse] = await Promise.all([
            fetch("documentos.json"),
            fetch(`api.php?op=json_jobs&case=mixed&graphic=true&user_id=${encodeURIComponent(userId)}`)
        ]);
        
        if (!docsResponse.ok) throw new Error("Error al cargar los documentos");
        
        const documents = await docsResponse.json();
        
        // Obtener signed_uri y sha256 desde json_jobs
        let signedUris = {};
        let sha256Map = {};
        if (jobsResponse.ok) {
            const jobsData = await jobsResponse.json();
            if (jobsData.documents) {
                jobsData.documents.forEach(jobDoc => {
                    const toUrl = new URL(jobDoc.to, window.location.origin);
                    const codePdf = toUrl.searchParams.get('codigo');
                    if (codePdf) {
                        if (jobDoc.signed_uri) signedUris[codePdf] = jobDoc.signed_uri;
                        if (jobDoc.sha256) sha256Map[codePdf] = jobDoc.sha256;
                    }
                });
            }
        }
        
        // Agregar signed_uri y sha256 a cada documento
        return documents.map(doc => ({
            ...doc,
            signed_uri: signedUris[doc.codePdf] || null,
            sha256: sha256Map[doc.codePdf] || null
        }));
    } catch (error) {
        console.error("Error:", error);
        return [];
    }
}

function createActionButton(color, iconName, disabled, label, onClickFn) {
    const td = document.createElement("td");
    td.className = "text-center";

    const btn = document.createElement("button");
    btn.className = `btn btn-${color} btn-sm btn-shadow`;
    if (disabled) btn.disabled = true;

    const icon = document.createElement("i");
    icon.className = "icon-sm";
    icon.setAttribute("data-lucide", iconName);

    btn.appendChild(icon);
    btn.appendChild(document.createTextNode(" " + label));
    btn.addEventListener("click", onClickFn);

    td.appendChild(btn);
    return td;
}

async function generateTableRows() {
    const tbody = document.getElementById("documents-body");
    tbody.innerHTML = "";

    // Cargar documentos y firmas
    if (!globalDocuments || globalDocuments.length === 0) {
        globalDocuments = await loadDocuments();
    }

    if (!globalSignedDocs) {
        globalSignedDocs = await loadSignedDocuments();
    }

    // Obtener firmas del usuario actual
    const userId = UserManager.getUserId();
    const userSignedDocs = globalSignedDocs[userId] || [];
    const userName = UserManager.getUserName();
    const localSignatures = SignatureTracker.getUserSignatures();

    globalDocuments.forEach((doc, index) => {
        // Verificar si el documento está firmado (servidor O localStorage)
        const signatureEntry = userSignedDocs.find(
            (entry) => entry.code === doc.codePdf
        );
        const localEntry = localSignatures[doc.id];
        const isSigned = !!signatureEntry || (localEntry && localEntry.status === "in_progress");
        const rowClass = index % 2 === 0 ? "bg-white" : "bg-slate-25";

        // Crear elementos de la fila
        const tr = document.createElement("tr");
        tr.className = `border-b border-slate-100 hover:bg-slate-50/50 transition-colors ${rowClass}`;

        // Celda del documento
        const tdFile = document.createElement("td");
        tdFile.className = "p-4";

        const fileCell = document.createElement("div");
        fileCell.className = "file-cell";

        const icon = document.createElement("i");
        icon.className = "file-icon";
        icon.setAttribute("data-lucide", "file-text");

        const fileInfo = document.createElement("div");
        const fileName = document.createElement("span");
        fileName.className = "file-name";
        fileName.textContent = doc.fileName;

        fileInfo.appendChild(fileName);

        if (isSigned) {
            const signedBy = document.createElement("div");
            signedBy.className = "text-xs text-gray-500 mt-1";
            signedBy.textContent = `Firmado por: ${userName}`;
            fileInfo.appendChild(signedBy);
        }

        fileCell.appendChild(icon);
        fileCell.appendChild(fileInfo);
        tdFile.appendChild(fileCell);
        tr.appendChild(tdFile);

        // ==== Botón Firmar ====
        tr.appendChild(
            createActionButton(
                "primary",
                "pen-tool",
                isSigned,
                isSigned ? "Firmado" : "Firmar",
                () => {
                    if (!isSigned) {
                        BtnFirmarDocumentById(doc.id);
                    }
                }
            )
        );

        // ==== Botón Config ====
        tr.appendChild(
            createActionButton("amber", "settings", false, "Config", () =>
                openConfigFirmaModal(doc.id)
            )
        );

        // ==== Botón Ver ====
        tr.appendChild(
            createActionButton(
                "secondary",
                isSigned ? "eye" : "clock",
                !isSigned,
                isSigned ? "Ver" : "Pendiente",
                () => {
                    if (isSigned) {
                        viewSignedDocument(doc.id);
                    } else {
                        showToast(
                            "El documento aún no ha sido firmado",
                            "info"
                        );
                    }
                }
            )
        );

        tbody.appendChild(tr);
    });

    // Actualizar íconos de Lucide
    lucide.createIcons();
}

// Modal de configuración
function openSignatureModal(docId) {
    const doc = globalDocuments.find((d) => d.id == docId);
    const cfg = doc.signatureConfig || {};

    const html = `
    <div class="sig-graphic-toggle">
    <input type="checkbox" id="sig-use-graphic" ${cfg.useGraphic ? "checked" : ""}>
    <label for="sig-use-graphic">Usar imagen gráfica en la firma</label>
    </div>

    <label class="optional">URL del gráfico:</label>
    <input type="text" id="sig-graphic" class="input mb-2" placeholder="https://... (opcional)" value="${cfg.graphic || ""}" ${cfg.useGraphic ? "" : "disabled"}>

    <div class="flex gap-2">
      <div class="flex-1">
        <label>Anchura (px):</label>
        <input type="number" id="sig-width" class="input" value="${cfg.width || 150}">
      </div>
      <div class="flex-1">
        <label>Altura (px):</label>
        <input type="number" id="sig-height" class="input" value="${cfg.height || 55}">
      </div>
    </div>

    <div class="flex gap-2 mt-2">
      <div class="flex-1">
        <label>Posición X (px):</label>
        <input type="number" id="sig-x" class="input" value="${cfg.positionx || 100}">
      </div>
      <div class="flex-1">
        <label>Posición Y (px):</label>
        <input type="number" id="sig-y" class="input" value="${cfg.positiony || 150}">
      </div>
      <div class="flex-1">
        <label>Número de página:</label>
        <input type="number" id="sig-page" class="input" value="${cfg.pageNumber || 1}" disabled>
      </div>
    </div>
    `;

    document.getElementById("signature-fields").innerHTML = html;
    document.getElementById("modal-signature").classList.remove("hidden");

    const useGraphicCheckbox = document.getElementById("sig-use-graphic");
    const graphicInput = document.getElementById("sig-graphic");

    useGraphicCheckbox.onchange = () => {
        graphicInput.disabled = !useGraphicCheckbox.checked;
    };

    document.getElementById("save-signature").onclick = () => {
        cfg.useGraphic = useGraphicCheckbox.checked;
        cfg.graphic = graphicInput.value.trim();
        cfg.width = parseInt(document.getElementById("sig-width").value);
        cfg.height = parseInt(document.getElementById("sig-height").value);
        cfg.positionx = parseInt(document.getElementById("sig-x").value);
        cfg.positiony = parseInt(document.getElementById("sig-y").value);

        document.getElementById("modal-signature").classList.add("hidden");
    };

    document.getElementById("close-signature").onclick = () => {
        document.getElementById("modal-signature").classList.add("hidden");
    };
}

async function openTsaModal(docId) {
    const doc = globalDocuments.find((d) => d.id == docId);
    if (!doc) {
        showToast("Documento no encontrado", "error");
        return;
    }
    doc.signatureConfig = doc.signatureConfig || {};
    doc.signatureConfig.tsp = doc.signatureConfig.tsp || {};

    const html = `
        <label>URL del TSA:</label>
        <input type="text" id="tsa-url" class="input" value="${doc.signatureConfig.tsp.url || ""}" placeholder="https://...">

        <label>Usuario:</label>
        <input type="text" id="tsa-user" class="input" value="${doc.signatureConfig.tsp.name || ""}" placeholder="usuario">

        <label>Contraseña:</label>
        <input type="password" id="tsa-pass" class="input" value="${doc.signatureConfig.tsp.password || ""}" placeholder="********">
    `;

    document.getElementById("tsa-fields").innerHTML = html;
    document.getElementById("modal-tsa").classList.remove("hidden");

    document.getElementById("save-tsa").onclick = async () => {
        const url = document.getElementById("tsa-url");
        const user = document.getElementById("tsa-user");
        const pass = document.getElementById("tsa-pass");

        [url, user, pass].forEach((el) => el.classList.remove("input-error"));

        if (url.value.trim() && (!user.value.trim() || !pass.value.trim())) {
            if (!user.value.trim()) user.classList.add("input-error");
            if (!pass.value.trim()) pass.classList.add("input-error");
            showToast("Se requieren usuario y contraseña para TSA", "warning");
            return;
        }

        try {
            doc.signatureConfig.tsp.url = url.value.trim();
            doc.signatureConfig.tsp.name = user.value.trim();
            doc.signatureConfig.tsp.password = pass.value.trim();

            const urlIsValid = !!url.value.trim();
            doc.signatureConfig.useTsp = urlIsValid;

            if (doc.signatureConfig.useTsp) {
                const csvContent = `${doc.signatureConfig.tsp.url},${doc.signatureConfig.tsp.name},${doc.signatureConfig.tsp.password}`;
                const formData = new FormData();
                formData.append("csv_file", new Blob([csvContent], { type: "text/csv" }), "tsa_credentials.csv");

                const response = await fetch(`${BASE_URL}?op=csv_upload_tsa`, {
                    method: "POST",
                    body: formData
                });

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.error || "Error al subir credenciales TSA");
                }
                doc.signatureConfig.tspUrl = result.url;
            }

            doc.status = doc.signatureConfig.useTsp ? "configured" : "pending";

            showToast("Configuración TSA guardada", "success");
            document.getElementById("modal-tsa").classList.add("hidden");
            await generateTableRows(); 

        } catch (error) {
            console.error("Error en configuración TSA:", error);
            showToast(`Error: ${error.message}`, "error");
        }
    };

    document.getElementById("close-tsa").onclick = () => {
        document.getElementById("modal-tsa").classList.add("hidden");
    };
}


function BtnFirmarDocument(doc) {
    try {
        const userId = UserManager.getUserId();
        const userName = UserManager.getUserName();
        if (!doc?.codePdf) {
            throw new Error("Documento no tiene código PDF válido");
        }

        const cfg = doc.signatureConfig || {};

        // Usar signed_uri si existe (viene firmado con Ed25519 desde json_jobs)
        if (doc.signed_uri) {
            SignatureTracker.recordSignature(doc.id, {
                status: "in_progress",
                documentName: doc.fileName,
                userId: userId,
                userName: userName,
                timestamp: new Date().toISOString(),
                config: {
                    position: { x: cfg.positionx, y: cfg.positiony },
                    size: { width: cfg.width, height: cfg.height },
                },
            });

            const uri = doc.signed_uri;
            const timeout = setTimeout(() => {
                alert("Parece que no tienes instalado el programa de escritorio. Por favor, instálalo para continuar.");
            }, 1500);

            window.location.href = uri;

            window.addEventListener("blur", () => {
                clearTimeout(timeout);
            });
            startSignaturePolling(doc.codePdf, userId);
            return;
        }

        // Fallback: construir URI manualmente (compatibilidad)
        const params = new URLSearchParams();
        const codigo = encodeURIComponent(doc.codePdf);

        SignatureTracker.recordSignature(doc.id, {
            status: "in_progress",
            documentName: doc.fileName,
            userId: userId,
            userName: userName,
            timestamp: new Date().toISOString(),
            config: {
                position: { x: cfg.positionx, y: cfg.positiony },
                size: { width: cfg.width, height: cfg.height },
            },
        });
        console.log(`${BASE_URL}`);
        params.set("from", getPdfUrlFromCode(doc.codePdf));
        params.set(
            "to",
            `${BASE_URL}?op=sign_upload&codigo=${codigo}&user_id=${userId}`
        );
        params.set("vis_sig_x", cfg.positionx || 100);
        params.set("vis_sig_y", cfg.positiony || 150);
        params.set("vis_sig_width", cfg.width || 150);
        params.set("vis_sig_height", cfg.height || 55);
        params.set("vis_sig_page", cfg.pageNumber || -1);
        params.set("vis_sig_text_size", cfg.textSize || 6);
        params.set(
            "vis_sig_text",
            encodeURIComponent(cfg.signatureText || DEFAULT_SIGNATURE_TEXT)
        );

        if (cfg.useGraphic && cfg.graphic) {
            if (!isValidUrl(cfg.graphic)) {
                throw new Error("URL de imagen de firma no válida");
            }
            params.set("vis_sig_graphic", cfg.graphic);
        }

        if (cfg.useTsp) {
            if (!cfg.tspUrl || !isValidUrl(cfg.tspUrl)) {
                throw new Error("URL del archivo CSV de TSP no válida o no configurada");
            }
            params.set("tsp", cfg.tspUrl);
            params.set("tlv", 1);
        } else {
            params.set("tlv", 0);
        }

        const uri = 'firmeasy://?' + params.toString();
        const timeout = setTimeout(() => {
            alert("Parece que no tienes instalado el programa de escritorio. Por favor, instálalo para continuar.");
        }, 1500);

        window.location.href = uri;

        window.addEventListener("blur", () => {
            clearTimeout(timeout);
        });
        startSignaturePolling(doc.codePdf, userId);
    } catch (error) {
        console.error("Error en BtnFirmarDocument:", error);
        alert(`Error al preparar firma: ${error.message}`);
    }
}

function isValidUrl(string) {
    try {
        new URL(string);
        return true;
    } catch (_) {
        return false;
    }
}

function openConfigFirmaModal(docId) {
    openSignatureModal(docId);
}

function openConfigTsaModal(docId) {
    openTsaModal(docId);
}

function BtnFirmarDocumentById(docId) {
    const doc = globalDocuments.find((d) => d.id == docId);
    BtnFirmarDocument(doc);
}

async function loadSignedDocuments() {
    try {
        const isLocal =
            window.location.hostname === "localhost" ||
            window.location.hostname === "127.0.0.1";

        /*
         * Conservamos el comportamiento antiguo en local,
         * porque allí ya está funcionando.
         */
        if (isLocal) {
            const response = await fetch(
                "/api_signed_docs.php",
                {
                    cache: "no-store",
                }
            );

            if (!response.ok) {
                throw new Error(
                    "No se pudo cargar api_signed_docs.php"
                );
            }

            return await response.json();
        }

        /*
         * En producción consultamos Vercel Blob
         * mediante /api/sign-status.
         */
        const userId = UserManager.getUserId();

        if (
            !userId ||
            !Array.isArray(globalDocuments) ||
            globalDocuments.length === 0
        ) {
            return {
                [userId || "anonymous"]: [],
            };
        }

        const signedEntries = [];

        await Promise.all(
            globalDocuments.map(async (doc) => {
                const codigo =
                    doc.codePdf ||
                    doc.codigo ||
                    doc.code ||
                    doc.id;

                if (!codigo) {
                    console.warn(
                        "Documento sin código:",
                        doc
                    );
                    return;
                }

                try {
                    const url =
                        `/api/sign-status` +
                        `?codigo=${encodeURIComponent(codigo)}` +
                        `&user_id=${encodeURIComponent(userId)}`;

                    const response = await fetch(url, {
                        method: "GET",
                        cache: "no-store",
                        headers: {
                            Accept: "application/json",
                        },
                    });

                    if (!response.ok) {
                        console.warn(
                            `No se pudo consultar ${codigo}:`,
                            response.status
                        );
                        return;
                    }

                    const status = await response.json();

                    if (status.signed === true) {
                        signedEntries.push({
                            code: String(codigo),
                            pathname: status.pathname || "",
                            signed: true,
                        });
                    }
                } catch (error) {
                    console.error(
                        `Error consultando estado de ${codigo}:`,
                        error
                    );
                }
            })
        );

        return {
            [userId]: signedEntries,
        };
    } catch (error) {
        console.error(
            "Error cargando documentos firmados:",
            error
        );

        return {};
    }
}
function showToast(message, type = "info") {
    console.log(`${type.toUpperCase()}: ${message}`);
}

async function viewSignedDocument(docId) {
    try {
        const doc = globalDocuments.find((d) => d.id == docId);
        if (!doc) throw new Error("Documento no encontrado");
        const userId = UserManager.getUserId();
        const signedDocUrl = `/sign_download.php?codigo=${encodeURIComponent(doc.codePdf)}&user_id=${userId}`;
        window.open(signedDocUrl, "_blank");
    } catch (error) {
        console.error("Error al ver documento:", error);
        showToast("Error al mostrar documento firmado", "error");
    }
}

function startSignaturePolling(docId, userId) {
    const isLocal =
        window.location.hostname === "localhost" ||
        window.location.hostname === "127.0.0.1";

    let attempts = 0;
    const maxAttempts = 120;

    const interval = setInterval(async () => {
        attempts += 1;

        try {
            /*
             * Local conserva el funcionamiento existente.
             */
            if (isLocal) {
                globalSignedDocs =
                    await loadSignedDocuments();

                const userSignedDocs =
                    globalSignedDocs[userId] || [];

                const isSigned = userSignedDocs.some(
                    (entry) =>
                        String(entry.code).trim() ===
                        String(docId).trim()
                );

                if (isSigned) {
                    clearInterval(interval);
                    await generateTableRows();

                    showToast(
                        "Documento firmado exitosamente",
                        "success"
                    );
                }

                return;
            }

            /*
             * Producción consulta directamente sign-status.
             */
            const url =
                `/api/sign-status` +
                `?codigo=${encodeURIComponent(docId)}` +
                `&user_id=${encodeURIComponent(userId)}`;

            const response = await fetch(url, {
                method: "GET",
                cache: "no-store",
                headers: {
                    Accept: "application/json",
                },
            });

            if (!response.ok) {
                throw new Error(
                    `HTTP ${response.status}`
                );
            }

            const status = await response.json();

            if (status.signed === true) {
                clearInterval(interval);

                if (!globalSignedDocs) {
                    globalSignedDocs = {};
                }

                if (
                    !Array.isArray(
                        globalSignedDocs[userId]
                    )
                ) {
                    globalSignedDocs[userId] = [];
                }

                const alreadyRegistered =
                    globalSignedDocs[userId].some(
                        (entry) =>
                            String(entry.code).trim() ===
                            String(docId).trim()
                    );

                if (!alreadyRegistered) {
                    globalSignedDocs[userId].push({
                        code: String(docId),
                        pathname: status.pathname || "",
                        signed: true,
                    });
                }

                SignatureTracker.addSignature?.(
                    String(docId)
                );

                await generateTableRows();

                showToast(
                    "Documento firmado exitosamente",
                    "success"
                );

                return;
            }

            if (attempts >= maxAttempts) {
                clearInterval(interval);

                console.warn(
                    "Se agotó el tiempo de espera para:",
                    docId
                );

                showToast(
                    "No se detectó el documento firmado",
                    "warning"
                );
            }
        } catch (error) {
            console.error(
                `Error verificando firma ${docId}:`,
                error
            );

            /*
             * No detenemos el polling por un error aislado.
             */
            if (attempts >= maxAttempts) {
                clearInterval(interval);

                showToast(
                    "No se pudo verificar la firma",
                    "error"
                );
            }
        }
    }, 3000);

    return interval;
}
