<?php
// Incluir archivo de conexión a la base de datos
require_once 'conexion.php';

// Iniciar sesión
session_start();

// Verificar si está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}

// Función para obtener notificaciones con filtros
function getNotificaciones($conexion, $filters = []) {
    $query = "SELECT n.*, s.descripcion as seguimiento_descripcion, s.fecha_programada, s.estatus as seguimiento_estatus,
              u.username as usuario_destino, u2.username as usuario_creacion
              FROM notificaciones n
              LEFT JOIN seguimientos s ON n.id_seguimiento = s.id
              LEFT JOIN usuarios u ON n.id_usuario_destino = u.id
              LEFT JOIN usuarios u2 ON s.id_usuario_asignado = u2.id
              WHERE 1=1";
    $params = [];
    $types = '';
    
    // Filtros
    if (!empty($filters['tipo'])) {
        $query .= " AND n.tipo = ?";
        $params[] = $filters['tipo'];
        $types .= 's';
    }
    
    if (!empty($filters['leida'])) {
        $query .= " AND n.leida = ?";
        $params[] = $filters['leida'];
        $types .= 'i';
    }
    
    if (!empty($filters['prioritaria'])) {
        $query .= " AND n.prioritaria = ?";
        $params[] = $filters['prioritaria'];
        $types .= 'i';
    }
    
    if (!empty($filters['fecha_desde'])) {
        $query .= " AND DATE(n.fecha_envio) >= ?";
        $params[] = $filters['fecha_desde'];
        $types .= 's';
    }
    
    if (!empty($filters['fecha_hasta'])) {
        $query .= " AND DATE(n.fecha_envio) <= ?";
        $params[] = $filters['fecha_hasta'];
        $types .= 's';
    }
    
    $query .= " ORDER BY n.prioritaria DESC, n.fecha_limite ASC, n.fecha_envio DESC";
    
    $stmt = $conexion->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Función para obtener notificación por ID
function getNotificacionById($conexion, $id) {
    $query = "SELECT n.*, s.descripcion as seguimiento_descripcion, s.fecha_programada, s.estatus as seguimiento_estatus,
              u.username as usuario_destino, u2.username as usuario_creacion
              FROM notificaciones n
              LEFT JOIN seguimientos s ON n.id_seguimiento = s.id
              LEFT JOIN usuarios u ON n.id_usuario_destino = u.id
              LEFT JOIN usuarios u2 ON s.id_usuario_asignado = u2.id
              WHERE n.id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Función para marcar notificación como leída
function marcarComoLeida($conexion, $id) {
    $query = "UPDATE notificaciones SET leida = 1 WHERE id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('i', $id);
    return $stmt->execute();
}

// Función para marcar todas como leídas
function marcarTodasComoLeidas($conexion, $usuario_id) {
    $query = "UPDATE notificaciones SET leida = 1 WHERE id_usuario_destino = ? AND leida = 0";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('i', $usuario_id);
    return $stmt->execute();
}

// Función para eliminar notificación
function eliminarNotificacion($conexion, $id) {
    $query = "DELETE FROM notificaciones WHERE id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('i', $id);
    return $stmt->execute();
}

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

// Función para verificar si una notificación está vencida
function isNotificacionVencida($fecha_limite) {
    if (empty($fecha_limite)) return false;
    return strtotime($fecha_limite) < strtotime('today');
}

// Función para verificar si una notificación vence hoy
function isNotificacionVenceHoy($fecha_limite) {
    if (empty($fecha_limite)) return false;
    return strtotime($fecha_limite) == strtotime('today');
}

// Función para obtener todos los usuarios
function getUsuarios($conexion) {
    $query = "SELECT id, username, email FROM usuarios WHERE activo = 1 ORDER BY username";
    $result = $conexion->query($query);
    return $result;
}

// Función para obtener todos los clientes
function getClientes($conexion) {
    $query = "SELECT id, nombre, ciudad, telefono FROM clientes ORDER BY nombre";
    $result = $conexion->query($query);
    return $result;
}

// Función para obtener todas las empresas
function getEmpresas($conexion) {
    $query = "SELECT id, nombre, ciudad, telefono FROM empresas ORDER BY nombre";
    $result = $conexion->query($query);
    return $result;
}

// Función para crear nueva notificación
function crearNotificacion($conexion, $data) {
    // Si no hay seguimiento, crear uno básico
    if (empty($data['id_seguimiento'])) {
        $seguimiento_data = [
            'id_entidad' => 1, // ID por defecto
            'tipo_entidad' => 'cliente',
            'id_usuario_asignado' => $_SESSION['usuario_id'],
            'fecha_programada' => date('Y-m-d'),
            'descripcion' => 'Notificación general'
        ];
        $data['id_seguimiento'] = crearSeguimiento($conexion, $seguimiento_data);
    }
    
    $query = "INSERT INTO notificaciones (id_seguimiento, id_usuario_destino, tipo, mensaje, fecha_envio, fecha_limite, prioritaria) VALUES (?, ?, ?, ?, NOW(), ?, ?)";
    $stmt = $conexion->prepare($query);
    
    // Manejar fecha_limite NULL
    $fecha_limite = !empty($data['fecha_limite']) ? $data['fecha_limite'] : null;
    
    $stmt->bind_param('iisssi', 
        $data['id_seguimiento'], 
        $data['id_usuario_destino'], 
        $data['tipo'], 
        $data['mensaje'], 
        $fecha_limite, 
        $data['prioritaria']
    );
    return $stmt->execute();
}

// Función para crear seguimiento
function crearSeguimiento($conexion, $data) {
    $query = "INSERT INTO seguimientos (id_entidad, tipo_entidad, id_usuario_asignado, fecha_programada, descripcion, estatus) VALUES (?, ?, ?, ?, ?, 'Pendiente')";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('isiss', 
        $data['id_entidad'], 
        $data['tipo_entidad'], 
        $data['id_usuario_asignado'], 
        $data['fecha_programada'], 
        $data['descripcion']
    );
    $stmt->execute();
    return $conexion->insert_id;
}

// Función para obtener clase CSS del tipo
function getTipoClass($tipo) {
    switch (strtolower($tipo)) {
        case 'notificacion':
            return 'tipo-notificacion';
        case 'alerta':
            return 'tipo-alerta';
        default:
            return 'tipo-default';
    }
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'marcar_leida':
                $id = $_POST['id'];
                if (marcarComoLeida($conexion, $id)) {
                    $_SESSION['success'] = 'Notificación marcada como leída';
                } else {
                    $_SESSION['error'] = 'Error al marcar la notificación';
                }
                break;
                
            case 'marcar_todas':
                if (marcarTodasComoLeidas($conexion, $_SESSION['usuario_id'])) {
                    $_SESSION['success'] = 'Todas las notificaciones marcadas como leídas';
                } else {
                    $_SESSION['error'] = 'Error al marcar las notificaciones';
                }
                break;
                
            case 'eliminar':
                $id = $_POST['id'];
                if (eliminarNotificacion($conexion, $id)) {
                    $_SESSION['success'] = 'Notificación eliminada exitosamente';
                } else {
                    $_SESSION['error'] = 'Error al eliminar la notificación';
                }
                break;
                
            case 'crear':
                // Crear seguimiento si es necesario
                $id_seguimiento = null;
                if (!empty($_POST['crear_seguimiento']) && !empty($_POST['id_entidad']) && !empty($_POST['tipo_entidad'])) {
                    $seguimiento_data = [
                        'id_entidad' => $_POST['id_entidad'],
                        'tipo_entidad' => $_POST['tipo_entidad'],
                        'id_usuario_asignado' => $_SESSION['usuario_id'],
                        'fecha_programada' => $_POST['fecha_programada'],
                        'descripcion' => $_POST['descripcion_seguimiento']
                    ];
                    $id_seguimiento = crearSeguimiento($conexion, $seguimiento_data);
                }
                
                // Validar que se seleccionaron usuarios
                if (empty($_POST['usuarios_destino']) || !is_array($_POST['usuarios_destino'])) {
                    $_SESSION['error'] = 'Debe seleccionar al menos un usuario destino';
                } else {
                    // Crear notificaciones para cada usuario seleccionado
                    $usuarios_destino = $_POST['usuarios_destino'];
                    $success_count = 0;
                    $error_count = 0;
                    
                    foreach ($usuarios_destino as $usuario_id) {
                        $notificacion_data = [
                            'id_seguimiento' => $id_seguimiento,
                            'id_usuario_destino' => $usuario_id,
                            'tipo' => $_POST['tipo'],
                            'mensaje' => $_POST['mensaje'],
                            'fecha_limite' => !empty($_POST['fecha_limite']) ? $_POST['fecha_limite'] : null,
                            'prioritaria' => !empty($_POST['prioritaria']) ? 1 : 0
                        ];
                        
                        if (crearNotificacion($conexion, $notificacion_data)) {
                            $success_count++;
                        } else {
                            $error_count++;
                            // Log del error para debugging
                            error_log("Error creando notificación: " . $conexion->error);
                        }
                    }
                    
                    if ($error_count == 0) {
                        $_SESSION['success'] = "Notificación creada exitosamente para $success_count usuario(s)";
                    } else {
                        $_SESSION['error'] = "Notificación creada para $success_count usuario(s), $error_count error(es). Revisa los logs para más detalles.";
                    }
                }
                break;
        }
        
        // Redireccionar para evitar reenvío del formulario
        header('Location: notificaciones.php');
        exit();
    }
}

// Actualizar notificaciones prioritarias automáticamente
actualizarPrioritarias($conexion);

// Obtener parámetros de filtros
$filters = [
    'tipo' => isset($_GET['tipo']) ? trim($_GET['tipo']) : '',
    'leida' => isset($_GET['leida']) ? trim($_GET['leida']) : '',
    'prioritaria' => isset($_GET['prioritaria']) ? trim($_GET['prioritaria']) : '',
    'fecha_desde' => isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '',
    'fecha_hasta' => isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : ''
];

// Obtener notificaciones
$notificaciones = getNotificaciones($conexion, $filters);

// Obtener contadores
$contador_no_leidas = getContadorNotificaciones($conexion, $_SESSION['usuario_id']);
$contador_prioritarias = getContadorPrioritarias($conexion, $_SESSION['usuario_id']);

// Obtener datos para el formulario
$usuarios = getUsuarios($conexion);
$clientes = getClientes($conexion);
$empresas = getEmpresas($conexion);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones - Sistema Guiargo</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/material.min.css">
    <link rel="stylesheet" href="css/material-design-iconic-font.min.css">
    <link rel="stylesheet" href="css/main.css">
    
    <!-- JavaScript -->
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
    <script>window.jQuery || document.write('<script src="js/jquery-1.11.2.min.js"><\/script>')</script>
    <script src="js/material.min.js"></script>
    <script src="js/main.js"></script>
    
    <style>
        /* Estilos modernos para el panel de notificaciones */
        :root {
            --primary-color: #0b1786;
            --secondary-color: #0b1786;
            --accent-color: #f39c12;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --light-bg: #f8f9fa;
            --dark-text: #2c3e50;
            --light-text: #6c757d;
            --white: #ffffff;
            --shadow: 0 2px 10px rgba(11, 23, 134, 0.1);
            --border-radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #0b1786 0%, #1a2a8a 100%);
            min-height: 100vh;
            color: var(--dark-text);
        }

        /* Navbar Superior */
        .top-navbar {
            background: var(--white);
            box-shadow: var(--shadow);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .navbar-brand i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }

        .company-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-image {
            height: 65px;
            width: auto;
            max-width: 250px;
            object-fit: contain;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .logo-main-text {
            font-size: 1.4rem;
            font-weight: bold;
            line-height: 1;
        }

        .logo-grupo {
            color: var(--primary-color);
        }

        .logo-guiargo {
            color: var(--accent-color);
        }

        .logo-tagline {
            font-size: 0.7rem;
            color: var(--light-text);
            font-weight: normal;
            margin-top: 2px;
        }

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .logout-btn {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 80px;
            left: 0;
            width: 280px;
            height: calc(100vh - 80px);
            background: var(--white);
            box-shadow: var(--shadow);
            overflow-y: auto;
            z-index: 999;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), #1a2a8a);
            color: white;
            text-align: center;
        }

        .sidebar-header h3 {
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .sidebar-header p {
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .menu-section {
            margin-bottom: 1.5rem;
        }

        .menu-section-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.8rem;
            font-weight: bold;
            color: var(--light-text);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .menu-item {
            display: block;
            padding: 1rem 1.5rem;
            color: var(--dark-text);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .menu-item:hover {
            background: var(--light-bg);
            border-left-color: var(--primary-color);
            color: var(--primary-color);
        }

        .menu-item.active {
            background: var(--light-bg);
            border-left-color: var(--primary-color);
            color: var(--primary-color);
        }

        .menu-item i {
            margin-right: 1rem;
            width: 20px;
            text-align: center;
        }

        /* Contenido Principal */
        .main-content {
            margin-left: 280px;
            margin-top: 80px;
            padding: 2rem;
            min-height: calc(100vh - 80px);
        }

        .page-header {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--light-text);
            font-size: 1.1rem;
        }

        /* Controles de filtros */
        .search-filters {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--dark-text);
        }

        .filter-group select,
        .filter-group input {
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .search-actions {
            display: flex;
            gap: 1rem;
            align-items: end;
        }

        .btn-search {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .btn-search:hover {
            background: #0a1469;
            transform: translateY(-2px);
        }

        .btn-clear {
            background: var(--light-text);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .btn-clear:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        /* Controles de vista */
        .view-controls {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-new {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-new:hover {
            background: #219a52;
            transform: translateY(-2px);
        }

        .btn-mark-all {
            background: var(--info-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-mark-all:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        /* Lista de Notificaciones */
        .notificaciones-container {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .notificaciones-header {
            background: var(--primary-color);
            color: white;
            padding: 1rem 2rem;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notificacion-item {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
        }

        .notificacion-item:hover {
            background: var(--light-bg);
        }

        .notificacion-item.no-leida {
            background: #f8f9ff;
            border-left: 4px solid var(--primary-color);
        }

        .notificacion-item.prioritaria {
            background: #fff5f5;
            border-left: 4px solid var(--danger-color);
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.2);
        }

        .notificacion-item.prioritaria.no-leida {
            background: #fff0f0;
            border-left: 4px solid var(--danger-color);
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }

        .notificacion-prioritaria {
            background: var(--danger-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .notificacion-vencida {
            background: #8b0000;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .notificacion-vence-hoy {
            background: var(--warning-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .notificacion-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .notificacion-titulo {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--dark-text);
            margin-bottom: 0.5rem;
        }

        .notificacion-tipo {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tipo-notificacion {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }

        .tipo-alerta {
            background: #ffebee;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
        }

        .tipo-default {
            background: #e9ecef;
            color: #495057;
            border: 1px solid #dee2e6;
        }

        .notificacion-mensaje {
            color: var(--light-text);
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .notificacion-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: var(--light-text);
        }

        .notificacion-fecha {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notificacion-acciones {
            display: flex;
            gap: 0.5rem;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-leer {
            background: var(--success-color);
            color: white;
        }

        .btn-leer:hover {
            background: #219a52;
        }

        .btn-delete {
            background: var(--danger-color);
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .btn-leida {
            background: var(--light-text);
            color: white;
        }

        .btn-leida:hover {
            background: #5a6268;
        }

        /* Contador de notificaciones */
        .notification-badge.prioritaria {
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: bold;
            min-width: 20px;
            text-align: center;
        }

        /* Alertas */
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .filters-row {
                grid-template-columns: 1fr;
            }

            .notificacion-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .notificacion-acciones {
                margin-top: 1rem;
            }
        }

        /* Animaciones */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notificacion-item {
            animation: fadeInUp 0.6s ease forwards;
        }

        .notificacion-item:nth-child(1) { animation-delay: 0.1s; }
        .notificacion-item:nth-child(2) { animation-delay: 0.2s; }
        .notificacion-item:nth-child(3) { animation-delay: 0.3s; }
        .notificacion-item:nth-child(4) { animation-delay: 0.4s; }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideInUp 0.3s ease;
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: bold;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: background 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--dark-text);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .checkbox-group label {
            margin: 0;
            font-weight: normal;
        }

        .usuarios-destino {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: var(--border-radius);
            padding: 1rem;
            background: var(--light-bg);
        }

        .usuario-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            transition: background 0.3s ease;
        }

        .usuario-option:hover {
            background: rgba(11, 23, 134, 0.1);
        }

        .usuario-option input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn-modal {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #0a1469;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--light-text);
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- Navbar Superior -->
    <nav class="top-navbar">
        <div class="company-logo">
            <!-- Logo de la Empresa -->
            <img src="assets/img/Logo-guiargo.png" alt="Logo Grupo Guiargo" class="logo-image">
            
            <!-- Texto del Logo -->
            <div class="logo-text">
                <div class="logo-main-text">
                    <span class="logo-grupo">GRUPO</span> <span class="logo-guiargo">GUIARGO</span>
                </div>
                <div class="logo-tagline">CONSULTORÍA, CAPACITACIÓN Y CENTRO EVALUADOR</div>
            </div>
        </div>
        <div class="navbar-user">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
                <span><?php echo $_SESSION['username']; ?></span>
                <?php if ($contador_prioritarias > 0): ?>
                    <span class="notification-badge prioritaria"><?php echo $contador_prioritarias; ?></span>
                <?php endif; ?>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="zmdi zmdi-power"></i>
                Cerrar Sesión
            </a>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h3>Panel de Control</h3>
            <p>Bienvenido, <?php echo $_SESSION['username']; ?></p>
        </div>
        
        <nav class="sidebar-menu">
            <!-- Dashboard -->
            <div class="menu-section">
                <div class="menu-section-title">Principal</div>
                <a href="home_guiargo.php" class="menu-item">
                    <i class="zmdi zmdi-view-dashboard"></i>
                    Dashboard
                </a>
            </div>

            <!-- Administración -->
            <div class="menu-section">
                <div class="menu-section-title">Administración</div>
                <a href="empresas.php" class="menu-item">
                    <i class="zmdi zmdi-city-alt"></i>
                    Empresas
                </a>
                <a href="notificaciones.php" class="menu-item active">
                    <i class="zmdi zmdi-notifications"></i>
                    Notificaciones
                </a>
            </div>

            <!-- Usuarios -->
            <div class="menu-section">
                <div class="menu-section-title">Usuarios</div>
                <a href="usuarios.php" class="menu-item">
                    <i class="zmdi zmdi-account-circle"></i>
                    Usuarios
                </a>
                <a href="clientes.php" class="menu-item">
                    <i class="zmdi zmdi-accounts"></i>
                    Clientes
                </a>
            </div>

            <!-- Sistema -->
            <div class="menu-section">
                <div class="menu-section-title">Sistema</div>
                <a href="notificaciones.php" class="menu-item active">
                    <i class="zmdi zmdi-notifications"></i>
                    Notificaciones
                    <?php if ($contador_prioritarias > 0): ?>
                        <span class="notification-badge prioritaria"><?php echo $contador_prioritarias; ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Configuración -->
            <div class="menu-section">
                <div class="menu-section-title">Configuración</div>
                <a href="#" class="menu-item">
                    <i class="zmdi zmdi-settings"></i>
                    Configuración
                </a>
            </div>
        </nav>
    </aside>

    <!-- Contenido Principal -->
    <main class="main-content">
        <!-- Header de la página -->
        <section class="page-header">
            <h1 class="page-title">Notificaciones y Recordatorios</h1>
            <p class="page-subtitle">Gestiona tus notificaciones y mantén el control de tus tareas pendientes</p>
        </section>

        <!-- Alertas -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Filtros -->
        <section class="search-filters">
            <form method="GET" action="notificaciones.php">
                <div class="filters-row">
                    <div class="filter-group">
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="">Todos los tipos</option>
                            <option value="Notificacion" <?php echo $filters['tipo'] == 'Notificacion' ? 'selected' : ''; ?>>Notificación</option>
                            <option value="Alerta" <?php echo $filters['tipo'] == 'Alerta' ? 'selected' : ''; ?>>Alerta</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Estado</label>
                        <select name="leida">
                            <option value="">Todos los estados</option>
                            <option value="0" <?php echo $filters['leida'] === '0' ? 'selected' : ''; ?>>No leídas</option>
                            <option value="1" <?php echo $filters['leida'] === '1' ? 'selected' : ''; ?>>Leídas</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Prioridad</label>
                        <select name="prioritaria">
                            <option value="">Todas las prioridades</option>
                            <option value="1" <?php echo $filters['prioritaria'] === '1' ? 'selected' : ''; ?>>Prioritarias</option>
                            <option value="0" <?php echo $filters['prioritaria'] === '0' ? 'selected' : ''; ?>>Normales</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Fecha Desde</label>
                        <input type="date" name="fecha_desde" value="<?php echo htmlspecialchars($filters['fecha_desde']); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" value="<?php echo htmlspecialchars($filters['fecha_hasta']); ?>">
                    </div>
                </div>
                
                <div class="search-actions">
                    <button type="submit" class="btn-search">
                        <i class="zmdi zmdi-search"></i> Filtrar
                    </button>
                    <a href="notificaciones.php" class="btn-clear">
                        <i class="zmdi zmdi-close"></i> Limpiar
                    </a>
                </div>
            </form>
        </section>

        <!-- Controles de Vista -->
        <section class="view-controls">
            <div>
                <span>Total de notificaciones: <strong><?php echo $notificaciones->num_rows; ?></strong></span>
                <?php if ($contador_no_leidas > 0): ?>
                    <span style="margin-left: 1rem; color: var(--primary-color);">
                        <i class="zmdi zmdi-email"></i> <?php echo $contador_no_leidas; ?> no leídas
                    </span>
                <?php endif; ?>
                <?php if ($contador_prioritarias > 0): ?>
                    <span style="margin-left: 1rem; color: var(--danger-color);">
                        <i class="zmdi zmdi-alert-circle"></i> <?php echo $contador_prioritarias; ?> prioritarias
                    </span>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button onclick="abrirModal()" class="btn-new">
                    <i class="zmdi zmdi-plus"></i>
                    Nueva Notificación
                </button>
                <?php if ($contador_no_leidas > 0): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="marcar_todas">
                        <button type="submit" class="btn-mark-all">
                            <i class="zmdi zmdi-check-all"></i>
                            Marcar Todas como Leídas
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <!-- Lista de Notificaciones -->
        <section class="notificaciones-container">
            <div class="notificaciones-header">
                <h3>Lista de Notificaciones</h3>
                <span><?php echo $notificaciones->num_rows; ?> notificaciones</span>
            </div>
            
            <?php if ($notificaciones->num_rows > 0): ?>
                <?php while ($notificacion = $notificaciones->fetch_assoc()): ?>
                    <?php 
                    $clases = $notificacion['leida'] ? '' : 'no-leida';
                    if ($notificacion['prioritaria']) {
                        $clases .= ' prioritaria';
                    }
                    ?>
                    <div class="notificacion-item <?php echo $clases; ?>">
                        <div class="notificacion-header">
                            <div>
                                <div class="notificacion-titulo">
                                    <?php echo htmlspecialchars($notificacion['mensaje']); ?>
                                </div>
                                <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap;">
                                    <span class="notificacion-tipo <?php echo getTipoClass($notificacion['tipo']); ?>">
                                        <?php echo htmlspecialchars($notificacion['tipo']); ?>
                                    </span>
                                    <?php if ($notificacion['prioritaria']): ?>
                                        <?php if (isNotificacionVencida($notificacion['fecha_limite'])): ?>
                                            <span class="notificacion-vencida">
                                                <i class="zmdi zmdi-close-circle"></i> VENCIDA
                                            </span>
                                        <?php elseif (isNotificacionVenceHoy($notificacion['fecha_limite'])): ?>
                                            <span class="notificacion-vence-hoy">
                                                <i class="zmdi zmdi-time"></i> VENCE HOY
                                            </span>
                                        <?php else: ?>
                                            <span class="notificacion-prioritaria">
                                                <i class="zmdi zmdi-alert-circle"></i> PRIORITARIA
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($notificacion['seguimiento_descripcion'])): ?>
                            <div class="notificacion-mensaje">
                                <strong>Seguimiento:</strong> <?php echo htmlspecialchars($notificacion['seguimiento_descripcion']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="notificacion-info">
                            <div class="notificacion-fecha">
                                <i class="zmdi zmdi-time"></i>
                                <span>Enviada: <?php echo date('d/m/Y H:i', strtotime($notificacion['fecha_envio'])); ?></span>
                                <?php if (!empty($notificacion['fecha_limite'])): ?>
                                    <span style="margin-left: 1rem; <?php echo isNotificacionVencida($notificacion['fecha_limite']) ? 'color: var(--danger-color); font-weight: bold;' : ''; ?>">
                                        <i class="zmdi zmdi-calendar"></i>
                                        Límite: <?php echo date('d/m/Y', strtotime($notificacion['fecha_limite'])); ?>
                                    </span>
                                <?php elseif (!empty($notificacion['seguimiento_fecha_programada'])): ?>
                                    <span style="margin-left: 1rem;">
                                        <i class="zmdi zmdi-calendar"></i>
                                        Programado: <?php echo date('d/m/Y', strtotime($notificacion['fecha_programada'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="notificacion-acciones">
                                <?php if (!$notificacion['leida']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="marcar_leida">
                                        <input type="hidden" name="id" value="<?php echo $notificacion['id']; ?>">
                                        <button type="submit" class="btn-action btn-leer">
                                            <i class="zmdi zmdi-check"></i>
                                            Marcar como Leída
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="btn-action btn-leida">
                                        <i class="zmdi zmdi-check-circle"></i>
                                        Leída
                                    </span>
                                <?php endif; ?>
                                
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que deseas eliminar esta notificación?')">
                                    <input type="hidden" name="action" value="eliminar">
                                    <input type="hidden" name="id" value="<?php echo $notificacion['id']; ?>">
                                    <button type="submit" class="btn-action btn-delete">
                                        <i class="zmdi zmdi-delete"></i>
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 3rem; text-align: center; color: var(--light-text);">
                    <i class="zmdi zmdi-notifications-off" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h3>No hay notificaciones</h3>
                    <p>No se encontraron notificaciones con los filtros aplicados.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Modal para Nueva Notificación -->
    <div id="modalNotificacion" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Nueva Notificación</h3>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            
            <form method="POST" id="formNotificacion">
                <input type="hidden" name="action" value="crear">
                
                <div class="modal-body">
                    <!-- Tipo de Notificación -->
                    <div class="form-group">
                        <label for="tipo">Tipo de Notificación *</label>
                        <select name="tipo" id="tipo" required>
                            <option value="">Seleccionar tipo</option>
                            <option value="Notificacion">Notificación</option>
                            <option value="Alerta">Alerta</option>
                        </select>
                    </div>

                    <!-- Mensaje -->
                    <div class="form-group">
                        <label for="mensaje">Mensaje *</label>
                        <textarea name="mensaje" id="mensaje" placeholder="Escribe el mensaje de la notificación..." required></textarea>
                    </div>

                    <!-- Fecha Límite -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fecha_limite">Fecha Límite</label>
                            <input type="date" name="fecha_limite" id="fecha_limite">
                        </div>
                        <div class="form-group">
                            <label for="prioritaria">Prioritaria</label>
                            <div class="checkbox-group">
                                <input type="checkbox" name="prioritaria" id="prioritaria" value="1">
                                <label for="prioritaria">Marcar como prioritaria</label>
                            </div>
                        </div>
                    </div>

                    <!-- Usuarios Destino -->
                    <div class="form-group">
                        <label>Usuarios Destino * <span id="contadorUsuarios" style="color: var(--primary-color); font-weight: bold;">(0 seleccionados)</span></label>
                        <div class="usuarios-destino">
                            <?php while ($usuario = $usuarios->fetch_assoc()): ?>
                                <div class="usuario-option">
                                    <input type="checkbox" name="usuarios_destino[]" value="<?php echo $usuario['id']; ?>" id="usuario_<?php echo $usuario['id']; ?>" onchange="actualizarContador()">
                                    <label for="usuario_<?php echo $usuario['id']; ?>">
                                        <?php echo htmlspecialchars($usuario['username']); ?>
                                        <small style="color: var(--light-text);">(<?php echo htmlspecialchars($usuario['email']); ?>)</small>
                                    </label>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Crear Seguimiento -->
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="crear_seguimiento" id="crear_seguimiento" value="1" onchange="toggleSeguimiento()">
                            <label for="crear_seguimiento">Crear seguimiento relacionado</label>
                        </div>
                    </div>

                    <!-- Campos de Seguimiento (inicialmente ocultos) -->
                    <div id="camposSeguimiento" style="display: none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="tipo_entidad">Tipo de Entidad</label>
                                <select name="tipo_entidad" id="tipo_entidad" onchange="cargarEntidades()">
                                    <option value="">Seleccionar tipo</option>
                                    <option value="cliente">Cliente</option>
                                    <option value="empresa">Empresa</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="id_entidad">Entidad</label>
                                <select name="id_entidad" id="id_entidad">
                                    <option value="">Primero selecciona el tipo</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="fecha_programada">Fecha Programada</label>
                                <input type="date" name="fecha_programada" id="fecha_programada">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="descripcion_seguimiento">Descripción del Seguimiento</label>
                            <textarea name="descripcion_seguimiento" id="descripcion_seguimiento" placeholder="Describe la tarea o seguimiento a realizar..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-modal btn-secondary" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="btn-modal btn-primary">Crear Notificación</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Funcionalidad para el sidebar en móviles
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.querySelector('.menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('open');
                });
            }
        });

        // Funciones del modal
        function abrirModal() {
            document.getElementById('modalNotificacion').classList.add('show');
        }

        function cerrarModal() {
            document.getElementById('modalNotificacion').classList.remove('show');
            document.getElementById('formNotificacion').reset();
            document.getElementById('camposSeguimiento').style.display = 'none';
            actualizarContador(); // Resetear contador
        }

        function toggleSeguimiento() {
            const checkbox = document.getElementById('crear_seguimiento');
            const campos = document.getElementById('camposSeguimiento');
            
            if (checkbox.checked) {
                campos.style.display = 'block';
            } else {
                campos.style.display = 'none';
            }
        }

        function actualizarContador() {
            const usuariosSeleccionados = document.querySelectorAll('input[name="usuarios_destino[]"]:checked');
            const contador = document.getElementById('contadorUsuarios');
            contador.textContent = `(${usuariosSeleccionados.length} seleccionados)`;
            
            // Cambiar color según la cantidad
            if (usuariosSeleccionados.length === 0) {
                contador.style.color = 'var(--danger-color)';
            } else {
                contador.style.color = 'var(--primary-color)';
            }
        }

        function cargarEntidades() {
            const tipoEntidad = document.getElementById('tipo_entidad').value;
            const selectEntidad = document.getElementById('id_entidad');
            
            // Limpiar opciones
            selectEntidad.innerHTML = '<option value="">Cargando...</option>';
            
            if (tipoEntidad === '') {
                selectEntidad.innerHTML = '<option value="">Primero selecciona el tipo</option>';
                return;
            }

            // Hacer petición AJAX para cargar entidades
            fetch(`get_entidades.php?tipo=${tipoEntidad}`)
                .then(response => response.json())
                .then(data => {
                    selectEntidad.innerHTML = '<option value="">Seleccionar entidad</option>';
                    data.forEach(entidad => {
                        const option = document.createElement('option');
                        option.value = entidad.id;
                        option.textContent = entidad.nombre + ' - ' + entidad.ciudad;
                        selectEntidad.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    selectEntidad.innerHTML = '<option value="">Error al cargar</option>';
                });
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('modalNotificacion').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });

        // Validación del formulario
        document.getElementById('formNotificacion').addEventListener('submit', function(e) {
            const usuariosSeleccionados = document.querySelectorAll('input[name="usuarios_destino[]"]:checked');
            
            if (usuariosSeleccionados.length === 0) {
                e.preventDefault();
                alert('Debe seleccionar al menos un usuario destino');
                return false;
            }
            
            // Validar campos requeridos
            const tipo = document.getElementById('tipo').value;
            const mensaje = document.getElementById('mensaje').value;
            
            if (!tipo || !mensaje.trim()) {
                e.preventDefault();
                alert('Debe completar todos los campos requeridos');
                return false;
            }
        });
    </script>
</body>
</html>
