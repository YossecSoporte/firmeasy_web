const { get } = require("@vercel/blob");
const { Readable } = require("node:stream");

module.exports = async function handler(request, response) {
  if (request.method !== "GET") {
    response.setHeader("Allow", "GET");

    return response.status(405).json({
      success: false,
      error: "Método no permitido",
    });
  }

  try {
    const pathname = String(request.query.pathname || "").trim();

    if (
      !pathname.startsWith("firmas/") ||
      !pathname.endsWith(".csv") ||
      pathname.includes("..")
    ) {
      return response.status(400).json({
        success: false,
        error: "Ruta CSV inválida",
      });
    }

    const result = await get(pathname, {
      access: "private",
      useCache: false,
    });

    if (!result || result.statusCode !== 200 || !result.stream) {
      return response.status(404).json({
        success: false,
        error: "CSV no encontrado",
      });
    }

    const filename = pathname.split("/").pop() || "firmas.csv";

    response.statusCode = 200;
    response.setHeader(
      "Content-Type",
      result.blob.contentType || "text/csv; charset=utf-8"
    );
    response.setHeader(
      "Content-Disposition",
      `attachment; filename="${filename}"`
    );
    response.setHeader("Cache-Control", "private, no-store");
    response.setHeader("X-Content-Type-Options", "nosniff");

    Readable.fromWeb(result.stream).pipe(response);
  } catch (error) {
    console.error("csv-download:", error);

    return response.status(500).json({
      success: false,
      error: "No se pudo descargar el CSV",
      detail: error.message,
    });
  }
};