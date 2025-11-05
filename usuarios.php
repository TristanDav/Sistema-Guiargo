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

// Función para obtener usuarios con búsqueda y filtros
function getUsuarios($conexion, $busqueda = '', $filtros = []) {
    $sql = "SELECT * FROM usuarios WHERE 1=1";
    $params = [];
    $types = "";
    
    // Búsqueda por username o email
    if (!empty($busqueda)) {
        $sql .= " AND (username LIKE ? OR email LIKE ?)";
        $params[] = "%$busqueda%";
        $params[] = "%$busqueda%";
        $types .= "ss";
    }
    
    // Filtros
    if (!empty($filtros['rol'])) {
        $sql .= " AND rol = ?";
        $params[] = $filtros['rol'];
        $types .= "s";
    }
    
    if (isset($filtros['activo']) && $filtros['activo'] !== '') {
        $sql .= " AND activo = ?";
        $params[] = (int)$filtros['activo'];
        $types .= "i";
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
    
    $sql .= " ORDER BY fecha_registro DESC";
    
    $stmt = $conexion->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
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

// Función para obtener un usuario por ID
function getUsuarioById($conexion, $id) {
    $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $usuario = $result->fetch_assoc();
        $stmt->close();
        return $usuario;
    }
    return null;
}

// Función para verificar si el username ya existe
function usernameExiste($conexion, $username, $excluir_id = null) {
    $sql = "SELECT COUNT(*) as total FROM usuarios WHERE username = ?";
    if ($excluir_id) {
        $sql .= " AND id != ?";
    }
    $stmt = $conexion->prepare($sql);
    if ($stmt) {
        if ($excluir_id) {
            $stmt->bind_param("si", $username, $excluir_id);
        } else {
            $stmt->bind_param("s", $username);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['total'] > 0;
    }
    return false;
}

// Función para verificar si el email ya existe
function emailExiste($conexion, $email, $excluir_id = null) {
    $sql = "SELECT COUNT(*) as total FROM usuarios WHERE email = ?";
    if ($excluir_id) {
        $sql .= " AND id != ?";
    }
    $stmt = $conexion->prepare($sql);
    if ($stmt) {
        if ($excluir_id) {
            $stmt->bind_param("si", $email, $excluir_id);
        } else {
            $stmt->bind_param("s", $email);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['total'] > 0;
    }
    return false;
}

// Función para insertar un nuevo usuario
function insertarUsuario($conexion, $datos) {
    // Verificar si el username ya existe
    if (usernameExiste($conexion, $datos['username'])) {
        return ['success' => false, 'message' => 'El nombre de usuario ya existe'];
    }
    
    // Verificar si el email ya existe
    if (emailExiste($conexion, $datos['email'])) {
        return ['success' => false, 'message' => 'El correo electrónico ya existe'];
    }
    
    // Hash de la contraseña
    $password_hash = password_hash($datos['password'], PASSWORD_DEFAULT);
    
    $stmt = $conexion->prepare("INSERT INTO usuarios (username, password, rol, email, activo) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $rol = isset($datos['rol']) ? $datos['rol'] : 'colaborador';
        $activo = isset($datos['activo']) ? (int)$datos['activo'] : 1;
        $stmt->bind_param("ssssi", $datos['username'], $password_hash, $rol, $datos['email'], $activo);
        $resultado = $stmt->execute();
        $stmt->close();
        
        if ($resultado) {
            return ['success' => true, 'message' => 'Usuario registrado exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al registrar el usuario'];
        }
    }
    return ['success' => false, 'message' => 'Error en la consulta'];
}

// Función para actualizar un usuario
function actualizarUsuario($conexion, $id, $datos) {
    // Verificar si el username ya existe (excluyendo el actual)
    if (usernameExiste($conexion, $datos['username'], $id)) {
        return ['success' => false, 'message' => 'El nombre de usuario ya existe'];
    }
    
    // Verificar si el email ya existe (excluyendo el actual)
    if (emailExiste($conexion, $datos['email'], $id)) {
        return ['success' => false, 'message' => 'El correo electrónico ya existe'];
    }
    
    // Si se proporciona una nueva contraseña, actualizarla
    if (!empty($datos['password'])) {
        $password_hash = password_hash($datos['password'], PASSWORD_DEFAULT);
        $stmt = $conexion->prepare("UPDATE usuarios SET username = ?, password = ?, rol = ?, email = ?, activo = ? WHERE id = ?");
        if ($stmt) {
            $rol = isset($datos['rol']) ? $datos['rol'] : 'colaborador';
            $activo = isset($datos['activo']) ? (int)$datos['activo'] : 1;
            $stmt->bind_param("ssssii", $datos['username'], $password_hash, $rol, $datos['email'], $activo, $id);
        }
    } else {
        // No actualizar la contraseña
        $stmt = $conexion->prepare("UPDATE usuarios SET username = ?, rol = ?, email = ?, activo = ? WHERE id = ?");
        if ($stmt) {
            $rol = isset($datos['rol']) ? $datos['rol'] : 'colaborador';
            $activo = isset($datos['activo']) ? (int)$datos['activo'] : 1;
            $stmt->bind_param("sssii", $datos['username'], $rol, $datos['email'], $activo, $id);
        }
    }
    
    if ($stmt) {
        $resultado = $stmt->execute();
        $stmt->close();
        
        if ($resultado) {
            return ['success' => true, 'message' => 'Usuario actualizado exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al actualizar el usuario'];
        }
    }
    return ['success' => false, 'message' => 'Error en la consulta'];
}

// Función para eliminar un usuario
function eliminarUsuario($conexion, $id) {
    // No permitir eliminar el usuario actual
    if ($id == $_SESSION['usuario_id']) {
        return ['success' => false, 'message' => 'No puedes eliminar tu propio usuario'];
    }
    
    $stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $resultado = $stmt->execute();
        $stmt->close();
        
        if ($resultado) {
            return ['success' => true, 'message' => 'Usuario eliminado exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al eliminar el usuario'];
        }
    }
    return ['success' => false, 'message' => 'Error en la consulta'];
}

// Procesar formularios
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['accion'])) {
        $redirect_url = 'usuarios.php';
        
        switch ($_POST['accion']) {
            case 'crear':
                $datos = [
                    'username' => trim($_POST['username']),
                    'password' => trim($_POST['password']),
                    'email' => trim($_POST['email']),
                    'rol' => isset($_POST['rol']) ? trim($_POST['rol']) : 'colaborador',
                    'activo' => isset($_POST['activo']) ? (int)$_POST['activo'] : 1
                ];
                
                // Validar campos requeridos
                if (empty($datos['username']) || empty($datos['password']) || empty($datos['email'])) {
                    $_SESSION['mensaje'] = 'Todos los campos son requeridos';
                    $_SESSION['tipo_mensaje'] = 'error';
                } else {
                    $resultado = insertarUsuario($conexion, $datos);
                    $_SESSION['mensaje'] = $resultado['message'];
                    $_SESSION['tipo_mensaje'] = $resultado['success'] ? 'success' : 'error';
                }
                break;
                
            case 'actualizar':
                $id = (int)$_POST['id'];
                $datos = [
                    'username' => trim($_POST['username']),
                    'password' => trim($_POST['password']), // Puede estar vacío
                    'email' => trim($_POST['email']),
                    'rol' => isset($_POST['rol']) ? trim($_POST['rol']) : 'colaborador',
                    'activo' => isset($_POST['activo']) ? (int)$_POST['activo'] : 1
                ];
                
                // Validar campos requeridos
                if (empty($datos['username']) || empty($datos['email'])) {
                    $_SESSION['mensaje'] = 'Username y email son requeridos';
                    $_SESSION['tipo_mensaje'] = 'error';
                } else {
                    $resultado = actualizarUsuario($conexion, $id, $datos);
                    $_SESSION['mensaje'] = $resultado['message'];
                    $_SESSION['tipo_mensaje'] = $resultado['success'] ? 'success' : 'error';
                }
                break;
                
            case 'eliminar':
                $id = (int)$_POST['id'];
                $resultado = eliminarUsuario($conexion, $id);
                $_SESSION['mensaje'] = $resultado['message'];
                $_SESSION['tipo_mensaje'] = $resultado['success'] ? 'success' : 'error';
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
    'rol' => isset($_GET['rol']) ? trim($_GET['rol']) : '',
    'activo' => isset($_GET['activo']) ? trim($_GET['activo']) : '',
    'fecha_desde' => isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '',
    'fecha_hasta' => isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : ''
];

// Obtener usuarios con filtros aplicados
$usuarios = getUsuarios($conexion, $busqueda, $filtros);

// Obtener usuario para editar si se especifica
$usuario_editar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $usuario_editar = getUsuarioById($conexion, $_GET['editar']);
}

// Función para obtener la clase CSS del rol
function getRolClass($rol) {
    return $rol == 'admin' ? 'status-admin' : 'status-colaborador';
}

// Función para obtener la clase CSS del estado activo
function getActivoClass($activo) {
    return $activo == 1 ? 'status-activo' : 'status-inactivo';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Sistema Guiargo</title>
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
    <link rel="stylesheet" href="css/usuarios.css">
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
                        Nuevo Usuario
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
                                       placeholder="Buscar por username o email..." 
                                       value="<?php echo htmlspecialchars($busqueda); ?>">
                                <button type="submit" class="search-btn">
                                    <i class="zmdi zmdi-search"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Filtros -->
                        <div class="filters-section">
                            <div class="filter-group">
                                <label for="rol">Rol:</label>
                                <select name="rol" id="rol" class="filter-select">
                                    <option value="">Todos los roles</option>
                                    <option value="admin" <?php echo $filtros['rol'] == 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                    <option value="colaborador" <?php echo $filtros['rol'] == 'colaborador' ? 'selected' : ''; ?>>Colaborador</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="activo">Estado:</label>
                                <select name="activo" id="activo" class="filter-select">
                                    <option value="">Todos</option>
                                    <option value="1" <?php echo $filtros['activo'] == '1' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="0" <?php echo $filtros['activo'] == '0' ? 'selected' : ''; ?>>Inactivo</option>
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

                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="zmdi zmdi-search"></i> Filtrar
                                </button>
                                <a href="usuarios.php" class="btn btn-secondary btn-sm">
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
                        <strong>Resultados encontrados:</strong> <?php echo count($usuarios); ?> usuario(s)
                        <?php if (!empty($busqueda)): ?>
                            para "<?php echo htmlspecialchars($busqueda); ?>"
                        <?php endif; ?>
                        <?php if (!empty($filtros['rol'])): ?>
                            con rol "<?php echo htmlspecialchars($filtros['rol']); ?>"
                        <?php endif; ?>
                        <?php if ($filtros['activo'] !== ''): ?>
                            <?php echo $filtros['activo'] == '1' ? 'activos' : 'inactivos'; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Cards View -->
                <div id="cards-view" class="cards-container">
                    <?php if (empty($usuarios)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-color);">
                            <?php if (!empty($busqueda) || !empty(array_filter($filtros))): ?>
                                <i class="zmdi zmdi-search" style="font-size: 4rem; color: var(--border-color); margin-bottom: 1rem;"></i>
                                <h3>No se encontraron resultados</h3>
                                <p>Intenta con otros términos de búsqueda o filtros</p>
                                <a href="usuarios.php" class="btn btn-primary" style="margin-top: 1rem;">
                                    <i class="zmdi zmdi-refresh"></i> Ver todos los usuarios
                                </a>
                            <?php else: ?>
                                <i class="zmdi zmdi-account" style="font-size: 4rem; color: var(--border-color); margin-bottom: 1rem;"></i>
                                <h3>No hay usuarios registrados</h3>
                                <p>Comienza agregando tu primer usuario</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($usuarios as $usuario): ?>
                            <div class="user-card">
                                <div class="card-header">
                                    <div>
                                        <h3 class="user-name"><?php echo htmlspecialchars($usuario['username']); ?></h3>
                                        <p class="user-email"><?php echo htmlspecialchars($usuario['email']); ?></p>
                                    </div>
                                    <div class="card-actions">
                                        <button class="btn btn-primary btn-sm" onclick="openModal('editar', <?php echo $usuario['id']; ?>)">
                                            <i class="zmdi zmdi-edit"></i>
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="eliminarUsuario(<?php echo $usuario['id']; ?>)">
                                            <i class="zmdi zmdi-delete"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-info">
                                    <div class="info-item">
                                        <i class="zmdi zmdi-account"></i>
                                        <span><strong>Rol:</strong> <span class="status-badge <?php echo getRolClass($usuario['rol']); ?>"><?php echo htmlspecialchars($usuario['rol']); ?></span></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="zmdi zmdi-<?php echo $usuario['activo'] == 1 ? 'check-circle' : 'close-circle'; ?>"></i>
                                        <span><strong>Estado:</strong> <span class="status-badge <?php echo getActivoClass($usuario['activo']); ?>"><?php echo $usuario['activo'] == 1 ? 'Activo' : 'Inactivo'; ?></span></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="zmdi zmdi-calendar"></i>
                                        <span><strong>Registro:</strong> <?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Table View -->
                <div id="table-view" class="table-container hidden">
                    <?php if (empty($usuarios)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-color);">
                            <?php if (!empty($busqueda) || !empty(array_filter($filtros))): ?>
                                <i class="zmdi zmdi-search" style="font-size: 4rem; color: var(--border-color); margin-bottom: 1rem;"></i>
                                <h3>No se encontraron resultados</h3>
                                <p>Intenta con otros términos de búsqueda o filtros</p>
                                <a href="usuarios.php" class="btn btn-primary" style="margin-top: 1rem;">
                                    <i class="zmdi zmdi-refresh"></i> Ver todos los usuarios
                                </a>
                            <?php else: ?>
                                <i class="zmdi zmdi-account" style="font-size: 4rem; color: var(--border-color); margin-bottom: 1rem;"></i>
                                <h3>No hay usuarios registrados</h3>
                                <p>Comienza agregando tu primer usuario</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Fecha Registro</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?php echo $usuario['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($usuario['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                        <td><span class="status-badge <?php echo getRolClass($usuario['rol']); ?>"><?php echo htmlspecialchars($usuario['rol']); ?></span></td>
                                        <td><span class="status-badge <?php echo getActivoClass($usuario['activo']); ?>"><?php echo $usuario['activo'] == 1 ? 'Activo' : 'Inactivo'; ?></span></td>
                                        <td><?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" onclick="openModal('editar', <?php echo $usuario['id']; ?>)">
                                                <i class="zmdi zmdi-edit"></i> Editar
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="eliminarUsuario(<?php echo $usuario['id']; ?>)">
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

    <!-- Modal para Crear/Editar Usuario -->
    <div id="modalUsuario" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Nuevo Usuario</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <form id="formUsuario" method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="accion" id="accion" value="crear">
                    <input type="hidden" name="id" id="usuario_id">
                    
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               required 
                               placeholder="Ingrese el nombre de usuario">
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               required 
                               placeholder="usuario@ejemplo.com">
                    </div>

                    <div class="form-group" id="password-group">
                        <label for="password">Contraseña <?php echo isset($usuario_editar) ? '<span class="password-help">(dejar vacío para no cambiar)</span>' : '*'; ?></label>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               <?php echo !isset($usuario_editar) ? 'required' : ''; ?>
                               placeholder="Ingrese la contraseña">
                        <?php if (isset($usuario_editar)): ?>
                            <small class="password-help">Dejar vacío si no deseas cambiar la contraseña</small>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="rol">Rol *</label>
                            <select class="form-control" id="rol" name="rol" required>
                                <option value="colaborador">Colaborador</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="activo">Estado</label>
                            <select class="form-control" id="activo" name="activo">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
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

        // Abrir modal
        function openModal(accion, id = null) {
            const modal = document.getElementById('modalUsuario');
            const form = document.getElementById('formUsuario');
            const modalTitle = document.getElementById('modalTitle');
            const accionInput = document.getElementById('accion');
            const passwordGroup = document.getElementById('password-group');
            const passwordInput = document.getElementById('password');
            
            if (accion === 'crear') {
                modalTitle.textContent = 'Nuevo Usuario';
                accionInput.value = 'crear';
                form.reset();
                document.getElementById('usuario_id').value = '';
                passwordInput.required = true;
                passwordInput.placeholder = 'Ingrese la contraseña';
            } else if (accion === 'editar' && id) {
                modalTitle.textContent = 'Editar Usuario';
                accionInput.value = 'actualizar';
                document.getElementById('usuario_id').value = id;
                passwordInput.required = false;
                passwordInput.placeholder = 'Dejar vacío para no cambiar';
                
                // Cargar datos del usuario
                fetch(`usuarios.php?editar=${id}`)
                    .then(response => response.text())
                    .then(html => {
                        // Extraer datos del usuario desde PHP
                        // Por simplicidad, usaremos un enfoque diferente
                        // En producción, sería mejor usar AJAX para obtener JSON
                    });
                
                // Cargar datos directamente desde PHP usando inline script
                <?php if ($usuario_editar): ?>
                    document.getElementById('username').value = '<?php echo htmlspecialchars($usuario_editar['username']); ?>';
                    document.getElementById('email').value = '<?php echo htmlspecialchars($usuario_editar['email']); ?>';
                    document.getElementById('rol').value = '<?php echo htmlspecialchars($usuario_editar['rol']); ?>';
                    document.getElementById('activo').value = '<?php echo $usuario_editar['activo']; ?>';
                    document.getElementById('password').value = '';
                <?php endif; ?>
            }
            
            modal.style.display = 'block';
        }

        // Cerrar modal
        function closeModal() {
            const modal = document.getElementById('modalUsuario');
            modal.style.display = 'none';
            document.getElementById('formUsuario').reset();
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('modalUsuario');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Eliminar usuario
        function eliminarUsuario(id) {
            if (confirm('¿Estás seguro de que deseas eliminar este usuario?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'usuarios.php';
                
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

        // Eliminar seleccionados (placeholder)
        function eliminarSeleccionados() {
            alert('Funcionalidad de eliminar seleccionados en desarrollo');
        }

        // Si hay un usuario para editar, abrir el modal automáticamente
        <?php if ($usuario_editar): ?>
            window.onload = function() {
                openModal('editar', <?php echo $usuario_editar['id']; ?>);
            };
        <?php endif; ?>
    </script>
</body>
</html>

