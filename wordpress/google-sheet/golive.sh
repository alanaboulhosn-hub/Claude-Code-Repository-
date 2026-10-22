#!/bin/bash
# Go live: daily candy sales. Run only after the owner says "go live" and FIKA_DS_MIXES holds the mix contents.
# 1 installs the snippet (POST, once), 2 reads the key, 3 checks the link against the orders API, 4 writes the
# owner's ready-to-paste script, 5 checks shop pages still load. Prints no key.
set -e
cd "${WORK:?set WORK to a scratch folder}"
R=/home/user/Claude-Code-Repository-
C="curl -sS -c cj.txt -b cj.txt -L -A Mozilla/5.0 -u $WP_USER:$WP_APP_PASSWORD"
B=https://swedishfikalb.com/wp-json
php -l $R/wordpress/snippets/fika-daily-sales.php
grep -q "define( 'FIKA_DS_MIXES', array() )" $R/wordpress/snippets/fika-daily-sales.php && { echo "STOP: Ready Mix contents not filled in"; exit 1; }
# 1
ID=$($C "$B/code-snippets/v1/snippets?per_page=100" | python3 -c "import json,sys;print(next((s['id'] for s in json.load(sys.stdin) if s['name']=='Fika: daily candy sales for the FIKA sheet'),''))")
python3 -c "
import json,re
src=open('$R/wordpress/snippets/fika-daily-sales.php').read()
json.dump({'name':'Fika: daily candy sales for the FIKA sheet','code':re.sub(r'^<\?php\s*','',src),'scope':'global','active':True,'priority':10},open('ds-snippet.json','w'))"
$C -H 'Content-Type: application/json' -X POST --data-binary @ds-snippet.json "$B/code-snippets/v1/snippets${ID:+/$ID}" \
  | python3 -c "import json,sys;d=json.load(sys.stdin);print('snippet',d.get('id'),'active',d.get('active'),'error',d.get('code_error'))"
# 2
KEY=$($C "$B/fika/v1/daily-sales-key" | python3 -c "import json,sys;print(json.load(sys.stdin)['key'])")
[ ${#KEY} -eq 40 ] || { echo "STOP: no key"; exit 1; }
# 3: yesterday and the last 7 days, compared with the orders API
Y=$(TZ=Asia/Beirut date -d yesterday +%F); W=$(TZ=Asia/Beirut date -d '7 days ago' +%F)
curl -sS -A "Mozilla/5.0 (compatible; Google-Apps-Script)" -H "X-Fika-Key: $KEY" "$B/fika/v1/daily-sales?from=$W&to=$Y" -o ds-live.json -w "link: HTTP %{http_code}\n"
curl -sS -o /dev/null -w "link without key: HTTP %{http_code}\n" "$B/fika/v1/daily-sales?from=$W&to=$Y"
$C "$B/wc/v3/orders?per_page=100&after=${W}T00:00:00&before=${Y}T23:59:59&status=processing,on-hold,completed&_fields=id,date_created,customer_note,line_items" -o ds-orders.json
python3 -c "
import json
rows=json.load(open('ds-live.json'))['rows']; o=[x for x in json.load(open('ds-orders.json')) if 'TEST ORDER' not in (x['customer_note'] or '')]
print('link: %d rows, %.2f kg, days %s' % (len(rows), sum(r['kg'] for r in rows), sorted({r['date'] for r in rows})))
print('orders API: %d orders in the same days' % len(o))
print('not on the sheet:', [r['candy'] for r in rows if 'not on the sheet' in r['candy'] or r['candy'].startswith('Ready Mix')] or 'none')"
# 4
sed "s/PASTE_KEY_HERE/$KEY/" $R/wordpress/google-sheet/fika-daily-sales.gs > fika-sheet-script.gs
grep -c "$KEY" fika-sheet-script.gs >/dev/null && echo "owner script written: $(pwd)/fika-sheet-script.gs"
# 5
for u in / /mix-your-own/ /ready-mix/ /checkout/; do curl -sS -o /dev/null -c cj.txt -b cj.txt -L -A "Mozilla/5.0" -w "page $u: %{http_code}\n" "https://swedishfikalb.com$u"; done
