(function (global) {
    'use strict';
    var fields = {
        gender:'sexInput', first_name:'firstNameInput', middle_name:'middleNameInput', last_name:'lastNameInput', birth_date:'birthDateInput',
        region:'addressRegionSelect', state_province:'addressProvinceSelect', city:'addressCitySelect', barangay:'addressBarangaySelect',
        postal_code:'postalCodeInput', street_address:'streetAddressInput', category_id:'job-category',
        job_title:'jobTitleInput', company_name:'companyNameInput', experience_start_month:'startMonthSelect', experience_start_year:'startYearSelect',
        experience_end_month:'endMonthSelect', experience_end_year:'endYearSelect', classification_id:'classificationSelect', sub_classification_id:'subClassificationSelect'
    };
    function validate(p, now) {
        var errors = {}, names = /^[A-Za-zÑñ\s.\'\-]+$/;
        ['first_name','last_name','middle_name'].forEach(function (key) {
            var label = {first_name:'First Name',last_name:'Last Name',middle_name:'Middle Name'}[key];
            if (!p[key] && key !== 'middle_name') errors[key] = label + ' is required.';
            else if (p[key] && (!names.test(p[key]) || (key !== 'middle_name' && p[key].length < 2))) errors[key] = label + ' must contain at least two letters and only letters, spaces, periods, apostrophes or hyphens.';
        });
        if (!['Male','Female','Other','Prefer not to say'].includes(p.gender)) errors.gender = 'Please select your sex.';
        if (!p.birth_date) errors.birth_date = 'Date of Birth is required.';
        else {
            var parts = String(p.birth_date).split('-'), y=Number(parts[0]), m=Number(parts[1]), d=Number(parts[2]);
            var dob=new Date(y,m-1,d), today=now || new Date();
            var age=today.getFullYear()-y; if(today.getMonth()+1<m || (today.getMonth()+1===m && today.getDate()<d))age--;
            if(!/^\d{4}-\d{2}-\d{2}$/.test(p.birth_date) || dob.getFullYear()!==y || dob.getMonth()+1!==m || dob.getDate()!==d || dob>today) errors.birth_date='Please enter a valid Date of Birth.';
            else if(age<18) errors.birth_date='You must be at least 18 years old to register.';
        }
        var codes=p.address_psgc || {};
        ['region','city','barangay'].forEach(function(key){if(!/^\d{10}$/.test(codes[key] || ''))errors[key]={region:'Please select a Region.',city:'Please select a City / Municipality.',barangay:'Please select a Barangay.'}[key];});
        if(p.province_required && !codes.province)errors.state_province='Please select a Province / geographic path.';
        if(!p.postal_code)errors.postal_code='Postal / ZIP Code is required.';
        else if(!/^\d{4}$/.test(p.postal_code))errors.postal_code='Please enter a valid four-digit Postal / ZIP Code.';
        if(!p.street_address)errors.street_address='Street Address is required.';
        else if(p.street_address.length>255 || /[\x00-\x1f<>]/.test(p.street_address))errors.street_address='Please enter a valid Street Address (maximum 255 characters).';
        if(!p.category_id)errors.category_id='Please select a Job Category.';
        if(!p.classification_id)errors.classification_id='Please select a Classification.';
        if(!p.sub_classification_id)errors.sub_classification_id='Please select a Sub-classification.';
        if(p.has_recent_experience){
            if(!p.job_title)errors.job_title='Job Title is required when you have experience.';
            if(!p.company_name)errors.company_name='Company Name is required when you have experience.';
            ['experience_start_month','experience_start_year'].concat(p.still_in_role?[]:['experience_end_month','experience_end_year']).forEach(function(key){
                if(!p[key])errors[key]='Please select your experience '+(key.indexOf('start')!==-1?'start ':'end ')+(key.indexOf('month')!==-1?'month.':'year.');
            });
        }
        return errors;
    }
    function create(form, summary, readPayload) {
        var active={}, attempted=false, serverErrors={};
        function signature(key,p){
            if(['region','state_province','city','barangay'].indexOf(key)!==-1)return JSON.stringify(p.address_psgc);
            if(['category_id','classification_id','sub_classification_id'].indexOf(key)!==-1)return JSON.stringify([p.category_id,p.classification_id,p.sub_classification_id]);
            return JSON.stringify(p[key]);
        }
        function control(key){return document.getElementById(fields[key]);}
        function mark(key, message){
            var el=control(key);if(!el)return;
            var id=el.id+'ValidationError', error=document.getElementById(id);
            if(message && !error){error=document.createElement('div');error.id=id;error.className='apm-field-error';error.setAttribute('aria-live','polite');
                var anchor=el.closest('.searchable-select') || el;anchor.insertAdjacentElement('afterend',error);}
            el.classList.toggle('is-invalid',!!message);
            var described=(el.getAttribute('aria-describedby') || '').split(/\s+/).filter(function(s){return s && s!==id;});
            if(message){el.setAttribute('aria-invalid','true');described.push(id);active[key]=message;}
            else {el.removeAttribute('aria-invalid');delete active[key];}
            if(described.length)el.setAttribute('aria-describedby',described.join(' '));else el.removeAttribute('aria-describedby');
            if(error){error.textContent=message || '';error.hidden=!message;}
        }
        function updateSummary(){
            if(!summary)return;var count=Object.keys(active).length;
            summary.textContent=count?'Please complete the highlighted required field'+(count===1?'.':'s.'):'';
            summary.style.display=count?'block':'none';summary.classList.toggle('d-none',!count);
        }
        function focusFirst(){
            var invalid=Array.from(form.querySelectorAll('[aria-invalid="true"]'));
            var first=invalid[0];if(!first)return;
            first.scrollIntoView({behavior:global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'center'});
            var focus=first;
            if(first.disabled){focus=first.closest('.searchable-select') || first.parentElement;focus.setAttribute('tabindex','-1');}
            focus.focus({preventScroll:true});
        }
        function apply(errors, focus){attempted=true;Object.keys(fields).forEach(function(key){mark(key,errors[key]);});updateSummary();if(focus)focusFirst();return !Object.keys(active).length;}
        function refresh(){if(!attempted)return;var p=readPayload(),errors=validate(p);Object.keys(serverErrors).forEach(function(key){if(signature(key,p)===serverErrors[key].signature)errors[key]=serverErrors[key].message;else delete serverErrors[key];});Object.keys(active).forEach(function(key){mark(key,errors[key]);});updateSummary();}
        form.addEventListener('input',refresh);form.addEventListener('change',refresh);
        // Custom PSGC controls update state asynchronously instead of dispatching input/change.
        document.addEventListener('applicant-address-change',refresh);
        document.addEventListener('applicant-profile-field-change',refresh);
        return {check:function(){serverErrors={};return apply(validate(readPayload()),true);},apply:function(errors){var p=readPayload();serverErrors={};Object.keys(errors).forEach(function(key){serverErrors[key]={message:errors[key],signature:signature(key,p)};});return apply(errors,true);},refresh:refresh};
    }
    global.ApplicantProfileValidation={validate:validate,create:create};
})(window);
