<?php
/**
 * Funciones helper para verificación de roles de usuario
 */

/**
 * Verifica si el usuario actual es administrador
 * @return bool True si es administrador, False en caso contrario
 */
function esAdministrador() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}

/**
 * Verifica si el usuario actual es colaborador
 * @return bool True si es colaborador, False en caso contrario
 */
function esColaborador() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'colaborador';
}

/**
 * Verifica si el usuario tiene permisos para eliminar registros
 * Solo los administradores pueden eliminar
 * @return bool True si puede eliminar, False en caso contrario
 */
function puedeEliminar() {
    return esAdministrador();
}

/**
 * Redirige si el usuario no es administrador
 * Útil para proteger acciones que solo los administradores pueden realizar
 */
function requerirAdministrador() {
    if (!esAdministrador()) {
        $_SESSION['mensaje'] = 'No tienes permisos para realizar esta acción';
        $_SESSION['tipo_mensaje'] = 'error';
        header('Location: ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'home_guiargo.php'));
        exit();
    }
}

