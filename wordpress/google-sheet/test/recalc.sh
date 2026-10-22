#!/bin/sh
# $1 in.xlsx -> $2 out.xlsx, every formula recalculated by LibreOffice
d=$(mktemp -d); cp "$1" "$d/x.xlsx"
soffice --headless --calc --convert-to xlsx:"Calc MS Excel 2007 XML" --outdir "$d/o" "$d/x.xlsx" >/dev/null 2>&1
cp "$d/o/x.xlsx" "$2"
