[README.md](https://github.com/user-attachments/files/35007568/README_PROMAGA1.0.md)

# ProMaga 1.0

Sistema web de gestión y seguimiento de operaciones para un taller
automotriz. ProMaga centraliza el registro de vehículos y servicios, el
control de trabajos en curso, el historial de servicios, la
administración de clientes y el análisis del desempeño del taller.

## 🌐 Demo

**Aplicación desplegada:**\
\[Pegar aquí el enlace de la página desplegada\]

## 📋 Descripción del proyecto

ProMaga es una aplicación web diseñada para apoyar la operación diaria
de un taller automotriz. Permite registrar los vehículos que ingresan al
taller, documentar los servicios solicitados, dar seguimiento al avance
de las actividades y conservar un historial de los trabajos finalizados.

Además, incorpora herramientas para administrar clientes, enviar
comunicaciones mediante WhatsApp y consultar indicadores operativos
desde un panel de estadísticas.

El sistema utiliza una API desarrollada con Laravel para gestionar la
información y proporcionar los servicios que consume la interfaz
construida con React.

## ✨ Funcionalidades principales

### 1. Gestión de trabajos y vehículos

-   Registrar vehículos mediante información como marca, modelo, año y
    color.
-   Registrar los trabajos o servicios solicitados para cada vehículo.
-   Consultar los trabajos que se encuentran activos o pendientes.
-   Dividir los servicios en subtareas y actualizar su estado.
-   Registrar qué usuario o técnico realizó cada subtarea.
-   Añadir notas y observaciones a los trabajos.
-   Marcar los trabajos como finalizados y conservarlos en el historial.

### 2. Historial de servicios

-   Consultar los trabajos que ya fueron completados.
-   Buscar registros por información del vehículo.
-   Filtrar el historial por fechas, marca y modelo.
-   Consultar resultados mediante paginación.
-   Actualizar notas y administrar los registros del historial.

### 3. Administración de clientes

-   Registrar y actualizar clientes.
-   Asociar clientes con trabajos del historial.
-   Consultar información de contacto y servicios relacionados.
-   Buscar clientes por nombre o teléfono.
-   Gestionar recordatorios y mensajes de seguimiento.

### 4. Integración con WhatsApp

-   Conectar la aplicación con un servicio externo de WhatsApp.
-   Enviar recordatorios a clientes.
-   Enviar mensajes de finalización de servicio.
-   Compartir solicitudes de recomendación o reseñas en Google Maps.
-   Consultar el estado de la conexión y gestionar la desconexión.

> La disponibilidad de estas funciones depende de que el servicio
> externo de WhatsApp esté correctamente configurado.

### 5. Documentos y garantías

-   Generar documentos PDF relacionados con la atención al cliente y los
    servicios realizados.
-   Gestionar la generación de garantías desde el módulo de clientes.

### 6. Dashboard y estadísticas

-   Consultar indicadores generales del taller.
-   Visualizar tendencias de vehículos atendidos.
-   Analizar el rendimiento de los técnicos.
-   Consultar estadísticas por marca, modelo y año.
-   Revisar los trabajos más frecuentes y los tiempos de servicio.
-   Aplicar filtros por rangos de fechas.

### 7. Consulta de información vehicular

-   Consultar marcas, modelos y años disponibles mediante una fuente de
    información vehicular externa.
-   Buscar vehículos.
-   Consultar el estado del servicio de información y actualizar su
    caché.

### 8. Usuarios y autenticación

-   Iniciar y cerrar sesión.
-   Consultar la información del usuario autenticado.
-   Administrar usuarios y roles.
-   Proteger los endpoints privados de la API mediante Laravel Sanctum.

## 🔄 Funcionamiento general

El flujo de trabajo principal de ProMaga es el siguiente:

1.  **Ingreso del vehículo:** se registra la información del vehículo y
    los servicios que requiere.
2.  **Creación del trabajo:** el sistema almacena el trabajo y lo
    muestra entre los trabajos activos.
3.  **Seguimiento:** los usuarios actualizan el estado de las subtareas,
    registran avances y agregan observaciones.
4.  **Finalización:** cuando el servicio termina, el trabajo se marca
    como completado y se conserva en el historial, junto con información
    de seguimiento.
5.  **Atención al cliente:** los datos del cliente pueden asociarse con
    el servicio para facilitar recordatorios, mensajes y seguimiento
    posterior.
6.  **Análisis:** la información registrada alimenta el dashboard, que
    permite consultar estadísticas y tendencias de operación.

A nivel técnico, el frontend consume los endpoints de la API de Laravel.
El backend valida las solicitudes, aplica las reglas de negocio,
consulta o actualiza la base de datos y devuelve respuestas en formato
JSON.

## 🛠️ Tecnologías utilizadas

### Backend

  -----------------------------------------------------------------------
  Tecnología                          Uso
  ----------------------------------- -----------------------------------
  PHP 8.2 o superior                  Lenguaje principal del backend.

  Laravel 12                          Framework para rutas,
                                      controladores, modelos,
                                      validaciones y lógica de negocio.

  Laravel Sanctum                     Autenticación y protección de
                                      endpoints de la API.

  Eloquent ORM                        Acceso y administración de los
                                      datos de la aplicación.

  DomPDF (`barryvdh/laravel-dompdf`)  Generación de documentos PDF.
  -----------------------------------------------------------------------

### Frontend

  Tecnología         Uso
  ------------------ -------------------------------------------
  React 18           Construcción de la interfaz de usuario.
  JavaScript         Lógica e interacción del frontend.
  Axios              Comunicación HTTP con la API.
  Chart.js           Visualización de estadísticas y gráficas.
  react-chartjs-2    Integración de Chart.js con React.
  react-datepicker   Selección de fechas y rangos.

### Herramientas y entorno

  -----------------------------------------------------------------------
  Tecnología                          Uso
  ----------------------------------- -----------------------------------
  Composer                            Administración de dependencias de
                                      PHP.

  npm                                 Administración de dependencias de
                                      JavaScript.

  Laravel Mix 6                       Compilación y empaquetado de
                                      recursos del frontend.

  Webpack / Babel                     Procesamiento y transformación de
                                      recursos JavaScript.

  PHPUnit 11                          Ejecución de pruebas automatizadas.

  Git y GitHub                        Control de versiones y alojamiento
                                      del código fuente.
  -----------------------------------------------------------------------

### Integraciones

-   Servicio externo de WhatsApp mediante una capa de servicio propia.
-   Fuente externa de información vehicular para consultar marcas,
    modelos y años.
-   Google Maps como destino para solicitudes de reseñas de clientes.

## 🗂️ Estructura del proyecto

``` text
PROMAGA1.0/
├── app/
│   ├── Http/
│   │   └── Controllers/     # Controladores y endpoints de la aplicación
│   ├── Models/              # Modelos y relaciones de datos
│   └── Services/            # Servicios e integraciones externas
├── bootstrap/               # Inicialización de Laravel
├── config/                  # Configuración de la aplicación
├── database/
│   ├── migrations/          # Estructura y cambios de la base de datos
│   ├── seeders/             # Datos iniciales
│   └── factories/           # Fábricas para pruebas y datos
├── deployment/              # Archivos relacionados con despliegue
├── public/                  # Recursos públicos
├── resources/
│   ├── js/                  # Aplicación React
│   ├── css/                 # Estilos
│   └── views/               # Vistas Blade
├── routes/
│   ├── api.php              # Endpoints de la API
│   └── web.php              # Entrada de la aplicación web
├── storage/                 # Archivos, logs y recursos generados
├── tests/                   # Pruebas automatizadas
├── composer.json            # Dependencias PHP
├── package.json             # Dependencias JavaScript
└── webpack.mix.js           # Configuración de compilación
```

## ⚙️ Requisitos previos

Para ejecutar el proyecto en un entorno local necesitas:

-   PHP 8.2 o superior.
-   Composer.
-   Node.js y npm.
-   Un motor de base de datos compatible con la configuración de Laravel
    (el archivo de ejemplo utiliza SQLite; también contempla
    configuración para MySQL).
-   Extensiones PHP requeridas por el proyecto, entre ellas `curl`,
    `json`, `mbstring`, `mysqli`, `pdo`, `xml` y `zip`.

## 🚀 Instalación y ejecución local

### 1. Clonar el repositorio

``` bash
git clone https://github.com/gato8989/PROMAGA1.0.git
cd PROMAGA1.0
```

### 2. Instalar dependencias

``` bash
composer install
npm install
```

### 3. Configurar variables de entorno

Copia el archivo de ejemplo:

``` bash
cp .env.example .env
```

En Windows puedes copiarlo manualmente o ejecutar:

``` powershell
Copy-Item .env.example .env
```

Genera la clave de la aplicación:

``` bash
php artisan key:generate
```

### 4. Configurar la base de datos

Edita el archivo `.env` y configura la conexión que utilizarás.

El archivo `.env.example` incluye SQLite como conexión predeterminada.
Para utilizar MySQL, configura las variables correspondientes, por
ejemplo:

``` env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nombre_base_datos
DB_USERNAME=usuario
DB_PASSWORD=contraseña
```

Crea previamente la base de datos si utilizas MySQL.

Ejecuta las migraciones:

``` bash
php artisan migrate
```

### 5. Compilar los recursos del frontend

Para desarrollo:

``` bash
npm run dev
```

Para generar los recursos optimizados:

``` bash
npm run build
```

### 6. Iniciar el servidor

En otra terminal ejecuta:

``` bash
php artisan serve
```

La aplicación estará disponible normalmente en:

``` text
http://127.0.0.1:8000
```

## 🔐 Variables de entorno e integraciones

La configuración de la aplicación se administra mediante el archivo
`.env`.

Además de los parámetros básicos de Laravel y la base de datos, las
integraciones externas pueden requerir credenciales, URLs o parámetros
adicionales. Configura estos valores de acuerdo con los servicios que
tengas habilitados en tu entorno.

**Importante:** no subas el archivo `.env`, contraseñas, tokens ni
credenciales privadas al repositorio. Utiliza `.env.example` para
documentar únicamente los nombres de las variables necesarias, sin
incluir secretos reales.

## 🧪 Pruebas

El proyecto incluye PHPUnit y la configuración de pruebas de Laravel.
Para ejecutar las pruebas:

``` bash
php artisan test
```

También puedes utilizar:

``` bash
composer test
```

## 📦 Despliegue

El repositorio incluye archivos relacionados con el despliegue. Para
publicar la aplicación, configura en tu proveedor de hosting:

-   La versión de PHP compatible y las extensiones requeridas.
-   Las dependencias de Composer y npm.
-   Las variables de entorno de producción.
-   La conexión a la base de datos.
-   La compilación de los recursos del frontend.
-   El directorio público de Laravel como raíz web.
-   Las integraciones externas que utilice la aplicación.

Antes de poner el sistema en producción, utiliza `APP_ENV=production`,
desactiva el modo debug (`APP_DEBUG=false`) y configura correctamente
las credenciales y permisos.

## 👨‍💻 Autor y repositorio

**Proyecto:** ProMaga 1.0\
**Repositorio:**
[github.com/gato8989/PROMAGA1.0](https://github.com/gato8989/PROMAGA1.0)

------------------------------------------------------------------------

Desarrollado para facilitar la administración, el seguimiento y el
análisis de las operaciones de un taller automotriz.
