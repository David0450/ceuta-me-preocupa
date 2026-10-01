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
COLUMNA_ESTADO = "procesado"

SQL_QUERY = f"""
SELECT D.id,D.sugerencia_id,D.texto_original,D.resultado_esperado_original,
D.procesado,S.id,C.nombre,SU.nombre FROM datos_originales AS D 
INNER JOIN sugerencias AS S ON D.sugerencia_id=S.id 
INNER JOIN categorias AS C ON S.categoria_id=C.id 
INNER JOIN sugerencia_subcategoria AS SB ON S.id=SB.sugerencia_id 
INNER JOIN subcategorias AS SU ON SB.subcategoria_id=SU.id WHERE D.procesado=0;
"""

# Taxonomia reconstruida a partir de la seccion 2 del protocolo (matriz de derivacion).
# organismo_propuesto es SOLO una propuesta: la unidad gestora debe confirmarla (seccion 2).
TAXONOMIA = [
    ("Medio Ambiente, Servicios Urbanos y Vivienda",
     "Ciudad Autonoma de Ceuta: Consejeria de Medio Ambiente, Servicios Urbanos y Vivienda"),
    ("Transporte, trafico y accesibilidad",
     "Ciudad Autonoma de Ceuta / Policia Local o Delegacion del Gobierno cuando afecte a seguridad"),
    ("Educacion no universitaria",
     "Direccion Provincial de Educacion (Ministerio) / Ciudad Autonoma: Direccion General de Educacion"),
    ("Educacion universitaria",
     "Ministerio competente en universidades o Ciudad Autonoma segun el caso"),
    ("Comercio, turismo y empleo",
     "Ciudad Autonoma de Ceuta: Consejeria/Direccion General de Comercio, Turismo y Empleo"),
    ("Sanidad",
     "Ciudad Autonoma: Direccion General de Sanidad / INGESA para emergencias sanitarias"),
    ("Seguridad ciudadana",
     "Delegacion del Gobierno y Fuerzas y Cuerpos de Seguridad del Estado / Policia Local"),
    ("Asistencia social e igualdad",
     "Direccion General de Igualdad y Lucha contra la Violencia de Genero / Servicios Sociales"),
    ("Infraestructuras portuarias y estatales",
     "Servicios Urbanos, autoridad portuaria u organismo estatal competente"),
    ("Competencia por confirmar",
     "Unidad gestora del buzon (pendiente de asignacion)"),
]

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


def is_emergency(text: str) -> bool:
    lowered = text.lower()
    return any(keyword in lowered for keyword in EMERGENCY_KEYWORDS)


def build_classification_prompt(text: str) -> str:
    categorias = "\n".join(f"- {cat} -> {org}" for cat, org in TAXONOMIA)
    return (
        "Eres el clasificador del buzon virtual ciudadano de Ceuta. Sigue el protocolo:\n"
        "1. Elimina cualquier dato personal restante (nombres, direcciones exactas, telefonos, "
        "correos, DNI/NIE),incluyendo menciones a su vida privada, y sustituyelo por \"[dato personal eliminado]\".\n"
        "2. Elige una categoria_principal de la lista y, si aplica, hasta dos subcategorias.\n"
        "3. Propon un organismo_propuesto (no es resolucion firme, solo propuesta).\n"
        "4. Si la informacion es insuficiente o afecta a varios organismos, usa "
        "\"Competencia por confirmar\".\n"
        "5. Evalua nivel_urgencia como \"baja\", \"media\" o \"alta\" segun riesgo y reversibilidad.\n"
        "No clasifiques por nacionalidad, origen, ideologia o religion.\n\n"
        f"Categorias disponibles:\n{categorias}\n\n"
        f"Texto de la comunicacion:\n\"\"\"{text}\"\"\"\n\n"
        "Responde SOLO con un JSON valido, sin explicaciones, con estas claves exactas:\n"
        "{\"categoria_principal\": \"...\", \"subcategoria\": \"...\", "
        "\"organismo_propuesto\": \"...\", \"nivel_urgencia\": \"baja|media|alta\", "
        "\"texto_desidentificado\": \"...\", \"requiere_revision_privacidad\": true|false}"
    )


def parse_model_json(raw_response: str) -> dict:
    match = re.search(r"\{.*\}", raw_response, re.DOTALL)
    if not match:
        raise ValueError("El modelo no devolvio JSON")
    return json.loads(match.group(0))


def classify_text(text: str) -> dict:
    """Aplica el paso de clasificacion doble (seccion 4.2.4) con fallback seguro."""
    prompt = build_classification_prompt(text)
    try:
        raw = generate_response(prompt, max_new_tokens=400)
        data = parse_model_json(raw)
        return {
            "categoria_principal": data.get("categoria_principal", "Competencia por confirmar"),
            "subcategoria": data.get("subcategoria", ""),
            "organismo_propuesto": data.get("organismo_propuesto", "Unidad gestora del buzon (pendiente de asignacion)"),
            "nivel_urgencia": data.get("nivel_urgencia", "media"),
            "texto_desidentificado": data.get("texto_desidentificado", text),
            "requiere_revision_privacidad": bool(data.get("requiere_revision_privacidad", True)),
        }
    except (ValueError, json.JSONDecodeError):
        # Fallback conservador: se marca para revision humana en vez de perder el registro.
        return {
            "categoria_principal": "Competencia por confirmar",
            "subcategoria": "",
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
        pre_redacted, pii_found = redact_pii(original_text)
        emergency = is_emergency(pre_redacted)

        if emergency:
            record = {
                "categoria_principal": "Seguridad ciudadana",
                "subcategoria": "Alerta de emergencia",
                "organismo_propuesto": "Canal de emergencias (112) - no se procesa como sugerencia ordinaria",
                "nivel_urgencia": "alta",
                "texto_desidentificado": pre_redacted,
                "requiere_revision_privacidad": True,
            }
            estado = "escalado_emergencia"
            accion = "Mostrado canal de emergencia al ciudadano; alerta priorizada para revision humana"
        else:
            record = classify_text(pre_redacted)
            record["requiere_revision_privacidad"] = record["requiere_revision_privacidad"] or pii_found
            estado = "pendiente_revision"
            accion = ""

        text_hash = duplicate_hash(record["texto_desidentificado"])
        posible_duplicado = text_hash in seen_hashes
        seen_hashes[text_hash] = seen_hashes.get(text_hash, 0) + 1

        records.append({
            "id_aleatorio": str(uuid.uuid4()),
            "fecha_recepcion": row.get("fecha_recepcion", now),
            "categoria_principal": record["categoria_principal"],
            "subcategoria": record["subcategoria"],
            "organismo_propuesto": record["organismo_propuesto"],
            "organismo_confirmado": "",
            "nivel_urgencia": record["nivel_urgencia"],
            "estado": estado,
            "texto_desidentificado": record["texto_desidentificado"],
            "posible_duplicado": posible_duplicado,
            "requiere_revision_privacidad": record["requiere_revision_privacidad"],
            "accion_adoptada": accion,
            "fecha_ultima_actualizacion": now,
        })

    nivel_b = pd.DataFrame(records)

    output_dir = OUTPUT_DIR / datetime.now().strftime("%Y%m%d_%H%M%S")
    output_dir.mkdir(parents=True, exist_ok=True)

    nivel_b.to_csv(output_dir / "nivel_b.csv", index=False, encoding="utf-8")

    for categoria, group in nivel_b.groupby("categoria_principal"):
        safe_name = re.sub(r"[^\w\-]+", "_", categoria.lower())
        group.to_csv(output_dir / f"categoria_{safe_name}.csv", index=False, encoding="utf-8")

    cuadro_maestro = (
        nivel_b.groupby(["categoria_principal", "organismo_propuesto", "estado"])
        .size()
        .reset_index(name="total")
    )
    cuadro_maestro.to_csv(output_dir / "cuadro_maestro.csv", index=False, encoding="utf-8")

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
