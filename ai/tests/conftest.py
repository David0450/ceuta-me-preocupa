"""Prepara la importacion de qwen_chat.py sin GPU, sin modelo y sin base de datos.

qwen_chat.py carga Qwen3-8B y su configuracion de BD al importarse. Para probar
la logica del pipeline (desidentificacion, urgencia, clasificacion) se
sustituyen esas dependencias por modulos falsos antes de importarlo.
"""

import sys
import types
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))


def _stub(name: str, **attrs) -> types.ModuleType:
    module = types.ModuleType(name)
    for key, value in attrs.items():
        setattr(module, key, value)
    sys.modules[name] = module
    return module


class _Pretrained:
    @classmethod
    def from_pretrained(cls, *args, **kwargs):
        return cls()


_stub("torch", bfloat16="bfloat16")
_stub(
    "transformers",
    AutoModelForCausalLM=_Pretrained,
    AutoTokenizer=_Pretrained,
    BitsAndBytesConfig=lambda **kwargs: kwargs,
)
_stub("pymysql", connect=None, cursors=types.SimpleNamespace(DictCursor=object))
_stub("pandas", isna=lambda value: value is None, DataFrame=dict)
_stub("db_config", DB_CONFIG={}, TABLA_COMUNICACIONES="datos_originales")
