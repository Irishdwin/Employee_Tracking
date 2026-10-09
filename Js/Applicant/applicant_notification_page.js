(function(){
 'use strict';
 var api='../../Api/Applicant/notifications.php',items=[],selected=Number(new URLSearchParams(location.search).get('notification'))||0,loading=false,marking=false;
 var inbox=document.getElementById('fullNotificationContainer'),detail=document.getElementById('applicantNotificationDetail'),filter=document.getElementById('applicantUnreadOnly');if(!inbox)return;
 function esc(s){var d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML;}
 async function request(body){var r=await fetch(api+(body?'':'?limit=50'),{method:body?'POST':'GET',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json'},body:body?JSON.stringify(body):undefined});var d=await r.json();if(!r.ok||!d.success)throw Error('Unable to load notifications. Please try again.');return d;}
 async function markRead(body){
  if(marking)return false;
  marking=true;
  try{
   var all=body.action==='mark_all_read';
   var answer=await Swal.fire({title:all?'Mark all notifications as read?':'Mark this notification as read?',text:all?'All your unread notifications will be marked as read.':'This notification will no longer appear as unread.',icon:'question',showCancelButton:true,confirmButtonText:'Mark as read',cancelButtonText:'Cancel',confirmButtonColor:'#0056b3'});
   if(!answer.isConfirmed)return false;
   await request(body);
   if(all)items.forEach(n=>n.is_read=true);else {var n=items.find(n=>n.id===body.notification_id);if(n)n.is_read=true;}
   render();addConversationButton();
   document.dispatchEvent(new Event('applicant-notifications-updated'));
   await Swal.fire({title:'Marked as read',text:all?'Your notifications have been marked as read.':'Your notification has been marked as read.',icon:'success',confirmButtonColor:'#0056b3'});
   return true;
  }catch(e){await Swal.fire({title:'Unable to mark as read',text:'Please try again. Your notification has not been marked as read.',icon:'error',confirmButtonColor:'#0056b3'});return false;}
  finally{marking=false;}
 }
 function render(){var visible=items.filter(n=>!filter.checked||!n.is_read);inbox.innerHTML=visible.length?visible.map(n=>'<button type="button" class="notif-card '+(!n.is_read?'unread ':'')+(n.id===selected?'selected':'')+'" data-id="'+Number(n.id)+'"><div class="d-flex justify-content-between gap-2"><span class="notif-title">'+esc(n.title||'Notification')+'</span><span class="notification-state">'+(n.is_read?'Read':'Unread')+'</span></div><div class="notif-msg">'+esc(String(n.message||'').slice(0,140))+(String(n.message||'').length>140?'…':'')+'</div></button>').join(''):'<div class="text-center bg-white rounded-4 p-5 text-muted">'+(filter.checked?'No unread notifications.':'No notifications yet.')+'</div>';inbox.querySelectorAll('[data-id]').forEach(el=>el.onclick=()=>open(Number(el.dataset.id)));document.getElementById('applicantNotificationCount').textContent=items.filter(n=>!n.is_read).length+' unread';var n=items.find(n=>n.id===selected);if(n)detail.innerHTML='<div class="d-flex justify-content-between gap-2 mb-3"><h2 class="h5">'+esc(n.title)+'</h2><span class="notification-state">'+(n.is_read?'Read':'Unread')+'</span></div><p>'+esc(n.message)+'</p>'+(n.application_id?'<a class="btn btn-outline-primary btn-sm" href="my_applications.html">View My Applications</a>':'');}
 function addConversationButton(){var n=items.find(n=>n.id===selected);if(n?.messenger_user_id&&window.OwMessenger){var btn=document.createElement('button');btn.type='button';btn.className='btn btn-primary';btn.textContent='Open Conversation';btn.onclick=()=>OwMessenger.openContact(n.messenger_user_id);detail.appendChild(btn);}}
 async function open(id){var n=items.find(n=>n.id===id);if(!n)return;selected=id;render();addConversationButton();if(!n.is_read)await markRead({action:'mark_read',notification_id:id});}
 async function load(){if(loading)return;loading=true;try{var d=await request();items=d.notifications||[];render();addConversationButton();}catch(e){if(!items.length)inbox.textContent=e.message;}finally{loading=false;}}
 filter.onchange=render;document.getElementById('applicantNotificationRefresh').onclick=load;document.getElementById('btnPageMarkAll').onclick=()=>markRead({action:'mark_all_read'});
 load().then(()=>{if(selected&&items.some(n=>n.id===selected&&!n.is_read))open(selected);});setInterval(()=>{if(!document.hidden)load();},15000);window.addEventListener('focus',load);document.addEventListener('visibilitychange',()=>{if(!document.hidden)load();});
})();
