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
    <link rel="stylesheet" href="css/clientes.css">
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
