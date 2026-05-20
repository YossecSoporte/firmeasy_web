# ❗ Errores Reportados

## 🖼️ Imagen no se adapta al tamaño enviado

### 📌 Descripción del problema

Cuando se utiliza una imagen en la firma (`vis_sig_graphic`), algunos casos reportan que **la imagen no respeta el tamaño esperado**.

Esto ocurre porque el sistema **no usa el tamaño real de la imagen**, sino el tamaño definido en el contenedor de firma.

---

## ✅ Solución

Para asegurar que la imagen se renderice correctamente, se debe cumplir lo siguiente en el CSV:

### 1. Definir el tamaño del contenedor

Las columnas:

* `vis_sig_width` (columna 5)
* `vis_sig_height` (columna 6)

son las que controlan el tamaño final de la firma.

---

### 2. Dejar vacío `vis_sig_text`

La columna:

* `vis_sig_text` (columna 7)

debe enviarse **vacía** cuando se usa imagen.

📌 Esto significa:

* No usar comillas (`""`)
* No usar espacios (`" "`)
* No usar `null`

👉 Simplemente dejar vacío entre comas:

```csv
...,220,100,,https://imagen.png,...
```

---

## 🧪 Ejemplo correcto

```csv
https://pdf-descarga/main/pdfWeb.pdf,http://localhost:8080/api.php?op=sign_upload&codigo=a1f45880-81f4-4e2c-9fed-3c0a098d8cf2&user_id=user_k6ixj4g,Documento_prueba.pdf,340,695,220,100,,https://cdn.pixabay.com/photo/2015/10/01/17/17/car-967387_1280.png,1,6
https://sample.pdf,http://localhost:8080/api.php?op=sign_upload&codigo=bb59a806-f5f1-44b5-a629-87d5e2f0af76&user_id=user_k6ixj4g,Documento_prueba_informacion.pdf,340,693,220,100,,https://cdn.pixabay.com/photo/2015/10/01/17/17/car-967387_1280.png,1,6
https://main/pdf_6paginas.pdf,http://localhost:8080/api.php?op=sign_upload&codigo=5a5e91a4-c5ce-4cad-8ad9-fdafff8d1148&user_id=user_k6ixj4g,Multi_hojas.pdf,340,693,220,100,,https://cdn.pixabay.com/photo/2015/10/01/17/17/car-967387_1280.png,1,6
```

---

## 🚫 Errores comunes

Evitar los siguientes casos en la columna 7:

```csv
...,220,100,"",https://...
...,220,100," ",https://...
...,220,100,null,https://...
...,220,100,texto,https://...
```

---

## 🧠 Nota técnica

* La imagen (`vis_sig_graphic`) se ajusta al contenedor definido por `width` y `height`
* Si se envía texto junto con imagen, el layout puede verse afectado
* Para firmas solo con imagen, siempre dejar `vis_sig_text` vacío

---

