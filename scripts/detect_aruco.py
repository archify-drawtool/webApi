#!/usr/bin/env python3
"""
ArUco marker detection script for the Archify Laravel API.

Usage:
    python3 detect_aruco.py --image /path/to/image.jpg --dictionary DICT_4X4_50

Output:
    JSON to stdout with structure: {"markers": [...]}
    Zero detections is a valid success case: {"markers": []}

Exit codes:
    0 = success (including zero markers found)
    1 = argument or file error
    2 = opencv not installed or incompatible version
"""

import sys
import json
import math
import argparse


SUPPORTED_DICTIONARIES = [
    'DICT_4X4_50',
    'DICT_4X4_100',
    'DICT_4X4_250',
    'DICT_ARUCO_MIP_36h12',
    'DICT_ARUCO_ORIGINAL',
]


def get_aruco_dictionary(aruco, name: str):
    mapping = {
        'DICT_4X4_50':          aruco.DICT_4X4_50,
        'DICT_4X4_100':         aruco.DICT_4X4_100,
        'DICT_4X4_250':         aruco.DICT_4X4_250,
        'DICT_ARUCO_MIP_36h12': aruco.DICT_ARUCO_MIP_36h12,
        'DICT_ARUCO_ORIGINAL':  aruco.DICT_ARUCO_ORIGINAL,
    }
    if name not in mapping:
        print(
            json.dumps({'error': f'Unknown dictionary "{name}". Supported: {SUPPORTED_DICTIONARIES}'}),
            file=sys.stderr,
        )
        sys.exit(1)
    return aruco.getPredefinedDictionary(mapping[name])


def calculate_rotation(corners) -> float:
    """
    Calculate the 2D clockwise rotation angle of a marker in degrees.
    Uses the top edge (TL -> TR) relative to the positive X axis.
    Returns a value in the range (-180, 180].
    """
    tl = corners[0]
    tr = corners[1]
    dx = float(tr[0]) - float(tl[0])
    dy = float(tr[1]) - float(tl[1])
    return round(math.degrees(math.atan2(dy, dx)), 4)


def calculate_center(corners) -> dict:
    cx = sum(float(c[0]) for c in corners) / 4.0
    cy = sum(float(c[1]) for c in corners) / 4.0
    return {'x': round(cx, 4), 'y': round(cy, 4)}


def format_corner(point) -> dict:
    return {'x': round(float(point[0]), 4), 'y': round(float(point[1]), 4)}


def detect(image_path: str, dictionary_name: str) -> list:
    try:
        import cv2
    except ImportError:
        print(json.dumps({'error': 'opencv-contrib-python is not installed'}), file=sys.stderr)
        sys.exit(2)

    aruco = cv2.aruco

    image = cv2.imread(image_path)
    if image is None:
        print(json.dumps({'error': f'Could not read image: {image_path}'}), file=sys.stderr)
        sys.exit(1)

    dictionary = get_aruco_dictionary(aruco, dictionary_name)
    parameters = aruco.DetectorParameters()
    detector   = aruco.ArucoDetector(dictionary, parameters)

    corners_list, ids, _ = detector.detectMarkers(image)

    if ids is None or len(ids) == 0:
        return []

    markers = []
    for i, marker_id in enumerate(ids.flatten()):
        # corners_list[i] shape: (1, 4, 2) — squeeze to (4, 2)
        corner_points = corners_list[i][0]

        markers.append({
            'id':       int(marker_id),
            'center':   calculate_center(corner_points),
            'corners':  [format_corner(pt) for pt in corner_points],
            'rotation': calculate_rotation(corner_points),
        })

    markers.sort(key=lambda m: m['id'])

    return markers


def main():
    parser = argparse.ArgumentParser(description='Detect ArUco markers in an image.')
    parser.add_argument('--image',      required=True,             help='Absolute path to the image file')
    parser.add_argument('--dictionary', default='DICT_4X4_50',     help='ArUco dictionary name')
    args = parser.parse_args()

    markers = detect(args.image, args.dictionary)
    print(json.dumps({'markers': markers}))


if __name__ == '__main__':
    main()
