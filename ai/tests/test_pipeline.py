import json

import pytest

import qwen_chat as pipeline


class FakeRows:
    """Sustituto minimo del DataFrame que recibe process_records."""

    def __init__(self, rows):
        self._rows = rows
        self.columns = list(rows[0].keys())

    def iterrows(self):
        return enumerate(self._rows)


@pytest.mark.parametrize(
    "value, expected",
    [
        ("ordinaria", "ordinaria"),
        ("Prioritaria", "prioritaria"),
        (" URGENTE ", "urgente"),
        ("alta", "ordinaria"),
        ("media", "ordinaria"),
        (None, "ordinaria"),
    ],
)
def test_urgency_is_always_a_value_the_web_accepts(value, expected):
    assert pipeline.normalize_urgency(value) == expected


def test_redact_pii_removes_direct_identifiers():
    text = (
        "Soy Ana, mi DNI es 12345678Z, mi NIE X1234567L, "
        "escribidme a ana.garcia@example.com o al 612345678."
    )
    redacted, found = pipeline.redact_pii(text)

    assert found
    for identifier in ("12345678Z", "X1234567L", "ana.garcia@example.com", "612345678"):
        assert identifier not in redacted
    assert redacted.count("[dato personal eliminado]") == 4


def test_redact_pii_keeps_street_addresses_to_locate_the_problem():
    text = "Hay un socavon en la calle Real 25 desde hace meses."
    redacted, found = pipeline.redact_pii(text)

    assert "calle Real 25" in redacted
    assert found


def test_sanitize_text_masks_offensive_language():
    sanitized, found = pipeline.sanitize_text("El concejal es un idiota.")

    assert "idiota" not in sanitized
    assert "[lenguaje ofensivo eliminado]" in sanitized
    assert found


@pytest.mark.parametrize(
    "text, expected",
    [
        ("Hay un incendio en el monte", True),
        ("Riesgo vital en el cruce", True),
        ("Faltan papeleras en el paseo", False),
    ],
)
def test_emergency_keywords(text, expected):
    assert pipeline.is_emergency(text) is expected


def test_classify_text_keeps_only_values_from_the_taxonomy(monkeypatch):
    answer = {
        "categoria_principal": "Movilidad",
        "subcategorias": ["Aparcamiento", "Inventada", "Tráfico", "Accesibilidad"],
        "nivel_urgencia": "prioritaria",
    }
    monkeypatch.setattr(pipeline, "generate_response", lambda *a, **k: json.dumps(answer))

    result = pipeline.classify_text("No hay sitio para aparcar en el centro.")

    assert result == {
        "categoria_llm": "Movilidad",
        "subcategoria_llm": "Aparcamiento; Tráfico",
        "nivel_urgencia": "prioritaria",
    }


def test_classify_text_falls_back_when_the_model_invents_values(monkeypatch):
    answer = {"categoria_principal": "Deportes", "subcategorias": [], "nivel_urgencia": "alta"}
    monkeypatch.setattr(pipeline, "generate_response", lambda *a, **k: json.dumps(answer))

    result = pipeline.classify_text("Queremos una piscina nueva.")

    assert result["categoria_llm"] == "Competencia por confirmar"
    assert result["subcategoria_llm"] == ""
    assert result["nivel_urgencia"] == "ordinaria"


def test_classify_text_falls_back_when_the_model_does_not_return_json(monkeypatch):
    monkeypatch.setattr(pipeline, "generate_response", lambda *a, **k: "No puedo ayudar.")

    result = pipeline.classify_text("Texto cualquiera.")

    assert result["categoria_llm"] == "Competencia por confirmar"
    assert result["nivel_urgencia"] == "ordinaria"


def test_process_records_marks_emergencies_as_urgent(monkeypatch):
    saved = []
    monkeypatch.setattr(pipeline, "sanitize_with_llm", lambda text, expected: (text, expected))
    monkeypatch.setattr(pipeline, "save_results", saved.extend)
    monkeypatch.setattr(
        pipeline,
        "classify_text",
        lambda text: pytest.fail("Una emergencia no debe pasar por el clasificador"),
    )
    rows = FakeRows([
        {
            "id": 7,
            "sugerencia_id": 3,
            "texto_original": "Hay un incendio en el solar de al lado.",
            "resultado_esperado_original": None,
            "procesado": 0,
        }
    ])

    result = pipeline.process_records(rows, "texto_original")

    assert result == {"total_registros": 1}
    assert saved[0]["nivel_urgencia"] == "urgente"
    assert saved[0]["sugerencia_id"] == 3
    assert saved[0]["id_origen"] == 7


def test_database_identifiers_are_valid():
    pipeline.validate_result_table_config()
