/**
 * Applicant profile — database-driven searchable address cascade, job category, and classification dropdowns.
 */
(function (global) {
  'use strict';

  var CATEGORIES_API = '../../Api/Applicant/get_active_categories.php';
  var CLASSIFICATIONS_API = '../../Api/Applicant/get_classifications.php';
  var SUB_CLASSIFICATIONS_API = '../../Api/Applicant/get_sub_classifications.php';
  var REGIONS_API = '../../Api/Applicant/get_regions.php';
  var PROVINCES_API = '../../Api/Applicant/get_provinces.php';
  var CITIES_API = '../../Api/Applicant/get_cities.php';
  var BARANGAYS_API = '../../Api/Applicant/get_barangays.php';

  /** Default postal / ZIP codes keyed by province|city (barangay inherits city code). */
  var POSTAL_CODES = {
    'Misamis Oriental|Cagayan de Oro City': '9000',
    'Misamis Oriental|Gingoog City': '9014',
    'Misamis Oriental|El Salvador City': '9018'
  };

  var els = {};
  var addressSelects = {
    region: null,
    province: null,
    city: null,
    barangay: null
  };

  var state = {
    region: '',
    province: '',
    city: '',
    barangay: '',
    regionsData: [],
    provincesData: [],
    citiesData: [],
    barangaysData: [],
    classificationsData: [],
    subClassificationsData: []
  };

  function $(id) {
    return document.getElementById(id);
  }

  function esc(text) {
    if (text == null) return '';
    var d = document.createElement('div');
    d.textContent = String(text);
    return d.innerHTML;
  }

  function cacheElements() {
    els.postal = $('postalCodeInput');
    els.street = $('streetAddressInput');
    els.jobCategory = $('job-category');
    els.classification = $('classificationSelect');
    els.subClassification = $('subClassificationSelect');
  }

  var PSGC_API = '../../Api/Applicant/get_psgc_locations.php';
  var addressCodes = {region:'',province:'',city:'',barangay:''};
  var addressRequest = 0, addressReady = Promise.resolve(), retryAddress = null;
  function addressStatus(message, retry) {
    var box=$('addressLocationStatus');
    if(!box){box=document.createElement('div');box.id='addressLocationStatus';box.className='small text-muted mt-2';box.setAttribute('role','status');$('addressRegionWrap').parentElement.appendChild(box);}
    box.replaceChildren();box.appendChild(document.createTextNode(message||''));retryAddress=retry||null;
    if(retry){var button=document.createElement('button');button.type='button';button.className='btn btn-link btn-sm';button.textContent='Retry';button.onclick=function(){retry();};box.appendChild(button);}
  }
  function setAddressOptions(level, rows) {
    state[(level==='city'?'cities':level+'s')+'Data']=rows;
    addressSelects[level].setOptions(rows.map(function(r){return r.name;}));
    addressSelects[level].setDisabled(!rows.length);
  }
  function clearBelow(level) {
    var levels=['region','province','city','barangay'];
    levels.slice(levels.indexOf(level)+1).forEach(function(child){state[child]='';addressCodes[child]='';state[(child==='city'?'cities':child+'s')+'Data']=[];addressSelects[child].clear();addressSelects[child].setOptions([]);addressSelects[child].setDisabled(true);});
  }
  function addressChanged(){document.dispatchEvent(new Event('applicant-address-change'));}
  function rowFor(level,name){return (state[(level==='city'?'cities':level+'s')+'Data']||[]).find(function(r){return r.name.trim()===String(name).trim();});}
  async function loadAddress(level,parent,request) {
    addressStatus('Loading '+(level==='cities'?'cities and municipalities':level)+'…');
    var response=await fetch(PSGC_API+'?level='+level+(parent?'&parent='+encodeURIComponent(parent):''),{credentials:'include',cache:'no-store'});
    var result=await response.json();
    if(request!==addressRequest)return null;
    if(!response.ok||!result.success)throw new Error(result.message||'Unable to load locations. Please try again.');
    addressStatus('');return result;
  }
  async function onRegionSelect(name) {
    var request=++addressRequest;state.region=name;var row=rowFor('region',name);addressCodes.region=row?row.id:'';clearBelow('region');
    addressChanged();if(!row)return;
    try{var result=await loadAddress('provinces',row.id,request);if(!result)return;
      var rows=result.data.slice();setAddressOptions('province',rows);
      if(!rows.length)addressSelects.province.setValue('Not applicable',true);
      // Region-only cities remain accessible without a synthetic province option.
      if(result.has_direct_cities){var cities=await loadAddress('cities',row.id,request);if(cities)setAddressOptions('city',cities.data);}
    }catch(e){if(request===addressRequest)addressStatus(e.message,function(){onRegionSelect(name);});}
    addressChanged();
  }
  async function onProvinceSelect(name) {
    var request=++addressRequest;var row=rowFor('province',name);state.province=name;addressCodes.province=row?row.id:'';clearBelow('province');
    addressChanged();if(!row)return;
    try{var result=await loadAddress('cities',row.id,request);if(result)setAddressOptions('city',result.data);}
    catch(e){if(request===addressRequest)addressStatus(e.message,function(){onProvinceSelect(name);});}
    addressChanged();
  }
  async function onCitySelect(name) {
    var request=++addressRequest;state.city=name;var row=rowFor('city',name);addressCodes.city=row?row.id:'';clearBelow('city');
    if(!row)return;
    // A geographic selector grouping can differ from the official saved province.
    addressCodes.province=row.province||'';
    addressChanged();
    try{var result=await loadAddress('barangays',row.id,request);if(result)setAddressOptions('barangay',result.data);}
    catch(e){if(request===addressRequest)addressStatus(e.message,function(){onCitySelect(name);});}
  }
  function onBarangaySelect(name){state.barangay=name;var row=rowFor('barangay',name);addressCodes.barangay=row?row.id:'';addressChanged();}
  function initAddressSearchableSelects() {
    if(typeof global.SearchableSelect!=='function'||!$('addressRegionWrap'))return;
    ['region','province','city','barangay'].forEach(function(level){var title=level.charAt(0).toUpperCase()+level.slice(1);addressSelects[level]=new SearchableSelect($('address'+title+'Wrap'),{placeholder:'Select '+(level==='city'?'city / municipality':level),onSelect:{region:onRegionSelect,province:onProvinceSelect,city:onCitySelect,barangay:onBarangaySelect}[level]});addressSelects[level].setDisabled(true);});
    function regions(){var request=++addressRequest;addressReady=loadAddress('regions','',request).then(function(result){if(result)setAddressOptions('region',result.data);}).catch(function(e){addressStatus(e.message,regions);});return addressReady;}
    regions();
  }
  function fetchJson(url) {
    return fetch(url,{credentials:'include',headers:{Accept:'application/json'}}).then(function(r){return r.json();}).catch(function(){return null;});
  }

  /** 'new to industry' vs 'has experience' — same radio group Js/Applicant/applicant_profile_form.js drives. */
  function getExpMode() {
    var checked = document.querySelector('input[name="expMode"]:checked');
    return checked ? checked.value : null; // '1' = has experience, '0' = new to industry
  }

  /**
   * Populate the Classification dropdown for a given job category (its id).
   * If the applicant is in "New to the industry" mode, the classification is
   * auto-determined (the category's default classification) and the select
   * is locked — the applicant is not meant to choose it manually in that mode.
   */
  function populateClassificationOptions(categoryId, selectedName, selectedSubName) {
    if (!els.classification) return Promise.resolve([]);
    if (!categoryId) {
      els.classification.innerHTML = '<option value="" disabled selected>Select classification</option>';
      els.classification.disabled = true;
      state.classificationsData = [];
      populateSubClassificationOptions(null, '');
      return Promise.resolve([]);
    }
    return fetchJson(CLASSIFICATIONS_API + '?category_id=' + encodeURIComponent(categoryId))
      .then(function (res) {
        var list = (res && res.success && Array.isArray(res.data)) ? res.data : [];
        state.classificationsData = list;

        var html = '<option value="" disabled' + (selectedName ? '' : ' selected') + '>Select classification</option>';
        list.forEach(function (c) {
          html += '<option value="' + esc(c.name) + '" data-id="' + esc(c.id) + '">' + esc(c.name) + '</option>';
        });
        els.classification.innerHTML = html;
        els.classification.disabled = list.length === 0;

        var expMode = getExpMode();
        if (expMode === '0' && list.length) {
          // New to the industry: auto-determine ONLY when nothing was saved
          // yet. A previously-saved classification (restoring on page
          // load/reload) must be honored, not silently overwritten with the
          // category default — that was discarding the applicant's saved
          // Preferred Classification/Sub-classification on every reload.
          if (selectedName) {
            var savedMatch = null;
            for (var s = 0; s < list.length; s++) {
              if (list[s].name === selectedName) { savedMatch = list[s]; break; }
            }
            if (savedMatch) {
              els.classification.value = savedMatch.name;
              els.classification.disabled = true;
              return populateSubClassificationOptions(savedMatch.id, selectedSubName || '').then(function () { return list; });
            }
          }
          var def = null;
          for (var i = 0; i < list.length; i++) {
            if (list[i].is_default) { def = list[i]; break; }
          }
          if (!def) def = list[0];
          els.classification.value = def.name;
          els.classification.disabled = true;
          return populateSubClassificationOptions(def.id, selectedSubName || '').then(function () { return list; });
        }

        if (selectedName) {
          els.classification.value = selectedName;
          var match = null;
          for (var j = 0; j < list.length; j++) {
            if (list[j].name === selectedName) { match = list[j]; break; }
          }
          return populateSubClassificationOptions(match ? match.id : null, selectedSubName || '').then(function () { return list; });
        }

        populateSubClassificationOptions(null, '');
        document.dispatchEvent(new Event('applicant-profile-field-change'));
        return list;
      })
      .catch(function () {
        els.classification.innerHTML = '<option value="" disabled selected>Select classification</option>';
        return [];
      });
  }

  function populateSubClassificationOptions(classificationId, selectedName) {
    if (!els.subClassification) return Promise.resolve([]);
    if (!classificationId) {
      els.subClassification.innerHTML = '<option value="" disabled selected>Select sub-classification</option>';
      els.subClassification.disabled = true;
      state.subClassificationsData = [];
      return Promise.resolve([]);
    }
    return fetchJson(SUB_CLASSIFICATIONS_API + '?classification_id=' + encodeURIComponent(classificationId))
      .then(function (res) {
        var list = (res && res.success && Array.isArray(res.data)) ? res.data : [];
        state.subClassificationsData = list;
        var html = '<option value="" disabled' + (selectedName ? '' : ' selected') + '>Select sub-classification</option>';
        list.forEach(function (s) {
          html += '<option value="' + esc(s.name) + '" data-id="' + esc(s.id) + '">' + esc(s.name) + '</option>';
        });
        els.subClassification.innerHTML = html;
        els.subClassification.disabled = list.length === 0;
        if (selectedName) els.subClassification.value = selectedName;
        document.dispatchEvent(new Event('applicant-profile-field-change'));
        return list;
      })
      .catch(function () {
        els.subClassification.innerHTML = '<option value="" disabled selected>Select sub-classification</option>';
        return [];
      });
  }

  /**
   * Restore a saved profile's classification/sub-classification, scoped to
   * its job category. Called AFTER the job category select has resolved
   * (see applicant_profile_form.js), since classification now depends on it.
   */
  function hydrateClassification(categoryId, classificationName, subClassificationName) {
    if (!categoryId) return Promise.resolve();
    return populateClassificationOptions(categoryId, classificationName || '', subClassificationName || '');
  }

  function bindClassificationCascade() {
    populateClassificationOptions(null, '');

    if (els.jobCategory) {
      els.jobCategory.addEventListener('change', function () {
        populateClassificationOptions(els.jobCategory.value, '');
      });
    }

    if (els.classification) {
      els.classification.addEventListener('change', function () {
        var match = null;
        for (var i = 0; i < state.classificationsData.length; i++) {
          if (state.classificationsData[i].name === els.classification.value) { match = state.classificationsData[i]; break; }
        }
        populateSubClassificationOptions(match ? match.id : null, '');
      });
    }

    // Switching "I have experience" <-> "New to the industry" changes
    // whether classification is manually chosen or auto-determined.
    var expRadios = document.querySelectorAll('input[name="expMode"]');
    Array.prototype.forEach.call(expRadios, function (radio) {
      radio.addEventListener('change', function () {
        if (!radio.checked || !els.jobCategory || !els.jobCategory.value) return;
        if (radio.value === '0') {
          populateClassificationOptions(els.jobCategory.value, '');
        } else if (els.classification) {
          els.classification.disabled = state.classificationsData.length === 0;
        }
      });
    });
  }

  function loadJobCategories(selectedValue) {
    if (!els.jobCategory) return Promise.resolve([]);
    return fetch(CATEGORIES_API, { credentials: 'include', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return null; }); })
      .then(function (d) {
        var categories = (d && d.success && Array.isArray(d.categories)) ? d.categories : [];
        var html = '<option value="" disabled selected>Select job category</option>';
        categories.forEach(function (cat) {
          var id = String(cat.id);
          var name = cat.category_name || cat.name || id;
          html += '<option value="' + esc(id) + '" data-name="' + esc(name) + '">' + esc(name) + '</option>';
        });
        els.jobCategory.innerHTML = html;
        if (selectedValue) {
          Array.prototype.forEach.call(els.jobCategory.options, function (opt) {
            if (opt.value === String(selectedValue) || opt.getAttribute('data-name') === selectedValue || opt.textContent === selectedValue) {
              els.jobCategory.value = opt.value;
            }
          });
        }
        return categories;
      })
      .catch(function () {
        els.jobCategory.innerHTML = '<option value="" disabled selected>Select job category</option>';
        return [];
      });
  }

  /** Restore saved profile address into searchable fields in correct sequence. */
  async function hydrateAddress(profile) {
    if(!profile||!addressSelects.region)return;
    await addressReady;
    var codes=profile.address_psgc;
    if(codes){
      var region=state.regionsData.find(function(r){return r.id===codes.region;});
      if(region){addressSelects.region.setValue(region.name,true);await onRegionSelect(region.name);
        var province=state.provincesData.find(function(r){return r.id===(profile.address_selector_province||codes.province||'');});
        if(province){addressSelects.province.setValue(province.name,true);await onProvinceSelect(province.name);}
          var city=state.citiesData.find(function(r){return r.id===codes.city;});
          if(city){addressSelects.city.setValue(city.name,true);await onCitySelect(city.name);
            var barangay=state.barangaysData.find(function(r){return r.id===codes.barangay;});
            if(barangay){addressSelects.barangay.setValue(barangay.name,true);onBarangaySelect(barangay.name);}
          }
      }
    }else if(profile.city||profile.barangay){
      ['region','province','city','barangay'].forEach(function(level){var value=profile[level==='province'?'state_province':level]||'';state[level]=value;addressSelects[level].setValue(value,true);});
      addressStatus('Your saved address is retained. Please select its PSGC locations before saving; the old names could not be matched unambiguously.');
    }
    if(els.postal)els.postal.value=profile.postal_code||'';
    if(els.street)els.street.value=profile.street_address||'';
  }

  function getAddressValues() {
    return {country:'Philippines',region:state.region,state_province:state.province,city:state.city,barangay:state.barangay,
      province_required:!!addressCodes.region && !state.province && state.provincesData.length>0 && !state.citiesData.length,
      address_psgc:Object.assign({},addressCodes),postal_code:els.postal?els.postal.value.trim():'',street_address:els.street?els.street.value.trim():''};
  }

  function getJobCategoryValue() {
    if (!els.jobCategory || !els.jobCategory.selectedOptions[0]) return '';
    var opt = els.jobCategory.selectedOptions[0];
    return opt.getAttribute('data-name') || opt.textContent || opt.value || '';
  }

  var initialized = false;
  var initPromise = null;

  function init() {
    // Idempotent: the caller (applicant_profile_form.js) fires this both on
    // its own DOMContentLoaded AND again right before restoring saved
    // profile values. Previously every post-first call re-ran
    // loadJobCategories() with no selected value, which could race the
    // real restore call and clobber the just-restored Job Category
    // selection back to blank. Once initialized, just resolve — categories
    // are already loaded by the first call below.
    if (initialized) return initPromise || Promise.resolve();
    initialized = true;
    cacheElements();
    initAddressSearchableSelects();
    bindClassificationCascade();
    initPromise = loadJobCategories();
    return initPromise;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      if (document.getElementById('addressProvinceWrap')) init();
    });
  } else if (document.getElementById('addressProvinceWrap')) {
    init();
  }

  global.ApplicantProfileCascades = {
    init: init,
    hydrateAddress: hydrateAddress,
    loadJobCategories: loadJobCategories,
    populateClassificationOptions: populateClassificationOptions,
    populateSubClassificationOptions: populateSubClassificationOptions,
    hydrateClassification: hydrateClassification,
    getAddressValues: getAddressValues,
    getJobCategoryValue: getJobCategoryValue,
    POSTAL_CODES: POSTAL_CODES
  };
})(window);
