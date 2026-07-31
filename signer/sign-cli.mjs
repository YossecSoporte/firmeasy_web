import { createHash, createPrivateKey, createPublicKey, generateKeyPairSync, sign as edSign, verify as edVerify } from 'node:crypto';
import { readFileSync } from 'node:fs';

const SCHEME_PREFIX = 'firmeasy:?';
const SIG_MARKER = '&sig=';

const toB64url = (buf) => Buffer.from(buf).toString('base64url');
const fromB64url = (str) => Buffer.from(str, 'base64url');
const enc = (value) => encodeURIComponent(String(value));
const sha256Hex = (bytes) => createHash('sha256').update(bytes).digest('hex');

function buildSignableString(orderedParams) {
  return orderedParams.map(([k, v]) => `${k}=${enc(v)}`).join('&');
}

function asPrivateKey(k) {
  return k && k.asymmetricKeyType ? k : createPrivateKey(k);
}
function asPublicKey(k) {
  return k && k.asymmetricKeyType ? k : createPublicKey(k);
}

function signUri(orderedParams, kid, privateKey) {
  const paramsWithKid = [...orderedParams, ['kid', kid]];
  const signable = buildSignableString(paramsWithKid);
  const signature = edSign(null, Buffer.from(signable, 'utf8'), asPrivateKey(privateKey));
  return `${SCHEME_PREFIX}${signable}${SIG_MARKER}${toB64url(signature)}`;
}

function verifyUri(uri, publicKeyResolver) {
  if (typeof uri !== 'string' || !uri.startsWith(SCHEME_PREFIX)) {
    return { ok: false, reason: 'prefijo invalido' };
  }
  const body = uri.slice(SCHEME_PREFIX.length);
  const idx = body.lastIndexOf(SIG_MARKER);
  if (idx === -1) return { ok: false, reason: 'falta sig' };
  const signed = body.slice(0, idx);
  const sigB64 = body.slice(idx + SIG_MARKER.length);
  if (sigB64.includes('&')) return { ok: false, reason: 'sig no es el ultimo parametro' };
  const params = new URLSearchParams(signed);
  const kid = params.get('kid');
  if (!kid) return { ok: false, reason: 'falta kid' };
const pub = publicKeyResolver(kid);

if (!pub) {
  return {
    ok: false,
    reason: `kid desconocido: ${kid}`,
  };
}

let publicKey;

try {
  publicKey = asPublicKey(pub);
} catch (error) {
  return {
    ok: false,
    reason: "clave pública inválida",
    detail: error.message,
  };
}

let signature;

try {
  signature = fromB64url(sigB64);
} catch (error) {
  return {
    ok: false,
    reason: "firma Base64URL inválida",
    detail: error.message,
  };
}

let valid = false;

try {
  valid = edVerify(
    null,
    Buffer.from(signed, "utf8"),
    publicKey,
    signature
  );
} catch (error) {
  return {
    ok: false,
    reason: "error verificando firma",
    detail: error.message,
  };
}

if (!valid) {
  return {
    ok: false,
    reason: "firma invalida",
  };
}

return {
  ok: true,
  params,
};
  if (!valid) return { ok: false, reason: 'firma invalida' };
  return { ok: true, params };
}

function generateEd25519Keypair() {
  const { publicKey, privateKey } = generateKeyPairSync('ed25519');
  return {
    privateKeyPem: privateKey.export({ type: 'pkcs8', format: 'pem' }),
    publicKeyPem: publicKey.export({ type: 'spki', format: 'pem' }),
  };
}

function main() {
  const inputRaw = readFileSync(0, 'utf8');
  const input = JSON.parse(inputRaw);
  const { mode } = input;

  if (mode === 'sign') {
    const { params, kid, privateKeyPem } = input;
    const uri = signUri(params, kid, privateKeyPem);
    console.log(JSON.stringify({ uri, signed: true }));
  } else if (mode === "verify") {
  const uri = input.uri;
  const expectedKid = String(input.kid || "").trim();
  const publicKeyPem = input.publicKeyPem;

  if (!uri || typeof uri !== "string") {
    console.log(
      JSON.stringify({
        ok: false,
        reason: "falta uri",
      })
    );
    process.exit(0);
  }

  if (
    !publicKeyPem ||
    typeof publicKeyPem !== "string"
  ) {
    console.log(
      JSON.stringify({
        ok: false,
        reason: "falta publicKeyPem",
        receivedType: typeof publicKeyPem,
        inputKeys: Object.keys(input),
      })
    );
    process.exit(0);
  }

  const result = verifyUri(uri, (uriKid) => {
    const normalizedUriKid = String(uriKid || "").trim();

    if (normalizedUriKid !== expectedKid) {
      return null;
    }

    return publicKeyPem;
  });

  console.log(JSON.stringify(result));
} else if (mode === 'keypair') {
    const kp = generateEd25519Keypair();
    console.log(JSON.stringify(kp));
  } else if (mode === 'sha256') {
    const { content } = input;
    const hex = sha256Hex(Buffer.from(content, 'base64'));
    console.log(JSON.stringify({ sha256: hex }));
  } else if (mode === 'bench') {
    const { params, kid, privateKeyPem, iterations = 100 } = input;
    const start = Date.now();
    for (let i = 0; i < iterations; i++) {
      signUri(params, kid, privateKeyPem);
    }
    const elapsed = Date.now() - start;
    console.log(JSON.stringify({ mode: 'bench', iterations, elapsedMs: elapsed, avgMs: elapsed / iterations }));
  } else {
    console.error(JSON.stringify({ error: 'mode desconocido', allowed: ['sign', 'verify', 'keypair', 'sha256', 'bench'] }));
    process.exit(1);
  }
}

main();
