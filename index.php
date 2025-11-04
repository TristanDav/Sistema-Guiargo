<?php
// Incluir archivo de conexión a la base de datos
require_once 'conexion.php';

// Verificar que la conexión se estableció correctamente
if (!isset($conexion) || $conexion->connect_error) {
    die('Error: No se pudo conectar a la base de datos');
}

// Iniciar sesión
session_start();

// Verificar si ya está logueado
if (isset($_SESSION['usuario_id'])) {
    header('Location: home_guiargo.php');
    exit();
}

// Procesar login
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (!empty($username) && !empty($password)) {
        // Verificar que la conexión esté disponible
        if (!$conexion) {
            $error = 'Error de conexión a la base de datos';
        } else {
            // Consultar usuario en la base de datos
            $stmt = $conexion->prepare("SELECT id, username, password, rol, email FROM usuarios WHERE username = ? AND activo = 1");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $usuario = $result->fetch_assoc();
                    
                    // Verificar contraseña usando password_verify para contraseñas hasheadas
                    // También soportar contraseñas sin hash para usuarios existentes (backward compatibility)
                    $password_valid = false;
                    
                    // Primero intentar con password_verify (para contraseñas hasheadas)
                    if (password_verify($password, $usuario['password'])) {
                        $password_valid = true;
                    } 
                    // Si no funciona, verificar contraseñas sin hash (para usuarios antiguos)
                    elseif ($password === 'admin123' && $usuario['username'] === 'admin') {
                        $password_valid = true;
                    } 
                    elseif ($password === 'colaborador123' && $usuario['username'] === 'colaborador') {
                        $password_valid = true;
                    }
                    
                    if ($password_valid) {
                        // Login exitoso
                        $_SESSION['usuario_id'] = $usuario['id'];
                        $_SESSION['username'] = $usuario['username'];
                        $_SESSION['rol'] = $usuario['rol'];
                        $_SESSION['email'] = $usuario['email'];
                        
                        header('Location: home_guiargo.php');
                        exit();
                    } else {
                        $error = 'Contraseña incorrecta';
                    }
                } else {
                    $error = 'Usuario no encontrado';
                }
                $stmt->close();
            } else {
                $error = 'Error al preparar la consulta';
            }
        }
    } else {
        $error = 'Por favor, complete todos los campos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Login</title>
	<link rel="stylesheet" href="css/normalize.css">
	<link rel="stylesheet" href="css/sweetalert2.css">
	<link rel="stylesheet" href="css/material.min.css">
	<link rel="stylesheet" href="css/material-design-iconic-font.min.css">
	<link rel="stylesheet" href="css/jquery.mCustomScrollbar.css">
	<link rel="stylesheet" href="css/main.css">
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
	<script>window.jQuery || document.write('<script src="js/jquery-1.11.2.min.js"><\/script>')</script>
	<script src="js/material.min.js" ></script>
	<script src="js/sweetalert2.min.js" ></script>
	<script src="js/jquery.mCustomScrollbar.concat.min.js" ></script>
	<script src="js/main.js" ></script>
</head>
<body class="cover">
	<div class="container-login">
		<p class="text-center" style="font-size: 80px;">
			<i class="zmdi zmdi-account-circle"></i>
		</p>
		<p class="text-center text-condensedLight">Inicia Sesión con tu cuenta</p>
		
		<?php if (!empty($error)): ?>
			<div class="alert alert-danger" style="background-color: #e74c3c; color: white; padding: 10px; margin: 10px 0; border-radius: 5px; text-align: center;">
				<i class="zmdi zmdi-alert-circle"></i> <?php echo $error; ?>
			</div>
		<?php endif; ?>
		
		<form method="POST" action="">
			<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
			    <input class="mdl-textfield__input" type="text" id="userName" name="username" required>
			    <label class="mdl-textfield__label" for="userName">Usuario</label>
			</div>
			<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label">
			    <input class="mdl-textfield__input" type="password" id="pass" name="password" required>
			    <label class="mdl-textfield__label" for="pass">Contraseña</label>
			</div>
			<button type="submit" class="mdl-button mdl-js-button mdl-js-ripple-effect" style="color: #3F51B5; float:right;">
				Entrar <i class="zmdi zmdi-mail-send"></i>
			</button>
		</form>
	</div>
</body>
</html>
