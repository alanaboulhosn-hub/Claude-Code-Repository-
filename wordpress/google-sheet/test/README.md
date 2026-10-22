Offline test of the shop snippet and the sheet script (no live changes). In a scratch folder: copy these files, the
snippet as snippet.php, the .gs as code.gs with PASTE_KEY_HERE replaced by 40 "k"s, an orders.json (wc/v3 orders plus
edge.json) and the FIKA sheet exported as xlsx. Then:
- php harness.php orders.json FROM TO  -> the rows the shop would answer
- python3 sheet.py dump fika.xlsx st.json; node run.js st.json ch.json "setup()" 2026-10-23T07:00:00Z orders.json;
  python3 sheet.py apply fika.xlsx ch.json out.xlsx; ./recalc.sh out.xlsx outr.xlsx (LibreOffice recalculates)
LibreOffice can't evaluate DAYS(), lower-case minifs, and gives 0 for the Stock Log SUMIFS in Inventory System AZ:BB
even on the untouched sheet; check those by hand.
