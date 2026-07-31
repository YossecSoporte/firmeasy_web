const { put } = require("@vercel/blob");

const MAX_PDF_SIZE = 25 * 1024 * 1024;

async function readRequestBody(request) {
  if (Buffer.isBuffer(request.body)) {
    return request.body;
  }

  if (typeof request.body === "string") {
    return Buffer.from(request.body, "binary");
  }

  if (
    request.body &&
    request.body.type === "Buffer" &&
    Array.isArray(request.body.data)
  ) {
    return Buffer.from(request.body.data);
  }

  const chunks = [];
  let totalSize = 0;

  for await (const chunk of request) {
    const buffer = Buffer.isBuffer(chunk)
      ? chunk
      : Buffer.from(chunk);

    totalSize += buffer.length;

    if (totalSize > MAX_PDF_SIZE) {
      const error = new Error(
        "El PDF supera el límite máximo permitido"
      );
      error.statusCode = 413;
      throw error;
    }

    chunks.push(buffer);
  }

  return Buffer.concat(chunks);
}

function isValidIdentifier(value) {
  return /^[a-zA-Z0-9_-]+$/.test(value);
}

module.exports = async function handler(request, response) {
  response.setHeader(
    "Content-Type",
    "application/json; charset=utf-8"
  );
  response.setHeader("Cache-Control", "no-store");

  if (request.method !== "POST" && request.method !== "PUT") {
    response.setHeader("Allow", "POST, PUT");

    return response.status(405).json({
      success: false,
      error: "Método no permitido",
    });
  }

  try {
    const codigo = String(request.query.codigo || "").trim();
    const userId = String(request.query.user_id || "").trim();

    if (!isValidIdentifier(codigo)) {
      return response.status(400).json({
        success: false,
        error: "Código de documento inválido",
      });
    }

    if (!isValidIdentifier(userId)) {
      return response.status(400).json({
        success: false,
        error: "Identificador de usuario inválido",
      });
    }

    const pdfBuffer = await readRequestBody(request);

    if (!pdfBuffer.length) {
      return response.status(400).json({
        success: false,
        error: "No se recibió el PDF firmado",
      });
    }

    const pdfSignature = pdfBuffer
      .subarray(0, 4)
      .toString("ascii");

    if (pdfSignature !== "%PDF") {
      return response.status(415).json({
        success: false,
        error: "El contenido recibido no es un PDF válido",
        bytes: pdfBuffer.length,
      });
    }

    /*
     * Ruta determinística:
     * user_id + código siempre apuntan al mismo PDF.
     */
    const pathname = `firmados/${userId}/${codigo}.pdf`;

    const blob = await put(pathname, pdfBuffer, {
      access: "private",
      contentType: "application/pdf",
      addRandomSuffix: false,
      allowOverwrite: true,
    });

    return response.status(200).json({
      success: true,
      message: "Documento firmado guardado correctamente",
      codigo,
      user_id: userId,
      pathname,
      blob_url: blob.url,
      size: pdfBuffer.length,
    });
  } catch (error) {
    console.error("sign-upload:", error);

    return response
      .status(error.statusCode || 500)
      .json({
        success: false,
        error: "No se pudo guardar el documento firmado",
        detail: error.message,
      });
  }
};