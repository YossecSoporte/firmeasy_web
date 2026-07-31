# README - Pruebas del Protocolo FirmaEasy URI

## Introducción

Este documento contiene todas las pruebas recomendadas para validar el funcionamiento del protocolo:

```txt
firmeasy://
```

Incluye:

- Firma visible
- Firma invisible
- Modo automático
- Modo manual
- Texto
- Imagen
- Validaciones
- Seguridad
- Casos límite
- Rendimiento
- Compatibilidad futura

---

# Estructura Base

```txt
firmeasy://?from=URL_PDF&to=URL_UPLOAD
```

---

# Parámetros Soportados

| Parámetro | Descripción |
|---|---|
| from | URL del PDF origen |
| to | URL callback/upload |
| vis_sig_x | Posición X |
| vis_sig_y | Posición Y |
| vis_sig_page | Página |
| vis_sig_width | Ancho firma |
| vis_sig_height | Alto firma |
| vis_sig_text | Texto visible |
| vis_sig_graphic | Imagen/logo |
| vis_sig_rotation | Rotación |
| vis_sig_text_size | Tamaño texto |
| vis_sig_visible | Firma visible/invisible |
| ltv | LTV habilitado |
| tsp | Timestamp server |
| upload | Auto upload |

---

# Reglas de Modos

## Modo 0 (Manual)

Se detecta automáticamente cuando existen:

```txt
vis_sig_x
vis_sig_y
vis_sig_page
```

## Modo 1 (Automático)

Se detecta automáticamente cuando NO existen:

```txt
vis_sig_x
vis_sig_y
vis_sig_page
```

---

# PRUEBAS FUNCIONALES

## 1. FIRMA MANUAL SOLO TEXTO

```csharp
ProcesarUri(
"firmeasy://?" +
"from=https%3A%2F%2Fraw.githubusercontent.com%2FYossecSoporte%2Fpdf-test%2Fmain%2FPrueba_firmado.pdf" +
"&to=http%3A%2F%2Flocalhost%3A8080%2Fapi.php%3Fop%3Dsign_upload%26codigo%3Dbb59a806-f5f1-44b5-a629-87d5e2f0af76%26user_id%3Duser_iedfdde" +
"&vis_sig_text_size=6" +
"&vis_sig_text=%253CSIGNER%253E%250AFecha%253A%2520%253CDATE%253E%250AOU%253A%2520%253COU%253E%250ACon%2520FirmaEasy" +
"&ltv=0" +
"&&vis_sig_width=210&vis_sig_height=100" +
"&upload=true"
);
```

## Resultado Esperado

- Mode = 0
- Firma visible
- Texto renderizado correctamente

---

## 2. FIRMA MANUAL SOLO IMAGEN

```csharp
ProcesarUri(
"firmeasy://?" +
"from=https%3A%2F%2Fraw.githubusercontent.com%2FYossecSoporte%2Fpdf-test%2Fmain%2FPrueba_firmado.pdf" +
"&to=http%3A%2F%2Flocalhost%3A8080%2Fapi.php%3Fop%3Dsign_upload%26codigo%3Dbb59a806-f5f1-44b5-a629-87d5e2f0af76%26user_id%3Duser_iedfdde" +
"&ltv=0" +
"&vis_sig_width=210&vis_sig_height=100" +
"&vis_sig_graphic=https%3A%2F%2Fencrypted-tbn0.gstatic.com%2Fimages%3Fq%3Dtbn%3AANd9GcT0iTD7C0BZFEnu5OYvcsp_0YaK9yCaca62zQ%26s" +
"&upload=true"
);
```

---

## 3. FIRMA MANUAL TEXTO + IMAGEN

```csharp
ProcesarUri(
"firmeasy://?" +
"from=https%3A%2F%2Fraw.githubusercontent.com%2FYossecSoporte%2Fpdf-test%2Fmain%2FPrueba_firmado.pdf" +
"&to=http%3A%2F%2Flocalhost%3A8080%2Fapi.php%3Fop%3Dsign_upload%26codigo%3Dbb59a806-f5f1-44b5-a629-87d5e2f0af76%26user_id%3Duser_iedfdde" +
"&vis_sig_text_size=6" +
"&vis_sig_text=%253CSIGNER%253E%250AFecha%253A%2520%253CDATE%253E%250AOU%253A%2520%253COU%253E%250ACon%2520FirmaEasy" +
"&ltv=0" +
"&vis_sig_width=210&vis_sig_height=100" +
"&vis_sig_graphic=https%3A%2F%2Fencrypted-tbn0.gstatic.com%2Fimages%3Fq%3Dtbn%3AANd9GcT0iTD7C0BZFEnu5OYvcsp_0YaK9yCaca62zQ%26s" +
"&upload=true"
);
```

---

## 4. FIRMA AUTOMÁTICA SOLO TEXTO

```csharp
ProcesarUri(
"firmeasy://?" +
"from=https%3A%2F%2Fraw.githubusercontent.com%2FYossecSoporte%2Fpdf-test%2Fmain%2FPrueba_firmado.pdf" +
"&to=http%3A%2F%2Flocalhost%3A8080%2Fapi.php%3Fop%3Dsign_upload%26codigo%3Dbb59a806-f5f1-44b5-a629-87d5e2f0af76%26user_id%3Duser_iedfdde" +
"&vis_sig_text_size=6" +
"&vis_sig_text=%253CSIGNER%253E%250AFecha%253A%2520%253CDATE%253E%250AOU%253A%2520%253COU%253E%250ACon%2520FirmaEasy" +
"&upload=true"
);
```

---

## 5. FIRMA AUTOMÁTICA SOLO IMAGEN

```csharp
ProcesarUri(
"firmeasy://?" +
"from=https%3A%2F%2Fraw.githubusercontent.com%2FYossecSoporte%2Fpdf-test%2Fmain%2FPrueba_firmado.pdf" +
"&to=http%3A%2F%2Flocalhost%3A8080%2Fapi.php%3Fop%3Dsign_upload%26codigo%3Dbb59a806-f5f1-44b5-a629-87d5e2f0af76%26user_id%3Duser_iedfdde" +
"&vis_sig_graphic=https%3A%2F%2Fencrypted-tbn0.gstatic.com%2Fimages%3Fq%3Dtbn%3AANd9GcT0iTD7C0BZFEnu5OYvcsp_0YaK9yCaca62zQ%26s" +
"&upload=true"
);
```

---

## 6. FIRMA INVISIBLE

```csharp
ProcesarUri(
    "firmeasy://?" +
    "from=https%3A%2F%2Fraw.githubusercontent.com%2FYossecSoporte%2Fpdf-test%2Fmain%2FPrueba_firmado.pdf" +
    "&to=http%3A%2F%2Flocalhost%3A8080%2Fapi.php%3Fop%3Dsign_upload%26codigo%3Dbb59a806-f5f1-44b5-a629-87d5e2f0af76%26user_id%3Duser_iedfdde" +
    "&vis_sig_visible=false" +
    "&upload=true"
);
```

---

# PRUEBAS DE VALIDACIÓN

## 7. FALTA FROM

```csharp
ProcesarUri(
    "firmeasy://?" +
    "&to=http%3A%2F%2Flocalhost%3A8080%2Fapi.php%3Fop%3Dsign_upload%26codigo%3Dbb59a806-f5f1-44b5-a629-87d5e2f0af76%26user_id%3Duser_iedfdde" +
);
```

## Resultado Esperado

```txt
El parámetro obligatorio 'from' no existe o está vacío.
```

---

## 8. FALTA TEXTO E IMAGEN

```csharp
ProcesarUri(
    "firmeasy://?" +
    "from=https%3A%2F%2Fraw.githubusercontent.com%2FYossecSoporte%2Fpdf-test%2Fmain%2FPrueba_firmado.pdf" +
    "&to=http%3A%2F%2Flocalhost%3A8080%2Fapi.php%3Fop%3Dsign_upload%26codigo%3Dbb59a806-f5f1-44b5-a629-87d5e2f0af76%26user_id%3Duser_iedfdde" +
);
```

---

## 9. WIDTH INVÁLIDO

```csharp
ProcesarUri(
    "firmeasy://?" +
    "from=https%3A%2F%2Fraw.githubusercontent.com%2FYossecSoporte%2Fpdf-test%2Fmain%2FPrueba_firmado.pdf" +
    "&to=http%3A%2F%2Flocalhost%3A8080%2Fapi.php%3Fop%3Dsign_upload%26codigo%3Dbb59a806-f5f1-44b5-a629-87d5e2f0af76%26user_id%3Duser_iedfdde" +
    "&vis_sig_text=%253CSIGNER%253E%250AFecha%253A%2520%253CDATE%253E%250AOU%253A%2520%253COU%253E%250ACon%2520FirmaEasy" +
    "&vis_sig_x=100" +
    "&vis_sig_y=100" +
    "&vis_sig_page=1" +
    "&vis_sig_width=ABC" +
    "&vis_sig_height=100" +
    "&upload=true"
);
```

---

# PRUEBAS DE SEGURIDAD

## 10. FILE:// BLOQUEADO

```csharp
"&from=file:///c:/windows/test.pdf"
```

## 11. FTP BLOQUEADO

```csharp
"&from=ftp://server/test.pdf"
```

---

# PRUEBAS PDF

## 12. PÁGINA INEXISTENTE

```txt
vis_sig_page=999
```

## 13. POSICIÓN FUERA DEL PDF

```txt
vis_sig_x=9999
vis_sig_y=9999
```

---

# PRUEBAS DE RENDIMIENTO

## 14. PDF GRANDE

Escenario:

- 500 páginas
- 50MB
- imagen visible

---

# RECOMENDACIONES

- Validar límites PDF
- Validar URLs
- Validar HTTPS
- Validar tamaños máximos
- Validar PDFs corruptos

---

# OBSERVACIONES

- Todas las URLs deben ir URL encoded.
- Las imágenes deben ir encoded.
- Los textos deben ir encoded.
- El protocolo soporta crecimiento futuro.
