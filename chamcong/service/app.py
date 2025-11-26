from __future__ import annotations

import logging
import sys
from pathlib import Path
from typing import Optional

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from PIL import Image

BASE_DIR = Path(__file__).resolve().parent
if str(BASE_DIR) not in sys.path:
    sys.path.append(str(BASE_DIR))

from face_pipeline import FacePipeline  # type: ignore  # noqa: E402
from schemas import EnrollRequest, RecognizeRequest, RecognizeResponse  # type: ignore  # noqa: E402
from storage import EmbeddingStore  # type: ignore  # noqa: E402
from utils import decode_base64_image, ensure_dir  # type: ignore  # noqa: E402

logger = logging.getLogger(__name__)

DATA_DIR = BASE_DIR / "data"
STORE_PATH = DATA_DIR / "embeddings.json"

ensure_dir(DATA_DIR)

pipeline = FacePipeline()
store = EmbeddingStore(STORE_PATH)

app = FastAPI(
    title="Face Attendance Service",
    version="0.1.0",
    description="Detects and recognizes faces for attendance logging.",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


def _image_from_payload(image_base64: Optional[str]) -> Image.Image:
    if not image_base64:
        raise HTTPException(status_code=400, detail="Thiếu ảnh base64.")
    try:
        return decode_base64_image(image_base64)
    except Exception as exc:  # noqa: BLE001
        logger.exception("Decode image failed")
        raise HTTPException(status_code=400, detail="Ảnh base64 không hợp lệ") from exc


@app.get("/health")
async def health_check() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/faces/enroll")
async def enroll_face(payload: EnrollRequest) -> dict[str, str]:
    image = _image_from_payload(payload.image_base64)
    try:
        embedding = pipeline.extract_embedding(image)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc

    store.upsert(payload.employee_id, payload.employee_name, embedding)
    return {"status": "enrolled", "employee_id": payload.employee_id}


@app.delete("/faces/{employee_id}")
async def delete_face(employee_id: str) -> dict[str, str]:
    removed = store.delete(employee_id)
    if not removed:
        raise HTTPException(status_code=404, detail="Không tìm thấy nhân viên trong embeddings.")
    return {"status": "deleted", "employee_id": employee_id}


@app.post("/faces/recognize", response_model=RecognizeResponse)
async def recognize_face(payload: RecognizeRequest) -> RecognizeResponse:
    image = _image_from_payload(payload.image_base64)
    try:
        embedding = pipeline.extract_embedding(image)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc

    match = store.match(embedding)
    if match is None:
        return RecognizeResponse(matched=False, score=0.0)

    employee, score = match
    threshold = payload.threshold or 0.65
    matched = score >= threshold
    return RecognizeResponse(
        matched=matched,
        employee_id=employee.employee_id if matched else None,
        employee_name=employee.employee_name if matched else None,
        score=score,
    )
