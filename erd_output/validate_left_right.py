from pathlib import Path
import xml.etree.ElementTree as E
import json,math
OUT=Path(__file__).resolve().parent
p=OUT/'Onazul_ERD_LEFT_RIGHT_ONLY.drawio'; tree=E.parse(p); root=tree.find('.//root'); cs=list(root); ids={c.get('id'):c for c in cs}
original=E.parse(OUT/'Onazul_ERD_RELATIONSHIPS_FIXED.drawio').find('.//root')
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
 return (round(x+float(style[prefix+'X'])*w,6),round(y+float(style[prefix+'Y'])*h,6))
def route(c):
 arr=c.find("mxGeometry/Array[@as='points']")
 return [endpoint(c,'exit')]+[(round(float(q.get('x','0')),6),round(float(q.get('y','0')),6)) for q in arr]+[endpoint(c,'entry')]
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
intersections=[]; label_boxes=[]; hidden_labels=[]; sides_after={'left':0,'right':0,'top':0,'bottom':0}; occupied_ports=set()
for c in edges:
 pts=route(c);assert len(pts)>=2 and pts[0]!=pts[-1]
 sm=dict(v.split('=',1) for v in c.get('style','').split(';') if '=' in v)
 for prefix,attr in [('exit','source'),('entry','target')]:
  cell=ids[c.get(attr)]; assert 'shape=tableRow;' in cell.get('style',''),c.get('id')
  fx=float(sm[prefix+'X']);fy=float(sm[prefix+'Y']);assert fx in (0,1) and 0<fy<1,c.get('id')
  side='left' if fx==0 else 'right';sides_after[side]+=1
  identity=(cell.get('id'),side,round(fy,8));assert identity not in occupied_ports,identity;occupied_ports.add(identity)
  columns=sorted([v for v in cs if v.get('parent')==cell.get('id')],key=lambda v:rect(v)[0]);attribute=columns[1].get('value')
  expected=c.get('parentColumn') if attr=='source' else c.get('childColumn');assert attribute==expected,(attribute,expected)
 assert pts[0][1]==pts[1][1] and pts[-1][1]==pts[-2][1],c.get('id')
 assert (pts[1][0]-pts[0][0])*(1 if float(sm['exitX'])==1 else -1)>0
 assert (pts[-2][0]-pts[-1][0])*(1 if float(sm['entryX'])==1 else -1)>0

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
  c.set('labelX',str(x));c.set('labelY',str(y));g=c.find('mxGeometry');
  for previous_offset in list(g):
   if previous_offset.get('as')=='offset':g.remove(previous_offset)
  g.set('x',str(2*d/total-1));g.set('y','0')
  E.SubElement(g,'mxPoint',x=str(ox),y=str(oy),attrib={'as':'offset'})
assert not intersections,intersections
legend=next(c for c in cs if c.get('auditLegend')=='1');legend.find('mxGeometry').set('y',str(min(r[1] for r in tables.values())-390))
tree.write(p,encoding='utf-8',xml_declaration=True);E.parse(p)
audit=json.loads((OUT/'left_right_audit.json').read_text());assert len([c for c in edges if c.get('relationshipType')=='physical'])==137
audit['summary']['independent_table_intersections']=0;audit['summary']['label_table_intersections']=0;audit['summary']['unchanged_vertex_cells_verified']=True
audit['summary']['labels_kept_in_metadata_to_avoid_text_overlap']=hidden_labels
def sidecounts(filename):
 counts={'left':0,'right':0,'top':0,'bottom':0,'unknown':0}
 for edge in E.parse(OUT/filename).findall('.//mxCell'):
  if edge.get('edge')!='1':continue
  style=dict(v.split('=',1) for v in edge.get('style','').split(';') if '=' in v)
  for prefix in ('exit','entry'):
   x=float(style.get(prefix+'X',1 if prefix=='exit' else 0));y=float(style.get(prefix+'Y',0.5))
   side='left' if x==0 else 'right' if x==1 else 'top' if y==0 else 'bottom' if y==1 else 'unknown'
   counts[side]+=1
 return counts
before=sidecounts('Onazul_ERD_RELATIONSHIPS_FIXED.drawio'); connected=sidecounts('Onazul_ERD_CONNECTED.drawio')
audit['summary']['endpoint_sides_before_cleaned_input']=before;audit['summary']['endpoint_sides_original_connected']=connected;audit['summary']['endpoint_sides_after']=sides_after
audit['summary']['connectors_rerouted']=len(edges);audit['summary']['all_endpoints_exact_attribute_rows']=True;audit['summary']['duplicate_boundary_ports']=0
legend.set('value',legend.get('value').replace('RELATIONSHIP LEGEND','RELATIONSHIP LEGEND — LEFT/RIGHT ONLY'))
tree.write(p,encoding='utf-8',xml_declaration=True)

(OUT/'left_right_audit.json').write_text(json.dumps(audit,indent=2),encoding='utf-8')
report=OUT/'ERD_LEFT_RIGHT_REPORT.md';report.write_text(report.read_text(encoding='utf-8')+'\n\nIndependent XML round-trip validation: all original table/attribute cells unchanged; 159 unique relationship identities; all source/target endpoints resolve; every final segment is orthogonal and avoids every table interior (including own source/target); 0 table intersections. All visible relationship label boxes independently placed outside table bounds. '+str(len(hidden_labels))+' labels retained in relationship metadata instead of covering table text. No tables moved. The separate legacy address hierarchy and PSGC self-hierarchy remain without forced links to unrelated production modules.\n',encoding='utf-8')
report.write_text(report.read_text(encoding='utf-8')+'\n\n## Left/right endpoint validation\nRouting input: Onazul_ERD_RELATIONSHIPS_FIXED.drawio (159 cleaned relationships). Original Onazul_ERD_CONNECTED.drawio was also inspected separately; its seven removed lines were not restored.\nEndpoints before (cleaned input): '+str(before)+'.\nEndpoints before (original connected input, 166 lines): '+str(connected)+'.\nEndpoints after: '+str(sides_after)+'.\n159/159 connectors rerouted. Every endpoint attached to the exact parent PK/UK and child reference row, with unique nearby boundary ports. First/last segments horizontal; departure is outward; approach is from outside. Top-side endpoints: 0. Bottom-side endpoints: 0. Table interior intersections: 0. Duplicate boundary ports: 0. Tables moved: 0.\n',encoding='utf-8')
print('Left/right validation passed: '+str(sides_after)+'; 159 exact attribute-row relationships, zero table/text intersections, zero repeated boundary ports.')
