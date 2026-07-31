# Firmeasy Signer - Especificación de Integración para Auditoría

## Resumen Ejecutivo

Este documento define los requisitos de integración para el componente **Firmeasy Signer** (aplicación de escritorio). Define **qué debe recibir la aplicación mediante URI**, **cómo debe validarse la firma**, y **las reglas de acceso al sistema**.

> **IMPORTANTE**: La aplicación **solo funcionará** si la URI recibida está firmada con las claves privadas oficiales de Firmeasy. Cualquier URI sin firma válida o con firma inválida será **rechazada** y no se permitirá el acceso al sistema de firma.

---

## 1. Formato de Entrada (URI `firmeasy:`)

La aplicación de escritorio se invoca mediante un protocolo personalizado `firmeasy:`. El sistema web genera esta URI y la pasa al SO, que lanza la aplicación.

### Formato Base

```
firmeasy:?from={url_descarga}&to={url_subida}&vis_sig_x={x}&vis_sig_y={y}&vis_sig_width={w}&vis_sig_height={h}&vis_sig_page={p}&vis_sig_text_size={size}&vis_sig_text={texto}&doc_sha256={hash}&tlv={0|1}&upload_simple=true&nonce={hex}&exp={timestamp}&kid={kid}&sig={firma_base64url}
```

### Parámetros Obligatorios

| Parámetro | Tipo | Descripción | Validación |
|-----------|------|-------------|------------|
| `from` | URL | URL de descarga del PDF original | Debe ser HTTPS, dominio autorizado |
| `to` | URL | Endpoint de subida del PDF firmado | Debe ser HTTPS, endpoint válido |
| `vis_sig_x` | int | Posición X de la firma visible (px) | ≥ 0 |
| `vis_sig_y` | int | Posición Y de la firma visible (px) | ≥ 0 |
| `vis_sig_width` | int | Ancho de la firma visible (px) | > 0 |
| `vis_sig_height` | int | Alto de la firma visible (px) | > 0 |
| `vis_sig_page` | int | Número de página (1-indexed) | ≥ 1 |
| `vis_sig_text_size` | int | Tamaño de fuente del texto (pt) | > 0 |
| `vis_sig_text` | string | Texto plantilla (URL-encoded) | UTF-8, placeholders `{name}`, `{date}`, `{OU}` |
| `doc_sha256` | hex (64 chars) | SHA-256 del PDF original | Debe coincidir con PDF descargado |
| `tlv` | `0\|1` | Usa sello de tiempo (TSA) | `0` o `1` |
| `upload_simple` | string | Modo simple | Siempre `"true"` |
| `nonce` | hex (32 chars) | Valor aleatorio único (anti-replay) | 32 chars hex, no repetido |
| `exp` | int (Unix ts) | Expiración (UTC) | Debe ser > ahora, máx 15 min |
| `kid` | string | Identificador de clave pública | Debe existir en keystore local |
| `sig` | base64url | Firma Ed25519 (detached) | **Obligatoria, verificada obligatoriamente** |

### Placeholders en `vis_sig_text`

| Placeholder | Se reemplaza por |
|-------------|------------------|
| `{name}` | Nombre del firmante |
| `{date}` | Fecha actual (formato local) |
| `{OU}` | Unidad organizativa |

---

## 2. Reglas de Validación Obligatorias

La aplicación **DEBE** implementar **todas** estas validaciones antes de proceder:

### 2.1 Verificación de Firma (CRÍTICO)

```csharp
// 1. Extraer parte firmada (entre "firmeasy:?" y "&sig=")
string signedPart = ExtractSignedPart(uri); // sin "&sig="

// 2. Obtener clave pública según KID
var publicKey = KeyStore.GetPublicKey(kid); // Embebida en la app

// 3. Verificar Ed25519 sobre bytes UTF-8 exactos
bool valid = Ed25519.Verify(
    data: Encoding.UTF8.GetBytes(signedPart),
    signature: Base64UrlDecode(sig),
    publicKey: publicKey
);

if (!valid) {
    REJECT("Firma Ed25519 inválida - URI no autorizada");
    return;
}
```

**Regla**: La firma se calcula sobre los **bytes UTF-8 exactos** de la cadena entre `firmeasy:?` y `&sig=` (incluyendo todos los parámetros en orden, con `encodeURIComponent` aplicado a cada valor). **No se debe decodificar ni re-encodear** antes de verificar.

### 2.2 Validaciones Adicionales (Todas obligatorias)

| Validación | Acción si falla |
|------------|-----------------|
| `exp` > ahora (UTC) | `REJECT("URI expirada")` |
| `nonce` no usado antes (ventana 15 min) | `REJECT("Replay detectado")` |
| `kid` existe en keystore local | `REJECT("Clave desconocida: {kid}")` |
| `doc_sha256` coincide con PDF descargado | `REJECT("PDF adulterado - hash no coincide")` |
| `from` y `to` son HTTPS + dominios autorizados | `REJECT("Dominio no autorizado")` |
| `tlv` ∈ {0,1}, `upload_simple` == "true" | `REJECT("Parámetros inválidos")` |

---

## 3. Flujo de Operación

```
1. Usuario pulsa "Firmar" en web
       ↓
2. Web genera URI firmada (clave privada Firmeasy)
       ↓
3. Navegador abre: firmeasy:?from=...&to=...&sig=...
       ↓
4. SO lanza Firmeasy Signer con URI completa
       ↓
4. App valida TODO (firma, exp, nonce, sha256, kid, dominios)
       ↓
   ✅ VÁLIDO → Continua
   ❌ INVÁLIDO → Muestra error, NO permite firmar
       ↓
5. Descarga PDF desde `from`
       ↓
6. Verifica SHA-256 del PDF descargado == `doc_sha256`
       ↓
7. Muestra UI de firma (posición, texto, gráfico)
       ↓
7. Usuario firma → Genera PDF firmado
       ↓
8. Sube PDF firmado a `to` (POST multipart, campo `pdf_file`)
       ↓
9. Si `tlv=1` → Solicita sello TSA al endpoint configurado
       ↓
10. Fin → Notifica éxito al usuario
```

---

## 4. Claves de Firma (Key Management)

### Claves Oficiales Firmeasy

| Entorno | KID | Clave Pública (embebida en app) | Uso |
|---------|-----|----------------------------------|-----|
| **Producción** | `prod-2024` | `MCowBQYDK2VwAyEA...` | Firmas oficiales |
| **Staging** | `staging-2024` | `MCowBQYDK2VwAyEA...` | Pruebas |
| **Desarrollo** | `dev-2024` | `MCowBQYDK2VwAyEA...` | Desarrollo local |

### Rotación de Claves
- La app **debe** soportar múltiples KIDs simultáneos
- Rotación: Se agrega nuevo KID a keystore local → Web firma con nuevo KID → App valida con nueva clave
- Claves antiguas se mantienen hasta expirar URIs pendientes (máx 15 min)

### Compromiso de Clave
Si clave privada se compromete:
1. Revocar KID en servidor
2. Publicar nueva versión de app con KID revocado removido
3. Forzar actualización obligatoria

---

## 4. Reglas de Acceso al Sistema

| Escenario | Resultado |
|-----------|-----------|
| URI sin parámetro `sig` | **BLOQUEADO** - "Firma requerida" |
| Firma Ed25519 inválida | **BLOQUEADO** - "Firma inválida" |
| `exp` < ahora | **BLOQUEADO** - "URI expirada" |
| `nonce` repetido (15 min) | **BLOQUEADO** - "Replay detectado" |
| `kid` no en keystore | **BLOQUEADO** - "Clave no autorizada" |
| `doc_sha256` ≠ hash real | **BLOQUEADO** - "Documento alterado" |
| Dominio `from`/`to` no autorizado | **BLOQUEADO** - "Origen/destino no autorizado" |
| `kid` revocado en servidor | **BLOQUEADO** - "Clave revocada" |

> **NINGUNA EXCEPCIÓN**. La aplicación no debe ofrecer "continuar de todos modos" ni modo compatibilidad.

---

## 5. Comportamiento de la Aplicación ante Errores

| Error | Mensaje al Usuario | Log Interno |
|-------|-------------------|-------------|
| Firma inválida | "La solicitud no está autorizada. Contacte a soporte." | `ERROR: Ed25519 verification failed for kid={kid}` |
| Expirada | "La solicitud ha expirado. Solicite una nueva firma." | `WARN: URI expired, exp={exp}, now={now}` |
| Replay | "Solicitud duplicada. Solicite una nueva firma." | `WARN: Nonce replay detected: {nonce}` |
| SHA256 mismatch | "El documento ha sido modificado. No se puede firmar." | `ERROR: SHA256 mismatch, expected={exp}, got={actual}` |
| Dominio no autorizado | "Origen o destino no permitido." | `WARN: Unauthorized domain in from/to` |

**No exponer detalles técnicos** (KID, nonce, hash) en mensajes al usuario final.

---

## 6. Requisitos de Implementación (Checklist Auditoría)

- [ ] Verificación Ed25519 **antes** de cualquier operación
- [ ] Claves públicas **embebidas** en binario (no descargadas en runtime)
- [ ] Soporte multi-KID con rotación sin downtime
- [ ] Nonce store con TTL 15 min (memoria o disco cifrado)
- [ ] Validación `exp` con reloj del sistema (tolerancia ±30s NTP)
- [ ] Descarga `from` con validación SSL/TLS + pinning opcional
- [ ] SHA-256 streaming (no cargar PDF completo en memoria si >50MB)
- [ ] Subida `to` con TLS mutuo si aplica
- [ ] TSA opcional (`tlv=1`) con certificado válido
- [ ] Logs de auditoría inmutables (firma, kid, nonce, exp, resultado)
- [ ] Sin fallback a modo "sin firma" o "compatibilidad"
- [ ] Actualización forzada ante revocación de KID
- [ ] Pruebas de penetración: URI manipulada, replay, expiración, key rotation

---

## 6. Esquema de URI (Ejemplo Completo)

```
firmeasy:?
  from=https%3A%2F%2Fdocs.firmeasy.legal%2Fdownload%2Fdoc123.pdf
  &to=https%3A%2F%2Fapi.firmeasy.legal%2Fupload%3Fdoc%3D123%26uid%3D456
  &vis_sig_x=340
  &vis_sig_y=693
  &vis_sig_width=155
  &vis_sig_height=55
  &vis_sig_page=1
  &vis_sig_text_size=10
  &vis_sig_text=Firmado%20por%3A%20%7Bname%7D%0AFechadoc_sha256=a1b2c3d4e5f6...
  &tlv=0
  &upload_simple=true
  &nonce=cb6f8264fa46902c7bfd993dcfdb863a
  &exp=1785195227
  &kid=prod-2024
  &sig=zC1pcIQuvbDprqVvon_dVb8NBnsD11yT9xMaVY5U3TF2Qe7ZzNcXSJFaBCvtkngrTWcC-g45DDjhB20VudYUAQ
```

---

## 7. Contacto Soporte Firmeasy

Para consultas de integración, rotación de claves o incidentes de seguridad:

- **Email**: seguridad@firmeasy.legal
- **SLA Incidencias Críticas**: 2 horas
- **Rotación Programada**: Cada 90 días (notificación previa 30 días)

---

**Versión**: 1.0  
**Clasificación**: Confidencial - Solo para auditores autorizados  
**Fecha**: 2024