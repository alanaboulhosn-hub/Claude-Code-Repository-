import csv,sys,json,collections,html,re
from datetime import datetime
from zoneinfo import ZoneInfo
src,prodf,out=sys.argv[1:4]
P={html.unescape(p['name']).lower():p for p in json.load(open(prodf))}
READY={'ready sweet & sour mix':'sweet & sour mix','ready sour mix':'sour mix','ready sweet mix':'sweet mix'}
def mapname(n):
    n=n.strip(); l=n.lower()
    m=re.match(r'^(ready .*?) \((500g|1kg)\)$',l)
    if m: return P[READY[m.group(1)]], (1 if m.group(2)=='500g' else 2)
    if l=='sweet mix': return P['sweet mix'],2   # sold at $25 = 1 kg
    return P[l],1
rows=list(csv.DictReader(open(src,encoding='utf-8-sig')))
orders=collections.OrderedDict()
for r in rows:
    o=orders.setdefault(r['Order Number'],{'h':None,'items':[]})
    if r['Email']: o['h']=r
    o['items'].append(r)
ST={'Fulfilled':'completed','Unfulfilled':'processing','Canceled':'cancelled'}
# owner's test orders, deleted from the shop on 2026-10-09: never import them again
SKIP={'1001','1004','1338','1435','1443'}
res=[]; kg=collections.Counter()
for num,o in orders.items():
    if num.lstrip('#') in SKIP: continue
    h=o['h']
    ts=int(datetime.strptime(h['Created'],'%b %d, %Y, %I:%M %p').replace(tzinfo=ZoneInfo('Asia/Beirut')).timestamp())
    items=[]
    for i in o['items']:
        p,mult=mapname(i['Product Names']); q=int(i['Quantity of Products'])*mult
        line=round(float(i['Product Price'])*int(i['Quantity of Products']),2)
        assert abs(line-float(p['price'])*q)<0.01, (num,i['Product Names'],line,p['price'],q)
        items.append({'id':p['id'],'qty':q,'line':line,'old_name':i['Product Names'].strip()})
        g=q*(500 if any(c['slug']=='ready-mix' for c in p['categories']) else 100)
        if ST[h['Order Status']]=='completed': kg[h['Email'].strip().lower()]+=g
    name=h['Billing Name'].strip().split(' ',1)
    res.append({'num':num.lstrip('#'),'status':ST[h['Order Status']],'ts':ts,'email':h['Email'].strip().lower(),
      'first':name[0],'last':name[1] if len(name)>1 else '','phone':h['Billing Phone'].strip(),
      'a1':h['Street address 1'].strip(),'a2':h['Street address 2'].strip(),'city':h['City'].strip(),
      'area':'BA' if h['Shipping Method'].startswith('Inside') else 'OB','ship':float(h['Shipping']),
      'sub':float(h['Subtotal']),'disc':float(h['Discount Amount'] or 0),'code':h['Discount Code'].strip(),'total':float(h['Total']),
      'pay':'Whish' if h['Payment Method']=='Whish' else 'cod','paid':h['Payment Status']=='Paid','items':items})
json.dump(res,open(out,'w'))
print(len(res),'orders built;', collections.Counter(r['status'] for r in res))
print('customers',len(set(r['email'] for r in res)),'| delivered kg total',sum(kg.values())/1000)
print('top by kg',[(e[:3]+'…',g/1000) for e,g in kg.most_common(5)])
print('kg buckets', collections.Counter(min(g//1500*1.5,15) for g in kg.values()))
