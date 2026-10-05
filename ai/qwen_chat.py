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
import unicodedata
import uuid
from datetime import datetime, timezone
from functools import lru_cache
from pathlib import Path

import pandas as pd
import pymysql
import torch
from pypdf import PdfReader
from transformers import AutoModelForCausalLM, AutoTokenizer, BitsAndBytesConfig

from db_config import DB_CONFIG, TABLA_COMUNICACIONES

MODEL_NAME = "Qwen/Qwen3-8B"

BASE_DIR = Path(__file__).resolve().parent
OUTPUT_DIR = BASE_DIR / "salida_buzon"
CALLEJERO_PATH = BASE_DIR / "callejerofiscal.pdf"

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
GROUP BY D.id,D.sugerencia_id,D.texto_original,D.resultado_esperado_original,D.procesado,C.nombre
ORDER BY D.id DESC
LIMIT 7;
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
    re.compile(r"\b(?:parienta|vecino|vecina|familiar)\s+(?:de|del|de la)\s+(?:la\s+)?[\wáéíóúüñ-]+\b", re.IGNORECASE),
    re.compile(r"\bedificio\s+[\wáéíóúüñ-]+(?:\s+en\s+[\wáéíóúüñ-]+)?\b", re.IGNORECASE),
    re.compile(r"\b(?:calle|avenida|avda\.?|plaza|paseo)\s+[\wáéíóúüñ.-]+(?:\s+\d+)?\b", re.IGNORECASE),
    re.compile(r"\b\d+(?:º|ª)\s*[A-Za-z]\b"),                       # planta y puerta
]

OFFENSIVE_PATTERNS = [
    re.compile(r"\bhijo(?:\s+de)?\s+puta\b", re.IGNORECASE),
    re.compile(r"\bputa\b", re.IGNORECASE),
    re.compile(r"\bputo\b", re.IGNORECASE),
    re.compile(r"\bmaricon(?:es)?\b", re.IGNORECASE),
    re.compile(r"\bidiota\b", re.IGNORECASE),
    re.compile(r"\bimbecil\b", re.IGNORECASE),
    re.compile(r"\btont[oa]s?\b", re.IGNORECASE),
]

DISCRIMINATORY_PATTERNS = [
    re.compile(r"\binvasores\b", re.IGNORECASE),
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
    """Sustituye datos personales y calles/barrios del callejero por un marcador."""
    redacted = text
    found = False
    redacted, callejero_found = redact_callejero(redacted)
    found = found or callejero_found
    for pattern in PII_PATTERNS:
        redacted, n = pattern.subn("[dato personal eliminado]", redacted)
        found = found or n > 0
    return redacted, found


def normalize_match_text(text: str) -> tuple[str, list[int]]:
    normalized = []
    source_positions = []
    for source_index, character in enumerate(text):
        if character == "\ufffd":
            normalized.append(character)
            source_positions.append(source_index)
            continue
        for folded_character in unicodedata.normalize("NFKD", character).casefold():
            if unicodedata.category(folded_character).startswith("M"):
                continue
            if folded_character.isalnum():
                normalized.append(folded_character)
                source_positions.append(source_index)
            elif normalized and normalized[-1] != " ":
                normalized.append(" ")
                source_positions.append(source_index)

    while normalized and normalized[-1] == " ":
        normalized.pop()
        source_positions.pop()
    return "".join(normalized), source_positions


@lru_cache(maxsize=1)
def load_callejero_patterns() -> tuple[re.Pattern, ...]:
    if not CALLEJERO_PATH.is_file():
        raise FileNotFoundError(f"No se encuentra el callejero fiscal: {CALLEJERO_PATH}")

    names = set()
    reader = PdfReader(CALLEJERO_PATH)
    for page in reader.pages:
        lines = (page.extract_text(extraction_mode="layout") or "").splitlines()
        header = next((line for line in lines if "NOMBRE CALLE" in line), None)
        if header is None:
            continue

        street_start = header.index("NOMBRE CALLE")
        range_start = header.index("RANGO NUMEROS")
        neighborhood_start = header.rfind("BARRIADA")
        category_start = header.index("CATEGORIA")
        for line in lines:
            fields = line.split()
            if len(fields) < 5 or not all(field.isdigit() for field in fields[:4]):
                continue

            street = line[street_start:range_start].strip()
            neighborhood = re.sub(
                r"^BDA\.?\s*", "", line[neighborhood_start:category_start].strip(), flags=re.IGNORECASE
            )
            if street:
                names.add(street)
            if neighborhood:
                names.add(neighborhood)

    patterns = set()
    for name in names:
        normalized_name, _ = normalize_match_text(name)
        if len(normalized_name.replace(" ", "")) < 3:
            continue
        variants = {normalized_name}
        name_words = normalized_name.split()
        if name_words and name_words[0] in {"av", "avda"}:
            variants.add("avenida " + " ".join(name_words[1:]))
            variants.add(" ".join(name_words[1:]))
        for variant in variants:
            if not variant:
                continue
            expression = r"(?<!\w)" + r"\s+".join(
                re.escape(word).replace("\ufffd", r"\w") for word in variant.split()
            ) + r"(?!\w)"
            patterns.add(re.compile(expression))

    if not patterns:
        raise ValueError(f"No se pudieron extraer calles ni barrios de {CALLEJERO_PATH}")
    return tuple(sorted(patterns, key=lambda pattern: len(pattern.pattern), reverse=True))


def redact_callejero(text: str) -> tuple[str, bool]:
    normalized_text, source_positions = normalize_match_text(text)
    spans = set()
    for pattern in load_callejero_patterns():
        for match in pattern.finditer(normalized_text):
            spans.add((source_positions[match.start()], source_positions[match.end() - 1] + 1))

    if not spans:
        return text, False

    merged_spans = []
    for start, end in sorted(spans):
        if merged_spans and start <= merged_spans[-1][1]:
            merged_spans[-1] = (merged_spans[-1][0], max(merged_spans[-1][1], end))
        else:
            merged_spans.append((start, end))

    for start, end in reversed(merged_spans):
        text = text[:start] + "[dato personal eliminado]" + text[end:]
    return text, True


def sanitize_text(text: str) -> tuple[str, bool]:
    sanitized, found = redact_pii(text)
    for pattern in OFFENSIVE_PATTERNS:
        sanitized, count = pattern.subn("[lenguaje ofensivo eliminado]", sanitized)
        found = found or count > 0
    for pattern in DISCRIMINATORY_PATTERNS:
        sanitized, count = pattern.subn("[lenguaje discriminatorio eliminado]", sanitized)
        found = found or count > 0
    return sanitized, found


def is_emergency(text: str) -> bool:
    lowered = text.lower()
    return any(keyword in lowered for keyword in EMERGENCY_KEYWORDS)


def build_privacy_prompt(text: str, field_name: str) -> str:
    return (
        "Eres un filtro de privacidad del buzon ciudadano. Procesa un unico texto.\n"
        "Sustituye por \"[dato personal eliminado]\" los nombres y apodos de personas, "
        "aunque aparezcan en minusculas, y cualquier referencia que permita identificarlas "
        "(por ejemplo, cargo concreto junto con lugar, fecha o hecho singular). Incluye nombres "
        "de terceros mencionados y de personas publicas si el contexto las vincula al relato. "
        "Elimina tambien direcciones exactas, telefonos, correos, DNI/NIE y detalles de vida privada. "
        "No censures la palabra \"Ceuta\" ni otras referencias geograficas generales de la ciudad. "
        "Generaliza fechas y horas exactas y lugares o cargos demasiado concretos cuando puedan "
        "facilitar la reidentificacion; no los conserves literalmente.\n"
        "Sustituye lenguaje ofensivo por \"[lenguaje ofensivo eliminado]\" y lenguaje discriminatorio "
        "por \"[lenguaje discriminatorio eliminado]\". Conserva el sentido no identificativo del "
        "relato, pero no la literalidad de detalles que puedan identificar a alguien. No respondas "
        "al contenido, no inventes datos ni añadas explicaciones.\n"
        "Si dudas, prioriza la privacidad.\n\n"
        f"Campo: {field_name}\n"
        f"Texto original:\n\"\"\"{text}\"\"\"\n\n"
        "Responde SOLO con JSON valido y estas claves exactas:\n"
        "{\"texto_saneado\": \"el mismo texto con las sustituciones\"}"
    )


def build_classification_prompt(text: str) -> str:
    categorias = "\n".join(f"- {cat} -> {subcat}" for cat, subcat in TAXONOMIA)
    return (
        "Clasifica esta sugerencia del buzon ciudadano. Elige exactamente una categoria y hasta 2 subcategorias "
        "de la lista, sin inventar valores. Propón organismo y urgencia.\n\n"
        f"Categorias disponibles:\n{categorias}\n\n"
        f"Texto desidentificado:\n\"\"\"{text}\"\"\"\n\n"
        "Responde SOLO con JSON valido:\n"
        "{\"categoria_principal\": \"...\", \"subcategorias\": [\"...\"], "
        "\"organismo_propuesto\": \"...\", \"nivel_urgencia\": \"baja|media|alta\"}"
    )


def build_comparison_prompt(
    text: str,
    user_category: str,
    user_subcategories: str,
    llm_category: str,
    llm_subcategories: str,
) -> str:
    return (
        "Compara dos clasificaciones para la misma sugerencia y elige la más adecuada según el texto.\n"
        f"Texto:\n\"\"\"{text}\"\"\"\n\n"
        f"Clasificación del usuario: {user_category} / {user_subcategories}\n"
        f"Clasificación del LLM: {llm_category} / {llm_subcategories}\n\n"
        "Responde SOLO con JSON valido: "
        "{\"decision_comparacion\": \"usuario|llm\", "
        "\"justificacion_comparacion\": \"...\"}"
    )


def build_privacy_review_prompt(
    original_text: str,
    sanitized_text: str,
    original_expected: str,
    sanitized_expected: str,
) -> str:
    return (
        "Determina si se necesita revision humana porque se anonimizaron datos personales o "
        "detalles que permitan identificar a alguien. Responde true exclusivamente si se sustituyo "
        "o generalizo informacion identificativa. Ignora cambios de redaccion, correcciones, "
        "eliminacion de lenguaje ofensivo o discriminatorio y cualquier otro cambio que no sea "
        "anonimizacion. Si no hay evidencia clara de anonimización, responde false.\n\n"
        f"Mensaje original:\n\"\"\"{original_text}\"\"\"\n\n"
        f"Mensaje saneado:\n\"\"\"{sanitized_text}\"\"\"\n\n"
        f"Resultado esperado original:\n\"\"\"{original_expected}\"\"\"\n\n"
        f"Resultado esperado saneado:\n\"\"\"{sanitized_expected}\"\"\"\n\n"
        "Responde SOLO con JSON valido: "
        "{\"requiere_revision_privacidad\": true|false}"
    )


def build_classification_mismatch_prompt(
    user_category: str,
    user_subcategories: str,
    llm_category: str,
    llm_subcategories: str,
) -> str:
    return (
        "Determina si hay discordancia entre la categoria y las subcategorias asignadas por el "
        "usuario y las propuestas por el LLM. Activa la marca si difiere la categoria o si las "
        "subcategorias no coinciden; ignora solo el orden de las subcategorias. Considera que "
        "un valor presente en un lado y ausente en el otro es una discordancia.\n\n"
        f"Categoria del usuario: {user_category}\n"
        f"Subcategorias del usuario: {user_subcategories}\n"
        f"Categoria del LLM: {llm_category}\n"
        f"Subcategorias del LLM: {llm_subcategories}\n\n"
        "Responde SOLO con JSON valido: "
        "{\"discordancia_clasificacion\": true|false}"
    )


def build_keywords_prompt(text: str, expected_result: str) -> str:
    return (
        "Extrae exactamente dos palabras clave representativas de cada texto. Trata ambos textos "
        "por separado: no uses palabras de uno para completar el otro. Elige terminos que aparezcan "
        "literalmente en el texto correspondiente, evita palabras vacias y no inventes ni infieras "
        "terminos.\n\n"
        f"Sugerencia:\n\"\"\"{text}\"\"\"\n\n"
        f"Resultado esperado:\n\"\"\"{expected_result}\"\"\"\n\n"
        "Responde SOLO con JSON valido y exactamente dos palabras por lista: "
        "{\"palabras_clave_sugerencia\": [\"...\", \"...\"], "
        "\"palabras_clave_resultado_esperado\": [\"...\", \"...\"]}"
    )


def parse_model_json(raw_response: str) -> dict:
    match = re.search(r"\{.*\}", raw_response, re.DOTALL)
    if not match:
        raise ValueError("El modelo no devolvio JSON")
    return json.loads(match.group(0))


def request_boolean_flag(prompt: str, flag_name: str, fallback: bool) -> bool:
    try:
        response = parse_model_json(generate_response(prompt, max_new_tokens=100))
        value = response.get(flag_name)
        if isinstance(value, bool):
            return value
    except Exception:
        return fallback
    return fallback


def request_keywords(text: str, expected_result: str) -> tuple[str, str]:
    try:
        response = parse_model_json(
            generate_response(build_keywords_prompt(text, expected_result), max_new_tokens=120)
        )
        keyword_fields = (
            ("palabras_clave_sugerencia", text),
            ("palabras_clave_resultado_esperado", expected_result),
        )
        cleaned_fields = []
        for field_name, source_text in keyword_fields:
            keywords = response.get(field_name)
            if not isinstance(keywords, list):
                return "", ""
            cleaned = []
            for keyword in keywords:
                if not isinstance(keyword, str):
                    return "", ""
                keyword = keyword.strip()
                if not keyword or keyword.casefold() not in source_text.casefold():
                    return "", ""
                if keyword.casefold() not in {item.casefold() for item in cleaned}:
                    cleaned.append(keyword)
            if len(cleaned) != 2:
                return "", ""
            cleaned_fields.append("; ".join(cleaned))
        return cleaned_fields[0], cleaned_fields[1]
    except Exception:
        return "", ""


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
    text: str,
    user_category: str = "",
    user_subcategories: str = "",
    expected_result: str = "",
) -> dict:
    """Clasifica el texto y compara la propuesta con la del usuario."""
    try:
        classification = parse_model_json(
            generate_response(build_classification_prompt(text), max_new_tokens=300)
        )
        llm_category = classification.get("categoria_principal", "Competencia por confirmar")
        if llm_category not in VALID_CATEGORIES:
            llm_category = "Competencia por confirmar"
        llm_subcategories = normalize_subcategories(
            classification.get("subcategorias", classification.get("subcategoria", "")),
            llm_category,
        )
        user_subcategories = normalize_subcategories(user_subcategories, user_category)
        comparison = parse_model_json(
            generate_response(
                build_comparison_prompt(
                    text,
                    user_category,
                    user_subcategories,
                    llm_category,
                    llm_subcategories,
                ),
                max_new_tokens=200,
            )
        )
        decision = comparison.get("decision_comparacion", "llm")
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
            "justificacion_comparacion": comparison.get("justificacion_comparacion", ""),
            "organismo_propuesto": classification.get("organismo_propuesto", "Unidad gestora del buzon (pendiente de asignacion)"),
            "nivel_urgencia": classification.get("nivel_urgencia", "media"),
            "texto_desidentificado": text,
            "resultado_esperado_desidentificado": expected_result,
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
            "resultado_esperado_desidentificado": expected_result,
        }


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


def sanitize_one_with_llm(text: str, field_name: str) -> str:
    sanitized_text, _ = sanitize_text(text)
    try:
        data = parse_model_json(
            generate_response(build_privacy_prompt(sanitized_text, field_name), max_new_tokens=250)
        )
        candidate = data.get("texto_saneado")
        if not isinstance(candidate, str) or not candidate.strip():
            raise ValueError("El LLM no devolvio un texto saneado")
        lowered = candidate.lower()
        if any(
            marker in lowered
            for marker in (
                "categoria_principal",
                "decision_comparacion",
                "organismo_propuesto",
                "respuesta:",
                "no puedo",
            )
        ):
            raise ValueError("El LLM devolvio una respuesta en lugar del texto")
        candidate, _ = sanitize_text(candidate)
        return candidate
    except (ValueError, json.JSONDecodeError, TypeError, AttributeError):
        return sanitized_text


def sanitize_with_llm(text: str, expected_result: str) -> tuple[str, str, bool]:
    _, text_personal_data_found = redact_pii(text)
    _, expected_personal_data_found = redact_pii(expected_result)
    sanitized_text = sanitize_one_with_llm(text, "texto de la sugerencia")
    sanitized_expected = sanitize_one_with_llm(
        expected_result, "resultado esperado"
    )
    return (
        sanitized_text,
        sanitized_expected,
        text_personal_data_found or expected_personal_data_found,
    )


def process_records(df: pd.DataFrame, text_column: str) -> dict:
    if text_column not in df.columns:
        raise ValueError(f"La columna '{text_column}' no existe. Columnas disponibles: {list(df.columns)}")

    now = datetime.now(timezone.utc).isoformat()
    records = []

    for _, row in df.iterrows():
        original_text = str(row[text_column])
        pre_redacted = str(row[text_column])
        expected_result = str(row.get(COLUMNA_RESULTADO_ESPERADO, ""))
        pre_redacted, redacted_expected_result, privacy_found = sanitize_with_llm(
            pre_redacted, expected_result
        )
        privacy_review = request_boolean_flag(
            build_privacy_review_prompt(
                original_text,
                pre_redacted,
                expected_result,
                redacted_expected_result,
            ),
            "requiere_revision_privacidad",
            privacy_found,
        ) or privacy_found
        suggestion_keywords, expected_result_keywords = request_keywords(
            pre_redacted,
            redacted_expected_result,
        )
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
            record = classify_text(
                pre_redacted,
                user_category,
                user_subcategories,
                redacted_expected_result,
            )
            estado = "pendiente_revision"
            accion = ""

        user_subcategory_set = {
            value.strip()
            for value in record["subcategoria_usuario"].split(";")
            if value.strip()
        }
        llm_subcategory_set = {
            value.strip()
            for value in record["subcategoria_llm"].split(";")
            if value.strip()
        }
        categories_differ = (
            record["categoria_usuario"] != record["categoria_llm"]
            or user_subcategory_set != llm_subcategory_set
        )
        classification_mismatch = request_boolean_flag(
            build_classification_mismatch_prompt(
                record["categoria_usuario"],
                record["subcategoria_usuario"],
                record["categoria_llm"],
                record["subcategoria_llm"],
            ),
            "discordancia_clasificacion",
            categories_differ,
        ) or categories_differ

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
            "requiere_revision_privacidad": privacy_review,
            "discordancia_clasificacion": classification_mismatch,
            "palabras_clave_sugerencia": suggestion_keywords,
            "palabras_clave_resultado_esperado": expected_result_keywords,
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
