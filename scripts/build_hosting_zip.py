#!/usr/bin/env python3
"""Construye el ZIP autocontenido de hosting sin bases, secretos ni dependencias."""
from __future__ import annotations

import sys
import zipfile
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[1]
DEMO = "--demo" in sys.argv[1:]
arguments = [argument for argument in sys.argv[1:] if argument != "--demo"]
if len(arguments) > 1:
    raise SystemExit("Uso: python3 scripts/build_hosting_zip.py [--demo] [ruta_de_salida]")
default_name = "normia-sst-hosting-demo.zip" if DEMO else "normia-sst-hosting.zip"
OUTPUT = Path(arguments[0]).resolve() if arguments else ROOT / "dist" / default_name

files: dict[str, Path] = {}


def add_file(source: str, target: str) -> None:
    path = ROOT / source
    if path.is_symlink() or not path.is_file():
        raise SystemExit(f"Falta un archivo requerido o es un enlace simbólico: {source}")
    if target in files:
        raise SystemExit(f"Entrada duplicada: {target}")
    files[target] = path


def add_tree(source: str, target: str) -> None:
    base = ROOT / source
    if not base.is_dir():
        raise SystemExit(f"Falta el directorio requerido: {source}")
    for path in sorted(base.rglob("*")):
        if path.is_symlink():
            raise SystemExit(f"No se permiten enlaces simbólicos en el paquete: {path}")
        if not path.is_file():
            continue
        if path.name == ".DS_Store" or "__pycache__" in path.parts:
            continue
        relative = path.relative_to(base).as_posix()
        add_file(str(path.relative_to(ROOT)), f"{target}/{relative}")


add_tree("app", "normia-sst/app")
add_tree("config", "normia-sst/config")
add_tree("resources/sgsst-library", "normia-sst/resources/sgsst-library")
add_tree("resources/pesv-library", "normia-sst/resources/pesv-library")
add_file("resources/iso7010-catalog.json", "normia-sst/resources/iso7010-catalog.json")
add_file("resources/cad-objects.json", "normia-sst/resources/cad-objects.json")
add_tree("public/assets", "public_html/assets")
for required_asset in ("css/app.css", "js/app.js", "js/floor-plan.js", "js/floor-plan-signs.js", "js/floor-plan-cad.js", "js/vendor/qrcode.js", "js/vendor/qrcode.LICENSE.txt", "js/vendor/iso-safety-signs.LICENSE.txt", "js/vendor/iso-safety-signs.NOTICE.txt"):
    target = f"public_html/assets/{required_asset}"
    if target not in files:
        raise SystemExit(f"Falta el recurso público obligatorio: {required_asset}. Ejecuta npm ci && npm run build.")
add_file("public/index.php", "normia-sst/public/index.php")
for name in ("schema.sql", ".htaccess", "index.php"):
    add_file(f"database/{name}", f"normia-sst/database/{name}")
for name in (".htaccess", "index.php"):
    add_file(f"storage/{name}", f"normia-sst/storage/{name}")
for name in ("install.php", "migrate.php"):
    add_file(f"scripts/{name}", f"normia-sst/scripts/{name}")
add_file("deploy/public_html/index.php", "public_html/index.php")
add_file("deploy/public_html/.htaccess", "public_html/.htaccess")
add_file("deploy/LEEME-DEMO.txt" if DEMO else "deploy/LEEME.txt", "LEEME.txt")

for archive_name in files:
    pure = PurePosixPath(archive_name)
    if pure.is_absolute() or ".." in pure.parts:
        raise SystemExit(f"Ruta insegura: {archive_name}")
    lowered = archive_name.lower()
    if lowered.endswith((".db", ".db-wal", ".db-shm", ".sqlite", ".log")) or "node_modules/" in lowered:
        raise SystemExit(f"Archivo prohibido en el paquete: {archive_name}")

OUTPUT.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(OUTPUT, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for target, source in sorted(files.items()):
        archive.write(source, target)
    environment_name = "deploy/demo.env.example" if DEMO else "deploy/production.env.example"
    environment = (ROOT / environment_name).read_bytes()
    env_info = zipfile.ZipInfo("normia-sst/.env")
    env_info.external_attr = 0o100600 << 16
    archive.writestr(env_info, environment)
    for directory in ("normia-sst/storage/logs/", "normia-sst/storage/uploads/"):
        info = zipfile.ZipInfo(directory)
        info.external_attr = (0o40755 << 16) | 0x10
        archive.writestr(info, b"")

with zipfile.ZipFile(OUTPUT) as archive:
    broken = archive.testzip()
    if broken:
        raise SystemExit(f"El ZIP no superó la verificación CRC: {broken}")
    names = archive.namelist()
    if "public_html/index.php" not in names or "normia-sst/.env" not in names:
        raise SystemExit("El paquete no contiene el cargador o el entorno de producción")
    if any(name.endswith((".db", ".sqlite")) for name in names):
        raise SystemExit("El paquete contiene una base de datos")

print(f"ZIP verificado: {OUTPUT}")
print(f"{len(names)} entradas · {OUTPUT.stat().st_size / 1024 / 1024:.2f} MiB")
print("Estructura: public_html + normia-sst · " + ("datos ficticios al instalar · sin base ni sesiones" if DEMO else "sin base demo ni credenciales"))
