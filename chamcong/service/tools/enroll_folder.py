from __future__ import annotations

import argparse
import base64
import json
from pathlib import Path

import requests


def to_base64(image_path: Path) -> str:
    data = base64.b64encode(image_path.read_bytes()).decode()
    return f"data:image/jpeg;base64,{data}"


def main() -> None:
    parser = argparse.ArgumentParser(description="Enroll toàn bộ ảnh trong thư mục.")
    parser.add_argument("folder", type=Path, help="Đường dẫn chứa ảnh (jpg/png).")
    parser.add_argument("employee_id", type=str, help="Mã nhân viên.")
    parser.add_argument("--name", type=str, default=None, help="Tên hiển thị.")
    parser.add_argument(
        "--api",
        type=str,
        default="http://127.0.0.1:8001/faces/enroll",
        help="Endpoint enroll.",
    )
    args = parser.parse_args()

    folder: Path = args.folder
    if not folder.exists():
        raise SystemExit(f"Folder {folder} không tồn tại.")

    employee_name = args.name or args.employee_id

    for image_path in sorted(folder.glob("*")):
        if image_path.suffix.lower() not in {".jpg", ".jpeg", ".png"}:
            continue
        payload = {
            "employee_id": args.employee_id,
            "employee_name": employee_name,
            "image_base64": to_base64(image_path),
        }
        response = requests.post(args.api, data=json.dumps(payload), headers={"Content-Type": "application/json"})
        status = "OK" if response.ok else "ERR"
        print(f"[{status}] {image_path.name} -> {response.status_code} {response.text}")


if __name__ == "__main__":
    main()
