#!/usr/bin/env python3
"""
Simply Fitness: extractor híbrido estático + navegador (Playwright).
Descubre las guías enlazadas desde la página principal y recoge imágenes:
- <img src>, srcset, lazy-load (data-src/data-srcset y atributos similares)
- <picture><source>
- CSS background-image
- recursos de imagen solicitados por el navegador
- controles/carruseles de imágenes que se puedan avanzar sin enviar formularios

Instalación:
  python -m pip install requests beautifulsoup4 playwright
  python -m playwright install chromium
Ejecución:
  python scrape_simplyfitness.py
  python scrape_simplyfitness.py --limit 10
"""
import argparse, csv, os, re, time, zipfile
from urllib.parse import urljoin, urlparse, unquote
import requests
from bs4 import BeautifulSoup

START_URL = "https://www.simplyfitness.com/es/pages/workout-exercise-guides"
HEADERS = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36"}
IMAGE_EXTS = (".jpg", ".jpeg", ".png", ".webp", ".avif", ".gif", ".svg")
SKIP_WORDS = ("logo", "avatar", "icon", "favicon", "sprite", "payment", "social", "flag", "placeholder")
EXERCISE_WORDS = ("exercise", "ejercicio", "workout", "guide", "ilustr", "position", "postura", "squat", "press", "curl", "deadlift", "row", "pulldown")

def norm(s):
    return re.sub(r"\s+", " ", str(s or "")).strip()

def safe(s):
    s = re.sub(r"[^A-Za-z0-9._-]+", "_", norm(s))
    return s.strip("._-")[:90] or "exercise"

def absurl(u, page):
    if not u: return ""
    u = u.strip()
    if u.startswith(("data:", "blob:", "javascript:")): return ""
    return urljoin(page, u)

def is_site_page(u):
    p = urlparse(u)
    return p.netloc.lower().endswith("simplyfitness.com") and p.path.startswith("/es/pages/") and p.path != "/es/pages/workout-exercise-guides"

def imageish(u):
    p = urlparse(u)
    path = p.path.lower()
    return path.endswith(IMAGE_EXTS) or "cdn.shopify.com" in p.netloc.lower() or "shopify" in p.netloc.lower()

def reject_asset(url, context):
    hay = (url + " " + context).lower()
    return any(w in hay for w in SKIP_WORDS)

def srcset_urls(value):
    out=[]
    for bit in (value or "").split(","):
        u = bit.strip().split(" ")[0] if bit.strip() else ""
        if u: out.append(u)
    return out

def parse_html_images(html, page_url):
    soup = BeautifulSoup(html, "html.parser")
    found=[]
    # Real image elements, including lazy-load conventions and picture sources.
    for node in soup.select("img, picture source"):
        alt = norm(node.get("alt") or node.get("title") or "")
        context = norm(" ".join([node.get("class", "") if isinstance(node.get("class"), str) else " ".join(node.get("class", [])),
                                node.get("id", ""), alt]))
        attrs = ("src", "data-src", "data-lazy-src", "data-original", "data-image", "data-flickity-lazyload", "data-zoom-image")
        urls=[node.get(a) for a in attrs]
        for attr in ("srcset", "data-srcset", "data-lazy-srcset"):
            urls.extend(srcset_urls(node.get(attr, "")))
        for raw in urls:
            u=absurl(raw, page_url)
            if not u or not imageish(u) or reject_asset(u, context): continue
            # Ignore tiny tracking/transparent placeholders when URL clearly indicates one.
            found.append((u, alt or context or "image element", "DOM image"))
    # CSS background-image URLs on likely content elements.
    for node in soup.select("[style*='background'], [data-bg], [data-background]"):
        context = norm(" ".join([str(node.get("class", "")), str(node.get("id", "")), node.get("aria-label", ""), node.get("alt", "")]))
        style = " ".join([node.get("style",""), node.get("data-bg",""), node.get("data-background","")])
        for raw in re.findall(r"url\((?:['\"])?(.*?)(?:['\"])?\)", style):
            u=absurl(raw, page_url)
            if u and imageish(u) and not reject_asset(u, context):
                found.append((u, context or "CSS background", "CSS background"))
    # Shopify often stores JSON data in script blocks; extract absolute CDN URLs with image-like paths.
    for raw in re.findall(r'https?:\\?/\\?/[^"\'\s<>]+', html):
        raw=raw.replace("\\/", "/").rstrip("\\,;")
        u=absurl(raw, page_url)
        if u and imageish(u) and not reject_asset(u, ""):
            found.append((u, "embedded media URL", "HTML embedded URL"))
    return found, soup

def discover_links(soup):
    links=[]
    for a in soup.select("a[href]"):
        u=urljoin(START_URL, a.get("href","")).split("#")[0]
        label=norm(a.get_text(" ", strip=True) or a.get("aria-label") or a.get("title"))
        if is_site_page(u) and label and not any(x in u.lower() for x in ("/blogs/", "/account", "/collections/", "/products/")):
            links.append((label,u))
    out=[]; seen=set()
    for label,u in links:
        if u not in seen: seen.add(u); out.append((label,u))
    return out

def main():
    ap=argparse.ArgumentParser()
    ap.add_argument("--limit",type=int,default=0,help="Procesar solo N guías (0 = todas)")
    ap.add_argument("--delay",type=float,default=.35,help="Pausa entre guías")
    ap.add_argument("--no-browser",action="store_true",help="No usar Playwright; solo HTML estático")
    args=ap.parse_args()
    os.makedirs("exercise_images",exist_ok=True)
    session=requests.Session(); session.headers.update(HEADERS)
    failures=[]; manifest=[]; guide_list=[]
    try:
        r=session.get(START_URL,timeout=30); r.raise_for_status()
        _, main_soup=parse_html_images(r.text,START_URL)
        guide_list=discover_links(main_soup)
    except Exception as e:
        failures.append(["",START_URL,"",f"Página principal inaccesible por requests: {type(e).__name__}: {e}"])
    if not guide_list:
        # No assume the site is empty if static requests are blocked: use the browser to discover links.
        if not args.no_browser:
            try:
                from playwright.sync_api import sync_playwright
                with sync_playwright() as p:
                    browser=p.chromium.launch(headless=True)
                    page=browser.new_page()
                    page.goto(START_URL,wait_until="domcontentloaded",timeout=60000)
                    page.wait_for_timeout(2500)
                    for a in page.locator("a[href]").all():
                        try:
                            href=urljoin(START_URL,a.get_attribute("href") or "").split("#")[0]
                            label=norm(a.inner_text(timeout=1000))
                            if is_site_page(href) and label: guide_list.append((label,href))
                        except Exception: pass
                    browser.close()
                guide_list=list(dict((u,(label,u)) for label,u in guide_list).values())
            except Exception as e:
                failures.append(["",START_URL,"",f"Descubrimiento dinámico falló: {type(e).__name__}: {e}"])
    if args.limit: guide_list=guide_list[:args.limit]
    print(f"Guías descubiertas: {len(guide_list)}")
    # Candidate records: URL -> best contextual record
    candidates={}
    for ix,(label,url) in enumerate(guide_list,1):
        html=""
        try:
            rr=session.get(url,timeout=30); rr.raise_for_status(); html=rr.text
            rows,soup=parse_html_images(html,url)
            # Main exercise media tends to be inside main/article/product-like content.
            for image_url, context, method in rows:
                # Filter images using contextual cues; retain content-area images and Shopify images.
                if reject_asset(image_url,context): continue
                if any(w in (context+" "+image_url).lower() for w in EXERCISE_WORDS) or "cdn.shopify.com" in image_url:
                    candidates.setdefault(image_url,[label,url,context,image_url,method])
            if not args.no_browser:
                try:
                    from playwright.sync_api import sync_playwright
                    with sync_playwright() as p:
                        browser=p.chromium.launch(headless=True)
                        page=browser.new_page()
                        resources=[]
                        page.on("response", lambda resp: resources.append(resp.url) if resp.request.resource_type=="image" else None)
                        page.goto(url,wait_until="domcontentloaded",timeout=60000)
                        page.wait_for_timeout(1800)
                        # Scroll to trigger lazy-loaded images.
                        for y in range(0, max(1, page.locator("body").evaluate("(el)=>el.scrollHeight")), 700):
                            page.evaluate("(y)=>window.scrollTo(0,y)",y); page.wait_for_timeout(180)
                        # Capture image sources after lazy loading.
                        for item in page.locator("img, picture source").all():
                            try:
                                alt=norm(item.get_attribute("alt") or item.get_attribute("title") or "")
                                for attr in ("src","srcset","data-src","data-srcset","data-lazy-src","data-original","data-zoom-image"):
                                    val=item.get_attribute(attr) or ""
                                    vals=srcset_urls(val) if "srcset" in attr else [val]
                                    for raw in vals:
                                        u=absurl(raw,url)
                                        if u and imageish(u) and not reject_asset(u,alt):
                                            candidates.setdefault(u,[label,url,alt or "dynamic DOM image",u,"Playwright DOM"])
                            except Exception: pass
                        # Advance image galleries/sliders a few times; don't submit forms or click navigation links.
                        for _ in range(12):
                            clicked=False
                            for selector in ("button[aria-label*='next' i]","button[aria-label*='siguiente' i]",
                                             "button[title*='next' i]","button[title*='siguiente' i]",
                                             ".swiper-button-next",".slick-next","button[aria-label*='slide' i]"):
                                try:
                                    loc=page.locator(selector).first
                                    if loc.count() and loc.is_visible():
                                        loc.click(timeout=500); page.wait_for_timeout(250); clicked=True; break
                                except Exception: pass
                            if not clicked: break
                        for u in resources:
                            if imageish(u) and not reject_asset(u,""):
                                candidates.setdefault(u,[label,url,"browser image resource",u,"Playwright network"])
                        browser.close()
                except Exception as e:
                    failures.append([label,url,"",f"Playwright/dynamic pass failed: {type(e).__name__}: {e}"])
        except Exception as e:
            failures.append([label,url,"",f"Guide page failed: {type(e).__name__}: {e}"])
        time.sleep(max(0,args.delay))
    print(f"URLs candidatas únicas: {len(candidates)}")
    # Download only plausible image responses; record precise per-URL failures.
    seen_names=set()
    for i,(u,rec) in enumerate(candidates.items(),1):
        label,guide,alt,src,method=rec
        try:
            resp=session.get(u,timeout=35,stream=True,headers={"Referer":guide})
            resp.raise_for_status()
            ct=resp.headers.get("Content-Type","").split(";")[0].lower()
            if not ct.startswith("image/"):
                raise ValueError(f"El servidor devolvió Content-Type {ct or '(ausente)'}, no una imagen")
            ext=os.path.splitext(urlparse(u).path)[1].lower()
            if ext not in IMAGE_EXTS:
                ext={"image/jpeg":".jpg","image/png":".png","image/webp":".webp","image/gif":".gif","image/avif":".avif","image/svg+xml":".svg"}.get(ct,".img")
            filename=f"{i:04d}_{safe(label)}{ext}"
            root,ex=os.path.splitext(filename); n=2
            while filename in seen_names:
                filename=f"{root}_{n}{ex}"; n+=1
            seen_names.add(filename)
            with open(os.path.join("exercise_images",filename),"wb") as f:
                for chunk in resp.iter_content(65536):
                    if chunk: f.write(chunk)
            manifest.append([label,guide,alt,u,filename,ct,method,resp.status_code])
            print(f"[OK] {filename}")
        except Exception as e:
            failures.append([label,guide,u,f"{type(e).__name__}: {e}"])
            print(f"[FALLO] {u} — {e}")
    with open("exercise_image_manifest.csv","w",newline="",encoding="utf-8-sig") as f:
        w=csv.writer(f); w.writerow(["exercise_or_guide","guide_url","context_or_alt","image_url","filename","content_type","discovery_method","http_status"]); w.writerows(manifest)
    with open("download_failures.csv","w",newline="",encoding="utf-8-sig") as f:
        w=csv.writer(f); w.writerow(["exercise_or_guide","page_url","image_url","reason"]); w.writerows(failures)
    with open("scrape_report.txt","w",encoding="utf-8") as f:
        f.write(f"Guías descubiertas/procesadas: {len(guide_list)}\nCandidatas únicas: {len(candidates)}\nImágenes descargadas: {len(manifest)}\nFallos/avisos: {len(failures)}\n")
        f.write("Métodos: requests/BeautifulSoup + Playwright DOM, lazy-load, recursos de red y controles de galería.\n")
        f.write("Ver download_failures.csv para detalles; revisar el manifiesto para filtrar posibles imágenes no pertinentes.\n")
    with zipfile.ZipFile("exercise_images.zip","w",zipfile.ZIP_DEFLATED) as z:
        for root,_,files in os.walk("exercise_images"):
            for name in files:
                path=os.path.join(root,name); z.write(path,os.path.relpath(path,"."))
        for name in ("exercise_image_manifest.csv","download_failures.csv","scrape_report.txt"):
            if os.path.exists(name): z.write(name,name)
    print(f"Listo: {len(manifest)} imágenes; {len(failures)} fallos/avisos. ZIP: exercise_images.zip")

if __name__=="__main__":
    main()
