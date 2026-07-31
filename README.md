# Firmeasy URI Signing - Documentación Técnica

## Resumen

Sistema de firma de URIs con Ed25519 para garantizar integridad, autenticidad y no repudio en flujos de firma de documentos PDF. El backend (PHP + Node.js) genera URIs `firmeasy:` firmados que el frontend entrega a la app de escritorio (C# MAUI/WinForms/WPF), la cual verifica la firma offline antes de procesar.

---

## Flujo General

```
┌─────────────┐     json_jobs (signed_uri)      ┌──────────────┐
│   Web App   │ ──────────────────────────────▶ │  Frontend JS │
│  (PHP/Node) │  signed_uri con Ed25519         │  (firmasyapp)│
└─────────────┘                                 └──────┬───────┘
                                                        │
                                              Botón "Firmar"
                                                        │
                                                        ▼
                                              ┌──────────────────┐
                                              │  App Escritorio  │
                                              │  (C# MAUI/WPF)   │
                                              │  Verifica offline│
                                              └────────┬─────────┘
                                                       │
                                              PDF + signed_uri
                                                       │
                                                       ▼
                                              ┌─────────────┐
                                              │  sign_upload  │
                                              │  (POST PDF)   │
                                              └─────────────┘
```

---

## Endpoints Principales

### 1. `GET /api.php?op=json_jobs&case=mixed&graphic=true`

**Descripción**: Devuelve lista de documentos con sus URIs ya firmados.

**Parámetros**:
- `case`: `visible` | `invisible` | `mixed`
- `graphic`: `true` | `false` (incluye URL de imagen gráfica)
- `tsa`: `true` | `false` (incluye config TSA)

**Respuesta**:
```json
{
  "session_id": "uuid",
  "mode": 0,
  "documents": [
    {
      "from": "http://host/api.php?op=sample&codigo=346db3fb...",
      "to": "http://host/api.php?op=sign_upload&codigo=346db3fb...&user_id=testuser",
      "name_pdf": "doc_firmado3",
      "signed_uri": "firmeasy:?from=...&to=...&vis_sig_x=340&vis_sig_y=693&...&doc_sha256=439baf0b5c...&tlv=0&upload_simple=true&nonce=xyz&exp=1785195927&kid=default&sig=Ed25519_BASE64URL",
      "signature": { "page": 1, "x": 340, "y": 693, ... }
    }
  ],
  "tsa": { "url": "...", "user": "...", "password": "..." }
}
```

### 2. `GET /api.php?op=sample&codigo={codePdf}`

**Descripción**: Descarga el PDF original para firmar.

**Parámetros**:
- `codigo`: codePdf del documento (ej: `346db3fb-9c32-4af7-a44e-9aa741699d19`)

**Respuesta**: PDF binario (`Content-Type: application/pdf`)

### 3. `POST /api.php?op=sign_upload&codigo={codePdf}&user_id={userId}`

**Descripción**: Sube el PDF firmado por la app de escritorio.

**Parámetros**:
- `codigo`: codePdf del documento
- `user_id`: ID del usuario

**Body**: `multipart/form-data` con campo `pdf_file` (PDF firmado)

### 4. `POST /api.php?op=sign_uri` (Opcional - Firma on-demand)

**Descripción**: Firma una URI arbitraria on-demand.

**Body JSON**:
```json
{
  "params": [
    ["from", "http://..."],
    ["to", "http://..."],
    ["vis_sig_x", 340],
    ["vis_sig_y", 693],
    ...
    ["doc_sha256", "sha256hex"],
    ["tlv", "0"],
    ["upload_simple", "true"]
  ],
  "kid": "default",
  "privateKeyPath": "/var/www/html/signer_keys/private.pem"
}
```

**Respuesta**:
```json
{
  "signed_uri": "firmeasy:?...&kid=default&sig=BASE64URL",
  "kid": "default"
}
```

---

## Formato URI Firmado (`firmeasy:`)

### Esquema
```
firmeasy:?from=...&to=...&vis_sig_x=340&vis_sig_y=693&vis_sig_width=155&vis_sig_height=55&vis_sig_page=1&vis_sig_text_size=10&vis_sig_text=Firmado%20por%3A%20%7Bname%7D%0AFechadoc_sha256=SHA256_HEX&tlv=0&upload_simple=true&nonce=HEX16&exp=UNIX_TIMESTAMP&kid=default&sig=BASE64URL_ED25519
```

### Parámetros Obligatorios

| Parámetro | Descripción | Ejemplo |
|-----------|-------------|---------|
| `from` | URL de descarga del PDF original | `http://host/api.php?op=sample&codigo=ABC` |
| `to` | URL de subida del PDF firmado | `http://host/api.php?op=sign_upload&codigo=ABC&user_id=USER` |
| `vis_sig_x` | Posición X firma visible | `340` |
| `vis_sig_y` | Posición Y firma visible | `693` |
| `vis_sig_width` | Ancho firma visible | `155` |
| `vis_sig_height` | Alto firma visible | `55` |
| `vis_sig_page` | Página de la firma | `1` |
| `vis_sig_text_size` | Tamaño texto | `10` |
| `vis_sig_text` | Texto plantilla (URL-encoded) | `Firmado%20por%3A%20%7Bname%7D%0AFecha%3A%20%7Bdate%7D` |
| `doc_sha256` | SHA-256 hex del PDF original | `439baf0b5c2eb5667351f936c5c234b11a2393bb535ca0339baa6a8d138ddd32` |
| `upload_simple` | Modo simple subida | `true` |
| `nonce` | 16 bytes hex aleatorio (anti-replay) | `cb6f8264fa46902c7bfd993dcfdb863a` |
| `exp` | Expiración Unix timestamp (15 min) | `1785195227` |
| `kid` | Key ID (identificador clave pública) | `default` |
| `sig` | Firma Ed25519 (base64url) | `BASE64URL` |

---

## Seguridad Implementada

### 1. Firma Digital Ed25519 (Detached)
- **Algoritmo**: Ed25519 (curva Edwards 25519)
- **Tipo**: Firma detached (firma sobre bytes exactos, no hash)
- **Claves**: 
  - Privada: Servidor (Node.js signer) - nunca sale del servidor
  - Pública: Embebida en app C# (offline verification)

### 2. Canonicalización Estricta
- **Orden fijo**: Parámetros en orden fijo al firmar
- **Encoding**: `encodeURIComponent` en cada valor (RFC 3986)
- **Firma sobre bytes exactos**: Se firma la cadena literal entre `firmeasy:?` y `&sig=`
- **No re-encoding**: Verificador usa los mismos bytes tal cual recibió

### 3. Anti-Replay (Nonce + Exp)
| Mecanismo | Implementación |
|-----------|----------------|
| **Nonce** | 16 bytes hex aleatorio por URI (`bin2hex(random_bytes(16))`) |
| **Exp** | Timestamp Unix + 15 min (`time() + 900`) |
| **Verificación** | App C# rechaza si `exp < now()` o nonce ya visto |

### 4. Key Rotation (KID)
- `kid=default` identifica qué clave pública usar
- Permite rotar claves sin romper compatibilidad
- App C# mapea `kid` → `publicKeyPem` embebida

### 4. Integridad del Documento (doc_sha256)
- SHA-256 hex del PDF original calculado al generar la URI
- App descarga PDF → calcula SHA-256 → compara con `doc_sha256` en URI
- Cualquier modificación del PDF invalida la verificación

### 5. Campos de Control
| Campo | Propósito |
|-------|-----------|
| `tlv` | `1` si usa TSA, `0` si no |
| `upload_simple` | Modo simple (`true`) vs batch |
| `kid` | Identificador clave para rotación |

---

## Prevención de Adulteración de Rutas

### ¿Cómo se protege `from` y `to`?

1. **Incluidos en la firma**: Ambos URLs están dentro de la cadena firmada
2. **Cualquier cambio invalida la firma**: Modificar un carácter → firma inválida
3. **Verificación offline**: App C# reconstruye la cadena exacta y verifica Ed25519

### Ejemplo de ataque impedido:
```
Atacante intenta cambiar:
  from=http://malicious.com/fake.pdf
  to=http://malicious.com/steal.pdf

Resultado: 
  - Firma Ed25519 ya no coincide
  - App C# rechaza: "firma inválida"
  - No se procesa el documento
```

### Verificación en C# (Pseudocódigo):
```csharp
// 1. Extraer parámetros de la URI
var uri = "firmeasy:?from=...&to=...&doc_sha256=...&kid=default&sig=BASE64URL";
var signedPart = ExtractSignedPart(uri);  // Entre "firmeasy:?" y "&sig="
var sigB64 = ExtractSig(uri);
var kid = ExtractKid(uri);

// 2. Cargar clave pública correspondiente al kid
var publicKeyPem = embeddedKeys[kid];
var publicKey = Ed25519PublicKey.ImportSubjectPublicKeyInfo(
    Convert.FromBase64String(StripPemHeaders(publicKeyPem)), out _);

// 3. Reconstruir bytes exactos firmados
var dataBytes = Encoding.UTF8.GetBytes(signedPart);

// 4. Verificar firma
var sigBytes = Base64UrlDecode(sigB64);
bool valid = Ed25519.Verify(dataBytes, sigBytes, publicKey);

if (!valid) throw new SecurityException("Firma inválida - URI adulterada");

// 5. Verificar expiración y nonce
var exp = ExtractExp(uri);
if (DateTimeOffset.FromUnixTimeSeconds(exp) < DateTimeOffset.UtcNow)
    throw new SecurityException("URI expirada");

// 6. Verificar doc_sha256 descargando PDF
var docSha256 = ExtractDocSha256(uri);
var downloadedPdf = await DownloadPdf(ExtractFrom(uri));
var actualSha256 = SHA256.HashData(downloadedPdf);
if (!actualSha256.SequenceEqual(HexToBytes(docSha256)))
    throw new SecurityException("PDF adulterado - SHA256 no coincide");
```

---

## Lo que Debe Recibir la App C# (Escritorio)

### Entrada (desde URI `firmeasy:`)
```csharp
class SignedUriData {
    string From;              // URL descarga PDF
    string To;                // URL subida PDF firmado
    int VisSigX, VisSigY;
    int VisSigWidth, VisSigHeight;
    int VisSigPage;
    int VisSigTextSize;
    string VisSigText;        // Plantilla con {name}, {date}, {OU}
    string DocSha256;         // SHA-256 hex del PDF original
    int Tlv;                  // 0 o 1
    bool UploadSimple;        // true
    string Nonce;             // 32 chars hex
    long Exp;                 // Unix timestamp
    string Kid;               // Key ID
    string Sig;               // Base64URL Ed25519
    string PublicKeyPem;      // Embebida según Kid
}
```

### Salida (POST a `to`)
- **URL**: valor de `to` (ej: `http://host/api.php?op=sign_upload&codigo=ABC&user_id=USER`)
- **Método**: `POST` multipart/form-data
- **Campo**: `pdf_file` (PDF binario firmado)

---

## Lo que Debe Enviar la Web

### 1. Al cargar documentos (`json_jobs`)
```json
GET /api.php?op=json_jobs&case=mixed&graphic=true
→ Devuelve array documents[] con signed_uri firmados
```

### 2. Al descargar PDF (`sample`)
```
GET /api.php?op=sample&codigo={codePdf}
→ PDF binario (Content-Type: application/pdf)
```

### 3. Al recibir PDF firmado (`sign_upload`)
```
POST /api.php?op=sign_upload&codigo={codePdf}&user_id={userId}
Content-Type: multipart/form-data
pdf_file: [PDF binario firmado]
```

---

## Configuración de Claves

### Generar par Ed25519 (una sola vez)
```bash
# En servidor (Node.js)
node signer/sign-cli.mjs <<< '{"mode":"keypair"}'
# Guarda private.pem y public.pem en signer_keys/
```

### Embebir en App C#
```csharp
// Resources/Keys.cs
public static class EmbeddedKeys {
    public static readonly Dictionary<string, string> PublicKeys = new() {
        ["default"] = @"-----BEGIN PUBLIC KEY-----
MCowBQYDK2VwAyEA...
-----END PUBLIC KEY-----"
    };
}
```

### Rotación de Claves
1. Generar nuevo par
2. Agregar `kid2` a `EmbeddedKeys`
3. Actualizar backend para firmar con `kid2`
4. App C# ya tiene la pública → funciona automáticamente

---

## Resumen de Archivos Clave

| Archivo | Rol |
|---------|-----|
| `api.php` | Backend PHP - endpoints `json_jobs`, `sample`, `sign_upload`, `sign_uri` |
| `signer/sign-cli.mjs` | Node.js CLI - firma/verifica Ed25519 (stdin/stdout JSON) |
| `signer/sign-cli.mjs` | Wrapper stdin/stdout para PHP |
| `public/firmeasyapp.js` | Frontend JS - consume `json_jobs`, usa `signed_uri` |
| `Consumo/Program.cs` | C# ejemplo verificación offline |
| `signer_keys/private.pem` | Clave privada (solo servidor) |
| `signer_keys/public.pem` | Clave pública (embebida en C#) |
| `documentos.json` | Catálogo de documentos (codePdf, fileName, signatureConfig) |

---

## Testing Rápido

```bash
# 1. Ver json_jobs con URIs firmadas
curl "http://localhost:8080/api.php?op=json_jobs&case=mixed&graphic=true"

# 2. Descargar PDF real
curl -O "http://localhost:8080/api.php?op=sample&codigo=346db3fb-9c32-4af7-a44e-9aa741699d19"

# 3. Firmar on-demand (opcional)
curl -X POST http://localhost:8080/api.php?op=sign_uri \
  -H "Content-Type: application/json" \
  -d '{"params":[["from","http://..."],["to","http://..."],...],"kid":"default"}'

# 3. Verificar firma (C# offline)
dotnet run --project Consumo/
```

---

## Checklist de Seguridad

- [ ] Clave privada **nunca** en frontend ni logs
- [ ] Clave pública **embebida** en app C# (no descargada en runtime)
- [ ] Nonce único por URI (16 bytes random)
- [ ] Expiración 15 min (`exp`)
- [ ] SHA-256 del PDF verificado antes de firmar
- [ ] App C# verifica `exp`, `nonce`, `doc_sha256`, `Ed25519`
- [ ] `kid` permite rotación sin downtime
- [ ] `tlv` y `upload_simple` transmitidos firmados#   f i r m e a s y _ w e b  
 