# Simply Fitness — scraper estático + dinámico

Esta versión amplía el scraping estático con Playwright/Chromium para detectar imágenes que se cargan al desplazarse, mediante `srcset` y atributos `data-*`, CSS `background-image`, URLs incrustadas en el HTML y recursos de imagen observados en la red del navegador. También intenta avanzar controles de galería comunes.

## Requisitos (Windows)
Instala Python 3.10 o posterior. En PowerShell, dentro de esta carpeta:

```powershell
python -m pip install requests beautifulsoup4 playwright
python -m playwright install chromium
python scrape_simplyfitness.py
```

Prueba limitada:
```powershell
python scrape_simplyfitness.py --limit 10
```

Solo modo estático:
```powershell
python scrape_simplyfitness.py --no-browser
```

## Salidas
- `exercise_images.zip`: imágenes descargadas + manifiesto CSV + informe de fallos.
- `exercise_image_manifest.csv`: URL, guía, método de detección, nombre local y estado HTTP.
- `download_failures.csv`: URL y razón de cada fallo/aviso.
- `scrape_report.txt`: resumen.

La selección es deliberadamente amplia para no perder medios cargados de forma dinámica. Conviene revisar el manifiesto y eliminar manualmente imágenes de grupo muscular o banners que se cuelen. El sitio puede cambiar su HTML y ningún filtro automático garantiza una clasificación perfecta.
