/**
 * ==============================================================================
 * FIT TRACKER APP - LÓGICA DE APLICACIÓN FRONTEND V2 (app.js)
 * Arquitectura SPA Mobile-First, Roles (Admin/Cliente), Fotos de Progreso y 
 * Galería Técnica de Ejercicios 100% Local.
 * ==============================================================================
 */

// Estado global de la aplicación
const AppState = {
    currentUser: null,       // Objeto de usuario autenticado
    isAdmin: false,          // Boolean: rol admin
    activeView: 'rutina',    // 'rutina' | 'progreso'
    clientes: [],            // Listado de clientes (si es admin)
    clienteActivoId: null,   // ID del cliente seleccionado
    clienteActual: null,     // Datos del cliente en pantalla
    rutinaActual: null,      // Rutina activa
    semanaActiva: 1,         // S1 por defecto
    diaFiltro: 0,            // 0 = Todos los días, 1..N = Día específico
    diasRutina: [],          // Ejercicios agrupados por día
    fotosProgreso: [],       // Lista de fotos de progreso del atleta
    fotoCompararA: null,     // ID foto A para comparador
    fotoCompararB: null,     // ID foto B para comparador
    cargando: false,
    saveDebounceTimers: {}
};

// Ruta relativa local para evitar errores de IP o servidor externo
const API_URL = 'api.php';

// ==============================================================================
// 1. INICIALIZACIÓN Y CONTROL DE SESIÓN
// ==============================================================================

document.addEventListener('DOMContentLoaded', () => {
    initApp();
    registerServiceWorker();
});

async function initApp() {
    setupEventListeners();
    await verificarSesion();
}

function registerServiceWorker() {
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('./sw.js').catch(() => {});
        });
    }
}

/**
 * Verifica si hay una sesión activa en el servidor
 */
async function verificarSesion() {
    mostrarCargando(true);
    try {
        const res = await fetch(`${API_URL}?action=check_session`);
        const json = await res.json();

        if (json.success && json.data.authenticated) {
            establecerUsuarioAutenticado(json.data.user);
            mostrarVistaApp();
            await cargarDatosIniciales();
        } else {
            AppState.currentUser = null;
            AppState.isAdmin = false;
            mostrarVistaLogin();
        }
    } catch (err) {
        mostrarVistaLogin();
    } finally {
        mostrarCargando(false);
    }
}

function establecerUsuarioAutenticado(user) {
    AppState.currentUser = user;
    AppState.isAdmin = (user.rol === 'admin');

    // Elementos de cabecera de usuario
    const userBadge = document.getElementById('user-role-badge');
    const userEmailSpan = document.getElementById('user-email-display');
    if (userBadge) {
        userBadge.textContent = AppState.isAdmin ? 'ENTRENADOR' : 'ATLETA';
        userBadge.className = AppState.isAdmin 
            ? 'text-[10px] uppercase font-black px-1.5 py-0.5 rounded bg-cyan-500/10 text-cyan-400 border border-cyan-500/30'
            : 'text-[10px] uppercase font-black px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/30';
    }
    if (userEmailSpan) {
        userEmailSpan.textContent = user.nombre;
    }

    // Mostrar u ocultar controles exclusivos de Administrador
    document.querySelectorAll('.admin-only').forEach(el => {
        if (AppState.isAdmin) el.classList.remove('hidden');
        else el.classList.add('hidden');
    });

    // Si es cliente, el clienteActivoId siempre es su propio ID
    if (!AppState.isAdmin) {
        AppState.clienteActivoId = user.id;
    }
}

function mostrarVistaLogin() {
    document.getElementById('view-login').classList.remove('hidden');
    document.getElementById('view-app').classList.add('hidden');
    cambiarModoAuth('login');
}

function mostrarVistaApp() {
    document.getElementById('view-login').classList.add('hidden');
    document.getElementById('view-app').classList.remove('hidden');
}

function cambiarModoAuth(modo) {
    const formLogin = document.getElementById('form-login');
    const formRegistro = document.getElementById('form-registro');
    const tabLogin = document.getElementById('tab-btn-login');
    const tabRegistro = document.getElementById('tab-btn-registro');
    const subtitulo = document.getElementById('auth-subtitulo');

    if (!formLogin || !formRegistro) return;

    if (modo === 'registro') {
        formLogin.classList.add('hidden');
        formRegistro.classList.remove('hidden');
        if (tabLogin && tabRegistro) {
            tabLogin.className = 'flex-1 py-2 text-xs font-bold rounded-xl transition-all duration-200 text-slate-400 hover:text-white';
            tabRegistro.className = 'flex-1 py-2 text-xs font-black rounded-xl transition-all duration-200 bg-emerald-500 text-slate-950 shadow-md';
        }
        if (subtitulo) {
            subtitulo.textContent = 'Crea tu cuenta de atleta y comienza a registrar tu evolución';
        }
    } else {
        formRegistro.classList.add('hidden');
        formLogin.classList.remove('hidden');
        if (tabLogin && tabRegistro) {
            tabRegistro.className = 'flex-1 py-2 text-xs font-bold rounded-xl transition-all duration-200 text-slate-400 hover:text-white';
            tabLogin.className = 'flex-1 py-2 text-xs font-black rounded-xl transition-all duration-200 bg-emerald-500 text-slate-950 shadow-md';
        }
        if (subtitulo) {
            subtitulo.textContent = 'Ingresa para acceder a tus microciclos y fotos de progreso';
        }
    }
}

/**
 * Procesa el registro de una nueva cuenta pública (Solo Rol Cliente)
 */
async function procesarRegistro() {
    const nombre = document.getElementById('registro-nombre')?.value.trim() || '';
    const apellido = document.getElementById('registro-apellido')?.value.trim() || '';
    const email = document.getElementById('registro-email')?.value.trim() || '';
    const password = document.getElementById('registro-password')?.value || '';
    const peso = parseFloat(document.getElementById('registro-peso')?.value) || 70.0;
    const telefono = document.getElementById('registro-telefono')?.value.trim() || '';

    if (!nombre || !apellido || !email || !password) {
        mostrarToast('Nombre, apellido, email y contraseña son obligatorios', 'error');
        return;
    }

    if (password.length < 6) {
        mostrarToast('La contraseña debe tener al menos 6 caracteres', 'error');
        return;
    }

    mostrarCargando(true);
    try {
        const res = await fetch(`${API_URL}?action=registro`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nombre,
                apellido,
                email,
                password,
                peso_corporal: peso,
                telefono
            })
        });
        const json = await res.json();

        if (json.success) {
            mostrarToast('¡Cuenta creada con éxito! Bienvenido, ' + (json.data?.nombre || nombre), 'success');
            if (json.data && json.data.id) {
                establecerUsuarioAutenticado(json.data);
                mostrarVistaApp();
                await cargarDatosIniciales();
            } else {
                await procesarLogin(email, password);
            }
        } else {
            mostrarToast(json.message || 'Error al registrar la cuenta', 'error');
        }
    } catch (err) {
        mostrarToast('Error de red al conectar con el servidor', 'error');
    } finally {
        mostrarCargando(false);
    }
}

/**
 * Procesa el formulario de Login
 */
async function procesarLogin(email, password) {
    mostrarCargando(true);
    try {
        const res = await fetch(`${API_URL}?action=login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const json = await res.json();

        if (json.success) {
            mostrarToast('¡Bienvenido, ' + json.data.nombre + '!', 'success');
            establecerUsuarioAutenticado(json.data);
            mostrarVistaApp();
            await cargarDatosIniciales();
        } else {
            mostrarToast(json.message || 'Error al iniciar sesión', 'error');
        }
    } catch (err) {
        mostrarToast('Error de red al conectar con el servidor', 'error');
    } finally {
        mostrarCargando(false);
    }
}

/**
 * Cierra la sesión activa
 */
async function cerrarSesion() {
    mostrarCargando(true);
    try {
        await fetch(`${API_URL}?action=logout`);
        AppState.currentUser = null;
        AppState.isAdmin = false;
        mostrarToast('Sesión cerrada correctamente', 'info');
        mostrarVistaLogin();
    } catch (err) {
        mostrarVistaLogin();
    } finally {
        mostrarCargando(false);
    }
}

// Botones rápidos de demo en pantalla de login
function autocompletarLogin(email, pass) {
    document.getElementById('login-email').value = email;
    document.getElementById('login-password').value = pass;
    procesarLogin(email, pass);
}

// ==============================================================================
// 2. CARGA DE DATOS INICIALES (CLIENTES, RUTINA O FOTOS)
// ==============================================================================

async function cargarDatosIniciales() {
    mostrarCargando(true);
    mostrarSkeletonFicha(true);
    try {
        if (AppState.isAdmin) {
            const res = await fetch(`${API_URL}?action=clientes`);
            const json = await res.json();
            if (json.success && json.data.length > 0) {
                AppState.clientes = json.data;
                actualizarSelectoresCliente();
                if (!AppState.clienteActivoId || !AppState.clientes.some(c => c.id == AppState.clienteActivoId)) {
                    AppState.clienteActivoId = AppState.clientes[0].id;
                }
                AppState.clienteActual = AppState.clientes.find(c => c.id == AppState.clienteActivoId) || null;
            } else {
                AppState.clientes = [];
                actualizarSelectoresCliente();
            }
        } else {
            AppState.clienteActivoId = AppState.currentUser.id;
        }

        await cambiarVistaPrincipal(AppState.activeView);
    } catch (err) {
        mostrarToast('Error al inicializar datos', 'error');
    } finally {
        mostrarCargando(false);
    }
}

function cambiarVistaPrincipal(vista) {
    AppState.activeView = vista;

    const btnTabRutina = document.getElementById('tab-btn-rutina');
    const btnTabProgreso = document.getElementById('tab-btn-progreso');
    const seccionRutina = document.getElementById('seccion-rutina');
    const seccionProgreso = document.getElementById('seccion-progreso');

    if (vista === 'rutina') {
        btnTabRutina.className = 'flex-1 py-2.5 px-4 rounded-xl text-xs font-black transition-all bg-emerald-500 text-slate-950 shadow-md shadow-emerald-500/20';
        btnTabProgreso.className = 'flex-1 py-2.5 px-4 rounded-xl text-xs font-bold transition-all text-slate-400 hover:text-white';
        seccionRutina.classList.remove('hidden');
        seccionProgreso.classList.add('hidden');
        if (AppState.clienteActivoId) {
            cargarRutinaCliente(AppState.clienteActivoId, AppState.semanaActiva);
        }
    } else {
        btnTabProgreso.className = 'flex-1 py-2.5 px-4 rounded-xl text-xs font-black transition-all bg-emerald-500 text-slate-950 shadow-md shadow-emerald-500/20';
        btnTabRutina.className = 'flex-1 py-2.5 px-4 rounded-xl text-xs font-bold transition-all text-slate-400 hover:text-white';
        seccionProgreso.classList.remove('hidden');
        seccionRutina.classList.add('hidden');
        if (AppState.clienteActivoId) {
            cargarFotosProgreso(AppState.clienteActivoId);
        }
    }
}

function normalizarTextoApp(txt) {
    return (txt || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
}

function asegurarBuscadorClienteTopApp() {
    let input = document.getElementById('buscar-cliente-top');
    if (input) return input;

    // Fallback automático si el navegador cargó un index.html cacheado con <select id="select-cliente">
    const oldSelect = document.getElementById('select-cliente');
    if (oldSelect && oldSelect.parentElement) {
        const container = oldSelect.parentElement;
        container.id = 'buscador-cliente-top-wrapper';
        container.className = 'admin-only relative flex-1 sm:flex-initial sm:w-60';
        container.innerHTML = `
            <div class="relative flex items-center">
                <input
                    type="text"
                    id="buscar-cliente-top"
                    placeholder="Buscar o elegir alumno..."
                    autocomplete="off"
                    oninput="filtrarClientesTopApp(this.value)"
                    onfocus="filtrarClientesTopApp(this.value)"
                    onclick="filtrarClientesTopApp(this.value)"
                    class="w-full bg-slate-900 border border-slate-700 hover:border-slate-600 focus:border-emerald-500 text-xs font-bold text-white rounded-xl pl-2.5 pr-12 py-1.5 outline-none focus:ring-2 focus:ring-emerald-500/20 transition truncate"
                />
                <div class="absolute right-2 flex items-center gap-1">
                    <button
                        type="button"
                        id="btn-limpiar-cliente-top"
                        onclick="limpiarBuscadorClienteTopApp()"
                        class="hidden text-slate-400 hover:text-white p-0.5 rounded transition"
                        title="Limpiar búsqueda"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                    <button
                        type="button"
                        onclick="toggleDropdownClientesTopApp()"
                        class="text-slate-400 hover:text-emerald-400 p-0.5 rounded transition"
                        title="Ver lista de alumnos"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                </div>
            </div>
            <div
                id="resultados-cliente-top"
                class="hidden absolute left-0 right-0 mt-1 max-h-60 overflow-y-auto rounded-xl bg-slate-900 border border-slate-700 shadow-2xl z-50"
            ></div>
        `;
        input = document.getElementById('buscar-cliente-top');
    }
    return input;
}

function toggleDropdownClientesTopApp() {
    const dropdown = document.getElementById('resultados-cliente-top');
    const input = asegurarBuscadorClienteTopApp();
    if (!dropdown) return;
    if (!dropdown.classList.contains('hidden')) {
        dropdown.classList.add('hidden');
    } else {
        filtrarClientesTopApp(input ? input.value : '');
        if (input) input.focus();
    }
}

function actualizarSelectoresCliente() {
    const input = asegurarBuscadorClienteTopApp();
    if (!input) return;

    if (!AppState.clientes || AppState.clientes.length === 0) {
        input.value = '';
        input.placeholder = 'Sin alumnos activos';
        return;
    }

    const clienteSel = AppState.clientes.find(c => c.id == AppState.clienteActivoId) || AppState.clientes[0];
    if (clienteSel) {
        input.value = `${clienteSel.nombre || ''} ${clienteSel.apellido || ''}`.trim();
    }
}

function filtrarClientesTopApp(texto) {
    const dropdown = document.getElementById('resultados-cliente-top');
    const btnLimpiar = document.getElementById('btn-limpiar-cliente-top');
    if (!dropdown) return;

    const lista = Array.isArray(AppState.clientes) ? AppState.clientes : [];
    const qRaw = (texto || '').trim();
    const qNorm = normalizarTextoApp(qRaw);

    if (btnLimpiar) {
        if (qRaw.length > 0) btnLimpiar.classList.remove('hidden');
        else btnLimpiar.classList.add('hidden');
    }

    const clienteActualObj = lista.find(c => c.id == AppState.clienteActivoId);
    const nombreActivoNorm = clienteActualObj
        ? normalizarTextoApp(`${clienteActualObj.nombre || ''} ${clienteActualObj.apellido || ''}`)
        : '';

    const usarFiltro = qNorm.length > 0 && qNorm !== nombreActivoNorm;

    const filtrados = usarFiltro
        ? lista.filter(c => {
            const nom = normalizarTextoApp(`${c.nombre || ''} ${c.apellido || ''}`);
            const nomInv = normalizarTextoApp(`${c.apellido || ''} ${c.nombre || ''}`);
            const email = normalizarTextoApp(c.email || '');
            return nom.includes(qNorm) || nomInv.includes(qNorm) || email.includes(qNorm);
        })
        : lista;

    if (filtrados.length === 0) {
        dropdown.innerHTML = `<div class="px-3 py-2 text-xs text-slate-400">No se encontraron alumnos</div>`;
        dropdown.classList.remove('hidden');
        return;
    }

    dropdown.innerHTML = filtrados.map(c => {
        const nombreCompleto = `${c.nombre || ''} ${c.apellido || ''}`.trim();
        const esActivo = c.id == AppState.clienteActivoId;
        return `
            <button
                type="button"
                onclick="seleccionarClienteTopApp(${c.id})"
                class="w-full text-left px-3 py-2 text-xs hover:bg-slate-800 flex items-center justify-between gap-2 border-b border-slate-800/70 last:border-b-0 transition ${esActivo ? 'bg-emerald-500/10 text-emerald-300' : 'text-slate-200'}"
            >
                <div class="min-w-0">
                    <div class="font-bold text-white truncate">${escaparHtml(nombreCompleto)}</div>
                    ${c.email ? `<div class="text-[11px] text-slate-400 truncate">${escaparHtml(c.email)}</div>` : ''}
                </div>
                ${esActivo ? '<span class="text-[10px] font-black text-emerald-400 flex-shrink-0">Activo</span>' : ''}
            </button>
        `;
    }).join('');

    dropdown.classList.remove('hidden');
}

function seleccionarClienteTopApp(nuevoId) {
    const dropdown = document.getElementById('resultados-cliente-top');
    const input = document.getElementById('buscar-cliente-top');
    const clienteEncontrado = (AppState.clientes || []).find(c => c.id == nuevoId);

    if (dropdown) dropdown.classList.add('hidden');
    if (input && clienteEncontrado) {
        input.value = `${clienteEncontrado.nombre || ''} ${clienteEncontrado.apellido || ''}`.trim();
    }

    nuevoId = parseInt(nuevoId);
    if (nuevoId && nuevoId !== AppState.clienteActivoId) {
        AppState.clienteActivoId = nuevoId;
        if (clienteEncontrado) {
            AppState.clienteActual = clienteEncontrado;
            renderFichaCliente();
        } else {
            mostrarSkeletonFicha(true);
        }
        if (AppState.activeView === 'rutina') {
            cargarRutinaCliente(nuevoId, AppState.semanaActiva);
        } else {
            cargarFotosProgreso(nuevoId);
        }
    }
}

function limpiarBuscadorClienteTopApp() {
    const input = document.getElementById('buscar-cliente-top');
    const btnLimpiar = document.getElementById('btn-limpiar-cliente-top');
    if (input) {
        input.value = '';
        input.focus();
    }
    if (btnLimpiar) btnLimpiar.classList.add('hidden');
    filtrarClientesTopApp('');
}

function mostrarSkeletonFicha(mostrar) {
    const nombre = document.getElementById('cliente-nombre');
    const peso = document.getElementById('cliente-peso');
    const avatar = document.getElementById('cliente-avatar');
    if (!nombre || !peso) return;

    if (mostrar) {
        nombre.innerHTML = '<span class="inline-block w-28 sm:w-36 h-5 bg-slate-800 rounded-lg animate-pulse align-middle"></span>';
        peso.innerHTML = '<span class="inline-block w-14 h-5 bg-slate-800 rounded-lg animate-pulse align-middle"></span>';
        if (avatar) avatar.innerHTML = '<span class="inline-block w-6 h-6 rounded-lg bg-slate-800 animate-pulse"></span>';
    }
}

// ==============================================================================
// 3. RUTINA Y SEGUIMIENTO SEMANAL (S1..S5)
// ==============================================================================

async function cargarRutinaCliente(clienteId, semana = 1) {
    mostrarCargando(true);
    mostrarSkeletonFicha(true);
    try {
        const res = await fetch(`${API_URL}?action=rutina&cliente_id=${clienteId}&semana=${semana}`);
        const json = await res.json();

        if (json.success) {
            AppState.clienteActual = json.data.cliente;
            AppState.rutinaActual = json.data.rutina;
            AppState.semanaActiva = json.data.semana_activa;
            AppState.diasRutina = json.data.dias;

            renderFichaCliente();
            renderNavegadorSemanas();
            renderFiltroDias();
            renderRutinaEjercicios();
        } else {
            mostrarToast(json.message || 'Error al obtener rutina', 'error');
            if (AppState.clienteActual) renderFichaCliente();
        }
    } catch (err) {
        mostrarToast('Error de red al cargar la rutina', 'error');
        if (AppState.clienteActual) renderFichaCliente();
    } finally {
        mostrarCargando(false);
    }
}

function renderFichaCliente() {
    const c = AppState.clienteActual;
    if (!c) return;

    document.getElementById('cliente-nombre').textContent = `${c.nombre} ${c.apellido}`;
    document.getElementById('cliente-peso').textContent = `${parseFloat(c.peso_corporal).toFixed(1)} kg`;

    const obsContainer = document.getElementById('cliente-observaciones-container');
    const obsText = document.getElementById('cliente-observaciones');

    if (obsContainer && obsText) {
        if (c.observaciones && c.observaciones.trim() !== '') {
            obsContainer.classList.remove('hidden');
            obsText.textContent = c.observaciones;
        } else {
            obsContainer.classList.add('hidden');
        }
    }

    const iniciales = `${c.nombre[0] || ''}${c.apellido[0] || ''}`.toUpperCase();
    const avatar = document.getElementById('cliente-avatar');
    if (avatar) avatar.textContent = iniciales;
}

function renderNavegadorSemanas() {
    const container = document.getElementById('semanas-nav');
    if (!container) return;

    container.innerHTML = '';
    const semanas = [1, 2, 3, 4, 5, 6];

    semanas.forEach(s => {
        const esActiva = s === AppState.semanaActiva;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `flex-1 py-3 px-2 rounded-2xl font-bold text-sm text-center transition-all duration-200 touch-btn ${
            esActiva 
                ? 'bg-gradient-to-b from-emerald-400 to-teal-500 text-slate-950 shadow-lg shadow-emerald-500/25 scale-[1.03] ring-2 ring-emerald-300' 
                : 'bg-slate-900/90 text-slate-400 hover:text-white hover:bg-slate-800 border border-slate-800'
        }`;
        
        btn.innerHTML = `
            <div class="text-[10px] uppercase tracking-wider ${esActiva ? 'text-emerald-950 font-extrabold' : 'text-slate-500'}">Semana</div>
            <div class="text-base sm:text-lg font-black tracking-tight">S${s}</div>
        `;

        btn.addEventListener('click', () => {
            if (AppState.semanaActiva !== s) {
                AppState.semanaActiva = s;
                cargarRutinaCliente(AppState.clienteActivoId, s);
            }
        });

        container.appendChild(btn);
    });
}

function renderFiltroDias() {
    const container = document.getElementById('filtro-dias');
    if (!container) return;

    container.innerHTML = '';

    const btnTodos = document.createElement('button');
    btnTodos.className = `px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition touch-btn ${
        AppState.diaFiltro === 0 
            ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40' 
            : 'bg-slate-900 text-slate-400 border border-slate-800'
    }`;
    btnTodos.textContent = 'Todos los Días';
    btnTodos.addEventListener('click', () => {
        AppState.diaFiltro = 0;
        renderFiltroDias();
        renderRutinaEjercicios();
    });
    container.appendChild(btnTodos);

    AppState.diasRutina.forEach(d => {
        const btnDia = document.createElement('button');
        const activo = AppState.diaFiltro === d.dia_semana;
        btnDia.className = `px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition touch-btn ${
            activo 
                ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40' 
                : 'bg-slate-900 text-slate-400 border border-slate-800'
        }`;
        btnDia.textContent = d.titulo;
        btnDia.addEventListener('click', () => {
            AppState.diaFiltro = d.dia_semana;
            renderFiltroDias();
            renderRutinaEjercicios();
        });
        container.appendChild(btnDia);
    });
}

function renderRutinaEjercicios() {
    const container = document.getElementById('ejercicios-container');
    if (!container) return;

    container.innerHTML = '';

    if (!AppState.diasRutina || AppState.diasRutina.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12 px-4 bg-slate-900/60 rounded-3xl border border-slate-800">
                <p class="text-slate-400 text-sm mb-4">No hay ejercicios registrados en esta rutina aún.</p>
                ${AppState.isAdmin ? `
                    <button onclick="abrirModalEjercicio()" class="py-2.5 px-5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold rounded-xl text-sm transition">
                        + Agregar Primer Ejercicio
                    </button>
                ` : ''}
            </div>
        `;
        return;
    }

    const diasAMostrar = AppState.diaFiltro === 0 
        ? AppState.diasRutina 
        : AppState.diasRutina.filter(d => d.dia_semana === AppState.diaFiltro);

    diasAMostrar.forEach(dia => {
        const diaSection = document.createElement('div');
        diaSection.className = 'space-y-3.5 mb-8';

        const headerDia = document.createElement('div');
        headerDia.className = 'flex items-center justify-between px-1';
        headerDia.innerHTML = `
            <div class="flex items-center space-x-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                <h3 class="text-base font-extrabold text-white tracking-wide uppercase">${dia.titulo}</h3>
                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 font-medium">
                    ${dia.ejercicios.length} ${dia.ejercicios.length === 1 ? 'ejercicio' : 'ejercicios'}
                </span>
            </div>
        `;
        diaSection.appendChild(headerDia);

        // BLOQUE DE MOVILIDAD & ENTRADA EN CALOR PREVIA (Req 5)
        if (dia.movilidad && dia.movilidad.trim() !== '') {
            const cardMovilidad = document.createElement('div');
            cardMovilidad.className = 'bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-amber-500/5 border border-amber-500/30 rounded-2xl p-4 shadow-md';
            cardMovilidad.innerHTML = `
                <div class="flex items-center space-x-2 text-amber-400 font-extrabold text-xs uppercase tracking-wider mb-1.5">
                    <svg class="w-4 h-4 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span>Movilidad &amp; Entrada en Calor Previa</span>
                </div>
                <p class="text-xs text-slate-200 whitespace-pre-line leading-relaxed font-medium">
                    ${escaparHtml(dia.movilidad)}
                </p>
            `;
            diaSection.appendChild(cardMovilidad);
        }

        const listEjercicios = document.createElement('div');
        listEjercicios.className = 'space-y-4';

        dia.ejercicios.forEach(ejercicio => {
            const card = crearTarjetaEjercicio(ejercicio);
            listEjercicios.appendChild(card);
        });

        diaSection.appendChild(listEjercicios);
        container.appendChild(diaSection);
    });
}

function crearTarjetaEjercicio(ej) {
    const card = document.createElement('div');
    const carga = ej.carga_actual;
    const completado = parseInt(carga.completado) === 1;
    const unRm = parseFloat(ej.un_rm_estimado) || 0;
    const pesoKg = parseFloat(carga.peso_kg) || 0;
    const porcentaje = parseFloat(carga.porcentaje_carga) || 0;
    const tieneImagen = !!ej.imagen_url;

    card.className = `p-4 sm:p-5 rounded-3xl border transition-all duration-200 ${
        completado 
            ? 'bg-slate-900/90 border-emerald-500/30 shadow-md shadow-emerald-950/20' 
            : 'bg-slate-900 border-slate-800/90 shadow-xl shadow-slate-950/40'
    }`;
    card.id = `ejercicio-card-${ej.id}`;

    // Miniaturas históricas S1..S6
    let badgesHistorialHtml = '';
    if (ej.historial_cargas) {
        for (let s = 1; s <= 6; s++) {
            const h = ej.historial_cargas[s];
            const hPeso = h && parseFloat(h.peso_kg) > 0 ? `${parseFloat(h.peso_kg).toFixed(1)}k` : '-';
            const esSemanaActual = s === AppState.semanaActiva;
            badgesHistorialHtml += `
                <div class="flex flex-col items-center py-1 px-1 rounded-lg text-center ${
                    esSemanaActual 
                        ? 'bg-emerald-500/20 text-emerald-300 font-bold border border-emerald-500/40' 
                        : 'bg-slate-800/60 text-slate-400 font-medium'
                }">
                    <span class="text-[10px] uppercase text-slate-400">S${s}</span>
                    <span id="hist-badge-${ej.id}-${s}" class="text-xs font-semibold">${hPeso}</span>
                </div>
            `;
        }
    }

    // Configuración de Series y Pesos individuales (Planilla de Fuerza)
    const numSeries = Math.max(1, parseInt(ej.series) || 4);
    let seriesPesos = [];
    if (carga && Array.isArray(carga.pesos_series) && carga.pesos_series.length > 0) {
        seriesPesos = carga.pesos_series;
    } else if (pesoKg > 0) {
        seriesPesos = Array(numSeries).fill(pesoKg);
    }

    let seriesInputsHtml = '';
    for (let i = 0; i < numSeries; i++) {
        const sVal = (seriesPesos[i] !== undefined && seriesPesos[i] !== null && seriesPesos[i] !== 0 && seriesPesos[i] !== '0') ? seriesPesos[i] : '';
        seriesInputsHtml += `
            <div class="flex-1 min-w-[70px] bg-slate-900 border border-slate-700/80 hover:border-slate-600 focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500/20 rounded-xl p-2 text-center transition">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Serie ${i + 1}</span>
                <div class="flex items-center justify-center">
                    <input 
                        type="number" 
                        step="0.5" 
                        id="input-serie-${ej.id}-${i}" 
                        value="${sVal}" 
                        placeholder="0.0"
                        class="w-full bg-transparent text-center text-sm sm:text-base font-black text-white outline-none"
                        oninput="onInputSeriePesoChange(${ej.id})"
                        onblur="guardarCargaInmediata(${ej.id})"
                    />
                    <span class="text-[10px] text-slate-500 font-bold ml-0.5">kg</span>
                </div>
            </div>
        `;
    }

    card.innerHTML = `
        <!-- Encabezado de Ejercicio con Miniatura de Técnica -->
        <div class="flex items-start justify-between gap-2 mb-3">
            <div class="flex items-start gap-3 flex-1">
                <!-- Miniatura o botón demostrativo de técnica -->
                <div onclick="verTecnicaEjercicio(${ej.id})" class="cursor-pointer group flex-shrink-0 relative w-12 h-12 rounded-xl bg-slate-950 border border-slate-800 overflow-hidden flex items-center justify-center hover:border-emerald-500/50 transition">
                    ${tieneImagen ? `
                        <img src="${ej.imagen_url}" 
                             alt="${escaparHtml(ej.nombre_ejercicio)}" 
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-300"
                             onerror="this.onerror=null; this.src='assets/ejercicios/default.gif';">
                        <div class="absolute inset-0 bg-slate-950/30 group-hover:bg-transparent transition flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-white drop-shadow" fill="currentColor" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                        </div>
                    ` : `
                        <div class="text-slate-500 group-hover:text-emerald-400 transition flex flex-col items-center justify-center p-2">
                            <img src="assets/ejercicios/default.gif" alt="Placeholder" class="w-6 h-6 opacity-40">
                        </div>
                    `}
                </div>

                <div class="flex-1">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-800 text-emerald-400 border border-slate-700/80">
                            ${ej.series} Series × ${ej.repeticiones}
                        </span>
                        ${unRm > 0 ? `
                            <span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-800 text-cyan-400 border border-slate-700/80">
                                1RM: ${unRm} kg
                            </span>
                        ` : ''}
                    </div>
                    <h4 class="text-base font-bold text-white tracking-tight leading-snug">${ej.nombre_ejercicio}</h4>
                </div>
            </div>

            <!-- Botones de Acción para Administradores -->
            ${AppState.isAdmin ? `
                <div class="flex items-center space-x-1 text-slate-400">
                    <button onclick="editarEjercicio(${ej.id})" class="p-2 hover:text-white rounded-lg hover:bg-slate-800 transition" title="Editar Ejercicio">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    </button>
                    <button onclick="confirmarEliminarEjercicio(${ej.id})" class="p-2 hover:text-rose-400 rounded-lg hover:bg-slate-800 transition" title="Eliminar Ejercicio">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </div>
            ` : `
                <button onclick="verTecnicaEjercicio(${ej.id})" class="text-xs font-bold text-cyan-400 hover:text-cyan-300 py-1 px-2 rounded-lg bg-cyan-500/10 hover:bg-cyan-500/20 transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    <span>Técnica</span>
                </button>
            `}
        </div>

        <!-- Detalle u Observaciones Técnicas -->
        ${ej.observaciones ? `
            <div class="text-xs text-slate-300 bg-slate-950/60 p-2.5 rounded-xl mb-4 border border-slate-800/80 flex items-start space-x-2">
                <svg class="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>${ej.observaciones}</span>
            </div>
        ` : ''}

        <!-- CONTROLES DE CARGA POR SERIE Y % PARA LA SEMANA ACTIVA -->
        <div class="bg-slate-950/80 p-3.5 sm:p-4 rounded-2xl border border-slate-800 space-y-3">
            <div class="flex items-center justify-between text-xs text-slate-400 font-semibold">
                <span class="text-emerald-400 uppercase tracking-wider font-bold">Carga Semana ${AppState.semanaActiva} (S${AppState.semanaActiva})</span>
                <span id="save-status-${ej.id}" class="text-[11px] text-slate-500 font-medium">Auto-guardado</span>
            </div>

            <!-- Planilla de Fuerza: Pesos individuales por Serie -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-300">Pesos por Serie (kg)</label>
                    <span class="text-[11px] text-slate-400 font-medium">${numSeries} series prescritas</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    ${seriesInputsHtml}
                </div>
            </div>

            <!-- Resumen de Carga Top y % Intensidad 1RM -->
            <div class="flex items-center justify-between bg-slate-900/80 px-3 py-2 rounded-xl border border-slate-800 text-xs">
                <div class="flex items-center space-x-2">
                    <span class="text-[11px] font-bold text-slate-400 uppercase">Carga Top:</span>
                    <span id="label-top-peso-${ej.id}" class="font-black text-white font-mono text-sm">${pesoKg > 0 ? pesoKg + ' kg' : '--'}</span>
                    <input type="hidden" id="input-peso-${ej.id}" value="${pesoKg > 0 ? pesoKg : ''}" />
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-[11px] font-bold text-slate-400 uppercase">% Intensidad (1RM):</span>
                    <span id="label-porcentaje-${ej.id}" class="font-black text-cyan-400 font-mono text-sm">${porcentaje > 0 ? porcentaje + '%' : '--'}</span>
                    <input type="hidden" id="input-porcentaje-${ej.id}" value="${porcentaje > 0 ? porcentaje : ''}" />
                </div>
            </div>

            <!-- Notas / RPE y Checkbox de Completado -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 pt-1 border-t border-slate-800/80">
                <input 
                    type="text" 
                    id="input-notas-${ej.id}" 
                    value="${carga.notas ? escaparHtml(carga.notas) : ''}" 
                    placeholder="Notas o RPE (ej. RPE 8, buena técnica)..."
                    class="flex-1 h-10 bg-slate-900 border border-slate-800 focus:border-slate-600 rounded-xl px-3 text-xs text-slate-300 outline-none"
                    oninput="programarGuardado(${ej.id})"
                    onblur="guardarCargaInmediata(${ej.id})"
                />

                <button 
                    type="button" 
                    id="btn-completado-${ej.id}"
                    onclick="toggleCompletado(${ej.id})"
                    class="h-10 px-4 rounded-xl text-xs font-bold transition-all duration-200 flex items-center justify-center space-x-2 touch-btn ${
                        completado 
                            ? 'bg-emerald-500 text-slate-950 font-black shadow-md shadow-emerald-500/20' 
                            : 'bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700'
                    }"
                >
                    <svg class="w-4 h-4 ${completado ? 'text-slate-950' : 'text-slate-400'}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    <span>${completado ? 'Completado' : 'Marcar Hecho'}</span>
                </button>
            </div>
        </div>

        <!-- HISTORIAL DE PROGRESIÓN S1..S6 -->
        <div class="mt-3 pt-2.5 border-t border-slate-800/60">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Progresión (S1 a S6)</span>
                ${AppState.semanaActiva < 6 ? `
                    <button onclick="copiarASiguienteSemana(${ej.id})" class="text-[11px] font-bold text-teal-400 hover:text-teal-300 flex items-center space-x-1" title="Copiar exactamente las mismas cargas a la semana siguiente">
                        <span>Copiar a S${AppState.semanaActiva + 1}</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                ` : ''}
            </div>
            <div class="grid grid-cols-6 gap-1.5">
                ${badgesHistorialHtml}
            </div>
        </div>
    `;

    return card;
}

// CÁLCULOS REACTIVOS Y AUTO-GUARDADO (LIBERTAD TOTAL DE EDICIÓN CARGA Y %)
function buscarEjercicioPorId(ejercicioId) {
    for (const dia of AppState.diasRutina) {
        const found = dia.ejercicios.find(e => e.id == ejercicioId);
        if (found) return found;
    }
    return null;
}

function onInputSeriePesoChange(ejercicioId) {
    const ej = buscarEjercicioPorId(ejercicioId);
    if (!ej) return;

    const numSeries = Math.max(1, parseInt(ej.series) || 4);
    const unRm = parseFloat(ej.un_rm_estimado) || 0;
    const inputPeso = document.getElementById(`input-peso-${ejercicioId}`);
    const labelTop = document.getElementById(`label-top-peso-${ejercicioId}`);
    const inputPorcentaje = document.getElementById(`input-porcentaje-${ejercicioId}`);
    const labelPorcentaje = document.getElementById(`label-porcentaje-${ejercicioId}`);

    const pesos = [];
    for (let i = 0; i < numSeries; i++) {
        const inp = document.getElementById(`input-serie-${ejercicioId}-${i}`);
        if (inp && inp.value !== '') {
            const val = parseFloat(inp.value) || 0;
            if (val > 0) pesos.push(val);
        }
    }

    const maxPeso = pesos.length > 0 ? Math.max(...pesos) : 0;
    if (inputPeso) inputPeso.value = maxPeso > 0 ? maxPeso : '';
    if (labelTop) labelTop.textContent = maxPeso > 0 ? maxPeso + ' kg' : '--';

    if (unRm > 0) {
        const pct = maxPeso > 0 ? ((maxPeso / unRm) * 100).toFixed(1) : 0;
        if (inputPorcentaje) inputPorcentaje.value = pct > 0 ? pct : '';
        if (labelPorcentaje) labelPorcentaje.textContent = pct > 0 ? pct + '%' : '--';
    } else {
        if (inputPorcentaje) inputPorcentaje.value = '';
        if (labelPorcentaje) labelPorcentaje.textContent = '--';
    }

    programarGuardado(ejercicioId);
}

function onInputPesoChange(ejercicioId) {
    const inputPeso = document.getElementById(`input-peso-${ejercicioId}`);
    const inputPorcentaje = document.getElementById(`input-porcentaje-${ejercicioId}`);
    const ej = buscarEjercicioPorId(ejercicioId);

    const peso = parseFloat(inputPeso.value) || 0;
    const unRm = ej ? (parseFloat(ej.un_rm_estimado) || 0) : 0;

    if (unRm > 0 && peso > 0) {
        inputPorcentaje.value = ((peso / unRm) * 100).toFixed(1);
    }
    programarGuardado(ejercicioId);
}

function onInputPorcentajeChange(ejercicioId) {
    const inputPeso = document.getElementById(`input-peso-${ejercicioId}`);
    const inputPorcentaje = document.getElementById(`input-porcentaje-${ejercicioId}`);
    const ej = buscarEjercicioPorId(ejercicioId);

    const pct = parseFloat(inputPorcentaje.value) || 0;
    const unRm = ej ? (parseFloat(ej.un_rm_estimado) || 0) : 0;

    if (unRm > 0 && pct > 0) {
        inputPeso.value = ((pct / 100) * unRm).toFixed(1);
    }
    programarGuardado(ejercicioId);
}

function toggleCompletado(ejercicioId) {
    const btn = document.getElementById(`btn-completado-${ejercicioId}`);
    const ej = buscarEjercicioPorId(ejercicioId);
    if (!ej) return;

    let completadoActual = parseInt(ej.carga_actual.completado) === 1;
    let nuevoCompletado = completadoActual ? 0 : 1;
    ej.carga_actual.completado = nuevoCompletado;

    const card = document.getElementById(`ejercicio-card-${ejercicioId}`);
    if (nuevoCompletado === 1) {
        btn.className = 'h-10 px-4 rounded-xl text-xs font-bold transition-all duration-200 flex items-center justify-center space-x-2 touch-btn bg-emerald-500 text-slate-950 font-black shadow-md shadow-emerald-500/20';
        btn.querySelector('span').textContent = 'Completado';
        card.classList.add('border-emerald-500/30');
    } else {
        btn.className = 'h-10 px-4 rounded-xl text-xs font-bold transition-all duration-200 flex items-center justify-center space-x-2 touch-btn bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700';
        btn.querySelector('span').textContent = 'Marcar Hecho';
        card.classList.remove('border-emerald-500/30');
    }

    guardarCargaInmediata(ejercicioId);
}

function programarGuardado(ejercicioId) {
    actualizarIndicadorGuardado(ejercicioId, 'Guardando...', 'text-amber-400');
    if (AppState.saveDebounceTimers[ejercicioId]) {
        clearTimeout(AppState.saveDebounceTimers[ejercicioId]);
    }
    AppState.saveDebounceTimers[ejercicioId] = setTimeout(() => {
        guardarCargaInmediata(ejercicioId);
    }, 800);
}

async function guardarCargaInmediata(ejercicioId) {
    if (AppState.saveDebounceTimers[ejercicioId]) {
        clearTimeout(AppState.saveDebounceTimers[ejercicioId]);
    }

    const inputPeso = document.getElementById(`input-peso-${ejercicioId}`);
    const inputPorcentaje = document.getElementById(`input-porcentaje-${ejercicioId}`);
    const inputNotas = document.getElementById(`input-notas-${ejercicioId}`);
    const ej = buscarEjercicioPorId(ejercicioId);

    if (!ej) return;

    const numSeries = Math.max(1, parseInt(ej.series) || 4);
    const pesosSeries = [];
    for (let i = 0; i < numSeries; i++) {
        const inp = document.getElementById(`input-serie-${ejercicioId}-${i}`);
        if (inp && inp.value !== '') {
            pesosSeries.push(parseFloat(inp.value) || 0);
        } else {
            pesosSeries.push(0);
        }
    }

    const pesosValidos = pesosSeries.filter(p => p > 0);
    const maxPeso = pesosValidos.length > 0 ? Math.max(...pesosValidos) : 0;
    const pesoKg = (inputPeso && parseFloat(inputPeso.value) > 0) ? parseFloat(inputPeso.value) : maxPeso;
    const porcentaje = (inputPorcentaje && parseFloat(inputPorcentaje.value) > 0) ? parseFloat(inputPorcentaje.value) : 0;
    const notas = inputNotas ? inputNotas.value.trim() : '';
    const completado = parseInt(ej.carga_actual.completado) === 1 ? 1 : 0;

    actualizarIndicadorGuardado(ejercicioId, 'Sincronizando...', 'text-cyan-400');

    try {
        const payload = {
            ejercicio_id: ejercicioId,
            semana: AppState.semanaActiva,
            peso_kg: pesoKg,
            pesos_series: pesosSeries,
            porcentaje_carga: porcentaje,
            completado: completado,
            notas: notas
        };

        const res = await fetch(`${API_URL}?action=carga_guardar`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const json = await res.json();
        if (json.success) {
            actualizarIndicadorGuardado(ejercicioId, 'Guardado ✓', 'text-emerald-400');
            ej.carga_actual = json.data;
            if (ej.historial_cargas) {
                ej.historial_cargas[AppState.semanaActiva] = json.data;
            }
            const miniBadge = document.getElementById(`hist-badge-${ejercicioId}-${AppState.semanaActiva}`);
            if (miniBadge) {
                miniBadge.textContent = pesoKg > 0 ? `${pesoKg.toFixed(1)}k` : '-';
            }
        } else {
            actualizarIndicadorGuardado(ejercicioId, 'Error', 'text-rose-400');
            mostrarToast(json.message || 'Error al guardar carga', 'error');
        }
    } catch (err) {
        actualizarIndicadorGuardado(ejercicioId, 'Error de red', 'text-rose-400');
    }
}

function actualizarIndicadorGuardado(ejercicioId, texto, claseColor) {
    const el = document.getElementById(`save-status-${ejercicioId}`);
    if (el) {
        el.className = `text-[11px] font-medium ${claseColor}`;
        el.textContent = texto;
    }
}

async function copiarASiguienteSemana(ejercicioId) {
    if (AppState.semanaActiva >= 6) {
        mostrarToast('Ya estás en la última semana (S6)', 'info');
        return;
    }
    const semanaDestino = AppState.semanaActiva + 1;
    mostrarCargando(true);
    try {
        const res = await fetch(`${API_URL}?action=carga_copiar_semana`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ejercicio_id: ejercicioId,
                semana_origen: AppState.semanaActiva,
                semana_destino: semanaDestino,
                incremento_kg: 0
            })
        });
        const json = await res.json();
        if (json.success) {
            mostrarToast(`Carga copiada fielmente a S${semanaDestino}`, 'success');
            await cargarRutinaCliente(AppState.clienteActivoId, AppState.semanaActiva);
        } else {
            mostrarToast(json.message || 'Error al clonar', 'error');
        }
    } catch (err) {
        mostrarToast('Error de conexión', 'error');
    } finally {
        mostrarCargando(false);
    }
}

// ==============================================================================
// 4. MÓDULO DE FOTOS DE PROGRESO Y COMPARADOR (ANTES VS DESPUÉS)
// ==============================================================================

async function cargarFotosProgreso(usuarioId) {
    mostrarCargando(true);
    try {
        const endpoint = AppState.isAdmin
            ? `${API_URL}?action=get_fotos_progreso_admin&usuario_id=${usuarioId}`
            : `${API_URL}?action=fotos_listar&usuario_id=${usuarioId}`;
        const res = await fetch(endpoint);
        const json = await res.json();
        if (json.success) {
            AppState.fotosProgreso = Array.isArray(json.data) ? json.data : (json.data && json.data.fotos ? json.data.fotos : []);
            renderGaleriaFotos();
        } else {
            mostrarToast(json.message || 'Error al obtener fotos', 'error');
        }
    } catch (err) {
        mostrarToast('Error al conectar con la galería de fotos', 'error');
    } finally {
        mostrarCargando(false);
    }
}

function renderGaleriaFotos() {
    const container = document.getElementById('fotos-container');
    const btnComparar = document.getElementById('btn-abrir-comparador');
    const btnSubirPrincipal = document.getElementById('btn-subir-foto-principal');
    if (!container) return;

    container.innerHTML = '';

    if (btnSubirPrincipal) {
        if (AppState.isAdmin) {
            btnSubirPrincipal.classList.add('hidden');
        } else {
            btnSubirPrincipal.classList.remove('hidden');
        }
    }

    if (AppState.isAdmin) {
        const adminNotice = document.createElement('div');
        adminNotice.className = 'col-span-2 sm:col-span-3 bg-cyan-950/40 border border-cyan-500/30 text-cyan-200 text-xs p-3.5 rounded-2xl mb-2 flex items-center gap-2.5';
        adminNotice.innerHTML = `
            <svg class="w-5 h-5 text-cyan-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <div>
                <p class="font-bold text-cyan-300">Modo Solo Lectura (Entrenador)</p>
                <p class="text-[11px] text-cyan-200/80">Puedes revisar y comparar la evolución del atleta. Por privacidad, solo el atleta puede subir o eliminar sus fotos de progreso.</p>
            </div>
        `;
        container.appendChild(adminNotice);
    }

    if (AppState.fotosProgreso.length === 0) {
        const emptyNotice = document.createElement('div');
        emptyNotice.className = 'col-span-2 sm:col-span-3 text-center py-12 px-4 bg-slate-900/60 rounded-3xl border border-dashed border-slate-800';
        emptyNotice.innerHTML = `
            <div class="w-14 h-14 rounded-full bg-emerald-500/10 text-emerald-400 mx-auto flex items-center justify-center mb-3">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            <h4 class="text-sm font-bold text-white mb-1">Aún no hay fotos registradas</h4>
            <p class="text-xs text-slate-400 mb-4 max-w-xs mx-auto">
                ${AppState.isAdmin ? 'El atleta aún no ha subido fotografías de progreso para este microciclo.' : 'Sube tu primera fotografía de evolución (frente, perfil o espalda) para comenzar el seguimiento fotográfico.'}
            </p>
            ${!AppState.isAdmin ? `
                <button onclick="abrirModalSubirFoto()" class="py-2.5 px-5 bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 font-black rounded-xl text-xs">
                    + Subir Primera Foto
                </button>
            ` : ''}
        `;
        container.appendChild(emptyNotice);
        if (btnComparar) btnComparar.classList.add('hidden');
        return;
    }

    if (btnComparar) {
        btnComparar.classList.remove('hidden');
    }

    AppState.fotosProgreso.forEach(foto => {
        const card = document.createElement('div');
        card.className = 'group bg-slate-900 border border-slate-800 hover:border-emerald-500/40 rounded-2xl overflow-hidden transition-all duration-200 shadow-lg';

        card.innerHTML = `
            <div class="relative aspect-photo bg-slate-950 cursor-pointer overflow-hidden" onclick="verFotoDetalle(${foto.id})">
                <img src="${foto.ruta_imagen}" alt="Progreso S${foto.semana}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" onerror="this.onerror=null; this.src='assets/ejercicios/default.gif';">
                <div class="absolute top-2 left-2 flex gap-1">
                    <span class="px-2 py-0.5 rounded-lg text-[10px] font-black bg-slate-950/80 text-emerald-400 border border-emerald-500/30 backdrop-blur-sm">
                        S${foto.semana}
                    </span>
                    <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-950/80 text-slate-300 capitalize border border-slate-700/80 backdrop-blur-sm">
                        ${foto.tipo_angulo}
                    </span>
                </div>
                <div class="absolute bottom-2 right-2">
                    <span class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-slate-950/80 text-slate-400 border border-slate-800 backdrop-blur-sm">
                        ${foto.fecha_subida}
                    </span>
                </div>
            </div>
            <div class="p-2.5 flex items-center justify-between text-xs">
                <p class="text-slate-300 text-[11px] truncate flex-1 pr-2" title="${escaparHtml(foto.observaciones || '')}">
                    ${foto.observaciones ? escaparHtml(foto.observaciones) : '<span class="text-slate-500 italic">Sin observaciones</span>'}
                </p>
                ${!AppState.isAdmin ? `
                    <button onclick="confirmarEliminarFoto(${foto.id})" class="p-1 text-slate-500 hover:text-rose-400 rounded transition" title="Eliminar foto">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                ` : ''}
            </div>
        `;
        container.appendChild(card);
    });
}

function abrirModalSubirFoto() {
    if (AppState.isAdmin) {
        mostrarToast('Restricción: Solo el atleta puede subir o modificar sus fotos de progreso.', 'warning');
        return;
    }
    const modal = document.getElementById('modal-subir-foto');
    const form = document.getElementById('form-subir-foto');
    form.reset();
    document.getElementById('foto-fecha').value = new Date().toISOString().split('T')[0];
    document.getElementById('foto-preview-container').classList.add('hidden');
    modal.classList.remove('hidden');
}

function cerrarModalSubirFoto() {
    document.getElementById('modal-subir-foto').classList.add('hidden');
}

function onFotoFileChange(e) {
    const file = e.target.files[0];
    if (!file) return;

    const preview = document.getElementById('foto-preview-img');
    const container = document.getElementById('foto-preview-container');

    const reader = new FileReader();
    reader.onload = (event) => {
        preview.src = event.target.result;
        container.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
}

async function subirFotoProgreso(e) {
    e.preventDefault();
    const fileInput = document.getElementById('foto-input-file');
    if (!fileInput.files || fileInput.files.length === 0) {
        mostrarToast('Por favor selecciona una fotografía', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('foto', fileInput.files[0]);
    formData.append('usuario_id', AppState.clienteActivoId);
    formData.append('semana', document.getElementById('foto-semana').value);
    formData.append('tipo_angulo', document.getElementById('foto-angulo').value);
    formData.append('fecha_subida', document.getElementById('foto-fecha').value);
    formData.append('observaciones', document.getElementById('foto-observaciones').value.trim());

    mostrarCargando(true);
    try {
        const res = await fetch(`${API_URL}?action=foto_subir`, {
            method: 'POST',
            body: formData
        });
        const json = await res.json();
        if (json.success) {
            cerrarModalSubirFoto();
            mostrarToast('Foto de progreso subida exitosamente', 'success');
            await cargarFotosProgreso(AppState.clienteActivoId);
        } else {
            mostrarToast(json.message || 'Error al subir foto', 'error');
        }
    } catch (err) {
        mostrarToast('Error de red al subir archivo', 'error');
    } finally {
        mostrarCargando(false);
    }
}

async function confirmarEliminarFoto(fotoId) {
    if (!confirm('¿Eliminar esta foto de progreso de forma permanente?')) return;

    mostrarCargando(true);
    try {
        const res = await fetch(`${API_URL}?action=foto_eliminar`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: fotoId })
        });
        const json = await res.json();
        if (json.success) {
            mostrarToast('Foto eliminada', 'success');
            await cargarFotosProgreso(AppState.clienteActivoId);
        } else {
            mostrarToast(json.message || 'Error al eliminar', 'error');
        }
    } catch (err) {
        mostrarToast('Error de conexión', 'error');
    } finally {
        mostrarCargando(false);
    }
}

function verFotoDetalle(fotoId) {
    const foto = AppState.fotosProgreso.find(f => f.id == fotoId);
    if (!foto) return;

    const modal = document.getElementById('modal-foto-detalle');
    document.getElementById('foto-detalle-img').src = foto.ruta_imagen;
    document.getElementById('foto-detalle-titulo').textContent = `Semana ${foto.semana} • Vista ${foto.tipo_angulo}`;
    document.getElementById('foto-detalle-fecha').textContent = foto.fecha_subida;
    document.getElementById('foto-detalle-obs').textContent = foto.observaciones || 'Sin notas registradas.';

    modal.classList.remove('hidden');
}

function cerrarModalFotoDetalle() {
    document.getElementById('modal-foto-detalle').classList.add('hidden');
}

function abrirModalComparador() {
    if (AppState.fotosProgreso.length < 2) {
        mostrarToast('Se necesitan al menos 2 fotos de progreso para poder comparar', 'info');
        return;
    }

    const selectA = document.getElementById('comparar-select-a');
    const selectB = document.getElementById('comparar-select-b');
    selectA.innerHTML = '';
    selectB.innerHTML = '';

    const fotosOrdenadas = [...AppState.fotosProgreso].sort((a, b) => new Date(a.fecha_subida) - new Date(b.fecha_subida));

    fotosOrdenadas.forEach((f, idx) => {
        const optA = document.createElement('option');
        optA.value = f.id;
        optA.textContent = `S${f.semana} (${f.tipo_angulo}) - ${f.fecha_subida}`;
        selectA.appendChild(optA);

        const optB = document.createElement('option');
        optB.value = f.id;
        optB.textContent = `S${f.semana} (${f.tipo_angulo}) - ${f.fecha_subida}`;
        selectB.appendChild(optB);
    });

    selectA.selectedIndex = 0;
    selectB.selectedIndex = fotosOrdenadas.length - 1;

    actualizarComparacion();
    document.getElementById('modal-comparador').classList.remove('hidden');
}

function cerrarModalComparador() {
    document.getElementById('modal-comparador').classList.add('hidden');
}

function actualizarComparacion() {
    const idA = document.getElementById('comparar-select-a').value;
    const idB = document.getElementById('comparar-select-b').value;

    const fotoA = AppState.fotosProgreso.find(f => f.id == idA);
    const fotoB = AppState.fotosProgreso.find(f => f.id == idB);

    if (fotoA) {
        document.getElementById('comp-img-a').src = fotoA.ruta_imagen;
        document.getElementById('comp-tag-a').textContent = `Semana ${fotoA.semana} (${fotoA.tipo_angulo})`;
        document.getElementById('comp-fecha-a').textContent = fotoA.fecha_subida;
        document.getElementById('comp-obs-a').textContent = fotoA.observaciones || '';
    }

    if (fotoB) {
        document.getElementById('comp-img-b').src = fotoB.ruta_imagen;
        document.getElementById('comp-tag-b').textContent = `Semana ${fotoB.semana} (${fotoB.tipo_angulo})`;
        document.getElementById('comp-fecha-b').textContent = fotoB.fecha_subida;
        document.getElementById('comp-obs-b').textContent = fotoB.observaciones || '';
    }
}

// ==============================================================================
// 5. VISOR DE TÉCNICA E IMAGEN DEMOSTRATIVA DE EJERCICIOS
// ==============================================================================

function verTecnicaEjercicio(ejercicioId) {
    const ej = buscarEjercicioPorId(ejercicioId);
    if (!ej) return;

    const modal = document.getElementById('modal-tecnica');
    document.getElementById('tecnica-titulo').textContent = ej.nombre_ejercicio;
    document.getElementById('tecnica-dia').textContent = `Día ${ej.dia_semana}`;
    document.getElementById('tecnica-series-reps').textContent = `${ej.series} Series × ${ej.repeticiones} Reps`;
    document.getElementById('tecnica-1rm').textContent = ej.un_rm_estimado > 0 ? `${ej.un_rm_estimado} kg` : 'No asignado';
    document.getElementById('tecnica-obs').textContent = ej.observaciones || 'Sigue una técnica estricta con cadencia controlada.';

    const imgContainer = document.getElementById('tecnica-img-container');
    if (ej.imagen_url && ej.imagen_url.trim() !== '') {
        imgContainer.innerHTML = `
            <div class="relative space-y-2">
                <img src="${ej.imagen_url}" 
                     alt="${escaparHtml(ej.nombre_ejercicio)}" 
                     class="w-full max-h-72 object-contain rounded-2xl mx-auto shadow-inner bg-slate-950 border border-slate-800/80"
                     onerror="this.onerror=null; this.src='assets/ejercicios/default.gif';">
            </div>
        `;
    } else {
        imgContainer.innerHTML = `
            <div class="h-48 flex flex-col items-center justify-center bg-slate-950 rounded-2xl border border-dashed border-slate-800 text-slate-500 p-4 text-center">
                <img src="assets/ejercicios/default.gif" alt="Placeholder" class="w-14 h-14 mb-2 opacity-50">
                <p class="text-xs font-semibold text-slate-300">Sin recurso demostrativo asignado</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Puedes cargar una imagen/GIF directamente al crear o editar este ejercicio.</p>
            </div>
        `;
    }

    modal.classList.remove('hidden');
}

function cerrarModalTecnica() {
    document.getElementById('modal-tecnica').classList.add('hidden');
}

// ==============================================================================
// 6. GESTIÓN DE USUARIOS Y EJERCICIOS (ADMIN)
// ==============================================================================

async function abrirModalGestionUsuarios() {
    mostrarCargando(true);
    try {
        const res = await fetch(`${API_URL}?action=usuarios`);
        const json = await res.json();
        if (json.success) {
            renderTablaUsuarios(json.data);
            document.getElementById('modal-gestion-usuarios').classList.remove('hidden');
        } else {
            mostrarToast(json.message || 'Error al listar usuarios', 'error');
        }
    } catch (err) {
        mostrarToast('Error de conexión', 'error');
    } finally {
        mostrarCargando(false);
    }
}

function cerrarModalGestionUsuarios() {
    document.getElementById('modal-gestion-usuarios').classList.add('hidden');
}

function renderTablaUsuarios(usuarios) {
    const list = document.getElementById('lista-usuarios-admin');
    if (!list) return;

    list.innerHTML = '';
    usuarios.forEach(u => {
        const item = document.createElement('div');
        item.className = 'p-3 bg-slate-950 border border-slate-800 rounded-2xl flex items-center justify-between gap-3';
        item.innerHTML = `
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-slate-800 text-emerald-400 font-bold flex items-center justify-center text-sm border border-slate-700">
                    ${(u.nombre[0] || '') + (u.apellido[0] || '')}
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm font-bold text-white">${u.nombre} ${u.apellido}</span>
                        <span class="text-[9px] uppercase px-1.5 py-0.2 rounded font-black ${u.rol === 'admin' ? 'bg-cyan-500/20 text-cyan-400' : 'bg-emerald-500/20 text-emerald-400'}">
                            ${u.rol}
                        </span>
                    </div>
                    <p class="text-xs text-slate-400">${u.email} • ${parseFloat(u.peso_corporal).toFixed(1)} kg</p>
                </div>
            </div>
            <div class="flex items-center space-x-1">
                <button onclick="editarUsuarioDesdeAdmin(${u.id})" class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition" title="Editar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                </button>
            </div>
        `;
        list.appendChild(item);
    });
}

function editarUsuarioDesdeAdmin(usuarioId) {
    cerrarModalGestionUsuarios();
    abrirModalCliente(usuarioId);
}

let imagenesLocalesCache = null;

async function cargarOpcionesImagenesLocales(valorSeleccionado = '') {
    const select = document.getElementById('ejercicio-form-imagen-url');
    if (!select) return;

    if (!imagenesLocalesCache) {
        try {
            const res = await fetch(`${API_URL}?action=listar_imagenes_locales`);
            const json = await res.json();
            if (json.success && Array.isArray(json.data)) {
                imagenesLocalesCache = json.data;
            } else {
                imagenesLocalesCache = [];
            }
        } catch (err) {
            imagenesLocalesCache = [];
        }
    }

    select.innerHTML = '';

    // Opción predeterminada
    const defaultOpt = document.createElement('option');
    defaultOpt.value = 'assets/ejercicios/default.gif';
    defaultOpt.textContent = '-- Seleccionar Imagen de la Galería --';
    select.appendChild(defaultOpt);

    let encontrado = false;
    imagenesLocalesCache.forEach(img => {
        const opt = document.createElement('option');
        const ruta = img.ruta || img;
        const nombre = img.nombre || img.archivo || ruta;
        opt.value = ruta;
        opt.textContent = nombre;
        if (valorSeleccionado && (valorSeleccionado === ruta || valorSeleccionado === img.archivo)) {
            opt.selected = true;
            encontrado = true;
        }
        select.appendChild(opt);
    });

    // Si el valor seleccionado no estaba en la lista y no es default.gif, agregarlo como opción
    if (valorSeleccionado && !encontrado && valorSeleccionado !== 'assets/ejercicios/default.gif') {
        const customOpt = document.createElement('option');
        customOpt.value = valorSeleccionado;
        customOpt.textContent = valorSeleccionado.split('/').pop();
        customOpt.selected = true;
        select.appendChild(customOpt);
    }

    if (!valorSeleccionado) {
        select.value = 'assets/ejercicios/default.gif';
    }

    actualizarPreviewImagenEjercicio(select.value);
}

function actualizarPreviewImagenEjercicio(url) {
    const preview = document.getElementById('ejercicio-img-preview');
    if (!preview) return;
    preview.src = url || 'assets/ejercicios/default.gif';
    preview.classList.remove('hidden');
}

function alSeleccionarImagenEjercicio(url) {
    actualizarPreviewImagenEjercicio(url);
    if (!imagenesLocalesCache) return;
    const encontrado = imagenesLocalesCache.find(img => (img.ruta === url || img.archivo === url));
    if (encontrado) {
        const inputNombre = document.getElementById('ejercicio-form-nombre');
        if (inputNombre && (!inputNombre.value || inputNombre.value.trim() === '')) {
            inputNombre.value = encontrado.nombre;
        }
        const previewNombre = document.getElementById('ejercicio-img-preview-nombre');
        if (previewNombre) {
            previewNombre.textContent = encontrado.nombre;
        }
    }
}

function filtrarEjerciciosEnModal(query) {
    const select = document.getElementById('ejercicio-form-imagen-url');
    const conteo = document.getElementById('ejercicio-busqueda-conteo');
    const btnLimpiar = document.getElementById('btn-limpiar-busqueda-ejercicio');
    const sugerenciasContainer = document.getElementById('ejercicio-sugerencias-container');
    if (!select || !imagenesLocalesCache) return;

    const q = query.trim().toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

    if (btnLimpiar) {
        if (q.length > 0) btnLimpiar.classList.remove('hidden');
        else btnLimpiar.classList.add('hidden');
    }

    const valorActual = select.value;
    select.innerHTML = '<option value="assets/ejercicios/default.gif">-- Seleccionar Ejercicio de la Galería --</option>';

    const filtrados = imagenesLocalesCache.filter(img => {
        if (!q) return true;
        const nombre = (img.nombre || img.archivo || '').toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        return nombre.includes(q);
    });

    filtrados.forEach(img => {
        const opt = document.createElement('option');
        const ruta = img.ruta || img;
        const nombre = img.nombre || img.archivo || ruta;
        opt.value = ruta;
        opt.textContent = nombre;
        if (ruta === valorActual) opt.selected = true;
        select.appendChild(opt);
    });

    if (conteo) {
        conteo.textContent = `${filtrados.length} disponibles`;
    }

    // Renderizar tarjetas interactivas de sugerencias si el usuario está buscando
    if (sugerenciasContainer) {
        if (q.length > 0 && filtrados.length > 0) {
            sugerenciasContainer.innerHTML = '';
            filtrados.slice(0, 8).forEach(img => {
                const ruta = img.ruta || img;
                const nombre = img.nombre || img.archivo || ruta;
                const card = document.createElement('div');
                card.className = 'flex items-center gap-2.5 p-1.5 rounded-lg bg-slate-950/80 hover:bg-slate-800 border border-slate-800 hover:border-emerald-500/50 cursor-pointer transition touch-btn';
                card.onclick = () => seleccionarEjercicioDeGaleria(ruta, nombre);
                card.innerHTML = `
                    <div class="w-9 h-9 rounded-md bg-slate-900 overflow-hidden flex-shrink-0 flex items-center justify-center border border-slate-800">
                        <img src="${ruta}" alt="${escaparHtml(nombre)}" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='assets/ejercicios/default.gif';">
                    </div>
                    <div class="flex-1 min-w-0 text-left">
                        <p class="text-xs font-bold text-slate-200 truncate">${escaparHtml(nombre)}</p>
                        <span class="text-[10px] text-emerald-400 font-medium">Toca para seleccionar</span>
                    </div>
                `;
                sugerenciasContainer.appendChild(card);
            });
            sugerenciasContainer.classList.remove('hidden');
        } else {
            sugerenciasContainer.classList.add('hidden');
            sugerenciasContainer.innerHTML = '';
        }
    }
}

function seleccionarEjercicioDeGaleria(ruta, nombre) {
    const select = document.getElementById('ejercicio-form-imagen-url');
    if (select) {
        select.value = ruta;
    }
    actualizarPreviewImagenEjercicio(ruta);

    const inputNombre = document.getElementById('ejercicio-form-nombre');
    if (inputNombre && (!inputNombre.value || inputNombre.value.trim() === '')) {
        inputNombre.value = nombre;
    }

    const previewNombre = document.getElementById('ejercicio-img-preview-nombre');
    if (previewNombre) {
        previewNombre.textContent = nombre;
    }

    const sugerenciasContainer = document.getElementById('ejercicio-sugerencias-container');
    if (sugerenciasContainer) {
        sugerenciasContainer.classList.add('hidden');
    }
}

function limpiarBuscadorEjercicios() {
    const input = document.getElementById('ejercicio-buscador');
    if (input) input.value = '';
    filtrarEjerciciosEnModal('');
}

function filtrarEjerciciosEnRutina(query) {
    const q = query.trim().toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
    const btnLimpiar = document.getElementById('btn-limpiar-busqueda-rutina');
    if (btnLimpiar) {
        if (q.length > 0) btnLimpiar.classList.remove('hidden');
        else btnLimpiar.classList.add('hidden');
    }

    const cards = document.querySelectorAll('[id^="ejercicio-card-"]');
    cards.forEach(card => {
        if (!q) {
            card.classList.remove('hidden');
            return;
        }
        const titleEl = card.querySelector('h4');
        const titleText = titleEl ? titleEl.textContent.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "") : '';
        if (titleText.includes(q)) {
            card.classList.remove('hidden');
        } else {
            card.classList.add('hidden');
        }
    });
}

function limpiarBuscadorRutina() {
    const input = document.getElementById('buscador-rutina-ejercicios');
    if (input) input.value = '';
    filtrarEjerciciosEnRutina('');
}

async function abrirModalEjercicio(diaPredeterminado = 1) {
    const modal = document.getElementById('modal-ejercicio');
    const form = document.getElementById('form-ejercicio');
    form.reset();
    document.getElementById('ejercicio-form-id').value = '';
    document.getElementById('ejercicio-form-dia').value = diaPredeterminado || 1;
    document.getElementById('ejercicio-form-series').value = '4';
    document.getElementById('ejercicio-form-reps').value = '8-10';
    document.getElementById('ejercicio-form-1rm').value = '';
    document.getElementById('ejercicio-form-obs').value = '';
    document.getElementById('modal-ejercicio-titulo').textContent = 'Nuevo Ejercicio de Rutina';

    limpiarBuscadorEjercicios();
    await cargarOpcionesImagenesLocales('assets/ejercicios/default.gif');

    modal.classList.remove('hidden');
}

function cerrarModalEjercicio() {
    document.getElementById('modal-ejercicio').classList.add('hidden');
}

async function editarEjercicio(ejercicioId) {
    const ej = buscarEjercicioPorId(ejercicioId);
    if (!ej) return;

    const modal = document.getElementById('modal-ejercicio');
    document.getElementById('modal-ejercicio-titulo').textContent = 'Editar Ejercicio';
    document.getElementById('ejercicio-form-id').value = ej.id;
    document.getElementById('ejercicio-form-dia').value = ej.dia_semana;
    document.getElementById('ejercicio-form-nombre').value = ej.nombre_ejercicio;
    document.getElementById('ejercicio-form-series').value = ej.series;
    document.getElementById('ejercicio-form-reps').value = ej.repeticiones;
    document.getElementById('ejercicio-form-1rm').value = ej.un_rm_estimado > 0 ? ej.un_rm_estimado : '';
    document.getElementById('ejercicio-form-obs').value = ej.observaciones || '';

    limpiarBuscadorEjercicios();
    await cargarOpcionesImagenesLocales(ej.imagen_url || 'assets/ejercicios/default.gif');

    const previewNombre = document.getElementById('ejercicio-img-preview-nombre');
    if (previewNombre) {
        previewNombre.textContent = ej.nombre_ejercicio;
    }

    modal.classList.remove('hidden');
}

async function subirImagenEjercicioDirecto(file) {
    const formData = new FormData();
    formData.append('imagen', file);
    const res = await fetch(`${API_URL}?action=ejercicio_subir_imagen`, {
        method: 'POST',
        body: formData
    });
    return await res.json();
}

async function confirmarEliminarEjercicio(ejercicioId) {
    const ej = buscarEjercicioPorId(ejercicioId);
    if (!ej) return;
    if (!confirm(`¿Eliminar "${ej.nombre_ejercicio}" de la rutina?`)) return;

    mostrarCargando(true);
    try {
        const res = await fetch(`${API_URL}?action=ejercicio_eliminar`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: ejercicioId })
        });
        const json = await res.json();
        if (json.success) {
            mostrarToast('Ejercicio eliminado', 'success');
            await cargarRutinaCliente(AppState.clienteActivoId, AppState.semanaActiva);
        } else {
            mostrarToast(json.message || 'Error', 'error');
        }
    } catch (err) {
        mostrarToast('Error de red', 'error');
    } finally {
        mostrarCargando(false);
    }
}

function abrirModalCliente(clienteId = null) {
    const modal = document.getElementById('modal-cliente');
    const titulo = document.getElementById('modal-cliente-titulo');
    const form = document.getElementById('form-cliente');
    form.reset();
    document.getElementById('cliente-form-id').value = '';

    // En Arquitectura SaaS Multi-Tenant el rol permanece fijo y oculto como 'cliente'
    const rolInput = document.getElementById('cliente-form-rol');
    if (rolInput) {
        rolInput.value = 'cliente';
    }
    const rolGroup = document.getElementById('cliente-form-rol-group');
    if (rolGroup) {
        rolGroup.classList.add('hidden');
    }

    if (clienteId) {
        mostrarCargando(true);
        fetch(`${API_URL}?action=usuario_detalle&id=${clienteId}`)
            .then(r => r.json())
            .then(json => {
                if (json.success && (json.data?.cliente || json.data?.usuario || json.data?.id)) {
                    const c = json.data.cliente || json.data.usuario || json.data;
                    titulo.textContent = 'Editar Datos del Atleta';
                    document.getElementById('cliente-form-id').value = c.id;
                    document.getElementById('cliente-form-nombre').value = c.nombre || '';
                    document.getElementById('cliente-form-apellido').value = c.apellido || '';
                    document.getElementById('cliente-form-email').value = c.email || '';
                    document.getElementById('cliente-form-peso').value = c.peso_corporal || '';
                    document.getElementById('cliente-form-telefono').value = c.telefono || '';
                    if (rolInput) rolInput.value = c.rol || 'cliente';
                    document.getElementById('cliente-form-observaciones').value = c.observaciones || '';
                } else {
                    mostrarToast(json.message || 'Error al obtener datos del atleta', 'error');
                }
            })
            .catch(err => {
                mostrarToast('Error al conectar con el servidor', 'error');
            })
            .finally(() => {
                mostrarCargando(false);
            });
    } else {
        titulo.textContent = 'Nuevo Atleta / Usuario';
    }

    modal.classList.remove('hidden');
}

function cerrarModalCliente() {
    document.getElementById('modal-cliente').classList.add('hidden');
}

function abrirModalPesoRapido() {
    const modal = document.getElementById('modal-peso');
    const input = document.getElementById('input-peso-rapido');
    if (AppState.clienteActual) {
        input.value = parseFloat(AppState.clienteActual.peso_corporal).toFixed(1);
    }
    modal.classList.remove('hidden');
    input.focus();
}

function cerrarModalPesoRapido() {
    document.getElementById('modal-peso').classList.add('hidden');
}

async function guardarPesoRapido() {
    const input = document.getElementById('input-peso-rapido');
    const peso = parseFloat(input.value);

    if (!peso || peso <= 0) {
        mostrarToast('Ingresa un peso válido', 'error');
        return;
    }

    mostrarCargando(true);
    try {
        const res = await fetch(`${API_URL}?action=cliente_actualizar_peso`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: AppState.clienteActivoId,
                peso_corporal: peso
            })
        });
        const json = await res.json();
        if (json.success) {
            if (AppState.clienteActual) {
                AppState.clienteActual.peso_corporal = peso;
            }
            document.getElementById('cliente-peso').textContent = `${peso.toFixed(1)} kg`;
            cerrarModalPesoRapido();
            mostrarToast('Peso corporal actualizado', 'success');
        } else {
            mostrarToast(json.message || 'Error', 'error');
        }
    } catch (err) {
        mostrarToast('Error de red', 'error');
    } finally {
        mostrarCargando(false);
    }
}

// ==============================================================================
// 7. EVENT LISTENERS Y ENVÍO DE FORMULARIOS
// ==============================================================================

function setupEventListeners() {
    const formLogin = document.getElementById('form-login');
    if (formLogin) {
        formLogin.addEventListener('submit', (e) => {
            e.preventDefault();
            const email = document.getElementById('login-email').value;
            const pass = document.getElementById('login-password').value;
            procesarLogin(email, pass);
        });
    }

    const formRegistro = document.getElementById('form-registro');
    if (formRegistro) {
        formRegistro.addEventListener('submit', (e) => {
            e.preventDefault();
            procesarRegistro();
        });
    }

    document.addEventListener('click', (e) => {
        const wrapper = document.getElementById('buscador-cliente-top-wrapper');
        const dropdown = document.getElementById('resultados-cliente-top');
        if (wrapper && dropdown && !wrapper.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    const formCliente = document.getElementById('form-cliente');
    if (formCliente) {
        formCliente.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('cliente-form-id').value;
            const payload = {
                id: id ? parseInt(id) : 0,
                nombre: document.getElementById('cliente-form-nombre').value.trim(),
                apellido: document.getElementById('cliente-form-apellido').value.trim(),
                email: document.getElementById('cliente-form-email').value.trim(),
                rol: (document.getElementById('cliente-form-rol')?.value) || 'cliente',
                peso_corporal: parseFloat(document.getElementById('cliente-form-peso').value) || 70,
                telefono: document.getElementById('cliente-form-telefono').value.trim(),
                observaciones: document.getElementById('cliente-form-observaciones').value.trim()
            };

            mostrarCargando(true);
            try {
                const res = await fetch(`${API_URL}?action=usuario_guardar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (json.success) {
                    cerrarModalCliente();
                    mostrarToast(json.message || 'Usuario guardado', 'success');
                    await cargarDatosIniciales();
                } else {
                    mostrarToast(json.message || 'Error al guardar', 'error');
                }
            } catch (err) {
                mostrarToast('Error de conexión', 'error');
            } finally {
                mostrarCargando(false);
            }
        });
    }

    const selectImagenEjercicio = document.getElementById('ejercicio-form-imagen-url');
    if (selectImagenEjercicio) {
        selectImagenEjercicio.addEventListener('change', (e) => {
            alSeleccionarImagenEjercicio(e.target.value);
        });
    }

    const formEjercicio = document.getElementById('form-ejercicio');
    if (formEjercicio) {
        formEjercicio.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('ejercicio-form-id').value;
            const selectImg = document.getElementById('ejercicio-form-imagen-url');
            const imagenUrl = selectImg ? selectImg.value.trim() : '';

            mostrarCargando(true);

            const payload = {
                id: id ? parseInt(id) : 0,
                rutina_id: AppState.rutinaActual ? AppState.rutinaActual.id : 1,
                dia_semana: parseInt(document.getElementById('ejercicio-form-dia').value),
                nombre_ejercicio: document.getElementById('ejercicio-form-nombre').value.trim(),
                series: parseInt(document.getElementById('ejercicio-form-series').value) || 4,
                repeticiones: document.getElementById('ejercicio-form-reps').value.trim(),
                un_rm_estimado: parseFloat(document.getElementById('ejercicio-form-1rm').value) || 0,
                imagen_url: imagenUrl,
                observaciones: document.getElementById('ejercicio-form-obs').value.trim()
            };

            try {
                const res = await fetch(`${API_URL}?action=ejercicio_guardar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (json.success) {
                    cerrarModalEjercicio();
                    mostrarToast(json.message || 'Ejercicio guardado', 'success');
                    await cargarRutinaCliente(AppState.clienteActivoId, AppState.semanaActiva);
                } else {
                    mostrarToast(json.message || 'Error', 'error');
                }
            } catch (err) {
                mostrarToast('Error de red', 'error');
            } finally {
                mostrarCargando(false);
            }
        });
    }

    const formFoto = document.getElementById('form-subir-foto');
    if (formFoto) {
        formFoto.addEventListener('submit', subirFotoProgreso);
    }
}

// ==============================================================================
// 8. UTILIDADES DE INTERFAZ (SPINNER, TOAST, ESCAPES)
// ==============================================================================

function mostrarCargando(mostrar) {
    AppState.cargando = mostrar;
    const loader = document.getElementById('global-loader');
    if (loader) {
        if (mostrar) loader.classList.remove('hidden');
        else loader.classList.add('hidden');
    }
}

function mostrarToast(mensaje, tipo = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    const colorBg = tipo === 'success' 
        ? 'bg-emerald-500 text-slate-950 font-black' 
        : tipo === 'error' 
        ? 'bg-rose-500 text-white font-bold' 
        : 'bg-slate-800 text-slate-100 font-semibold border border-slate-700';

    toast.className = `${colorBg} py-2.5 px-4 rounded-2xl shadow-2xl text-xs sm:text-sm flex items-center space-x-2 transition-all duration-300 transform translate-y-3 opacity-0`;
    toast.innerHTML = `<span>${escaparHtml(mensaje)}</span>`;
    container.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-3', 'opacity-0');
    });

    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-3');
        setTimeout(() => toast.remove(), 300);
    }, 2800);
}

function escaparHtml(texto) {
    if (!texto) return '';
    return texto
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}