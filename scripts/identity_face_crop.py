#!/usr/bin/env python3

import os
import sys
import cv2
import numpy as np


CASCADE_PATHS = [
    "/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml",
    "/usr/share/opencv/haarcascades/haarcascade_frontalface_default.xml",
]


def fail(message, code=1):
    print(message, file=sys.stderr)
    raise SystemExit(code)


def load_cascade():
    for path in CASCADE_PATHS:
        if os.path.isfile(path):
            cascade = cv2.CascadeClassifier(path)

            if not cascade.empty():
                return cascade

    fail("FACE_CASCADE_NOT_AVAILABLE")


def rotate_image(image, rotation):
    if rotation == 0:
        return image

    if rotation == 90:
        return cv2.rotate(
            image,
            cv2.ROTATE_90_CLOCKWISE
        )

    if rotation == 270:
        return cv2.rotate(
            image,
            cv2.ROTATE_90_COUNTERCLOCKWISE
        )

    return image


def candidate_faces(image, cascade):
    gray = cv2.cvtColor(
        image,
        cv2.COLOR_BGR2GRAY
    )

    gray = cv2.equalizeHist(gray)

    height, width = gray.shape[:2]

    minimum = max(
        28,
        int(
            min(
                width,
                height
            ) * 0.055
        )
    )

    attempts = [
        (1.07, 5),
        (1.06, 4),
        (1.05, 3),
    ]

    found = []

    for scale, neighbours in attempts:
        faces = cascade.detectMultiScale(
            gray,
            scaleFactor=scale,
            minNeighbors=neighbours,
            minSize=(
                minimum,
                minimum
            ),
            flags=cv2.CASCADE_SCALE_IMAGE,
        )

        for x, y, w, h in faces:
            ratio = (
                float(w) / float(h)
                if h
                else 0
            )

            if (
                ratio < 0.68
                or ratio > 1.45
            ):
                continue

            area = w * h

            found.append(
                (
                    area,
                    int(x),
                    int(y),
                    int(w),
                    int(h),
                )
            )

        if found:
            break

    return found


def crop_portrait(image, face):
    _, x, y, w, h = face

    image_height, image_width = (
        image.shape[:2]
    )

    center_x = x + w / 2
    center_y = y + h / 2

    crop_width = w * 2.15
    crop_height = h * 2.55

    left = int(
        center_x - crop_width / 2
    )

    top = int(
        center_y - crop_height * 0.42
    )

    right = int(
        center_x + crop_width / 2
    )

    bottom = int(
        top + crop_height
    )

    left = max(
        0,
        left
    )

    top = max(
        0,
        top
    )

    right = min(
        image_width,
        right
    )

    bottom = min(
        image_height,
        bottom
    )

    crop = image[
        top:bottom,
        left:right
    ]

    if crop.size == 0:
        fail(
            "FACE_CROP_EMPTY"
        )

    target = 360

    crop_height_px, crop_width_px = (
        crop.shape[:2]
    )

    scale = min(
        target / crop_width_px,
        target / crop_height_px
    )

    new_width = max(
        1,
        int(
            crop_width_px * scale
        )
    )

    new_height = max(
        1,
        int(
            crop_height_px * scale
        )
    )

    resized = cv2.resize(
        crop,
        (
            new_width,
            new_height
        ),
        interpolation=(
            cv2.INTER_AREA
            if scale < 1
            else cv2.INTER_CUBIC
        ),
    )

    canvas = np.full(
        (
            target,
            target,
            3
        ),
        245,
        dtype=np.uint8
    )

    offset_x = (
        target - new_width
    ) // 2

    offset_y = (
        target - new_height
    ) // 2

    canvas[
        offset_y:
            offset_y + new_height,
        offset_x:
            offset_x + new_width
    ] = resized

    return canvas


def main():
    if len(sys.argv) != 3:
        fail(
            "USAGE: input output"
        )

    source = sys.argv[1]
    output = sys.argv[2]

    image = cv2.imread(
        source,
        cv2.IMREAD_COLOR
    )

    if image is None:
        fail(
            "IMAGE_READ_FAILED"
        )

    cascade = load_cascade()

    best = None

    best_image = None

    for rotation in [
        0,
        90,
        270
    ]:
        rotated = rotate_image(
            image,
            rotation
        )

        faces = candidate_faces(
            rotated,
            cascade
        )

        if not faces:
            continue

        faces.sort(
            key=lambda item: item[0],
            reverse=True
        )

        candidate = faces[0]

        if (
            best is None
            or candidate[0] > best[0]
        ):
            best = candidate
            best_image = rotated

    if best is None:
        print(
            "FACE_NOT_DETECTED"
        )

        raise SystemExit(2)

    portrait = crop_portrait(
        best_image,
        best
    )

    directory = os.path.dirname(
        output
    )

    if directory:
        os.makedirs(
            directory,
            exist_ok=True
        )

    ok = cv2.imwrite(
        output,
        portrait,
        [
            cv2.IMWRITE_WEBP_QUALITY,
            68
        ]
    )

    if not ok:
        fail(
            "FACE_WRITE_FAILED"
        )

    print(
        "FACE_DETECTED"
    )

    print(
        "FACE_BYTES="
        + str(
            os.path.getsize(
                output
            )
        )
    )


if __name__ == "__main__":
    main()
