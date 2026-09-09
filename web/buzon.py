#!/usr/bin/env python3
"""Procesa sugerencias del buzón virtual de educación.

El flujo sigue la especificación del documento adjunto:
- lee un archivo Excel/CSV con sugerencias,
- anonimiza información personal,
- clasifica cada mensaje en Urbanismo, Educación, Economía o Sanidad,
- y crea un archivo Excel por categoría.
"""

from __future__ import annotations

import argparse
import os
import re
from pathlib import Path
from typing import Any, Iterable, List

import pandas as pd

CATEGORIES = ["Urbanismo", "Educación", "Economía", "Sanidad"]

CATEGORY_KEYWORDS = {
    "Urbanismo": [
        "calle", "acceso", "movilidad", "parque", "alumbrado", "pavimento",
        "transporte", "barrio", "urbanismo", "semaforo", "vial", "instalaciones",
        "amenaza", "riscos", "escalera", "pista", "autobús", "bus", "zona",
    ],
    "Educación": [
        "aula", "docente", "clase", "alumno", "profesor", "horario", "educación",
        "escolar", "curriculum", "formación", "aprendizaje", "actividad", "tutoría",
        "biblioteca", "curso", "grupo", "centro", "personal docente",
    ],
    "Economía": [
        "presupuesto", "gasto", "economía", "precio", "subvención", "material",
        "comedor", "matrícula", "coste", "factura", "transporte", "economico",
        "asignación", "fondos", "ingresos", "impuestos",
    ],
    "Sanidad": [
        "salud", "médico", "sanidad", "clínica", "hospital", "síntoma", "enfermedad",
        "asistencia", "psicológico", "psicología", "medicación", "atención sanitaria",
        "dolor", "vacuna", "urgencia", "riesgo sanitario",
    ],
}

ADDRESS_RE = re.compile(
    r"(?i)\b(?:calle|avenida|avenida|plaza|paseo|carretera|camino|vía|via|barrio|urbanización|urbani\w+|domicilio|vivienda|piso|portal)\s+[^\n,.;]{0,80}",
)
EMAIL_RE = re.compile(r"[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}", flags=re.I)
PHONE_RE = re.compile(r"(?:(?:\+\d{1,3})|\d{2,4})[\s.-]?(?:\d{3}[\s.-]?){2,3}\d{2,4}")
DNI_RE = re.compile(r"\b\d{7,8}[A-ZHJKLMNPQRSTVWXYZ]\b", flags=re.I)
NAME_PATTERNS = [
    re.compile(r"(?i)\b(?:mi nombre es|me llamo|soy|se llama|se llama\s+|nombre\s*[:\-]?\s*)[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+){0,3}"),
    re.compile(r"(?i)\b(?:apellidos?|apellido|dni|documento|teléfono|telefono|correo|email|vivienda|domicilio)\s*[:\-]?\s*[^\n,.;]{0,50}"),
]


def normalize_text(value: Any) -> str:
    if value is None or pd.isna(value):
        return ""
    return str(value).strip()


def anonymize_text(text: str) -> str:
    """Elimina datos personales obvios y los reemplaza por marcadores neutros."""
    cleaned = text
    cleaned = EMAIL_RE.sub("[EMAIL]", cleaned)
    cleaned = PHONE_RE.sub("[TELÉFONO]", cleaned)
    cleaned = DNI_RE.sub("[DNI]", cleaned)
    cleaned = ADDRESS_RE.sub("[DIRECCIÓN]", cleaned)
    for pattern in NAME_PATTERNS:
        cleaned = pattern.sub("[INFORMACIÓN PERSONAL]", cleaned)

    # Sustituciones extra para frases típicas de identificación personal.
    cleaned = re.sub(r"(?i)\b(?:mi\s+)?nombre\s+es\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+){0,3}\b", "[NOMBRE]", cleaned)
    cleaned = re.sub(r"(?i)\b(?:vivo\s+en|resido\s+en|mi\s+vivienda\s+es|domicilio\s*[:\-]?\s*)[^\n,.;]{0,80}", "[DIRECCIÓN]", cleaned)
    return cleaned.strip()


def classify_with_heuristics(text: str) -> str:
    lower = normalize_text(text).lower()
    scores = {category: 0 for category in CATEGORIES}
    for category, keywords in CATEGORY_KEYWORDS.items():
        for keyword in keywords:
            if keyword in lower:
                scores[category] += 1

    best_category = max(scores, key=scores.get) if any(scores.values()) else "Educación"
    if scores[best_category] == 0:
        return "Educación"
    return best_category


def find_text_column(columns: Iterable[str]) -> str:
    normalized = {str(col).lower(): col for col in columns}
    for candidate in [
        "mensaje",
        "sugerencia",
        "descripcion",
        "comentario",
        "texto",
        "propuesta",
        "detalle",
        "incidencia",
        "observacion",
        "observación",
        "reclamacion",
        "queja",
        "texto_sugerencia",
    ]:
        if candidate in normalized:
            return normalized[candidate]
    for col in columns:
        lower = str(col).lower()
        if "mensaje" in lower or "sugerencia" in lower or "descripcion" in lower or "comentario" in lower:
            return str(col)
    return list(columns)[0]


def get_category_column(columns: Iterable[str]) -> str | None:
    normalized = {str(col).lower(): col for col in columns}
    for candidate in ["categoria", "categoría", "clasificacion", "clasificación", "tipo", "tema"]:
        if candidate in normalized:
            return normalized[candidate]
    return None


def load_dataframe(path: Path) -> pd.DataFrame:
    if path.suffix.lower() == ".csv":
        return pd.read_csv(path)
    return pd.read_excel(path)


def generate_outputs(
    input_path: Path,
    output_dir: Path,
) -> dict[str, int]:
    df = load_dataframe(input_path)
    if df.empty:
        raise ValueError(f"El archivo {input_path} no contiene registros.")

    text_column = find_text_column(df.columns)
    category_column = get_category_column(df.columns)
    output_dir.mkdir(parents=True, exist_ok=True)

    processed_rows: List[dict[str, Any]] = []
    for index, row in df.iterrows():
        raw_text = normalize_text(row.get(text_column, ""))
        if not raw_text:
            continue

        anonymized = anonymize_text(raw_text)
        if category_column:
            original_category = normalize_text(row.get(category_column, ""))
            if original_category:
                normalized_category = next((cat for cat in CATEGORIES if cat.lower() == original_category.lower()), None)
                category = normalized_category or classify_with_heuristics(anonymized)
            else:
                category = classify_with_heuristics(anonymized)
        else:
            category = classify_with_heuristics(anonymized)

        processed_rows.append(
            {
                "id": index + 1,
                "categoria": category,
                "mensaje_original": raw_text,
                "mensaje_anonimizado": anonymized,
            }
        )

    if not processed_rows:
        raise ValueError("No se han encontrado mensajes válidos para procesar.")

    results = pd.DataFrame(processed_rows)
    counts = {category: 0 for category in CATEGORIES}
    for category in CATEGORIES:
        subset = results[results["categoria"] == category]
        counts[category] = len(subset)
        if not subset.empty:
            subset = subset[["id", "categoria", "mensaje_anonimizado"]].copy()
            subset.columns = ["id", "categoria", "mensaje"]
            subset.to_excel(output_dir / f"{category}.xlsx", index=False)

    summary_path = output_dir / "resumen.xlsx"
    summary_df = pd.DataFrame(
        [{"categoria": category, "num_registros": counts[category]} for category in CATEGORIES]
    )
    summary_df.to_excel(summary_path, index=False)

    return counts


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Procesa sugerencias del buzón virtual de educación.")
    parser.add_argument("input_file", type=Path, help="Ruta al archivo Excel/CSV de entrada.")
    parser.add_argument(
        "--output-dir",
        type=Path,
        default=Path("salidas"),
        help="Directorio donde se guardarán los Excel por categoría.",
    )
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    if not args.input_file.exists():
        raise FileNotFoundError(f"No existe el archivo de entrada: {args.input_file}")

    counts = generate_outputs(
        input_path=args.input_file,
        output_dir=args.output_dir,
    )

    print("Procesamiento completado.")
    for category, total in counts.items():
        print(f"- {category}: {total}")
    print(f"Salida: {args.output_dir}")


if __name__ == "__main__":
    main()
