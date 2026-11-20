import os
import json

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ASSETS_DIR = os.path.join(BASE_DIR, "assets", "ejercicios")
SCRIPTS_DIR = os.path.join(BASE_DIR, "scripts")

os.makedirs(ASSETS_DIR, exist_ok=True)

# Catálogo extendido de 60 ejercicios
EJERCICIOS = [
    # Pecho (10)
    {"nombre": "Press de banca con barra", "grupo": "Pecho", "filename": "press_banca_plano.svg", "color": "#ef4444"},
    {"nombre": "Press inclinado con mancuernas", "grupo": "Pecho", "filename": "press_inclinado_mancuernas.svg", "color": "#ef4444"},
    {"nombre": "Press declinado con barra", "grupo": "Pecho", "filename": "press_declinado_barra.svg", "color": "#ef4444"},
    {"nombre": "Aperturas en banco plano", "grupo": "Pecho", "filename": "aperturas_banco_plano.svg", "color": "#ef4444"},
    {"nombre": "Aperturas inclinadas con mancuernas", "grupo": "Pecho", "filename": "aperturas_inclinadas.svg", "color": "#ef4444"},
    {"nombre": "Cruce de poleas desde abajo", "grupo": "Pecho", "filename": "cruce_poleas_abajo.svg", "color": "#ef4444"},
    {"nombre": "Cruce de poleas desde arriba", "grupo": "Pecho", "filename": "cruce_poleas_arriba.svg", "color": "#ef4444"},
    {"nombre": "Pec Deck / Contractor de pecho", "grupo": "Pecho", "filename": "pec_deck.svg", "color": "#ef4444"},
    {"nombre": "Flexiones de pecho tradicionales", "grupo": "Pecho", "filename": "flexiones_pecho.svg", "color": "#ef4444"},
    {"nombre": "Pullover con mancuerna", "grupo": "Pecho", "filename": "pullover_mancuerna.svg", "color": "#ef4444"},

    # Espalda (10)
    {"nombre": "Remo con barra prono", "grupo": "Espalda", "filename": "remo_barra_prono.svg", "color": "#8b5cf6"},
    {"nombre": "Jalon al pecho agarre ancho", "grupo": "Espalda", "filename": "jalon_al_pecho.svg", "color": "#8b5cf6"},
    {"nombre": "Jalon al pecho agarre neutro", "grupo": "Espalda", "filename": "jalon_agarre_neutro.svg", "color": "#8b5cf6"},
    {"nombre": "Dominadas en barra fija", "grupo": "Espalda", "filename": "dominadas.svg", "color": "#8b5cf6"},
    {"nombre": "Remo en polea baja sentado", "grupo": "Espalda", "filename": "remo_polea_baja.svg", "color": "#8b5cf6"},
    {"nombre": "Remo unipersonal con mancuerna", "grupo": "Espalda", "filename": "remo_mancuerna.svg", "color": "#8b5cf6"},
    {"nombre": "Remo en barra T", "grupo": "Espalda", "filename": "remo_barra_t.svg", "color": "#8b5cf6"},
    {"nombre": "Pull-over en polea alta con cuerda", "grupo": "Espalda", "filename": "pullover_polea.svg", "color": "#8b5cf6"},
    {"nombre": "Encogimientos de hombros con mancuernas", "grupo": "Espalda", "filename": "encogimientos_trapecio.svg", "color": "#8b5cf6"},
    {"nombre": "Hiperextensiones lumbares", "grupo": "Espalda", "filename": "hiperextensiones_lumbares.svg", "color": "#8b5cf6"},

    # Hombros (8)
    {"nombre": "Press militar con mancuernas", "grupo": "Hombros", "filename": "press_militar_mancuernas.svg", "color": "#ec4899"},
    {"nombre": "Press militar con barra de pie", "grupo": "Hombros", "filename": "press_militar_barra.svg", "color": "#ec4899"},
    {"nombre": "Vuelos laterales con mancuernas", "grupo": "Hombros", "filename": "vuelos_laterales.svg", "color": "#ec4899"},
    {"nombre": "Vuelos laterales en polea baja", "grupo": "Hombros", "filename": "vuelos_laterales_polea.svg", "color": "#ec4899"},
    {"nombre": "Pajaros / Vuelos posteriores con mancuernas", "grupo": "Hombros", "filename": "vuelos_posteriores.svg", "color": "#ec4899"},
    {"nombre": "Face pulls en polea con cuerda", "grupo": "Hombros", "filename": "face_pulls.svg", "color": "#ec4899"},
    {"nombre": "Elevaciones frontales con disco", "grupo": "Hombros", "filename": "elevaciones_frontales_disco.svg", "color": "#ec4899"},
    {"nombre": "Press Arnold con mancuernas", "grupo": "Hombros", "filename": "press_arnold.svg", "color": "#ec4899"},

    # Piernas & Cuadriceps (8)
    {"nombre": "Sentadilla trasera con barra", "grupo": "Piernas", "filename": "sentadilla_con_barra.svg", "color": "#10b981"},
    {"nombre": "Sentadilla frontal con barra", "grupo": "Piernas", "filename": "sentadilla_frontal.svg", "color": "#10b981"},
    {"nombre": "Prensa de piernas 45 grados", "grupo": "Piernas", "filename": "prensa_piernas.svg", "color": "#10b981"},
    {"nombre": "Sillon cuadriceps / Extensiones", "grupo": "Piernas", "filename": "extensiones_cuadriceps.svg", "color": "#10b981"},
    {"nombre": "Sentadilla Hack en maquina", "grupo": "Piernas", "filename": "sentadilla_hack.svg", "color": "#10b981"},
    {"nombre": "Zancadas / Estocadas con mancuernas", "grupo": "Piernas", "filename": "zancadas_mancuernas.svg", "color": "#10b981"},
    {"nombre": "Sentadilla Búlgara con mancuernas", "grupo": "Piernas", "filename": "sentadilla_bulgara.svg", "color": "#10b981"},
    {"nombre": "Adductores en maquina", "grupo": "Piernas", "filename": "adductores_maquina.svg", "color": "#10b981"},

    # Isquios, Glúteos & Gemelos (8)
    {"nombre": "Peso muerto rumano", "grupo": "Isquios", "filename": "peso_muerto_rumano.svg", "color": "#f59e0b"},
    {"nombre": "Peso muerto convencional con barra", "grupo": "Isquios", "filename": "peso_muerto_convencional.svg", "color": "#f59e0b"},
    {"nombre": "Curl femoral acostado en maquina", "grupo": "Isquios", "filename": "curl_femoral_acostado.svg", "color": "#f59e0b"},
    {"nombre": "Curl femoral sentado en maquina", "grupo": "Isquios", "filename": "curl_femoral_sentado.svg", "color": "#f59e0b"},
    {"nombre": "Hip Thrust con barra", "grupo": "Gluteos", "filename": "hip_thrust.svg", "color": "#f59e0b"},
    {"nombre": "Patada de gluteo en polea", "grupo": "Gluteos", "filename": "patada_gluteo_polea.svg", "color": "#f59e0b"},
    {"nombre": "Elevacion de talones parado en maquina", "grupo": "Gemelos", "filename": "gemelos_parado.svg", "color": "#10b981"},
    {"nombre": "Elevacion de talones sentado", "grupo": "Gemelos", "filename": "gemelos_sentado.svg", "color": "#10b981"},

    # Bíceps (6)
    {"nombre": "Curl de biceps con barra Z", "grupo": "Biceps", "filename": "curl_biceps_barra.svg", "color": "#06b6d4"},
    {"nombre": "Curl martillo con mancuernas", "grupo": "Biceps", "filename": "curl_martillo.svg", "color": "#06b6d4"},
    {"nombre": "Curl de biceps alternado con mancuernas", "grupo": "Biceps", "filename": "curl_alternado.svg", "color": "#06b6d4"},
    {"nombre": "Curl en banco Scott con barra Z", "grupo": "Biceps", "filename": "curl_scott.svg", "color": "#06b6d4"},
    {"nombre": "Curl concentrado con mancuerna", "grupo": "Biceps", "filename": "curl_concentrado.svg", "color": "#06b6d4"},
    {"nombre": "Curl en polea baja con barra recta", "grupo": "Biceps", "filename": "curl_polea_baja.svg", "color": "#06b6d4"},

    # Tríceps (5)
    {"nombre": "Extension de triceps en polea con cuerda", "grupo": "Triceps", "filename": "extension_triceps_polea.svg", "color": "#3b82f6"},
    {"nombre": "Press frances con barra Z en banco plano", "grupo": "Triceps", "filename": "press_frances.svg", "color": "#3b82f6"},
    {"nombre": "Fondos en paralelas para triceps", "grupo": "Triceps", "filename": "fondos_paralelas.svg", "color": "#3b82f6"},
    {"nombre": "Extension copa a dos manos con mancuerna", "grupo": "Triceps", "filename": "extension_copa.svg", "color": "#3b82f6"},
    {"nombre": "Patada de triceps con mancuerna", "grupo": "Triceps", "filename": "patada_triceps.svg", "color": "#3b82f6"},

    # Abdomen & Core (5)
    {"nombre": "Crunches abdominales en colchoneta", "grupo": "Abdomen", "filename": "crunches_abdominales.svg", "color": "#6366f1"},
    {"nombre": "Elevacion de piernas colgado en barra", "grupo": "Abdomen", "filename": "elevacion_piernas.svg", "color": "#6366f1"},
    {"nombre": "Plancha abdominal isometrica", "grupo": "Abdomen", "filename": "plancha_isometrica.svg", "color": "#6366f1"},
    {"nombre": "Rueda abdominal / Ab Wheel", "grupo": "Abdomen", "filename": "rueda_abdominal.svg", "color": "#6366f1"},
    {"nombre": "Twist ruso con disco", "grupo": "Abdomen", "filename": "twist_ruso.svg", "color": "#6366f1"}
]

print("=" * 60)
print(f" Generando catálogo de {len(EJERCICIOS)} ejercicios locales")
print("=" * 60)

mapping = {}
sql_inserts = ["TRUNCATE TABLE ejercicios;\n"]

for item in EJERCICIOS:
    dest_path = os.path.join(ASSETS_DIR, item["filename"])
    rel_path = f"assets/ejercicios/{item['filename']}"
    
    svg_content = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300" width="100%" height="100%">
  <rect width="400" height="300" fill="#090d16" rx="16"/>
  <rect x="20" y="20" width="360" height="260" fill="none" stroke="{item['color']}" stroke-width="2" stroke-dasharray="6,6" opacity="0.3" rx="12"/>
  <circle cx="200" cy="110" r="45" fill="{item['color']}" opacity="0.15"/>
  <path d="M185 95 L220 110 L185 125 Z" fill="{item['color']}"/>
  <text x="200" y="195" font-family="sans-serif" font-size="15" font-weight="bold" fill="#ffffff" text-anchor="middle">{item['nombre']}</text>
  <rect x="150" y="215" width="100" height="24" fill="{item['color']}" opacity="0.2" rx="12"/>
  <text x="200" y="231" font-family="sans-serif" font-size="11" font-weight="bold" fill="{item['color']}" text-anchor="middle">{item['grupo'].upper()}</text>
</svg>'''

    with open(dest_path, "w", encoding="utf-8") as f:
        f.write(svg_content)
        
    mapping[item["nombre"]] = rel_path
    sql = f"INSERT INTO ejercicios (nombre_ejercicio, grupo_muscular, imagen_url) VALUES ('{item['nombre']}', '{item['grupo']}', '{rel_path}');\n"
    sql_inserts.append(sql)

json_path = os.path.join(SCRIPTS_DIR, "exercise_assets.json")
with open(json_path, "w", encoding="utf-8") as f:
    json.dump(mapping, f, ensure_ascii=False, indent=2)

sql_path = os.path.join(BASE_DIR, "seed_ejercicios.sql")
with open(sql_path, "w", encoding="utf-8") as f:
    f.writelines(sql_inserts)

print(f" [OK] {len(EJERCICIOS)} imágenes e instrucciones guardadas con éxito.")