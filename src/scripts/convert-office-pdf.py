"""Export trusted application files through the isolated LibreOffice worker."""

import argparse
import sys
import time
from pathlib import Path

import uno
from com.sun.star.beans import PropertyValue
from com.sun.star.connection import NoConnectException
from com.sun.star.document.MacroExecMode import NEVER_EXECUTE
from com.sun.star.document.UpdateDocMode import NO_UPDATE


def properties(**values):
    result = []
    for name, value in values.items():
        item = PropertyValue()
        item.Name, item.Value = name, value
        result.append(item)
    return tuple(result)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('pipe')
    parser.add_argument('output')
    parser.add_argument('export_filter')
    parser.add_argument('sources', nargs='+')
    parser.add_argument('--connect-timeout', type=float, default=2)
    args = parser.parse_args()
    pipe, output, export_filter, sources = args.pipe, args.output, args.export_filter, args.sources
    directory = Path(output).resolve(strict=True)
    files = [Path(source).resolve(strict=True) for source in sources]
    if export_filter not in ("pdf", "pdf:writer_pdf_Export", "pdf:calc_pdf_Export"):
        raise ValueError("Unsupported PDF export filter")
    if any(source.suffix.lower() not in (".docx", ".xlsx") for source in files):
        raise ValueError("Unsupported Office document format")
    local = uno.getComponentContext()
    resolver = local.ServiceManager.createInstanceWithContext(
        "com.sun.star.bridge.UnoUrlResolver", local
    )
    deadline = time.monotonic() + args.connect_timeout
    while True:
        try:
            context = resolver.resolve(
                "uno:pipe,name=" + pipe + ";urp;StarOffice.ComponentContext"
            )
            break
        except NoConnectException:
            if time.monotonic() >= deadline:
                print("The isolated LibreOffice worker did not accept the connection.", file=sys.stderr)
                return 75
            time.sleep(0.05)
    desktop = context.ServiceManager.createInstanceWithContext(
        "com.sun.star.frame.Desktop", context
    )
    for source in files:
        document = desktop.loadComponentFromURL(
            uno.systemPathToFileUrl(str(source)),
            "_blank",
            0,
            properties(
                Hidden=True,
                ReadOnly=True,
                MacroExecutionMode=NEVER_EXECUTE,
                UpdateDocMode=NO_UPDATE,
            ),
        )
        if document is None:
            raise RuntimeError("The Office document could not be opened")
        try:
            filter_name = (
                "calc_pdf_Export" if source.suffix.lower() == ".xlsx" else "writer_pdf_Export"
            )
            if export_filter != "pdf":
                filter_name = export_filter.split(":", 1)[1]
            target = directory / (source.stem + ".pdf")
            document.storeToURL(
                uno.systemPathToFileUrl(str(target)),
                properties(FilterName=filter_name, Overwrite=True),
            )
        finally:
            document.close(True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
