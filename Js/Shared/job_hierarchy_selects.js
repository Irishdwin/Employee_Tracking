(function(global){
  'use strict';
  var treePromise;
  function tree(){if(!treePromise)treePromise=fetch('../../Api/Common/job_hierarchy.php',{credentials:'same-origin',cache:'no-store'}).then(function(r){if(!r.ok)throw Error('Hierarchy unavailable');return r.json();}).then(function(d){if(!d.success)throw Error(d.message);return d.categories;}).catch(function(e){treePromise=null;throw e;});return treePromise;}
  function options(select,rows,label,key,value){select.replaceChildren(new Option(label,''));rows.forEach(function(r){select.add(new Option(r[key],String(r.id)));});select.value=String(value||'');}
  function attach(root,filter){
    if(root._jobHierarchy)return root._jobHierarchy;
    var old=root.querySelector('select[name="job_category"]');if(old)old.closest('.mb-3').remove();
    var row=document.createElement('div');row.className=filter?'row g-2 mt-2':'row';
    var controls={};[['category_id','Job Category'],['classification_id','Classification'],['sub_classification_id','Sub-classification']].forEach(function(field){var col=document.createElement('div');col.className='col-12 col-md-4 mb-3';var label=document.createElement('label');label.className='form-label';label.textContent=field[1]+(filter?'':' *');var select=document.createElement('select');select.name=field[0];select.className='form-select';select.id=(root.id||'hierarchy')+'-'+field[0];select.required=!filter;select.dataset.hierarchySelect='1';label.htmlFor=select.id;col.append(label,select);row.append(col);controls[field[0]]=select;});if(root.dataset.hierarchyStyle==='tailwind'){row.className='mt-3 grid gap-3 sm:grid-cols-2';Array.from(row.children).forEach(function(col,index){col.className=index===2?'min-w-0 sm:col-span-2':'min-w-0';col.querySelector('label').className='mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500';col.querySelector('select').className='w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100';});}root.prepend(row);controls.category_id.add(new Option('Select job category',''));controls.classification_id.add(new Option('Select classification',''));controls.sub_classification_id.add(new Option('Select sub-classification',''));controls.classification_id.disabled=true;controls.sub_classification_id.disabled=true;
    var cats=[],loaded=false;
    function category(){return cats.find(function(c){return String(c.id)===controls.category_id.value;});}
    function classes(value,sub){var cat=category();options(controls.classification_id,cat?cat.classifications:[],filter?'All classifications':'Select classification','classification_name',value);subs(sub);}
    function subs(value){var cat=category(),cls=cat&&cat.classifications.find(function(c){return String(c.id)===controls.classification_id.value;});options(controls.sub_classification_id,cls?cls.sub_classifications:[],filter?'All sub-classifications':'Select sub-classification','sub_classification_name',value);controls.classification_id.disabled=!cat;controls.sub_classification_id.disabled=!cls;}
    function changed(){root.dispatchEvent(new CustomEvent('job-hierarchy-change',{bubbles:true}));}
    controls.category_id.addEventListener('change',function(){classes();changed();});controls.classification_id.addEventListener('change',function(){subs();changed();});controls.sub_classification_id.addEventListener('change',changed);
    var state={set:function(values){return tree().then(function(data){cats=data;loaded=true;options(controls.category_id,cats,filter?'All categories':'Select job category','category_name',values.category_id);classes(values.classification_id,values.sub_classification_id);});},values:function(){return {category_id:controls.category_id.value,classification_id:controls.classification_id.value,sub_classification_id:controls.sub_classification_id.value};}};
    root._jobHierarchy=state;state.set({}).catch(function(){row.textContent='Could not load job hierarchy. Reload this page to retry.';});
    root.addEventListener('reset',function(){if(loaded)setTimeout(function(){state.set({});},0);});return state;
  }
  function init(){document.querySelectorAll('#createJobOrderForm,#editJobOrderForm,[data-job-hierarchy]').forEach(function(root){attach(root,root.dataset.jobHierarchy==='filter');});}
  global.JobHierarchySelectors={attach:attach,set:function(root,values){return attach(root,false).set(values);},values:function(root){return root&&root._jobHierarchy?root._jobHierarchy.values():{};}};
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})(window);
