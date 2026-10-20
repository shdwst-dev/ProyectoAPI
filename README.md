# TechStore API — Proyecto de Seguridad Informática (Symfony + JWT)

Proyecto de **Tienda en Línea con API RESTful y Cliente Web** construido en **Symfony 8.1** y **PostgreSQL**, con autenticación basada en **JSON Web Tokens (JWT - HS256)**, control de acceso por roles (**RBAC**) y mitigación de vulnerabilidades del **OWASP API Security Top 10**.

---

## 📋 Índice

1. [Paso 1: Verificar si ya tienes las herramientas instaladas](#-paso-1-verificar-si-ya-tienes-las-herramientas-instaladas)
2. [Paso 2: Instalación desde cero (Mac ARM y PC Intel x86)](#-paso-2-instalación-desde-cero-si-no-tienes-nada-instalado)
3. [Paso 3: Configurar y ejecutar el proyecto](#-paso-3-configurar-y-ejecutar-el-proyecto)
4. [Alternativa con SQLite (Entorno local sin PostgreSQL)](#-alternativa-con-sqlite-entorno-local-sin-postgresql)
5. [Diagrama de Carpetas y qué hace cada archivo](#-diagrama-de-carpetas-y-qué-hace-cada-archivo)
6. [Arquitectura y Flujo de Seguridad (Cómo funciona por dentro)](#-arquitectura-y-flujo-de-seguridad)
7. [Cuentas de Prueba y Endpoints de la API](#-cuentas-de-prueba-y-endpoints-de-la-api)
8. [Ejemplos de pruebas en terminal (`curl` / Postman)](#-ejemplos-de-pruebas-rápidas-con-curl)
9. [Solución de errores comunes (Troubleshooting)](#-solución-de-errores-comunes-troubleshooting)

---

## 🔍 Paso 1: Verificar si ya tienes las herramientas instaladas

Abre tu terminal (**Terminal** en Mac/Linux, o **PowerShell** en Windows) y ejecuta estos comandos uno por uno para revisar qué tienes instalado:

```bash
# 1. Verificar PHP (se requiere PHP 8.4 o superior)
php -v

# 2. Verificar que PHP tenga activados los drivers de PostgreSQL (pdo_pgsql y pgsql)
php -m | grep -i pgsql

# 3. Verificar Composer (gestor de paquetes de PHP)
composer --version

# 4. Verificar PostgreSQL
psql --version

# 5. (Opcional) Verificar Symfony CLI
symfony -V
```

- Si `php -v` muestra **8.4+** o **8.5+**, `composer --version` responde y `psql --version` responde, **sáltate directo al [Paso 3](#-paso-3-configurar-y-ejecutar-el-proyecto)**.
- Si alguno marca *"command not found"* o no tienes nada instalado, sigue el **Paso 2** según tu sistema operativo.

---

## 🛠️ Paso 2: Instalación desde cero (si no tienes nada instalado)

### 🍎 Opción A: Mac con chip Apple Silicon (M1 / M2 / M3 / M4 - ARM)

En Mac, la forma más limpia de instalar todo es con **Homebrew**.

1. **Instalar Homebrew** (si aún no lo tienes):
   ```bash
   /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
   ```
   > **Nota importante en Mac ARM:** Al terminar de instalar Homebrew, ejecuta los 2 comandos que la misma terminal te indica para agregar `/opt/homebrew/bin` a tu `PATH` (usualmente `eval "$(/opt/homebrew/bin/brew shellenv)"`).

2. **Instalar PHP, Composer, PostgreSQL y Symfony CLI** (Homebrew ya incluye `pdo_pgsql` activado en PHP por defecto):
   ```bash
   brew update
   brew install php composer postgresql@18 symfony-cli/tap/symfony-cli
   ```

3. **Iniciar el servicio de PostgreSQL y agregarlo al PATH:**
   ```bash
   brew services start postgresql@18
   echo 'export PATH="/opt/homebrew/opt/postgresql@18/bin:$PATH"' >> ~/.zshrc
   source ~/.zshrc
   ```

4. **Crear el usuario `root` con contraseña `toor` en PostgreSQL** (para que no tengas que cambiar el archivo `.env` del proyecto):
   ```bash
   createuser -s postgres 2>/dev/null || true
   psql postgres -c "CREATE ROLE root WITH SUPERUSER LOGIN PASSWORD 'toor';"
   ```

---

### 💻 Opción B: PC Intel x86 con Windows (10 / 11)

En Windows puedes instalar todo desde **PowerShell (como Administrador)** usando `winget`:

1. **Instalar PHP 8.4+, Composer y PostgreSQL:**
   ```powershell
   winget install PHP.PHP.8.4
   winget install Composer.Composer
   winget install PostgreSQL.PostgreSQL.17
   ```
   *(Durante la instalación de PostgreSQL te pedirá una contraseña para el usuario `postgres`; puedes poner `toor` o la que prefieras).*

2. **Habilitar la extensión de PostgreSQL en PHP (Muy importante en Windows):**
   - Cierra y vuelve a abrir PowerShell, y ejecuta:
     ```powershell
     php --ini
     ```
   - Abre con el Bloc de notas o VS Code el archivo que aparece en `Loaded Configuration File` (ej. `C:\Program Files\PHP\...\php.ini`).
   - Busca las siguientes líneas y **quítales el punto y coma (`;`) del inicio**:
     ```ini
     extension=pdo_pgsql
     extension=pgsql
     extension=openssl
     extension=mbstring
     extension=intl
     ```
   - Guarda el archivo `php.ini`.

---

### 🐧 Opción C: PC Intel x86 con Linux (Ubuntu / Debian / Mint / Arch)

- **En Ubuntu / Debian / Linux Mint:**
  ```bash
  sudo apt update
  sudo apt install -y software-properties-common
  sudo add-apt-repository ppa:ondrej/php -y
  sudo apt update
  sudo apt install -y php8.4 php8.4-cli php8.4-pgsql php8.4-xml php8.4-mbstring php8.4-intl php8.4-curl composer postgresql postgresql-contrib
  sudo systemctl enable --now postgresql
  sudo -u postgres psql -c "CREATE ROLE root WITH SUPERUSER LOGIN PASSWORD 'toor';"
  ```

- **En Arch Linux / Manjaro:**
  ```bash
  sudo pacman -Syu php php-pgsql composer postgresql
  # Descomentar extension=pdo_pgsql y extension=pgsql en /etc/php/php.ini
  sudo sed -i 's/;extension=pdo_pgsql/extension=pdo_pgsql/g' /etc/php/php.ini
  sudo sed -i 's/;extension=pgsql/extension=pgsql/g' /etc/php/php.ini
  sudo systemctl enable --now postgresql
  sudo -u postgres psql -c "CREATE ROLE root WITH SUPERUSER LOGIN PASSWORD 'toor';"
  ```

---

## 🚀 Paso 3: Configurar y ejecutar el proyecto

Una vez dentro de la carpeta del proyecto (`ProyectoAPI`), sigue estos 5 pasos:

### 1. Instalar las dependencias de PHP
```bash
composer install
```

### 2. Verificar la conexión a la Base de Datos en `.env`
Abre el archivo `.env` en la raíz del proyecto y revisa la última línea:
```dotenv
DATABASE_URL="postgresql://root:toor@127.0.0.1:5432/bd_tienda_api?serverVersion=18&charset=utf8"
```
> Si en tu computadora tu usuario de PostgreSQL es `postgres` en lugar de `root`, cámbialo a `postgresql://postgres:TU_PASSWORD@127.0.0.1:5432/bd_tienda_api?serverVersion=18&charset=utf8`.

### 3. Crear la base de datos y las tablas automáticamente
```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:update --force
```

### 4. Cargar los datos iniciales (Usuarios, Productos y Pedidos de prueba)
```bash
php bin/console doctrine:fixtures:load --no-interaction
```

### 5. Levantar el servidor
Puedes iniciarlo con cualquiera de estos dos comandos:
```bash
php -S 127.0.0.1:8000 -t public
# o si instalaste Symfony CLI:
symfony server:start
```

Ahora abre en tu navegador:
👉 **[http://127.0.0.1:8000](http://127.0.0.1:8000)**

---

## 🛟 Alternativa con SQLite (Entorno local sin PostgreSQL)

Si se desea ejecutar el proyecto sin un servidor PostgreSQL activo, Symfony permite utilizar **SQLite** mediante un archivo local:

1. Asegúrate de tener activa la extensión `pdo_sqlite` en tu `php.ini`.
2. En el archivo `.env`, comenta la línea de PostgreSQL y descomenta la de SQLite:
   ```dotenv
   DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_dev.db"
   ```
3. Ejecuta:
   ```bash
   php bin/console doctrine:schema:update --force
   php bin/console doctrine:fixtures:load --no-interaction
   ```

---

## 📂 Diagrama de Carpetas y qué hace cada archivo

A continuación se muestra la estructura del proyecto, **por qué está organizada así** (siguiendo el estándar de arquitectura de Symfony) y **qué hace cada archivo clave**:

```text
ProyectoAPI/
├── bin/
│   └── console                          # Ejecutable CLI de Symfony (comandos de BD, caché, rutas, etc.)
│
├── config/                              # Configuraciones globales de los paquetes (Bundles)
│   ├── bundles.php                      # Lista de paquetes activados en Symfony (Doctrine, Security, CORS, etc.)
│   ├── routes.yaml                      # Indica a Symfony que lea las rutas desde los atributos #[Route] de src/Controller/
│   ├── services.yaml                    # Configuración de inyección de dependencias (Autowiring automático)
│   └── packages/
│       ├── doctrine.yaml                # Configuración del ORM Doctrine y conexión a PostgreSQL
│       ├── nelmio_cors.yaml             # Configuración de cabeceras CORS para permitir peticiones HTTP a la API
│       ├── security.yaml                # ⭐ ARCHIVO CLAVE: Define el firewall stateless JWT, el hasher Bcrypt y permisos por rol
│       └── validator.yaml               # Configuración del componente de validación de datos de entrada
│
├── public/                              # Única carpeta expuesta al navegador (DocumentRoot del servidor web)
│   ├── index.php                        # Front Controller de Symfony: recibe todas las peticiones HTTP de la API
│   ├── client.html                      # Estructura HTML del cliente web de la tienda (Login, Catálogo, Carrito, Admin)
│   └── assets/
│       ├── styles.css                   # Estilos CSS limpios de la tienda en línea
│       └── app.js                       # Lógica JavaScript que consume la API con fetch() y envía el token JWT Bearer
│
├── src/                                 # Código fuente PHP de nuestra aplicación (Namespace App\)
│   ├── Kernel.php                       # Núcleo de Symfony que carga los bundles y la configuración
│   │
│   ├── Controller/                      # Capa de Controladores (Reciben peticiones HTTP y devuelven respuestas)
│   │   ├── FrontendController.php       # Sirve la interfaz web (public/client.html) al entrar a la raíz "/"
│   │   └── Api/                         # Controladores de la API RESTful (todos devuelven JsonResponse)
│   │       ├── AuthController.php       # Endpoints /api/login, /api/register, /api/me y /api/status
│   │       ├── ProductController.php    # CRUD de productos (/api/products): lectura para clientes, escritura solo ROLE_ADMIN
│   │       ├── OrderController.php      # Creación y consulta de pedidos (/api/orders) con protección BOLA/IDOR
│   │       └── UserController.php       # Listado de usuarios (/api/users) restringido exclusivamente a ROLE_ADMIN
│   │
│   ├── Entity/                          # Modelos de Base de Datos (Tablas mapeadas con Doctrine ORM)
│   │   ├── User.php                     # Tabla "users": id, email, name, roles, password (hash Bcrypt con salt integrado)
│   │   ├── Product.php                  # Tabla "products": id, name, description, price, stock, category, active
│   │   ├── Order.php                    # Tabla "orders": cabecera del pedido (cliente, dirección, estado, total)
│   │   └── OrderItem.php                # Tabla "order_items": detalle de cada producto comprado y su precio unitario
│   │
│   ├── Repository/                      # Capa de consultas a la Base de Datos (previene Inyección SQL)
│   │   ├── UserRepository.php           # Consultas de usuarios y actualización de hashes de contraseñas
│   │   ├── ProductRepository.php        # Búsqueda parametrizada por nombre y categoría (Prepared Statements)
│   │   ├── OrderRepository.php          # Consultas de pedidos por cliente
│   │   └── OrderItemRepository.php      # Consultas de ítems de pedidos
│   │
│   ├── Security/                        # Capa de Seguridad y Autenticación JWT
│   │   ├── JwtService.php               # ⭐ Crea y valida tokens JWT (RFC 7519) firmados con HMAC-SHA256 (HS256)
│   │   ├── JwtAccessTokenHandler.php    # Intercepta la cabecera "Authorization: Bearer <token>" y autentica al usuario
│   │   └── ApiSecurityHandler.php       # Devuelve errores estandarizados en JSON para códigos 401 y 403
│   │
│   └── DataFixtures/                    # Semillas de datos iniciales para pruebas
│       └── AppFixtures.php              # Crea los 3 usuarios de prueba, 6 productos y 2 pedidos iniciales
│
├── var/                                 # Archivos temporales generados por Symfony (caché y logs en var/log/dev.log)
├── vendor/                              # Librerías descargadas por Composer (no se edita manualmente)
├── .env                                 # Variables de entorno: conexión a BD (DATABASE_URL) y llave secreta JWT (JWT_SECRET)
└── composer.json                        # Lista oficial de dependencias y versión requerida de PHP
```

### ¿Por qué están separadas así las carpetas?
1. **`public/` separada de `src/` y `config/`:** Por seguridad, el servidor web únicamente expone la carpeta `public/`. Así ningún atacante puede descargar el código fuente PHP (`src/`) ni leer las contraseñas de configuración (`.env` o `config/`) desde el navegador.
2. **`Entity/` separada de `Repository/`:** Las entidades (`Entity`) representan la estructura de las tablas y la serialización segura (`toArray()`), mientras que los repositorios (`Repository`) concentran las consultas SQL parametrizadas.
3. **`Security/` separada de `Controller/`:** El firewall de Symfony valida el token JWT en `Security/JwtAccessTokenHandler.php` **antes** de que la petición llegue al controlador. Si el token es falso o expiró, la petición se rechaza en la entrada con `401 Unauthorized`.

---

## 🛡️ Arquitectura y Flujo de Seguridad

### 1. ¿Cómo viaja una petición autenticada?
```mermaid
sequenceDiagram
    participant C as Cliente Web (app.js)
    participant F as Firewall Symfony (JwtAccessTokenHandler)
    participant J as JwtService (HS256)
    participant K as Controlador API
    participant DB as PostgreSQL (Doctrine)

    C->>K: 1. POST /api/login (email, password)
    K->>DB: 2. Busca usuario y verifica hash Bcrypt
    K->>J: 3. Genera JWT firmado con JWT_SECRET
    K-->>C: 4. Devuelve { token, user }
    Note over C: Guarda el JWT y lo envía en cada petición

    C->>F: 5. GET /api/orders (Header: Authorization: Bearer <JWT>)
    F->>J: 6. Verifica firma HMAC-SHA256 y expiración (exp)
    J-->>F: 7. Payload válido (sub: email, roles)
    F->>K: 8. Permite paso al Controlador con el Usuario autenticado
    K->>DB: 9. Consulta solo los pedidos de ese usuario
    K-->>C: 10. Respuesta JSON (200 OK)
```

### 2. Controles de Seguridad Implementados

| Vulnerabilidad / Concepto | Cómo se protege en este proyecto | Archivo donde está implementado |
| :--- | :--- | :--- |
| **Hashing de Contraseñas y Salt** | Se utiliza **Bcrypt** (`$2y$13$...`) con factor de costo 13. El algoritmo genera un **salt aleatorio de 22 caracteres** único por usuario y lo guarda embebido en los primeros 29 caracteres de la columna `password`. | `config/packages/security.yaml` y `src/Controller/Api/AuthController.php` |
| **Autenticación Stateless (JWT)** | Los tokens se firman con **HMAC-SHA256 (`HS256`)** usando `JWT_SECRET`. Se valida explícitamente que el header sea `HS256` (evita ataque `alg: none`), se verifica `exp` y se compara la firma con `hash_equals()` (previene *Timing Attacks*). | `src/Security/JwtService.php` |
| **OWASP API1: BOLA / IDOR** *(Broken Object Level Authorization)* | En `GET /api/orders/{id}`, se valida que `$order->getCustomer()->getId() === $user->getId()` (a menos que sea `ROLE_ADMIN`). Así el Cliente 1 no puede espiar el pedido del Cliente 2 cambiando el ID en la URL. | `src/Controller/Api/OrderController.php` (método `show`) |
| **OWASP API3: Mass Assignment y Exposición de Datos** | 1) En `/api/register` se ignora cualquier campo `roles` enviado en el JSON y se fuerza `['ROLE_USER']`.<br>2) En `User::toArray()` jamás se devuelve el campo `$password`. | `src/Controller/Api/AuthController.php` y `src/Entity/User.php` |
| **OWASP API5: Control de Acceso por Roles (RBAC)** | Las rutas de creación/edición/borrado de productos y listado de usuarios exigen `ROLE_ADMIN` tanto en el firewall como con el atributo `#[IsGranted('ROLE_ADMIN')]`. | `config/packages/security.yaml` y `src/Controller/Api/ProductController.php` |
| **Integridad de Precios y Stock** | Al crear un pedido (`POST /api/orders`), el backend solo recibe `productId` y `quantity`. El precio se toma directamente desde PostgreSQL para impedir que un cliente modifique el precio en el JSON. | `src/Controller/Api/OrderController.php` (método `create`) |
| **Inyección SQL (SQLi)** | Todas las consultas usan *Prepared Statements* con parámetros enlazados (`:search`, `:category`) a través de Doctrine ORM. | `src/Repository/ProductRepository.php` |

---

## 🔑 Cuentas de Prueba y Endpoints de la API

### Usuarios pre-cargados (creados por `AppFixtures.php`)

| Rol | Correo | Contraseña | Descripción |
| :--- | :--- | :--- | :--- |
| **Administrador** (`ROLE_ADMIN`) | `admin@tienda.com` | `admin123` | Tiene acceso al menú **Administración** (gestionar productos, cambiar estados de todos los pedidos y ver usuarios). |
| **Cliente 1** (`ROLE_USER`) | `ana@cliente.com` | `cliente123` | Tiene asignado el **Pedido #1**. Solo ve el catálogo, su carrito y sus propios pedidos. |
| **Cliente 2** (`ROLE_USER`) | `carlos@cliente.com` | `cliente123` | Tiene asignado el **Pedido #2**. |

### Tabla de Endpoints

| Método | Endpoint | Permiso requerido | Descripción |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/status` | Público | Verifica que la API esté en línea. |
| `POST` | `/api/login` | Público | Recibe `{ "email", "password" }` y devuelve el token JWT. |
| `POST` | `/api/register` | Público | Registra un nuevo cliente `{ "name", "email", "password" }`. |
| `GET` | `/api/me` | `ROLE_USER` (JWT) | Devuelve los datos del usuario dueño del token actual. |
| `GET` | `/api/products` | `ROLE_USER` (JWT) | Lista los productos activos (acepta `?search=` y `?category=`). |
| `GET` | `/api/products/{id}` | `ROLE_USER` (JWT) | Muestra el detalle de un producto. |
| `POST` | `/api/products` | `ROLE_ADMIN` (JWT) | Crea un producto nuevo. |
| `PUT` | `/api/products/{id}` | `ROLE_ADMIN` (JWT) | Edita un producto existente. |
| `DELETE` | `/api/products/{id}` | `ROLE_ADMIN` (JWT) | Elimina (desactiva) un producto. |
| `GET` | `/api/orders` | `ROLE_USER` (JWT) | Lista los pedidos del cliente autenticado (o todos si es Admin). |
| `GET` | `/api/orders/{id}` | `ROLE_USER` (JWT) | Detalle de un pedido (protegido contra IDOR/BOLA). |
| `POST` | `/api/orders` | `ROLE_USER` (JWT) | Crea un nuevo pedido y descuenta stock. |
| `PATCH` | `/api/orders/{id}/status` | `ROLE_ADMIN` (JWT) | Cambia el estado de un pedido (`PENDING`, `PAID`, `SHIPPED`, `CANCELLED`). |
| `GET` | `/api/users` | `ROLE_ADMIN` (JWT) | Lista los usuarios registrados sin exponer contraseñas. |

---

## 🧪 Ejemplos de pruebas rápidas con `curl`

Comandos para probar los endpoints de la API directamente desde la terminal (o para importar en clientes REST como Postman o Insomnia):

```bash
# 1. Intentar ver productos sin iniciar sesión (Devuelve 401 Unauthorized)
curl -i http://127.0.0.1:8000/api/products

# 2. Iniciar sesión como Ana (Cliente 1) y obtener su Token JWT
curl -s -X POST http://127.0.0.1:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ana@cliente.com","password":"cliente123"}'

# 3. Guardar el token en una variable de terminal para probar rutas protegidas
TOKEN=$(curl -s -X POST http://127.0.0.1:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ana@cliente.com","password":"cliente123"}' | php -r 'echo json_decode(fgets(STDIN), true)["token"];')

# 4. Consultar el catálogo enviando el Token JWT (Devuelve 200 OK)
curl -s http://127.0.0.1:8000/api/products \
  -H "Authorization: Bearer $TOKEN"

# 5. Probar protección BOLA/IDOR: Ana intenta ver el Pedido #2 que es de Carlos (Devuelve 403 Forbidden)
curl -i http://127.0.0.1:8000/api/orders/2 \
  -H "Authorization: Bearer $TOKEN"

# 6. Probar protección de Roles: Ana intenta ver la lista de usuarios de Admin (Devuelve 403 Forbidden)
curl -i http://127.0.0.1:8000/api/users \
  -H "Authorization: Bearer $TOKEN"
```

---

## 🔧 Solución de errores comunes (Troubleshooting)

1. **`could not find driver` al ejecutar comandos de Doctrine:**
   - Significa que a tu PHP le falta activar la extensión de PostgreSQL (`pdo_pgsql`).
   - Ejecuta `php --ini` para ver la ruta de tu `php.ini`, ábrelo y quita el `;` de `;extension=pdo_pgsql` y `;extension=pgsql`.
2. **`SQLSTATE[08006] [7] connection to server at "127.0.0.1", port 5432 failed`:**
   - El servicio de PostgreSQL está apagado o la contraseña en `.env` no coincide con la de tu PostgreSQL local.
   - En Mac: `brew services start postgresql@18`
   - En Linux: `sudo systemctl start postgresql`
   - En Windows: Abre la aplicación **Servicios (`services.msc`)** e inicia el servicio `postgresql-x64-17`.
3. **`YourComposerDependencies` / Error de versión de PHP al hacer `composer install`:**
   - Si el entorno cuenta con **PHP 8.2 u 8.3** en lugar de **8.4+**, se puede ejecutar `composer install --ignore-platform-reqs` o actualizar PHP con `brew upgrade php`.
