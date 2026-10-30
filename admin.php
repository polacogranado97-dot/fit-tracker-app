<?php
/**
 * ===========================================================================
 * PANEL DE ADMINISTRACIÓN / ENTRENADOR (admin.php)
 * FitTracker Pro V2 - Vista exclusiva para Coach / Admin
 * - Gestión Integral de Atletas y Rutinas
 * - Módulo "Ver Progreso Visual" en modo SOLO LECTURA (sin opción de subida)
 * - Integración de recursos y técnica de Simply Fitness / Local
 * ===========================================================================
 */

require_once __DIR__ . '/auth.php';
startAuthSession();

// Exigir rol de administrador
if (!isLoggedIn() || !isAdmin()) {
    header('Location: index.html#login');
    exit;
}

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Panel de Entrenador - FitTracker Pro</title>
    
    <meta name="theme-color" content="#020617">
    <link rel="icon" type="image/svg+xml" href="icons/icon-192.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="css/custom.css">
</head>
<body class="bg-slate-950 text-slate-100 font-sans antialiased min-h-screen pb-16 w-full max-w-full overflow-x-hidden">

    <!-- CABECERA PRINCIPAL DEL PANEL DE ENTRENADOR (RESPONSIVA) -->
    <header class="sticky top-0 z-40 bg-slate-950/90 backdrop-blur-md border-b border-slate-800 safe-top w-full max-w-full">
        <div class="max-w-4xl mx-auto px-3 sm:px-4 py-2.5 sm:py-3 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 sm:gap-3">
            <div class="flex items-center justify-between">
                <a href="admin.php" class="flex items-center space-x-2">
                    <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-gradient-to-tr from-cyan-500 to-blue-500 p-0.5 flex items-center justify-center flex-shrink-0">
                        <div class="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center">
                            <span class="text-cyan-400 font-black text-xs sm:text-sm">COACH</span>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center space-x-1.5">
                            <span class="font-black text-sm sm:text-base text-white">FIT<span class="text-cyan-400">TRACKER</span></span>
                            <span class="text-[9px] uppercase font-black px-1.5 py-0.2 rounded bg-cyan-500/10 text-cyan-400 border border-cyan-500/30">ADMIN</span>
                        </div>
                        <p class="text-[10px] sm:text-[11px] text-slate-400 -mt-0.5"><?= htmlspecialchars($currentUser['nombre'] . ' ' . $currentUser['apellido']) ?></p>
                    </div>
                </a>

                <!-- Accesos rápidos móviles (Vista Móvil y Salir) -->
                <div class="flex sm:hidden items-center space-x-1.5">
                    <a href="index.html" class="p-2 bg-slate-900 hover:bg-slate-800 text-slate-300 rounded-xl border border-slate-800 text-xs font-bold transition flex items-center" title="Vista Móvil de la App">
                        <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </a>
                    <button onclick="cerrarSesionAdmin()" class="p-2 bg-slate-900 hover:bg-slate-800 text-rose-400 rounded-xl border border-slate-800 transition" title="Cerrar Sesión">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </button>
                </div>
            </div>

            <!-- Selector de Alumno a Supervisar y Botones Desktop -->
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <div class="relative flex-1 sm:flex-initial sm:w-60">
                    <select id="admin-select-cliente" class="w-full appearance-none bg-slate-900 border border-slate-700 hover:border-cyan-500/50 text-xs sm:text-sm font-bold text-white rounded-xl pl-3 pr-8 py-2 outline-none focus:ring-2 focus:ring-cyan-500/20 cursor-pointer transition truncate">
                        <option value="">Cargando alumnos...</option>
                    </select>
                    <div class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>

                <div class="hidden sm:flex items-center space-x-1.5 flex-shrink-0">
                    <a href="index.html" class="py-2 px-3 bg-slate-900 hover:bg-slate-800 text-slate-300 rounded-xl border border-slate-800 text-xs font-bold transition flex items-center space-x-1" title="Vista Móvil de la App">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <span>Vista Móvil</span>
                    </a>

                    <button onclick="cerrarSesionAdmin()" class="p-2 bg-slate-900 hover:bg-slate-800 text-rose-400 rounded-xl border border-slate-800 transition" title="Cerrar Sesión">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- NAVEGACIÓN DE PESTAÑAS ADMIN (RESPONSIVA) -->
    <div class="max-w-4xl mx-auto px-3 sm:px-4 pt-3 sm:pt-4 w-full max-w-full">
        <div class="flex flex-col sm:flex-row p-1 bg-slate-900 border border-slate-800 rounded-2xl gap-1">
            <button type="button" id="admin-tab-rutina" onclick="cambiarPestanaAdmin('rutina')" class="flex-1 py-2.5 px-3 rounded-xl text-xs font-black transition-all bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20 flex items-center justify-center space-x-1.5">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span>Planificación &amp; Ejercicios</span>
            </button>
            <button type="button" id="admin-tab-progreso" onclick="cambiarPestanaAdmin('progreso')" class="flex-1 py-2.5 px-3 rounded-xl text-xs font-bold transition-all text-slate-400 hover:text-white flex items-center justify-center space-x-1.5">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Ver Progreso Visual (Solo Lectura)</span>
            </button>
            <button type="button" id="admin-tab-usuarios" onclick="cambiarPestanaAdmin('usuarios')" class="flex-1 py-2.5 px-3 rounded-xl text-xs font-bold transition-all text-slate-400 hover:text-white flex items-center justify-center space-x-1.5">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span>Gestión de Atletas</span>
            </button>
        </div>
    </div>

    <!-- CONTENEDOR PRINCIPAL -->
    <main class="max-w-4xl mx-auto px-3 sm:px-4 pt-3 sm:pt-4 space-y-4 w-full max-w-full overflow-x-hidden">

        <!-- FICHA RESUMEN DEL ALUMNO SELECCIONADO (CON SKELETON INTEGRADO) -->
        <section class="bg-gradient-to-r from-slate-900 to-slate-900/90 rounded-3xl p-4 sm:p-5 border border-slate-800 shadow-xl flex items-center justify-between gap-3">
            <div class="flex items-center space-x-3 min-w-0">
                <div id="alumno-avatar" class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-slate-800 to-slate-700 text-cyan-400 font-black text-lg flex items-center justify-center border border-slate-700 flex-shrink-0">
                    --
                </div>
                <div class="min-w-0">
                    <h2 id="alumno-nombre" class="text-base sm:text-lg font-black text-white truncate">Cargando alumno...</h2>
                    <p id="alumno-info" class="text-xs text-slate-400 truncate">Peso: -- kg • Estado: Activo</p>
                </div>
            </div>
            <div class="text-right flex-shrink-0">
                <span class="text-[10px] uppercase font-bold text-slate-500 block">Modo Entrenador</span>
                <span class="text-xs px-2.5 py-1 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 font-bold inline-block">
                    Supervisión &amp; Cargas
                </span>
            </div>
        </section>

        <!-- SECCIÓN 1: PLANIFICACIÓN & EJERCICIOS -->
        <div id="seccion-admin-rutina" class="space-y-4">
            <!-- Barra de Semanas S1..S5 -->
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Microciclo Activo</span>
                <!-- BOTÓN LIMPIO Y COMPACTO: + Agregar Ejercicio -->
                <button type="button" onclick="abrirModalEjercicioAdmin()" class="py-1.5 px-3 sm:py-2 sm:px-3.5 bg-gradient-to-r from-cyan-500 to-blue-500 hover:brightness-110 text-slate-950 font-black rounded-xl text-[11px] sm:text-xs flex items-center space-x-1.5 shadow-md shadow-cyan-500/20 transition flex-shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    <span>Agregar Ejercicio</span>
                </button>
            </div>

            <!-- Navegador de Semanas -->
            <div id="admin-semanas-nav" class="flex gap-2 p-1.5 bg-slate-900/60 rounded-3xl border border-slate-800"></div>

            <!-- Contenedor de Ejercicios del Alumno -->
            <div id="admin-ejercicios-container" class="space-y-4"></div>
        </div>

        <!-- SECCIÓN 2: VER PROGRESO VISUAL (SOLO LECTURA) -->
        <div id="seccion-admin-progreso" class="space-y-4 hidden">
            <!-- Banner Explicativo de Restricción Estricta -->
            <div class="p-3.5 bg-cyan-950/30 border border-cyan-500/30 rounded-2xl flex items-start space-x-3 text-xs text-cyan-200">
                <div class="text-cyan-400 mt-0.5 flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <strong class="text-cyan-300 block mb-0.5">MODO SOLO LECTURA • CONTROL DE PROGRESO FOTOGRÁFICO</strong>
                    <span>Por directiva de privacidad y control, <strong>únicamente el alumno/atleta puede subir o eliminar sus fotos de progreso</strong> desde su celular. Como entrenador, tienes acceso exclusivo para revisar la galería cronológica y comparar el antes/después.</span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-black text-white uppercase tracking-wide">Galería de Evolución del Alumno</h3>
                    <p class="text-xs text-slate-400">Ordenada cronológicamente por semanas (S1..S5)</p>
                </div>

                <button type="button" id="admin-btn-comparar" onclick="abrirComparadorAdmin()" class="py-2 px-3.5 bg-cyan-500/20 hover:bg-cyan-500/30 border border-cyan-500/40 text-cyan-300 font-bold text-xs rounded-xl flex items-center justify-center space-x-1.5 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    <span>Comparar Fotos (Antes vs. Después)</span>
                </button>
            </div>

            <!-- Grilla de Fotos de Solo Lectura -->
            <div id="admin-fotos-container" class="grid grid-cols-2 sm:grid-cols-4 gap-3"></div>
        </div>

        <!-- SECCIÓN 3: GESTIÓN DE ATLETAS & USUARIOS -->
        <div id="seccion-admin-usuarios" class="space-y-4 hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                <div>
                    <h3 class="text-sm font-black text-white uppercase tracking-wide">Directorio de Atletas y Coaches</h3>
                    <p class="text-xs text-slate-400">Espacio de administración exclusiva y credenciales de tu gimnasio</p>
                </div>
                <button type="button" onclick="abrirModalCrearUsuarioAdmin()" class="py-2 px-3.5 bg-gradient-to-r from-emerald-500 to-teal-500 hover:brightness-110 text-slate-950 font-black rounded-xl text-xs flex items-center space-x-1 shadow-md shadow-emerald-500/20 transition flex-shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    <span>+ Nuevo Atleta / Usuario</span>
                </button>
            </div>

            <!-- Buscador para alto rendimiento con +100 alumnos -->
            <div class="relative">
                <input type="text" id="admin-buscar-usuario" oninput="filtrarListaUsuariosAdmin(this.value)" placeholder="🔍 Buscar atleta por nombre o email (+100 alumnos)..." class="w-full h-11 bg-slate-900 border border-slate-700 focus:border-cyan-500 rounded-xl px-3.5 text-xs text-white outline-none">
            </div>

            <div id="admin-lista-usuarios" class="space-y-2"></div>
        </div>

    </main>

    <!-- MODAL 1: EJERCICIO / LOCAL -->
    <div id="modal-ejercicio-admin" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4 hidden">
        <div class="modal-content-animated bg-slate-900 border border-slate-800 w-11/12 max-w-lg rounded-3xl p-5 sm:p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <div class="flex items-center space-x-2">
                    <span class="w-7 h-7 rounded-lg bg-cyan-500/20 text-cyan-400 flex items-center justify-center font-bold text-xs">SF</span>
                    <h3 id="admin-modal-ejercicio-titulo" class="text-base font-black text-white">Nuevo Ejercicio</h3>
                </div>
                <button type="button" onclick="cerrarModalEjercicioAdmin()" class="p-1.5 text-slate-400 hover:text-white rounded-lg">✕</button>
            </div>

            <form id="admin-form-ejercicio" class="mt-4 space-y-3.5">
                <input type="hidden" id="admin-ej-id" value="">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Día de la Rutina *</label>
                        <select id="admin-ej-dia" class="w-full h-11 bg-slate-950 border border-slate-700 rounded-xl px-3 text-xs text-white focus:border-cyan-500 outline-none">
                            <option value="1">Día 1 (Empuje / Pecho / Hombros)</option>
                            <option value="2">Día 2 (Pierna / Cuádriceps / Glúteos)</option>
                            <option value="3">Día 3 (Tracción / Espalda / Bíceps)</option>
                            <option value="4">Día 4 (Pierna / Isquios / Gemelos)</option>
                            <option value="5">Día 5 (Accesorios / Core / Brazos)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Nombre del Ejercicio *</label>
                    <input type="text" id="admin-ej-nombre" required placeholder="ej. Press de Banca con Barra" class="w-full h-11 bg-slate-950 border border-slate-700 rounded-xl px-3 text-sm text-white focus:border-cyan-500 outline-none">
                </div>

                <!-- Selección de Demostración Técnica / Galería Local -->
                <div class="p-3 bg-slate-950 rounded-2xl border border-slate-800 space-y-2">
                    <label class="block text-xs font-bold text-cyan-300">Galería de Imágenes Locales</label>
                    <select id="admin-ej-select-local" onchange="onImagenLocalAdminChange(this.value)" class="w-full h-10 bg-slate-900 border border-slate-700 rounded-xl px-3 text-xs text-white focus:border-cyan-400 outline-none cursor-pointer">
                        <option value="assets/ejercicios/default.gif">-- Seleccionar de Galería Local --</option>
                    </select>

                    <input type="hidden" id="admin-ej-imagen-url" value="assets/ejercicios/default.gif">

                    <div class="flex items-center gap-3 pt-1">
                        <div class="w-16 h-16 rounded-xl bg-slate-900 border border-slate-800 overflow-hidden flex items-center justify-center flex-shrink-0">
                            <img id="admin-ej-preview-img" src="assets/ejercicios/default.gif" alt="Preview" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='assets/ejercicios/default.gif';">
                        </div>
                        <p class="text-[11px] text-slate-400 leading-tight">
                            Recurso visual alojado localmente. Se proyecta de forma fluida y sin consumo de datos externos en el teléfono del atleta.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Series</label>
                        <input type="number" id="admin-ej-series" value="4" min="1" max="20" class="w-full h-10 bg-slate-950 border border-slate-700 rounded-xl px-2 text-center text-xs text-white font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Reps</label>
                        <input type="text" id="admin-ej-reps" value="8-10" class="w-full h-10 bg-slate-950 border border-slate-700 rounded-xl px-2 text-center text-xs text-white font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">1RM Ref. (kg)</label>
                        <input type="number" step="0.5" id="admin-ej-1rm" placeholder="0" class="w-full h-10 bg-slate-950 border border-slate-700 rounded-xl px-2 text-center text-xs text-cyan-400 font-bold">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Indicaciones Técnicas &amp; Tempo</label>
                    <textarea id="admin-ej-obs" rows="2" placeholder="ej. Tempo 3-0-1-0, agarre al ancho biacromial, pausa isométrica..." class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2.5 text-xs text-white outline-none resize-none"></textarea>
                </div>

                <div class="pt-3 border-t border-slate-800 flex justify-end gap-3">
                    <button type="button" onclick="cerrarModalEjercicioAdmin()" class="py-2.5 px-5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition">Cancelar</button>
                    <button type="submit" class="py-2.5 px-5 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-500 hover:brightness-110 text-slate-950 font-black text-xs shadow-lg shadow-cyan-500/20 transition">Guardar Ejercicio</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: COMPARADOR EN MODO SOLO LECTURA PARA ENTRENADOR -->
    <div id="admin-modal-comparador" class="fixed inset-0 z-50 bg-slate-950/85 backdrop-blur-md flex items-center justify-center p-3 sm:p-4 hidden">
        <div class="modal-content-animated bg-slate-900 border border-slate-800 w-11/12 max-w-lg sm:max-w-xl rounded-3xl p-5 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <div class="flex items-center space-x-2">
                    <span class="p-1.5 rounded-lg bg-cyan-500/20 text-cyan-400 font-bold text-xs">VS</span>
                    <h3 class="text-sm sm:text-base font-black text-white">Comparador de Evolución del Alumno (Solo Lectura)</h3>
                </div>
                <button type="button" onclick="cerrarComparadorAdmin()" class="p-1.5 text-slate-400 hover:text-white">✕</button>
            </div>

            <div class="grid grid-cols-2 gap-3 my-3">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-1">Foto Inicial (Antes)</label>
                    <select id="admin-comp-select-a" onchange="renderComparacionAdmin()" class="w-full h-9 bg-slate-950 border border-slate-700 rounded-xl px-2 text-xs text-white outline-none"></select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-1">Foto Posterior (Después)</label>
                    <select id="admin-comp-select-b" onchange="renderComparacionAdmin()" class="w-full h-9 bg-slate-950 border border-slate-700 rounded-xl px-2 text-xs text-white outline-none"></select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="bg-slate-950 p-2.5 rounded-2xl border border-slate-800 text-center">
                    <div class="aspect-photo bg-slate-900 rounded-xl overflow-hidden mb-2 relative">
                        <img id="admin-comp-img-a" src="" alt="Foto Inicial" class="w-full h-full object-cover">
                        <span id="admin-comp-tag-a" class="absolute top-2 left-2 px-2 py-0.5 rounded text-[10px] font-black bg-slate-950/80 text-cyan-400 border border-cyan-500/30">S1</span>
                    </div>
                    <p id="admin-comp-fecha-a" class="text-xs font-bold text-slate-300">--</p>
                    <p id="admin-comp-obs-a" class="text-[10px] text-slate-400 mt-1 line-clamp-2">--</p>
                </div>

                <div class="bg-slate-950 p-2.5 rounded-2xl border border-slate-800 text-center">
                    <div class="aspect-photo bg-slate-900 rounded-xl overflow-hidden mb-2 relative">
                        <img id="admin-comp-img-b" src="" alt="Foto Posterior" class="w-full h-full object-cover">
                        <span id="admin-comp-tag-b" class="absolute top-2 left-2 px-2 py-0.5 rounded text-[10px] font-black bg-slate-950/80 text-emerald-400 border border-emerald-500/30">S4</span>
                    </div>
                    <p id="admin-comp-fecha-b" class="text-xs font-bold text-slate-300">--</p>
                    <p id="admin-comp-obs-b" class="text-[10px] text-slate-400 mt-1 line-clamp-2">--</p>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-800 text-right">
                <button type="button" onclick="cerrarComparadorAdmin()" class="py-2.5 px-6 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- MODAL 3: GESTIÓN DE ATLETA / USUARIO (CREAR O EDITAR DESDE COACH) -->
    <div id="modal-usuario-admin" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4 hidden">
        <div class="modal-content-animated bg-slate-900 border border-slate-800 w-11/12 max-w-lg rounded-3xl p-5 sm:p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <h3 id="admin-modal-usuario-titulo" class="text-base font-black text-white">Nuevo Atleta / Usuario</h3>
                <button type="button" onclick="cerrarModalUsuarioAdmin()" class="p-1.5 text-slate-400 hover:text-white rounded-lg">✕</button>
            </div>

            <form id="admin-form-usuario" class="mt-4 space-y-3.5">
                <input type="hidden" id="admin-usr-id" value="">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Nombre *</label>
                        <input type="text" id="admin-usr-nombre" required placeholder="Carlos" class="w-full h-11 bg-slate-950 border border-slate-700 rounded-xl px-3 text-sm text-white focus:border-cyan-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Apellido *</label>
                        <input type="text" id="admin-usr-apellido" required placeholder="Mendoza" class="w-full h-11 bg-slate-950 border border-slate-700 rounded-xl px-3 text-sm text-white focus:border-cyan-500 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Email *</label>
                        <input type="email" id="admin-usr-email" required placeholder="atleta@correo.com" class="w-full h-11 bg-slate-950 border border-slate-700 rounded-xl px-3 text-sm text-white focus:border-cyan-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Contraseña</label>
                        <input type="password" id="admin-usr-password" placeholder="Contraseña de acceso" class="w-full h-11 bg-slate-950 border border-slate-700 rounded-xl px-3 text-sm text-white focus:border-cyan-500 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Peso Corporal (kg) *</label>
                        <input type="number" step="0.1" id="admin-usr-peso" required placeholder="75.0" class="w-full h-11 bg-slate-950 border border-slate-700 rounded-xl px-3 text-sm text-white focus:border-cyan-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Teléfono (WhatsApp)</label>
                        <input type="tel" id="admin-usr-telefono" placeholder="+54 9 11 ..." class="w-full h-11 bg-slate-950 border border-slate-700 rounded-xl px-3 text-sm text-white focus:border-cyan-500 outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Rol en la Plataforma</label>
                    <select id="admin-usr-rol" class="w-full h-11 bg-slate-950 border border-slate-700 rounded-xl px-3 text-sm text-white focus:border-cyan-500 outline-none">
                        <option value="cliente">Atleta / Cliente</option>
                        <option value="admin">Entrenador / Administrador</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Anamnesis / Lesiones / Observaciones</label>
                    <textarea id="admin-usr-observaciones" rows="3" placeholder="Molestias articulares, antecedentes médicos, cirugías, metas..." class="w-full bg-slate-950 border border-slate-700 rounded-xl p-3 text-xs text-white focus:border-cyan-500 outline-none resize-none"></textarea>
                </div>

                <div class="pt-3 border-t border-slate-800 flex justify-end gap-3">
                    <button type="button" onclick="cerrarModalUsuarioAdmin()" class="py-2.5 px-5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition">Cancelar</button>
                    <button type="submit" class="py-2.5 px-5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:brightness-110 text-slate-950 font-black text-xs shadow-lg shadow-emerald-500/20 transition">Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 4: CONFIGURAR BLOQUE DE MOVILIDAD Y CALENTAMIENTO PREVIO -->
    <div id="modal-movilidad-admin" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4 hidden">
        <div class="modal-content-animated bg-slate-900 border border-slate-800 w-11/12 max-w-lg rounded-3xl p-5 sm:p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <div class="flex items-center space-x-2">
                    <span class="p-1.5 rounded-xl bg-amber-500/20 text-amber-400 font-bold text-xs">🔥</span>
                    <h3 id="admin-modal-movilidad-titulo" class="text-base font-black text-white">Movilidad &amp; Calentamiento Previo</h3>
                </div>
                <button type="button" onclick="cerrarModalMovilidadAdmin()" class="p-1.5 text-slate-400 hover:text-white rounded-lg">✕</button>
            </div>

            <form id="admin-form-movilidad" onsubmit="guardarMovilidadAdmin(event)" class="mt-4 space-y-3.5">
                <input type="hidden" id="admin-movilidad-dia" value="1">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Indicaciones de Calentamiento / Movilidad Articular</label>
                    <p class="text-[11px] text-slate-400 mb-2">Este bloque se presentará al atleta al inicio del día antes de los ejercicios con carga.</p>
                    <textarea id="admin-movilidad-texto" rows="6" required placeholder="ej.&#10;• 5 min cinta suave o elíptica (RPE 4-5)&#10;• Dislocaciones de hombro con banda elástica (2x15)&#10;• Face pulls ligeros con banda (2x15)&#10;• Series de aproximación con barra vacía..." class="w-full bg-slate-950 border border-slate-700 rounded-xl p-3 text-xs text-white focus:border-amber-400 outline-none leading-relaxed resize-none"></textarea>
                </div>

                <div class="pt-3 border-t border-slate-800 flex justify-end gap-3">
                    <button type="button" onclick="cerrarModalMovilidadAdmin()" class="py-2.5 px-5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition">Cancelar</button>
                    <button type="submit" class="py-2.5 px-5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:brightness-110 text-slate-950 font-black text-xs shadow-lg shadow-amber-500/20 transition">Guardar Calentamiento</button>
                </div>
            </form>
        </div>
    </div>

    <!-- TOAST CONTAINER & LOADER -->
    <div id="admin-toast-container" class="fixed bottom-5 left-1/2 -translate-x-1/2 w-11/12 max-w-sm flex flex-col items-center gap-2 z-50 pointer-events-none"></div>
    <div id="admin-loader" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-[2px] flex items-center justify-center hidden pointer-events-none">
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl flex items-center space-x-3 shadow-2xl">
            <div class="w-5 h-5 border-2 border-cyan-400 border-t-transparent rounded-full animate-spin"></div>
            <span class="text-xs font-bold text-slate-300">Cargando datos...</span>
        </div>
    </div>

    <!-- SCRIPT DEL PANEL DE ADMINISTRACIÓN -->
    <script>
        const AdminState = {
            alumnos: [],
            alumnoActivoId: null,
            alumnoActual: null,
            semanaActiva: 1,
            rutinaActual: null,
            diasRutina: [],
            fotosProgreso: [],
            pestanaActiva: 'rutina',
            imagenesLocalesCache: null,
            listaUsuariosCache: [],
            currentAdminUserId: <?= (int)$currentUser['id'] ?>
        };

        const SF_PRESETS = {
            pecho: { nombre: 'Press de Banca Plano con Barra', url: 'assets/ejercicios/exercise_images/0031_Press_de_banca_con_barra.png', obs: 'Pausa de 1 segundo en el pecho. Retracción escapular activa.' },
            espalda: { nombre: 'Remo con Barra', url: 'assets/ejercicios/exercise_images/0048_Remo_con_barra.png', obs: 'Torso a 45 grados, codos pegados al cuerpo, foco en dorsal ancho.' },
            hombros: { nombre: 'Press Militar con Mancuernas', url: 'assets/ejercicios/exercise_images/0075_Press_Militar_con_mancuernas.png', obs: 'Sentado en banco a 75°. Rango de recorrido completo.' },
            piernas: { nombre: 'Sentadilla Trasera', url: 'assets/ejercicios/exercise_images/0122_Sentadilla.png', obs: 'Profundidad paralela o sub-paralela. Respiración diafragmática.' },
            isquios: { nombre: 'Peso Muerto Rumano', url: 'assets/ejercicios/exercise_images/0132_Peso_muerto_rumano_piernas_rectas_con_barra.png', obs: 'Bisagra de cadera pura, rodillas semirrígidas, sentir estiramiento en isquios.' },
            biceps: { nombre: 'Curl de Bíceps', url: 'assets/ejercicios/exercise_images/0096_Curl_de_b_ceps_con_barra.png', obs: 'Codos fijos a los costados, sin balanceo lumbar.' },
            triceps: { nombre: 'Fondos en Barras Paralelas', url: 'assets/ejercicios/exercise_images/0112_Fondos_en_barras_paralelas.png', obs: 'Extensión y bloqueo controlado de tríceps.' }
        };

        document.addEventListener('DOMContentLoaded', async () => {
            setupAdminListeners();
            await cargarAlumnosAdmin();
            await cargarImagenesLocalesAdmin();
        });

        function setupAdminListeners() {
            const select = document.getElementById('admin-select-cliente');
            if (select) {
                select.addEventListener('change', async (e) => {
                    const id = parseInt(e.target.value);
                    if (id && id !== AdminState.alumnoActivoId) {
                        AdminState.alumnoActivoId = id;
                        await cargarDatosAlumnoSeleccionado(id);
                    }
                });
            }

            const formEj = document.getElementById('admin-form-ejercicio');
            if (formEj) {
                formEj.addEventListener('submit', guardarEjercicioAdmin);
            }

            const formUsr = document.getElementById('admin-form-usuario');
            if (formUsr) {
                formUsr.addEventListener('submit', guardarUsuarioAdmin);
            }
        }

        function mostrarLoader(mostrar) {
            const el = document.getElementById('admin-loader');
            if (!el) return;
            if (mostrar) el.classList.remove('hidden');
            else el.classList.add('hidden');
        }

        function mostrarToastAdmin(mensaje, tipo = 'info') {
            const container = document.getElementById('admin-toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            const colorBg = tipo === 'success' 
                ? 'bg-emerald-500 text-slate-950 font-black' 
                : tipo === 'error' 
                ? 'bg-rose-500 text-white font-bold' 
                : 'bg-slate-800 text-slate-100 font-semibold border border-slate-700';
            toast.className = `${colorBg} py-2.5 px-4 rounded-2xl shadow-2xl text-xs sm:text-sm flex items-center space-x-2 transition-all duration-300 transform translate-y-2 opacity-0 pointer-events-auto`;
            toast.innerHTML = `<span>${escaparHtmlAdmin(mensaje)}</span>`;
            container.appendChild(toast);
            requestAnimationFrame(() => { toast.classList.remove('translate-y-2', 'opacity-0'); });
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 2800);
        }

        function escaparHtmlAdmin(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function mostrarSkeletonAlumno(mostrar) {
            const avatar = document.getElementById('alumno-avatar');
            const nombre = document.getElementById('alumno-nombre');
            const info = document.getElementById('alumno-info');
            if (mostrar) {
                if (avatar) avatar.innerHTML = `<div class="w-5 h-5 border-2 border-cyan-400 border-t-transparent rounded-full animate-spin"></div>`;
                if (nombre) nombre.innerHTML = `<span class="inline-block w-36 h-5 bg-slate-800 rounded-lg animate-pulse"></span>`;
                if (info) info.innerHTML = `<span class="inline-block w-48 h-3.5 bg-slate-800/80 rounded animate-pulse mt-1"></span>`;
            }
        }

        async function cargarAlumnosAdmin() {
            mostrarSkeletonAlumno(true);
            mostrarLoader(true);
            try {
                const res = await fetch('api.php?action=clientes');
                const json = await res.json();
                if (json.success && Array.isArray(json.data) && json.data.length > 0) {
                    AdminState.alumnos = json.data;
                    const select = document.getElementById('admin-select-cliente');
                    select.innerHTML = '';
                    json.data.forEach(al => {
                        const opt = document.createElement('option');
                        opt.value = al.id;
                        opt.textContent = `${al.nombre} ${al.apellido}`;
                        select.appendChild(opt);
                    });

                    if (!AdminState.alumnoActivoId || !json.data.some(a => a.id == AdminState.alumnoActivoId)) {
                        AdminState.alumnoActivoId = json.data[0].id;
                    }
                    select.value = AdminState.alumnoActivoId;
                    await cargarDatosAlumnoSeleccionado(AdminState.alumnoActivoId);
                } else {
                    document.getElementById('alumno-nombre').textContent = 'No hay alumnos activos';
                    document.getElementById('alumno-info').textContent = 'Crea uno desde la pestaña "Gestión de Atletas"';
                    document.getElementById('alumno-avatar').textContent = '--';
                    document.getElementById('admin-ejercicios-container').innerHTML = '<p class="text-xs text-slate-400 text-center py-8">Registra un alumno para comenzar su planificación.</p>';
                }
            } catch (err) {
                mostrarToastAdmin('Error al conectar con el servidor', 'error');
            } finally {
                mostrarLoader(false);
            }
        }

        async function cargarDatosAlumnoSeleccionado(alumnoId) {
            if (!alumnoId) return;
            mostrarSkeletonAlumno(true);
            mostrarLoader(true);
            try {
                const res = await fetch(`api.php?action=rutina&cliente_id=${alumnoId}&semana=${AdminState.semanaActiva}`);
                const json = await res.json();
                if (json.success && json.data) {
                    AdminState.alumnoActual = json.data.cliente;
                    AdminState.rutinaActual = json.data.rutina;
                    AdminState.diasRutina = json.data.dias || [];

                    const c = json.data.cliente;
                    const peso = parseFloat(c.peso_corporal || 0).toFixed(1);
                    const iniciales = ((c.nombre ? c.nombre[0] : '') + (c.apellido ? c.apellido[0] : '')).toUpperCase() || 'AT';
                    
                    document.getElementById('alumno-avatar').textContent = iniciales;
                    document.getElementById('alumno-nombre').textContent = `${c.nombre} ${c.apellido}`;
                    document.getElementById('alumno-info').textContent = `Peso: ${peso} kg • ${c.email || 'Atleta Activo'}`;

                    renderSemanasAdmin();
                    renderEjerciciosAdmin();
                } else {
                    mostrarToastAdmin(json.message || 'Error al obtener datos', 'error');
                }
                await cargarFotosAdminReadOnly(alumnoId);
            } catch (err) {
                mostrarToastAdmin('Error al sincronizar datos del alumno', 'error');
            } finally {
                mostrarLoader(false);
            }
        }

        async function cargarFotosAdminReadOnly(alumnoId) {
            try {
                const res = await fetch(`api.php?action=get_fotos_progreso_admin&usuario_id=${alumnoId}`);
                const json = await res.json();
                if (json.success) {
                    AdminState.fotosProgreso = json.data.fotos || [];
                    renderFotosAdminReadOnly();
                }
            } catch (err) {
                // Silencioso
            }
        }

        function renderFotosAdminReadOnly() {
            const container = document.getElementById('admin-fotos-container');
            const btnComp = document.getElementById('admin-btn-comparar');
            if (!container) return;
            container.innerHTML = '';

            if (AdminState.fotosProgreso.length === 0) {
                container.innerHTML = `
                    <div class="col-span-2 sm:col-span-4 text-center py-12 px-4 bg-slate-900/60 rounded-3xl border border-dashed border-slate-800">
                        <p class="text-xs text-slate-400">Este alumno aún no ha subido fotografías de progreso.</p>
                        <span class="text-[11px] text-cyan-400/80 block mt-1">El alumno debe subirlas desde su smartphone o perfil personal.</span>
                    </div>
                `;
                if (btnComp) btnComp.classList.add('hidden');
                return;
            }
            if (btnComp) btnComp.classList.remove('hidden');

            AdminState.fotosProgreso.forEach(foto => {
                const card = document.createElement('div');
                card.className = 'bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-lg';
                card.innerHTML = `
                    <div class="aspect-photo bg-slate-950 relative overflow-hidden">
                        <img src="${foto.ruta_imagen}" alt="Foto S${foto.semana}" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='assets/ejercicios/default.gif';">
                        <div class="absolute top-2 left-2 flex gap-1">
                            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-slate-950/80 text-cyan-400 border border-cyan-500/30">S${foto.semana}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-950/80 text-slate-300 capitalize border border-slate-700">${foto.tipo_angulo}</span>
                        </div>
                    </div>
                    <div class="p-2.5 text-xs">
                        <p class="text-slate-300 font-bold text-[11px]">${foto.fecha_subida}</p>
                        <p class="text-slate-400 text-[10px] truncate" title="${foto.observaciones || ''}">${foto.observaciones || 'Sin notas'}</p>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        function renderSemanasAdmin() {
            const container = document.getElementById('admin-semanas-nav');
            if (!container) return;
            container.innerHTML = '';
            [1, 2, 3, 4, 5].forEach(s => {
                const act = s === AdminState.semanaActiva;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `flex-1 py-2 rounded-2xl text-xs font-black transition ${act ? 'bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20 ring-2 ring-cyan-300' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800'}`;
                btn.textContent = `S${s}`;
                btn.onclick = () => { AdminState.semanaActiva = s; cargarDatosAlumnoSeleccionado(AdminState.alumnoActivoId); };
                container.appendChild(btn);
            });
        }

        function renderEjerciciosAdmin() {
            const container = document.getElementById('admin-ejercicios-container');
            if (!container) return;
            container.innerHTML = '';

            if (AdminState.diasRutina.length === 0) {
                container.innerHTML = `<p class="text-xs text-slate-400 text-center py-8">No hay ejercicios configurados para este microciclo.</p>`;
                return;
            }

            AdminState.diasRutina.forEach(dia => {
                const sec = document.createElement('div');
                sec.className = 'space-y-3 bg-slate-900/40 p-3 sm:p-4 rounded-3xl border border-slate-800/80 shadow-md';
                
                const tieneMovilidad = dia.movilidad && dia.movilidad.trim() !== '';
                const movilidadHtml = `
                    <!-- BLOQUE DE MOVILIDAD & CALENTAMIENTO PREVIO -->
                    <div class="bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-amber-500/5 border border-amber-500/30 rounded-2xl p-3.5 space-y-1.5 shadow-sm">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center space-x-1.5 text-amber-400 font-extrabold text-xs uppercase tracking-wider">
                                <svg class="w-4 h-4 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <span>Movilidad &amp; Entrada en Calor Previa</span>
                            </div>
                            <button type="button" onclick="abrirModalMovilidadAdmin(${dia.dia_semana})" class="text-[11px] font-bold text-amber-300 hover:text-white px-2.5 py-1 bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/30 rounded-lg transition flex items-center space-x-1 flex-shrink-0">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                <span>${tieneMovilidad ? 'Editar' : '+ Configurar'}</span>
                            </button>
                        </div>
                        <p class="text-xs text-slate-300 whitespace-pre-line leading-relaxed ${tieneMovilidad ? '' : 'italic text-slate-500'}">
                            ${tieneMovilidad ? escaparHtmlAdmin(dia.movilidad) : 'Sin notas de movilidad asignadas para este día. Haz clic en "+ Configurar" para detallar los ejercicios de calentamiento previo.'}
                        </p>
                    </div>
                `;

                sec.innerHTML = `
                    <div class="flex items-center justify-between border-b border-slate-800 pb-2 mb-1">
                        <h4 class="text-xs sm:text-sm font-extrabold text-cyan-400 uppercase tracking-wider">${escaparHtmlAdmin(dia.titulo)}</h4>
                        <span class="text-[11px] text-slate-400 font-bold bg-slate-950 px-2 py-0.5 rounded-lg border border-slate-800">${dia.ejercicios.length} ejercicios</span>
                    </div>
                    ${movilidadHtml}
                `;

                const list = document.createElement('div');
                list.className = 'space-y-2 mt-2';
                dia.ejercicios.forEach(ej => {
                    const row = document.createElement('div');
                    row.className = 'p-3 bg-slate-900 border border-slate-800 rounded-2xl flex items-center justify-between gap-3';
                    const tieneSf = ej.imagen_url && ej.imagen_url.includes('assets/ejercicios');
                    row.innerHTML = `
                        <div class="flex items-center space-x-3 flex-1 min-w-0">
                            <div class="w-12 h-12 rounded-xl bg-slate-950 border border-slate-800 overflow-hidden flex-shrink-0 flex items-center justify-center">
                                <img src="${ej.imagen_url || 'assets/ejercicios/default.gif'}" alt="${escaparHtmlAdmin(ej.nombre_ejercicio)}" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='assets/ejercicios/default.gif';">
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-xs font-bold text-white truncate">${escaparHtmlAdmin(ej.nombre_ejercicio)}</span>
                                    ${tieneSf ? `<span class="text-[9px] font-black px-1.5 py-0.2 rounded bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">Simply Fitness</span>` : ''}
                                </div>
                                <p class="text-[11px] text-slate-400">${ej.series} Series × ${ej.repeticiones} • 1RM: ${ej.un_rm_estimado > 0 ? ej.un_rm_estimado + 'kg' : '--'}</p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 text-right flex-shrink-0">
                            <div class="text-xs font-bold text-emerald-400 font-mono">
                                ${ej.carga_actual && ej.carga_actual.peso_kg > 0 ? ej.carga_actual.peso_kg + ' kg' : '0 kg'}
                            </div>
                            <button type="button" onclick="editarEjercicioAdmin(${ej.id})" class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition" title="Editar">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </button>
                            <button type="button" onclick="eliminarEjercicioAdmin(${ej.id})" class="p-1.5 text-slate-500 hover:text-rose-400 rounded-lg hover:bg-slate-800 transition" title="Eliminar">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    `;
                    list.appendChild(row);
                });
                sec.appendChild(list);
                container.appendChild(sec);
            });
        }

        function abrirModalMovilidadAdmin(diaSemana) {
            const diaObj = AdminState.diasRutina.find(d => d.dia_semana == diaSemana);
            document.getElementById('admin-modal-movilidad-titulo').textContent = `Movilidad & Calentamiento - Día ${diaSemana}`;
            document.getElementById('admin-movilidad-dia').value = diaSemana;
            document.getElementById('admin-movilidad-texto').value = diaObj ? (diaObj.movilidad || '') : '';
            document.getElementById('modal-movilidad-admin').classList.remove('hidden');
        }

        function cerrarModalMovilidadAdmin() {
            document.getElementById('modal-movilidad-admin').classList.add('hidden');
        }

        async function guardarMovilidadAdmin(e) {
            e.preventDefault();
            if (!AdminState.rutinaActual) {
                mostrarToastAdmin('No hay rutina activa para este alumno', 'error');
                return;
            }
            const diaSemana = parseInt(document.getElementById('admin-movilidad-dia').value);
            const movilidad = document.getElementById('admin-movilidad-texto').value.trim();

            mostrarLoader(true);
            try {
                const res = await fetch('api.php?action=rutina_guardar_movilidad', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        rutina_id: AdminState.rutinaActual.id,
                        dia_semana: diaSemana,
                        movilidad: movilidad
                    })
                });
                const json = await res.json();
                if (json.success) {
                    cerrarModalMovilidadAdmin();
                    mostrarToastAdmin('Bloque de movilidad y calentamiento actualizado', 'success');
                    await cargarDatosAlumnoSeleccionado(AdminState.alumnoActivoId);
                } else {
                    mostrarToastAdmin(json.message || 'Error al guardar movilidad', 'error');
                }
            } catch (err) {
                mostrarToastAdmin('Error de red al guardar movilidad', 'error');
            } finally {
                mostrarLoader(false);
            }
        }

        function cambiarPestanaAdmin(pestana) {
            AdminState.pestanaActiva = pestana;
            const tabs = {
                rutina: { btn: 'admin-tab-rutina', sec: 'seccion-admin-rutina' },
                progreso: { btn: 'admin-tab-progreso', sec: 'seccion-admin-progreso' },
                usuarios: { btn: 'admin-tab-usuarios', sec: 'seccion-admin-usuarios' }
            };
            for (const key in tabs) {
                const b = document.getElementById(tabs[key].btn);
                const s = document.getElementById(tabs[key].sec);
                if (!b || !s) continue;
                if (key === pestana) {
                    b.className = 'flex-1 py-2.5 px-3 rounded-xl text-xs font-black transition-all bg-cyan-500 text-slate-950 shadow-md shadow-cyan-500/20 flex items-center justify-center space-x-1.5';
                    s.classList.remove('hidden');
                } else {
                    b.className = 'flex-1 py-2.5 px-3 rounded-xl text-xs font-bold transition-all text-slate-400 hover:text-white flex items-center justify-center space-x-1.5';
                    s.classList.add('hidden');
                }
            }
            if (pestana === 'usuarios') { cargarListaUsuariosAdmin(); }
        }

        async function cargarListaUsuariosAdmin() {
            mostrarLoader(true);
            try {
                const res = await fetch('api.php?action=usuarios');
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    AdminState.listaUsuariosCache = json.data;
                    renderListaUsuariosAdmin(json.data);
                }
            } catch (err) {
                mostrarToastAdmin('Error al cargar lista de usuarios', 'error');
            } finally {
                mostrarLoader(false);
            }
        }

        function filtrarListaUsuariosAdmin(query) {
            query = (query || '').toLowerCase().trim();
            if (!AdminState.listaUsuariosCache) return;
            if (!query) {
                renderListaUsuariosAdmin(AdminState.listaUsuariosCache);
                return;
            }
            const filtrados = AdminState.listaUsuariosCache.filter(u => {
                const nom = (u.nombre + ' ' + u.apellido).toLowerCase();
                const mail = (u.email || '').toLowerCase();
                return nom.includes(query) || mail.includes(query);
            });
            renderListaUsuariosAdmin(filtrados);
        }

        function renderListaUsuariosAdmin(lista) {
            const container = document.getElementById('admin-lista-usuarios');
            if (!container) return;
            container.innerHTML = '';

            if (lista.length === 0) {
                container.innerHTML = `<p class="text-xs text-slate-400 text-center py-8 bg-slate-900 rounded-2xl border border-slate-800">No se encontraron usuarios coincidentes.</p>`;
                return;
            }

            lista.forEach(u => {
                const item = document.createElement('div');
                item.className = 'p-3 bg-slate-900 border border-slate-800 rounded-2xl flex items-center justify-between gap-3 text-xs shadow-sm hover:border-slate-700 transition';
                const esPropioAdmin = (u.id == AdminState.currentAdminUserId);
                item.innerHTML = `
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="font-bold text-white text-sm">${escaparHtmlAdmin(u.nombre)} ${escaparHtmlAdmin(u.apellido)}</span>
                            <span class="text-[9px] uppercase px-1.5 py-0.2 rounded font-black ${u.rol === 'admin' ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30' : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'}">${u.rol}</span>
                            ${esPropioAdmin ? '<span class="text-[9px] font-black px-1.5 py-0.2 rounded bg-amber-500/10 text-amber-300 border border-amber-500/20">Tu Cuenta</span>' : ''}
                        </div>
                        <p class="text-slate-400 text-[11px] truncate mt-0.5">${escaparHtmlAdmin(u.email)} • ${parseFloat(u.peso_corporal).toFixed(1)} kg • ${u.telefono || 'Sin teléfono'}</p>
                    </div>
                    <div class="flex items-center space-x-1.5 flex-shrink-0">
                        <span class="text-slate-500 text-[11px] mr-1 hidden sm:inline">${u.activo == 1 ? 'Activo' : 'Inactivo'}</span>
                        <button type="button" onclick="editarUsuarioAdmin(${u.id})" class="p-2 text-slate-300 hover:text-white rounded-xl bg-slate-800/80 hover:bg-slate-700 transition" title="Editar datos del atleta">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        </button>
                        ${!esPropioAdmin ? `
                        <button type="button" onclick="eliminarUsuarioAdmin(${u.id}, '${escaparHtmlAdmin(u.nombre)} ${escaparHtmlAdmin(u.apellido)}')" class="p-2 text-rose-400 hover:text-rose-200 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 transition" title="Eliminar atleta permanentemente">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                        ` : ''}
                    </div>
                `;
                container.appendChild(item);
            });
        }

        async function eliminarUsuarioAdmin(id, nombre) {
            if (!id) return;
            const confirmacion = confirm(`⚠️ ADVERTENCIA DE SEGURIDAD CRÍTICA:\n\n¿Estás completamente seguro de que deseas eliminar permanentemente al atleta "${nombre}"?\n\nEsta acción borrará de forma irreversible todas sus rutinas, registros de cargas históricas (S1..S5) y fotografías de progreso de la base de datos.\n\n¿Deseas proceder con la eliminación definitiva?`);
            if (!confirmacion) return;

            mostrarLoader(true);
            try {
                const res = await fetch('api.php?action=usuario_eliminar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const json = await res.json();
                if (json.success) {
                    mostrarToastAdmin('Atleta eliminado permanentemente de la base de datos.', 'success');
                    if (AdminState.alumnoActivoId == id) {
                        AdminState.alumnoActivoId = null;
                        AdminState.alumnoActual = null;
                    }
                    await cargarAlumnosAdmin();
                    await cargarListaUsuariosAdmin();
                } else {
                    mostrarToastAdmin(json.message || 'Error al eliminar usuario', 'error');
                }
            } catch (err) {
                mostrarToastAdmin('Error de red al intentar eliminar el atleta', 'error');
            } finally {
                mostrarLoader(false);
            }
        }

        async function cargarImagenesLocalesAdmin() {
            if (AdminState.imagenesLocalesCache) return;
            try {
                const res = await fetch('api.php?action=listar_imagenes_locales');
                const json = await res.json();
                if (json.success && Array.isArray(json.data)) {
                    AdminState.imagenesLocalesCache = json.data;
                    const select = document.getElementById('admin-ej-select-local');
                    if (select) {
                        select.innerHTML = '<option value="assets/ejercicios/default.gif">-- Seleccionar de Galería Local --</option>';
                        json.data.forEach(item => {
                            const opt = document.createElement('option');
                            opt.value = item.ruta;
                            opt.textContent = item.nombre;
                            select.appendChild(opt);
                        });
                    }
                }
            } catch (e) {
                // Silencioso
            }
        }

        function onImagenLocalAdminChange(url) {
            document.getElementById('admin-ej-imagen-url').value = url || 'assets/ejercicios/default.gif';
            actualizarPrevisualizacionSf(url);
        }

        function onCategoriaSfChange(cat) {
            if (SF_PRESETS[cat]) {
                const p = SF_PRESETS[cat];
                document.getElementById('admin-ej-nombre').value = p.nombre;
                document.getElementById('admin-ej-imagen-url').value = p.url;
                document.getElementById('admin-ej-obs').value = p.obs;
                const selectLocal = document.getElementById('admin-ej-select-local');
                if (selectLocal) { selectLocal.value = p.url; }
                actualizarPrevisualizacionSf(p.url);
            }
        }

        function actualizarPrevisualizacionSf(url) {
            const img = document.getElementById('admin-ej-preview-img');
            if (img) {
                img.src = url || 'assets/ejercicios/default.gif';
            }
        }

        function abrirModalEjercicioAdmin() {
            const modal = document.getElementById('modal-ejercicio-admin');
            document.getElementById('admin-form-ejercicio').reset();
            document.getElementById('admin-ej-id').value = '';
            document.getElementById('admin-ej-imagen-url').value = 'assets/ejercicios/default.gif';
            document.getElementById('admin-ej-preview-img').src = 'assets/ejercicios/default.gif';
            document.getElementById('admin-modal-ejercicio-titulo').textContent = 'Nuevo Ejercicio de Rutina';
            modal.classList.remove('hidden');
        }

        function cerrarModalEjercicioAdmin() {
            document.getElementById('modal-ejercicio-admin').classList.add('hidden');
        }

        function editarEjercicioAdmin(id) {
            let ej = null;
            for (const d of AdminState.diasRutina) {
                const found = d.ejercicios.find(e => e.id == id);
                if (found) { ej = found; break; }
            }
            if (!ej) return;

            document.getElementById('admin-modal-ejercicio-titulo').textContent = 'Editar Ejercicio';
            document.getElementById('admin-ej-id').value = ej.id;
            document.getElementById('admin-ej-dia').value = ej.dia_semana;
            document.getElementById('admin-ej-nombre').value = ej.nombre_ejercicio;
            document.getElementById('admin-ej-series').value = ej.series;
            document.getElementById('admin-ej-reps').value = ej.repeticiones;
            document.getElementById('admin-ej-1rm').value = ej.un_rm_estimado > 0 ? ej.un_rm_estimado : '';
            document.getElementById('admin-ej-imagen-url').value = ej.imagen_url || 'assets/ejercicios/default.gif';
            document.getElementById('admin-ej-obs').value = ej.observaciones || '';

            const selectLocal = document.getElementById('admin-ej-select-local');
            if (selectLocal && ej.imagen_url) {
                selectLocal.value = ej.imagen_url;
            }

            actualizarPrevisualizacionSf(ej.imagen_url);
            document.getElementById('modal-ejercicio-admin').classList.remove('hidden');
        }

        async function guardarEjercicioAdmin(e) {
            e.preventDefault();
            const id = document.getElementById('admin-ej-id').value;
            const dia = parseInt(document.getElementById('admin-ej-dia').value);
            const nombre = document.getElementById('admin-ej-nombre').value.trim();
            const series = parseInt(document.getElementById('admin-ej-series').value) || 4;
            const reps = document.getElementById('admin-ej-reps').value.trim() || '8-10';
            const unRm = parseFloat(document.getElementById('admin-ej-1rm').value) || 0;
            const imagenUrl = document.getElementById('admin-ej-imagen-url').value.trim() || 'assets/ejercicios/default.gif';
            const obs = document.getElementById('admin-ej-obs').value.trim();

            if (!nombre) {
                mostrarToastAdmin('El nombre del ejercicio es obligatorio', 'error');
                return;
            }

            const rutinaId = AdminState.rutinaActual ? AdminState.rutinaActual.id : 1;
            mostrarLoader(true);

            try {
                const payload = {
                    id: id ? parseInt(id) : 0,
                    rutina_id: rutinaId,
                    dia_semana: dia,
                    nombre_ejercicio: nombre,
                    series: series,
                    repeticiones: reps,
                    un_rm_estimado: unRm,
                    imagen_url: imagenUrl,
                    observaciones: obs
                };

                const res = await fetch('api.php?action=ejercicio_guardar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const json = await res.json();
                if (json.success) {
                    cerrarModalEjercicioAdmin();
                    mostrarToastAdmin(json.message || 'Ejercicio guardado correctamente', 'success');
                    await cargarDatosAlumnoSeleccionado(AdminState.alumnoActivoId);
                } else {
                    mostrarToastAdmin(json.message || 'Error al guardar ejercicio', 'error');
                }
            } catch (err) {
                mostrarToastAdmin('Error de red al guardar ejercicio', 'error');
            } finally {
                mostrarLoader(false);
            }
        }

        async function eliminarEjercicioAdmin(id) {
            if (!confirm('¿Eliminar este ejercicio de la rutina del alumno?')) return;
            mostrarLoader(true);
            try {
                const res = await fetch('api.php?action=ejercicio_eliminar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const json = await res.json();
                if (json.success) {
                    mostrarToastAdmin('Ejercicio eliminado', 'success');
                    await cargarDatosAlumnoSeleccionado(AdminState.alumnoActivoId);
                } else {
                    mostrarToastAdmin(json.message || 'Error al eliminar', 'error');
                }
            } catch (err) {
                mostrarToastAdmin('Error al eliminar', 'error');
            } finally {
                mostrarLoader(false);
            }
        }

        function abrirComparadorAdmin() {
            if (!AdminState.fotosProgreso || AdminState.fotosProgreso.length < 1) {
                mostrarToastAdmin('No hay fotos para comparar en este alumno', 'info');
                return;
            }
            const selA = document.getElementById('admin-comp-select-a');
            const selB = document.getElementById('admin-comp-select-b');
            selA.innerHTML = '';
            selB.innerHTML = '';

            AdminState.fotosProgreso.forEach(f => {
                const text = `S${f.semana} - ${f.tipo_angulo} (${f.fecha_subida})`;
                const optA = document.createElement('option');
                optA.value = f.id;
                optA.textContent = text;
                selA.appendChild(optA);

                const optB = document.createElement('option');
                optB.value = f.id;
                optB.textContent = text;
                selB.appendChild(optB);
            });

            selA.selectedIndex = 0;
            selB.selectedIndex = AdminState.fotosProgreso.length > 1 ? AdminState.fotosProgreso.length - 1 : 0;

            renderComparacionAdmin();
            document.getElementById('admin-modal-comparador').classList.remove('hidden');
        }

        function cerrarComparadorAdmin() {
            document.getElementById('admin-modal-comparador').classList.add('hidden');
        }

        function renderComparacionAdmin() {
            const idA = document.getElementById('admin-comp-select-a').value;
            const idB = document.getElementById('admin-comp-select-b').value;
            const fA = AdminState.fotosProgreso.find(f => f.id == idA);
            const fB = AdminState.fotosProgreso.find(f => f.id == idB);

            if (fA) {
                document.getElementById('admin-comp-img-a').src = fA.ruta_imagen;
                document.getElementById('admin-comp-tag-a').textContent = `S${fA.semana}`;
                document.getElementById('admin-comp-fecha-a').textContent = `${fA.fecha_subida} (${fA.tipo_angulo})`;
                document.getElementById('admin-comp-obs-a').textContent = fA.observaciones || 'Sin notas';
            }
            if (fB) {
                document.getElementById('admin-comp-img-b').src = fB.ruta_imagen;
                document.getElementById('admin-comp-tag-b').textContent = `S${fB.semana}`;
                document.getElementById('admin-comp-fecha-b').textContent = `${fB.fecha_subida} (${fB.tipo_angulo})`;
                document.getElementById('admin-comp-obs-b').textContent = fB.observaciones || 'Sin notas';
            }
        }

        function abrirModalCrearUsuarioAdmin() {
            const modal = document.getElementById('modal-usuario-admin');
            document.getElementById('admin-form-usuario').reset();
            document.getElementById('admin-usr-id').value = '';
            document.getElementById('admin-modal-usuario-titulo').textContent = 'Nuevo Atleta / Usuario';
            modal.classList.remove('hidden');
        }

        function cerrarModalUsuarioAdmin() {
            document.getElementById('modal-usuario-admin').classList.add('hidden');
        }

        async function editarUsuarioAdmin(id) {
            mostrarLoader(true);
            try {
                const res = await fetch(`api.php?action=cliente&id=${id}`);
                const json = await res.json();
                if (json.success && json.data.cliente) {
                    const c = json.data.cliente;
                    document.getElementById('admin-modal-usuario-titulo').textContent = 'Editar Datos del Atleta';
                    document.getElementById('admin-usr-id').value = c.id;
                    document.getElementById('admin-usr-nombre').value = c.nombre;
                    document.getElementById('admin-usr-apellido').value = c.apellido;
                    document.getElementById('admin-usr-email').value = c.email;
                    document.getElementById('admin-usr-peso').value = c.peso_corporal;
                    document.getElementById('admin-usr-telefono').value = c.telefono || '';
                    document.getElementById('admin-usr-rol').value = c.rol || 'cliente';
                    document.getElementById('admin-usr-observaciones').value = c.observaciones || '';
                    document.getElementById('modal-usuario-admin').classList.remove('hidden');
                } else {
                    mostrarToastAdmin(json.message || 'Error al cargar usuario', 'error');
                }
            } catch (err) {
                mostrarToastAdmin('Error al cargar datos del usuario', 'error');
            } finally {
                mostrarLoader(false);
            }
        }

        async function guardarUsuarioAdmin(e) {
            e.preventDefault();
            const id = document.getElementById('admin-usr-id').value;
            const payload = {
                id: id ? parseInt(id) : 0,
                nombre: document.getElementById('admin-usr-nombre').value.trim(),
                apellido: document.getElementById('admin-usr-apellido').value.trim(),
                email: document.getElementById('admin-usr-email').value.trim(),
                password: document.getElementById('admin-usr-password').value.trim(),
                rol: document.getElementById('admin-usr-rol').value,
                peso_corporal: parseFloat(document.getElementById('admin-usr-peso').value) || 70,
                telefono: document.getElementById('admin-usr-telefono').value.trim(),
                observaciones: document.getElementById('admin-usr-observaciones').value.trim()
            };

            mostrarLoader(true);
            try {
                const res = await fetch('api.php?action=usuario_guardar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (json.success) {
                    cerrarModalUsuarioAdmin();
                    mostrarToastAdmin(json.message || 'Usuario guardado', 'success');
                    await cargarListaUsuariosAdmin();
                    await cargarAlumnosAdmin();
                } else {
                    mostrarToastAdmin(json.message || 'Error al guardar usuario', 'error');
                }
            } catch (err) {
                mostrarToastAdmin('Error de conexión', 'error');
            } finally {
                mostrarLoader(false);
            }
        }

        async function cerrarSesionAdmin() {
            try {
                await fetch('api.php?action=logout');
            } catch (e) {
                // Silencioso
            }
            window.location.href = 'index.html#login';
        }
    </script>
</body>
</html>
