import { put } from "@vercel/blob";
import {
  createPrivateKey,
  sign as edSign,
  randomBytes,
  createHash,
} from "node:crypto";
import { readFile } from "node:fs/promises";

const SCHEME_PREFIX = "firmeasy:?";
const SIG_MARKER = "&sig=";

function encode(value) {
  return encodeURIComponent(String(value));
}

function buildSignableString(params) {
  return params.map(([key, value]) => `${key}=${encode(value)}`).join("&");
}

function signUri(params, kid, privateKeyPem) {
  const paramsWithKid = [...params, ["kid", kid]];
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

export default async function handler(request, response) {
  if (request.method !== "POST") {
    return response.status(405).json({
      success: false,
      error: "Método no permitido",
    });
  }

  try {
    const { codigo, csvContent } = request.body ?? {};

    if (
      typeof codigo !== "string" ||
      !/^[a-zA-Z0-9_-]+$/.test(codigo)
    ) {
      return response.status(400).json({
        success: false,
        error: "Código CSV inválido",
      });
    }

    if (typeof csvContent !== "string" || csvContent.length === 0) {
      return response.status(400).json({
        success: false,
        error: "Contenido CSV vacío",
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
      }
    );

    const privateKeyPath =
      process.env.FIRMEASY_PRIVATE_KEY_PATH ||
      new URL("../signer_keys/private.pem", import.meta.url);

    const privateKeyPem = await readFile(privateKeyPath, "utf8");

    const nonce = randomBytes(16).toString("hex");
    const exp = Math.floor(Date.now() / 1000) + 30 * 60;
    const kid = "default";

    const csvHash = createHash("sha256")
      .update(csvContent, "utf8")
      .digest("hex");

    /*
     * El programa Firmeasy no podrá descargar directamente una URL privada
     * sin autenticación. Por eso se usa un endpoint intermediario.
     */
    const origin =
      request.headers["x-forwarded-proto"] +
      "://" +
      request.headers.host;

    const downloadUrl =
      `${origin}/api/csv-download?pathname=` +
      encodeURIComponent(pathname);

    const signedUri = signUri(
      [
        ["batch_csv", downloadUrl],
        ["nonce", nonce],
        ["exp", String(exp)],
      ],
      kid,
      privateKeyPem
    );

    return response.status(200).json({
      success: true,
      batch: {
        pathname,
        blob_url: blob.url,
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
      error: "No se pudo procesar la firma masiva",
      detail:
        process.env.NODE_ENV === "development"
          ? error.message
          : undefined,
    });
  }
}