#!/usr/bin/env python3

import os
import sys

import cv2


CASCADE_PATH = (
    "/usr/share/opencv4/haarcascades/"
    "haarcascade_frontalface_default.xml"
)


def fail(message):
    print(message, file=sys.stderr)
    sys.exit(2)


def rotated_images(image):
    return [
        image,
        cv2.rotate(
            image,
            cv2.ROTATE_90_CLOCKWISE
        ),
        cv2.rotate(
            image,
            cv2.ROTATE_90_COUNTERCLOCKWISE
        ),
    ]


def find_best_face(image, cascade):
    best = None

    for rotated in rotated_images(image):
        gray = cv2.cvtColor(
            rotated,
            cv2.COLOR_BGR2GRAY
        )

        gray = cv2.equalizeHist(gray)

        height, width = gray.shape[:2]

        min_side = max(
            40,
            min(width, height) // 22
        )

        faces = cascade.detectMultiScale(
            gray,
            scaleFactor=1.06,
            minNeighbors=7,
            minSize=(
                min_side,
                min_side
            ),
            flags=cv2.CASCADE_SCALE_IMAGE,
        )

        for x, y, w, h in faces:
            if w <= 0 or h <= 0:
                continue

            ratio = w / float(h)

            if ratio < 0.72 or ratio > 1.42:
                continue

            image_area = width * height
            face_area = w * h

            relative_area = (
                face_area /
                float(image_area)
            )

            if relative_area < 0.001:
                continue

            if relative_area > 0.35:
                continue

            score = face_area

            if (
                best is None
                or score > best["score"]
            ):
                best = {
                    "image": rotated,
                    "x": int(x),
                    "y": int(y),
                    "w": int(w),
                    "h": int(h),
                    "score": int(score),
                }

    return best


def crop_face_only(candidate):
    image = candidate["image"]

    x = candidate["x"]
    y = candidate["y"]
    w = candidate["w"]
    h = candidate["h"]

    image_h, image_w = image.shape[:2]

    center_x = x + (w / 2.0)
    center_y = y + (h / 2.0)

    # Tight square around the detected face.
    # Enough space for hair/chin, but intentionally
    # avoids ID text and document background.
    side = int(
        round(
            max(w, h) * 1.28
        )
    )

    side = max(
        side,
        max(w, h)
    )

    # Slight upward bias keeps forehead/hair
    # while preventing too much area below face.
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
            left - shift
        )
        right = image_w

    if bottom > image_h:
        shift = bottom - image_h
        top = max(
            0,
            top - shift
        )
        bottom = image_h

    crop = image[
        top:bottom,
        left:right
    ]

    if crop.size == 0:
        fail(
            "FACE_CROP_EMPTY"
        )

    crop = cv2.resize(
        crop,
        (360, 360),
        interpolation=cv2.INTER_LANCZOS4
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
        fail(
            "SOURCE_NOT_FOUND"
        )

    cascade = cv2.CascadeClassifier(
        CASCADE_PATH
    )

    if cascade.empty():
        fail(
            "FACE_CASCADE_UNAVAILABLE"
        )

    image = cv2.imread(
        source,
        cv2.IMREAD_COLOR
    )

    if image is None:
        fail(
            "IMAGE_READ_FAILED"
        )

    candidate = find_best_face(
        image,
        cascade
    )

    if candidate is None:
        fail(
            "FACE_NOT_DETECTED"
        )

    face = crop_face_only(
        candidate
    )

    os.makedirs(
        os.path.dirname(
            os.path.abspath(target)
        ),
        exist_ok=True
    )

    ok = cv2.imwrite(
        target,
        face,
        [
            cv2.IMWRITE_WEBP_QUALITY,
            72,
        ]
    )

    if not ok:
        fail(
            "FACE_WRITE_FAILED"
        )

    if (
        not os.path.isfile(target)
        or os.path.getsize(target) < 100
    ):
        fail(
            "FACE_OUTPUT_INVALID"
        )

    print(
        "FACE_ONLY_CROP=PASS"
    )


if __name__ == "__main__":
    main()
