# Firma Masiva - CSV Batch con Seguridad Ed25519

## Resumen

El sistema de Firma Masiva recibe un lote de documentos para firmar mediante un CSV firmado criptográficamente con Ed25519. El programa de escritorio debe:

1. Verificar la firma Ed25519 del batch URI
2. Descargar el CSV desde la URL indicada
3. Verificar el SHA-256 de cada PDF descargado
4. Proceder con la firma de cada documento

---

## Formato del CSV

**12 columnas**, separadas por coma, sin cabecera. El campo `sigText` (columna 7) puede ir entrecomillado si contiene saltos de línea.

| Col | Campo         | Tipo    | Ejemplo                                          | Descripción                                      |
|-----|---------------|---------|--------------------------------------------------|--------------------------------------------------|
| 0   | urlDescarga   | string  | `http://server/api.php?op=sample&codigo=UUID`    | URL de descarga del PDF original                  |
| 1   | urlSubida     | string  | `http://server/api.php?op=sign_upload&codigo=UUID&user_id=testuser` | URL donde el programa envía el PDF firmado (POST binario) |
| 2   | fileName      | string  | `doc_firmado3.pdf`                               | Nombre del archivo PDF                            |
| 3   | x             | int     | `340`                                            | Posición X de la firma visual (px)                |
| 4   | y             | int     | `693`                                            | Posición Y de la firma visual (px)                |
| 5   | width         | int     | `155`                                            | Ancho de la firma visual (px)                     |
| 6   | height        | int     | `55`                                             | Alto de la firma visual (px)                      |
| 7   | sigText       | string  | `"Firmado digitalmente por: \n<SIGNER>\nFecha: <DATE>\nOU: <OU>\nFirmado con FirmEasy"` | Texto de la firma. Saltos de línea como `\n`. Entrecomillado si contiene comas o saltos. |
| 8   | graphic       | string  | `https://girasol.pe/favicon.png`                 | URL de imagen gráfica de firma (vacío si no aplica) |
| 9   | page          | int     | `1`                                              | Número de página donde se coloca la firma         |
| 10  | textSize      | int     | `10`                                             | Tamaño de fuente del texto de firma               |
| 11  | sha256        | string  | `439baf0b5c2eb5667351f936c5c234b11a2393bb...`   | Hash SHA-256 del PDF original (hex)               |

### Ejemplo de CSV

```csv
http://server/api.php?op=sample&codigo=346db3fb-9c32-4af7-a44e-9aa741699d19,http://server/api.php?op=sign_upload&codigo=346db3fb-9c32-4af7-a44e-9aa741699d19&user_id=testuser,doc_firmado3.pdf,340,693,155,55,"Firmado digitalmente por: \n<SIGNER>\nFecha: <DATE>\nOU: <OU>\nFirmado con FirmEasy",,1,10,439baf0b5c2eb5667351f936c5c234b11a2393bb535ca0339baa6a8d138ddd32
http://server/api.php?op=sample&codigo=8ebe7192-4239-41fc-9dae-5ef40b747167,http://server/api.php?op=sign_upload&codigo=8ebe7192-4239-41fc-9dae-5ef40b747167&user_id=testuser,doc_prueba4.pdf,340,693,155,55,"Firmado digitalmente por: \n<SIGNER>\nFecha: <DATE>\nOU: <OU>\nFirmado con FirmEasy",,1,10,5fc98f69ee47bf444cf1b92826df3bb278c57f08f3e05899d4c4bf0191e9857c
```

---

## URI del Batch Firmado

Cuando el usuario presiona "Firma Masiva", la web genera el CSV, lo sube al servidor, y el servidor lo firma con Ed25519. El resultado es una URI con este formato:

```
firmeasy:?batch_csv=<URL_CSV>&nonce=<HEX32>&exp=<UNIX_TIMESTAMP>&kid=default&sig=<ED25519_B64URL>
```

### Parámetros

| Parametro   | Tipo   | Descripción                                                    |
|-------------|--------|----------------------------------------------------------------|
| batch_csv   | string | URL completa para descargar el CSV firmado                     |
| nonce       | string | 32 caracteres hexadecimales aleatorios (anti-replay)           |
| exp         | int    | Timestamp Unix de expiración (30 minutos desde creación)       |
| kid         | string | Identificador de la clave pública (`default`)                  |
| sig         | string | Firma Ed25519 en Base64url del string canónico (ver abajo)     |

### Ejemplo

```
firmeasy:?batch_csv=http%3A%2F%2Flocalhost%3A8080%2Fapi.php%3Fop%3Dcsv_download%26codigo%3Dtest_batch2&nonce=517034102adbc6ecd1c62261ce963d5d&exp=1785208287&kid=default&sig=DfiPQA3NnQVQbGf_0EkZ_RxJ4MYTYEV-rH_BBf-R4yfVSYlF-ckp9ZA9AnXNdD4fyKi4s0WBuv4wF_0JrJ93Dg
```

---

## Verificación Ed25519 en el Escritorio

### Algoritmo de verificación

1. **Extraer el string firmado y la firma** de la URI:
   ```
   Prefijo:  "firmeasy:?"
   Body:     "batch_csv=...&nonce=...&exp=...&kid=..."
   Separador: "&sig="  (buscar la ÚLTIMA ocurrencia)
   Firma:    base64url del bytes de firma Ed25519
   ```

2. **Reconstruir el string canónico** (sin decodificar):
   - Tomar todo el body desde después de `firmeasy:?` hasta antes de `&sig=`
   - Este string incluye los valores URL-encoded tal cual

3. **Verificar con Ed25519** usando la clave pública correspondiente al `kid`:
   ```
   Ed25519_Verificar(
     publicKey  = clave_publica_para_kid,
     message    = string_canónico (UTF-8 bytes),
     signature  = base64url_decode(sig)
   )
   ```

### Datos necesarios

| Dato             | Descripción                                    |
|------------------|------------------------------------------------|
| public.pem       | Clave pública Ed25519 en formato PEM (SPKI)    |
| kid              | Identificador (`default`)                       |

### Validaciones adicionales

- `exp` debe ser mayor que `Date.now() / 1000` (no expirado)
- `batch_csv` debe ser una URL accesible y devolver un CSV válido
- La respuesta de `csv_sign` también retorna `csv_hash` (SHA-256 del contenido CSV) para referencia, pero la verificación se hace con la firma Ed25519

---

## Verificación SHA-256 del PDF

Después de descargar cada PDF desde `urlDescarga` (columna 0), verificar:

```
SHA256_contenido_pdf == columna_11_del_csv
```

En C#:
```csharp
using var sha256 = SHA256.Create();
using var stream = File.OpenRead(pdfPath);
string hash = BitConverter.ToString(sha256.ComputeHash(stream)).Replace("-", "").ToLowerInvariant();

if (hash != csvRow.Sha256)
    throw new Exception($"SHA-256 no coincide para {csvRow.FileName}");
```

En Node.js:
```javascript
const crypto = require('crypto');
const hash = crypto.createHash('sha256').update(fs.readFileSync(pdfPath)).digest('hex');

if (hash !== csvRow.sha256)
    throw new Error(`SHA-256 mismatch for ${csvRow.fileName}`);
```

---

## Flujo Completo del Programa de Escritorio

```
1. Recibir URI batch:
   firmeasy:?batch_csv=URL&nonce=...&exp=...&kid=...&sig=...

2. VALIDAR firma Ed25519:
   a. Extraer string canónico (sin prefijo, sin &sig=...)
   b. Verificar Ed25519 con public.pem
   c. Verificar que exp > timestamp_actual

3. DESCARGAR CSV desde batch_csv:
   GET batch_csv_url → contenido CSV

4. PARA CADA FILA del CSV:
   a. Parsear las 12 columnas
   b. DESCARGAR PDF desde urlDescarga (columna 0)
   c. VERIFICAR SHA-256 del PDF contra sha256 (columna 11)
   d. APLICAR firma visual:
      - Posición: x, y (columnas 3,4)
      - Tamaño: width, height (columnas 5,6)
      - Texto: sigText (columna 7) con placeholders:
        <SIGNER> → nombre del firmante
        <DATE>   → fecha/hora de firma
        <OU>     → unidad organizacional
      - Página: page (columna 9)
      - TextSize: textSize (columna 10)
      - Graphic: graphic (columna 8, opcional)
   e. ENVIAR PDF firmado a urlSubida (columna 1):
      POST binario al URL con Content-Type: application/pdf
```

---

## Estructura del JSON de respuesta de `csv_sign`

```json
{
  "success": true,
  "download_url": "http://server/api.php?op=csv_download&codigo=test_batch2",
  "batch": {
    "csv_hash": "a44e5fe1d3a885019c31f372c20a3f66c8bf61dc...",
    "nonce": "517034102adbc6ecd1c62261ce963d5d",
    "exp": 1785208287,
    "kid": "default",
    "signed_uri": "firmeasy:?batch_csv=...&nonce=...&exp=...&kid=...&sig=..."
  }
}
```

---

## Placeholders del Texto de Firma

| Placeholder  | Se reemplaza por                        |
|--------------|------------------------------------------|
| `<SIGNER>`   | Nombre del firmante (del sistema)        |
| `<DATE>`     | Fecha/hora actual de la firma            |
| `<OU>`       | Unidad organizacional del firmante       |

Ejemplo renderizado:
```
Firmado digitalmente por: 
Juan Perez
Fecha: 2026-07-28 10:30:00
OU: Contabilidad
Firmado con FirmEasy
```

---

## Endpoints de la API

| Método | Endpoint                          | Descripción                          |
|--------|-----------------------------------|--------------------------------------|
| POST   | `?op=csv_upload&csv=ID`          | Sube el CSV al servidor              |
| POST   | `?op=csv_sign&codigo=ID`         | Firma Ed25519 el batch y retorna URI |
| GET    | `?op=csv_download&codigo=ID`     | Descarga el CSV                      |
| GET    | `?op=json_jobs&case=mixed`       | Retorna documentos con SHA-256 y signed_uri |
| POST   | `?op=sign_upload&codigo=UUID&user_id=USER` | Recibe PDF firmado (POST binario)  |
