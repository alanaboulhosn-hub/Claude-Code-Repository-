# dump: xlsx -> state.json (values + formulas of the tabs the script uses); apply: changes.json -> new xlsx
import sys, json, datetime, openpyxl
TABS = ['Inventory', 'Inventory System', 'Stock Log', 'Daily Sales']
def enc(v):
    if isinstance(v, datetime.datetime): return {'$date': v.strftime('%Y-%m-%dT%H:%M')}
    if isinstance(v, datetime.date): return {'$date': v.strftime('%Y-%m-%dT00:00')}
    if v is None or isinstance(v, (int, float, str, bool)): return v
    return str(v)
if sys.argv[1] == 'dump':
    f = openpyxl.load_workbook(sys.argv[2]); v = openpyxl.load_workbook(sys.argv[2], data_only=True)
    out = {}
    for t in TABS:
        if t not in f.sheetnames: continue
        cells = {}
        for row in f[t].iter_rows():
            for c in row:
                fv = c.value; vv = v[t][c.coordinate].value
                if fv is None and vv is None: continue
                form = fv if isinstance(fv, str) and fv.startswith('=') else (getattr(fv, 'text', None) and '{array}' or '')
                cells[c.coordinate] = [enc(vv), form]
        out[t] = cells
    json.dump(out, open(sys.argv[3], 'w'), ensure_ascii=False)
else:
    wb = openpyxl.load_workbook(sys.argv[2]); ch = json.load(open(sys.argv[3]))
    for t, cells in ch.items():
        ws = wb[t] if t in wb.sheetnames else wb.create_sheet(t)
        for coord, (val, form) in cells.items():
            if form: ws[coord] = form
            elif isinstance(val, dict) and '$date' in val: ws[coord] = datetime.datetime.strptime(val['$date'], '%Y-%m-%dT%H:%M')
            else: ws[coord] = None if val == '' else val
    wb.save(sys.argv[4])
