(function(){'use strict';
const text=(id,value)=>{const el=document.getElementById(id);if(el)el.textContent=value||'';};
function optional(block,id,value){document.getElementById(block).hidden=!value;text(id,value);}
const aboutModal=document.getElementById('landingAboutModal');
if(aboutModal)aboutModal.addEventListener('show.bs.modal',()=>{
 text('landingAboutModalTitle',document.getElementById('landingAboutTitle').textContent);
 const body=document.getElementById('landingAboutModalContent');body.replaceChildren();
 const description=document.createElement('p');description.textContent=document.getElementById('landingAboutText').textContent;description.className='mb-4';body.append(description);
 for(const [source,title,block] of [['landingMission','Our purpose','landingMissionBlock'],['landingOfficeAddress','Visit our office','landingOfficeAddressBlock'],['landingOfficeHours','Support hours','landingOfficeHoursBlock'],['landingPublicContact','Contact the agency',null],['landingOfficialLinks','Official channels',null]]){
  const original=document.getElementById(source);if(!original||!original.textContent.trim()||(block&&document.getElementById(block).hidden))continue;
  const section=document.createElement('section');section.className='mb-4';const heading=document.createElement('h3');heading.className='fs-6 fw-semibold';heading.textContent=title;
  const content=original.cloneNode(true);content.removeAttribute('id');content.querySelectorAll('[id]').forEach(el=>el.removeAttribute('id'));section.append(heading,content);body.append(section);
 }
});
const explore=document.getElementById('landingAboutExplore');if(explore)explore.addEventListener('click',()=>{
 aboutModal.addEventListener('hidden.bs.modal',()=>{const section=document.getElementById('jobWallSection');section.scrollIntoView({behavior:'smooth',block:'start'});const heading=section.querySelector('h2');if(heading){heading.tabIndex=-1;heading.focus({preventScroll:true});}},{once:true});
 bootstrap.Modal.getInstance(aboutModal).hide();
});
fetch('../../Api/Applicant/get_agency_contact.php').then(r=>{if(!r.ok)throw Error('Unavailable');return r.json();}).then(data=>{if(!data.success)return;const info=data.landing||{},contact=data.contact||{};
 const name=info.company_name||'Onazul Manpower Services';text('landingAboutTitle','About '+name);text('landingFooterName',name);document.querySelector('.landing-office figcaption').textContent=name;
 if(info.about)text('landingAboutText',info.about);if(info.tagline)text('landingWelcome',info.tagline);
 optional('landingMissionBlock','landingMission',info.mission);optional('landingOfficeAddressBlock','landingOfficeAddress',info.office_address);optional('landingOfficeHoursBlock','landingOfficeHours',info.office_hours);
 if(info.applicant_guidance)text('landingGuidance',info.applicant_guidance);
 const target=document.getElementById('landingPublicContact');target.classList.add('agency-contact-details');
 if(contact.email||contact.phone){
  target.replaceChildren();
  if(contact.name){const line=document.createElement('div');line.className='agency-contact-person';line.textContent=contact.name;target.append(line);}
  function contactRow(label,value,href,icon){
   const row=document.createElement('a');row.className='agency-contact-row';row.href=href;
   const symbol=document.createElement('i');symbol.className='bi '+icon;symbol.setAttribute('aria-hidden','true');
   const details=document.createElement('span');details.className='agency-contact-copy';
   const caption=document.createElement('span');caption.className='agency-contact-label';caption.textContent=label;
   const content=document.createElement('span');content.className='agency-contact-value';content.textContent=value;
   details.append(caption,content);row.append(symbol,details);target.append(row);
  }
  if(contact.email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contact.email))contactRow('Email address',contact.email,'mailto:'+contact.email,'bi-envelope');
  if(contact.phone)contactRow('Contact number',contact.phone,'tel:'+contact.phone.replace(/[^+\d]/g,''),'bi-telephone');
 }
 const links=document.getElementById('landingOfficialLinks');for(const [key,label] of [['website','Official website'],['facebook','Facebook']]){try{const url=new URL(info[key]);if(url.protocol!=='https:')continue;const a=document.createElement('a');a.href=url.href;a.textContent=label;a.className='landing-text-link';a.target='_blank';a.rel='noopener noreferrer';links.append(a);}catch(e){}}
}).catch(()=>{});
})();
