"""Generate synthetic fixtures; run before measured workloads: python3 make-fixtures.py OUTPUT_DIRECTORY."""

import argparse
import hashlib
import json
import random
from io import BytesIO
from pathlib import Path
from xml.sax.saxutils import escape
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

from PIL import Image, ImageDraw, ImageFont
from reportlab.lib.utils import ImageReader
from reportlab.pdfgen import canvas

MIB = 1024 * 1024
PROSE = "Synthetic coastal research requires ethics approval, seven transects and retention for eighteen months. "
TITLE = "Coastal Resilience Study"


def jpeg(path, size, seed, report=False):
    rng = random.Random(seed)
    low = Image.frombytes("RGB", (720, 480), rng.randbytes(720 * 480 * 3))
    image = low.resize(size, Image.Resampling.LANCZOS)
    low.close()
    draw = ImageDraw.Draw(image)
    for index in range(80):
        x, y = rng.randrange(size[0]), rng.randrange(size[1])
        draw.rectangle((x, y, min(size[0] - 1, x + 180), min(size[1] - 1, y + 90)),
                       outline=(index * 29 % 256, 140, 220), width=5)
    draw.text((50, 50), f"SYNTHETIC FIXTURE {seed}", fill="white")
    candidates = []
    for quality in range(5, 96, 3) if report else [82]:
        buffer = BytesIO()
        image.save(buffer, format="JPEG", quality=quality, optimize=False)
        candidates.append((abs(len(buffer.getvalue()) - 440 * 1024), buffer.getvalue()))
    encoded = min(candidates, key=lambda item: item[0])[1]
    path.write_bytes(encoded)
    image.close()
    if report:
        assert 340 * 1024 <= len(encoded) <= 510 * 1024, (path, len(encoded))
    assert len(encoded) <= 10 * MIB


def text_pdf(path, pages, dense=False, manual=False):
    pdf = canvas.Canvas(str(path), pagesize=(612, 792), pageCompression=1, invariant=1)
    for page in range(1, pages + 1):
        pdf.setFont("Helvetica", 9)
        pdf.drawString(30, 775, "SYNTHETIC RESEARCH MANUAL" if manual else "SYNTHETIC RESEARCH PAPER")
        pdf.drawString(30, 752, f"{page}. Research Policy")
        pdf.setFont("Helvetica", 5 if dense or manual else 9)
        lines = 65 if dense else (40 if manual else 6)
        for line in range(lines):
            text = f"Page {page} clause {line}: " + (PROSE if dense or manual else "Ethics approval precedes coastal sampling.")
            pdf.drawString(30, 730 - line * (10 if dense else 15), text)
        pdf.drawString(30, 30, f"CAPACITY-END-PAGE-{page}")
        pdf.drawString(560, 15, str(page))
        pdf.showPage()
    pdf.save()
    assert path.stat().st_size <= (25 if manual else 8) * MIB


def work_plan(path):
    lines = ["BatStateU-FO-RES-02", "MAJOR ACTIVITIES/WORK PLAN", "Total Duration Planned Start Planned End Expected Output",
             f"Project Title: {TITLE}", "Prepared by: Synthetic Faculty", "Checked and Verified by: Synthetic Research Office",
             "The approved research work plan records community sampling, project milestones and documented outputs."]
    pdf = canvas.Canvas(str(path), pagesize=(612, 792), invariant=1)
    pdf.setFont("Helvetica", 10)
    for offset, line in enumerate(lines):
        pdf.drawString(30, 750 - offset * 30, line)
    pdf.showPage()
    pdf.save()
    return lines


def scanned_form(path, lines):
    font_path = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
    font = ImageFont.truetype(font_path, 36) if Path(font_path).exists() else ImageFont.load_default()
    pdf = canvas.Canvas(str(path), pagesize=(612, 792), pageCompression=1, invariant=1)
    for page in range(1, 3):
        rng = random.Random(600 + page)
        noise = Image.frombytes("L", (2480, 3508), rng.randbytes(2480 * 3508))
        image = noise.point(lambda value: 241 + value // 19).convert("RGB")
        noise.close()
        draw = ImageDraw.Draw(image)
        for index, text in enumerate(lines):
            draw.text((120, 150 + index * 120), text, font=font, fill=(20, 20, 20))
        for row in range(11):
            y = 1250 + row * 155
            draw.line((120, y, 2350, y), fill=(50, 50, 50), width=4)
            draw.text((150, y + 25), f"Activity {row + 1}: sampling, ethics and reporting", font=font, fill=(20, 20, 20))
        for x in [120, 1500, 1900, 2350]:
            draw.line((x, 1250, x, 2800), fill=(50, 50, 50), width=4)
        draw.text((120, 3200), f"Synthetic scanned page {page} - no real signatures", font=font, fill=(20, 20, 20))
        buffer = BytesIO()
        image.save(buffer, format="JPEG", quality=88)
        image.close()
        pdf.drawImage(ImageReader(BytesIO(buffer.getvalue())), 0, 0, 612, 792)
        pdf.showPage()
    pdf.save()
    assert path.stat().st_size <= 25 * MIB


def docx(path, target_xml_bytes):
    ns = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"
    prefix = f'<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="{ns}"><w:body>'
    suffix = "</w:body></w:document>"
    paragraph = "<w:p><w:r><w:t>" + escape(PROSE * 5) + "</w:t></w:r></w:p>"
    end = "<w:p><w:r><w:t>CAPACITY-DOCX-END</w:t></w:r></w:p>"
    count = (target_xml_bytes - len(prefix) - len(suffix) - len(end)) // len(paragraph)
    padding = target_xml_bytes - len(prefix) - len(suffix) - len(end) - count * len(paragraph)
    document = prefix + paragraph * count + end + " " * padding + suffix
    assert len(document.encode()) == target_xml_bytes
    content_types = ('<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                     '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                     '<Default Extension="xml" ContentType="application/xml"/>'
                     '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>')
    rels = ('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>')
    with ZipFile(path, "w", ZIP_DEFLATED) as archive:
        for name, text in {"[Content_Types].xml": content_types, "_rels/.rels": rels, "word/document.xml": document}.items():
            info = ZipInfo(name, date_time=(2026, 1, 1, 0, 0, 0))
            info.compress_type = ZIP_DEFLATED
            archive.writestr(info, text)
    assert path.stat().st_size <= 25 * MIB
    return count + 1


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("output_directory", type=Path)
    output = parser.parse_args().output_directory.resolve()
    output.mkdir(parents=True, exist_ok=True)
    fixtures = {}

    def register(key, filename, **metadata):
        path = output / filename
        fixtures[key] = {"path": str(path), "filename": filename, "bytes": path.stat().st_size,
                         "sha256": hashlib.sha256(path.read_bytes()).hexdigest(), **metadata}

    image_paths = []
    for index in range(1, 13):
        filename = f"report-{index:02d}.jpg"
        jpeg(output / filename, (2400, 1600), index, report=True)
        register(f"report_image_{index:02d}", filename, width=2400, height=1600)
        image_paths.append(filename)
    report_bytes = sum(fixtures[f"report_image_{index:02d}"]["bytes"] for index in range(1, 13))
    assert 4 * MIB <= report_bytes <= 6 * MIB
    assert len({fixtures[f"report_image_{index:02d}"]["sha256"] for index in range(1, 13)}) == 12
    for key, size in [("image_12mp", (4000, 3000)), ("image_20mp", (5000, 4000)), ("image_guard", (5001, 4000))]:
        jpeg(output / f"{key}.jpg", size, 100)
        register(key, f"{key}.jpg", width=size[0], height=size[1])
    for key, pages, dense, manual in [("pdf_200", 200, False, False), ("pdf_dense_200", 200, True, False), ("manual_1000", 1000, False, True)]:
        text_pdf(output / f"{key}.pdf", pages, dense, manual)
        register(key, f"{key}.pdf", pages=pages)
    lines = work_plan(output / "work_plan.pdf")
    register("work_plan", "work_plan.pdf", pages=1)
    scanned_form(output / "scan_2pages.pdf", lines)
    register("scan_2pages", "scan_2pages.pdf", pages=2, raster_width=2480, raster_height=3508)
    for key, target in [("docx_5m", 5 * MIB), ("docx_12m", 12 * MIB - 1024)]:
        count = docx(output / f"{key}.docx", target)
        register(key, f"{key}.docx", selected_xml_bytes=target, paragraphs=count)
    for key, count, length in [("ocr_pages", 1000, 10 * 1024), ("ocr_long_paragraph", 1, MIB)]:
        pages = []
        for page in range(1, count + 1):
            text = (PROSE * (length // len(PROSE) + 1))[:length]
            pages.append({"number": page, "text": f"{page}. Research Policy\n\n{text}\n\nCAPACITY-OCR-END-{page}"})
        path = output / f"{key}.json"
        path.write_text(json.dumps({"pages": pages}, separators=(",", ":")), encoding="utf-8")
        register(key, path.name, pages=count, paragraph_characters=length)
    manifest = {"schema_version": 1, "fixtures": fixtures, "image_paths": image_paths,
                "report_image_bytes": report_bytes, "project_title": TITLE}
    (output / "manifest.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"manifest": str(output / "manifest.json"), "fixtures": len(fixtures), "report_image_bytes": report_bytes}))


if __name__ == "__main__":
    main()
