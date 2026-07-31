const { get } = require("@vercel/blob");
const { Readable } = require("node:stream");

function isValidIdentifier(value) {
  return /^[a-zA-Z0-9_-]+$/.test(value);
}

module.exports = async function handler(request, response) {
  if (request.method !== "GET") {
    response.setHeader("Allow", "GET");

    return response.status(405).json({
      success: false,
      error: "Método no permitido",
    });
  }

  try {
    const codigo = String(request.query.codigo || "").trim();
    const userId = String(request.query.user_id || "").trim();

    if (
      !isValidIdentifier(codigo) ||
      !isValidIdentifier(userId)
    ) {
      return response.status(400).json({
        success: false,
        error: "Parámetros inválidos",
      });
    }

    const pathname = `firmados/${userId}/${codigo}.pdf`;

    const result = await get(pathname, {
      access: "private",
      useCache: false,
    });

    if (!result || result.statusCode !== 200 || !result.stream) {
      return response.status(404).send(
        "Documento firmado no encontrado"
      );
    }

    response.statusCode = 200;
    response.setHeader("Content-Type", "application/pdf");
    response.setHeader(
      "Content-Disposition",
      `inline; filename="${codigo}-firmado.pdf"`
    );
    response.setHeader("Cache-Control", "private, no-store");
    response.setHeader("X-Content-Type-Options", "nosniff");

    Readable.fromWeb(result.stream).pipe(response);
  } catch (error) {
    const message = String(error.message || "");

    if (
      message.includes("404") ||
      message.toLowerCase().includes("not found")
    ) {
      return response.status(404).send(
        "Documento firmado no encontrado"
      );
    }

    console.error("sign-download:", error);

    return response.status(500).json({
      success: false,
      error: "No se pudo descargar el documento firmado",
      detail: error.message,
    });
  }
};