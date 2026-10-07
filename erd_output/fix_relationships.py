from pathlib import Path
from collections import defaultdict,Counter
import xml.etree.ElementTree as E
import re,html,json,heapq,math,time

OUT=Path(__file__).resolve().parent
INPUT=OUT/'Onazul_ERD_CONNECTED.drawio'
OUTPUT=OUT/'Onazul_ERD_RELATIONSHIPS_FIXED.drawio'
tree=E.parse(INPUT); root=tree.find('.//root'); cells=list(root); byid={c.get('id'):c for c in cells}
def plain(s): return html.unescape(re.sub('<[^>]*>','',s or '')).strip()
def norm(s): return re.sub('[^a-z0-9]','',plain(s).lower())
def rect(c):
 g=c.find('mxGeometry'); return tuple(float(g.get(k,'0')) for k in ('x','y','width','height'))
meta=[json.loads(x) for x in (OUT/'relationship_fix_live_metadata.jsonl').read_text(encoding='utf-8-sig').splitlines() if x.strip()]
schema={r['base_table']:{'cols':{},'pk':[],'unique':[],'fk':[]} for r in meta if 'base_table' in r}
groups=defaultdict(list)
for r in meta:
 t=r.get('table')
 if t not in schema: continue
 if 'definition' in r: schema[t]['cols'][r['column']]=r
 elif 'kind' in r: groups[t,r['constraint'],r['kind']].append(r)
for (t,name,kind),rr in groups.items():
 cols=[r['column'] for r in rr]
 if kind=='PRIMARY KEY': schema[t]['pk']=cols; schema[t]['unique'].append(cols)
 elif kind=='UNIQUE': schema[t]['unique'].append(cols)
 elif kind=='FOREIGN KEY': schema[t]['fk'].append(dict(name=name,cols=cols,parent=rr[0]['parent'],targets=[r['target'] for r in rr]))
entities={}
for c in cells:
 if 'shape=table;' in c.get('style',''):
  name=norm(c.get('value')); t='clearance_items' if name=='clearanceitem' else next((t for t in schema if norm(t)==name),None)
  assert t,t
  assert t not in entities,t
  entities[t]=c
assert set(entities)==set(schema)
boxes={t:rect(c) for t,c in entities.items()}
entityid={c.get('id'):t for t,c in entities.items()}
rows={}; rowidentity={}
for t,c in entities.items():
 for r in cells:
  if r.get('parent')!=c.get('id'): continue
  cc=sorted([v for v in cells if v.get('parent')==r.get('id')],key=lambda v:rect(v)[0])
  if len(cc)==2:
   col=plain(cc[1].get('value')); rows[t,col]=r; rowidentity[r.get('id')]=(t,col)
 assert {col for tab,col in rows if tab==t}==set(schema[t]['cols']),t
def endpoint(id):
 while id not in rowidentity and id not in entityid:
  if id not in byid: return None
  id=byid[id].get('parent')
 return rowidentity.get(id,(entityid.get(id),None))
def stylemap(c): return dict(p.split('=',1) for p in c.get('style','').split(';') if '=' in p)
def absolute(id,style,prefix):
 pair=endpoint(id)
 if not pair: return None
 t,col=pair; x,y,w,h=boxes[t]
 if col is not None:
  rx,ry,rw,rh=rect(rows[t,col]); x+=rx; y+=ry; w=rw; h=rh
 return x+float(style.get(prefix+'X',1 if prefix=='exit' else 0))*w,y+float(style.get(prefix+'Y',0.5))*h
def pathof(c):
 sm=stylemap(c); a=absolute(c.get('source'),sm,'exit'); b=absolute(c.get('target'),sm,'entry')
 if a is None or b is None: return []
 pts=[a]; arr=c.find("mxGeometry/Array[@as='points']")
 if arr is not None: pts += [(float(v.get('x','0')),float(v.get('y','0'))) for v in arr if v.tag=='mxPoint']
 pts.append(b); return pts
def intersects(a,b,box):
 x,y,w,h=box; eps=1e-7
 # Open rectangle intersection, also handles the older occasional diagonal segment.
 dx=b[0]-a[0]; dy=b[1]-a[1]; low=0.; high=1.
 for origin,delta,lo,hi in [(a[0],dx,x+eps,x+w-eps),(a[1],dy,y+eps,y+h-eps)]:
  if abs(delta)<eps:
   if not(lo<origin<hi): return False
  else:
   l=(lo-origin)/delta; r=(hi-origin)/delta
   if l>r: l,r=r,l
   low=max(low,l); high=min(high,r)
   if low>=high: return False
 return high>low
def tablehits(paths):
 hits=[]
 for eid,(s,t,pts) in paths.items():
  for tab,box in boxes.items():
   if tab in (s,t): continue
   if any(intersects(a,b,box) for a,b in zip(pts,pts[1:])): hits.append((eid,tab))
 return hits
def crossings(paths):
 # Proper interior crossings. Shared endpoints and collinear overlaps counted separately.
 segs=[]
 for eid,(_,_,pts) in paths.items():
  for a,b in zip(pts,pts[1:]):
   if a!=b: segs.append((eid,a,b))
 events=set(); pairs=set(); overlaps=set()
 for i,(e,a,b) in enumerate(segs):
  for f,c,d in segs[i+1:]:
   if e==f: continue
   dx=b[0]-a[0]; dy=b[1]-a[1]; ex=d[0]-c[0]; ey=d[1]-c[1]; den=dx*ey-dy*ex
   if abs(den)>1e-7:
    u=((c[0]-a[0])*ey-(c[1]-a[1])*ex)/den; v=((c[0]-a[0])*dy-(c[1]-a[1])*dx)/den
    if 1e-7<u<1-1e-7 and 1e-7<v<1-1e-7:
     x=a[0]+u*dx; y=a[1]+u*dy; pair=tuple(sorted((e,f))); events.add((pair,round(x,6),round(y,6))); pairs.add(pair)
   elif abs((c[0]-a[0])*dy-(c[1]-a[1])*dx)<1e-7:
    k=0 if abs(dx)>abs(dy) else 1
    if min(max(a[k],b[k]),max(c[k],d[k]))>max(min(a[k],b[k]),min(c[k],d[k]))+1e-7: overlaps.add(tuple(sorted((e,f))))
 return {'proper_crossing_events':len(events),'crossing_connector_pairs':len(pairs),'collinear_overlap_pairs':len(overlaps)}
edges=[c for c in cells if c.get('edge')=='1']; beforepaths={c.get('id'):(endpoint(c.get('source'))[0],endpoint(c.get('target'))[0],pathof(c)) for c in edges if endpoint(c.get('source')) and endpoint(c.get('target'))}
beforehits=tablehits(beforepaths); beforecross=crossings(beforepaths)
print('Before geometry: '+json.dumps({'table_hits':len(beforehits),'crossings':beforecross}),flush=True)
expected={}
for child,d in schema.items():
 for fk in d['fk']:
  cols=fk['cols']; parent=fk['parent']; nullable=any(d['cols'][c]['nullable']=='YES' for c in cols)
  unique=any(set(u)<=set(cols) for u in d['unique'])
  for col,target in zip(cols,fk['targets']):
   assert any(set(u)<=set(fk['targets']) for u in schema[parent]['unique']),(child,fk)
   key=f'{child}.{col} -> {parent}.{target}'
   expected[key]={'child':child,'column':col,'parent':parent,'key':target,'constraint':fk['name'],'nullable':nullable,'unique':unique,'kind':'physical','startArrow':'ERzeroToOne' if nullable else 'ERone','endArrow':'ERzeroToOne' if unique else 'ERzeroToMany','cardinality':f"parent per child: {'0..1' if nullable else '1'}; children per parent: {'0..1' if unique else '0..many'}"}
removed=[]; duplicate=[]; kept={}; corrected=[]; audited=[]; missing=[]
logical=json.loads((OUT/'application_relationships.json').read_text())['links']; logicalmap={f"{r['child']}.{r['column']} -> {r['parent']}.{r['key']}":r for r in logical}
for c in edges:
 eid=c.get('id'); key=c.get('schemaRelationship') or c.get('logicalRelationship'); record=expected.get(key)
 if record is None:
  l=logicalmap.get(key)
  if l and l['kind']=='application_reference' and not(l['child']=='applicants' and l['column']=='AddressPSGC'):
   d=schema[l['child']]; nullable=d['cols'][l['column']]['nullable']=='YES' or l['column']=='LastReadMessageID'
   unique=any(set(u)<={l['column']} for u in d['unique'])
   record=dict(l,kind='logical',nullable=nullable,unique=unique,startArrow='ERzeroToOne' if nullable else 'ERone',endArrow='ERzeroToOne' if unique else 'ERzeroToMany',cardinality=f"intended parent per child: {'0..1' if nullable else '1'}; children per parent: {'0..1' if unique else '0..many'}")
   if l['child']=='system_logs': record['cardinality']='user-session actor: 0..1 user per log; 0..many logs per user; applicant fallback is polymorphic'; record['startArrow']='ERzeroToOne'
  else:
   reason='Unverified schema-only candidate' if l and l['kind']=='unverified_reference' else 'Lookup/calculation dependency, not an ERD key relationship' if l and l['kind']=='dependency' else 'JSON multi-value address reference cannot be represented as a scalar FK/cardinality' if l else 'Unsupported/orphan connector'
   removed.append({'id':eid,'relationship':key,'reason':reason}); root.remove(c); audited.append({'id':eid,'classification':'NOT SUPPORTED BY DATABASE','action':'removed','reason':reason}); continue
 if key in kept:
  duplicate.append({'id':eid,'relationship':key}); root.remove(c); continue
 source=endpoint(c.get('source')); target=endpoint(c.get('target')); expectedsource=(record['parent'],record['key']); expectedtarget=(record['child'],record['column'])
 sm=stylemap(c); reasons=[]
 if source!=expectedsource: reasons.append('WRONG SOURCE')
 if target!=expectedtarget: reasons.append('WRONG TARGET')
 if sm.get('startArrow')!=record['startArrow'] or sm.get('endArrow')!=record['endArrow']: reasons.append('WRONG CARDINALITY'); corrected.append(key)
 if any(eid==x[0] for x in beforehits): reasons.append('VISUALLY BAD ROUTING')
 record['element']=c; record['relationship']=key; kept[key]=record
 audited.append({'id':eid,'relationship':key,'classification':reasons or ['VALID'],'kind':record['kind'],'action':'retain and reroute'})
for key,r in expected.items():
 if key in kept: continue
 c=E.SubElement(root,'mxCell',id=f'onazul_fixed_missing_{len(missing)+1}',edge='1',parent='1',schemaRelationship=key)
 E.SubElement(c,'mxGeometry',relative='1',attrib={'as':'geometry'}); r['element']=c; r['relationship']=key; kept[key]=r; missing.append(key)

# Routing on a 10-unit obstacle grid. All actual endpoints are boundary ports;
# padded occupancy prevents routes from even grazing unrelated table borders.
STEP=10
minx=math.floor((min(b[0] for b in boxes.values())-180)/STEP)*STEP; miny=math.floor((min(b[1] for b in boxes.values())-180)/STEP)*STEP
maxx=math.ceil((max(b[0]+b[2] for b in boxes.values())+180)/STEP)*STEP; maxy=math.ceil((max(b[1]+b[3] for b in boxes.values())+180)/STEP)*STEP
W=int((maxx-minx)/STEP)+1; H=int((maxy-miny)/STEP)+1
blocked=bytearray(W*H)
for x,y,w,h in boxes.values():
 i0=max(0,math.ceil((x-6-minx)/STEP)); i1=min(W-1,math.floor((x+w+6-minx)/STEP)); j0=max(0,math.ceil((y-6-miny)/STEP)); j1=min(H-1,math.floor((y+h+6-miny)/STEP))
 for j in range(j0,j1+1): blocked[j*W+i0:j*W+i1+1]=bytes([1])*(i1-i0+1)
def node(x,y): return int(round((y-miny)/STEP))*W+int(round((x-minx)/STEP))
def coords(n): return (minx+(n%W)*STEP,miny+(n//W)*STEP)
portcount=Counter(); horizontal=Counter(); vertical=Counter()
def validstub(a,b,own): return not any(intersects(a,b,box) for t,box in boxes.items() if t!=own)
def ports(t,col):
 x,y,w,h=boxes[t]; row=rows[t,col]; _,ry,_,rh=rect(row); exacty=y+ry+rh/2; py=round(exacty/STEP)*STEP
 candidate=[]
 for side in ('west','east','north','south'):
  if side in ('west','east'):
   a=(x if side=='west' else x+w,py); b=(math.floor((x-18)/STEP)*STEP if side=='west' else math.ceil((x+w+18)/STEP)*STEP,py)
   cell=row.get('id'); fx=0 if side=='west' else 1; fy=(py-y-ry)/rh
  else:
   # Separate top/bottom ports across table width, retaining field identity in metadata.
   slot=portcount[t,side]%max(1,int((w-40)/STEP)); px=math.ceil((x+20)/STEP)*STEP+slot*STEP
   a=(px,y if side=='north' else y+h); b=(px,math.floor((y-18)/STEP)*STEP if side=='north' else math.ceil((y+h+18)/STEP)*STEP)
   cell=entities[t].get('id'); fx=(px-x)/w; fy=0 if side=='north' else 1
  n=node(*b)
  if 0<=n<len(blocked) and not blocked[n] and validstub(a,b,t): candidate.append({'node':n,'a':a,'b':b,'side':side,'cell':cell,'fx':fx,'fy':fy,'cost':abs(a[0]-b[0])+abs(a[1]-b[1])+portcount[t,side]*1.5})
 return candidate
def simplify(pts):
 clean=[]
 for p in pts:
  if clean and p==clean[-1]: continue
  while len(clean)>=2 and ((clean[-2][0]==clean[-1][0]==p[0]) or (clean[-2][1]==clean[-1][1]==p[1])): clean.pop()
  clean.append(p)
 return clean
def route(r):
 starts=ports(r['parent'],r['key']); ends=ports(r['child'],r['column']); assert starts and ends,r['relationship']
 if r['parent']==r['child']:
  starts=[p for p in starts if p['side'] in ('west','east')]
  ends=[p for p in ends if p['side'] in ('west','east')]
 goals=defaultdict(list)
 for e in ends: goals[e['node']].append(e)
 gc=[(n%W,n//W) for n in goals]
 def heuristic(n):
  i=n%W;j=n//W;return min(abs(i-a)+abs(j-b) for a,b in gc)*STEP
 queue=[]; dist={}; previous={}; origin={}
 for k,s in enumerate(starts):
  state=(s['node'],0 if s['side'] in ('west','east') else 1); d=s['cost']
  if d<dist.get(state,1e30): dist[state]=d; origin[state]=k; heapq.heappush(queue,(d+heuristic(s['node']),d,state))
 best=None; bestcost=1e30
 while queue:
  priority,cost,state=heapq.heappop(queue)
  if priority>=bestcost: break
  if cost!=dist.get(state): continue
  n,axis=state; i=n%W;j=n//W
  if n in goals:
   e=min(goals[n],key=lambda e:e['cost']); total=cost+e['cost']
   if total<bestcost: best=(state,e);bestcost=total
  for step,a in ((1,0),(-1,0),(W,1),(-W,1)):
   if step==1 and i==W-1 or step==-1 and i==0 or step==W and j==H-1 or step==-W and j==0: continue
   v=n+step
   if blocked[v]: continue
   used=horizontal if a==0 else vertical; opposite=vertical if a==0 else horizontal
   nd=cost+STEP+(18 if a!=axis else 0)+min(used[v],6)*20+min(opposite[v],6)*200
   ns=(v,a)
   if nd<dist.get(ns,1e30):
    dist[ns]=nd; previous[ns]=state; origin[ns]=origin[state]; heapq.heappush(queue,(nd+heuristic(v),nd,ns))
 assert best is not None,'No obstacle-free route: '+r['relationship']
 state,e=best; s=starts[origin[state]]; nodes=[state[0]]
 while state in previous: state=previous[state];nodes.append(state[0])
 nodes.reverse(); pts=simplify([s['a']]+[coords(n) for n in nodes]+[e['a']])
 assert len(pts)>=2 and pts[0]!=pts[-1],r['relationship']
 assert all(a!=b and (a[0]==b[0] or a[1]==b[1]) for a,b in zip(pts,pts[1:])),r['relationship']
 assert not any(intersects(a,b,box) for t,box in boxes.items() for a,b in zip(pts,pts[1:]) if t not in (r['parent'],r['child'])),r['relationship']
 # Endpoints are on boundaries; all intermediate parts must also avoid own interiors.
 assert not any(intersects(a,b,boxes[t]) for t in set((r['parent'],r['child'])) for a,b in zip(pts,pts[1:])),r['relationship']
 for a,b in zip(nodes,nodes[1:]): (horizontal if abs(a-b)==1 else vertical)[b]+=1
 portcount[r['parent'],s['side']]+=1;portcount[r['child'],e['side']]+=1
 c=r['element']; c.set('source',s['cell']);c.set('target',e['cell'])
 role=r['column']
 c.set('value',('Logical: '+role) if r['kind']=='logical' else role if sum(x['child']==r['child'] and x['parent']==r['parent'] for x in kept.values())>1 else '')
 c.set('style',f"edgeStyle=orthogonalEdgeStyle;noEdgeStyle=1;orthogonal=1;rounded=0;jettySize=0;html=1;fontSize=10;labelBackgroundColor=#ffffff;startArrow={r['startArrow']};endArrow={r['endArrow']};startFill=0;endFill=0;exitX={s['fx']};exitY={s['fy']};entryX={e['fx']};entryY={e['fy']};exitPerimeter=0;entryPerimeter=0;"+('dashed=1;dashPattern=6 4;' if r['kind']=='logical' else ''))
 c.set('relationshipType',r['kind']); c.set('childTable',r['child']); c.set('childColumn',r['column']);c.set('parentTable',r['parent']);c.set('parentColumn',r['key']);c.set('cardinality',r['cardinality'])
 g=c.find('mxGeometry')
 for item in list(g):g.remove(item)
 g.set('relative','1'); arr=E.SubElement(g,'Array',attrib={'as':'points'})
 for x,y in pts[1:-1]:E.SubElement(arr,'mxPoint',x=str(x),y=str(y))
 # Explicit label position follows a clear segment; labels never need to cover table text.
 g.set('x','0');g.set('y','-8')
 return pts

paths={}; start=time.time()
# Local shorter relationships first reserve sensible corridors; routing penalizes
# shared lanes and crossings when placing longer links.
order=sorted(kept.values(),key=lambda r:abs(boxes[r['child']][0]-boxes[r['parent']][0])+abs(boxes[r['child']][1]-boxes[r['parent']][1]))
for i,r in enumerate(order,1):
 pts=route(r); paths[r['element'].get('id')]=(r['parent'],r['child'],pts)
 if i%10==0:print(f'Routed {i}/{len(order)}; elapsed {time.time()-start:.1f}s',flush=True)
afterhits=tablehits(paths);assert not afterhits
aftercross=crossings(paths)
for c in list(root):
 if c.get('auditLegend')=='1':root.remove(c)
minlegendy=min(b[1] for b in boxes.values())-390
legend=E.SubElement(root,'mxCell',id='onazul_fixed_legend',parent='1',vertex='1',value='RELATIONSHIP LEGEND\nSolid: installed MySQL FK; constraint-derived cardinality\nDashed: separately verified application reference; intended cardinality\nCircle: optional (zero); bar: one; crow foot: many\nLookup/calculation dependencies and unverified references removed.\nStandalone tables remain when no justified external relationship exists.',style='whiteSpace=wrap;html=0;align=left;spacingLeft=10;fillColor=#ffffff;strokeColor=#000000;fontSize=12;',auditLegend='1')
E.SubElement(legend,'mxGeometry',x=str(min(b[0] for b in boxes.values())),y=str(minlegendy),width='660',height='160',attrib={'as':'geometry'})
tree.write(OUTPUT,encoding='utf-8',xml_declaration=True);E.parse(OUTPUT)
assert len({c.get('id') for c in root})==len(list(root))
physical={k for k,r in kept.items() if r['kind']=='physical'};assert physical==set(expected)
adj={t:set() for t in schema}; physicaladj={t:set() for t in schema}
for r in kept.values():
 adj[r['child']].add(r['parent']);adj[r['parent']].add(r['child'])
 if r['kind']=='physical':physicaladj[r['child']].add(r['parent']);physicaladj[r['parent']].add(r['child'])
standalone=[t for t,a in adj.items() if not a]; physically_standalone=[t for t,a in physicaladj.items() if not a]
oldadj={t:set() for t in schema}
for _,(s,t,pts) in beforepaths.items():oldadj[s].add(t);oldadj[t].add(s)
previously_disconnected=[t for t,a in oldadj.items() if not a]
maprows=[{k:v for k,v in r.items() if k!='element'}|{'connector_id':r['element'].get('id'),'connector_found':True,'route_intersects_table':False,'status':'VERIFIED PHYSICAL FK' if r['kind']=='physical' else 'VERIFIED LOGICAL REFERENCE, NO FK CONSTRAINT'} for r in kept.values()]
summary={'database_tables':len(schema),'erd_entities':len(entities),'connectors_before':len(edges),'connectors_after':len(kept),'physical_fks':len(expected),'logical_references':sum(r['kind']=='logical' for r in kept.values()),'invalid_removed':removed,'duplicates_removed':duplicate,'missing_fks_added':missing,'cardinalities_corrected':corrected,'one_to_one_physical':[k for k,r in expected.items() if r['unique']],'one_to_many_physical':[k for k,r in expected.items() if not r['unique']],'optional_physical':[k for k,r in expected.items() if r['nullable']],'previously_disconnected':previously_disconnected,'tables_zero_connectors':standalone,'tables_without_installed_fks':physically_standalone,'table_intersections_before':len(beforehits),'table_intersections_after':len(afterhits),'table_hits_before':beforehits,'crossings_before':beforecross,'crossings_after':aftercross,'tables_moved':[],'missing_physical_fks':[],'extra_physical_fks':[],'orphans':[],'junctions':['chat_thread_participants: composite PK (ThreadID, UserID), two verified logical parent references, no installed FK constraints','chat_thread_reads: composite PK (ThreadID, UserID), verified logical parent references and optional read cursor']}
(OUT/'relationship_fix_audit.json').write_text(json.dumps({'summary':summary,'connector_audit':audited,'relationship_map':maprows,'routes':{k:v[2] for k,v in paths.items()}},indent=2),encoding='utf-8')
reasons={'payroll_run_status_history':'No installed FK constraints and no current production usage found; unverified candidate removed.','industry_lookup':'Name dropdown dependency only; no installed FK or stored reference ID.','work_schedule_lookup':'Name dropdown with custom text values; no installed FK or stored reference ID.','sss_rates_config':'Shared calculation configuration selected by salary bracket; no stored FK reference.','gov_percentage_rates':'Shared calculation configuration; no stored FK reference.'}
report=['# ONAZUL ERD relationship cleanup','','Input: Onazul_ERD_CONNECTED.drawio. Output: Onazul_ERD_RELATIONSHIPS_FIXED.drawio.','Read-only live information_schema metadata inspected; all 30 SQL migration/support files and prior production-query evidence cross-checked. No database or production code modified.','',f"Tables inspected / existing entities: {len(schema)} / {len(entities)}.",f"Connectors before / after: {len(edges)} / {len(kept)}. Physical FKs: {len(expected)}. Retained separately labelled logical references: {sum(r['kind']=='logical' for r in kept.values())}.",f"Invalid/non-relational lines removed: {len(removed)}. Duplicates removed: {len(duplicate)}. Missing FKs added: {len(missing)}. Cardinalities corrected: {len(corrected)}.",'','## Removed connectors']
report += [f"- {r['relationship']}: {r['reason']}" for r in removed]
report+=['','## Cardinality',f"Physical 1:1 maximum: {len(summary['one_to_one_physical'])}. Physical 1:M maximum: {len(summary['one_to_many_physical'])}. Nullable/optional parent links: {len(summary['optional_physical'])}.",'Uniqueness is checked against complete PK/UNIQUE groups. Nullable FK permits 0..1 parent; non-null FK requires one parent. Parent may have zero children; unique child FK permits at most one child. Logical cardinalities are intended references with separately checked child uniqueness/nullability, not claims of DB enforcement. LastReadMessageID=0 is an optional initial cursor sentinel. system_logs actor has an applicant-session fallback, reported as polymorphic.','Junctions: chat_thread_participants (ThreadID, UserID) and chat_thread_reads (ThreadID, UserID) are retained with both logical parent links, representing association through visible bridge entities. No direct M:N shortcut added.','PSGC parent links remain a separately evidenced logical self-loop, and province-less cities are not forced into a province hierarchy. JSON address-code association is reported separately, not depicted as a scalar FK.','', '## Disconnected tables',f'Previously zero connectors: {previously_disconnected}. Missing declared relationships to those tables: none.']
report += [f'- {t}: LEGITIMATELY STANDALONE IN CURRENT DATABASE. '+reasons.get(t,'No installed FK and no retained justified relationship.') for t in standalone]
report+=['','Tables without installed incoming/outgoing FKs (some have clearly evidenced dashed logical links): '+', '.join(physically_standalone)+'.','','## Geometry validation',f'Connector/unrelated-table intersection pairs BEFORE: {len(beforehits)}; AFTER: {len(afterhits)}.',f'Crossings BEFORE: {beforecross}. AFTER: {aftercross}.','Proper crossings count interior/interior segment intersections; shared endpoints and collinear overlaps are separate. Counts use the explicit saved polyline geometry. Previous automatic router effects could not be reconstructed reliably beyond saved waypoints. Final edge automatic routing is disabled; all line segments and boundary ports are explicitly fixed.', 'All final routes also tested against source/target interiors; no text-crossing segments. No floating endpoints. Tables moved: none; positions, sizes, styles, columns and key notation preserved. Supplemental legend replaced.','', '## Final FK validation','All installed FKs represented exactly once. Missing FK connectors: 0. Extra physical FK connectors: 0. Duplicate relationship identities: 0. Unrelated entity intersections: 0. Detailed per-relationship source/FK/target/cardinality/route status and removed line classifications are in relationship_fix_audit.json.','', '## Files','- Onazul_ERD_RELATIONSHIPS_FIXED.drawio','- relationship_fix_audit.json','- ERD_RELATIONSHIPS_FIXED_REPORT.md','- Onazul_ERD_RELATIONSHIPS_FIXED.png (preview generated separately)']
(OUT/'ERD_RELATIONSHIPS_FIXED_REPORT.md').write_text('\n'.join(report),encoding='utf-8')
print('Final: '+json.dumps({k:summary[k] for k in ('database_tables','connectors_before','connectors_after','table_intersections_before','table_intersections_after','crossings_before','crossings_after','tables_zero_connectors')}),flush=True)
