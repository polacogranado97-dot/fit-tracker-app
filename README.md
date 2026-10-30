# 🏋️ FitTracker Pro V2 - Web App / PWA para Entrenamiento Personalizado & Fotos de Progreso

Web App Responsive y PWA (Progressive Web App) Mobile-First con gestión de roles (**Entrenador / Admin** vs. **Atleta / Cliente**), microciclos de 5 semanas (S1..S5), control reactivo de cargas, galería técnica de ejercicios y módulo fotográfico de evolución con comparador de antes y después.

---

## 🚀 Nuevas Funcionalidades V2

1. **Gestión de Roles y Permisos Seguros:**
   - **Rol Entrenador / Administrador:**
     - Panel de control para gestionar todos los usuarios (crear, editar datos, rol y contraseña).
     - Asignar, crear y modificar rutinas, ejercicios, series, repeticiones y cargas de cualquier atleta.
     - Asignar o subir imágenes/GIFs técnicos demostrativos para cada ejercicio.
     - Supervisar las fotos de evolución subidas por cada cliente y realizar comparativas.
   - **Rol Atleta / Cliente:**
     - Pantalla de inicio personalizada con su nombre, avatar, peso corporal actual y rutina activa.
     - Permiso exclusivo para editar **únicamente sus propios datos** (cargas levantadas en kg, % intensidad, notas y pesaje).
     - Visor modal de técnica de ejercicios con animaciones o imágenes demostrativas.
     - Sección personal para subir y gestionar sus fotos de progreso.

2. **Módulo de Fotos de Progreso & Comparador (Antes vs. Después):**
   - Subida de fotografías desde la cámara del smartphone o galería (`capture="environment"`).
   - Clasificación por semana (S1 a S5), ángulo de toma (Frente, Perfil, Espalda), fecha y notas.
   - Almacenamiento seguro en `/uploads/progreso/` con nombres únicos generados mediante hash y protección contra ejecución de scripts vía `.htaccess`.
   - **Comparador interactivo lado a lado**: Permite seleccionar dos fotos (ej. Semana 1 Frente vs. Semana 4 Frente) para visualizar cambios en composición corporal, postura y tono muscular.

3. **Galería e Imágenes Técnicas en Ejercicios:**
   - Campo `imagen_url` en la base de datos para almacenar la ruta local o URL externa de imágenes/GIFs de técnica.
   - En la tarjeta del ejercicio, miniatura táctil y botón *"Técnica"* que despliega un modal con la ilustración demostrativa, tempo, series x reps, 1RM y recomendaciones técnicas.
   - El Administrador puede subir archivos de imagen directamente desde el modal de creación/edición de ejercicios (`/uploads/ejercicios/`).

---

## 🔑 Credenciales de Acceso de Demostración

La pantalla de login cuenta con **botones de 1 clic** para ingresar rápidamente con cualquiera de las siguientes cuentas de prueba:

| Rol | Correo Electrónico | Contraseña | Descripción |
| :--- | :--- | :--- | :--- |
| **Admin / Coach** | `admin@fittracker.com` | `admin123` | Control total, gestión de usuarios, asignación de rutinas y fotos. |
| **Atleta / Cliente** | `mateo.valenzuela@email.com` | `cliente123` | Atleta con rutina completa de 3 días y 4 fotos de progreso listas para comparar. |
| **Atleta / Cliente** | `sofia.rossi@email.com` | `cliente123` | Atleta mujer, nivel intermedio, enfoque en tren inferior. |

---

## 🛠️ Stack Tecnológico

- **Frontend:** HTML5, CSS3 con Tailwind CSS (CDN), Vanilla JavaScript moderno (SPA reactiva, Fetch API, debouncing, DOM interactivo, PWA).
- **Backend:** PHP 8.x estructurado y modular con API RESTful JSON y sesiones seguras (`auth.php`).
- **Base de Datos:** MySQL / MariaDB (InnoDB, UTF-8mb4, claves foráneas, vistas e índices).
- **Servidor:** XAMPP (Apache + MariaDB).

---

## 📂 Estructura del Proyecto

```text
fit-tracker-app/
├── schema_v2.sql          # Script DDL V2 (usuarios, roles, fotos_progreso, imagen_url)
├── schema.sql             # Esquema base original
├── db.php                 # Conexión PDO singleton y utilidades JSON
├── auth.php               # Sistema de autenticación por sesiones y roles (Admin/Cliente)
├── api.php                # Endpoints RESTful JSON V2 con subida de archivos multipart
├── install.php            # Instalador visual y CLI para migración de BD a V2
├── index.html             # Interfaz Mobile-First, Dark Mode con vistas Login / Atleta / Admin
├── index.php              # Redirección amigable a index.html
├── login.php              # Redirección amigable al login
├── admin.php              # Acceso directo verificado al panel admin
├── progreso.php           # Acceso directo al módulo de fotos
├── manifest.json          # Manifiesto PWA para instalación en Android / iOS
├── sw.js                  # Service Worker para caché offline
├── css/
│   └── custom.css         # Estilos específicos, steppers táctiles, comparador de fotos
├── js/
│   └── app.js             # SPA Vanilla JS V2, estado reactivo, comparador y fotos
├── uploads/
│   ├── .htaccess          # Protección de seguridad contra ejecución de scripts
│   ├── progreso/          # Carpeta de almacenamiento de fotos de progreso de atletas
│   ├── ejercicios/        # Carpeta de ilustraciones e imágenes de técnica
│   └── perfiles/          # Fotos de perfil de usuarios
└── README.md              # Documentación técnica completa
```

---

## 📡 Referencia de la API V2 (`api.php`)

| Método | Parámetro `action` | Permiso | Descripción |
| :--- | :--- | :--- | :--- |
| `POST` | `login` | Público | Autentica al usuario por email y contraseña. |
| `GET` | `check_session` / `me` | Público | Devuelve el usuario activo o estado no autenticado. |
| `GET` | `logout` | Autenticado | Destruye la sesión activa. |
| `GET` | `clientes` | Autenticado | Si es Admin: todos los clientes; Si es Cliente: solo su propio perfil. |
| `GET` | `usuarios` | **Solo Admin** | Lista todos los usuarios con roles y contraseñas cifradas. |
| `POST` | `usuario_guardar` | Autenticado | Admin crea/edita cualquier usuario; Cliente solo su propio perfil. |
| `POST` | `cliente_actualizar_peso` | Autenticado | Actualiza el peso corporal en kg del atleta. |
| `GET` | `rutina` | Autenticado | Devuelve ejercicios (con `imagen_url`), cargas de la semana y progresión S1..S5. |
| `POST` | `ejercicio_guardar` | **Solo Admin** | Crea o edita un ejercicio y su imagen técnica. |
| `POST` | `ejercicio_eliminar` | **Solo Admin** | Elimina un ejercicio de la rutina. |
| `POST` | `ejercicio_subir_imagen` | **Solo Admin** | Subida multipart de imagen/GIF demostrativo a `/uploads/ejercicios/`. |
| `POST` | `carga_guardar` | Autenticado | Guarda carga (kg), % y notas verificando pertenencia del atleta. |
| `POST` | `carga_copiar_semana` | Autenticado | Proyecta la carga a la semana posterior (+2.5 kg). |
| `POST` | `foto_subir` | Autenticado | Subida multipart de foto de progreso a `/uploads/progreso/`. |
| `GET` | `fotos_listar` | Autenticado | Lista fotos de progreso del atleta seleccionado o propio. |
| `POST` | `foto_eliminar` | Autenticado | Elimina el registro de la foto y su archivo físico. |
