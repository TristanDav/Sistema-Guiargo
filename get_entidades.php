<?php
// Incluir archivo de conexión a la base de datos
require_once 'conexion.php';

// Iniciar sesión
session_start();

// Verificar si está logueado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

// Obtener tipo de entidad
$tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';

if (empty($tipo)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo de entidad requerido']);
    exit();
}

try {
    $entidades = [];
    
    if ($tipo === 'cliente') {
        $query = "SELECT id, nombre, ciudad FROM clientes ORDER BY nombre";
        $result = $conexion->query($query);
        
        while ($row = $result->fetch_assoc()) {
            $entidades[] = [
                'id' => $row['id'],
                'nombre' => $row['nombre'],
                'ciudad' => $row['ciudad']
            ];
        }
    } elseif ($tipo === 'empresa') {
        $query = "SELECT id, nombre, ciudad FROM empresas ORDER BY nombre";
        $result = $conexion->query($query);
        
        while ($row = $result->fetch_assoc()) {
            $entidades[] = [
                'id' => $row['id'],
                'nombre' => $row['nombre'],
                'ciudad' => $row['ciudad']
            ];
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo de entidad no válido']);
        exit();
    }
    
    // Devolver respuesta JSON
    header('Content-Type: application/json');
    echo json_encode($entidades);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>
