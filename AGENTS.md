# Regla obligatoria de ramas

- Trabaja **exclusivamente en `main`**. Antes de modificar archivos, crear commits, sincronizar o publicar, comprueba que la rama actual sea `main` y actualízala desde `origin/main`.
- **Nunca crees otra rama** de Git, una rama de base de datos (incluidas Turso u otras plataformas), un worktree ni una rama temporal para implementar, probar o desplegar cambios.
- Guarda los cambios y commits solo en `main`; si publicas en GitHub, hazlo solo hacia `origin/main`. No envíes commits a otras ramas.
- Si `main` no puede actualizarse o aparecen conflictos, resuélvelos sobre `main`. Si no puedes hacerlo sin riesgo de perder trabajo, detente y comunica el bloqueo; no crees una rama alternativa.
- Al limpiar ramas antiguas, elimina únicamente las que ya estén integradas por completo en `main` y después de comprobar que no contienen cambios exclusivos. Esta limpieza no autoriza crear ramas nuevas.
