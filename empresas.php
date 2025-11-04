<?php
// Incluir archivo de conexión a la base de datos
require_once 'conexion.php';
require_once 'funciones_notificaciones.php';

// Iniciar sesión
session_start();

// Verificar si está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}

// Actualizar notificaciones prioritarias automáticamente
actualizarPrioritarias($conexion);

// Obtener contadores de notificaciones
$contador_no_leidas = getContadorNotificaciones($conexion, $_SESSION['usuario_id']);
$contador_prioritarias = getContadorPrioritarias($conexion, $_SESSION['usuario_id']);

// Función para obtener empresas con filtros
function getEmpresas($conexion, $search = '', $filters = []) {
    $query = "SELECT * FROM empresas WHERE 1=1";
    $params = [];
    $types = '';
    
    // Búsqueda por nombre
    if (!empty($search)) {
        $query .= " AND nombre LIKE ?";
        $params[] = "%$search%";
        $types .= 's';
    }
    
    // Filtros
    if (!empty($filters['estatus'])) {
        $query .= " AND estatus = ?";
        $params[] = $filters['estatus'];
        $types .= 's';
    }
    
    if (!empty($filters['ciudad'])) {
        $query .= " AND ciudad LIKE ?";
        $params[] = "%{$filters['ciudad']}%";
        $types .= 's';
    }
    
    if (!empty($filters['fecha_desde'])) {
        $query .= " AND fecha_registro >= ?";
        $params[] = $filters['fecha_desde'];
        $types .= 's';
    }
    
    if (!empty($filters['fecha_hasta'])) {
        $query .= " AND fecha_registro <= ?";
        $params[] = $filters['fecha_hasta'];
        $types .= 's';
    }
    
    $query .= " ORDER BY fecha_registro DESC";
    
    $stmt = $conexion->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Función para obtener empresa por ID
function getEmpresaById($conexion, $id) {
    $query = "SELECT * FROM empresas WHERE id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Función para insertar empresa
function insertarEmpresa($conexion, $data) {
    $query = "INSERT INTO empresas (nombre, direccion, ciudad, telefono, whatsapp, correo, rfc, notas, estatus, fecha_registro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('sssssssss', 
        $data['nombre'], 
        $data['direccion'], 
        $data['ciudad'], 
        $data['telefono'], 
        $data['whatsapp'], 
        $data['correo'], 
        $data['rfc'], 
        $data['notas'], 
        $data['estatus']
    );
    return $stmt->execute();
}

// Función para actualizar empresa
function actualizarEmpresa($conexion, $id, $data) {
    $query = "UPDATE empresas SET nombre=?, direccion=?, ciudad=?, telefono=?, whatsapp=?, correo=?, rfc=?, notas=?, estatus=? WHERE id=?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('sssssssssi', 
        $data['nombre'], 
        $data['direccion'], 
        $data['ciudad'], 
        $data['telefono'], 
        $data['whatsapp'], 
        $data['correo'], 
        $data['rfc'], 
        $data['notas'], 
        $data['estatus'],
        $id
    );
    return $stmt->execute();
}

// Función para eliminar empresa
function eliminarEmpresa($conexion, $id) {
    $query = "DELETE FROM empresas WHERE id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('i', $id);
    return $stmt->execute();
}

// Función para obtener clase CSS del estatus
function getStatusClass($estatus) {
    $estatus = strtolower(str_replace(' ', '-', $estatus));
    return "status-badge status-{$estatus}";
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $data = [
                    'nombre' => trim($_POST['nombre']),
                    'direccion' => trim($_POST['direccion']),
                    'ciudad' => trim($_POST['ciudad']),
                    'telefono' => trim($_POST['telefono']),
                    'whatsapp' => trim($_POST['whatsapp']),
                    'correo' => trim($_POST['correo']),
                    'rfc' => trim($_POST['rfc']),
                    'notas' => trim($_POST['notas']),
                    'estatus' => trim($_POST['estatus'])
                ];
                
                if (insertarEmpresa($conexion, $data)) {
                    $_SESSION['success'] = 'Empresa registrada exitosamente';
                } else {
                    $_SESSION['error'] = 'Error al registrar la empresa';
                }
                break;
                
            case 'update':
                $id = $_POST['id'];
                $data = [
                    'nombre' => trim($_POST['nombre']),
                    'direccion' => trim($_POST['direccion']),
                    'ciudad' => trim($_POST['ciudad']),
                    'telefono' => trim($_POST['telefono']),
                    'whatsapp' => trim($_POST['whatsapp']),
                    'correo' => trim($_POST['correo']),
                    'rfc' => trim($_POST['rfc']),
                    'notas' => trim($_POST['notas']),
                    'estatus' => trim($_POST['estatus'])
                ];
                
                if (actualizarEmpresa($conexion, $id, $data)) {
                    $_SESSION['success'] = 'Empresa actualizada exitosamente';
                } else {
                    $_SESSION['error'] = 'Error al actualizar la empresa';
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                if (eliminarEmpresa($conexion, $id)) {
                    $_SESSION['success'] = 'Empresa eliminada exitosamente';
                } else {
                    $_SESSION['error'] = 'Error al eliminar la empresa';
                }
                break;
        }
        
        // Redireccionar para evitar reenvío del formulario
        header('Location: empresas.php');
        exit();
    }
}

// Obtener parámetros de búsqueda y filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filters = [
    'estatus' => isset($_GET['estatus']) ? trim($_GET['estatus']) : '',
    'ciudad' => isset($_GET['ciudad']) ? trim($_GET['ciudad']) : '',
    'fecha_desde' => isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '',
    'fecha_hasta' => isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : ''
];

// Obtener empresas
$empresas = getEmpresas($conexion, $search, $filters);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Empresas - Sistema Guiargo</title>
    
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
        /* Estilos modernos para el panel de empresas */
        :root {
            --primary-color: #0b1786;
            --secondary-color: #0b1786;
            --accent-color: #f39c12;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
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

        /* Controles de búsqueda y filtros */
        .search-filters {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .search-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: end;
        }

        .search-input {
            flex: 1;
        }

        .search-input input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .search-input input:focus {
            outline: none;
            border-color: var(--primary-color);
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

        .view-toggle {
            display: flex;
            gap: 0.5rem;
        }

        .btn-view {
            padding: 0.5rem 1rem;
            border: 2px solid var(--primary-color);
            background: transparent;
            color: var(--primary-color);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-view.active {
            background: var(--primary-color);
            color: white;
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

        /* Vista de Cards */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .empresa-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-color);
        }

        .empresa-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--dark-text);
            margin-bottom: 0.5rem;
        }

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

        .card-info {
            margin-bottom: 1rem;
        }

        .card-info-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            color: var(--light-text);
        }

        .card-info-item i {
            margin-right: 0.5rem;
            width: 16px;
            color: var(--primary-color);
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
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

        .btn-edit {
            background: var(--accent-color);
            color: white;
        }

        .btn-edit:hover {
            background: #e67e22;
        }

        .btn-delete {
            background: var(--danger-color);
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        /* Vista de Tabla */
        .table-container {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table-header {
            background: var(--primary-color);
            color: white;
            padding: 1rem 2rem;
            font-weight: bold;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .empresas-table {
            width: 100%;
            border-collapse: collapse;
        }

        .empresas-table th,
        .empresas-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .empresas-table th {
            background: var(--light-bg);
            font-weight: bold;
            color: var(--dark-text);
        }

        .empresas-table tr:hover {
            background: var(--light-bg);
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
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
            background-color: var(--white);
            margin: 5% auto;
            padding: 0;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: bold;
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
            font-weight: bold;
            color: var(--dark-text);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            background: var(--light-bg);
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn {
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
        }

        .btn-secondary {
            background: var(--light-text);
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
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

            .search-row {
                flex-direction: column;
            }

            .filters-row {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .cards-container {
                grid-template-columns: 1fr;
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

        .empresa-card {
            animation: fadeInUp 0.6s ease forwards;
        }

        .empresa-card:nth-child(1) { animation-delay: 0.1s; }
        .empresa-card:nth-child(2) { animation-delay: 0.2s; }
        .empresa-card:nth-child(3) { animation-delay: 0.3s; }
        .empresa-card:nth-child(4) { animation-delay: 0.4s; }

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
                <a href="empresas.php" class="menu-item active">
                    <i class="zmdi zmdi-city-alt"></i>
                    Empresas
                </a>
                <a href="notificaciones.php" class="menu-item">
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
            <h1 class="page-title">Gestión de Empresas</h1>
            <p class="page-subtitle">Administra y gestiona todas las empresas registradas en el sistema</p>
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

        <!-- Búsqueda y Filtros -->
        <section class="search-filters">
            <form method="GET" action="empresas.php">
                <div class="search-row">
                    <div class="search-input">
                        <input type="text" name="search" placeholder="Buscar por nombre de empresa..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="search-actions">
                        <button type="submit" class="btn-search">
                            <i class="zmdi zmdi-search"></i> Buscar
                        </button>
                        <a href="empresas.php" class="btn-clear">
                            <i class="zmdi zmdi-close"></i> Limpiar
                        </a>
                    </div>
                </div>
                
                <div class="filters-row">
                    <div class="filter-group">
                        <label>Estatus</label>
                        <select name="estatus">
                            <option value="">Todos los estatus</option>
                            <option value="Por contactar" <?php echo $filters['estatus'] == 'Por contactar' ? 'selected' : ''; ?>>Por contactar</option>
                            <option value="En seguimiento" <?php echo $filters['estatus'] == 'En seguimiento' ? 'selected' : ''; ?>>En seguimiento</option>
                            <option value="Agendado" <?php echo $filters['estatus'] == 'Agendado' ? 'selected' : ''; ?>>Agendado</option>
                            <option value="Cliente cerrado" <?php echo $filters['estatus'] == 'Cliente cerrado' ? 'selected' : ''; ?>>Cliente cerrado</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Ciudad</label>
                        <input type="text" name="ciudad" placeholder="Filtrar por ciudad..." value="<?php echo htmlspecialchars($filters['ciudad']); ?>">
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
            </form>
        </section>

        <!-- Controles de Vista -->
        <section class="view-controls">
            <div class="view-toggle">
                <button class="btn-view active" onclick="toggleView('cards')">
                    <i class="zmdi zmdi-view-module"></i> Cards
                </button>
                <button class="btn-view" onclick="toggleView('table')">
                    <i class="zmdi zmdi-view-list"></i> Tabla
                </button>
            </div>
            <button class="btn-new" onclick="openModal('create')">
                <i class="zmdi zmdi-plus"></i>
                Nueva Empresa
            </button>
        </section>

        <!-- Vista de Cards -->
        <div id="cards-view" class="cards-container">
            <?php while ($empresa = $empresas->fetch_assoc()): ?>
                <div class="empresa-card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title"><?php echo htmlspecialchars($empresa['nombre']); ?></h3>
                            <span class="<?php echo getStatusClass($empresa['estatus']); ?>">
                                <?php echo htmlspecialchars($empresa['estatus']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-info">
                        <div class="card-info-item">
                            <i class="zmdi zmdi-city"></i>
                            <span><strong>Ciudad:</strong> <?php echo htmlspecialchars($empresa['ciudad']); ?></span>
                        </div>
                        <div class="card-info-item">
                            <i class="zmdi zmdi-pin"></i>
                            <span><?php echo htmlspecialchars($empresa['direccion']); ?></span>
                        </div>
                        <div class="card-info-item">
                            <i class="zmdi zmdi-email"></i>
                            <span><?php echo htmlspecialchars($empresa['correo']); ?></span>
                        </div>
                        <div class="card-info-item">
                            <i class="zmdi zmdi-phone"></i>
                            <span><?php echo htmlspecialchars($empresa['telefono']); ?></span>
                        </div>
                        <?php if (!empty($empresa['whatsapp'])): ?>
                        <div class="card-info-item">
                            <i class="zmdi zmdi-whatsapp"></i>
                            <span><?php echo htmlspecialchars($empresa['whatsapp']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($empresa['rfc'])): ?>
                        <div class="card-info-item">
                            <i class="zmdi zmdi-file-text"></i>
                            <span><strong>RFC:</strong> <?php echo htmlspecialchars($empresa['rfc']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($empresa['notas'])): ?>
                        <div class="card-info-item">
                            <i class="zmdi zmdi-note"></i>
                            <span><strong>Notas:</strong> <?php echo htmlspecialchars($empresa['notas']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-actions">
                        <button class="btn-action btn-edit" onclick="editEmpresa(<?php echo $empresa['id']; ?>)">
                            <i class="zmdi zmdi-edit"></i>
                            Editar
                        </button>
                        <button class="btn-action btn-delete" onclick="deleteEmpresa(<?php echo $empresa['id']; ?>, '<?php echo htmlspecialchars($empresa['nombre']); ?>')">
                            <i class="zmdi zmdi-delete"></i>
                            Eliminar
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Vista de Tabla -->
        <div id="table-view" class="table-container" style="display: none;">
            <div class="table-header">
                <h3>Lista de Empresas</h3>
            </div>
            <div class="table-responsive">
                <table class="empresas-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Ciudad</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>WhatsApp</th>
                            <th>RFC</th>
                            <th>Estatus</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Resetear el resultado para la tabla
                        $empresas = getEmpresas($conexion, $search, $filters);
                        while ($empresa = $empresas->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($empresa['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($empresa['ciudad']); ?></td>
                                <td><?php echo htmlspecialchars($empresa['correo']); ?></td>
                                <td><?php echo htmlspecialchars($empresa['telefono']); ?></td>
                                <td><?php echo htmlspecialchars($empresa['whatsapp']); ?></td>
                                <td><?php echo htmlspecialchars($empresa['rfc']); ?></td>
                                <td>
                                    <span class="<?php echo getStatusClass($empresa['estatus']); ?>">
                                        <?php echo htmlspecialchars($empresa['estatus']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-action btn-edit" onclick="editEmpresa(<?php echo $empresa['id']; ?>)">
                                            <i class="zmdi zmdi-edit"></i>
                                        </button>
                                        <button class="btn-action btn-delete" onclick="deleteEmpresa(<?php echo $empresa['id']; ?>, '<?php echo htmlspecialchars($empresa['nombre']); ?>')">
                                            <i class="zmdi zmdi-delete"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal para Crear/Editar Empresa -->
    <div id="empresaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Nueva Empresa</h2>
            </div>
            <form id="empresaForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="empresaId">
                    
                    <div class="form-group">
                        <label for="nombre">Nombre de la Empresa *</label>
                        <input type="text" id="nombre" name="nombre" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="direccion">Dirección *</label>
                        <input type="text" id="direccion" name="direccion" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="ciudad">Ciudad *</label>
                        <input type="text" id="ciudad" name="ciudad" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="telefono">Teléfono *</label>
                            <input type="tel" id="telefono" name="telefono" required>
                        </div>
                        <div class="form-group">
                            <label for="whatsapp">WhatsApp</label>
                            <input type="tel" id="whatsapp" name="whatsapp">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="correo">Correo Electrónico *</label>
                        <input type="email" id="correo" name="correo" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="rfc">RFC *</label>
                        <input type="text" id="rfc" name="rfc" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="notas">Notas</label>
                        <textarea id="notas" name="notas" rows="3" style="width: 100%; padding: 0.75rem; border: 2px solid #e9ecef; border-radius: var(--border-radius); font-size: 1rem; transition: border-color 0.3s ease; resize: vertical;"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="estatus">Estatus *</label>
                        <select id="estatus" name="estatus" required>
                            <option value="">Seleccionar estatus</option>
                            <option value="Por contactar">Por contactar</option>
                            <option value="En seguimiento">En seguimiento</option>
                            <option value="Agendado">Agendado</option>
                            <option value="Cliente cerrado">Cliente cerrado</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Variables globales
        let currentView = 'cards';

        // Función para cambiar vista
        function toggleView(view) {
            currentView = view;
            const cardsView = document.getElementById('cards-view');
            const tableView = document.getElementById('table-view');
            const buttons = document.querySelectorAll('.btn-view');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            if (view === 'cards') {
                cardsView.style.display = 'grid';
                tableView.style.display = 'none';
            } else {
                cardsView.style.display = 'none';
                tableView.style.display = 'block';
            }
        }

        // Función para abrir modal
        function openModal(action, empresaId = null) {
            const modal = document.getElementById('empresaModal');
            const form = document.getElementById('empresaForm');
            const title = document.getElementById('modalTitle');
            const actionInput = document.getElementById('formAction');
            const idInput = document.getElementById('empresaId');
            
            // Limpiar formulario
            form.reset();
            
            if (action === 'create') {
                title.textContent = 'Nueva Empresa';
                actionInput.value = 'create';
                idInput.value = '';
            } else if (action === 'edit' && empresaId) {
                title.textContent = 'Editar Empresa';
                actionInput.value = 'update';
                idInput.value = empresaId;
                loadEmpresaData(empresaId);
            }
            
            modal.style.display = 'block';
        }

        // Función para cerrar modal
        function closeModal() {
            document.getElementById('empresaModal').style.display = 'none';
        }

        // Función para cargar datos de empresa
        function loadEmpresaData(id) {
            fetch('get_empresa.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('nombre').value = data.empresa.nombre || '';
                        document.getElementById('direccion').value = data.empresa.direccion || '';
                        document.getElementById('ciudad').value = data.empresa.ciudad || '';
                        document.getElementById('telefono').value = data.empresa.telefono || '';
                        document.getElementById('whatsapp').value = data.empresa.whatsapp || '';
                        document.getElementById('correo').value = data.empresa.correo || '';
                        document.getElementById('rfc').value = data.empresa.rfc || '';
                        document.getElementById('notas').value = data.empresa.notas || '';
                        document.getElementById('estatus').value = data.empresa.estatus || '';
                    } else {
                        alert('Error al cargar los datos de la empresa');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los datos de la empresa');
                });
        }

        // Función para editar empresa
        function editEmpresa(id) {
            openModal('edit', id);
        }

        // Función para eliminar empresa
        function deleteEmpresa(id, nombre) {
            if (confirm(`¿Estás seguro de que deseas eliminar la empresa "${nombre}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('empresaModal');
            if (event.target === modal) {
                closeModal();
            }
        }

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
    </script>
</body>
</html>
