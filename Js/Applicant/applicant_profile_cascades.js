/**
 * Applicant profile — database-driven searchable address cascade, job category, and classification dropdowns.
 */
(function (global) {
  'use strict';

  var CATEGORIES_API = '../../Api/Applicant/get_active_categories.php';
  var PROVINCES_API = '../../Api/Applicant/get_provinces.php';
  var CITIES_API = '../../Api/Applicant/get_cities.php';
  var BARANGAYS_API = '../../Api/Applicant/get_barangays.php';

  /** Default postal / ZIP codes keyed by province|city (barangay inherits city code). */
  var POSTAL_CODES = {
    'Misamis Oriental|Cagayan de Oro City': '9000',
    'Misamis Oriental|Gingoog City': '9014',
    'Misamis Oriental|El Salvador City': '9018'
  };

  /** Industry-standard manpower classifications → sub-classifications. */
  var CLASSIFICATION_CASCADE = {
    'Food & Beverage': ['Waiter/Waitress', 'Barista', 'Kitchen Staff', 'Chef', 'Cashier', 'Food Server'],
    'Retail & Sales': ['Sales Clerk', 'Cashier', 'Merchandiser', 'Promodiser', 'Retail Associate'],
    'Hospitality': ['Housekeeping', 'Front Desk Agent', 'Bellhop', 'Room Attendant'],
    'Security & Safety': ['Security Guard', 'Lady Guard', 'Safety Officer', 'CCTV Operator'],
    'Logistics & Warehouse': ['Warehouse Staff', 'Forklift Operator', 'Delivery Rider', 'Picker/Packer'],
    'Customer Service & BPO': ['Call Center Agent', 'Customer Support', 'Chat Support'],
    'Corporate & Admin': ['Office Staff', 'Data Encoder', 'Receptionist', 'Admin Assistant']
  };

  var els = {};
  var addressSelects = {
    province: null,
    city: null,
    barangay: null
  };

  var state = {
    province: '',
    city: '',
    barangay: '',
    provincesData: [],
    citiesData: [],
    barangaysData: []
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

  function postalKey(province, city) {
    return [province, city].map(function (s) { return String(s || '').trim(); }).join('|');
  }

  function applyPostalCode() {
    if (!els.postal) return;
    var key = postalKey(state.province, state.city);
    var code = POSTAL_CODES[key];
    if (code) els.postal.value = code;
  }

  function resetChildren(fromLevel) {
    if (fromLevel === 'province') {
      state.city = state.barangay = '';
      state.citiesData = [];
      state.barangaysData = [];
      if (addressSelects.city) {
        addressSelects.city.clear();
        addressSelects.city.setDisabled(true);
      }
      if (addressSelects.barangay) {
        addressSelects.barangay.clear();
        addressSelects.barangay.setDisabled(true);
      }
      if (els.postal) els.postal.value = '';
    } else if (fromLevel === 'city') {
      state.barangay = '';
      state.barangaysData = [];
      if (addressSelects.barangay) {
        addressSelects.barangay.clear();
        addressSelects.barangay.setDisabled(true);
      }
    }
  }

  function fetchJson(url) {
    return fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } })
      .then(function (res) {
        return res.text().then(function (text) {
          try {
            return text ? JSON.parse(text) : null;
          } catch (e) {
            return null;
          }
        });
      })
      .catch(function () {
        return null;
      });
  }

  function onProvinceSelect(provinceName) {
    if (state.province === provinceName) return;
    state.province = provinceName;
    resetChildren('province');

    var provinceObj = state.provincesData.find(function (p) { return p.name.trim() === provinceName.trim(); });
    if (!provinceObj) return;

    fetchJson(CITIES_API + '?province_id=' + encodeURIComponent(provinceObj.id))
      .then(function (res) {
        if (res && res.success && Array.isArray(res.data)) {
          state.citiesData = res.data;
          var cityNames = res.data.map(function (c) { return c.name; });
          addressSelects.city.setOptions(cityNames);
          addressSelects.city.setDisabled(!cityNames.length);
        }
      });
  }

  function onCitySelect(cityName) {
    if (state.city === cityName) return;
    state.city = cityName;
    resetChildren('city');
    applyPostalCode();

    var cityObj = state.citiesData.find(function (c) { return c.name.trim() === cityName.trim(); });
    if (!cityObj) return;

    fetchJson(BARANGAYS_API + '?city_id=' + encodeURIComponent(cityObj.id))
      .then(function (res) {
        if (res && res.success && Array.isArray(res.data)) {
          state.barangaysData = res.data;
          var barangayNames = res.data.map(function (b) { return b.name; });
          addressSelects.barangay.setOptions(barangayNames);
          addressSelects.barangay.setDisabled(!barangayNames.length);
        }
      });
  }

  function onBarangaySelect(barangayName) {
    state.barangay = barangayName;
    applyPostalCode();
  }

  /** Instantiate searchable address fields on the profile page. */
  function initAddressSearchableSelects() {
    if (typeof global.SearchableSelect !== 'function') {
      console.warn('SearchableSelect not loaded — address cascade skipped.');
      return;
    }

    var provinceWrap = $('addressProvinceWrap');
    var cityWrap = $('addressCityWrap');
    var barangayWrap = $('addressBarangayWrap');

    if (!provinceWrap) return;

    addressSelects.province = new SearchableSelect(provinceWrap, {
      placeholder: 'Select province',
      onSelect: onProvinceSelect
    });
    addressSelects.city = new SearchableSelect(cityWrap, {
      placeholder: 'Select city / municipality',
      onSelect: onCitySelect
    });
    addressSelects.barangay = new SearchableSelect(barangayWrap, {
      placeholder: 'Select barangay',
      onSelect: onBarangaySelect
    });

    addressSelects.province.setDisabled(true);
    addressSelects.city.setDisabled(true);
    addressSelects.barangay.setDisabled(true);

    fetchJson(PROVINCES_API)
      .then(function (res) {
        if (res && res.success && Array.isArray(res.data)) {
          state.provincesData = res.data;
          var provinceNames = res.data.map(function (p) { return p.name; });
          addressSelects.province.setOptions(provinceNames);
          addressSelects.province.setDisabled(false);
        }
      });
  }

  function populateClassificationOptions(selected) {
    if (!els.classification) return;
    var html = '<option value="" disabled' + (selected ? '' : ' selected') + '>Select classification</option>';
    Object.keys(CLASSIFICATION_CASCADE).forEach(function (label) {
      html += '<option value="' + esc(label) + '">' + esc(label) + '</option>';
    });
    els.classification.innerHTML = html;
    if (selected) els.classification.value = selected;
  }

  function populateSubClassificationOptions(classification, selected) {
    if (!els.subClassification) return;
    var list = CLASSIFICATION_CASCADE[classification] || [];
    var html = '<option value="" disabled' + (selected ? '' : ' selected') + '>Select sub-classification</option>';
    list.forEach(function (item) {
      html += '<option value="' + esc(item) + '">' + esc(item) + '</option>';
    });
    els.subClassification.innerHTML = html;
    els.subClassification.disabled = list.length === 0;
    if (selected) els.subClassification.value = selected;
  }

  function bindClassificationCascade() {
    populateClassificationOptions();
    populateSubClassificationOptions('', '');
    if (!els.classification) return;
    els.classification.addEventListener('change', function () {
      populateSubClassificationOptions(els.classification.value, '');
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
  function hydrateAddress(profile) {
    if (!profile || !addressSelects.province) return Promise.resolve();

    var province = String(profile.state_province || '').trim();
    var city = String(profile.city || '').trim();
    var barangay = String(profile.barangay || '').trim();

    state.province = state.city = state.barangay = '';

    return fetchJson(PROVINCES_API)
      .then(function (res) {
        if (res && res.success && Array.isArray(res.data)) {
          state.provincesData = res.data;
          var provinceNames = res.data.map(function (p) { return p.name; });
          addressSelects.province.setOptions(provinceNames);
          addressSelects.province.setDisabled(false);

          if (province) {
            addressSelects.province.setValue(province, true);
            state.province = province;
            var provinceObj = state.provincesData.find(function (p) { return p.name.trim().toLowerCase() === province.toLowerCase(); });
            if (provinceObj) {
              return fetchJson(CITIES_API + '?province_id=' + encodeURIComponent(provinceObj.id))
                .then(function (res2) {
                  if (res2 && res2.success && Array.isArray(res2.data)) {
                    state.citiesData = res2.data;
                    var cityNames = res2.data.map(function (c) { return c.name; });
                    addressSelects.city.setOptions(cityNames);
                    addressSelects.city.setDisabled(false);

                    if (city) {
                      addressSelects.city.setValue(city, true);
                      state.city = city;
                      var cityObj = state.citiesData.find(function (c) { return c.name.trim().toLowerCase() === city.toLowerCase(); });
                      if (cityObj) {
                        return fetchJson(BARANGAYS_API + '?city_id=' + encodeURIComponent(cityObj.id))
                          .then(function (res3) {
                            if (res3 && res3.success && Array.isArray(res3.data)) {
                              state.barangaysData = res3.data;
                              var barangayNames = res3.data.map(function (b) { return b.name; });
                              addressSelects.barangay.setOptions(barangayNames);
                              addressSelects.barangay.setDisabled(false);

                              if (barangay) {
                                addressSelects.barangay.setValue(barangay, true);
                                state.barangay = barangay;
                              }
                            }
                          });
                      } else {
                        addressSelects.barangay.setDisabled(false);
                        addressSelects.barangay.setValue(barangay, true);
                        state.barangay = barangay;
                      }
                    }
                  }
                });
            } else {
              state.city = city;
              state.barangay = barangay;
            }
          }
        }
      })
      .then(function () {
        if (els.postal && profile.postal_code) {
          els.postal.value = profile.postal_code;
        } else {
          applyPostalCode();
        }
        if (els.street && profile.street_address) els.street.value = profile.street_address;
      })
      .catch(function (err) {
        console.error('Failed to hydrate address:', err);
      });
  }

  function getAddressValues() {
    var prov = addressSelects.province ? addressSelects.province.getValue() : '';
    if (!prov && $('addressProvinceSelect')) {
      var lblP = $('addressProvinceSelect').querySelector('.searchable-select__trigger-label');
      if (lblP && !lblP.classList.contains('is-placeholder')) prov = lblP.textContent.trim();
    }
    var cit = addressSelects.city ? addressSelects.city.getValue() : '';
    if (!cit && $('addressCitySelect')) {
      var lblC = $('addressCitySelect').querySelector('.searchable-select__trigger-label');
      if (lblC && !lblC.classList.contains('is-placeholder')) cit = lblC.textContent.trim();
    }
    var bar = addressSelects.barangay ? addressSelects.barangay.getValue() : '';
    if (!bar && $('addressBarangaySelect')) {
      var lblB = $('addressBarangaySelect').querySelector('.searchable-select__trigger-label');
      if (lblB && !lblB.classList.contains('is-placeholder')) bar = lblB.textContent.trim();
    }

    return {
      country: 'Philippines',
      state_province: prov || state.province,
      city: cit || state.city,
      barangay: bar || state.barangay,
      postal_code: els.postal ? els.postal.value.trim() : '',
      street_address: els.street ? els.street.value.trim() : ''
    };
  }

  function getJobCategoryValue() {
    if (!els.jobCategory || !els.jobCategory.selectedOptions[0]) return '';
    var opt = els.jobCategory.selectedOptions[0];
    return opt.getAttribute('data-name') || opt.textContent || opt.value || '';
  }

  var initialized = false;

  function init() {
    if (initialized) return loadJobCategories();
    initialized = true;
    cacheElements();
    initAddressSearchableSelects();
    bindClassificationCascade();
    return loadJobCategories();
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
    getAddressValues: getAddressValues,
    getJobCategoryValue: getJobCategoryValue,
    CLASSIFICATION_CASCADE: CLASSIFICATION_CASCADE,
    POSTAL_CODES: POSTAL_CODES
  };
})(window);