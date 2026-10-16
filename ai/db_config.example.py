"""Plantilla de db_config.py. Copia este archivo como db_config.py y rellena tus datos reales.
db_config.py esta en .gitignore y no se sube al repositorio.
"""

import os

DB_CONFIG = {
    "host": os.environ.get("BUZON_DB_HOST", "TU_HOST"),
    "port": int(os.environ.get("BUZON_DB_PORT", "3306")),
    "user": os.environ.get("BUZON_DB_USER", "TU_USUARIO"),
    "password": os.environ.get("BUZON_DB_PASSWORD", "TU_PASSWORD"),
    "database": os.environ.get("BUZON_DB_NAME", "TU_BASE_DE_DATOS"),
}

TABLA_COMUNICACIONES = os.environ.get("BUZON_DB_TABLE", "datos_originales")
