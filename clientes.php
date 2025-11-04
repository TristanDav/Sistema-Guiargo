<?php
// Incluir archivo de conexión a la base de datos
require_once 'conexion.php';
require_once 'funciones_notificaciones.php';

// Verificar que la conexión se estableció correctamente
if (!isset($conexion) || $conexion->connect_error) {
    die('Error: No se pudo conectar a la base de datos');
}

// Iniciar sesión
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}

// Actualizar notificaciones prioritarias automáticamente
actualizarPrioritarias($conexion);

// Obtener contadores de notificaciones
$contador_no_leidas = getContadorNotificaciones($conexion, $_SESSION['usuario_id']);
$contador_prioritarias = getContadorPrioritarias($conexion, $_SESSION['usuario_id']);

// Función para obtener clientes con búsqueda y filtros
function getClientes($conexion, $busqueda = '', $filtros = []) {
    $sql = "SELECT * FROM clientes WHERE 1=1";
    $params = [];
    $types = "";
    
    // Búsqueda por nombre
    if (!empty($busqueda)) {
        $sql .= " AND nombre LIKE ?";
        $params[] = "%$busqueda%";
        $types .= "s";
    }
    
    // Filtros
    if (!empty($filtros['estatus'])) {
        $sql .= " AND estatus = ?";
        $params[] = $filtros['estatus'];
        $types .= "s";
    }
    
    if (!empty($filtros['ciudad'])) {
        $sql .= " AND ciudad LIKE ?";
        $params[] = "%{$filtros['ciudad']}%";
        $types .= "s";
    }
    
    if (!empty($filtros['fecha_desde'])) {
        $sql .= " AND DATE(fecha_registro) >= ?";
        $params[] = $filtros['fecha_desde'];
        $types .= "s";
    }
    
    if (!empty($filtros['fecha_hasta'])) {
        $sql .= " AND DATE(fecha_registro) <= ?";
        $params[] = $filtros['fecha_hasta'];
        $types .= "s";
    }
    
    $sql .= " ORDER BY nombre ASC";
    
    $stmt = $conexion->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $clientes = [];
        while ($row = $result->fetch_assoc()) {
            $clientes[] = $row;
        }
        $stmt->close();
        return $clientes;
    }
    return [];
}

// Función para obtener un cliente por ID
function getClienteById($conexion, $id) {
    $stmt = $conexion->prepare("SELECT * FROM clientes WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $cliente = $result->fetch_assoc();
        $stmt->close();
        return $cliente;
    }
    return null;
}

// Función para insertar un nuevo cliente
function insertarCliente($conexion, $datos) {
    $stmt = $conexion->prepare("INSERT INTO clientes (nombre, correo, telefono, whatsapp, direccion, ciudad, estatus, notas, id_usuario_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $estatus = isset($datos['estatus']) ? $datos['estatus'] : 'Por contactar';
        $notas = isset($datos['notas']) ? $datos['notas'] : '';
        $whatsapp = isset($datos['whatsapp']) ? $datos['whatsapp'] : '';
        $id_usuario = $_SESSION['usuario_id'];
        $stmt->bind_param("ssssssssi", $datos['nombre'], $datos['correo'], $datos['telefono'], $whatsapp, $datos['direccion'], $datos['ciudad'], $estatus, $notas, $id_usuario);
        $resultado = $stmt->execute();
        $stmt->close();
        return $resultado;
    }
    return false;
}

// Función para actualizar un cliente
function actualizarCliente($conexion, $id, $datos) {
    $stmt = $conexion->prepare("UPDATE clientes SET nombre = ?, correo = ?, telefono = ?, whatsapp = ?, direccion = ?, ciudad = ?, estatus = ?, notas = ? WHERE id = ?");
    if ($stmt) {
        $estatus = isset($datos['estatus']) ? $datos['estatus'] : 'Por contactar';
        $notas = isset($datos['notas']) ? $datos['notas'] : '';
        $whatsapp = isset($datos['whatsapp']) ? $datos['whatsapp'] : '';
        $stmt->bind_param("ssssssssi", $datos['nombre'], $datos['correo'], $datos['telefono'], $whatsapp, $datos['direccion'], $datos['ciudad'], $estatus, $notas, $id);
        $resultado = $stmt->execute();
        $stmt->close();
        return $resultado;
    }
    return false;
}

// Función para eliminar un cliente
function eliminarCliente($conexion, $id) {
    $stmt = $conexion->prepare("DELETE FROM clientes WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $resultado = $stmt->execute();
        $stmt->close();
        return $resultado;
    }
    return false;
}

// Procesar formularios
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['accion'])) {
        $redirect_url = 'clientes.php';
        
        switch ($_POST['accion']) {
            case 'crear':
                $datos = [
                    'nombre' => trim($_POST['nombre']),
                    'correo' => trim($_POST['correo']),
                    'telefono' => trim($_POST['telefono']),
                    'whatsapp' => isset($_POST['whatsapp']) ? trim($_POST['whatsapp']) : '',
                    'direccion' => trim($_POST['direccion']),
                    'ciudad' => trim($_POST['ciudad']),
                    'estatus' => isset($_POST['estatus']) ? trim($_POST['estatus']) : 'Por contactar',
                    'notas' => isset($_POST['notas']) ? trim($_POST['notas']) : ''
                ];
                
                if (insertarCliente($conexion, $datos)) {
                    $_SESSION['mensaje'] = 'Cliente registrado exitosamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                } else {
                    $_SESSION['mensaje'] = 'Error al registrar el cliente';
                    $_SESSION['tipo_mensaje'] = 'error';
                }
                break;
                
            case 'actualizar':
                $id = (int)$_POST['id'];
                $datos = [
                    'nombre' => trim($_POST['nombre']),
                    'correo' => trim($_POST['correo']),
                    'telefono' => trim($_POST['telefono']),
                    'whatsapp' => isset($_POST['whatsapp']) ? trim($_POST['whatsapp']) : '',
                    'direccion' => trim($_POST['direccion']),
                    'ciudad' => trim($_POST['ciudad']),
                    'estatus' => isset($_POST['estatus']) ? trim($_POST['estatus']) : 'Por contactar',
                    'notas' => isset($_POST['notas']) ? trim($_POST['notas']) : ''
                ];
                
                if (actualizarCliente($conexion, $id, $datos)) {
                    $_SESSION['mensaje'] = 'Cliente actualizado exitosamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                } else {
                    $_SESSION['mensaje'] = 'Error al actualizar el cliente';
                    $_SESSION['tipo_mensaje'] = 'error';
                }
                break;
                
            case 'eliminar':
                $id = (int)$_POST['id'];
                if (eliminarCliente($conexion, $id)) {
                    $_SESSION['mensaje'] = 'Cliente eliminado exitosamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                } else {
                    $_SESSION['mensaje'] = 'Error al eliminar el cliente';
                    $_SESSION['tipo_mensaje'] = 'error';
                }
                break;
        }
        
        // Redirigir para evitar reenvío de formulario
        header('Location: ' . $redirect_url);
        exit();
    }
}

// Obtener mensajes de la sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipo_mensaje = $_SESSION['tipo_mensaje'];
    unset($_SESSION['mensaje']);
    unset($_SESSION['tipo_mensaje']);
}

// Obtener parámetros de búsqueda y filtros
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$filtros = [
    'estatus' => isset($_GET['estatus']) ? trim($_GET['estatus']) : '',
    'ciudad' => isset($_GET['ciudad']) ? trim($_GET['ciudad']) : '',
    'fecha_desde' => isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '',
    'fecha_hasta' => isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : ''
];

// Obtener clientes con filtros aplicados
$clientes = getClientes($conexion, $busqueda, $filtros);

// Obtener cliente para editar si se especifica
$cliente_editar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $cliente_editar = getClienteById($conexion, $_GET['editar']);
}

// Función para obtener la clase CSS del estatus
function getStatusClass($estatus) {
    $estatus = strtolower(str_replace(' ', '-', $estatus));
    return "status-badge status-{$estatus}";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - Sistema Guiargo</title>
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/sweetalert2.css">
    <link rel="stylesheet" href="css/material.min.css">
    <link rel="stylesheet" href="css/material-design-iconic-font.min.css">
    <link rel="stylesheet" href="css/jquery.mCustomScrollbar.css">
    <link rel="stylesheet" href="css/main.css">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
    <script>window.jQuery || document.write('<script src="js/jquery-1.11.2.min.js"><\/script>')</script>
    <script src="js/material.min.js"></script>
    <script src="js/sweetalert2.min.js"></script>
    <script src="js/jquery.mCustomScrollbar.concat.min.js"></script>
    <script src="js/main.js"></script>
    <style>
        :root {
            --primary-color: #0b1786;
            --secondary-color: #0b1786;
            --accent-color: #f39c12;
            --text-color: #333;
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --border-color: #e9ecef;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #1a237e 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-logo i {
            font-size: 2rem;
            color: var(--accent-color);
        }

        .sidebar-logo h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            display: block;
            padding: 0.75rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background-color: rgba(255,255,255,0.1);
            border-left-color: var(--accent-color);
            color: white;
        }

        .nav-item.active {
            background-color: rgba(255,255,255,0.15);
            border-left-color: var(--accent-color);
        }

        .nav-item i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            background-color: var(--bg-color);
        }

        /* Top Navbar */
        .top-navbar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .page-title h1 {
            margin: 0;
            color: var(--text-color);
            font-size: 1.5rem;
            font-weight: 600;
        }

        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .view-toggle {
            display: flex;
            background: var(--bg-color);
            border-radius: 25px;
            padding: 0.25rem;
        }

        .view-btn {
            padding: 0.5rem 1rem;
            border: none;
            background: transparent;
            color: var(--text-color);
            cursor: pointer;
            border-radius: 20px;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .view-btn.active {
            background: var(--primary-color);
            color: white;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-color);
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .logout-btn {
            color: var(--danger-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: var(--danger-color);
            color: white;
        }

        /* Content Area */
        .content {
            padding: 2rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #0a1468;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        /* Cards View */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .client-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .client-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .client-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-color);
            margin: 0;
        }

        .client-email {
            color: var(--primary-color);
            font-size: 0.875rem;
            margin: 0.25rem 0;
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        .card-info {
            display: grid;
            gap: 0.75rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-color);
            font-size: 0.875rem;
        }

        .info-item i {
            color: var(--primary-color);
            width: 16px;
        }

        /* Table View */
        .table-container {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: var(--primary-color);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .table tr:hover {
            background: var(--bg-color);
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
        }

        .close {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
        }

        .close:hover {
            opacity: 0.7;
        }

        .modal-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(11, 23, 134, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
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

            .content {
                padding: 1rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .cards-container {
                grid-template-columns: 1fr;
            }

            .table-container {
                overflow-x: auto;
            }
        }

        /* Search and Filters */
        .search-filters-container {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
        }

        .search-filters-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .search-section {
            display: flex;
            align-items: center;
        }

        .search-input-group {
            position: relative;
            display: flex;
            align-items: center;
            flex: 1;
            max-width: 500px;
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            color: var(--primary-color);
            font-size: 1.25rem;
            z-index: 2;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid var(--border-color);
            border-radius: 25px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            background: var(--bg-color);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(11, 23, 134, 0.1);
        }

        .search-btn {
            position: absolute;
            right: 0.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 2;
        }

        .search-btn:hover {
            background: #0a1468;
            transform: scale(1.05);
        }

        .filters-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 500;
            color: var(--text-color);
            font-size: 0.875rem;
        }

        .filter-select,
        .filter-input {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: border-color 0.3s ease;
            background: white;
        }

        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(11, 23, 134, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 0.75rem;
            align-items: end;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }

        /* Results counter */
        .results-info {
            background: var(--bg-color);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: var(--text-color);
            border-left: 4px solid var(--primary-color);
        }

        .results-info i {
            color: var(--primary-color);
            margin-right: 0.5rem;
        }

        /* Responsive filters */
        @media (max-width: 768px) {
            .search-filters-container {
                padding: 1rem;
            }

            .filters-section {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input-group {
                max-width: 100%;
            }
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid transparent;
        }

        .status-por-contactar {
            background: #e3f2fd;
            color: #1976d2;
            border-color: #bbdefb;
        }

        .status-en-seguimiento {
            background: #fff3e0;
            color: #f57c00;
            border-color: #ffcc02;
        }

        .status-agendado {
            background: #e8f5e8;
            color: #388e3c;
            border-color: #c8e6c9;
        }

        .status-cliente-cerrado {
            background: #ffebee;
            color: #d32f2f;
            border-color: #ffcdd2;
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

        /* Hidden classes */
        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="zmdi zmdi-account-circle"></i>
                    <h2>Clientes</h2>
                </div>
            </div>
            <div class="sidebar-nav">
                <a href="home_guiargo.php" class="nav-item">
                    <i class="zmdi zmdi-home"></i>
                    Dashboard
                </a>
                <a href="clientes.php" class="nav-item active">
                    <i class="zmdi zmdi-account-circle"></i>
                    Clientes
                </a>
                <a href="empresas.php" class="nav-item">
                    <i class="zmdi zmdi-city-alt"></i>
                    Empresas
                </a>
                <a href="usuarios.php" class="nav-item">
                    <i class="zmdi zmdi-account-circle"></i>
                    Usuarios
                </a>
                <a href="notificaciones.php" class="nav-item">
                    <i class="zmdi zmdi-notifications"></i>
                    Notificaciones
                </a>
            </div>
        </nav>

        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navbar -->
            <nav class="top-navbar">
                <div class="page-title">
                    <i class="zmdi zmdi-account-circle"></i>
                    <h1>Gestión de Clientes</h1>
                </div>
                <div class="navbar-actions">
                    <div class="view-toggle">
                        <button class="view-btn active" onclick="toggleView('cards')">
                            <i class="zmdi zmdi-view-module"></i> Cards
                        </button>
                        <button class="view-btn" onclick="toggleView('table')">
                            <i class="zmdi zmdi-view-list"></i> Tabla
                        </button>
                    </div>
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
                        <i class="zmdi zmdi-power"></i> Cerrar Sesión
                    </a>
                </div>
            </nav>

            <!-- Content -->
            <div class="content">
                <?php if (!empty($mensaje)): ?>
                    <div class="alert <?php echo $tipo_mensaje; ?>">
                        <i class="zmdi zmdi-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : 'alert-circle'; ?>"></i>
                        <?php echo $mensaje; ?>
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="openModal('crear')">
                        <i class="zmdi zmdi-plus"></i>
                        Nuevo Cliente
                    </button>
                    <button class="btn btn-danger" onclick="eliminarSeleccionados()">
                        <i class="zmdi zmdi-delete"></i>
                        Eliminar Seleccionados
                    </button>
                </div>

                <!-- Barra de Búsqueda y Filtros -->
                <div class="search-filters-container">
                    <form method="GET" class="search-filters-form">
                        <!-- Barra de Búsqueda -->
                        <div class="search-section">
                            <div class="search-input-group">
                                <i class="zmdi zmdi-search search-icon"></i>
                                <input type="text" 
                                       class="search-input" 
                                       name="busqueda" 
                                       placeholder="Buscar por nombre..." 
                                       value="<?php echo htmlspecialchars($busqueda); ?>">
                                <button type="submit" class="search-btn">
                                    <i class="zmdi zmdi-search"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Filtros -->
                        <div class="filters-section">
                            <div class="filter-group">
                                <label for="estatus">Estatus:</label>
                                <select name="estatus" id="estatus" class="filter-select">
                                    <option value="">Todos los estatus</option>
                                    <option value="Por contactar" <?php echo $filtros['estatus'] == 'Por contactar' ? 'selected' : ''; ?>>Por contactar</option>
                                    <option value="En seguimiento" <?php echo $filtros['estatus'] == 'En seguimiento' ? 'selected' : ''; ?>>En seguimiento</option>
                                    <option value="Agendado" <?php echo $filtros['estatus'] == 'Agendado' ? 'selected' : ''; ?>>Agendado</option>
                                    <option value="Cliente cerrado" <?php echo $filtros['estatus'] == 'Cliente cerrado' ? 'selected' : ''; ?>>Cliente cerrado</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="ciudad">Ciudad:</label>
                                <input type="text" 
                                       name="ciudad" 
                                       id="ciudad" 
                                       class="filter-input" 
                                       placeholder="Filtrar por ciudad..." 
                                       value="<?php echo htmlspecialchars($filtros['ciudad']); ?>">
                            </div>

                            <div class="filter-group">
                                <label for="fecha_desde">Desde:</label>
                                <input type="date" 
                                       name="fecha_desde" 
                                       id="fecha_desde" 
                                       class="filter-input" 
                                       value="<?php echo htmlspecialchars($filtros['fecha_desde']); ?>">
                            </div>

                            <div class="filter-group">
                                <label for="fecha_hasta">Hasta:</label>
                                <input type="date" 
                                       name="fecha_hasta" 
                                       id="fecha_hasta" 
                                       class="filter-input" 
                                       value="<?php echo htmlspecialchars($filtros['fecha_hasta']); ?>">
                            </div>

                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="zmdi zmdi-search"></i> Filtrar
                                </button>
                                <a href="clientes.php" class="btn btn-secondary btn-sm">
                                    <i class="zmdi zmdi-refresh"></i> Limpiar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Información de Resultados -->
                <?php if (!empty($busqueda) || !empty(array_filter($filtros))): ?>
                    <div class="results-info">
                        <i class="zmdi zmdi-info"></i>
                        <strong>Resultados encontrados:</strong> <?php echo count($clientes); ?> cliente(s)
                        <?php if (!empty($busqueda)): ?>
                            para "<?php echo htmlspecialchars($busqueda); ?>"
                        <?php endif; ?>
                        <?php if (!empty($filtros['estatus'])): ?>
                            con estatus "<?php echo htmlspecialchars($filtros['estatus']); ?>"
                        <?php endif; ?>
                        <?php if (!empty($filtros['ciudad'])): ?>
                            en ciudad "<?php echo htmlspecialchars($filtros['ciudad']); ?>"
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Cards View -->
                <div id="cards-view" class="cards-container">
                    <?php if (empty($clientes)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-color);">
                            <?php if (!empty($busqueda) || !empty(array_filter($filtros))): ?>
                                <i class="zmdi zmdi-search" style="font-size: 4rem; color: var(--border-color); margin-bottom: 1rem;"></i>
                                <h3>No se encontraron resultados</h3>
                                <p>Intenta con otros términos de búsqueda o filtros</p>
                                <a href="clientes.php" class="btn btn-primary" style="margin-top: 1rem;">
                                    <i class="zmdi zmdi-refresh"></i> Ver todos los clientes
                                </a>
                            <?php else: ?>
                                <i class="zmdi zmdi-account-circle" style="font-size: 4rem; color: var(--border-color); margin-bottom: 1rem;"></i>
                                <h3>No hay clientes registrados</h3>
                                <p>Comienza agregando tu primer cliente</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <div class="client-card">
                                <div class="card-header">
                                    <div>
                                        <h3 class="client-name"><?php echo htmlspecialchars($cliente['nombre']); ?></h3>
                                        <p class="client-email"><?php echo htmlspecialchars($cliente['correo']); ?></p>
                                    </div>
                                    <div class="card-actions">
                                        <button class="btn btn-sm btn-success" onclick="editarCliente(<?php echo $cliente['id']; ?>)">
                                            <i class="zmdi zmdi-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="eliminarCliente(<?php echo $cliente['id']; ?>)">
                                            <i class="zmdi zmdi-delete"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-info">
                                    <div class="info-item">
                                        <i class="zmdi zmdi-phone"></i>
                                        <span><?php echo htmlspecialchars($cliente['telefono']); ?></span>
                                    </div>
                                    <?php if (!empty($cliente['whatsapp'])): ?>
                                    <div class="info-item">
                                        <i class="zmdi zmdi-whatsapp" style="color: #25D366;"></i>
                                        <span><?php echo htmlspecialchars($cliente['whatsapp']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="info-item">
                                        <i class="zmdi zmdi-pin"></i>
                                        <span><?php echo htmlspecialchars($cliente['direccion']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="zmdi zmdi-city"></i>
                                        <span><?php echo htmlspecialchars($cliente['ciudad']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="zmdi zmdi-flag"></i>
                                        <span class="<?php echo getStatusClass($cliente['estatus']); ?>">
                                            <?php echo htmlspecialchars($cliente['estatus']); ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <i class="zmdi zmdi-calendar"></i>
                                        <span>Registrado: <?php echo date('d/m/Y', strtotime($cliente['fecha_registro'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Table View -->
                <div id="table-view" class="table-container hidden">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Correo</th>
                                <th>Teléfono</th>
                                <th>WhatsApp</th>
                                <th>Ciudad</th>
                                <th>Estatus</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clientes)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 3rem; color: var(--text-color);">
                                        <?php if (!empty($busqueda) || !empty(array_filter($filtros))): ?>
                                            <i class="zmdi zmdi-search" style="font-size: 3rem; color: var(--border-color); margin-bottom: 1rem; display: block;"></i>
                                            No se encontraron resultados
                                        <?php else: ?>
                                            <i class="zmdi zmdi-account-circle" style="font-size: 3rem; color: var(--border-color); margin-bottom: 1rem; display: block;"></i>
                                            No hay clientes registrados
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clientes as $cliente): ?>
                                    <tr>
                                        <td><?php echo $cliente['id']; ?></td>
                                        <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['correo']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['telefono']); ?></td>
                                        <td>
                                            <?php if (!empty($cliente['whatsapp'])): ?>
                                                <i class="zmdi zmdi-whatsapp" style="color: #25D366; margin-right: 5px;"></i>
                                                <?php echo htmlspecialchars($cliente['whatsapp']); ?>
                                            <?php else: ?>
                                                <span style="color: #999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($cliente['ciudad']); ?></td>
                                        <td>
                                            <span class="<?php echo getStatusClass($cliente['estatus']); ?>">
                                                <?php echo htmlspecialchars($cliente['estatus']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($cliente['fecha_registro'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-success" onclick="editarCliente(<?php echo $cliente['id']; ?>)">
                                                <i class="zmdi zmdi-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="eliminarCliente(<?php echo $cliente['id']; ?>)">
                                                <i class="zmdi zmdi-delete"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal para Crear/Editar Cliente -->
    <div id="clienteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Nuevo Cliente</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <form id="clienteForm" method="POST">
                <input type="hidden" name="accion" id="accion" value="crear">
                <input type="hidden" name="id" id="clienteId" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nombre">Nombre Completo *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="correo">Correo *</label>
                            <input type="email" class="form-control" id="correo" name="correo" required>
                        </div>
                        <div class="form-group">
                            <label for="telefono">Teléfono *</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="whatsapp">WhatsApp</label>
                        <input type="tel" class="form-control" id="whatsapp" name="whatsapp" placeholder="Número de WhatsApp (opcional)">
                    </div>
                    <div class="form-group">
                        <label for="direccion">Dirección *</label>
                        <input type="text" class="form-control" id="direccion" name="direccion" required>
                    </div>
                    <div class="form-group">
                        <label for="ciudad">Ciudad *</label>
                        <input type="text" class="form-control" id="ciudad" name="ciudad" required>
                    </div>
                    <div class="form-group">
                        <label for="estatus">Estatus *</label>
                        <select class="form-control" id="estatus" name="estatus" required>
                            <option value="Por contactar">Por contactar</option>
                            <option value="En seguimiento">En seguimiento</option>
                            <option value="Agendado">Agendado</option>
                            <option value="Cliente cerrado">Cliente cerrado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notas">Notas</label>
                        <textarea class="form-control" id="notas" name="notas" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="zmdi zmdi-check"></i>
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle entre vista de cards y tabla
        function toggleView(view) {
            const cardsView = document.getElementById('cards-view');
            const tableView = document.getElementById('table-view');
            const viewBtns = document.querySelectorAll('.view-btn');
            
            viewBtns.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            if (view === 'cards') {
                cardsView.classList.remove('hidden');
                tableView.classList.add('hidden');
            } else {
                cardsView.classList.add('hidden');
                tableView.classList.remove('hidden');
            }
        }

        // Abrir modal para crear cliente
        function openModal(accion) {
            const modal = document.getElementById('clienteModal');
            const form = document.getElementById('clienteForm');
            const title = document.getElementById('modalTitle');
            const accionInput = document.getElementById('accion');
            
            form.reset();
            accionInput.value = accion;
            
            if (accion === 'crear') {
                title.textContent = 'Nuevo Cliente';
            } else {
                title.textContent = 'Editar Cliente';
            }
            
            modal.style.display = 'block';
        }

        // Cerrar modal
        function closeModal() {
            document.getElementById('clienteModal').style.display = 'none';
        }

        // Editar cliente
        function editarCliente(id) {
            // Aquí podrías hacer una petición AJAX para obtener los datos del cliente
            // Por simplicidad, redirigimos a la misma página con parámetro
            window.location.href = '?editar=' + id;
        }

        // Eliminar cliente
        function eliminarCliente(id) {
            if (confirm('¿Estás seguro de que deseas eliminar este cliente?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Eliminar clientes seleccionados
        function eliminarSeleccionados() {
            // Implementar lógica para eliminar múltiples clientes
            alert('Funcionalidad de eliminación múltiple en desarrollo');
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('clienteModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Cargar datos del cliente para editar
        <?php if ($cliente_editar): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const cliente = <?php echo json_encode($cliente_editar); ?>;
            document.getElementById('nombre').value = cliente.nombre;
            document.getElementById('correo').value = cliente.correo;
            document.getElementById('telefono').value = cliente.telefono;
            document.getElementById('whatsapp').value = cliente.whatsapp || '';
            document.getElementById('direccion').value = cliente.direccion;
            document.getElementById('ciudad').value = cliente.ciudad;
            document.getElementById('estatus').value = cliente.estatus;
            document.getElementById('notas').value = cliente.notas || '';
            document.getElementById('accion').value = 'actualizar';
            document.getElementById('clienteId').value = cliente.id;
            document.getElementById('modalTitle').textContent = 'Editar Cliente';
            document.getElementById('clienteModal').style.display = 'block';
        });
        <?php endif; ?>
    </script>
</body>
</html>
