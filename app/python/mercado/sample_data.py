import json
from pathlib import Path


def _sample_data_path() -> Path:
    return Path(__file__).resolve().parents[2] / "data" / "mercado_seed.json"


def load_items() -> list[dict]:
    with _sample_data_path().open("r", encoding="utf-8") as handle:
        return json.load(handle)
