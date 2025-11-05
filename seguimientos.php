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

// Función para obtener clientes con estatus "En seguimiento"
function getClientesEnSeguimiento($conexion) {
    $stmt = $conexion->prepare("SELECT id, nombre FROM clientes WHERE estatus = 'En seguimiento' ORDER BY nombre ASC");
    if ($stmt) {
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

// Función para obtener empresas con estatus "En seguimiento"
function getEmpresasEnSeguimiento($conexion) {
    $stmt = $conexion->prepare("SELECT id, nombre FROM empresas WHERE estatus = 'En seguimiento' ORDER BY nombre ASC");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $empresas = [];
        while ($row = $result->fetch_assoc()) {
            $empresas[] = $row;
        }
        $stmt->close();
        return $empresas;
    }
    return [];
}

// Función para obtener nombre de entidad (cliente o empresa)
function getNombreEntidad($conexion, $tipo_entidad, $id_entidad) {
    $tabla = $tipo_entidad == 'cliente' ? 'clientes' : 'empresas';
    $stmt = $conexion->prepare("SELECT nombre FROM $tabla WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id_entidad);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['nombre'];
        }
        $stmt->close();
    }
    return 'N/A';
}

// Función para obtener nombre de usuario
function getNombreUsuario($conexion, $usuario_id) {
    $stmt = $conexion->prepare("SELECT username FROM usuarios WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['username'];
        }
        $stmt->close();
    }
    return 'N/A';
}

// Función para obtener seguimientos con búsqueda y filtros
function getSeguimientos($conexion, $busqueda = '', $filtros = []) {
    $sql = "SELECT s.*, 
                   CASE 
                       WHEN s.tipo_entidad = 'cliente' THEN c.nombre 
                       WHEN s.tipo_entidad = 'empresa' THEN e.nombre 
                   END as nombre_entidad,
                   u.username as nombre_usuario
            FROM seguimientos s
            LEFT JOIN clientes c ON s.tipo_entidad = 'cliente' AND s.id_entidad = c.id
            LEFT JOIN empresas e ON s.tipo_entidad = 'empresa' AND s.id_entidad = e.id
            LEFT JOIN usuarios u ON s.id_usuario_asignado = u.id
            WHERE 1=1";
    $params = [];
    $types = "";
    
    // Búsqueda por descripción o nombre de entidad
    if (!empty($busqueda)) {
        $sql .= " AND (s.descripcion LIKE ? OR c.nombre LIKE ? OR e.nombre LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
        $types .= "sss";
    }
    
    // Filtros
    if (!empty($filtros['tipo_entidad'])) {
        $sql .= " AND s.tipo_entidad = ?";
        $params[] = $filtros['tipo_entidad'];
        $types .= "s";
    }
    
    if (!empty($filtros['estatus'])) {
        $sql .= " AND s.estatus = ?";
        $params[] = $filtros['estatus'];
        $types .= "s";
    }
    
    if (!empty($filtros['fecha_desde'])) {
        $sql .= " AND s.fecha_programada >= ?";
        $params[] = $filtros['fecha_desde'];
        $types .= "s";
    }
    
    if (!empty($filtros['fecha_hasta'])) {
        $sql .= " AND s.fecha_programada <= ?";
        $params[] = $filtros['fecha_hasta'];
        $types .= "s";
    }
    
    if (!empty($filtros['usuario_asignado'])) {
        $sql .= " AND s.id_usuario_asignado = ?";
        $params[] = (int)$filtros['usuario_asignado'];
        $types .= "i";
    }
    
    $sql .= " ORDER BY s.fecha_programada ASC";
    
    $stmt = $conexion->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $seguimientos = [];
        while ($row = $result->fetch_assoc()) {
            $seguimientos[] = $row;
        }
        $stmt->close();
        return $seguimientos;
    }
    return [];
}

// Función para obtener un seguimiento por ID
function getSeguimientoById($conexion, $id) {
    $stmt = $conexion->prepare("SELECT * FROM seguimientos WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $seguimiento = $result->fetch_assoc();
        $stmt->close();
        return $seguimiento;
    }
    return null;
}

// Función para insertar un nuevo seguimiento
function insertarSeguimiento($conexion, $datos) {
    $stmt = $conexion->prepare("INSERT INTO seguimientos (id_entidad, tipo_entidad, id_usuario_asignado, fecha_programada, descripcion, estatus) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $estatus = isset($datos['estatus']) ? $datos['estatus'] : 'Pendiente';
        $stmt->bind_param("isisss", $datos['id_entidad'], $datos['tipo_entidad'], $datos['id_usuario_asignado'], $datos['fecha_programada'], $datos['descripcion'], $estatus);
        $resultado = $stmt->execute();
        $stmt->close();
        return $resultado;
    }
    return false;
}

// Función para actualizar un seguimiento
function actualizarSeguimiento($conexion, $id, $datos) {
    $stmt = $conexion->prepare("UPDATE seguimientos SET id_entidad = ?, tipo_entidad = ?, id_usuario_asignado = ?, fecha_programada = ?, descripcion = ?, estatus = ?, fecha_cumplimiento = ? WHERE id = ?");
    if ($stmt) {
        $estatus = isset($datos['estatus']) ? $datos['estatus'] : 'Pendiente';
        $fecha_cumplimiento = (!empty($datos['fecha_cumplimiento']) && $datos['estatus'] == 'Cumplido') ? $datos['fecha_cumplimiento'] : null;
        $stmt->bind_param("isissssi", $datos['id_entidad'], $datos['tipo_entidad'], $datos['id_usuario_asignado'], $datos['fecha_programada'], $datos['descripcion'], $estatus, $fecha_cumplimiento, $id);
        $resultado = $stmt->execute();
        $stmt->close();
        return $resultado;
    }
    return false;
}

// Función para eliminar un seguimiento
function eliminarSeguimiento($conexion, $id) {
    $stmt = $conexion->prepare("DELETE FROM seguimientos WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $resultado = $stmt->execute();
        $stmt->close();
        return $resultado;
    }
    return false;
}

// Función para marcar seguimiento como cumplido
function marcarComoCumplido($conexion, $id) {
    $fecha_cumplimiento = date('Y-m-d');
    $stmt = $conexion->prepare("UPDATE seguimientos SET estatus = 'Cumplido', fecha_cumplimiento = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $fecha_cumplimiento, $id);
        $resultado = $stmt->execute();
        $stmt->close();
        return $resultado;
    }
    return false;
}

// Función para actualizar automáticamente seguimientos vencidos
function actualizarSeguimientosVencidos($conexion) {
    $stmt = $conexion->prepare("UPDATE seguimientos 
                                 SET estatus = 'Vencido' 
                                 WHERE fecha_programada < CURDATE() 
                                 AND estatus = 'Pendiente' 
                                 AND fecha_cumplimiento IS NULL");
    if ($stmt) {
        $resultado = $stmt->execute();
        $stmt->close();
        return $resultado;
    }
    return false;
}

// Función para obtener todos los usuarios
function getUsuarios($conexion) {
    $stmt = $conexion->prepare("SELECT id, username FROM usuarios WHERE activo = 1 ORDER BY username ASC");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $usuarios = [];
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
        $stmt->close();
        return $usuarios;
    }
    return [];
}

// Actualizar seguimientos vencidos automáticamente (después de definir la función)
actualizarSeguimientosVencidos($conexion);

// Procesar formularios
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['accion'])) {
        $redirect_url = 'seguimientos.php';
        
        switch ($_POST['accion']) {
            case 'crear':
                $datos = [
                    'id_entidad' => (int)$_POST['id_entidad'],
                    'tipo_entidad' => trim($_POST['tipo_entidad']),
                    'id_usuario_asignado' => (int)$_POST['id_usuario_asignado'],
                    'fecha_programada' => trim($_POST['fecha_programada']),
                    'descripcion' => trim($_POST['descripcion']),
                    'estatus' => isset($_POST['estatus']) ? trim($_POST['estatus']) : 'Pendiente'
                ];
                
                if (insertarSeguimiento($conexion, $datos)) {
                    $_SESSION['mensaje'] = 'Seguimiento creado exitosamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                } else {
                    $_SESSION['mensaje'] = 'Error al crear el seguimiento';
                    $_SESSION['tipo_mensaje'] = 'error';
                }
                break;
                
            case 'actualizar':
                $id = (int)$_POST['id'];
                $datos = [
                    'id_entidad' => (int)$_POST['id_entidad'],
                    'tipo_entidad' => trim($_POST['tipo_entidad']),
                    'id_usuario_asignado' => (int)$_POST['id_usuario_asignado'],
                    'fecha_programada' => trim($_POST['fecha_programada']),
                    'descripcion' => trim($_POST['descripcion']),
                    'estatus' => isset($_POST['estatus']) ? trim($_POST['estatus']) : 'Pendiente',
                    'fecha_cumplimiento' => isset($_POST['fecha_cumplimiento']) ? trim($_POST['fecha_cumplimiento']) : ''
                ];
                
                if (actualizarSeguimiento($conexion, $id, $datos)) {
                    $_SESSION['mensaje'] = 'Seguimiento actualizado exitosamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                } else {
                    $_SESSION['mensaje'] = 'Error al actualizar el seguimiento';
                    $_SESSION['tipo_mensaje'] = 'error';
                }
                break;
                
            case 'eliminar':
                $id = (int)$_POST['id'];
                if (eliminarSeguimiento($conexion, $id)) {
                    $_SESSION['mensaje'] = 'Seguimiento eliminado exitosamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                } else {
                    $_SESSION['mensaje'] = 'Error al eliminar el seguimiento';
                    $_SESSION['tipo_mensaje'] = 'error';
                }
                break;
                
            case 'marcar_cumplido':
                $id = (int)$_POST['id'];
                if (marcarComoCumplido($conexion, $id)) {
                    $_SESSION['mensaje'] = 'Seguimiento marcado como cumplido exitosamente';
                    $_SESSION['tipo_mensaje'] = 'success';
                } else {
                    $_SESSION['mensaje'] = 'Error al marcar el seguimiento como cumplido';
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
    'tipo_entidad' => isset($_GET['tipo_entidad']) ? trim($_GET['tipo_entidad']) : '',
    'estatus' => isset($_GET['estatus']) ? trim($_GET['estatus']) : '',
    'fecha_desde' => isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '',
    'fecha_hasta' => isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '',
    'usuario_asignado' => isset($_GET['usuario_asignado']) ? trim($_GET['usuario_asignado']) : ''
];

// Obtener seguimientos con filtros aplicados
$seguimientos = getSeguimientos($conexion, $busqueda, $filtros);

// Obtener seguimiento para editar si se especifica
$seguimiento_editar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $seguimiento_editar = getSeguimientoById($conexion, $_GET['editar']);
}

// Obtener listas para formularios
$clientes_en_seguimiento = getClientesEnSeguimiento($conexion);
$empresas_en_seguimiento = getEmpresasEnSeguimiento($conexion);
$usuarios = getUsuarios($conexion);

// Función para obtener la clase CSS del estatus
function getEstatusClass($estatus) {
    $estatus = strtolower(str_replace(' ', '-', $estatus));
    return "status-badge status-{$estatus}";
}

// Función para obtener la clase CSS del tipo de entidad
function getTipoEntidadClass($tipo) {
    return $tipo == 'cliente' ? 'status-cliente' : 'status-empresa';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Seguimientos - Sistema Guiargo</title>
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
    <link rel="stylesheet" href="css/seguimientos.css">
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
                <a href="clientes.php" class="menu-item">
                    <i class="zmdi zmdi-accounts"></i>
                    Clientes
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
            </div>
        </nav>
    </aside>

    <!-- Contenido Principal -->
    <main class="main-content">
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
                        Nuevo Seguimiento
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
                                       placeholder="Buscar por descripción o nombre de entidad..." 
                                       value="<?php echo htmlspecialchars($busqueda); ?>">
                                <button type="submit" class="search-btn">
                                    <i class="zmdi zmdi-search"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Filtros -->
                        <div class="filters-section">
                            <div class="filter-group">
                                <label for="tipo_entidad">Tipo:</label>
                                <select name="tipo_entidad" id="tipo_entidad" class="filter-select">
                                    <option value="">Todos los tipos</option>
                                    <option value="cliente" <?php echo $filtros['tipo_entidad'] == 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                                    <option value="empresa" <?php echo $filtros['tipo_entidad'] == 'empresa' ? 'selected' : ''; ?>>Empresa</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="estatus">Estatus:</label>
                                <select name="estatus" id="estatus" class="filter-select">
                                    <option value="">Todos los estatus</option>
                                    <option value="Pendiente" <?php echo $filtros['estatus'] == 'Pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="Cumplido" <?php echo $filtros['estatus'] == 'Cumplido' ? 'selected' : ''; ?>>Cumplido</option>
                                    <option value="Vencido" <?php echo $filtros['estatus'] == 'Vencido' ? 'selected' : ''; ?>>Vencido</option>
                                </select>
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

                            <div class="filter-group">
                                <label for="usuario_asignado">Usuario:</label>
                                <select name="usuario_asignado" id="usuario_asignado" class="filter-select">
                                    <option value="">Todos los usuarios</option>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <option value="<?php echo $usuario['id']; ?>" <?php echo $filtros['usuario_asignado'] == $usuario['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($usuario['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="zmdi zmdi-search"></i> Filtrar
                                </button>
                                <a href="seguimientos.php" class="btn btn-secondary btn-sm">
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
                        <strong>Resultados encontrados:</strong> <?php echo count($seguimientos); ?> seguimiento(s)
                    </div>
                <?php endif; ?>

                <!-- Cards View -->
                <div id="cards-view" class="cards-container">
                    <?php if (empty($seguimientos)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-color);">
                            <?php if (!empty($busqueda) || !empty(array_filter($filtros))): ?>
                                <i class="zmdi zmdi-search" style="font-size: 4rem; color: var(--border-color); margin-bottom: 1rem;"></i>
                                <h3>No se encontraron resultados</h3>
                                <p>Intenta con otros términos de búsqueda o filtros</p>
                                <a href="seguimientos.php" class="btn btn-primary" style="margin-top: 1rem;">
                                    <i class="zmdi zmdi-refresh"></i> Ver todos los seguimientos
                                </a>
                            <?php else: ?>
                                <i class="zmdi zmdi-calendar-check" style="font-size: 4rem; color: var(--border-color); margin-bottom: 1rem;"></i>
                                <h3>No hay seguimientos registrados</h3>
                                <p>Comienza agregando tu primer seguimiento</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($seguimientos as $seguimiento): ?>
                            <div class="seguimiento-card">
                                <div class="card-header">
                                    <div>
                                        <h3 class="entidad-name"><?php echo htmlspecialchars($seguimiento['nombre_entidad']); ?></h3>
                                        <span class="status-badge <?php echo getTipoEntidadClass($seguimiento['tipo_entidad']); ?>">
                                            <?php echo $seguimiento['tipo_entidad'] == 'cliente' ? 'Cliente' : 'Empresa'; ?>
                                        </span>
                                    </div>
                                    <div class="card-actions">
                                        <?php if ($seguimiento['estatus'] != 'Cumplido'): ?>
                                            <button class="btn btn-success btn-sm" onclick="marcarComoCumplido(<?php echo $seguimiento['id']; ?>)" title="Marcar como cumplido">
                                                <i class="zmdi zmdi-check-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-primary btn-sm" onclick="openModal('editar', <?php echo $seguimiento['id']; ?>)">
                                            <i class="zmdi zmdi-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="eliminarSeguimiento(<?php echo $seguimiento['id']; ?>)">
                                            <i class="zmdi zmdi-delete"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-info">
                                    <div class="info-item">
                                        <i class="zmdi zmdi-calendar"></i>
                                        <span><strong>Fecha programada:</strong> <?php echo date('d/m/Y', strtotime($seguimiento['fecha_programada'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="zmdi zmdi-account"></i>
                                        <span><strong>Usuario asignado:</strong> <?php echo htmlspecialchars($seguimiento['nombre_usuario']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="zmdi zmdi-<?php echo $seguimiento['estatus'] == 'Cumplido' ? 'check-circle' : ($seguimiento['estatus'] == 'Vencido' ? 'close-circle' : 'time'); ?>"></i>
                                        <span><strong>Estatus:</strong> <span class="status-badge <?php echo getEstatusClass($seguimiento['estatus']); ?>"><?php echo htmlspecialchars($seguimiento['estatus']); ?></span></span>
                                    </div>
                                    <?php if ($seguimiento['fecha_cumplimiento']): ?>
                                        <div class="info-item">
                                            <i class="zmdi zmdi-check-all"></i>
                                            <span><strong>Cumplido el:</strong> <?php echo date('d/m/Y', strtotime($seguimiento['fecha_cumplimiento'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="descripcion-text">
                                        <strong>Descripción:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($seguimiento['descripcion'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Table View -->
                <div id="table-view" class="table-container hidden">
                    <?php if (empty($seguimientos)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-color);">
                            <?php if (!empty($busqueda) || !empty(array_filter($filtros))): ?>
                                <i class="zmdi zmdi-search" style="font-size: 4rem; color: var(--border-color); margin-bottom: 1rem;"></i>
                                <h3>No se encontraron resultados</h3>
                                <p>Intenta con otros términos de búsqueda o filtros</p>
                                <a href="seguimientos.php" class="btn btn-primary" style="margin-top: 1rem;">
                                    <i class="zmdi zmdi-refresh"></i> Ver todos los seguimientos
                                </a>
                            <?php else: ?>
                                <i class="zmdi zmdi-calendar-check" style="font-size: 4rem; color: var(--border-color); margin-bottom: 1rem;"></i>
                                <h3>No hay seguimientos registrados</h3>
                                <p>Comienza agregando tu primer seguimiento</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Entidad</th>
                                    <th>Tipo</th>
                                    <th>Fecha Programada</th>
                                    <th>Usuario</th>
                                    <th>Estatus</th>
                                    <th>Descripción</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($seguimientos as $seguimiento): ?>
                                    <tr>
                                        <td><?php echo $seguimiento['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($seguimiento['nombre_entidad']); ?></strong></td>
                                        <td><span class="status-badge <?php echo getTipoEntidadClass($seguimiento['tipo_entidad']); ?>"><?php echo $seguimiento['tipo_entidad'] == 'cliente' ? 'Cliente' : 'Empresa'; ?></span></td>
                                        <td><?php echo date('d/m/Y', strtotime($seguimiento['fecha_programada'])); ?></td>
                                        <td><?php echo htmlspecialchars($seguimiento['nombre_usuario']); ?></td>
                                        <td><span class="status-badge <?php echo getEstatusClass($seguimiento['estatus']); ?>"><?php echo htmlspecialchars($seguimiento['estatus']); ?></span></td>
                                        <td><?php echo htmlspecialchars(substr($seguimiento['descripcion'], 0, 50)) . (strlen($seguimiento['descripcion']) > 50 ? '...' : ''); ?></td>
                                        <td>
                                            <?php if ($seguimiento['estatus'] != 'Cumplido'): ?>
                                                <button class="btn btn-success btn-sm" onclick="marcarComoCumplido(<?php echo $seguimiento['id']; ?>)" title="Marcar como cumplido">
                                                    <i class="zmdi zmdi-check-circle"></i> Cumplido
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-primary btn-sm" onclick="openModal('editar', <?php echo $seguimiento['id']; ?>)">
                                                <i class="zmdi zmdi-edit"></i> Editar
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="eliminarSeguimiento(<?php echo $seguimiento['id']; ?>)">
                                                <i class="zmdi zmdi-delete"></i> Eliminar
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal para Crear/Editar Seguimiento -->
    <div id="modalSeguimiento" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Nuevo Seguimiento</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <form id="formSeguimiento" method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="accion" id="accion" value="crear">
                    <input type="hidden" name="id" id="seguimiento_id">
                    
                    <div class="form-group">
                        <label for="tipo_entidad">Tipo de Entidad *</label>
                        <select class="form-control" id="tipo_entidad" name="tipo_entidad" required onchange="cargarEntidades()">
                            <option value="">Seleccione un tipo</option>
                            <option value="cliente">Cliente</option>
                            <option value="empresa">Empresa</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="id_entidad">Entidad *</label>
                        <select class="form-control" id="id_entidad" name="id_entidad" required>
                            <option value="">Primero seleccione un tipo</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="fecha_programada">Fecha Programada *</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="fecha_programada" 
                                   name="fecha_programada" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="id_usuario_asignado">Usuario Asignado *</label>
                            <select class="form-control" id="id_usuario_asignado" name="id_usuario_asignado" required>
                                <option value="">Seleccione un usuario</option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?php echo $usuario['id']; ?>">
                                        <?php echo htmlspecialchars($usuario['username']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción *</label>
                        <textarea class="form-control" 
                                  id="descripcion" 
                                  name="descripcion" 
                                  required 
                                  placeholder="Ingrese la descripción del seguimiento"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="estatus">Estatus *</label>
                            <select class="form-control" id="estatus" name="estatus" required onchange="toggleFechaCumplimiento()">
                                <option value="Pendiente">Pendiente</option>
                                <option value="Cumplido">Cumplido</option>
                                <option value="Vencido">Vencido</option>
                            </select>
                        </div>

                        <div class="form-group" id="fecha_cumplimiento_group" style="display: none;">
                            <label for="fecha_cumplimiento">Fecha de Cumplimiento</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="fecha_cumplimiento" 
                                   name="fecha_cumplimiento">
                        </div>
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
        // Datos para JavaScript
        const clientesEnSeguimiento = <?php echo json_encode($clientes_en_seguimiento); ?>;
        const empresasEnSeguimiento = <?php echo json_encode($empresas_en_seguimiento); ?>;

        // Toggle entre vista de cards y tabla
        function toggleView(view) {
            const cardsView = document.getElementById('cards-view');
            const tableView = document.getElementById('table-view');
            const buttons = document.querySelectorAll('.view-btn');
            
            buttons.forEach(btn => btn.classList.remove('active'));
            
            if (view === 'cards') {
                cardsView.classList.remove('hidden');
                tableView.classList.add('hidden');
                buttons[0].classList.add('active');
            } else {
                cardsView.classList.add('hidden');
                tableView.classList.remove('hidden');
                buttons[1].classList.add('active');
            }
        }

        // Cargar entidades según el tipo seleccionado
        function cargarEntidades() {
            const tipoEntidad = document.getElementById('tipo_entidad').value;
            const selectEntidad = document.getElementById('id_entidad');
            
            selectEntidad.innerHTML = '<option value="">Seleccione una entidad</option>';
            
            if (tipoEntidad === 'cliente') {
                clientesEnSeguimiento.forEach(cliente => {
                    const option = document.createElement('option');
                    option.value = cliente.id;
                    option.textContent = cliente.nombre;
                    selectEntidad.appendChild(option);
                });
            } else if (tipoEntidad === 'empresa') {
                empresasEnSeguimiento.forEach(empresa => {
                    const option = document.createElement('option');
                    option.value = empresa.id;
                    option.textContent = empresa.nombre;
                    selectEntidad.appendChild(option);
                });
            }
        }

        // Toggle fecha de cumplimiento
        function toggleFechaCumplimiento() {
            const estatus = document.getElementById('estatus').value;
            const fechaCumplimientoGroup = document.getElementById('fecha_cumplimiento_group');
            
            if (estatus === 'Cumplido') {
                fechaCumplimientoGroup.style.display = 'block';
            } else {
                fechaCumplimientoGroup.style.display = 'none';
                document.getElementById('fecha_cumplimiento').value = '';
            }
        }

        // Abrir modal
        function openModal(accion, id = null) {
            const modal = document.getElementById('modalSeguimiento');
            const form = document.getElementById('formSeguimiento');
            const modalTitle = document.getElementById('modalTitle');
            const accionInput = document.getElementById('accion');
            
            if (accion === 'crear') {
                modalTitle.textContent = 'Nuevo Seguimiento';
                accionInput.value = 'crear';
                form.reset();
                document.getElementById('seguimiento_id').value = '';
                document.getElementById('fecha_cumplimiento_group').style.display = 'none';
                document.getElementById('id_entidad').innerHTML = '<option value="">Primero seleccione un tipo</option>';
            } else if (accion === 'editar' && id) {
                modalTitle.textContent = 'Editar Seguimiento';
                accionInput.value = 'actualizar';
                document.getElementById('seguimiento_id').value = id;
                
                // Cargar datos del seguimiento desde PHP
                <?php if ($seguimiento_editar): ?>
                    document.getElementById('tipo_entidad').value = '<?php echo htmlspecialchars($seguimiento_editar['tipo_entidad']); ?>';
                    cargarEntidades();
                    setTimeout(() => {
                        document.getElementById('id_entidad').value = '<?php echo $seguimiento_editar['id_entidad']; ?>';
                    }, 100);
                    document.getElementById('fecha_programada').value = '<?php echo $seguimiento_editar['fecha_programada']; ?>';
                    document.getElementById('id_usuario_asignado').value = '<?php echo $seguimiento_editar['id_usuario_asignado']; ?>';
                    document.getElementById('descripcion').value = '<?php echo htmlspecialchars($seguimiento_editar['descripcion']); ?>';
                    document.getElementById('estatus').value = '<?php echo htmlspecialchars($seguimiento_editar['estatus']); ?>';
                    toggleFechaCumplimiento();
                    <?php if ($seguimiento_editar['fecha_cumplimiento']): ?>
                        document.getElementById('fecha_cumplimiento').value = '<?php echo $seguimiento_editar['fecha_cumplimiento']; ?>';
                    <?php endif; ?>
                <?php endif; ?>
            }
            
            modal.style.display = 'block';
        }

        // Cerrar modal
        function closeModal() {
            const modal = document.getElementById('modalSeguimiento');
            modal.style.display = 'none';
            document.getElementById('formSeguimiento').reset();
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('modalSeguimiento');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Eliminar seguimiento
        function eliminarSeguimiento(id) {
            if (confirm('¿Estás seguro de que deseas eliminar este seguimiento?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'seguimientos.php';
                
                const accionInput = document.createElement('input');
                accionInput.type = 'hidden';
                accionInput.name = 'accion';
                accionInput.value = 'eliminar';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                
                form.appendChild(accionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Marcar seguimiento como cumplido
        function marcarComoCumplido(id) {
            if (confirm('¿Deseas marcar este seguimiento como cumplido?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'seguimientos.php';
                
                const accionInput = document.createElement('input');
                accionInput.type = 'hidden';
                accionInput.name = 'accion';
                accionInput.value = 'marcar_cumplido';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                
                form.appendChild(accionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Si hay un seguimiento para editar, abrir el modal automáticamente
        <?php if ($seguimiento_editar): ?>
            window.onload = function() {
                openModal('editar', <?php echo $seguimiento_editar['id']; ?>);
            };
        <?php endif; ?>
    </script>
</body>
</html>

