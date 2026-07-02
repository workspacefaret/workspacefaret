# Workspace Faret

Workspace Faret es una plataforma web modular desarrollada en PHP para centralizar herramientas internas de operación, datos, recursos humanos y formularios corporativos.

El proyecto está organizado por módulos funcionales, permitiendo mantener separadas las distintas áreas del sistema y facilitar su mantenimiento, evolución y despliegue.

## Características principales

- Estructura modular por áreas de negocio.
- Interfaz web basada en PHP, CSS y JavaScript, sin framework ni build step.
- Separación de componentes comunes mediante carpetas de configuración, servicios, layouts e includes.
- Integración con APIs REST corporativas (Guardias, Mejora Continua, Formularios).
- Organización simple y directa, pensada para mantenimiento práctico.

## Estructura general

```text
workspacefaret/
├── assets/              # Recursos estáticos: estilos, imágenes, video y scripts
├── config/              # Constantes de configuración (endpoints de API)
├── includes/            # Componentes PHP reutilizables (sidebar, topbar)
├── layouts/             # Plantilla visual común de la aplicación (app.php)
├── modules/              # Módulos funcionales del sistema
│   ├── welcome/          # Pantalla de bienvenida / acceso al sistema
│   ├── operacion/        # Hub de operación (logística, desarrollo gráfico)
│   ├── rrhh/              # Recursos Humanos (guardias: registros y usuarios)
│   ├── datos/             # Centro de Control: dashboard, reportes, exportaciones
│   ├── formularios/       # Formularios corporativos (desarrollo gráfico, admin)
│   └── en-proceso/        # Placeholder genérico para módulos aún no implementados
├── services/             # Servicios y lógica compartida (cliente de API)
├── exports/              # Generación de reportes descargables (Excel)
└── index.php             # Punto de entrada principal
```

## Módulos disponibles

### Operación

Hub de acceso a las áreas operativas: Logística (formularios externos en `solicitudes.faret.cl`) y Desarrollo Gráfico (formularios de solicitud y panel administrativo).

### RRHH

Gestión de personal de guardias: registros de recorridos y puntos de control, y administración de usuarios del sistema Guardias.

### Datos (Centro de Control)

Visualización, reportes y exportaciones de información operacional: dashboard, guardias, mejora continua (no conformidades) y exportaciones a Excel.

### Formularios

Formularios corporativos conectados a APIs REST propias. Actualmente incluye el área de **Desarrollo Gráfico**: ingreso de solicitudes, panel administrativo con filtros y KPIs, detalle de solicitud con historial y cambio de estado.

### Welcome

Pantalla inicial de acceso al entorno de trabajo.

## Arquitectura de integración con APIs

El proyecto usa dos patrones de integración según el módulo:

- **Proxy server-side** (mayoría de los módulos): las páginas PHP consultan las APIs a través de `services/ApiClient.php`, que centraliza las llamadas cURL y devuelve una respuesta uniforme (`ok`, `status`, `error`, `data`).
- **Consumo directo desde el cliente** (`modules/formularios/desarrollo/`): la página PHP entrega la URL base de la API al navegador y el JavaScript de `assets/js/formularios/` realiza las llamadas `fetch()` directamente.

Las URLs base de cada API se definen como constantes en `config/api.php`.

## Tecnologías utilizadas

- PHP 8.x
- CSS / JavaScript vanilla
- HTML
- Consumo de APIs REST corporativas

## Requisitos generales

Para ejecutar el proyecto se requiere un entorno web compatible con PHP.

Requisitos recomendados:

- Servidor web Apache, Nginx o IIS con soporte PHP.
- PHP 8.x o superior.
- Navegador web moderno.
- Acceso a las configuraciones internas necesarias del entorno de despliegue.

Las credenciales, rutas internas, endpoints privados y configuraciones sensibles no deben almacenarse en este repositorio.

## Instalación básica

1. Clonar el repositorio.
2. Copiar el proyecto al directorio del servidor web.
3. Configurar el entorno PHP según el servidor utilizado (el document root debe apuntar a la raíz del repositorio, ya que el código usa `$_SERVER['DOCUMENT_ROOT']` para resolver includes).
4. Revisar los endpoints definidos en `config/api.php`.
5. Acceder al sistema desde el navegador apuntando al dominio o ruta configurada.

## Buenas prácticas de seguridad

Antes de publicar o desplegar cambios, verificar que no se incluyan:

- Credenciales de base de datos.
- Tokens o claves API.
- Direcciones internas sensibles.
- Respaldos con información productiva.
- Archivos temporales del sistema operativo.
- Datos personales o información confidencial.

Se recomienda usar variables de entorno o archivos de configuración excluidos del control de versiones para manejar información sensible.

## Convenciones de desarrollo

- Mantener cada funcionalidad dentro del módulo correspondiente.
- Reutilizar componentes comunes desde `includes/`, `layouts/` y `services/`.
- Evitar duplicar lógica entre módulos.
- Escapar toda salida dinámica con `htmlspecialchars()`.
- Documentar cambios relevantes.
- Validar entradas de usuario antes de procesarlas.
- No exponer información interna en mensajes de error visibles al usuario.

## Estado del proyecto

Repositorio privado de Workspace Faret para organización y mantenimiento de módulos web internos.

## Contribución

Para colaborar en el proyecto:

1. Crear una rama descriptiva.
2. Realizar cambios pequeños y revisables.
3. Validar funcionamiento localmente.
4. Enviar los cambios mediante pull request o revisión interna.

## Licencia

Uso interno / privado de la organización. No distribuir información sensible ni componentes internos sin autorización.
