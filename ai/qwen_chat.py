"""Pipeline del buzon virtual ciudadano de Ceuta.

Lee las comunicaciones ciudadanas pendientes desde la base de datos
MySQL/MariaDB gestionada con phpMyAdmin y sigue el protocolo: filtro de
emergencia, minimizacion de datos personales, clasificacion doble
(categoria/subcategoria/organismo) y generacion del "nivel B" (seudonimizado)
mas los archivos agregados por categoria y el cuadro maestro, tal como
describen las secciones 4 y 5 del protocolo.
"""

import hashlib
import json
import re
import uuid
from datetime import datetime, timezone
from pathlib import Path

import pandas as pd
import pymysql
import torch
from transformers import AutoModelForCausalLM, AutoTokenizer, BitsAndBytesConfig

from db_config import DB_CONFIG, TABLA_COMUNICACIONES

MODEL_NAME = "Qwen/Qwen3-8B"

BASE_DIR = Path(__file__).resolve().parent
OUTPUT_DIR = BASE_DIR / "salida_buzon"

# Ajustar al esquema real de phpMyAdmin.
COLUMNA_ID = "id"
COLUMNA_TEXTO = "texto_original"
COLUMNA_RESULTADO_ESPERADO = "resultado_esperado_original"
COLUMNA_CATEGORIA_USUARIO = "categoria_usuario"
COLUMNA_SUBCATEGORIAS_USUARIO = "subcategorias_usuario"
COLUMNA_ESTADO = "procesado"

SQL_QUERY = f"""
SELECT D.id,D.sugerencia_id,D.texto_original,D.resultado_esperado_original,
D.procesado,C.nombre AS categoria_usuario,
GROUP_CONCAT(DISTINCT SU.nombre ORDER BY SU.nombre SEPARATOR '; ') AS subcategorias_usuario
FROM datos_originales AS D 
INNER JOIN sugerencias AS S ON D.sugerencia_id=S.id 
INNER JOIN categorias AS C ON S.categoria_id=C.id 
INNER JOIN sugerencia_subcategoria AS SB ON S.id=SB.sugerencia_id 
INNER JOIN subcategorias AS SU ON SB.subcategoria_id=SU.id
WHERE D.procesado=0
GROUP BY D.id,D.sugerencia_id,D.texto_original,D.resultado_esperado_original,D.procesado,C.nombre;
"""

# Categorias y subcategorias disponibles en las tablas de la base de datos.
TAXONOMIA = [
    ("Economía", "Empleo"),
    ("Economía", "Comercio"),
    ("Economía", "Emprendimiento"),
    ("Economía", "Turismo"),
    ("Economía", "Ayudas"),
    ("Economía", "Transformación económica"),
    ("Educación no universitaria", "Centros"),
    ("Educación no universitaria", "Escolarización"),
    ("Educación no universitaria", "Profesorado"),
    ("Educación no universitaria", "Currículo"),
    ("Educación no universitaria", "Formación profesional"),
    ("Educación no universitaria", "Convivencia escolar"),
    ("Medioambiente", "Residuos"),
    ("Medioambiente", "Limpieza"),
    ("Medioambiente", "Ruido"),
    ("Medioambiente", "Zonas verdes"),
    ("Medioambiente", "Contaminación"),
    ("Medioambiente", "Sostenibilidad"),
    ("Movilidad", "Transporte público"),
    ("Movilidad", "Tráfico"),
    ("Movilidad", "Aparcamiento"),
    ("Movilidad", "Itinerarios peatonales"),
    ("Movilidad", "Accesibilidad"),
    ("Movilidad", "Carga y descarga"),
    ("Sanidad", "Salud pública"),
    ("Sanidad", "Atención sanitaria"),
    ("Sanidad", "Accesibilidad sanitaria"),
    ("Sanidad", "Prevención"),
    ("Seguridad", "Seguridad ciudadana"),
    ("Seguridad", "Seguridad de instalaciones"),
    ("Seguridad", "Policía local"),
    ("Seguridad", "Prevención"),
    ("Servicios Sociales", "Atención social"),
    ("Servicios Sociales", "Dependencia"),
    ("Servicios Sociales", "Inclusión"),
    ("Servicios Sociales", "Vulnerabilidad"),
    ("Servicios Sociales", "Igualdad"),
    ("Servicios Sociales", "Apoyo comunitario"),
    ("Universidad", "Docencia"),
    ("Universidad", "Investigación"),
    ("Universidad", "Becas"),
    ("Universidad", "Instalaciones"),
    ("Universidad", "Vida universitaria"),
    ("Urbanismo", "Planeamiento"),
    ("Urbanismo", "Licencias"),
    ("Urbanismo", "Vivienda"),
    ("Urbanismo", "Obras"),
    ("Urbanismo", "Accesibilidad urbana"),
    ("Urbanismo", "Patrimonio"),
]
VALID_CATEGORIES = {category for category, _ in TAXONOMIA}

EMERGENCY_KEYWORDS = [
    "emergencia", "riesgo vital", "peligro de muerte", "violencia", "agresion",
    "arma", "suicidio", "menor en riesgo", "maltrato", "abuso", "incendio",
    "explosion", "delito en curso", "secuestro",
]

PII_PATTERNS = [
    re.compile(r"[\w.+-]+@[\w-]+\.[\w.-]+"),                       # email
    re.compile(r"\b\d{8}[A-Za-z]\b"),                              # DNI
    re.compile(r"\b[XYZxyz]\d{7}[A-Za-z]\b"),                      # NIE
    re.compile(r"\b(?:\+?\d{2,3}[ -]?)?\d{9}\b"),                  # telefono
]

OFFENSIVE_PATTERNS = [
    re.compile(r"\bhijo(?:\s+de)?\s+puta\b", re.IGNORECASE),
    re.compile(r"\bputa\b", re.IGNORECASE),
    re.compile(r"\bputo\b", re.IGNORECASE),
    re.compile(r"\bmaricon(?:es)?\b", re.IGNORECASE),
    re.compile(r"\bidiota\b", re.IGNORECASE),
    re.compile(r"\bimbecil\b", re.IGNORECASE),
]

# Cuantizacion 4-bit: el modelo en bf16 (~16GB) no cabe en 8GB de VRAM
quant_config = BitsAndBytesConfig(
    load_in_4bit=True,
    bnb_4bit_quant_type="nf4",
    bnb_4bit_compute_dtype=torch.bfloat16,
)

tokenizer = AutoTokenizer.from_pretrained(MODEL_NAME)
model = AutoModelForCausalLM.from_pretrained(
    MODEL_NAME,
    quantization_config=quant_config,
    device_map="cuda",
    low_cpu_mem_usage=True,
)


def generate_response(prompt: str, system_prompt: str | None = None, max_new_tokens: int = 512) -> str:
    messages = []
    if system_prompt:
        messages.append({"role": "system", "content": system_prompt})
    messages.append({"role": "user", "content": prompt})
    text = tokenizer.apply_chat_template(
        messages,
        tokenize=False,
        add_generation_prompt=True,
        enable_thinking=False,
    )
    inputs = tokenizer([text], return_tensors="pt").to(model.device)

    output_ids = model.generate(
        **inputs,
        max_new_tokens=max_new_tokens,
    )
    new_tokens = output_ids[0][inputs["input_ids"].shape[1]:]
    return tokenizer.decode(new_tokens, skip_special_tokens=True)


def redact_pii(text: str) -> tuple[str, bool]:
    """Sustituye emails/telefonos/DNI-NIE por marcador. Devuelve (texto, se_encontro_algo)."""
    redacted = text
    found = False
    for pattern in PII_PATTERNS:
        redacted, n = pattern.subn("[dato personal eliminado]", redacted)
        found = found or n > 0
    return redacted, found


def sanitize_text(text: str) -> tuple[str, bool]:
    sanitized, found = redact_pii(text)
    for pattern in OFFENSIVE_PATTERNS:
        sanitized, count = pattern.subn("[lenguaje ofensivo eliminado]", sanitized)
        found = found or count > 0
    return sanitized, found


def is_emergency(text: str) -> bool:
    lowered = text.lower()
    return any(keyword in lowered for keyword in EMERGENCY_KEYWORDS)


def build_classification_prompt(
    text: str, user_category: str = "", user_subcategories: str = ""
) -> str:
    categorias = "\n".join(f"- {cat} -> {subcat}" for cat, subcat in TAXONOMIA)
    return (
        "Eres el clasificador del buzon virtual ciudadano de Ceuta. Sigue el protocolo:\n"
        "1. Elimina cualquier dato personal restante (nombres,nombres de personas,nombres propios, direcciones exactas, telefonos, "
        "correos, DNI/NIE),incluyendo menciones a su vida privada(relaciones familiares, problemas de salud, etc.), y sustituyelo por \"[dato personal eliminado]\".\n"
        "2. Elige exactamente una categoria_principal y hasta 2 subcategorias de la lista; "
        "no inventes categorias ni subcategorias fuera de la lista.\n"
        "3. Compara la clasificacion del usuario con la tuya usando el texto y elige la mas adecuada; "
        "devuelve decision_comparacion como 'usuario' o 'llm' y justifica brevemente la decision.\n"
        "4. Propon un organismo_propuesto (no es resolucion firme, solo propuesta).\n"
        "5. Si la informacion es insuficiente o afecta a varios organismos, usa "
        "\"Competencia por confirmar\".\n"
        "6. Evalua nivel_urgencia como \"baja\", \"media\" o \"alta\" segun riesgo y reversibilidad.\n"
        "7. Si el texto contiene informacion personal o sensible, marca \"requiere_revision_privacidad\": true.\n" \
        "8. Si el texto contiene palabrotas, insultos o lenguaje ofensivo, sustituyelos por \"[lenguaje ofensivo eliminado]\".\n"
        "9. Si el texto contiene lenguaje discriminatorio, sustituyelos por \"[lenguaje discriminatorio eliminado]\".\n"
        "10. Si el texto contiene informacion que pueda ser considerada como noticia falsa,inverosimil(NO EXISTEN LOS HOMBRES LAGARTO) o fantasiosa pon requiere_revision_privacidad: true.\n"
        "No clasifiques por nacionalidad, origen, ideologia o religion.\n\n"
        f"Categorias disponibles:\n{categorias}\n\n"
        f"Clasificacion introducida por el usuario:\n"
        f"- categoria: {user_category or '(no disponible)'}\n"
        f"- subcategorias: {user_subcategories or '(sin subcategoria)'}\n\n"
        f"Texto de la comunicacion:\n\"\"\"{text}\"\"\"\n\n"
        "Responde SOLO con un JSON valido, sin explicaciones, con estas claves exactas:\n"
        "{\"categoria_principal\": \"...\", \"subcategorias\": [\"...\"], "
        "\"decision_comparacion\": \"usuario|llm\", "
        "\"justificacion_comparacion\": \"...\", "
        "\"organismo_propuesto\": \"...\", \"nivel_urgencia\": \"baja|media|alta\", "
        "\"texto_desidentificado\": \"...\", \"requiere_revision_privacidad\": true|false}"
    )


def parse_model_json(raw_response: str) -> dict:
    match = re.search(r"\{.*\}", raw_response, re.DOTALL)
    if not match:
        raise ValueError("El modelo no devolvio JSON")
    return json.loads(match.group(0))


def normalize_subcategories(value: object, category: str) -> str:
    if isinstance(value, str):
        candidates = value.split(";")
    elif isinstance(value, list):
        candidates = value
    else:
        candidates = []

    valid_subcategories = {
        subcategory
        for tax_category, subcategory in TAXONOMIA
        if tax_category == category and subcategory
    }
    selected = []
    for candidate in candidates:
        subcategory = str(candidate).strip()
        if subcategory in valid_subcategories and subcategory not in selected:
            selected.append(subcategory)
        if len(selected) == 2:
            break
    return "; ".join(selected)


def classify_text(
    text: str, user_category: str = "", user_subcategories: str = ""
) -> dict:
    """Aplica el paso de clasificacion doble (seccion 4.2.4) con fallback seguro."""
    prompt = build_classification_prompt(text, user_category, user_subcategories)
    try:
        raw = generate_response(prompt, max_new_tokens=400)
        data = parse_model_json(raw)
        llm_category = data.get("categoria_principal", "Competencia por confirmar")
        if llm_category not in VALID_CATEGORIES:
            llm_category = "Competencia por confirmar"
        llm_subcategories = normalize_subcategories(
            data.get("subcategorias", data.get("subcategoria", "")), llm_category
        )
        desidentified_text, _ = sanitize_text(data.get("texto_desidentificado", text))
        user_subcategories = normalize_subcategories(user_subcategories, user_category)
        decision = data.get("decision_comparacion", "llm")
        selected_category = user_category if decision == "usuario" and user_category else llm_category
        selected_subcategories = (
            user_subcategories if decision == "usuario" and user_category else llm_subcategories
        )
        return {
            "categoria_principal": selected_category,
            "subcategoria": selected_subcategories,
            "categoria_llm": llm_category,
            "subcategoria_llm": llm_subcategories,
            "categoria_usuario": user_category,
            "subcategoria_usuario": user_subcategories,
            "decision_comparacion": decision if decision in {"usuario", "llm"} else "llm",
            "justificacion_comparacion": data.get("justificacion_comparacion", ""),
            "organismo_propuesto": data.get("organismo_propuesto", "Unidad gestora del buzon (pendiente de asignacion)"),
            "nivel_urgencia": data.get("nivel_urgencia", "media"),
            "texto_desidentificado": desidentified_text,
            "requiere_revision_privacidad": bool(data.get("requiere_revision_privacidad", True)),
        }
    except (ValueError, json.JSONDecodeError):
        # Fallback conservador: se marca para revision humana en vez de perder el registro.
        return {
            "categoria_principal": "Competencia por confirmar",
            "subcategoria": "",
            "categoria_llm": "Competencia por confirmar",
            "subcategoria_llm": "",
            "categoria_usuario": user_category,
            "subcategoria_usuario": user_subcategories,
            "decision_comparacion": "llm",
            "justificacion_comparacion": "No se pudo comparar la clasificacion del usuario con la del modelo.",
            "organismo_propuesto": "Unidad gestora del buzon (pendiente de asignacion)",
            "nivel_urgencia": "media",
            "texto_desidentificado": text,
            "requiere_revision_privacidad": True,
        }


def duplicate_hash(text: str) -> str:
    normalized = re.sub(r"\W+", " ", text.lower()).strip()
    return hashlib.sha256(normalized.encode("utf-8")).hexdigest()


def fetch_records(query: str) -> pd.DataFrame:
    """Ejecuta una consulta SQL arbitraria contra la BD y devuelve el resultado."""
    connection = pymysql.connect(**DB_CONFIG, cursorclass=pymysql.cursors.DictCursor)
    try:
        with connection.cursor() as cursor:
            cursor.execute(query)
            rows = cursor.fetchall()
    finally:
        connection.close()
    return pd.DataFrame(rows)


def mark_as_processed(ids: list) -> None:
    """Marca los registros ya tratados con procesado = 1 para no releerlos."""
    """if not ids:
        return
    connection = pymysql.connect(**DB_CONFIG)
    try:
        placeholders = ", ".join(["%s"] * len(ids))
        query = (
            f"UPDATE {TABLA_COMUNICACIONES} SET {COLUMNA_ESTADO} = 1 "
            f"WHERE {COLUMNA_ID} IN ({placeholders})"
        )
        with connection.cursor() as cursor:
            cursor.execute(query, ids)
        connection.commit()
    finally:
        connection.close()"""


def process_records(df: pd.DataFrame, text_column: str) -> dict:
    if text_column not in df.columns:
        raise ValueError(f"La columna '{text_column}' no existe. Columnas disponibles: {list(df.columns)}")

    now = datetime.now(timezone.utc).isoformat()
    seen_hashes: dict[str, int] = {}
    records = []

    for _, row in df.iterrows():
        original_text = str(row[text_column])
        pre_redacted, pii_found = sanitize_text(original_text)
        expected_result = str(row.get(COLUMNA_RESULTADO_ESPERADO, ""))
        redacted_expected_result, expected_pii_found = sanitize_text(expected_result)
        user_category = row.get(COLUMNA_CATEGORIA_USUARIO, "")
        user_subcategories = row.get(COLUMNA_SUBCATEGORIAS_USUARIO, "")
        user_category = "" if pd.isna(user_category) else str(user_category).strip()
        user_subcategories = "" if pd.isna(user_subcategories) else str(user_subcategories).strip()
        emergency = is_emergency(pre_redacted)

        if emergency:
            record = {
                "categoria_principal": "Seguridad ciudadana",
                "subcategoria": "Alerta de emergencia",
                "organismo_propuesto": "Canal de emergencias (112) - no se procesa como sugerencia ordinaria",
                "nivel_urgencia": "alta",
                "texto_desidentificado": pre_redacted,
                "requiere_revision_privacidad": True,
                "categoria_llm": "Seguridad",
                "subcategoria_llm": "",
                "categoria_usuario": user_category,
                "subcategoria_usuario": normalize_subcategories(user_subcategories, user_category),
                "decision_comparacion": "llm",
                "justificacion_comparacion": "La comunicación se ha escalado por emergencia.",
            }
            estado = "escalado_emergencia"
            accion = "Mostrado canal de emergencia al ciudadano; alerta priorizada para revision humana"
        else:
            record = classify_text(pre_redacted, user_category, user_subcategories)
            record["requiere_revision_privacidad"] = (
                record["requiere_revision_privacidad"] or pii_found or expected_pii_found
            )
            estado = "pendiente_revision"
            accion = ""

        text_hash = duplicate_hash(record["texto_desidentificado"])
        posible_duplicado = text_hash in seen_hashes
        seen_hashes[text_hash] = seen_hashes.get(text_hash, 0) + 1

        records.append({
            "categoria_llm": record["categoria_llm"],
            "subcategoria_llm": record["subcategoria_llm"],
            "categoria_usuario": record["categoria_usuario"],
            "subcategoria_usuario": record["subcategoria_usuario"],
            "decision_comparacion": record["decision_comparacion"],
            "justificacion_comparacion": record["justificacion_comparacion"],
            "organismo_propuesto": record["organismo_propuesto"],
            "organismo_confirmado": "",
            "nivel_urgencia": record["nivel_urgencia"],
            "texto_desidentificado": record["texto_desidentificado"],
            "resultado_esperado_desidentificado": redacted_expected_result,
            "posible_duplicado": posible_duplicado,
            "requiere_revision_privacidad": record["requiere_revision_privacidad"],
        })

    nivel_b = pd.DataFrame(records)

    output_dir = OUTPUT_DIR / datetime.now().strftime("%Y%m%d_%H%M%S")
    output_dir.mkdir(parents=True, exist_ok=True)

    nivel_b.to_csv(output_dir / "nivel_b.csv", index=False, encoding="utf-8")

    ids = df[COLUMNA_ID].tolist() if COLUMNA_ID in df.columns else []
    return {"output_dir": str(output_dir), "total_registros": len(nivel_b), "ids": ids}


def process_query(query: str, text_column: str) -> None:
    """Ejecuta la consulta SQL indicada y procesa todos los registros obtenidos."""
    OUTPUT_DIR.mkdir(exist_ok=True)

    df = fetch_records(query)
    if df.empty:
        print("La consulta no devolvio registros.")
        return

    print(f"Procesando {len(df)} registros obtenidos de la consulta...")
    try:
        resultado = process_records(df, text_column)
    except Exception as exc:
        print(f"Error al procesar los registros: {exc}")
        return

    print(f"  Procesados {resultado['total_registros']} registros. Salida en: {resultado['output_dir']}")

    if resultado["ids"]:
        mark_as_processed(resultado["ids"])
        print(f"  Marcados como procesado=1 en la tabla {TABLA_COMUNICACIONES}")


if __name__ == "__main__":
    process_query(SQL_QUERY, COLUMNA_TEXTO)
