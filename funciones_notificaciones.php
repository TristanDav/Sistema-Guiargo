<?php
// Funciones comunes para notificaciones
// Incluir archivo de conexión a la base de datos
require_once 'conexion.php';

// Función para obtener contador de notificaciones no leídas
function getContadorNotificaciones($conexion, $usuario_id) {
    $query = "SELECT COUNT(*) as total FROM notificaciones WHERE id_usuario_destino = ? AND leida = 0";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'];
}

// Función para obtener contador de notificaciones prioritarias
function getContadorPrioritarias($conexion, $usuario_id) {
    $query = "SELECT COUNT(*) as total FROM notificaciones WHERE id_usuario_destino = ? AND prioritaria = 1 AND leida = 0";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'];
}

// Función para actualizar notificaciones prioritarias automáticamente
function actualizarPrioritarias($conexion) {
    $query = "UPDATE notificaciones 
              SET prioritaria = 1 
              WHERE fecha_limite IS NOT NULL 
              AND fecha_limite <= CURDATE() 
              AND leida = 0 
              AND prioritaria = 0";
    $stmt = $conexion->prepare($query);
    return $stmt->execute();
}
?>
