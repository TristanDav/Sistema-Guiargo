<?php
// Incluir archivo de conexión a la base de datos
require_once 'conexion.php';
require_once 'funciones_notificaciones.php';
require_once 'funciones_roles.php';

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
    // Verificar que el usuario sea administrador
    if (!puedeEliminar()) {
        return false;
    }
    
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
                // Verificar permisos antes de eliminar
                if (!puedeEliminar()) {
                    $_SESSION['error'] = 'No tienes permisos para eliminar registros';
                    break;
                }
                
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
    <script src="js/mobile-menu.js"></script>
    <link rel="stylesheet" href="css/empresas.css">
</head>
<body>
    <!-- Overlay para sidebar móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
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
        <div class="navbar-right">
            <button class="menu-toggle" id="menuToggle" title="Menú">
                <i class="zmdi zmdi-menu"></i>
            </button>
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
            <?php if (esAdministrador()): ?>
            <div class="menu-section">
                <div class="menu-section-title">Usuarios</div>
                <a href="usuarios.php" class="menu-item">
                    <i class="zmdi zmdi-account-circle"></i>
                    Usuarios
                </a>
            </div>
            <?php endif; ?>
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
                        <?php if (puedeEliminar()): ?>
                        <button class="btn-action btn-delete" onclick="deleteEmpresa(<?php echo $empresa['id']; ?>, '<?php echo htmlspecialchars($empresa['nombre']); ?>')">
                            <i class="zmdi zmdi-delete"></i>
                            Eliminar
                        </button>
                        <?php endif; ?>
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
                                        <?php if (puedeEliminar()): ?>
                                        <button class="btn-action btn-delete" onclick="deleteEmpresa(<?php echo $empresa['id']; ?>, '<?php echo htmlspecialchars($empresa['nombre']); ?>')">
                                            <i class="zmdi zmdi-delete"></i>
                                        </button>
                                        <?php endif; ?>
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
