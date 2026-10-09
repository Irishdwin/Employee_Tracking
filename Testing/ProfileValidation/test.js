const fs=require('fs'),vm=require('vm'),assert=require('assert');
const elements={},listeners={},order=[];let focused='';
function element(id){const attrs={},classes=new Set();return {id,disabled:false,hidden:false,textContent:'',style:{},parentElement:null,
classList:{toggle(k,v){v?classes.add(k):classes.delete(k)},contains:k=>classes.has(k)},setAttribute(k,v){attrs[k]=v},getAttribute:k=>attrs[k]??null,removeAttribute(k){delete attrs[k]},closest(){return null},insertAdjacentElement(_,el){elements[el.id]=el},scrollIntoView(){},focus(){focused=id}};}
const ids=['sexInput','firstNameInput','middleNameInput','lastNameInput','birthDateInput','addressRegionSelect','addressProvinceSelect','addressCitySelect','addressBarangaySelect','postalCodeInput','streetAddressInput','job-category','jobTitleInput','companyNameInput','startMonthSelect','startYearSelect','endMonthSelect','endYearSelect','classificationSelect','subClassificationSelect'];
ids.forEach(id=>{elements[id]=element(id);order.push(elements[id]);});
const form={addEventListener(k,v){listeners[k]=v},querySelectorAll(){return order.filter(e=>e.getAttribute('aria-invalid')==='true')}};
const summary=element('summary');const context={window:{},document:{getElementById:id=>elements[id]||null,createElement:()=>element(''),addEventListener(k,v){listeners[k]=v}},Date,JSON,Array};
vm.createContext(context);vm.runInContext(fs.readFileSync('Js/Applicant/applicant_profile_validation.js','utf8'),context);
const api=context.window.ApplicantProfileValidation, data=JSON.parse(fs.readFileSync('Testing/PSGC/official_import.json','utf8'));
const b=data.locations.find(n=>n.city==='1030500000'&&/^Barangay 10\b/.test(n.name));assert(b);
const complete={gender:'Female',first_name:'Juan',middle_name:'',last_name:'Dela Cruz',birth_date:'2000-01-01',address_psgc:{region:'1000000000',province:'',city:'1030500000',barangay:b.id},postal_code:'9000',street_address:'phase 1',category_id:'1',classification_id:'1',sub_classification_id:'11',has_recent_experience:0,province_required:false};
let p=structuredClone(complete),ui=api.create(form,summary,()=>p);
assert.equal(form.querySelectorAll().length,0);assert.equal(summary.textContent,'');
p.postal_code='';assert.equal(ui.check(),false);assert.equal(form.querySelectorAll().length,1);assert.equal(focused,'postalCodeInput');assert.equal(elements.postalCodeInputValidationError.textContent,'Postal / ZIP Code is required.');assert.equal(p.street_address,'phase 1');
p.postal_code='9000';listeners.input();assert.equal(form.querySelectorAll().length,0);assert.equal(elements.postalCodeInput.getAttribute('aria-invalid'),null);
p.street_address='';ui.check();assert.equal(focused,'streetAddressInput');assert.equal(form.querySelectorAll().length,1);
p=structuredClone(complete);p.address_psgc.barangay='';ui.check();assert.equal(focused,'addressBarangaySelect');assert.equal(form.querySelectorAll().length,1);
p=structuredClone(complete);p.address_psgc.region='';ui.check();assert.equal(focused,'addressRegionSelect');
p=structuredClone(complete);p.address_psgc.barangay='';p.postal_code='';p.street_address='';ui.check();assert.equal(form.querySelectorAll().length,3);assert.equal(focused,'addressBarangaySelect');
p.postal_code='9000';listeners.input();assert.equal(form.querySelectorAll().length,2);assert(!elements.postalCodeInput.classList.contains('is-invalid'));
p=structuredClone(complete);assert(ui.check());assert.equal(form.querySelectorAll().length,0);
const ncr={...complete,address_psgc:{region:'1300000000',province:'',city:'1380600000',barangay:'1380601001'}};assert.equal(Object.keys(api.validate(ncr)).length,0);
ui.apply({city:'City does not belong to the selected Region.'});assert.equal(elements.addressCitySelect.getAttribute('aria-invalid'),'true');listeners.input();assert.equal(elements.addressCitySelect.getAttribute('aria-invalid'),'true');p.address_psgc.city='1380600000';listeners['applicant-address-change']();assert.equal(elements.addressCitySelect.getAttribute('aria-invalid'),null);
assert.equal(api.validate({...complete,postal_code:'abc'}).postal_code,'Please enter a valid four-digit Postal / ZIP Code.');
const missing=api.validate({...complete,first_name:'',last_name:'',birth_date:'',category_id:'',classification_id:'',sub_classification_id:'',has_recent_experience:1,still_in_role:0});assert.equal(Object.keys(missing).length,12);
console.log('PASS: exact CDO postal-only error, street/barangay/region errors, all errors marked, first focus, input preserved, correction clears one error, complete/NCR validation, backend mapping and no initial invalid state');
