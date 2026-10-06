// Employee pages share the fit-to-viewport, zoom and pointer-pan behavior used by HR.
(function () {
  var modal=document.getElementById('doc-view-modal');
  var frame=document.getElementById('doc-view-frame');
  if (!modal || !frame) return;
  var box=modal.firstElementChild, viewport=frame.parentElement;
  box.style.cssText='width:98vw;max-width:1600px;height:94vh;display:flex;flex-direction:column;';
  viewport.className='employee-doc-viewport';
  viewport.style.cssText='flex:1;min-height:0;overflow:auto;background:#edf2f7;padding:12px;position:relative;';
  frame.style.cssText='width:100%;height:100%;border:0;';
  var image=document.createElement('img');
  image.alt='Document preview';image.draggable=false;image.style.cssText='display:none;max-width:none;margin-left:auto;margin-right:auto;';
  viewport.appendChild(image);
  var toolbar=document.createElement('div');
  toolbar.style.cssText='display:flex;flex-wrap:wrap;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid #dce5ef;';
  toolbar.setAttribute('aria-label','Document zoom controls');
  function button(label,action) {
    var b=document.createElement('button');b.type='button';b.textContent=label;
    b.style.cssText='padding:6px 12px;border:1px solid #b9cbe0;border-radius:6px;color:#0a2a5e;background:white;';
    b.addEventListener('click',action);toolbar.appendChild(b);return b;
  }
  var zoom=1,isImage=false,drag=null;
  var minus=button('− Zoom out',function(){setZoom(zoom-.25);});
  var level=document.createElement('output');level.setAttribute('aria-live','polite');toolbar.appendChild(level);
  var plus=button('+ Zoom in',function(){setZoom(zoom+.25);});
  button('Reset',function(){setZoom(1);viewport.scrollLeft=0;viewport.scrollTop=0;});
  var hint=document.createElement('span');hint.textContent='Drag the image to view its sides.';hint.style.cssText='font-size:12px;color:#64748b;';toolbar.appendChild(hint);
  box.insertBefore(toolbar,viewport);
  var footer=document.createElement('div');footer.style.cssText='padding:12px 16px;border-top:1px solid #dce5ef;text-align:right;';
  var link=document.createElement('a');link.textContent='Download / Open File';link.target='_blank';link.rel='noopener noreferrer';link.style.cssText='color:#0a2a5e;text-decoration:underline;';footer.appendChild(link);box.appendChild(footer);
  function setZoom(value) {
    var previous=zoom;zoom=Math.max(.5,Math.min(3,value));
    if(isImage && image.naturalWidth) {
      var width=Math.max(1,viewport.clientWidth-24),height=Math.max(1,viewport.clientHeight-24);
      var ratio=image.naturalWidth/image.naturalHeight;
      var fitted=Math.min(width,height*ratio);
      image.style.width=fitted*zoom+'px';
      image.style.marginTop=Math.max(0,(height-fitted/ratio*zoom)/2)+'px';
      if(previous!==zoom) {var scale=zoom/previous;viewport.scrollLeft=(viewport.scrollLeft+viewport.clientWidth/2)*scale-viewport.clientWidth/2;viewport.scrollTop=(viewport.scrollTop+viewport.clientHeight/2)*scale-viewport.clientHeight/2;}
    } else frame.style.zoom=String(zoom);
    level.textContent=Math.round(zoom*100)+'%';minus.disabled=zoom<=.5;plus.disabled=zoom>=3;
  }
  image.addEventListener('load',function(){setZoom(zoom);});
  viewport.addEventListener('pointerdown',function(event){
    if(!isImage || event.button!==0)return;
    var rect=viewport.getBoundingClientRect();
    if(event.clientX-rect.left>=viewport.clientWidth||event.clientY-rect.top>=viewport.clientHeight)return;
    drag={id:event.pointerId,x:event.clientX,y:event.clientY,left:viewport.scrollLeft,top:viewport.scrollTop};
    viewport.setPointerCapture(event.pointerId);viewport.style.cursor='grabbing';event.preventDefault();
  });
  viewport.addEventListener('pointermove',function(event){if(drag && drag.id===event.pointerId){viewport.scrollLeft=drag.left-(event.clientX-drag.x);viewport.scrollTop=drag.top-(event.clientY-drag.y);}});
  function stop(){if(!drag)return;var id=drag.id;drag=null;if(viewport.hasPointerCapture(id))viewport.releasePointerCapture(id);viewport.style.cursor=isImage?'grab':'auto';}
  ['pointerup','pointercancel','lostpointercapture'].forEach(function(type){viewport.addEventListener(type,stop);});
  function close(){stop();image.removeAttribute('src');frame.src='about:blank';modal.classList.add('hidden');modal.classList.remove('flex');}
  document.getElementById('doc-view-close').addEventListener('click',close);
  modal.addEventListener('click',function(event){if(event.target===modal)close();});
  document.addEventListener('keydown',function(event){if(event.key==='Escape'&&!modal.classList.contains('hidden'))close();});
  window.addEventListener('resize',function(){if(!modal.classList.contains('hidden'))setZoom(zoom);});
  window.EmployeeDocumentPreview={open:function(path,title){
    stop();zoom=1;image.removeAttribute('src');frame.src='about:blank';
    document.getElementById('doc-view-title').textContent=title||'Document Preview';
    isImage=/\.(png|jpe?g|gif|webp|bmp)(?:[?#]|$)/i.test(path);
    var pdf=/\.pdf(?:[?#]|$)/i.test(path);
    toolbar.style.display=isImage||pdf?'flex':'none';hint.hidden=!isImage;
    frame.style.display=isImage?'none':'block';image.style.display=isImage?'block':'none';
    viewport.style.cursor=isImage?'grab':'auto';viewport.style.touchAction=isImage?'none':'auto';
    link.href=path;modal.classList.remove('hidden');modal.classList.add('flex');
    viewport.scrollLeft=0;viewport.scrollTop=0;
    if(isImage)image.src=path;else frame.src=path;
    setZoom(1);
  }};
})();
