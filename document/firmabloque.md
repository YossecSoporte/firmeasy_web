# Estructura del CSV — Firma en bloque

## Columnas (índice base 0)

| Índice | Campo | Tipo | Default | Notas |
|--------|-------|------|---------|-------|
| 0 | `from` | URL | — | URL del PDF a firmar |
| 1 | `to` | URL | — | URL de destino (endpoint de subida) |
| 2 | `name_pdf` | string | — | Nombre del archivo PDF |
| 3 | `vis_sig_x` | int | 0 | Acepta decimal, se redondea |
| 4 | `vis_sig_y` | int | 0 | Acepta decimal, se redondea |
| 5 | `vis_sig_width` | int | 150 | Ancho del campo de firma |
| 6 | `vis_sig_height` | int | 60 | Alto del campo de firma |
| 7 | `vis_sig_text` | string | `""` | **Puede enviarse vacío**. Soporta `\n` |
| 8 | `vis_sig_graphic` | URL | `null` | URL de imagen o vacío |
| 9 | `vis_sig_page` | int | 1 | Página donde se ubica la firma |
| 10 | `vis_sig_text_size` | int | 10 | Tamaño de fuente del texto |
| 11 | `vis_sig_rotation` | int | 0 | Rotación de la firma en grados |
| 12 | `vis_sig_visible` | bool | `true` | `true` = firma visible / `false` = firma invisible |

> **Nota:** Si la columna 12 se omite o no es parseable como booleano, se asume `true`.

---

## Comportamiento según `vis_sig_visible`

| `vis_sig_visible` | Resultado |
|-------------------|-----------|
| `true` | Firma visible. El modo (0 o 1) se infiere según si x, y, page tienen valores. |
| `false` | Firma invisible. Se ignoran x, y, width, height, text y graphic. Se fuerza `mode=0`. |

**Inferencia de modo cuando `vis_sig_visible = true`:**
- Si `x = 0` o `y = 0` o `page = 0` → **modo 1** (el usuario ubica la firma)
- Si todos tienen valores → respeta el `mode` global recibido por parámetro

---

## El campo `vis_sig_text` puede ir vacío

El campo `vis_sig_text` (columna 7) es opcional y puede enviarse vacío. En el CSV se deja la columna sin valor entre las comas correspondientes.

---

## Ejemplo de CSV

```csv
http://localhost/pdf-descarga/main/pdfWeb.pdf,http://localhost:8080/api.php?op=sign_upload&codigo=a1f45880-81f4-4e2c-9fed-3c0a098d8cf2&user_id=user_k6ixj4g,Documento_prueba.pdf,340,695,150,55,,https://girasol.pe/favicon.png,1,6,,true
http://localhost/pdf-descarga/main/sample.pdf,http://localhost:8080/api.php?op=sign_upload&codigo=bb59a806-f5f1-44b5-a629-87d5e2f0af76&user_id=user_k6ixj4g,Documento_prueba_informacion.pdf,340,693,155,55,,https://girasol.pe/favicon.png,1,6,,true
http://localhost/pdf-descarga/main/pdf_6paginas.pdf,http://localhost:8080/api.php?op=sign_upload&codigo=5a5e91a4-c5ce-4cad-8ad9-fdafff8d1148&user_id=user_k6ixj4g,Multi_hojas.pdf,340,693,155,55,,https://girasol.pe/favicon.png,1,6,,true
```

### Observaciones del ejemplo

- La columna 7 (`vis_sig_text`) va **vacía** — dos comas seguidas: `...55,,https://...`
- La columna 11 (`vis_sig_rotation`) también va vacía en los tres registros
- La columna 12 (`vis_sig_visible`) es `true` en todos → firma visible
- Como x, y, width, height tienen valores, el modo final dependerá del `mode` global enviado al iniciar el proceso
