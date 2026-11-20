<?php
/**
 * ==============================================================================
 * MÓDULO DE FOTOS DE PROGRESO DEL ATLETA (progreso.php)
 * FitTracker Pro V2 - Exclusivo para Alumnos/Clientes
 * Permite subir fotografías de evolución (cámara/galería) y comparar S1..S5
 * ==============================================================================
 */

require_once __DIR__ . '/auth.php';
startAuthSession();

if (!isLoggedIn()) {
    header('Location: index.html#login');
    exit;
}

$currentUser = getCurrentUser();

// Si un administrador entra a progreso.php, enviarlo a su panel de control
if ($currentUser['rol'] === 'admin') {
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Mis Fotos de Progreso - FitTracker</title>
    
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
<body class="bg-slate-950 text-slate-100 font-sans antialiased min-h-screen pb-16">

    <!-- CABECERA -->
    <header class="sticky top-0 z-40 bg-slate-950/90 backdrop-blur-md border-b border-slate-800 safe-top">
        <div class="max-w-2xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <a href="index.html" class="p-2 bg-slate-900 rounded-xl border border-slate-800 text-slate-400 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h1 class="text-base font-black text-white">Mi Evolución Fotográfica</h1>
                    <p class="text-[10px] text-emerald-400 font-bold"><?= htmlspecialchars($currentUser['nombre'] . ' ' . $currentUser['apellido']) ?></p>
                </div>
            </div>

            <button onclick="document.getElementById('modal-subir-cliente').classList.remove('hidden')" class="py-2 px-3 bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 font-black rounded-xl text-xs flex items-center space-x-1 shadow-md shadow-emerald-500/20">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                <span>+ Subir Foto</span>
            </button>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 pt-4 space-y-4">
        <!-- Banner de Privacidad -->
        <div class="p-3 bg-slate-900/80 border border-slate-800 rounded-2xl flex items-center justify-between text-xs text-slate-400">
            <span>Tus fotos solo son visibles para ti y tu entrenador asignado.</span>
            <button id="btn-comp-cliente" onclick="abrirComparadorCliente()" class="py-1.5 px-3 bg-cyan-500/10 text-cyan-400 border border-cyan-500/30 rounded-xl font-bold text-xs hover:bg-cyan-500/20 transition hidden">
                ⚡ Comparar Fotos
            </button>
        </div>

        <!-- Grid de Fotos del Cliente -->
        <div id="cliente-fotos-grid" class="grid grid-cols-2 sm:grid-cols-3 gap-3"></div>
    </main>

    <!-- MODAL DE SUBIDA PARA EL CLIENTE (Cámara/Galería) -->
    <div id="modal-subir-cliente" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-end sm:items-center justify-center p-0 sm:p-4 hidden">
        <div class="modal-content-animated bg-slate-900 border border-slate-800 w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl p-5 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <h3 class="text-base font-black text-white">Subir Foto de Progreso</h3>
                <button onclick="document.getElementById('modal-subir-cliente').classList.add('hidden')" class="p-1.5 text-slate-400 hover:text-white">✕</button>
            </div>

            <form id="form-subir-cliente" class="mt-4 space-y-3.5">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Tomar Foto con Cámara o Galería *</label>
                    <input type="file" id="input-foto-cliente" accept="image/*" capture="environment" required class="w-full text-xs text-slate-300 file:mr-3 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-emerald-500 file:text-slate-950 cursor-pointer">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Semana *</label>
                        <select id="select-semana-cliente" class="w-full h-11 bg-slate-950 border border-slate-700 rounded-xl px-3 text-xs text-white">
                            <option value="1">Semana 1 (S1)</option>
                            <option value="2">Semana 2 (S2)</option>
                            <option value="3">Semana 3 (S3)</option>
                            <option value="4">Semana 4 (S4)</option>
                            <option value="5">Semana 5 (S5)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Ángulo *</label>
                        <select id="select-angulo-cliente" class="w-full h-11 bg-slate-950 border border-slate-700 rounded-xl px-3 text-xs text-white">
                            <option value="frente">Frente</option>
                            <option value="perfil">Perfil Lateral</option>
                            <option value="espalda">Espalda</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Fecha</label>
                    <input type="date" id="input-fecha-cliente" class="w-full h-11 bg-slate-950 border border-slate-700 rounded-xl px-3 text-xs text-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Notas / Sensaciones</label>
                    <textarea id="input-obs-cliente" rows="2" placeholder="ej. Ayunas, peso actual 78.5kg..." class="w-full bg-slate-950 border border-slate-700 rounded-xl p-2.5 text-xs text-white resize-none"></textarea>
                </div>

                <div class="pt-2 flex gap-2">
                    <button type="button" onclick="document.getElementById('modal-subir-cliente').classList.add('hidden')" class="flex-1 py-3 rounded-xl bg-slate-800 text-slate-300 font-bold text-xs">Cancelar</button>
                    <button type="submit" class="flex-1 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 font-black text-xs shadow-lg shadow-emerald-500/20">Subir</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL COMPARADOR CLIENTE -->
    <div id="modal-comp-cliente" class="fixed inset-0 z-50 bg-slate-950/85 backdrop-blur-md flex items-center justify-center p-3 sm:p-4 hidden">
        <div class="modal-content-animated bg-slate-900 border border-slate-800 w-full max-w-xl rounded-3xl p-5 shadow-2xl max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <h3 class="text-sm font-black text-white">Comparador de Progreso</h3>
                <button onclick="document.getElementById('modal-comp-cliente').classList.add('hidden')" class="text-slate-400 hover:text-white">✕</button>
            </div>

            <div class="grid grid-cols-2 gap-2 my-3">
                <select id="comp-sel-a-cli" onchange="renderCompCliente()" class="w-full h-9 bg-slate-950 border border-slate-700 rounded-xl px-2 text-xs text-white"></select>
                <select id="comp-sel-b-cli" onchange="renderCompCliente()" class="w-full h-9 bg-slate-950 border border-slate-700 rounded-xl px-2 text-xs text-white"></select>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="bg-slate-950 p-2 rounded-2xl text-center">
                    <div class="aspect-photo bg-slate-900 rounded-xl overflow-hidden mb-1 relative">
                        <img id="comp-img-a-cli" src="" class="w-full h-full object-cover">
                        <span id="comp-tag-a-cli" class="absolute top-2 left-2 px-2 py-0.5 rounded text-[10px] font-black bg-slate-950/80 text-cyan-400"></span>
                    </div>
                    <p id="comp-fecha-a-cli" class="text-xs font-bold text-slate-300"></p>
                </div>
                <div class="bg-slate-950 p-2 rounded-2xl text-center">
                    <div class="aspect-photo bg-slate-900 rounded-xl overflow-hidden mb-1 relative">
                        <img id="comp-img-b-cli" src="" class="w-full h-full object-cover">
                        <span id="comp-tag-b-cli" class="absolute top-2 left-2 px-2 py-0.5 rounded text-[10px] font-black bg-slate-950/80 text-emerald-400"></span>
                    </div>
                    <p id="comp-fecha-b-cli" class="text-xs font-bold text-slate-300"></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let misFotos = [];

        document.addEventListener('DOMContentLoaded', async () => {
            document.getElementById('input-fecha-cliente').value = new Date().toISOString().split('T')[0];
            await cargarMisFotos();

            document.getElementById('form-subir-cliente').addEventListener('submit', async (e) => {
                e.preventDefault();
                const fileInput = document.getElementById('input-foto-cliente');
                if (!fileInput.files || fileInput.files.length === 0) return;

                const fd = new FormData();
                fd.append('foto', fileInput.files[0]);
                fd.append('semana', document.getElementById('select-semana-cliente').value);
                fd.append('tipo_angulo', document.getElementById('select-angulo-cliente').value);
                fd.append('fecha_subida', document.getElementById('input-fecha-cliente').value);
                fd.append('observaciones', document.getElementById('input-obs-cliente').value.trim());

                const res = await fetch('api.php?action=foto_subir', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.success) {
                    document.getElementById('modal-subir-cliente').classList.add('hidden');
                    document.getElementById('form-subir-cliente').reset();
                    await cargarMisFotos();
                } else {
                    alert(json.message || 'Error al subir foto');
                }
            });
        });

        async function cargarMisFotos() {
            const res = await fetch('api.php?action=fotos_listar');
            const json = await res.json();
            if (json.success) {
                misFotos = json.data;
                renderFotosCliente();
            }
        }

        function renderFotosCliente() {
            const cont = document.getElementById('cliente-fotos-grid');
            const btnComp = document.getElementById('btn-comp-cliente');
            cont.innerHTML = '';

            if (misFotos.length === 0) {
                cont.innerHTML = `<p class="col-span-2 sm:col-span-3 text-center text-xs text-slate-400 py-12">No tienes fotos aún. Presiona "+ Subir Foto" para comenzar.</p>`;
                btnComp.classList.add('hidden');
                return;
            }

            btnComp.classList.remove('hidden');

            misFotos.forEach(f => {
                const c = document.createElement('div');
                c.className = 'bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-lg';
                c.innerHTML = `
                    <div class="aspect-photo bg-slate-950 relative overflow-hidden">
                        <img src="${f.ruta_imagen}" class="w-full h-full object-cover">
                        <div class="absolute top-2 left-2 flex gap-1">
                            <span class="px-2 py-0.5 rounded text-[10px] font-black bg-slate-950/80 text-emerald-400">S${f.semana}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-950/80 text-slate-300 capitalize">${f.tipo_angulo}</span>
                        </div>
                    </div>
                    <div class="p-2 flex items-center justify-between text-xs">
                        <span class="text-[10px] text-slate-400">${f.fecha_subida}</span>
                        <button onclick="eliminarMiFoto(${f.id})" class="p-1 text-slate-500 hover:text-rose-400 transition" title="Eliminar">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                `;
                cont.appendChild(c);
            });
        }

        async function eliminarMiFoto(id) {
            if (!confirm('¿Eliminar esta fotografía de tu perfil?')) return;
            const res = await fetch('api.php?action=foto_eliminar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const json = await res.json();
            if (json.success) {
                await cargarMisFotos();
            } else {
                alert(json.message || 'Error');
            }
        }

        function abrirComparadorCliente() {
            if (misFotos.length < 2) return;
            const sA = document.getElementById('comp-sel-a-cli');
            const sB = document.getElementById('comp-sel-b-cli');
            sA.innerHTML = '';
            sB.innerHTML = '';

            const fotos = [...misFotos].sort((a,b) => new Date(a.fecha_subida) - new Date(b.fecha_subida));
            fotos.forEach(f => {
                const optA = document.createElement('option');
                optA.value = f.id;
                optA.textContent = `S${f.semana} (${f.tipo_angulo}) - ${f.fecha_subida}`;
                sA.appendChild(optA);

                const optB = document.createElement('option');
                optB.value = f.id;
                optB.textContent = `S${f.semana} (${f.tipo_angulo}) - ${f.fecha_subida}`;
                sB.appendChild(optB);
            });

            sA.selectedIndex = 0;
            sB.selectedIndex = fotos.length - 1;
            renderCompCliente();
            document.getElementById('modal-comp-cliente').classList.remove('hidden');
        }

        function renderCompCliente() {
            const idA = document.getElementById('comp-sel-a-cli').value;
            const idB = document.getElementById('comp-sel-b-cli').value;
            const fA = misFotos.find(f => f.id == idA);
            const fB = misFotos.find(f => f.id == idB);
            if (fA) {
                document.getElementById('comp-img-a-cli').src = fA.ruta_imagen;
                document.getElementById('comp-tag-a-cli').textContent = `S${fA.semana}`;
                document.getElementById('comp-fecha-a-cli').textContent = fA.fecha_subida;
            }
            if (fB) {
                document.getElementById('comp-img-b-cli').src = fB.ruta_imagen;
                document.getElementById('comp-tag-b-cli').textContent = `S${fB.semana}`;
                document.getElementById('comp-fecha-b-cli').textContent = fB.fecha_subida;
            }
        }
    </script>
</body>
</html>
