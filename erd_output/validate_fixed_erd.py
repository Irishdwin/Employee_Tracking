from pathlib import Path
import xml.etree.ElementTree as E
import json,math
OUT=Path(__file__).resolve().parent
p=OUT/'Onazul_ERD_RELATIONSHIPS_FIXED.drawio'; tree=E.parse(p); root=tree.find('.//root'); cs=list(root); ids={c.get('id'):c for c in cs}
original=E.parse(OUT/'Onazul_ERD_CONNECTED.drawio').find('.//root')
def rect(c):
 g=c.find('mxGeometry');return tuple(float(g.get(k,'0')) for k in ('x','y','width','height'))
tables={c.get('id'):rect(c) for c in cs if 'shape=table;' in c.get('style','')}
def parent(id):
 while id not in tables:id=ids[id].get('parent')
 return id
def endpoint(c,prefix):
 id=c.get('source' if prefix=='exit' else 'target');x,y,w,h=rect(ids[id])
 if id not in tables:
  px,py,_,_=tables[parent(id)];x+=px;y+=py
 style=dict(s.split('=',1) for s in c.get('style','').split(';') if '=' in s)
 return (x+float(style[prefix+'X'])*w,y+float(style[prefix+'Y'])*h)
def route(c):
 arr=c.find("mxGeometry/Array[@as='points']")
 return [endpoint(c,'exit')]+[(float(q.get('x','0')),float(q.get('y','0'))) for q in arr]+[endpoint(c,'entry')]
def hit(a,b,r):
 x,y,w,h=r
 if a[0]==b[0]:return x<a[0]<x+w and min(a[1],b[1])<y+h and max(a[1],b[1])>y
 if a[1]==b[1]:return y<a[1]<y+h and min(a[0],b[0])<x+w and max(a[0],b[0])>x
 raise AssertionError('Non-orthogonal segment')
def overlap(a,b):
 x,y,w,h=a;X,Y,W,H=b;return x<X+W and X<x+w and y<Y+H and Y<y+h
# Vertex/attribute cells, including every original row label, are preserved exactly.
for old in original:
 if old.get('edge')=='1' or old.get('auditLegend')=='1':continue
 assert E.tostring(old)==E.tostring(ids[old.get('id')]),old.get('id')
edges=[c for c in cs if c.get('edge')=='1'];assert len(edges)==159
identities=[c.get('schemaRelationship') or c.get('logicalRelationship') for c in edges];assert len(identities)==len(set(identities))
intersections=[]; label_boxes=[]; hidden_labels=[]
for c in edges:
 pts=route(c);assert len(pts)>=2 and pts[0]!=pts[-1]
 for a,b in zip(pts,pts[1:]):
  assert a!=b and (a[0]==b[0] or a[1]==b[1])
  for id,r in tables.items():
   if hit(a,b,r):intersections.append((c.get('id'),id))
 label=c.get('value','')
 if label:
  widths=7*len(label)+16;height=20
  lengths=[abs(b[0]-a[0])+abs(b[1]-a[1]) for a,b in zip(pts,pts[1:])];total=sum(lengths);distance=0;candidates=[]
  for k,(a,b) in enumerate(zip(pts,pts[1:])):
   length=lengths[k]
   for fraction in (0.5,0.25,0.75):
    x=a[0]+(b[0]-a[0])*fraction;y=a[1]+(b[1]-a[1])*fraction
    for ox,oy in ((0,0),(0,-15),(0,15),(0,-30),(0,30),(0,-45),(0,45)):
     box=(x+ox-widths/2,y+oy-height/2,widths,height)
     if any(overlap(box,r) for r in tables.values()):continue
     occupied=sum(overlap(box,oldbox) for oldbox in label_boxes)
     score=occupied*10000+abs(ox)+abs(oy)+1/(length+1)
     candidates.append((score,distance+length*fraction,x+ox,y+oy,ox,oy,box))
   distance+=length
  if not candidates:
   hidden_labels.append({'connector':c.get('id'),'role':label})
   c.set('relationshipRole',label);c.set('value','')
   continue
  _,d,x,y,ox,oy,box=min(candidates);label_boxes.append(box)
  c.set('labelX',str(x));c.set('labelY',str(y));g=c.find('mxGeometry');g.set('x',str(2*d/total-1));g.set('y','0')
  E.SubElement(g,'mxPoint',x=str(ox),y=str(oy),attrib={'as':'offset'})
assert not intersections,intersections
legend=next(c for c in cs if c.get('auditLegend')=='1');legend.find('mxGeometry').set('y',str(min(r[1] for r in tables.values())-390))
tree.write(p,encoding='utf-8',xml_declaration=True);E.parse(p)
audit=json.loads((OUT/'relationship_fix_audit.json').read_text());assert len([c for c in edges if c.get('relationshipType')=='physical'])==137
audit['summary']['independent_table_intersections']=0;audit['summary']['label_table_intersections']=0;audit['summary']['unchanged_vertex_cells_verified']=True
audit['summary']['labels_kept_in_metadata_to_avoid_text_overlap']=hidden_labels
(OUT/'relationship_fix_audit.json').write_text(json.dumps(audit,indent=2),encoding='utf-8')
report=OUT/'ERD_RELATIONSHIPS_FIXED_REPORT.md';report.write_text(report.read_text(encoding='utf-8')+'\n\nIndependent XML round-trip validation: all original table/attribute cells unchanged; 159 unique relationship identities; all source/target endpoints resolve; every final segment is orthogonal and avoids every table interior (including own source/target); 0 table intersections. All visible relationship label boxes independently placed outside table bounds. '+str(len(hidden_labels))+' labels retained in relationship metadata instead of covering table text. No tables moved. The separate legacy address hierarchy and PSGC self-hierarchy remain without forced links to unrelated production modules.\n',encoding='utf-8')
print('Independent validation passed: 159 relationships / 0 table intersections / 0 label-table intersections / vertices unchanged.')
