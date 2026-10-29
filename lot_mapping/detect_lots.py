import argparse
import hashlib
import json
import sys
from pathlib import Path

import cv2
import numpy as np


def detection_output_for_image(image_path):
    """
    Generate a separate JSON filename for each map image.
    Must match PHP: hash('sha256', basename(map_image_path)).
    """
    image_name = image_path.name
    detection_key = hashlib.sha256(
        image_name.encode("utf-8")
    ).hexdigest()

    return (
        Path(__file__).resolve().parent
        / "uploads"
        / "detections"
        / f"{detection_key}.json"
    )


def detect_lots(image_path, output_path=None):
    image_path = Path(image_path)

    if not image_path.is_file():
        raise FileNotFoundError(
            f"Map image not found: {image_path}"
        )

    # Read images, including paths containing special characters.
    image_data = np.fromfile(str(image_path), dtype=np.uint8)
    image = cv2.imdecode(image_data, cv2.IMREAD_COLOR)

    if image is None:
        raise ValueError(
            "Cannot read image. Check that it is a valid JPG or PNG."
        )

    original_height, original_width = image.shape[:2]

    # Resize large maps for faster processing.
    scale = min(
        1.0,
        2400 / max(original_width, original_height)
    )

    if scale < 1:
        image = cv2.resize(
            image,
            (
                int(original_width * scale),
                int(original_height * scale)
            ),
            interpolation=cv2.INTER_AREA
        )

    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    gray = cv2.GaussianBlur(gray, (3, 3), 0)

    # Detect printed lines and close small gaps.
    edges = cv2.Canny(gray, 40, 120)

    kernel = cv2.getStructuringElement(
        cv2.MORPH_RECT,
        (3, 3)
    )

    edges = cv2.morphologyEx(
        edges,
        cv2.MORPH_CLOSE,
        kernel,
        iterations=2
    )

    contours, _ = cv2.findContours(
        edges,
        cv2.RETR_LIST,
        cv2.CHAIN_APPROX_SIMPLE
    )

    work_height, work_width = gray.shape
    image_area = work_width * work_height
    candidates = []

    for contour in contours:
        area = abs(cv2.contourArea(contour))

        if area < max(150, image_area * 0.00002):
            continue

        if area > image_area * 0.15:
            continue

        perimeter = cv2.arcLength(contour, True)

        if perimeter <= 0:
            continue

        polygon = cv2.approxPolyDP(
            contour,
            0.015 * perimeter,
            True
        )

        if not 4 <= len(polygon) <= 12:
            continue

        x, y, w, h = cv2.boundingRect(polygon)

        if w < 12 or h < 12:
            continue

        points = polygon.reshape(-1, 2).astype(float)

        # Convert coordinates back to original image dimensions.
        points /= scale

        normalized_points = [
            [
                round(
                    float(
                        np.clip(
                            px / max(1, original_width - 1),
                            0,
                            1
                        )
                    ),
                    6
                ),
                round(
                    float(
                        np.clip(
                            py / max(1, original_height - 1),
                            0,
                            1
                        )
                    ),
                    6
                )
            ]
            for px, py in points
        ]

        pixel_points = [
            [
                int(round(np.clip(
                    px, 0, original_width - 1
                ))),
                int(round(np.clip(
                    py, 0, original_height - 1
                )))
            ]
            for px, py in points
        ]

        candidates.append({
            "points": normalized_points,
            "pixel_points": pixel_points,
            "area_pixels": int(
                round(area / (scale * scale))
            )
        })

    # Keep larger candidates first and remove near-identical boxes.
    candidates.sort(
        key=lambda item: item["area_pixels"],
        reverse=True
    )

    filtered = []

    for candidate in candidates:
        pts = np.array(
            candidate["pixel_points"],
            dtype=np.int32
        )

        x, y, w, h = cv2.boundingRect(pts)
        duplicate = False

        for existing in filtered:
            other = np.array(
                existing["pixel_points"],
                dtype=np.int32
            )

            ox, oy, ow, oh = cv2.boundingRect(other)

            intersection = (
                max(0, min(x + w, ox + ow) - max(x, ox))
                * max(0, min(y + h, oy + oh) - max(y, oy))
            )

            union = w * h + ow * oh - intersection

            if union and intersection / union > 0.88:
                duplicate = True
                break

        if not duplicate:
            filtered.append(candidate)

        if len(filtered) >= 500:
            break

    polygons = []

    for index, candidate in enumerate(filtered, start=1):
        polygons.append({
            "id": f"candidate-{index}",
            "points": candidate["points"],
            "pixel_points": candidate["pixel_points"],
            "area_pixels": candidate["area_pixels"],
            "confidence": "candidate",
            "review_status": "pending"
        })

    result = {
        "ok": True,
        "image": image_path.name,
        "image_width": original_width,
        "image_height": original_height,
        "coordinate_system": "normalized_0_to_1",
        "polygon_count": len(polygons),
        "polygons": polygons,
        "warnings": [
            "Detected polygons are candidates, not verified lot boundaries.",
            "Image lines, roads, text and stamps may produce false detections.",
            "Lot numbers and ownership must be entered or verified separately."
        ]
    }

    # Use a separate JSON file for each image by default.
    if output_path is None:
        output_path = detection_output_for_image(image_path)
    else:
        output_path = Path(output_path)

    output_path.parent.mkdir(
        parents=True,
        exist_ok=True
    )

    output_path.write_text(
        json.dumps(result, indent=2, ensure_ascii=False),
        encoding="utf-8"
    )

    print("Detection finished.")
    print("Image:", image_path.resolve())
    print("Candidate polygons:", len(polygons))
    print("JSON saved to:", output_path.resolve())


def main():
    parser = argparse.ArgumentParser(
        description=(
            "Detect candidate lot boundaries from a map image."
        )
    )

    parser.add_argument(
        "image",
        help="Path to the map image"
    )

    parser.add_argument(
        "--output",
        default=None,
        help=(
            "Optional JSON output path. "
            "If omitted, a separate file is created "
            "under uploads/detections/ for this image."
        )
    )

    args = parser.parse_args()

    try:
        detect_lots(args.image, args.output)
    except Exception as error:
        print("ERROR:", error, file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()