/** Expand the existing same-origin iframe while a modal is open, preserving its document and handlers. */
(function(){'use strict';
 let frame, originalStyle, backdrop, previousOverflow;
 function restore(){if(!frame)return;document.documentElement.classList.remove('embedded-modal-open');if(originalStyle===null)frame.removeAttribute('style');else frame.setAttribute('style',originalStyle);backdrop?.remove();window.parent.document.body.style.overflow=previousOverflow;frame=null;backdrop=null;}
 document.addEventListener('show.bs.modal',function(event){
  if(event.target.id==='viewProfileModal')document.body.classList.add('profile-view-open');
  if(window.parent===window || frame)return;
  try {frame=window.frameElement;if(!frame)return;originalStyle=frame.getAttribute('style');previousOverflow=window.parent.document.body.style.overflow;
   backdrop=window.parent.document.createElement('div');backdrop.setAttribute('aria-hidden','true');backdrop.style.cssText='position:fixed;inset:0;background:rgba(16,44,84,.18);z-index:1999;';window.parent.document.body.append(backdrop);
   frame.style.cssText='position:fixed;inset:0;width:100%;height:100%;border:0;background:transparent;z-index:2000;';window.parent.document.body.style.overflow='hidden';document.documentElement.classList.add('embedded-modal-open');
  }catch(error){restore();}
 });
 document.addEventListener('hidden.bs.modal',function(event){if(event.target.id==='viewProfileModal')document.body.classList.remove('profile-view-open');if(!document.querySelector('.modal.show'))restore();});
 window.addEventListener('pagehide',restore);
})();
