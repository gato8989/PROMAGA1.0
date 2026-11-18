# ProMaga 1.0

Sistema web de gestión y seguimiento de operaciones para un taller
automotriz. ProMaga centraliza el registro de vehículos y servicios, el
control de trabajos en curso, el historial de servicios, la
administración de clientes y el análisis del desempeño del taller.

## Demo

**Aplicación desplegada:**\
\[https://promaga.up.railway.app/\]

## Descripción del proyecto

Es una aplicación web diseñada para apoyar la operación diaria
de un taller automotriz. Permite registrar los vehículos que ingresan al
taller, documentar los servicios solicitados, dar seguimiento al avance
de las actividades y conservar un historial de los trabajos finalizados.

Además, incorpora herramientas para administrar clientes, enviar
comunicaciones mediante WhatsApp y consultar indicadores operativos
desde un panel de estadísticas.

El sistema utiliza una API desarrollada con Laravel para gestionar la
información y proporcionar los servicios que consume la interfaz
construida con React.

## Funcionalidades principales

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
    información vehicular externa. (NHTSA Vehicle API)
-   Buscar vehículos.

### 8. Usuarios y autenticación

-   Iniciar y cerrar sesión.
-   Consultar la información del usuario autenticado.
-   Administrar usuarios y roles.
-   Proteger los endpoints privados de la API mediante Laravel Sanctum.

## Tecnologías utilizadas

### Backend

  -----------------------------------------------------------------------
  Tecnología                          Uso
  ----------------------------------- -----------------------------------
  PHP                                 Lenguaje principal del backend.

  Laravel 12                          Framework para rutas,
                                      controladores, modelos,
                                      validaciones y lógica de negocio.

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

  Git y GitHub                        Control de versiones y alojamiento
                                      del código fuente.
  -----------------------------------------------------------------------

## Estructura del proyecto

``` text
PROMAGA1.0/
├── app/
│   ├── Http/
│   │   └── Controllers/     # Controladores y endpoints de la aplicación
│   ├── Models/              # Modelos y relaciones de datos
│   └── Services/            # Servicios e integraciones externas
├── bootstrap/               
├── config/                  # Configuración de la aplicación
├── database/
│   ├── migrations/          # Estructura y cambios de la base de datos
│   ├── seeders/             # Datos iniciales
│   └── factories/          
├── deployment/              
├── public/                  
├── resources/
│   ├── js/                  # Aplicación React
│   ├── css/                 # Estilos
│   └── views/               # Vistas Blade
├── routes/
│   ├── api.php              # Endpoints de la API
│   └── web.php              # Entrada de la aplicación web
├── storage/                 
├── tests/                   
├── composer.json            # Dependencias PHP
├── package.json             # Dependencias JavaScript
└── webpack.mix.js          
```

## Autor y repositorio

**Proyecto:** ProMaga 1.0\
**Repositorio:**
[github.com/gato8989/PROMAGA1.0](https://github.com/gato8989/PROMAGA1.0)
   Diego Ivan Orozco Gallarza

------------------------------------------------------------------------

Desarrollado para facilitar la administración, el seguimiento y el
análisis de las operaciones de un taller automotriz.
