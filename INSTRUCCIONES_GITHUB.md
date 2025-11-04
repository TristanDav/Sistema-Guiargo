# Instrucciones para Subir el Proyecto a GitHub

## Paso 1: Crear el Repositorio en GitHub

1. Inicia sesión en tu cuenta de GitHub: https://github.com
2. Haz clic en el botón **"+"** en la esquina superior derecha y selecciona **"New repository"**
3. Configura el repositorio:
   - **Repository name**: `Sistema-Guiargo` (o el nombre que prefieras)
   - **Description**: "Sistema de gestión de clientes y empresas - Grupo Guiargo"
   - **Visibility**: Elige **Private** (recomendado para proyectos empresariales) o **Public**
   - **NO marques** "Initialize this repository with a README" (ya tenemos uno)
   - **NO marques** "Add .gitignore" (ya tenemos uno)
   - **NO marques** "Choose a license" (ya tenemos uno)
4. Haz clic en **"Create repository"**

## Paso 2: Conectar el Repositorio Local con GitHub

Una vez creado el repositorio en GitHub, verás una página con instrucciones. 

**Opción A: Si es la primera vez que usas este repositorio**

Ejecuta estos comandos en la terminal (PowerShell) desde la carpeta del proyecto:

```powershell
cd "C:\xampp\htdocs\Sistema Guiargo"
git remote add origin https://github.com/TU_USUARIO/Sistema-Guiargo.git
git branch -M main
git push -u origin main
```

**Reemplaza `TU_USUARIO` con tu nombre de usuario de GitHub.**

**Opción B: Si ya tienes el repositorio configurado**

Solo necesitas hacer push:

```powershell
cd "C:\xampp\htdocs\Sistema Guiargo"
git push -u origin main
```

## Paso 3: Autenticación

Cuando ejecutes `git push`, GitHub te pedirá autenticarte. Puedes usar:

1. **Personal Access Token (PAT)** (recomendado):
   - Ve a GitHub > Settings > Developer settings > Personal access tokens > Tokens (classic)
   - Genera un nuevo token con permisos `repo`
   - Usa ese token como contraseña cuando Git te lo pida

2. **GitHub CLI**:
   ```powershell
   gh auth login
   ```

## Verificación

Después de hacer push, puedes verificar en GitHub que todos los archivos se hayan subido correctamente. 

**IMPORTANTE**: El archivo `conexion.php` NO debe aparecer en GitHub (está en .gitignore por seguridad). Solo debe aparecer `conexion.php.example`.

## Para Futuros Cambios

Cada vez que hagas cambios y quieras subirlos a GitHub:

```powershell
cd "C:\xampp\htdocs\Sistema Guiargo"
git add .
git commit -m "Descripción de los cambios"
git push
```

## Notas de Seguridad

✅ **Archivos protegidos**:
- `conexion.php` está en `.gitignore` y no se subirá al repositorio
- Las credenciales de base de datos no se expondrán

✅ **Archivo de ejemplo**:
- `conexion.php.example` está en el repositorio para que otros desarrolladores sepan qué configuración necesitan

## Solución de Problemas

Si encuentras errores al hacer push:

1. **Error: remote origin already exists**
   ```powershell
   git remote remove origin
   git remote add origin https://github.com/TU_USUARIO/Sistema-Guiargo.git
   ```

2. **Error: authentication failed**
   - Verifica que estés usando un Personal Access Token válido
   - O configura GitHub CLI con `gh auth login`

3. **Error: branch main does not exist**
   ```powershell
   git branch -M main
   git push -u origin main
   ```

