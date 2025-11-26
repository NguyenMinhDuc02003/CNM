from __future__ import annotations

import json
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, List, Optional, Tuple

import numpy as np

try:
    from .utils import ensure_dir  # type: ignore
except ImportError:  # pragma: no cover
    import sys

    sys.path.append(str(Path(__file__).resolve().parent))
    from utils import ensure_dir  # type: ignore


def _normalize(vec: np.ndarray) -> np.ndarray:
    norm = np.linalg.norm(vec)
    if norm < 1e-12:
        return vec
    return vec / norm


@dataclass
class EmployeeEmbedding:
    employee_id: str
    employee_name: Optional[str]
    embeddings: np.ndarray  # shape (n, d)

    @property
    def avg(self) -> np.ndarray:
        if self.embeddings.size == 0:
            raise ValueError("No embeddings recorded")
        return np.mean(self.embeddings, axis=0)


class EmbeddingStore:
    def __init__(self, storage_path: Path):
        self.storage_path = storage_path
        ensure_dir(storage_path.parent)
        self._records: Dict[str, EmployeeEmbedding] = {}
        self._load()

    def _load(self) -> None:
        if not self.storage_path.exists():
            return
        payload = json.loads(self.storage_path.read_text())
        for emp_id, item in payload.items():
            embeddings = np.array(item.get("embeddings", []), dtype=np.float32)
            self._records[emp_id] = EmployeeEmbedding(
                employee_id=emp_id,
                employee_name=item.get("employee_name"),
                embeddings=embeddings,
            )

    def _dump(self) -> None:
        serializable = {}
        for emp_id, rec in self._records.items():
            serializable[emp_id] = {
                "employee_id": rec.employee_id,
                "employee_name": rec.employee_name,
                "embeddings": rec.embeddings.tolist(),
            }
        self.storage_path.write_text(json.dumps(serializable, indent=2))

    def list(self) -> List[EmployeeEmbedding]:
        return list(self._records.values())

    def upsert(self, employee_id: str, employee_name: Optional[str], embedding: np.ndarray) -> None:
        embedding = _normalize(embedding).astype(np.float32)
        record = self._records.get(employee_id)
        if record is None:
            record = EmployeeEmbedding(
                employee_id=employee_id,
                employee_name=employee_name,
                embeddings=np.expand_dims(embedding, axis=0),
            )
            self._records[employee_id] = record
        else:
            record.employee_name = employee_name or record.employee_name
            record.embeddings = np.vstack([record.embeddings, embedding])
        self._dump()

    def match(self, embedding: np.ndarray) -> Optional[Tuple[EmployeeEmbedding, float]]:
        if not self._records:
            return None
        embedding = _normalize(embedding).astype(np.float32)
        best_score = -1.0
        best_record: Optional[EmployeeEmbedding] = None
        for record in self._records.values():
            emb = _normalize(record.avg)
            score = float(np.dot(embedding, emb))
            if score > best_score:
                best_score = score
                best_record = record
        if best_record is None:
            return None
        return best_record, best_score

    def delete(self, employee_id: str) -> bool:
        if employee_id in self._records:
            del self._records[employee_id]
            self._dump()
            return True
        return False
