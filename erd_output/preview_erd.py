from pathlib import Path
import xml.etree.ElementTree as E
import re,html,heapq,math,sys
from PIL import Image,ImageDraw,ImageFont
OUT=Path(__file__).resolve().parent
p=OUT/(sys.argv[1] if len(sys.argv)>1 else 'Onazul_ERD_UPDATED.drawio'); tree=E.parse(p); root=tree.find('.//root'); cells=list(root); ids={x.get('id'):x for x in cells}
tables=[c for c in cells if 'shape=table;' in c.get('style','')]
def rect(c):
 g=c.find('mxGeometry'); return [float(g.get(k,'0')) for k in ('x','y','width','height')]
boxes={c.get('id'):rect(c) for c in tables}
def ancestor(id):
 while id not in boxes: id=ids[id].get('parent')
 return id
legendcells=[c for c in cells if c.get('auditLegend')=='1']
left=min(b[0] for b in boxes.values())-120; top=min([b[1] for b in boxes.values()]+[rect(c)[1] for c in legendcells])-120
right=max(b[0]+b[2] for b in boxes.values())+120; bottom=max(b[1]+b[3] for b in boxes.values())+120
step=20
def grid(x,y): return (round((x-left)/step),round((y-top)/step))
def point(g): return (left+g[0]*step,top+g[1]*step)
blocked=set()
for x,y,w,h in boxes.values():
 for i in range(math.floor((x-left-5)/step),math.ceil((x+w-left+5)/step)+1):
  for j in range(math.floor((y-top-5)/step),math.ceil((y+h-top+5)/step)+1): blocked.add((i,j))
nx=math.ceil((right-left)/step); ny=math.ceil((bottom-top)/step)
routes=[]
for edge in [c for c in cells if c.get('edge')=='1']:
 s=ids[edge.get('source')]; t=ids[edge.get('target')]
 si=ancestor(s.get('id')); ti=ancestor(t.get('id')); sx,sy,sw,sh=boxes[si]; tx,ty,tw,th=boxes[ti]
 sr=rect(s); tr=rect(t)
 side=1 if tx+tw/2>=sx+sw/2 else -1
 a=(sx+sw if side==1 else sx,sy+sr[1]+sr[3]/2); b=(tx if side==1 else tx+tw,ty+tr[1]+tr[3]/2)
 existing=edge.find("mxGeometry/Array[@as='points']")
 if edge.get('relationshipType'):
  sm=dict(v.split('=',1) for v in edge.get('style','').split(';') if '=' in v)
  rx,ry,rw,rh=sr; qx,qy,qw,qh=tr
  if s.get('id')!=si: rx+=sx;ry+=sy
  if t.get('id')!=ti: qx+=tx;qy+=ty
  a=(rx+float(sm['exitX'])*rw,ry+float(sm['exitY'])*rh); b=(qx+float(sm['entryX'])*qw,qy+float(sm['entryY'])*qh)
  pts=[a]+[(float(v.get('x','0')),float(v.get('y','0'))) for v in existing if v.tag=='mxPoint']+[b]
  routes.append((edge,a,pts,b)); continue
 if existing is not None and not edge.get('logicalKind'):
  pts=[a]+[(float(v.get('x','0')),float(v.get('y','0'))) for v in existing if v.tag=='mxPoint']+[b]
  routes.append((edge,a,pts,b)); continue
 if si==ti:
  a=(sx+sw,sy+sr[1]+sr[3]/2); b=(tx+tw,ty+tr[1]+tr[3]/2)
  pts=[a,(sx+sw+70,a[1]),(sx+sw+70,b[1]),b]
  geom=edge.find('mxGeometry')
  for oldarr in list(geom): geom.remove(oldarr)
  arr=E.SubElement(geom,'Array',attrib={'as':'points'})
  for x,y in pts[1:-1]: E.SubElement(arr,'mxPoint',x=str(x),y=str(y))
  edge.set('style',edge.get('style','')+'exitX=1;exitY=0.5;entryX=1;entryY=0.5;')
  routes.append((edge,a,pts,b)); continue
 start=grid(a[0]+side*40,a[1]); end=grid(b[0]-side*40,b[1])
 # Move ports out of neighboring obstacles when needed.
 if start in blocked or end in blocked:
  routes.append((edge,a,[a,(a[0]+side*25,a[1]),(a[0]+side*25,b[1]),b],b)); continue
 q=[(0,0,start)]; cost={start:0}; prev={}; found=False
 while q:
  _,dist,node=heapq.heappop(q)
  if node==end: found=True; break
  if dist!=cost[node]: continue
  for dx,dy in [(1,0),(-1,0),(0,1),(0,-1)]:
   nxt=(node[0]+dx,node[1]+dy)
   if nxt in blocked or not(0<=nxt[0]<=nx and 0<=nxt[1]<=ny): continue
   nd=dist+1
   if nd<cost.get(nxt,1e9):
    cost[nxt]=nd; prev[nxt]=node; heapq.heappush(q,(nd+abs(nxt[0]-end[0])+abs(nxt[1]-end[1]),nd,nxt))
 if found:
  path=[end]
  while path[-1]!=start: path.append(prev[path[-1]])
  path.reverse(); pts=[point(path[0])]
  for i in range(1,len(path)-1):
   if (path[i][0]-path[i-1][0],path[i][1]-path[i-1][1])!=(path[i+1][0]-path[i][0],path[i+1][1]-path[i][1]): pts.append(point(path[i]))
  pts.append(point(path[-1])); pts=[a,(pts[0][0],a[1])]+pts+[(pts[-1][0],b[1]),b]
 else: pts=[a,(a[0]+side*30,a[1]),(a[0]+side*30,b[1]),b]
 geom=edge.find('mxGeometry')
 for oldarr in list(geom): geom.remove(oldarr)
 arr=E.SubElement(geom,'Array',attrib={'as':'points'})
 for x,y in pts[1:-1]: E.SubElement(arr,'mxPoint',x=str(x),y=str(y))
 style=edge.get('style','')+f'exitX={1 if side==1 else 0};exitY=0.5;entryX={0 if side==1 else 1};entryY=0.5;'
 edge.set('style',style); routes.append((edge,a,pts,b))
tree.write(p,encoding='utf-8',xml_declaration=True); E.parse(p)
left=min(left,min(pt[0] for _,_,pts,_ in routes for pt in pts)-40)
right=max(right,max(pt[0] for _,_,pts,_ in routes for pt in pts)+40)
top=min(top,min(pt[1] for _,_,pts,_ in routes for pt in pts)-40)
bottom=max(bottom,max(pt[1] for _,_,pts,_ in routes for pt in pts)+40)
scale=2
im=Image.new('RGB',(math.ceil((right-left)*scale),math.ceil((bottom-top)*scale)),'white'); draw=ImageDraw.Draw(im)
def xy(p): return ((p[0]-left)*scale,(p[1]-top)*scale)
font=ImageFont.truetype('C:/Windows/Fonts/arial.ttf',24); bold=ImageFont.truetype('C:/Windows/Fonts/arialbd.ttf',24)
def plain(s): return html.unescape(re.sub('<[^>]*>','',s or '')).strip()
for edge,a,pts,b in routes:
 color=re.search('strokeColor=([^;]+)',edge.get('style',''))
 color=color[1] if color else '#555555'
 if edge.get('logicalKind'):
  dash,gap=(2,7) if edge.get('logicalKind')=='dependency' else (9,6)
  for start,end in zip(pts,pts[1:]):
   dx=end[0]-start[0]; dy=end[1]-start[1]; length=math.hypot(dx,dy)
   if not length: continue
   for pos in range(0,math.ceil(length),dash+gap):
    finish=min(pos+dash,length)
    draw.line([xy((start[0]+dx*pos/length,start[1]+dy*pos/length)),xy((start[0]+dx*finish/length,start[1]+dy*finish/length))],fill=color,width=2)
 else: draw.line([xy(x) for x in pts],fill=color,width=2)
 # Crow foot at child and single bar / optional circle at parent.
 for endpoint,other,child in [(b,pts[-2],True),(a,pts[1],False)]:
  if edge.get('logicalKind') and not edge.get('relationshipType'): continue
  vx=other[0]-endpoint[0];vy=other[1]-endpoint[1];length=math.hypot(vx,vy)
  if not length: continue
  vx/=length;vy/=length;px=-vy;py=vx;x,y=endpoint
  prefix='endArrow' if child else 'startArrow'
  match=re.search(prefix+'=([^;]+)',edge.get('style','')); arrow=match[1] if match else ''
  if 'Many' in arrow:
   for offset in (-7,0,7): draw.line([xy((x+px*offset,y+py*offset)),xy((x+vx*12,y+vy*12))],fill=color,width=2)
  elif arrow not in ('','none'):
   draw.line([xy((x+vx*8+px*7,y+vy*8+py*7)),xy((x+vx*8-px*7,y+vy*8-py*7))],fill=color,width=2)
  if 'zero' in arrow.lower():
   center=xy((x+vx*20,y+vy*20));radius=4*scale
   draw.ellipse((center[0]-radius,center[1]-radius,center[0]+radius,center[1]+radius),fill='white',outline=color,width=2)
for c in tables:
 x,y,w,h=boxes[c.get('id')]
 draw.rectangle([xy((x,y)),xy((x+w,y+h))],fill='white',outline='black',width=2)
 title=plain(c.get('value')); titlefont=bold
 while draw.textlength(title,font=titlefont)>w*scale-8 and titlefont.size>16: titlefont=ImageFont.truetype('C:/Windows/Fonts/arialbd.ttf',titlefont.size-1)
 draw.text(xy((x+w/2,y+15)),title,font=titlefont,fill='black',anchor='mm')
 draw.line([xy((x,y+30)),xy((x+w,y+30))],fill='black',width=2)
 for row in [r for r in cells if r.get('parent')==c.get('id')]:
  rg=rect(row); cc=sorted([v for v in cells if v.get('parent')==row.get('id')],key=lambda v:rect(v)[0])
  if len(cc)!=2: continue
  mark=plain(cc[0].get('value')); text=plain(cc[1].get('value'))
  draw.text(xy((x+15,y+rg[1]+15)),mark,font=font if len(mark)<4 else ImageFont.truetype('C:/Windows/Fonts/arial.ttf',16),fill='black',anchor='mm')
  # Wrap long column labels within the original table width.
  available=(w-38)*scale; chunks=[]; chunk=''
  for char in text:
   if draw.textlength(chunk+char,font=font)>available: chunks.append(chunk); chunk=char
   else: chunk+=char
  chunks.append(chunk)
  for j,line in enumerate(chunks[:2]): draw.text(xy((x+36,y+rg[1]+(4 if len(chunks)>1 else 9)+j*13)),line,font=font,fill='black')
for edge,a,pts,b in routes:
 label=edge.get('value','')
 if not label: continue
 mid=(float(edge.get('labelX')),float(edge.get('labelY'))) if edge.get('labelX') is not None else pts[len(pts)//2]; center=xy(mid); label_font=ImageFont.truetype('C:/Windows/Fonts/arial.ttf',20)
 textw=draw.textlength(label,font=label_font)
 draw.rectangle((center[0]-textw/2-4,center[1]-12,center[0]+textw/2+4,center[1]+12),fill='white')
 draw.text(center,label,font=label_font,fill='#d97706' if edge.get('logicalKind')=='unverified_reference' else '#555555',anchor='mm')
for legend in legendcells:
 x,y,w,h=rect(legend)
 draw.rectangle([xy((x,y)),xy((x+w,y+h))],fill='white',outline='black',width=2)
 for i,line in enumerate(legend.get('value','').splitlines()): draw.text(xy((x+10,y+10+i*23)),line,font=bold if i==0 else font,fill='black')
im.save(OUT/(p.stem+'.png'),optimize=True)
print({'preview_pixels':im.size,'routed_edges':len(routes)})
