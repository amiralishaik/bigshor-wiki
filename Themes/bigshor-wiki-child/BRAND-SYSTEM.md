# BIGSHOR Wiki — Official Brand Identity System

## Official assets
- BS monogram: `../bigshor-wiki-parent/assets/brand/bigshor-bs-monogram.jpg` (supplied by BIGSHOR; use without distortion or added text).
- Full horizontal wordmark reference: `../bigshor-wiki-parent/assets/brand/bigshor-full-wordmark-reference.png`.

## Official palette
- BIGSHOR Red: `#FE0000`
- Black: `#000000`
- White: `#FFFFFF`
- Neutral canvas: `#F7F7F7`
- Body text: `#171717`
- Borders: `#E5E5E5`

## Application
- Header mark and favicon fallback: supplied BS monogram.
- Header and navigation: white/black with restrained red active states.
- Primary actions: red with white text; hover: black with white text.
- Article reading pages: white surfaces, high contrast, restrained decoration.
- News division: same master brand with distinct News templates and labels.

## Architecture
The parent theme owns templates and logo rendering. The child theme owns brand tokens and visual overrides. BIGSHOR Wiki Core remains responsible for content types, editorial workflows, permissions and persistent data.


## Appearance policy (v20.0.5)
BIGSHOR Wiki uses a permanent light appearance. Dark-mode controls and automatic operating-system dark-mode switching are disabled. Surfaces remain white/light grey with dark text and BIGSHOR red accents.
