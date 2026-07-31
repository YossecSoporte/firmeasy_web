const { get } = require("@vercel/blob");

function isValidIdentifier(value) {
  return /^[a-zA-Z0-9_-]+$/.test(value);
}

module.exports = async function handler(request, response) {
  response.setHeader(
    "Content-Type",
    "application/json; charset=utf-8"
  );
  response.setHeader("Cache-Control", "no-store");

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

    const signed = Boolean(
      result &&
      result.statusCode === 200 &&
      result.stream
    );

    /*
     * No enviamos el stream al navegador. Solo verificamos
     * que el PDF exista en Blob.
     */
    if (result?.stream?.cancel) {
      await result.stream.cancel().catch(() => {});
    }

    return response.status(200).json({
      success: true,
      signed,
      codigo,
      user_id: userId,
      pathname: signed ? pathname : null,
    });
  } catch (error) {
    /*
     * Blob puede responder con no encontrado mediante excepción.
     * Para el polling esto equivale a signed: false.
     */
    const message = String(error.message || "");

    if (
      message.includes("404") ||
      message.toLowerCase().includes("not found")
    ) {
      return response.status(200).json({
        success: true,
        signed: false,
      });
    }

    console.error("sign-status:", error);

    return response.status(500).json({
      success: false,
      signed: false,
      error: "No se pudo consultar el estado",
      detail: error.message,
    });
  }
};