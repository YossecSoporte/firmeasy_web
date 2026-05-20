# JSON Jobs — Endpoints de prueba

## Firma visible

```http
GET http://localhost:8080/api.php?op=json_jobs&case=visible
```

---

## Firma invisible

```http
GET http://localhost:8080/api.php?op=json_jobs&case=invisible
```

---

## Escenario mixto

```http
GET http://localhost:8080/api.php?op=json_jobs&case=mixed
```

---

# Modos de firma

## Firma directa

```http
GET http://localhost:8080/api.php?op=json_jobs&case=visible&mode=0
```

---

## Modo visor

```http
GET http://localhost:8080/api.php?op=json_jobs&case=visible&mode=1
```

---

# TSA

## Con TSA

```http
GET http://localhost:8080/api.php?op=json_jobs&case=visible&tsa=true
```

---

## Sin TSA

```http
GET http://localhost:8080/api.php?op=json_jobs&case=visible&tsa=false
```

---

# Imagen en sello

## Con imagen

```http
GET http://localhost:8080/api.php?op=json_jobs&case=visible&graphic=true
```

---

## Sin imagen

```http
GET http://localhost:8080/api.php?op=json_jobs&case=visible&graphic=false
```

---

# Escenarios enterprise completos

## Mixed + visor + TSA + imagen

```http
GET http://localhost:8080/api.php?op=json_jobs&case=mixed&mode=1&tsa=true&graphic=true
```

---

## Visible + firma directa + sin TSA

```http
GET http://localhost:8080/api.php?op=json_jobs&case=visible&mode=0&tsa=false
```

---

## Invisible + visor + sin TSA

```http
GET http://localhost:8080/api.php?op=json_jobs&case=invisible&mode=1&tsa=false
```

---

# Uso desde URI

## Visible

```text
firmeasyenterprise://?batch_json=http%3A%2F%2Flocalhost%3A8080%2Fapi.php%3Fop%3Djson_jobs%26case%3Dvisible&token_integration=TOKEN_TEST
```

---

## Invisible

```text
firmeasyenterprise://?batch_json=http%3A%2F%2Flocalhost%3A8080%2Fapi.php%3Fop%3Djson_jobs%26case%3Dinvisible&token_integration=TOKEN_TEST
```

---

## Mixed + visor + TSA

```text
firmeasyenterprise://?batch_json=http%3A%2F%2Flocalhost%3A8080%2Fapi.php%3Fop%3Djson_jobs%26case%3Dmixed%26mode%3D1%26tsa%3Dtrue&token_integration=TOKEN_TEST
```