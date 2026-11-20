#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script: download_simply_fitness.py
Descripcion: Descarga el catalogo completo de ejercicios desde Simply Fitness
             usando Playwright para renderizar el contenido JS.
Autor: Antigravity AI
"""

import io
import sys
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", errors="replace")
sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding="utf-8", errors="replace")

import os
import re
import json
import time
import shutil
import unicodedata
import urllib.parse
from pathlib import Path

# ──────────────────────────────────────────────────────────────────────────────
# Rutas
# ──────────────────────────────────────────────────────────────────────────────
BASE_DIR    = Path(__file__).parent.parent
ASSETS_DIR  = BASE_DIR / "assets" / "ejercicios"
OUTPUT_JSON = Path(__file__).parent / "exercise_assets.json"
DEFAULT_GIF = ASSETS_DIR / "default.gif"
TARGET_URL  = "https://www.simplyfitness.com/es/pages/workout-exercise-guides"


# ──────────────────────────────────────────────────────────────────────────────
# Utilidades
# ──────────────────────────────────────────────────────────────────────────────
def install(pkg):
    import subprocess
    subprocess.check_call([sys.executable, "-m", "pip", "install", "--quiet", pkg])


def normalize_name(name: str) -> str:
    name = unicodedata.normalize("NFKD", name)
    name = name.encode("ascii", "ignore").decode("ascii")
    name = name.lower()
    name = re.sub(r"[\s\-/\\]+", "_", name)
    name = re.sub(r"[^a-z0-9_]", "", name)
    name = re.sub(r"_+", "_", name).strip("_")
    return name


def get_extension(url: str, default: str = "jpg") -> str:
    path = urllib.parse.urlparse(url).path
    ext  = os.path.splitext(path)[1].lower().lstrip(".")
    ext  = ext.split("?")[0]
    if ext in ("jpg", "jpeg", "png", "gif", "webp", "svg"):
        return ext if ext != "jpeg" else "jpg"
    return default


def ensure_default_gif():
    if DEFAULT_GIF.exists():
        return
    GIF_BYTES = (
        b"GIF87a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff"
        b"\x00\x00\x00!\xf9\x04\x00\x00\x00\x00\x00,"
        b"\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;"
    )
    DEFAULT_GIF.parent.mkdir(parents=True, exist_ok=True)
    DEFAULT_GIF.write_bytes(GIF_BYTES)
    print(f"[+] GIF placeholder creado: {DEFAULT_GIF}")


def download_image(url: str, dest_path: Path, session) -> bool:
    try:
        headers = {
            "User-Agent": (
                "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                "AppleWebKit/537.36 (KHTML, like Gecko) "
                "Chrome/120.0.0.0 Safari/537.36"
            ),
            "Referer": TARGET_URL,
        }
        r = session.get(url, headers=headers, timeout=30, stream=True)
        r.raise_for_status()
        with open(dest_path, "wb") as f:
            for chunk in r.iter_content(chunk_size=8192):
                f.write(chunk)
        return True
    except Exception as e:
        print(f"  [!] Error al descargar {url}: {e}")
        return False


# ──────────────────────────────────────────────────────────────────────────────
# Scraping con Playwright
# ──────────────────────────────────────────────────────────────────────────────
def scrape_with_playwright() -> list[dict]:
    """
    Usa Playwright (Chromium headless) para:
    1. Cargar la pagina principal y obtener los links de grupos musculares.
    2. Entrar a cada grupo y extraer los ejercicios individuales con nombre + imagen.
    """
    from playwright.sync_api import sync_playwright, TimeoutError as PW_Timeout

    exercises = []

    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=True, args=["--no-sandbox"])
        ctx = browser.new_context(
            locale="es-ES",
            user_agent=(
                "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                "AppleWebKit/537.36 (KHTML, like Gecko) "
                "Chrome/120.0.0.0 Safari/537.36"
            ),
            viewport={"width": 1280, "height": 900},
        )
        page = ctx.new_page()

        # ── Paso 1: Cargar pagina principal ──────────────────────────────────
        print(f"[*] Cargando pagina principal: {TARGET_URL}")
        try:
            page.goto(TARGET_URL, wait_until="domcontentloaded", timeout=60_000)
            time.sleep(3)
        except PW_Timeout:
            print("    [!] Timeout en pagina principal, continuando...")

        # Scroll completo para activar lazy load
        for i in range(10):
            page.evaluate("window.scrollBy(0, window.innerHeight * 0.8)")
            time.sleep(0.5)
        page.evaluate("window.scrollTo(0, 0)")
        time.sleep(1)

        # ── Paso 2: Extraer ejercicios de la pagina principal ─────────────────
        print("[*] Extrayendo ejercicios de la pagina principal...")
        main_exercises = page.evaluate("""
            () => {
                const results = [];
                const seen = new Set();

                // Buscar todas las listas/grids de ejercicios
                // Simply Fitness usa grid con items que tienen imagen + texto
                const allLinks = Array.from(document.querySelectorAll('a'));
                
                for (const link of allLinks) {
                    const img = link.querySelector('img');
                    if (!img) continue;
                    
                    // Buscar texto del ejercicio (puede ser en alt, title, o texto hijo)
                    const alt = (img.getAttribute('alt') || '').trim();
                    const title = link.getAttribute('title') || '';
                    const textContent = link.textContent.trim().replace(/\\s+/g, ' ');
                    
                    const name = alt || title || textContent;
                    if (!name || name.length < 2) continue;
                    
                    // Evitar logos, banners, etc.
                    const lname = name.toLowerCase();
                    if (lname.includes('logo') || lname.includes('banner') || 
                        lname.includes('cart') || lname.includes('menu')) continue;
                    
                    const src = img.getAttribute('data-src') || 
                                img.getAttribute('data-lazy-src') ||
                                img.getAttribute('src') || '';
                    
                    const href = link.href || '';
                    
                    if (src && !seen.has(name)) {
                        seen.add(name);
                        results.push({ name, image_url: src, page_url: href });
                    }
                }
                
                // Tambien buscar imagenes sueltas con texto hermano
                const figures = Array.from(document.querySelectorAll('figure, [class*="item"], [class*="card"], li'));
                for (const fig of figures) {
                    const img = fig.querySelector('img');
                    if (!img) continue;
                    
                    const src = img.getAttribute('data-src') || 
                                img.getAttribute('data-lazy-src') ||
                                img.getAttribute('src') || '';
                    if (!src) continue;
                    
                    // Buscar texto del nombre cerca de la imagen
                    const textEls = fig.querySelectorAll('h1,h2,h3,h4,h5,h6,p,span,strong');
                    let name = '';
                    for (const el of textEls) {
                        const t = el.textContent.trim();
                        if (t.length > 2 && t.length < 80) { name = t; break; }
                    }
                    if (!name) name = img.getAttribute('alt') || '';
                    if (!name || name.length < 2) continue;
                    
                    const lname = name.toLowerCase();
                    if (lname.includes('logo') || lname.includes('banner')) continue;
                    
                    if (!seen.has(name)) {
                        seen.add(name);
                        results.push({ name, image_url: src, page_url: '' });
                    }
                }
                
                return results;
            }
        """)
        
        print(f"    -> {len(main_exercises)} ejercicios encontrados en pagina principal")
        exercises.extend(main_exercises)

        # ── Paso 3: Detectar links a subpaginas de grupos musculares ──────────
        print("[*] Buscando links a grupos musculares...")
        muscle_links = page.evaluate("""
            () => {
                const links = new Set();
                const baseUrl = 'simplyfitness.com';
                
                // Buscar links que apuntan a paginas de ejercicios
                for (const a of document.querySelectorAll('a[href]')) {
                    const href = a.href;
                    if (!href.includes(baseUrl)) continue;
                    // Paginas de blogs/articles/collections con ejercicios
                    if (href.includes('/blogs/') || 
                        href.includes('/collections/') ||
                        href.includes('/pages/') ||
                        href.includes('/ejercicios') ||
                        href.includes('/exercise') ||
                        href.includes('/workout')) {
                        links.add(href);
                    }
                }
                return Array.from(links).slice(0, 30);  // maximo 30 subpaginas
            }
        """)
        
        print(f"    -> {len(muscle_links)} links de subpaginas encontrados")

        # ── Paso 4: Visitar cada subpagina y extraer ejercicios ───────────────
        visited = {TARGET_URL}
        for link_url in muscle_links:
            if link_url in visited:
                continue
            visited.add(link_url)
            
            try:
                print(f"    [->] Visitando: {link_url[:70]}...")
                page.goto(link_url, wait_until="domcontentloaded", timeout=30_000)
                time.sleep(2)
                
                # Scroll para lazy load
                for _ in range(5):
                    page.evaluate("window.scrollBy(0, window.innerHeight)")
                    time.sleep(0.4)
                
                sub_exercises = page.evaluate("""
                    () => {
                        const results = [];
                        const seen = new Set();
                        
                        // Buscar pares imagen+nombre
                        const containers = document.querySelectorAll(
                            'article, figure, [class*="exercise"], [class*="card"], ' +
                            '[class*="item"], [class*="workout"], li'
                        );
                        
                        for (const c of containers) {
                            const img = c.querySelector('img');
                            if (!img) continue;
                            
                            const src = img.getAttribute('data-src') || 
                                        img.getAttribute('data-lazy-src') ||
                                        img.getAttribute('src') || '';
                            if (!src) continue;
                            
                            const textEls = c.querySelectorAll('h1,h2,h3,h4,h5,h6,p,span,strong,a');
                            let name = '';
                            for (const el of textEls) {
                                const t = el.textContent.trim().replace(/\\s+/g, ' ');
                                if (t.length > 2 && t.length < 80) { name = t; break; }
                            }
                            if (!name) name = img.getAttribute('alt') || '';
                            
                            if (!name || name.length < 2) continue;
                            const lname = name.toLowerCase();
                            if (lname.includes('logo') || lname.includes('banner') ||
                                lname.includes('cart') || lname.includes('search')) continue;
                            
                            if (!seen.has(name)) {
                                seen.add(name);
                                results.push({ name, image_url: src });
                            }
                        }
                        return results;
                    }
                """)
                
                print(f"        -> {len(sub_exercises)} ejercicios encontrados")
                exercises.extend(sub_exercises)
                
            except Exception as e:
                print(f"        [!] Error en {link_url}: {e}")
                continue

        browser.close()

    return exercises


# ──────────────────────────────────────────────────────────────────────────────
# Pipeline principal
# ──────────────────────────────────────────────────────────────────────────────
def main():
    print("=" * 60)
    print("  Simply Fitness - Descarga de Catalogo de Ejercicios")
    print("=" * 60)

    # 1. Instalar dependencias
    print("\n[1/5] Verificando dependencias...")
    for pip_name, import_name in {"requests": "requests", "playwright": "playwright"}.items():
        try:
            __import__(import_name)
        except ImportError:
            print(f"  -> Instalando {pip_name}...")
            install(pip_name)

    # Playwright chromium
    try:
        import subprocess
        result = subprocess.run(
            [sys.executable, "-m", "playwright", "install", "chromium", "--with-deps"],
            capture_output=True, text=True, timeout=300
        )
        if result.returncode != 0:
            print(f"  [!] playwright install: {result.stderr[:200]}")
    except Exception as e:
        print(f"  [!] No se pudo instalar Playwright chromium: {e}")

    import requests

    # 2. Preparar directorios
    print("\n[2/5] Preparando directorios...")
    ASSETS_DIR.mkdir(parents=True, exist_ok=True)
    ensure_default_gif()
    print(f"  -> {ASSETS_DIR}")

    # 3. Scraping con Playwright
    print("\n[3/5] Extrayendo catalogo (modo Playwright)...")
    raw_exercises = scrape_with_playwright()

    if not raw_exercises:
        print("\n[!] No se encontraron ejercicios.")
        sys.exit(1)

    # Deduplicar por nombre normalizado
    seen_keys = set()
    unique_exercises = []
    for ex in raw_exercises:
        key = normalize_name(ex.get("name", ""))
        if key and key not in seen_keys:
            seen_keys.add(key)
            unique_exercises.append(ex)

    print(f"\n  -> Total ejercicios unicos: {len(unique_exercises)}")

    # 4. Descargar imagenes
    print("\n[4/5] Descargando imagenes...")
    session  = requests.Session()
    asset_map = {}
    total     = len(unique_exercises)

    for idx, ex in enumerate(unique_exercises, 1):
        name      = ex["name"]
        image_url = ex.get("image_url", "")
        norm      = normalize_name(name)

        if not norm:
            continue

        # Normalizar URL
        if image_url.startswith("//"):
            image_url = "https:" + image_url
        elif image_url.startswith("/"):
            image_url = "https://www.simplyfitness.com" + image_url

        # Limpiar parametros de Shopify que pueden truncar la URL
        # Ejemplo: ...photo.jpg?v=1234&width=360 -> ...photo.jpg
        if "?" in image_url and not image_url.endswith("gif"):
            base_url = image_url.split("?")[0]
            # Solo limpiar si la base tiene extension valida
            if any(base_url.lower().endswith(ext) for ext in (".jpg",".png",".gif",".webp",".svg")):
                image_url = base_url

        ext       = get_extension(image_url)
        filename  = f"{norm}.{ext}"
        dest_path = ASSETS_DIR / filename
        rel_path  = f"assets/ejercicios/{filename}"

        print(f"  [{idx:>3}/{total}] {name[:45]:<45} -> {filename}")

        if dest_path.exists() and dest_path.stat().st_size > 100:
            print(f"           (ya existe, omitiendo)")
            asset_map[name] = rel_path
            continue

        success = False
        if image_url:
            success = download_image(image_url, dest_path, session)

        if not success:
            print(f"           [!] Usando default.gif como fallback")
            shutil.copy(DEFAULT_GIF, dest_path)

        asset_map[name] = rel_path

    # 5. Guardar JSON
    print(f"\n[5/5] Guardando mapeo JSON -> {OUTPUT_JSON}")
    OUTPUT_JSON.parent.mkdir(parents=True, exist_ok=True)
    with open(OUTPUT_JSON, "w", encoding="utf-8") as f:
        json.dump(asset_map, f, ensure_ascii=False, indent=2)

    print("\n" + "=" * 60)
    print(f"  [OK] Completado: {len(asset_map)} ejercicios procesados")
    print(f"  [OK] Imagenes en:  {ASSETS_DIR}")
    print(f"  [OK] JSON en:      {OUTPUT_JSON}")
    print("=" * 60)


if __name__ == "__main__":
    main()
