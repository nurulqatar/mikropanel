#!/usr/bin/env python3

import os
import sys

import cv2


CASCADE_PATH = (
    "/usr/share/opencv4/haarcascades/"
    "haarcascade_frontalface_default.xml"
)

DETECTION_MAX_SIDE = 640


def fail(message):
    print(message, file=sys.stderr, flush=True)
    sys.exit(2)


def detection_image(image):
    height, width = image.shape[:2]
    longest = max(width, height)

    if longest <= DETECTION_MAX_SIDE:
        return image, 1.0

    scale = DETECTION_MAX_SIDE / float(longest)

    resized = cv2.resize(
        image,
        (
            max(1, int(round(width * scale))),
            max(1, int(round(height * scale))),
        ),
        interpolation=cv2.INTER_AREA,
    )

    return resized, scale


def detect_one_orientation(image, cascade):
    small, scale = detection_image(image)

    gray = cv2.cvtColor(
        small,
        cv2.COLOR_BGR2GRAY,
    )

    gray = cv2.equalizeHist(gray)

    height, width = gray.shape[:2]

    min_side = max(
        28,
        min(width, height) // 18,
    )

    faces = cascade.detectMultiScale(
        gray,
        scaleFactor=1.12,
        minNeighbors=5,
        minSize=(
            min_side,
            min_side,
        ),
        flags=cv2.CASCADE_SCALE_IMAGE,
    )

    if len(faces) == 0:
        return None

    best = None

    for x, y, w, h in faces:
        if w <= 0 or h <= 0:
            continue

        ratio = w / float(h)

        if ratio < 0.68 or ratio > 1.48:
            continue

        image_area = width * height
        face_area = w * h

        relative_area = (
            face_area /
            float(image_area)
        )

        if relative_area < 0.0008:
            continue

        if relative_area > 0.40:
            continue

        if (
            best is None
            or face_area > best["score"]
        ):
            inverse = 1.0 / scale

            best = {
                "image": image,
                "x": int(round(x * inverse)),
                "y": int(round(y * inverse)),
                "w": int(round(w * inverse)),
                "h": int(round(h * inverse)),
                "score": int(face_area),
            }

    return best


def find_best_face(image, cascade):
    # Fast path:
    # scanner/browser images normally arrive correctly oriented.
    candidate = detect_one_orientation(
        image,
        cascade,
    )

    if candidate is not None:
        return candidate

    # Rotation fallback runs only when normal orientation fails.
    rotated = cv2.rotate(
        image,
        cv2.ROTATE_90_CLOCKWISE,
    )

    candidate = detect_one_orientation(
        rotated,
        cascade,
    )

    if candidate is not None:
        return candidate

    rotated = cv2.rotate(
        image,
        cv2.ROTATE_90_COUNTERCLOCKWISE,
    )

    return detect_one_orientation(
        rotated,
        cascade,
    )


def crop_face_only(candidate):
    image = candidate["image"]

    x = candidate["x"]
    y = candidate["y"]
    w = candidate["w"]
    h = candidate["h"]

    image_h, image_w = image.shape[:2]

    center_x = x + (w / 2.0)
    center_y = y + (h / 2.0)

    side = int(
        round(
            max(w, h) * 1.30
        )
    )

    side = max(
        side,
        max(w, h),
    )

    center_y -= h * 0.03

    left = int(
        round(
            center_x - side / 2
        )
    )

    top = int(
        round(
            center_y - side / 2
        )
    )

    right = left + side
    bottom = top + side

    if left < 0:
        right -= left
        left = 0

    if top < 0:
        bottom -= top
        top = 0

    if right > image_w:
        shift = right - image_w
        left = max(
            0,
            left - shift,
        )
        right = image_w

    if bottom > image_h:
        shift = bottom - image_h
        top = max(
            0,
            top - shift,
        )
        bottom = image_h

    crop = image[
        top:bottom,
        left:right
    ]

    if crop.size == 0:
        fail("FACE_CROP_EMPTY")

    crop = cv2.resize(
        crop,
        (360, 360),
        interpolation=cv2.INTER_LANCZOS4,
    )

    return crop


def main():
    if len(sys.argv) < 3:
        fail(
            "USAGE: identity_face_crop.py "
            "<input> <output>"
        )

    source = sys.argv[1]
    target = sys.argv[2]

    if not os.path.isfile(source):
        fail("SOURCE_NOT_FOUND")

    # Keep OpenCV predictable on the 1-vCPU VPS.
    try:
        cv2.setNumThreads(1)
        cv2.setUseOptimized(True)
    except Exception:
        pass

    cascade = cv2.CascadeClassifier(
        CASCADE_PATH
    )

    if cascade.empty():
        fail("FACE_CASCADE_UNAVAILABLE")

    image = cv2.imread(
        source,
        cv2.IMREAD_COLOR,
    )

    if image is None:
        fail("IMAGE_READ_FAILED")

    candidate = find_best_face(
        image,
        cascade,
    )

    if candidate is None:
        fail("FACE_NOT_DETECTED")

    face = crop_face_only(
        candidate
    )

    os.makedirs(
        os.path.dirname(
            os.path.abspath(target)
        ),
        exist_ok=True,
    )

    ok = cv2.imwrite(
        target,
        face,
        [
            cv2.IMWRITE_WEBP_QUALITY,
            72,
        ],
    )

    if not ok:
        fail("FACE_WRITE_FAILED")

    if (
        not os.path.isfile(target)
        or os.path.getsize(target) < 100
    ):
        fail("FACE_OUTPUT_INVALID")

    print(
        "FACE_FAST_V29=PASS",
        flush=True,
    )


if __name__ == "__main__":
    main()
