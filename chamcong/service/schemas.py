from __future__ import annotations

from typing import Optional

from pydantic import BaseModel, Field


class EnrollRequest(BaseModel):
    employee_id: str = Field(..., description="Mã nhân viên duy nhất")
    employee_name: Optional[str] = Field(None, description="Tên hiển thị")
    image_base64: Optional[str] = Field(
        None, description="Ảnh mã hóa base64 (JPEG/PNG). Nếu dùng multipart thì để trống."
    )


class RecognizeRequest(BaseModel):
    image_base64: Optional[str] = Field(None, description="Ảnh base64 cần nhận diện.")
    threshold: Optional[float] = Field(
        0.65,
        ge=0.0,
        le=1.0,
        description="Ngưỡng cosine similarity yêu cầu để xác nhận nhân viên.",
    )


class RecognizeResponse(BaseModel):
    matched: bool
    employee_id: Optional[str] = None
    employee_name: Optional[str] = None
    score: float = 0.0


class BatchEnrollResult(BaseModel):
    total: int
    success: int
    failed: int
