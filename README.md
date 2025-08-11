# 🏔️ Cusco Back - API REST para Gestión de Ventas

Sistema backend desarrollado en Laravel 11 para la gestión de ventas, inventario, transacciones y control de roles en el negocio de Luis en Cusco.

## 📋 **Requisitos del Sistema**

- **PHP**: >= 8.2
- **Composer**: >= 2.0
- **MySQL**: >= 8.0 (o MariaDB >= 10.3)
- **Node.js**: >= 18.0 (para assets)
- **Extensiones PHP requeridas**:
  - OpenSSL
  - PDO
  - Mbstring
  - Tokenizer
  - XML
  - Ctype
  - JSON
  - BCMath
  - Fileinfo
  - GD (para procesamiento de imágenes)

## 🚀 **Instalación y Configuración**

### **1. Clonar el Repositorio**

```bash
git clone [URL_DEL_REPOSITORIO] cusco-back
cd cusco-back
```

### **2. Instalar Dependencias**

```bash
# Instalar dependencias de PHP
composer install

# Instalar dependencias de Node.js (si las hay)
npm install
```

### **3. Configuración del Entorno**

```bash
# Copiar archivo de configuración
cp .env.example .env

# Generar clave de aplicación
php artisan key:generate
```

### **4. Configurar Base de Datos**

Edita el archivo `.env` con los datos de tu base de datos:

```env
# Configuración de la aplicación
APP_NAME="Cusco Back API"
APP_ENV=local
APP_KEY=base64:[CLAVE_GENERADA_AUTOMATICAMENTE]
APP_DEBUG=true
APP_TIMEZONE=America/Lima
APP_URL=http://localhost:8000

# Configuración de base de datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cusco_back
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña

# Configuración de autenticación
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000

# Configuración de archivos
FILESYSTEM_DISK=public

# Configuración de logging
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug
```

### **5. Crear Base de Datos**

```sql
-- Conectar a MySQL y crear la base de datos
CREATE DATABASE cusco_back CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### **6. Ejecutar Migraciones y Seeders**

```bash
# Ejecutar migraciones
php artisan migrate

# Ejecutar seeders para datos iniciales
php artisan db:seed

# O ejecutar todo en un comando
php artisan migrate:fresh --seed
```

### **7. Configurar Storage**

```bash
# Crear enlace simbólico para storage público
php artisan storage:link

# Verificar que se creó correctamente
ls -la public/storage
```

### **8. Limpiar y Optimizar**

```bash
# Limpiar caché de configuración
php artisan config:clear

# Limpiar caché de rutas
php artisan route:clear

# Optimizar para producción (opcional)
php artisan config:cache
php artisan route:cache
```

## 🔧 **Ejecutar la Aplicación**

### **Desarrollo Local**

```bash
# Iniciar servidor de desarrollo
php artisan serve

# La aplicación estará disponible en:
# http://localhost:8000
```

### **Con Puerto Personalizado**

```bash
# Usar puerto específico
php artisan serve --port=8080

# Actualizar APP_URL en .env si cambias el puerto
APP_URL=http://localhost:8080
```

## 📊 **Verificar Instalación**

### **1. Probar API de Salud**

```bash
# Verificar que la API responde
curl http://localhost:8000/api/health

# Respuesta esperada:
{
  "status": "OK",
  "timestamp": "2025-08-11T20:45:00.000000Z"
}
```

### **2. Verificar Base de Datos**

```bash
# Listar tablas creadas
php artisan tinker
>>> \DB::select('SHOW TABLES');
```

### **3. Verificar Seeders**

```bash
# Verificar usuarios creados
php artisan tinker
>>> App\Models\User::with('role')->get(['id', 'name', 'email', 'role_id']);
```

## 👤 **Usuarios por Defecto**

Después de ejecutar los seeders, tendrás estos usuarios disponibles:

| Email | Contraseña | Rol | Descripción |
|-------|------------|-----|-------------|
| `admin@cusco.com` | `admin123` | Administrador | Acceso completo al sistema |
| `vendedor@cusco.com` | `vendedor123` | Vendedor | Gestión de ventas y su zona |
| `agente@cusco.com` | `agente123` | Agente | Operaciones básicas |

## 🔐 **Autenticación API**

### **Login**

```bash
# Obtener token de autenticación
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@cusco.com",
    "password": "admin123"
  }'
```

### **Usar Token**

```bash
# Usar token en requests autenticados
curl -X GET http://localhost:8000/api/v1/users \
  -H "Authorization: Bearer TU_TOKEN_AQUI" \
  -H "Accept: application/json"
```

## 📁 **Estructura del Proyecto**

```
cusco-back/
├── app/
│   ├── Attributes/          # Atributos PHP (Role)
│   ├── DTOs/               # Data Transfer Objects
│   ├── Http/
│   │   ├── Controllers/    # Controladores API
│   │   ├── Middleware/     # Middleware personalizado
│   │   └── Requests/       # Form Requests
│   ├── Helpers/            # Clases Helper
│   ├── Mappers/            # Mappers (Model <-> DTO)
│   ├── Models/             # Modelos Eloquent
│   ├── Repositories/       # Repositorios
│   └── Services/           # Servicios de negocio
├── database/
│   ├── migrations/         # Migraciones de BD
│   └── seeders/           # Datos iniciales
├── routes/
│   └── api/v1/            # Rutas de API v1
├── storage/
│   └── app/public/        # Archivos públicos
└── tests/                 # Tests automatizados
```

## 🧪 **Testing**

```bash
# Ejecutar tests
php artisan test

# Ejecutar tests con cobertura
php artisan test --coverage

# Ejecutar test específico
php artisan test --filter UserTest
```

## 🚀 **Deployment en Producción**

### **1. Configuración de Producción**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

# Configurar base de datos de producción
DB_CONNECTION=mysql
DB_HOST=tu_host_produccion
DB_PORT=3306
DB_DATABASE=cusco_back_prod
DB_USERNAME=usuario_prod
DB_PASSWORD=contraseña_segura
```

### **2. Optimizaciones**

```bash
# Instalar dependencias sin dev
composer install --no-dev --optimize-autoloader

# Cachear configuración
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimizar autoloader
composer dump-autoload --optimize
```

### **3. Permisos de Servidor**

```bash
# Dar permisos de escritura
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## 🔧 **Comandos Útiles**

```bash
# Limpiar todos los cachés
php artisan optimize:clear

# Rehacer migraciones y seeders
php artisan migrate:fresh --seed

# Generar nuevos datos de prueba
php artisan db:seed --class=UserSeeder

# Ver rutas disponibles
php artisan route:list

# Ver configuración actual
php artisan config:show database

# Generar reporte de rutas
php artisan route:list --path=api
```

## 📱 **Endpoints Principales**

### **Autenticación**
- `POST /api/v1/auth/login` - Iniciar sesión
- `POST /api/v1/auth/logout` - Cerrar sesión
- `GET /api/v1/auth/profile` - Perfil del usuario

### **Usuarios** (Requiere autenticación)
- `GET /api/v1/users` - Listar usuarios
- `POST /api/v1/users` - Crear usuario
- `GET /api/v1/users/{id}` - Ver usuario
- `PUT /api/v1/users/{id}` - Actualizar usuario

### **Productos**
- `GET /api/v1/products` - Listar productos
- `POST /api/v1/products` - Crear producto
- `GET /api/v1/products/{id}` - Ver producto

### **Transacciones**
- `GET /api/v1/transactions` - Listar transacciones
- `POST /api/v1/transactions` - Crear transacción
- `POST /api/v1/transactions/{id}/payments` - Agregar pago

## ❗ **Troubleshooting**

### **Error: Base de datos no conecta**
```bash
# Verificar configuración
php artisan config:show database

# Probar conexión
php artisan tinker
>>> DB::connection()->getPdo();
```

### **Error: Storage no funciona**
```bash
# Recrear enlace simbólico
rm public/storage
php artisan storage:link

# Verificar permisos
ls -la storage/app/public
```

### **Error: Sanctum no funciona**
```bash
# Verificar middleware
php artisan route:list --middleware=auth:sanctum

# Limpiar caché de configuración
php artisan config:clear
```

### **Error: Migraciones fallan**
```bash
# Ver estado de migraciones
php artisan migrate:status

# Rollback y volver a migrar
php artisan migrate:rollback
php artisan migrate
```

## 📞 **Soporte**

Para reportar bugs o solicitar features, crear un issue en el repositorio o contactar al equipo de desarrollo.

## 📄 **Licencia**

Este proyecto es propiedad privada de Luis - Cusco. Todos los derechos reservados.