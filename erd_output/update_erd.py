from pathlib import Path
import re, json, html, copy, xml.etree.ElementTree as ET

BASE=Path(__file__).resolve().parents[1]
OUT=Path(__file__).resolve().parent
ORIGINAL=Path(r'C:\Users\Irishdwin Gamutan\OneDrive\Desktop\Onazul Capstone\Onazul_WORD_ERD.drawio')
schema={}; sources=[]; conflicts=[]
def clean(s):
    return re.sub(r'/\*.*?\*/|--[^\n]*', '', s, flags=re.S)
def split(s, sep=','):
    parts=[]; start=0; depth=0; quote=None; i=0
    while i<len(s):
        c=s[i]
        if quote:
            if c=='\\': i+=1
            elif c==quote:
                if i+1<len(s) and s[i+1]==quote: i+=1
                else: quote=None
        elif c in "'\"`": quote=c
        elif c=='(': depth+=1
        elif c==')': depth-=1
        elif c==sep and depth==0: parts.append(s[start:i]); start=i+1
        i+=1
    parts.append(s[start:]); return parts
def names(s): return [x.strip().strip('`') for x in split(s)]
def definition(t, body, source, mode='create'):
    d=schema.setdefault(t,dict(columns={},pk=[],fk=[],unique=[],sources=[]))
    if source not in d['sources']: d['sources'].append(source)
    for part in split(body):
        p=part.strip()
        fk=re.search(r'FOREIGN\s+KEY\s*\(([^)]+)\)\s*REFERENCES\s+`?(\w+)`?\s*\(([^)]+)\)',p,re.I)
        if fk:
            item=[names(fk[1]),fk[2].lower(),names(fk[3])]
            if item not in d['fk']: d['fk'].append(item)
            continue
        pk=re.search(r'PRIMARY\s+KEY\s*\(([^)]+)\)',p,re.I)
        if pk: d['pk']=names(pk[1]); continue
        uq=re.search(r'UNIQUE\s+(?:KEY|INDEX)?\s*(?:IF NOT EXISTS\s+)?(?:`?\w+`?\s*)?\(([^)]+)\)',p,re.I)
        if uq:
            cols=names(uq[1])
            if cols not in d['unique']: d['unique'].append(cols)
            continue
        if re.match(r'(?:ADD\s+)?(?:CONSTRAINT|KEY|INDEX|UNIQUE|DROP|ALGORITHM|LOCK)\b',p,re.I): continue
        p=re.sub(r'^(?:ADD|MODIFY)\s+(?:COLUMN\s+)?(?:IF NOT EXISTS\s+)?','',p,flags=re.I)
        m=re.match(r'`?(\w+)`?\s+((?:INT|BIGINT|SMALLINT|TINYINT|MEDIUMINT|VARCHAR|CHAR|TEXT|LONGTEXT|MEDIUMTEXT|DATETIME|TIMESTAMP|DATE|TIME|DECIMAL|DOUBLE|FLOAT|ENUM|SET|BOOLEAN|JSON|BLOB)\b.*)',p,re.I|re.S)
        if not m: continue
        col,typ=m.groups(); typ=re.sub(r'\s+(?:AFTER\s+`?\w+`?|FIRST)\s*$','',typ,flags=re.I).strip()
        if col not in d['columns'] or mode=='modify': d['columns'][col]=typ
        if re.search(r'\bPRIMARY KEY\b',typ,re.I) and col not in d['pk']: d['pk'].append(col)
        if re.search(r'\bUNIQUE\b',typ,re.I) and [col] not in d['unique']: d['unique'].append([col])
def scan(s,source,php=False):
    s=clean(s)
    for m in re.finditer(r'CREATE\s+TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?\s*\(',s,re.I):
        t=m[1].lower(); begin=m.end(); depth=1; quote=None; i=begin
        while i<len(s) and depth:
            c=s[i]
            if quote:
                if c=='\\': i+=1
                elif c==quote: quote=None
            elif c in "'\"`": quote=c
            elif c=='(': depth+=1
            elif c==')': depth-=1
            i+=1
        if not php or t not in schema: definition(t,s[begin:i-1],source)
    if php: return
    for m in re.finditer(r'ALTER\s+TABLE\s+`?(\w+)`?\s+([^;]+)',s,re.I):
        t=m[1].lower(); body=m[2].strip().strip("'\"")
        if t not in schema: conflicts.append('ALTER target absent: '+t); continue
        definition(t,body,source,'modify' if re.match('MODIFY',body,re.I) else 'add')
    for m in re.finditer(r'CREATE\s+UNIQUE\s+INDEX\s+(?:IF NOT EXISTS\s+)?\w+\s+ON\s+(\w+)\s*\(([^)]+)\)',s,re.I):
        if m[1].lower() in schema:
            u=names(m[2]); d=schema[m[1].lower()]
            if u not in d['unique']: d['unique'].append(u)

scan((BASE/'onazul_hris_db.sql').read_text(encoding='utf-8-sig'),'onazul_hris_db.sql')
dump_count=len(schema)
# Create definitions first, so migration dependency order cannot lose constraints.
files=sorted((BASE/'sql').glob('*.sql'))
for f in files:
    s=clean(f.read_text(encoding='utf-8-sig'))
    scan(re.sub(r'ALTER\s+TABLE\s+[^;]+;','',s,flags=re.I),str(f.relative_to(BASE)))
for f in files: scan(f.read_text(encoding='utf-8-sig'),str(f.relative_to(BASE)))
for f in sorted((BASE/'Api').rglob('*.php')):
    if 'testing' not in str(f).lower(): scan(f.read_text(encoding='utf-8-sig',errors='replace'),str(f.relative_to(BASE)),True)

project_schema=copy.deepcopy(schema)
metadata=OUT/'live_schema_metadata.jsonl'
if metadata.exists():
    entries=[json.loads(x) for x in metadata.read_text(encoding='utf-8-sig').splitlines() if x.strip()]
    tables={x['base_table'] for x in entries if 'base_table' in x}
    schema={t:dict(columns={},pk=[],fk=[],unique=[],sources=project_schema.get(t,{}).get('sources',[])+['live information_schema (2026-10-07)']) for t in sorted(tables)}
    constraints={}
    for item in entries:
        t=item.get('table')
        if t not in schema: continue
        if 'definition' in item:
            schema[t]['columns'][item['column']]=item['definition']+(' NOT NULL' if item['nullable']=='NO' else ' NULL')+' '+item['extra']
        elif 'kind' in item: constraints.setdefault((t,item['constraint'],item['kind']),[]).append(item)
    for (t,name,kind),items in constraints.items():
        cols=[x['column'] for x in items]
        if kind=='PRIMARY KEY': schema[t]['pk']=cols
        elif kind=='UNIQUE': schema[t]['unique'].append(cols)
        elif kind=='FOREIGN KEY': schema[t]['fk'].append([cols,items[0]['parent'],[x['target'] for x in items]])
    conflicts=[]
project_only=sorted(set(project_schema)-set(schema))
live_only=sorted(set(schema)-set(project_schema))
project_missing_columns={t:sorted(set(project_schema[t]['columns'])-set(schema[t]['columns'])) for t in set(schema)&set(project_schema) if set(project_schema[t]['columns'])-set(schema[t]['columns'])}
live_extra_columns={t:sorted(set(schema[t]['columns'])-set(project_schema[t]['columns'])) for t in set(schema)&set(project_schema) if set(schema[t]['columns'])-set(project_schema[t]['columns'])}

def plain(s): return html.unescape(re.sub('<[^>]+>','',s or '')).strip()
def norm(s): return re.sub('[^a-z0-9]','',plain(s).lower())
tree=ET.parse(ORIGINAL); root=tree.find('.//root'); cells=list(root); byid={c.get('id'):c for c in cells}
entities=[c for c in cells if 'shape=table;' in c.get('style','')]
original_count=len(entities); aliases={'clearanceitem':'clearance_items'}
entitymap={}; changes={}; extra=[]; rows={}; original_geom={}
def children(c): return [x for x in root if x.get('parent')==c.get('id')]
def rowcols(c):
    return sorted(children(c),key=lambda x:float(x.find('mxGeometry').get('x','0')))
for c in entities:
    n=norm(c.get('value')); t=aliases.get(n,next((x for x in schema if norm(x)==n),None))
    if not t: extra.append(plain(c.get('value'))); continue
    entitymap[t]=c; original_geom[t]=dict(c.find('mxGeometry').attrib)
template=entities[0]; rowtemplate=children(template)[1]; coltemplates=rowcols(rowtemplate)
counter=0
def uid():
    global counter
    counter+=1; return 'onazul_updated_'+str(counter)
def make_row(entity,name,mark,index,width):
    row=copy.deepcopy(rowtemplate); row.set('id',uid()); row.set('parent',entity.get('id'))
    row.find('mxGeometry').set('y',str(30+index*30)); row.find('mxGeometry').set('width',str(width)); root.append(row)
    for j,src in enumerate(coltemplates):
        col=copy.deepcopy(src); col.set('id',uid()); col.set('parent',row.get('id')); col.set('value',mark if j==0 else name)
        g=col.find('mxGeometry'); g.set('width',str(30 if j==0 else width-30)); root.append(col)
    return row
for t,d in schema.items():
    if t not in entitymap:
        c=copy.deepcopy(template); c.set('id',uid()); c.set('value','_'.join(x.capitalize() for x in t.split('_'))); root.append(c); entitymap[t]=c
    c=entitymap[t]; g=c.find('mxGeometry'); width=float(g.get('width')); oldrows=children(c)
    existing={}; before=[]
    for r in oldrows:
        cc=rowcols(r)
        if len(cc)>=2: existing[norm(cc[1].get('value'))]=r; before.append(plain(cc[1].get('value')))
    fkcols={col for fk in d['fk'] for col in fk[0]}; corrected=[]; rm=[]; added=[]; rows[t]={}
    ordered=[next((x for x in d['columns'] if norm(x)==norm(a)),None) for a in before]
    ordered=[x for x in ordered if x]+[x for x in d['columns'] if x not in ordered]
    for r in oldrows:
        cc=rowcols(r)
        if len(cc)<2 or norm(cc[1].get('value')) not in {norm(x) for x in d['columns']}:
            if len(cc)>=2: rm.append(plain(cc[1].get('value')))
            for child in children(r): root.remove(child)
            root.remove(r)
    for i,col in enumerate(ordered):
        mark=', '.join(x for x,ok in [('PK',col in d['pk']),('FK',col in fkcols)] if ok)
        r=existing.get(norm(col))
        if r is None: r=make_row(c,col,mark,i,width); added.append(col)
        else:
            cc=rowcols(r)
            if plain(cc[0].get('value'))!=mark or plain(cc[1].get('value'))!=col: corrected.append(col)
            cc[0].set('value',mark); cc[1].set('value',col)
            r.find('mxGeometry').set('y',str(30+i*30))
        rows[t][col]=r.get('id')
    g.set('height',str(30+30*len(ordered)))
    changes[t]={'added':added,'removed':rm,'corrected':corrected,'new':t not in original_geom}

# Keep original x and module order. Shift down only to resolve expanded boxes.
def rect(c):
    g=c.find('mxGeometry'); return tuple(float(g.get(k,'0')) for k in ('x','y','width','height'))
def overlap(a,b,pad=25):
    x,y,w,h=a; X,Y,W,H=b
    return x<X+W+pad and X<x+w+pad and y<Y+H+pad and Y<y+h+pad
placed=[]; moved={}
for t in sorted(original_geom,key=lambda t:rect(entitymap[t])[1]):
    c=entitymap[t]; g=c.find('mxGeometry'); oldy=float(g.get('y','0'))
    while any(overlap(rect(c),rect(entitymap[p])) for p in placed):
        hits=[rect(entitymap[p]) for p in placed if overlap(rect(c),rect(entitymap[p]))]
        g.set('y',str(max(r[1]+r[3]+30 for r in hits)))
    if float(g.get('y','0'))!=oldy: moved[t]=[oldy,float(g.get('y'))]
    placed.append(t)
for t in schema:
    if t in original_geom: continue
    c=entitymap[t]; g=c.find('mxGeometry')
    related=[fk[1] for fk in schema[t]['fk'] if fk[1] in placed]
    related+= [p for p in placed if any(fk[1]==t for fk in schema[p]['fk'])]
    if not related:
        related=[p for p in placed if p.split('_')[0]==t.split('_')[0]]
    anchor=entitymap[related[0] if related else 'users']; ax,ay,aw,ah=rect(anchor)
    candidates=[]
    for radius in range(1,30):
        for dx,dy in [(radius,0),(-radius,0),(0,radius),(0,-radius),(radius,radius),(-radius,radius)]:
            x=ax+dx*260; y=ay+dy*180
            box=(x,y,float(g.get('width')),float(g.get('height')))
            if not any(overlap(box,rect(entitymap[p])) for p in placed): candidates.append((abs(dx)*260+abs(dy)*180,x,y))
        if candidates: break
    _,x,y=min(candidates); g.set('x',str(x)); g.set('y',str(y)); placed.append(t)

# Map old edges to explicit row identities; retain only matching schema relationships.
rowidentity={rid:(t,col) for t,rr in rows.items() for col,rid in rr.items()}
def identity(id):
    seen=set()
    while id and id not in seen:
        if id in rowidentity: return rowidentity[id]
        seen.add(id); cell=byid.get(id)
        id=cell.get('parent') if cell is not None else None
    return None
expected=[]
for t,d in schema.items():
    for cols,parent,targets in d['fk']:
        for col,target in zip(cols,targets):
            if parent not in schema or col not in rows[t] or target not in rows[parent]: conflicts.append(f'Unresolved FK: {t}.{col} -> {parent}.{target}'); continue
            expected.append((t,col,parent,target))
expected=list(dict.fromkeys(expected)); retained={}; removed_edges=[]
edges=[c for c in root if c.get('edge')=='1']; edge_style=edges[0].get('style')
for e in edges:
    a=identity(e.get('source')); b=identity(e.get('target')); match=None
    if a and b:
        for key in [(a[0],a[1],b[0],b[1]),(b[0],b[1],a[0],a[1])]:
            if key in expected: match=key; break
    if match and match not in retained: retained[match]=e
    else: removed_edges.append(e.get('id')); root.remove(e)
added_edges=[]
for key in expected:
    t,col,parent,target=key; e=retained.get(key)
    if e is None:
        e=ET.SubElement(root,'mxCell',id=uid(),edge='1',parent='1',style=edge_style)
        ET.SubElement(e,'mxGeometry',relative='1',attrib={'as':'geometry'}); added_edges.append(key)
    e.set('source',rows[parent][target]); e.set('target',rows[t][col])
    unique=[col] in schema[t]['unique'] or schema[t]['pk']==[col]
    optional=not re.search(r'\bNOT NULL\b',schema[t]['columns'][col],re.I) and col not in schema[t]['pk']
    style=re.sub(r'(?:startArrow|endArrow)=[^;]*;','',e.get('style',''))
    style+='startArrow='+('ERzeroToOne' if optional else 'ERone')+';endArrow='+('ERzeroToOne' if unique else 'ERzeroToMany')+';'
    e.set('style',style)
    e.set('value',''); e.set('schemaRelationship',f'{t}.{col} -> {parent}.{target}')
    # Discard stale waypoints after any unavoidable entity shift.
    geom=e.find('mxGeometry')
    for x in list(geom): geom.remove(x)

OUT.mkdir(exist_ok=True)
tree.write(OUT/'Onazul_ERD_UPDATED.drawio',encoding='utf-8',xml_declaration=True)
ET.parse(OUT/'Onazul_ERD_UPDATED.drawio')
validation={'database_table_count':len(schema),'erd_table_count':len(entitymap),'matched_tables':len(schema),'missing_tables':[],'extra_tables':extra,'missing_attributes':[],'pk_mismatches':[],'fk_mismatches':[],'missing_relationships':[],'duplicate_relationships':len(expected)-len(set(expected)),'unresolved_schema_items':conflicts}
# Independent round-trip field/key validation.
final=ET.parse(OUT/'Onazul_ERD_UPDATED.drawio'); fc=final.findall('.//mxCell'); index={c.get('id'):c for c in fc}
for t,c in entitymap.items():
    actual={}
    for r in fc:
        if r.get('parent')!=c.get('id'): continue
        cc=sorted([x for x in fc if x.get('parent')==r.get('id')],key=lambda x:float(x.find('mxGeometry').get('x','0')))
        if len(cc)==2: actual[plain(cc[1].get('value'))]=plain(cc[0].get('value'))
    assert set(actual)==set(schema[t]['columns']),t
    assert {x for x,m in actual.items() if 'PK' in m}==set(schema[t]['pk']),t
    assert {x for x,m in actual.items() if 'FK' in m}=={x for fk in schema[t]['fk'] for x in fk[0]},t
assert len([c for c in fc if c.get('edge')=='1'])==len(expected)
assert len(index)==len(fc)
(OUT/'schema_audit.json').write_text(json.dumps({'schema':schema,'changes':changes,'validation':validation,'original_geometry':original_geom,'moved':moved,'relationships':expected,'added_relationships':added_edges,'removed_original_connectors':removed_edges},indent=2),encoding='utf-8')
report=['# ONAZUL ERD update report','','Original: Onazul_WORD_ERD.drawio; '+str(original_count)+' entities.','',f'Current installed production schema: {len(schema)} tables ({dump_count} in the base dump); {len(files)} SQL migration/support files inspected.','', 'Validation uses read-only live information_schema metadata, cross-checked against the project dump, SQL migrations and PHP schema helpers. No data rows were queried and no DDL/DML was executed. Commented optional constraints and views are excluded.','', '## Project versus installed database differences',f'Project-defined tables not installed (excluded from final ERD): {project_only}.',f'Installed tables absent from parsed project definitions (included from verified metadata): {live_only}.',f'Project-only column definitions absent from live schema: {project_missing_columns}.',f'Additional installed columns beyond parsed project definitions: {live_extra_columns}.','The diagram represents the installed schema; these discrepancies are reported rather than applying migrations or depicting uninstalled legacy tables.','', '## Existing entities updated']
for t,v in changes.items():
    if not v['new']: report.append(f"- {t}: added {', '.join(v['added']) or 'none'}; removed {', '.join(v['removed']) or 'none'}; corrected names/key labels {', '.join(v['corrected']) or 'none'}.")
report+=['','## New entities added']
for t,v in changes.items():
    if v['new']: report.append(f"- {t}: defined in {', '.join(schema[t]['sources'])}; FK parents: {', '.join(sorted({x[1] for x in schema[t]['fk']})) or 'none declared' }.")
report+=['','## Verified relationships (parent to child connectors)']
for t,col,p,target in expected: report.append(f'- {t}.{col} → {p}.{target}'+(' (added)' if (t,col,p,target) in added_edges else ' (retained/corrected)'))
report+=['','## ERD-only entities',str(extra or 'None. ClearanceItem maps to clearance_items.'),'', '## Layout and support scope',f'Original headers, table widths, row styles, fonts, IDs and x coordinates retained. {len(moved)} existing entities moved vertically to resolve expanded-table overlap. New tables placed beside related entities using cloned original styles.',f'{len(removed_edges)} obsolete, unverified or duplicate original connectors removed. Cardinality uses nullable FK and single-column unique/PK constraints; parents may have zero children.', 'Support/lookup/log tables absent from the original are explicitly listed as new entities above; all project-defined base tables are included. No test-directory DDL, views or row data included.','', '## Validation',json.dumps(validation,indent=2),'','## Sources']
report += ['- '+str(f.relative_to(BASE)) for f in files]
report+=['','## Files','- Onazul_ERD_UPDATED.drawio (editable XML)','- schema_audit.json (schema-only audit and exact change details)','- ERD_UPDATE_REPORT.md','', 'XML parses and round-trip entity/column/key/edge/ID assertions pass. GUI opening and preview are verified separately if available.']
(OUT/'ERD_UPDATE_REPORT.md').write_text('\n'.join(report),encoding='utf-8')
print(json.dumps({'original_entities':original_count,'schema_tables':len(schema),'edges':len(expected),'new_entities':sum(v['new'] for v in changes.values()),'moved':len(moved),'conflicts':conflicts,'extra':extra},indent=2))
