<?php
// Incluir archivo de conexión a la base de datos
require_once 'conexion.php';

// Iniciar sesión
session_start();

// Verificar si está logueado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de empresa requerido']);
    exit();
}

$id = intval($_GET['id']);

try {
    // Obtener empresa por ID
    $query = "SELECT * FROM empresas WHERE id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $empresa = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'empresa' => $empresa
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Empresa no encontrada']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}
?>
