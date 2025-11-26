from __future__ import annotations

import base64
import io
from pathlib import Path
from typing import Optional

import numpy as np
from PIL import Image


def read_image_file(file_path: Path) -> np.ndarray:
    return np.array(Image.open(file_path).convert("RGB"))


def decode_base64_image(data: str) -> Image.Image:
    payload = data.split(",")[-1]
    binary = base64.b64decode(payload)
    return Image.open(io.BytesIO(binary)).convert("RGB")


def pil_to_ndarray(image: Image.Image) -> np.ndarray:
    return np.array(image)


def ensure_dir(path: Path) -> None:
    path.mkdir(parents=True, exist_ok=True)
