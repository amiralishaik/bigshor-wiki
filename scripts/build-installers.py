"""Build the three BIGSHOR WordPress installers from repository source."""
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile
import sys

root = Path(__file__).resolve().parents[1]
destination = Path(sys.argv[1]) if len(sys.argv) > 1 else root / "dist"
destination.mkdir(parents=True, exist_ok=True)
core = root / "Plugins/BIGSHOR_WIKI_CORE_v34.10.0"
admin = root / "Plugins/bigshor-wiki-admin-ui"
packages = {
    "bigshor-wiki-core": [(core, ""), (admin / "includes", "admin-ui/includes"), (admin / "assets", "admin-ui/assets")],
    "bigshor-wiki-parent": [(root / "Themes/bigshor-wiki-parent", "")],
    "bigshor-wiki-child": [(root / "Themes/bigshor-wiki-child", "")],
}
for slug, sources in packages.items():
    path = destination / (slug + ".zip")
    with ZipFile(path, "w", compression=ZIP_DEFLATED) as archive:
        for source, subdirectory in sources:
            if not source.is_dir():
                raise FileNotFoundError(source)
            for file in sorted(source.rglob("*")):
                if file.is_file():
                    archive.write(file, Path(slug) / subdirectory / file.relative_to(source))
    with ZipFile(path) as archive:
        if archive.testzip():
            raise ValueError(f"Invalid installer: {path}")
    print(path)
