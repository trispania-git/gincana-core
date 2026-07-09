# Changelog — Gincana Core

Registro de cambios por versión. El más reciente arriba.
Para el histórico anterior a la v1.0.117, consulta `git log`.

---

## 1.0.120 — 2026-07-09 — Fix pérdida de preguntas al editar (colisión de índices)

- **`metabox-prueba.php`**: el editor de pruebas calculaba el índice de la nueva
  pregunta contando bloques (`.length`). Si el organizador borraba una pregunta
  intermedia y añadía otra (o importaba por CSV tras un borrado), el índice
  colisionaba con uno ya existente y, al guardar, dos preguntas caían en el mismo
  índice y se perdía una sin aviso. Ahora el índice se calcula como el máximo
  existente + 1 (helper `nextPreguntaIdx`), tanto al añadir a mano como al importar
  CSV. Al guardar, PHP sigue reindexando el array, así que los índices se
  normalizan en la siguiente recarga.

## 1.0.119 — 2026-07-09 — Fix inyección de fórmulas en el CSV de ranking

- **`admin-users.php`**: la exportación del ranking a CSV neutraliza la inyección
  de fórmulas. El nombre de jugador y el email los controla un usuario de perfil
  bajo (al registrarse o entrar como invitado); `sanitize_text_field` no elimina
  `= + - @`, así que un nombre como `=HYPERLINK(...)` se ejecutaba al abrir el CSV
  en Excel/LibreOffice. Ahora cada celda que empieza por un carácter peligroso se
  prefija con comilla simple (helper `gincana_core_csv_safe`).

## 1.0.118 — 2026-07-09 — Integridad de la puntuación en el servidor (anti-trampas)

Refuerzo de seguridad. La concesión de puntos y el marcado de estaciones como
"superadas" dejan de fiarse del cliente. Antes, un jugador con la sesión iniciada
podía completar la gymkana entera y lograr la puntuación máxima **sin resolver
ninguna prueba**, llamando directamente a los endpoints REST (posee el nonce).

- **`/progress/complete`**: ahora exige un intento **acertado real** registrado en
  el servidor (`gincana_attempts.result='success'`) para esa (usuario, estación)
  antes de marcarla superada y puntuar. Si no existe, responde `403 no_success_attempt`.
  La prueba de la que se leen los puntos se toma de ese intento, no del dato que
  envía el navegador.
- **`/quiz/submit`**: se elimina el override del cliente `q_mode='lista_libre'`, que
  permitía anular toda la validación y "acertar" cualquier prueba. El modo "lista
  libre" se decide solo por el meta de servidor de la prueba.
- **`/quiz/submit`**: la heurística "parece lista libre" (respuesta con forma de
  array JSON plano) ya no salta la validación en preguntas con tipo validable; así
  no se puede acertar una pregunta de opción múltiple/texto enviando `["x"]`. Se
  mantiene como salvaguarda solo para preguntas sin tipo definido.
- **`/progress/complete` y `/progress/skip`**: se aplica el orden obligatorio también
  en el servidor (`gc_user_can_access_station`), no solo al pintar la pantalla. Si el
  escenario no fuerza orden, el comportamiento no cambia.

Pendiente (siguiente iteración de este bloque de seguridad):

- El tiempo de rapidez todavía admite el `time_ms` del cliente como fallback cuando
  no hay cronómetro de servidor (solo afecta al bonus de rapidez de quien ya ha
  acertado; ya no permite puntuar sin resolver).
- La solución del ahorcado sigue viajando en el DOM (`data-letter`/`data-original`).
- `?gc_quiz_reset=1` sigue siendo GET sin nonce.
- Verificación de GPS en el servidor (hoy la distancia se calcula solo en el navegador).

## 1.0.117 — 2026-07-09 — Fix XSS reflejado en el pie (gc_subpage)

- **`theme.php`**: se escapa con `esc_html()` el valor de la query var pública
  `gc_subpage`, que se imprimía sin filtrar en el comentario de diagnóstico del
  footer (presente en todas las páginas del front). Evitaba un XSS reflejado sin
  autenticar del tipo `?gc_subpage=--><script>…</script><!--`, que afectaba a
  cualquier visitante, incluido un administrador con sesión iniciada.
