-- Migración para agregar fecha límite a notificaciones
-- Ejecutar este script para agregar la funcionalidad de fecha límite

USE gestor_clientes_guiargo;

-- Agregar columna fecha_limite a la tabla notificaciones
ALTER TABLE notificaciones 
ADD COLUMN fecha_limite DATE NULL AFTER fecha_envio;

-- Agregar columna prioritaria para marcar notificaciones urgentes
ALTER TABLE notificaciones 
ADD COLUMN prioritaria BOOLEAN DEFAULT FALSE AFTER fecha_limite;

-- Crear índice para optimizar consultas por fecha límite
CREATE INDEX idx_notificaciones_fecha_limite ON notificaciones(fecha_limite);

-- Crear índice para optimizar consultas por prioritaria
CREATE INDEX idx_notificaciones_prioritaria ON notificaciones(prioritaria);

-- Actualizar notificaciones existentes con fecha límite basada en seguimientos
UPDATE notificaciones n
JOIN seguimientos s ON n.id_seguimiento = s.id
SET n.fecha_limite = s.fecha_programada
WHERE n.fecha_limite IS NULL;

-- Marcar como prioritarias las notificaciones que vencen hoy o ya vencieron
UPDATE notificaciones 
SET prioritaria = TRUE 
WHERE fecha_limite IS NOT NULL 
AND fecha_limite <= CURDATE() 
AND leida = FALSE;

-- Mostrar la estructura actualizada
DESCRIBE notificaciones;

-- Mostrar notificaciones con nueva funcionalidad
SELECT 
    n.id,
    n.mensaje,
    n.tipo,
    n.fecha_envio,
    n.fecha_limite,
    n.prioritaria,
    n.leida,
    s.descripcion as seguimiento_descripcion,
    s.fecha_programada
FROM notificaciones n
LEFT JOIN seguimientos s ON n.id_seguimiento = s.id
ORDER BY n.prioritaria DESC, n.fecha_limite ASC;
