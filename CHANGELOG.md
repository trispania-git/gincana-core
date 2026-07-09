# Changelog — Gincana Core

Registro de cambios por versión. El más reciente arriba.
Para el histórico anterior a la v1.0.117, consulta `git log`.

---

## 1.0.117 — 2026-07-09 — Fix XSS reflejado en el pie (gc_subpage)

- **`theme.php`**: se escapa con `esc_html()` el valor de la query var pública
  `gc_subpage`, que se imprimía sin filtrar en el comentario de diagnóstico del
  footer (presente en todas las páginas del front). Evitaba un XSS reflejado sin
  autenticar del tipo `?gc_subpage=--><script>…</script><!--`, que afectaba a
  cualquier visitante, incluido un administrador con sesión iniciada.
