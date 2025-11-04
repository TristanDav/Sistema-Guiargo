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

// Función para obtener notificaciones con fechas límite
function getNotificacionesConFechas($conexion, $usuario_id) {
    $query = "SELECT id, mensaje, fecha_limite, prioritaria, leida 
              FROM notificaciones 
              WHERE id_usuario_destino = ? 
              AND fecha_limite IS NOT NULL 
              ORDER BY fecha_limite ASC";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notificaciones = [];
    while ($row = $result->fetch_assoc()) {
        $notificaciones[] = $row;
    }
    $stmt->close();
    
    return $notificaciones;
}

// Función para obtener seguimientos con fechas programadas
function getSeguimientosConFechas($conexion, $usuario_id) {
    $query = "SELECT s.id, s.descripcion, s.fecha_programada, s.estatus, s.tipo_entidad, s.id_entidad,
                     CASE 
                         WHEN s.tipo_entidad = 'cliente' THEN c.nombre 
                         WHEN s.tipo_entidad = 'empresa' THEN e.nombre 
                     END as nombre_entidad
              FROM seguimientos s
              LEFT JOIN clientes c ON s.tipo_entidad = 'cliente' AND s.id_entidad = c.id
              LEFT JOIN empresas e ON s.tipo_entidad = 'empresa' AND s.id_entidad = e.id
              WHERE s.id_usuario_asignado = ? 
              AND s.fecha_programada IS NOT NULL 
              ORDER BY s.fecha_programada ASC";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $seguimientos = [];
    while ($row = $result->fetch_assoc()) {
        $seguimientos[] = $row;
    }
    $stmt->close();
    
    return $seguimientos;
}

// Obtener notificaciones para el calendario
$notificaciones_calendario = getNotificacionesConFechas($conexion, $_SESSION['usuario_id']);

// Obtener seguimientos para el calendario
$seguimientos_calendario = getSeguimientosConFechas($conexion, $_SESSION['usuario_id']);

// Debug temporal - remover después
// echo "<!-- Debug: contador_prioritarias = " . $contador_prioritarias . " -->";

// Obtener estadísticas de la base de datos
$stats = [
    'usuarios' => 0,
    'clientes' => 0,
    'empresas' => 0,
    'productos' => 0,
    'notificaciones' => 0,
    'categorias' => 0
];

// Función para obtener conteo de manera segura
function getCount($conexion, $table, $where = '') {
    $query = "SELECT COUNT(*) as total FROM $table";
    if ($where) {
        $query .= " WHERE $where";
    }
    
    $result = $conexion->query($query);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    return 0;
}

try {
    // Verificar que la conexión esté disponible
    if ($conexion && !$conexion->connect_error) {
        // Contar usuarios
        $stats['usuarios'] = getCount($conexion, 'usuarios', 'activo = 1');
        
        // Contar clientes
        $stats['clientes'] = getCount($conexion, 'clientes');
        
        // Contar empresas
        $stats['empresas'] = getCount($conexion, 'empresas');
        
        // Contar productos
        $stats['productos'] = getCount($conexion, 'productos');
        
        // Contar notificaciones
        $stats['notificaciones'] = getCount($conexion, 'notificaciones');
        
        // Contar categorías
        $stats['categorias'] = getCount($conexion, 'categorias');
    }
} catch (Exception $e) {
    // En caso de error, mantener valores por defecto (0)
    error_log("Error obteniendo estadísticas: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo - Sistema Guiargo</title>
    
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
        /* Estilos modernos para el panel administrativo */
        :root {
            --primary-color: #0b1786;
            --secondary-color: #0b1786;
            --accent-color: #f39c12;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #d32f2f;
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

        .welcome-section {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .welcome-title {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            color: var(--light-text);
            font-size: 1.1rem;
        }

        /* Dashboard Cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .dashboard-card {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--dark-text);
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .card-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .card-description {
            color: var(--light-text);
            font-size: 0.9rem;
        }

        /* Quick Actions */
        .quick-actions {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .quick-actions h3 {
            margin-bottom: 1.5rem;
            color: var(--primary-color);
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--light-bg);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--dark-text);
        }

        .action-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .action-btn i {
            font-size: 1.5rem;
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

            .dashboard-grid {
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

        .dashboard-card {
            animation: fadeInUp 0.6s ease forwards;
        }

        .dashboard-card:nth-child(1) { animation-delay: 0.1s; }
        .dashboard-card:nth-child(2) { animation-delay: 0.2s; }
        .dashboard-card:nth-child(3) { animation-delay: 0.3s; }
        .dashboard-card:nth-child(4) { animation-delay: 0.4s; }
        .dashboard-card:nth-child(5) { animation-delay: 0.5s; }
        .dashboard-card:nth-child(6) { animation-delay: 0.6s; }

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

        /* Calendario de Notificaciones */
        .calendar-section {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-top: 2rem;
        }

        .calendar-section h3 {
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .calendar {
            background: var(--white);
            border: 1px solid #e0e0e0;
            border-radius: var(--border-radius);
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
        }

        .calendar-header {
            background: var(--primary-color);
            color: white;
            padding: 1rem;
            text-align: center;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .calendar-nav {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .calendar-nav:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: #e0e0e0;
        }

        #calendar-days {
            display: contents;
        }

        .calendar-day-header {
            background: var(--light-bg);
            padding: 0.75rem 0.5rem;
            text-align: center;
            font-weight: bold;
            font-size: 0.9rem;
            color: var(--dark-text);
        }

        .calendar-day {
            background: var(--white);
            padding: 0.75rem 0.5rem;
            min-height: 60px;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .calendar-day:hover {
            background: var(--light-bg);
        }

        .calendar-day.other-month {
            color: #ccc;
            background: #f9f9f9;
        }

        .calendar-day.has-notification {
            background: #fff3cd;
            border-left: 3px solid var(--warning-color);
        }

        .calendar-day.has-priority {
            background: #f8d7da;
            border-left: 3px solid var(--danger-color);
        }

        .calendar-day.today {
            background: var(--primary-color);
            color: white;
        }

        .calendar-day.today.has-notification {
            background: var(--accent-color);
        }

        .notification-dot {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--warning-color);
        }

        .notification-dot.priority {
            background: var(--danger-color);
        }

        .calendar-day.has-seguimiento {
            background: #e8f5e9;
            border-left: 3px solid var(--success-color);
        }

        .calendar-day.today.has-seguimiento {
            background: var(--success-color);
            color: white;
        }

        .notification-dot.seguimiento-dot {
            top: 5px;
            right: 18px;
            background: var(--success-color);
        }

        .notification-item.seguimiento-item {
            border-left-color: var(--success-color);
        }

        .notifications-list {
            background: var(--light-bg);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-top: 1rem;
        }

        .notifications-list h4 {
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .notification-item {
            background: var(--white);
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--warning-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .notification-item.priority {
            border-left-color: var(--danger-color);
        }

        .notification-item.overdue {
            border-left-color: var(--danger-color);
            background: #fff5f5;
        }

        .notification-date {
            font-size: 0.85rem;
            color: var(--light-text);
            margin-bottom: 0.5rem;
        }

        .notification-message {
            font-size: 0.9rem;
            color: var(--dark-text);
            line-height: 1.4;
        }

        .notification-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-top: 0.5rem;
        }

        .status-priority {
            background: #f8d7da;
            color: #721c24;
        }

        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }

        .status-normal {
            background: #d1ecf1;
            color: #0c5460;
        }

        @media (max-width: 768px) {
            .calendar-section {
                padding: 1rem;
            }
            
            .calendar-day {
                min-height: 50px;
                padding: 0.5rem 0.25rem;
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
                <a href="notificaciones.php" class="menu-item">
                    <i class="zmdi zmdi-notifications"></i>
                    Notificaciones
                </a>
                <a href="seguimientos.php" class="menu-item">
                    <i class="zmdi zmdi-calendar-check"></i>
                    Seguimientos
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
        <!-- Sección de Bienvenida -->
        <section class="welcome-section">
            <h1 class="welcome-title">¡Bienvenido al Sistema Guiargo!</h1>
            <p class="welcome-subtitle">Panel de administración moderno e intuitivo para gestionar tu negocio</p>
        </section>

        <!-- Dashboard Cards -->
        <section class="dashboard-grid">
            <div class="dashboard-card" onclick="window.location.href='usuarios.php'">
                <div class="card-header">
                    <h3 class="card-title">Usuarios</h3>
                    <div class="card-icon" style="background: var(--primary-color);">
                        <i class="zmdi zmdi-account-circle"></i>
                    </div>
                </div>
                <div class="card-number"><?php echo $stats['usuarios']; ?></div>
                <p class="card-description">Usuarios activos en el sistema</p>
            </div>

            <div class="dashboard-card" onclick="window.location.href='clientes.php'">
                <div class="card-header">
                    <h3 class="card-title">Clientes</h3>
                    <div class="card-icon" style="background: var(--success-color);">
                        <i class="zmdi zmdi-accounts"></i>
                    </div>
                </div>
                <div class="card-number"><?php echo $stats['clientes']; ?></div>
                <p class="card-description">Clientes registrados</p>
            </div>

            <div class="dashboard-card" onclick="window.location.href='empresas.php'">
                <div class="card-header">
                    <h3 class="card-title">Empresas</h3>
                    <div class="card-icon" style="background: var(--accent-color);">
                        <i class="zmdi zmdi-city-alt"></i>
                    </div>
                </div>
                <div class="card-number"><?php echo $stats['empresas']; ?></div>
                <p class="card-description">Empresas registradas</p>
            </div>

            <div class="dashboard-card" onclick="window.location.href='usuarios.php'">
                <div class="card-header">
                    <h3 class="card-title">Gestión de Usuarios</h3>
                    <div class="card-icon" style="background: var(--accent-color);">
                        <i class="zmdi zmdi-account-add"></i>
                    </div>
                </div>
                <div class="card-number"><?php echo $stats['usuarios']; ?></div>
                <p class="card-description">Registrar y gestionar usuarios</p>
            </div>

            <div class="dashboard-card" onclick="window.location.href='notificaciones.php'">
                <div class="card-header">
                    <h3 class="card-title">Notificaciones</h3>
                    <div class="card-icon" style="background: var(--accent-color);">
                        <i class="zmdi zmdi-notifications"></i>
                    </div>
                </div>
                <div class="card-number"><?php echo $stats['notificaciones']; ?></div>
                <p class="card-description">Notificaciones activas</p>
            </div>
        </section>

        <!-- Calendario de Notificaciones -->
        <section class="calendar-section">
            <h3>
                <i class="zmdi zmdi-calendar"></i>
                Calendario de Notificaciones
            </h3>
            <div class="calendar">
                <div class="calendar-header">
                    <button class="calendar-nav" onclick="changeMonth(-1)">‹</button>
                    <span id="current-month-year"></span>
                    <button class="calendar-nav" onclick="changeMonth(1)">›</button>
                </div>
                <div class="calendar-grid">
                    <div class="calendar-day-header">Lun</div>
                    <div class="calendar-day-header">Mar</div>
                    <div class="calendar-day-header">Mié</div>
                    <div class="calendar-day-header">Jue</div>
                    <div class="calendar-day-header">Vie</div>
                    <div class="calendar-day-header">Sáb</div>
                    <div class="calendar-day-header">Dom</div>
                    <div id="calendar-days"></div>
                </div>
            </div>
            <div class="notifications-list">
                <h4>Próximas Notificaciones y Seguimientos</h4>
                <div id="notifications-list">
                    <?php 
                    // Combinar notificaciones y seguimientos
                    $eventos_calendario = [];
                    
                    // Agregar notificaciones
                    foreach ($notificaciones_calendario as $notif) {
                        $eventos_calendario[] = [
                            'tipo' => 'notificacion',
                            'fecha' => $notif['fecha_limite'],
                            'mensaje' => $notif['mensaje'],
                            'prioritaria' => $notif['prioritaria'],
                            'leida' => $notif['leida']
                        ];
                    }
                    
                    // Agregar seguimientos
                    foreach ($seguimientos_calendario as $seg) {
                        $eventos_calendario[] = [
                            'tipo' => 'seguimiento',
                            'fecha' => $seg['fecha_programada'],
                            'mensaje' => $seg['nombre_entidad'] . ': ' . $seg['descripcion'],
                            'estatus' => $seg['estatus'],
                            'tipo_entidad' => $seg['tipo_entidad']
                        ];
                    }
                    
                    // Ordenar por fecha
                    usort($eventos_calendario, function($a, $b) {
                        return strtotime($a['fecha']) - strtotime($b['fecha']);
                    });
                    ?>
                    <?php if (empty($eventos_calendario)): ?>
                        <p style="color: var(--light-text); text-align: center; padding: 2rem;">
                            No hay notificaciones o seguimientos programados
                        </p>
                    <?php else: ?>
                        <?php foreach ($eventos_calendario as $evento): ?>
                            <?php
                            $fecha_evento = new DateTime($evento['fecha']);
                            $hoy = new DateTime();
                            $diferencia = $hoy->diff($fecha_evento);
                            $es_vencida = $fecha_evento < $hoy;
                            $es_hoy = $fecha_evento->format('Y-m-d') === $hoy->format('Y-m-d');
                            ?>
                            <?php if ($evento['tipo'] == 'notificacion'): ?>
                                <?php
                                $notif = null;
                                foreach ($notificaciones_calendario as $n) {
                                    if ($n['fecha_limite'] == $evento['fecha'] && $n['mensaje'] == $evento['mensaje']) {
                                        $notif = $n;
                                        break;
                                    }
                                }
                                ?>
                                <div class="notification-item <?php echo $notif && $notif['prioritaria'] ? 'priority' : ''; ?> <?php echo $es_vencida ? 'overdue' : ''; ?>">
                                    <div class="notification-date">
                                        <i class="zmdi zmdi-calendar"></i>
                                        <?php echo $fecha_evento->format('d/m/Y'); ?>
                                        <?php if ($es_hoy): ?>
                                            <span class="notification-status status-priority">HOY</span>
                                        <?php elseif ($es_vencida): ?>
                                            <span class="notification-status status-overdue">VENCIDA</span>
                                        <?php elseif ($notif && $notif['prioritaria']): ?>
                                            <span class="notification-status status-priority">PRIORITARIA</span>
                                        <?php else: ?>
                                            <span class="notification-status status-normal">NORMAL</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notification-message">
                                        <strong>📢 Notificación:</strong> <?php echo htmlspecialchars($evento['mensaje']); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php
                                $seg = null;
                                foreach ($seguimientos_calendario as $s) {
                                    if ($s['fecha_programada'] == $evento['fecha'] && $s['nombre_entidad'] . ': ' . $s['descripcion'] == $evento['mensaje']) {
                                        $seg = $s;
                                        break;
                                    }
                                }
                                ?>
                                <div class="notification-item seguimiento-item">
                                    <div class="notification-date">
                                        <i class="zmdi zmdi-calendar-check"></i>
                                        <?php echo $fecha_evento->format('d/m/Y'); ?>
                                        <?php if ($es_hoy): ?>
                                            <span class="notification-status status-priority">HOY</span>
                                        <?php elseif ($es_vencida && $seg && $seg['estatus'] != 'Cumplido'): ?>
                                            <span class="notification-status status-overdue">VENCIDO</span>
                                        <?php elseif ($seg && $seg['estatus'] == 'Cumplido'): ?>
                                            <span class="notification-status" style="background: #e8f5e9; color: #388e3c;">CUMPLIDO</span>
                                        <?php else: ?>
                                            <span class="notification-status" style="background: #fff3e0; color: #f57c00;">PENDIENTE</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notification-message">
                                        <strong>📅 Seguimiento:</strong> <?php echo htmlspecialchars($evento['mensaje']); ?>
                                        <?php if ($seg): ?>
                                            <br><small style="color: var(--light-text);">Tipo: <?php echo $seg['tipo_entidad'] == 'cliente' ? 'Cliente' : 'Empresa'; ?> | Estatus: <?php echo htmlspecialchars($seg['estatus']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Datos de notificaciones y seguimientos desde PHP
        const notificaciones = <?php echo json_encode($notificaciones_calendario); ?>;
        const seguimientos = <?php echo json_encode($seguimientos_calendario); ?>;
        
        // Variables globales para el calendario
        let currentDate = new Date();
        
        // Funcionalidad del calendario
        function generateCalendar() {
            const currentMonth = currentDate.getMonth();
            const currentYear = currentDate.getFullYear();
            
            // Actualizar header del calendario
            const monthNames = [
                'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
            ];
            document.getElementById('current-month-year').textContent = 
                `${monthNames[currentMonth]} ${currentYear}`;
            
            // Obtener primer día del mes y cuántos días tiene
            const firstDay = new Date(currentYear, currentMonth, 1);
            const lastDay = new Date(currentYear, currentMonth + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startingDayOfWeek = (firstDay.getDay() + 6) % 7; // Lunes = 0
            
            // Crear array de días
            const days = [];
            
            // Días del mes anterior
            const prevMonth = new Date(currentYear, currentMonth, 0);
            const daysInPrevMonth = prevMonth.getDate();
            for (let i = startingDayOfWeek - 1; i >= 0; i--) {
                days.push({
                    day: daysInPrevMonth - i,
                    isCurrentMonth: false,
                    date: new Date(currentYear, currentMonth - 1, daysInPrevMonth - i)
                });
            }
            
            // Días del mes actual
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(currentYear, currentMonth, day);
                const dateStr = date.toISOString().split('T')[0];
                
                // Verificar si hay notificaciones en este día
                const dayNotifications = notificaciones.filter(notif => 
                    notif.fecha_limite === dateStr
                );
                
                // Verificar si hay seguimientos en este día
                const daySeguimientos = seguimientos.filter(seg => 
                    seg.fecha_programada === dateStr
                );
                
                const hasNotification = dayNotifications.length > 0;
                const hasPriority = dayNotifications.some(notif => notif.prioritaria == 1);
                const hasSeguimiento = daySeguimientos.length > 0;
                const isToday = dateStr === new Date().toISOString().split('T')[0];
                
                days.push({
                    day: day,
                    isCurrentMonth: true,
                    date: date,
                    hasNotification: hasNotification,
                    hasPriority: hasPriority,
                    hasSeguimiento: hasSeguimiento,
                    isToday: isToday,
                    notifications: dayNotifications,
                    seguimientos: daySeguimientos
                });
            }
            
            // Días del mes siguiente para completar la grilla
            const remainingDays = 42 - days.length; // 6 semanas x 7 días
            for (let day = 1; day <= remainingDays; day++) {
                days.push({
                    day: day,
                    isCurrentMonth: false,
                    date: new Date(currentYear, currentMonth + 1, day)
                });
            }
            
            // Generar HTML del calendario
            const calendarDays = document.getElementById('calendar-days');
            calendarDays.innerHTML = '';
            
            days.forEach(day => {
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day';
                dayElement.textContent = day.day;
                
                if (!day.isCurrentMonth) {
                    dayElement.classList.add('other-month');
                }
                
                if (day.isToday) {
                    dayElement.classList.add('today');
                }
                
                if (day.hasNotification) {
                    dayElement.classList.add('has-notification');
                    if (day.hasPriority) {
                        dayElement.classList.add('has-priority');
                    }
                    
                    // Agregar punto de notificación
                    const dot = document.createElement('div');
                    dot.className = 'notification-dot';
                    if (day.hasPriority) {
                        dot.classList.add('priority');
                    }
                    dayElement.appendChild(dot);
                }
                
                if (day.hasSeguimiento) {
                    dayElement.classList.add('has-seguimiento');
                    
                    // Agregar punto de seguimiento
                    const dotSeguimiento = document.createElement('div');
                    dotSeguimiento.className = 'notification-dot seguimiento-dot';
                    dayElement.appendChild(dotSeguimiento);
                }
                
                // Tooltip con información combinada
                const tooltipParts = [];
                if (day.hasNotification) {
                    tooltipParts.push(...day.notifications.map(notif => '📢 ' + notif.mensaje));
                }
                if (day.hasSeguimiento) {
                    tooltipParts.push(...day.seguimientos.map(seg => '📅 ' + seg.nombre_entidad + ': ' + seg.descripcion));
                }
                if (tooltipParts.length > 0) {
                    dayElement.title = tooltipParts.join('\n');
                }
                
                calendarDays.appendChild(dayElement);
            });
        }
        
        // Cambiar mes
        function changeMonth(direction) {
            currentDate.setMonth(currentDate.getMonth() + direction);
            generateCalendar();
        }
        
        // Funcionalidad para el sidebar en móviles
        document.addEventListener('DOMContentLoaded', function() {
            // Generar el calendario al cargar la página
            generateCalendar();
            
            // Agregar funcionalidad de menú móvil si es necesario
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
