# Sistema Guiargo - Gestor de Clientes

Sistema de gestión de clientes y empresas desarrollado para Grupo Guiargo.

## Características

- **Gestión de Clientes**: CRUD completo para clientes con estados de seguimiento
- **Gestión de Empresas**: CRUD completo para empresas con RFC
- **Sistema de Notificaciones**: Recordatorios y alertas para el personal
- **Seguimientos**: Sistema de seguimiento para clientes y empresas en proceso
- **Gestión de Usuarios**: CRUD para administración de usuarios del sistema
- **Calendario Integrado**: Visualización de notificaciones y seguimientos en calendario
- **Dashboard Moderno**: Panel de control con estadísticas y vista general

## Requisitos

- PHP 7.4 o superior
- MySQL 5.7 o superior (o MariaDB 10.4+)
- Servidor web (Apache/Nginx) o XAMPP
- Extensión mysqli de PHP

## Instalación

1. Clonar el repositorio:
```bash
git clone https://github.com/TU_USUARIO/Sistema-Guiargo.git
cd Sistema-Guiargo
```

2. Configurar la base de datos:
   - Crear una base de datos MySQL llamada `gestor_clientes_guiargo`
   - Importar el archivo `gestor_clientes_guiargo.sql` en tu base de datos

3. Configurar la conexión:
   - Copiar el archivo `conexion.php.example` a `conexion.php`
   - Editar `conexion.php` con tus credenciales de base de datos:
   ```php
   $host = 'localhost:3307';
   $usuario = 'tu_usuario';
   $contrasena = 'tu_contraseña';
   $base_de_datos = 'gestor_clientes_guiargo';
   ```

4. Configurar el servidor web:
   - Si usas XAMPP, coloca el proyecto en `htdocs/Sistema Guiargo`
   - Asegúrate de que Apache y MySQL estén corriendo

5. Acceder al sistema:
   - Abrir el navegador en `http://localhost/Sistema Guiargo/`
   - Usar las credenciales por defecto:
     - Usuario: `admin`
     - Contraseña: `password` (o la que hayas configurado)

## Estructura del Proyecto

```
Sistema Guiargo/
├── assets/              # Recursos estáticos (imágenes, iconos)
├── css/                 # Archivos de estilos
├── js/                  # Archivos JavaScript
├── fonts/               # Fuentes personalizadas
├── clientes.php         # CRUD de clientes
├── empresas.php         # CRUD de empresas
├── notificaciones.php   # Sistema de notificaciones
├── seguimientos.php     # Sistema de seguimientos
├── usuarios.php         # CRUD de usuarios
├── home_guiargo.php     # Dashboard principal
├── index.php            # Página de login
├── conexion.php         # Configuración de base de datos (no incluido en repo)
└── gestor_clientes_guiargo.sql  # Script de base de datos
```

## Características de Seguridad

- Autenticación de usuarios con contraseñas hasheadas
- Validación de sesiones
- Prepared statements para prevenir SQL injection
- Protección contra XSS con `htmlspecialchars()`

## Colores Corporativos

- **Principal**: Blanco
- **Secundario**: Azul #0b1786
- **Terciario**: Naranja #f39c12

## Desarrollo

Este proyecto utiliza:
- PHP para el backend
- MySQL para la base de datos
- HTML5, CSS3 y JavaScript para el frontend
- Material Design Icons para iconografía

## Licencia

Este proyecto es propiedad de Grupo Guiargo.

## Soporte

Para soporte o consultas, contactar al equipo de desarrollo.

