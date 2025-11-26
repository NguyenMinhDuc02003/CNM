from __future__ import annotations

from dataclasses import dataclass
from typing import Optional, Sequence

import numpy as np
import torch
from facenet_pytorch import InceptionResnetV1, MTCNN
from PIL import Image


@dataclass
class DetectionResult:
    tensor: torch.Tensor
    confidence: float


class FacePipeline:
    def __init__(self, device: Optional[str] = None, image_size: int = 160):
        self.device = device or ("cuda" if torch.cuda.is_available() else "cpu")
        self._detector = MTCNN(
            image_size=image_size,
            margin=20,
            keep_all=False,
            min_face_size=60,
            device=self.device,
            post_process=True,
            selection_method="center_weighted_size",
        )
        self._encoder = (
            InceptionResnetV1(pretrained="vggface2", classify=False)
            .eval()
            .to(self.device)
        )

    def detect_and_align(self, image: Image.Image) -> Optional[DetectionResult]:
        face_tensor, prob = self._detector(image, return_prob=True)
        if face_tensor is None or prob is None:
            return None
        score = float(prob if isinstance(prob, (int, float)) else prob[0])
        if score < 0.7:
            return None
        if face_tensor.ndim == 3:
            face_tensor = face_tensor.unsqueeze(0)
        return DetectionResult(tensor=face_tensor, confidence=score)

    def embed(self, aligned_tensor: torch.Tensor) -> np.ndarray:
        if aligned_tensor.ndim == 3:
            aligned_tensor = aligned_tensor.unsqueeze(0)
        aligned_tensor = aligned_tensor.to(self.device)
        with torch.no_grad():
            embedding = self._encoder(aligned_tensor).cpu().numpy()[0]
        return embedding

    def extract_embedding(self, image: Image.Image) -> np.ndarray:
        detection = self.detect_and_align(image)
        if detection is None:
            raise ValueError("Không phát hiện được gương mặt đạt ngưỡng.")
        return self.embed(detection.tensor)

    @staticmethod
    def batch_normalize(embeddings: Sequence[np.ndarray]) -> np.ndarray:
        if not embeddings:
            raise ValueError("Empty embeddings")
        arr = np.vstack(embeddings).astype(np.float32)
        norms = np.linalg.norm(arr, axis=1, keepdims=True).clip(min=1e-12)
        return arr / norms
