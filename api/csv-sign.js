const { put } = require("@vercel/blob");

const {
  createHash,
  createPrivateKey,
  randomBytes,
  sign: edSign,
} = require("node:crypto");

const SCHEME_PREFIX = "firmeasy:?";
const SIG_MARKER = "&sig=";

function encodeValue(value) {
  return encodeURIComponent(String(value));
}

function buildSignableString(params) {
  return params
    .map(([key, value]) => `${key}=${encodeValue(value)}`)
    .join("&");
}

function getPrivateKeyPem() {
  const value = process.env.FIRMEASY_PRIVATE_KEY_PEM;

  if (!value) {
    throw new Error(
      "No existe la variable FIRMEASY_PRIVATE_KEY_PEM"
    );
  }

  /*
   * Admite tanto una clave PEM multilínea como una variable
   * que contenga los saltos de línea escapados como \n.
   */
  return value.replace(/\\n/g, "\n").trim() + "\n";
}

function signUri(orderedParams, kid, privateKeyPem) {
  const paramsWithKid = [...orderedParams, ["kid", kid]];
  const signable = buildSignableString(paramsWithKid);

  const privateKey = createPrivateKey(privateKeyPem);

  const signature = edSign(
    null,
    Buffer.from(signable, "utf8"),
    privateKey
  );

  return (
    SCHEME_PREFIX +
    signable +
    SIG_MARKER +
    signature.toString("base64url")
  );
}

function getOrigin(request) {
  const protoHeader = request.headers["x-forwarded-proto"];
  const protocol = Array.isArray(protoHeader)
    ? protoHeader[0]
    : protoHeader || "https";

  const host = request.headers.host;

  if (!host) {
    throw new Error("No se pudo determinar el host");
  }

  return `${protocol}://${host}`;
}

function parseJsonBody(request) {
  if (
    request.body &&
    typeof request.body === "object" &&
    !Buffer.isBuffer(request.body)
  ) {
    return request.body;
  }

  if (typeof request.body === "string") {
    return JSON.parse(request.body);
  }

  if (Buffer.isBuffer(request.body)) {
    return JSON.parse(request.body.toString("utf8"));
  }

  return {};
}

module.exports = async function handler(request, response) {
  response.setHeader(
    "Content-Type",
    "application/json; charset=utf-8"
  );
  response.setHeader("Cache-Control", "no-store");

  if (request.method !== "POST") {
    response.setHeader("Allow", "POST");

    return response.status(405).json({
      success: false,
      error: "Método no permitido",
    });
  }

  try {
    const input = parseJsonBody(request);

    const codigo = String(input.codigo || "").trim();
    const csvContent = String(input.csvContent || "");

    if (!/^[a-zA-Z0-9_-]+$/.test(codigo)) {
      return response.status(400).json({
        success: false,
        error: "Código CSV inválido",
      });
    }

    if (!csvContent.trim()) {
      return response.status(400).json({
        success: false,
        error: "El contenido CSV está vacío",
      });
    }

    if (Buffer.byteLength(csvContent, "utf8") > 5 * 1024 * 1024) {
      return response.status(413).json({
        success: false,
        error: "El archivo CSV supera el límite permitido",
      });
    }

    const pathname = `firmas/${codigo}.csv`;

    const blob = await put(
      pathname,
      Buffer.from(csvContent, "utf8"),
      {
        access: "private",
        contentType: "text/csv; charset=utf-8",
        addRandomSuffix: false,
        allowOverwrite: true,
      }
    );

    const origin = getOrigin(request);

    const downloadUrl =
      `${origin}/api/csv-download?pathname=` +
      encodeURIComponent(pathname);

    const nonce = randomBytes(16).toString("hex");
    const exp = Math.floor(Date.now() / 1000) + 30 * 60;
    const kid = "default";

    const csvHash = createHash("sha256")
      .update(csvContent, "utf8")
      .digest("hex");

    const signedUri = signUri(
      [
        ["batch_csv", downloadUrl],
        ["nonce", nonce],
        ["exp", String(exp)],
      ],
      kid,
      getPrivateKeyPem()
    );

    return response.status(200).json({
      success: true,
      batch: {
        codigo,
        pathname,
        blob_url: blob.url,
        download_url: downloadUrl,
        csv_hash: csvHash,
        nonce,
        exp,
        kid,
        signed_uri: signedUri,
      },
    });
  } catch (error) {
    console.error("csv-sign:", error);

    return response.status(500).json({
      success: false,
      error: "No se pudo preparar la firma masiva",
      detail: error.message,
    });
  }
};