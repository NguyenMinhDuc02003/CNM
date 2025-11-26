# Face Attendance Service

Service Python độc lập xử lý nhận diện gương mặt và cung cấp API để hệ thống Admin PHP gọi tới.

## Cấu trúc

```
chamcong/service/
├── app.py                # FastAPI application
├── face_pipeline.py      # Pipeline detect + embedding + matching
├── storage.py            # Lưu embeddings và metadata
├── schemas.py            # Pydantic models cho request/response
├── utils.py              # Hỗ trợ xử lý ảnh
└── requirements.txt
```

## Yêu cầu

- Python ≥ 3.10
- Các package trong `requirements.txt` (FastAPI, facenet-pytorch, torch, torchvision, opencv-python, Pillow,…)

## Chạy service

```bash
cd chamcong/service
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
uvicorn app:app --host 0.0.0.0 --port 8001 --reload
```

## Quy trình

1. `POST /faces/enroll` với `employee_id`, `employee_name` và ảnh (base64). Service detect mặt, trích embedding và lưu vào kho (mặc định `data/embeddings.json`).
2. `POST /faces/recognize` với ảnh mới → service trả về nhân viên phù hợp nếu điểm tương đồng vượt ngưỡng (mặc định 0.65).
3. Admin PHP gọi API này để ghi nhận chấm công.

## API mẫu

- Enroll:
```json
POST /faces/enroll
{
  "employee_id": "NV001",
  "employee_name": "Nguyen Van A",
  "image_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD..."
}
```

- Recognize:
```json
POST /faces/recognize
{
  "image_base64": "...",
  "threshold": 0.65
}
```

Kết quả:
```json
{
  "matched": true,
  "employee_id": "NV001",
  "employee_name": "Nguyen Van A",
  "score": 0.81
}
```

## Gợi ý tích hợp Admin

1. Tạo trang quản lý khuôn mặt: cho phép upload ảnh, JS chuyển ảnh sang base64 và gọi `/faces/enroll`. Khi enroll thành công, lưu thêm log vào DB.
2. Tạo kiosk/webcam: front-end lấy frame ảnh (JS canvas) → POST `/faces/recognize`. Nếu `matched=true`, gọi API nội bộ Admin để ghi bảng chấm công.
3. Logging & bảo mật: đặt API key đơn giản ở header, chỉ expose trong LAN, ghi log mỗi request kèm IP/người thực hiện.

## Dữ liệu

- Ảnh mẫu nên lưu tại `data/faces/<employee_id>/aligned/`.
- Script nội bộ có thể gọi class `FacePipeline` để trích embedding hàng loạt khi cần khởi tạo.
