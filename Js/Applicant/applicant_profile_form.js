(function () {
    var STATUS_API = '../../Api/Applicant/get_profile_status.php';
    var SETUP_GET_API = '../../Api/Applicant/get_applicant_profile_setup.php';
    var SETUP_SAVE_API = '../../Api/Applicant/save_applicant_profile_setup.php';
    var UPLOAD_DOC_API = '../../Api/Applicant/upload_applicant_document.php';
    var DASHBOARD = '../../Html/Applicant/applicant_landing.html';

    var form = document.getElementById('almostDoneForm');
    var errorBox = document.getElementById('almostDoneError');

    var expHave = document.getElementById('expHave');
    var expNew = document.getElementById('expNew');
    var expFields = document.getElementById('expFields');
    var expFieldsWrap = document.getElementById('expFieldsWrap');
    var expNewInfo = document.getElementById('expNewInfo');
    var stillInRoleCheckbox = document.getElementById('stillInRoleCheckbox');
    var endMonthSelect = document.getElementById('endMonthSelect');
    var endYearSelect = document.getElementById('endYearSelect');

    var startMonthSelect = document.getElementById('startMonthSelect');
    var startYearSelect = document.getElementById('startYearSelect');

    var firstNameInput = document.getElementById('firstNameInput');
    var middleNameInput = document.getElementById('middleNameInput');
    var lastNameInput = document.getElementById('lastNameInput');
    var hasSuffixCheckbox = document.getElementById('hasSuffixCheckbox');
    var suffixSelect = document.getElementById('suffixSelect');

    var birthDateInput = document.getElementById('birthDateInput');
    var birthDateError = document.getElementById('birthDateError');
    var btnSaveAndContinue = document.getElementById('btnSaveAndContinue');
    var streetAddressInput = document.getElementById('streetAddressInput');
    var postalCodeInput = document.getElementById('postalCodeInput');
    var jobTitleInput = document.getElementById('jobTitleInput');
    var companyNameInput = document.getElementById('companyNameInput');
    var jobCategorySelect = document.getElementById('job-category');
    var classificationSelect = document.getElementById('classificationSelect');
    var subClassificationSelect = document.getElementById('subClassificationSelect');

    var uploadedDocsMap = {};
    var pendingDocumentUploads = 0;

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    var cascadesReady = false;

    function ensureCascadesReady() {
        if (cascadesReady || !window.ApplicantProfileCascades) return Promise.resolve();
        return window.ApplicantProfileCascades.init().then(function () {
            cascadesReady = true;
        });
    }

    function dobIsoLocal(y, m, d) {
        var pad = function (n) { return n < 10 ? '0' + n : String(n); };
        return y + '-' + pad(m) + '-' + pad(d);
    }

    function setBirthDateBounds() {
        if (!birthDateInput) return;
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var maxD = new Date(today);
        var minD = new Date(today.getFullYear() - 120, today.getMonth(), today.getDate());
        birthDateInput.max = dobIsoLocal(maxD.getFullYear(), maxD.getMonth() + 1, maxD.getDate());
        birthDateInput.min = dobIsoLocal(minD.getFullYear(), minD.getMonth() + 1, minD.getDate());
    }

    function ageFromIsoDate(iso) {
        if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return null;
        var p = iso.split('-');
        var y = parseInt(p[0], 10);
        var m = parseInt(p[1], 10) - 1;
        var d = parseInt(p[2], 10);
        var birth = new Date(y, m, d);
        if (birth.getFullYear() !== y || birth.getMonth() !== m || birth.getDate() !== d) return null;
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        birth.setHours(0, 0, 0, 0);
        if (birth > today) return -1;
        var age = today.getFullYear() - birth.getFullYear();
        var md = today.getMonth() - birth.getMonth();
        if (md < 0 || (md === 0 && today.getDate() < birth.getDate())) age--;
        return age;
    }

    function syncBirthDateUi() {
        if (!birthDateInput || !birthDateError) return;
        birthDateError.classList.remove('is-visible'); // Shared field validation owns DOB feedback.

    }

    if (birthDateInput) {
        setBirthDateBounds();
        birthDateInput.addEventListener('input', syncBirthDateUi);
        birthDateInput.addEventListener('change', syncBirthDateUi);
    }

    if (hasSuffixCheckbox && suffixSelect) {
        hasSuffixCheckbox.addEventListener('change', function () {
            var checked = this.checked;
            suffixSelect.disabled = !checked;
            if (!checked) suffixSelect.value = '';
        });
    }

    ensureCascadesReady();

    function fillMonths(selectEl) {
        if (!selectEl) return;
        for (var m = 1; m <= 12; m++) {
            var opt = document.createElement('option');
            opt.value = String(m);
            opt.textContent = new Date(2000, m - 1, 1).toLocaleString(undefined, { month: 'long' });
            selectEl.appendChild(opt);
        }
    }

    function fillYears(selectEl) {
        if (!selectEl) return;
        var currentYear = new Date().getFullYear();
        for (var y = currentYear; y >= currentYear - 40; y--) {
            var opt = document.createElement('option');
            opt.value = String(y);
            opt.textContent = String(y);
            selectEl.appendChild(opt);
        }
    }

    fillMonths(startMonthSelect);
    fillMonths(document.getElementById('endMonthSelect'));
    fillYears(startYearSelect);
    fillYears(document.getElementById('endYearSelect'));

    function setExpMode(mode) {
        if (!expHave || !expNew || !expFields || !expNewInfo) return;
        if (mode === '1') {
            expHave.checked = true;
            expFields.classList.remove('d-none');
            expNewInfo.classList.add('d-none');
            if (expFieldsWrap) {
                expFieldsWrap.classList.add('is-open');
                expFieldsWrap.setAttribute('aria-hidden', 'false');
            }

            if (jobTitleInput) jobTitleInput.required = true;
            if (companyNameInput) companyNameInput.required = true;
            if (startMonthSelect) startMonthSelect.required = true;
            if (startYearSelect) startYearSelect.required = true;
            syncEndDateDisabled();
        } else {
            expNew.checked = true;
            expFields.classList.add('d-none');
            expNewInfo.classList.remove('d-none');
            if (expFieldsWrap) {
                expFieldsWrap.classList.remove('is-open');
                expFieldsWrap.setAttribute('aria-hidden', 'true');
            }

            if (jobTitleInput) jobTitleInput.required = false;
            if (companyNameInput) companyNameInput.required = false;
            if (startMonthSelect) startMonthSelect.required = false;
            if (startYearSelect) startYearSelect.required = false;
            if (endMonthSelect) endMonthSelect.required = false;
            if (endYearSelect) endYearSelect.required = false;
        }
    }

    function syncEndDateDisabled() {
        if (!stillInRoleCheckbox) return;
        var disabled = !!stillInRoleCheckbox.checked;
        endMonthSelect.disabled = disabled;
        endYearSelect.disabled = disabled;
        endMonthSelect.required = !disabled;
        endYearSelect.required = !disabled;
        var hint = document.getElementById('endDisabledHint');
        if (hint) hint.classList.toggle('d-none', !disabled);
    }

    if (stillInRoleCheckbox) {
        stillInRoleCheckbox.addEventListener('change', syncEndDateDisabled);
    }

    function showError(msg) {
        if (!errorBox) {
            alert(msg || 'Something went wrong.');
            return;
        }
        errorBox.textContent = msg || 'Something went wrong.';
        errorBox.style.display = 'block';
        errorBox.classList.remove('d-none');
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function hideError() {
        if (!errorBox) return;
        errorBox.textContent = '';
        errorBox.style.display = 'none';
        errorBox.classList.add('d-none');
    }

    function updateQuickDocCardUI(docType, docItem) {
        var card = document.querySelector('.apm-quick-upload[data-doc-type="' + docType + '"]');
        if (!card) return;
        var statusSpan = card.querySelector('.apm-doc-status');
        var viewBtn = card.querySelector('.apm-view-btn');
        var uploadText = card.querySelector('.apm-upload-text');
        var progressBar = card.querySelector('.apm-upload-progress-inner');

        if (docItem && docItem.DocID) {
            uploadedDocsMap[docType] = docItem.DocID;
            if (statusSpan) {
                statusSpan.innerHTML = '<span class="text-success fw-bold">✓ ' + escapeHtml(docItem.OriginalFileName || 'Uploaded file') + '</span>';
            }
            if (viewBtn) {
                viewBtn.classList.remove('d-none');
                viewBtn.setAttribute('data-doc-id', docItem.DocID);
            }
            if (uploadText) {
                uploadText.textContent = 'Replace';
            }
            // Bar reflects a successfully saved document record (DocID present),
            // not upload-in-progress — same source of truth as the status text above.
            if (progressBar) {
                progressBar.style.width = '100%';
                progressBar.classList.add('apm-upload-success');
            }
        } else {
            delete uploadedDocsMap[docType];
            if (statusSpan) {
                if (docType === 'Resume') statusSpan.textContent = 'PDF only · Maximum 5 MB';
                else if (docType === 'Government ID') statusSpan.textContent = 'Government-issued ID';
                else if (docType === 'Certification') statusSpan.textContent = 'Licenses, diplomas, etc.';
            }
            if (viewBtn) {
                viewBtn.classList.add('d-none');
                viewBtn.removeAttribute('data-doc-id');
            }
            if (uploadText) {
                uploadText.textContent = 'Choose File';
            }
            if (progressBar) {
                progressBar.style.width = '0%';
                progressBar.classList.remove('apm-upload-success');
            }
        }
    }

    function openDocumentPreview(docId) {
        var modalEl = document.getElementById('applicantDocumentPreviewModal');
        var frame = document.getElementById('applicantDocumentPreviewFrame');
        var img = document.getElementById('applicantDocumentPreviewImage');
        var empty = document.getElementById('applicantDocumentPreviewEmpty');
        var downloadBtn = document.getElementById('applicantDocumentPreviewDownload');

        if (!modalEl) return;

        var viewUrl = '../../Api/Applicant/view_document.php?id=' + encodeURIComponent(docId);
        if (downloadBtn) downloadBtn.href = viewUrl+"&download=1";

        if (frame) { frame.style.display = 'none'; frame.src = ''; }
        if (img) { img.style.display = 'none'; img.src = ''; }
        if (empty) { empty.classList.add('d-none'); }

        fetch(viewUrl, { method: 'HEAD', credentials: 'include' })
            .then(function (r) {
                if(!r.ok){if(empty){empty.textContent='This document is missing or unavailable. Please upload a replacement.';empty.classList.remove('d-none');}bootstrap.Modal.getOrCreateInstance(modalEl).show();return;}
                var contentType = r.headers.get('content-type') || '';
                if (frame && (contentType.indexOf('pdf') !== -1 || r.url.indexOf('.pdf') !== -1)) {
                    frame.src = viewUrl;
                    frame.style.display = 'block';
                } else if (img && (contentType.indexOf('image') !== -1)) {
                    img.src = viewUrl;
                    img.style.display = 'block';
                } else {
                    if (empty) {
                        empty.textContent = 'Preview is available via Download / Open File below.';
                        empty.classList.remove('d-none');
                    }
                }
                var bsModal = new bootstrap.Modal(modalEl);
                bsModal.show();
            })
            .catch(function () {
                if (frame) {
                    frame.src = viewUrl;
                    frame.style.display = 'block';
                }
                var bsModal = new bootstrap.Modal(modalEl);
                bsModal.show();
            });
    }

    function initQuickUploadHandlers() {
        var cards = document.querySelectorAll('.apm-quick-upload');
        Array.prototype.forEach.call(cards, function (card) {
            var docType = card.getAttribute('data-doc-type');
            var docCategory = card.getAttribute('data-doc-category') || 'general';
            var fileInput = card.querySelector('input[type="file"]');
            var viewBtn = card.querySelector('.apm-view-btn');

            if (viewBtn) {
                viewBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var docId = this.getAttribute('data-doc-id');
                    if (docId) openDocumentPreview(docId);
                });
            }

            if (fileInput) {
                fileInput.addEventListener('change', function () {
                    if (!this.files || !this.files[0]) return;
                    var file = this.files[0];
                    if(docType==='Resume' && (!/\.pdf$/i.test(file.name) || (file.type && file.type!=='application/pdf') || file.size>5*1024*1024)){showError(file.size>5*1024*1024?'Resume/CV must not exceed 5 MB.':'Only PDF files are allowed for Resume/CV.');this.value='';return;}
                    var formData = new FormData();
                    formData.append('document', file);
                    formData.append('document_type', docType);
                    formData.append('document_category', docCategory);

                    pendingDocumentUploads++;
                    fetch(UPLOAD_DOC_API, {
                        method: 'POST',
                        credentials: 'include',
                        headers: { 'Accept': 'application/json' },
                        body: formData
                    })
                    .then(function (r) { return r.json().catch(function () { return null; }); })
                    .then(function (res) {
                        if (res && res.success) {
                            // Bar fill is set inside updateQuickDocCardUI from the
                            // saved DocID — the single source of truth for "successfully
                            // uploaded", not a timed animation.
                            updateQuickDocCardUI(docType, {
                                DocID: res.doc_id || res.document_id,
                                OriginalFileName: file.name
                            });
                        } else {
                            // Failed attempt: leave the bar exactly as it was (filled if
                            // this card already had a previously saved document, empty
                            // if it didn't) — a failed Replace must not erase the
                            // existing successful-upload state.
                            alert((res && res.message) ? res.message : 'Failed to upload document.');
                        }
                    })
                    .catch(function () {
                        alert('Network error while uploading document.');
                    }).finally(function () { pendingDocumentUploads--; });
                });
            }
        });
    }

    function applyValues(d) {
        if (!d || !d.profile) return;
        var p = d.profile;

        if (firstNameInput && p.first_name) firstNameInput.value = p.first_name;
        if (middleNameInput && p.middle_name) middleNameInput.value = p.middle_name;
        if (lastNameInput && p.last_name) lastNameInput.value = p.last_name;
        if (p.suffix && hasSuffixCheckbox && suffixSelect) {
            hasSuffixCheckbox.checked = true;
            suffixSelect.disabled = false;
            suffixSelect.value = p.suffix;
        } else if (hasSuffixCheckbox && suffixSelect) {
            hasSuffixCheckbox.checked = false;
            suffixSelect.disabled = true;
            suffixSelect.value = '';
        }

        if (document.getElementById('sexInput')) document.getElementById('sexInput').value=p.gender || '';
        if (birthDateInput && p.birth_date) birthDateInput.value = String(p.birth_date).slice(0, 10);
        if (streetAddressInput && p.street_address) streetAddressInput.value = p.street_address;
        if (postalCodeInput && p.postal_code) postalCodeInput.value = p.postal_code;
        ensureCascadesReady().then(function () {
            if (window.ApplicantProfileCascades) {
                return ApplicantProfileCascades.hydrateAddress(p);
            }
        });
        syncBirthDateUi();

        if (p.has_recent_experience === 1 || p.has_recent_experience === '1') {
            setExpMode('1');
        } else {
            setExpMode('0');
        }

        if (jobTitleInput && p.job_title) jobTitleInput.value = p.job_title;
        if (companyNameInput && p.company_name) companyNameInput.value = p.company_name;
        if (startMonthSelect && p.experience_start_month) startMonthSelect.value = String(p.experience_start_month);
        if (startYearSelect && p.experience_start_year) startYearSelect.value = String(p.experience_start_year);

        if (stillInRoleCheckbox) stillInRoleCheckbox.checked = !!p.still_in_role;
        if (endMonthSelect && p.experience_end_month) endMonthSelect.value = String(p.experience_end_month);
        if (endYearSelect && p.experience_end_year) endYearSelect.value = String(p.experience_end_year);
        syncEndDateDisabled();

        ensureCascadesReady().then(function () {
            var loadCategories = (window.ApplicantProfileCascades && p.job_category)
                ? ApplicantProfileCascades.loadJobCategories(p.job_category)
                : Promise.resolve();
            if (!window.ApplicantProfileCascades && jobCategorySelect && p.job_category) {
                jobCategorySelect.value = p.job_category;
            }
            return Promise.resolve(loadCategories).then(function () {
                // Classification depends on the job category's id, so this can
                // only run once the category select has actually resolved above.
                if (window.ApplicantProfileCascades && jobCategorySelect && jobCategorySelect.value) {
                    return ApplicantProfileCascades.hydrateClassification(
                        jobCategorySelect.value,
                        p.preferred_classification || '',
                        p.preferred_sub_classification || ''
                    );
                }
                if (classificationSelect && p.preferred_classification) {
                    classificationSelect.value = p.preferred_classification;
                    if (subClassificationSelect && p.preferred_sub_classification) {
                        subClassificationSelect.value = p.preferred_sub_classification;
                    }
                }
            });
        });

        if (d.documents && Array.isArray(d.documents)) {
            d.documents.forEach(function (doc) {
                if (doc.DocType) {
                    updateQuickDocCardUI(doc.DocType, doc);
                }
            });
        }
    }

    function fetchJson(url, init) {
        var opts = init ? Object.assign({}, init) : {};
        opts.credentials = 'include';
        opts.headers = Object.assign({ 'Accept': 'application/json' }, opts.headers || {});
        return fetch(url, opts).then(function (r) {
            return r.text().then(function (text) {
                try {
                    return { ok: r.ok, status: r.status, data: text ? JSON.parse(text) : null };
                } catch (e) {
                    return { ok: r.ok, status: r.status, data: null };
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        hideError();
        syncBirthDateUi();
        setExpMode('0');
        ensureCascadesReady();
        initQuickUploadHandlers();

        fetchJson(STATUS_API, { method: 'GET' })
            .then(function (res) {
                var status = res.data;
                if (!res.ok || !status || !status.success) {
                    if (status && status.message) showError(status.message);
                    return;
                }
                if (status.logged_in === false) {
                    showError('Please sign in to continue.');
                    return;
                }
                var hintEl = document.getElementById('profileCompleteHint');
                if (hintEl) {
                    hintEl.classList.toggle('d-none', !(status.status === 'complete' || status.profile_completed));
                }
                var needTerms = !status.registration_terms_accepted && (status.profile_status === 0 || status.profile_status === undefined);
                if (status.logged_in && needTerms) {
                    return fetchJson('../../Api/Applicant/get_applicant_terms_payload.php', { method: 'GET' }).then(function (pres) {
                        var p = pres.data;
                        if (p && p.success && p.email && p.user_id && p.sig) {
                            var u = new URL('applicant_terms.html', window.location.href);
                            u.searchParams.set('email', p.email);
                            u.searchParams.set('uid', String(p.user_id));
                            u.searchParams.set('sig', p.sig);
                            window.location.href = u.href;
                            return;
                        }
                        showError('Please accept the registration terms before continuing.');
                    });
                }
                return fetchJson(SETUP_GET_API, { method: 'GET' }).then(function (res2) {
                    var d = res2.data;
                    if (!d || !d.success) {
                        showError((d && d.message) ? d.message : 'Could not load your profile. Please refresh or sign in again.');
                        return;
                    }
                    applyValues(d);
                    hideError();
                    if (!expHave.checked && !expNew.checked) setExpMode('0');
                });
            })
            .catch(function () {
                showError('Network error while loading your profile. Please try again.');
            });
    });

    if (expHave) expHave.addEventListener('change', function () { setExpMode('1'); });
    if (expNew) expNew.addEventListener('change', function () { setExpMode('0'); });

    function buildProfilePayload() {
            var expMode = document.querySelector('input[name="expMode"]:checked');
            var hasRecentExperience = expMode ? expMode.value === '1' : false;

            var birthIso = birthDateInput ? birthDateInput.value.trim() : '';
            var addressValues = window.ApplicantProfileCascades
                ? ApplicantProfileCascades.getAddressValues()
                : {
                    street_address: streetAddressInput ? streetAddressInput.value.trim() : '',
                    region: '',
                    city: '',
                    state_province: '',
                    barangay: '',
                    postal_code: postalCodeInput ? postalCodeInput.value.trim() : '',
                    country: 'Philippines'
                };
            var streetAddress = addressValues.street_address;
            var region = addressValues.region;
            var city = addressValues.city;
            var stateProvince = addressValues.state_province;
            var postalCode = addressValues.postal_code;
            var country = 'Philippines';
            var barangay = addressValues.barangay;
            var jobCategoryValue = window.ApplicantProfileCascades
                ? ApplicantProfileCascades.getJobCategoryValue()
                : (jobCategorySelect ? jobCategorySelect.value : '');
            var computedHomeLocation = [barangay, city, stateProvince, country].filter(Boolean).join(', ');

            var hasSuffix = hasSuffixCheckbox ? hasSuffixCheckbox.checked : false;
            var suffixVal = (hasSuffix && suffixSelect) ? suffixSelect.value : '';

            var payload = {
                first_name: firstNameInput.value.trim(),
                middle_name: middleNameInput ? middleNameInput.value.trim() : '',
                last_name: lastNameInput.value.trim(),
                has_suffix: hasSuffix ? 1 : 0,
                suffix: suffixVal,
                birth_date: birthIso,
                gender: document.getElementById('sexInput').value,
                street_address: streetAddress,
                region: region,
                address_psgc: addressValues.address_psgc,
                province_required: addressValues.province_required,
                city: city,
                state_province: stateProvince,
                barangay: barangay,
                postal_code: postalCode,
                country: country,
                home_location: computedHomeLocation,
                has_recent_experience: hasRecentExperience ? 1 : 0,
                job_title: jobTitleInput.value.trim(),
                company_name: companyNameInput.value.trim(),
                experience_start_month: startMonthSelect.value ? parseInt(startMonthSelect.value, 10) : null,
                experience_start_year: startYearSelect.value ? parseInt(startYearSelect.value, 10) : null,
                experience_end_month: stillInRoleCheckbox.checked ? null : (endMonthSelect.value ? parseInt(endMonthSelect.value, 10) : null),
                experience_end_year: stillInRoleCheckbox.checked ? null : (endYearSelect.value ? parseInt(endYearSelect.value, 10) : null),
                still_in_role: stillInRoleCheckbox.checked ? 1 : 0,
                preferred_classification: classificationSelect.value,
                preferred_sub_classification: subClassificationSelect.value,
                job_category: jobCategoryValue
            };
            payload.category_id = jobCategorySelect ? jobCategorySelect.value : '';
            payload.classification_id = classificationSelect.selectedOptions[0] ? classificationSelect.selectedOptions[0].dataset.id : '';
            payload.sub_classification_id = subClassificationSelect.selectedOptions[0] ? subClassificationSelect.selectedOptions[0].dataset.id : '';
            if (!payload.home_location && computedHomeLocation) payload.home_location = computedHomeLocation;

        return payload;
    }
    var profileValidation = form && window.ApplicantProfileValidation.create(form, errorBox, buildProfilePayload);

    function checkRequiredDocuments() {
        var missing=['Resume','Government ID'].filter(function(type){return !uploadedDocsMap[type];});
        if(!pendingDocumentUploads && !missing.length)return true;
        var names=missing.map(function(type){return type==='Resume'?'Résumé / CV':'Valid ID';});
        var message=pendingDocumentUploads?'Please wait for your document uploads to finish before saving.':'Please upload '+names.join(' and ')+' before saving your profile. Both your résumé and valid ID are required. Certifications are optional.';
        var modalEl=document.getElementById('requiredDocumentsModal');
        if(modalEl && window.bootstrap){document.getElementById('requiredDocumentsTitle').textContent=pendingDocumentUploads?'Uploads in progress':'Required documents missing';document.getElementById('requiredDocumentsMessage').textContent=message;bootstrap.Modal.getOrCreateInstance(modalEl).show();}else showError(message);
        return false;
    }
    if(btnSaveAndContinue)btnSaveAndContinue.addEventListener('click',function(event){if(!checkRequiredDocuments())event.preventDefault();});

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            hideError();

            if (!checkRequiredDocuments()) return;

            var payload = buildProfilePayload();
            if (!profileValidation.check()) return;

            if (btnSaveAndContinue) {
                btnSaveAndContinue.disabled = true;
                btnSaveAndContinue.textContent = 'Saving...';
            }

            fetchJson(SETUP_SAVE_API, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (res) {
                    var d = res.data;
                    if (!d || !d.success) {
                        if (d && d.errors) profileValidation.apply(d.errors);
                        else showError((d && d.message) ? d.message : 'Failed to save profile.');
                        if (btnSaveAndContinue) {
                            btnSaveAndContinue.disabled = false;
                            syncBirthDateUi();
                            btnSaveAndContinue.textContent = 'Save & continue';
                        }
                        return;
                    }
                    var destination = d.redirect || DASHBOARD;
                    var savedModal = document.getElementById('profileSavedModal');
                    if (savedModal && window.bootstrap) {
                        document.getElementById('profileSavedContinue').onclick = function () { window.location.href = destination; };
                        bootstrap.Modal.getOrCreateInstance(savedModal).show();
                    } else {
                        window.alert('Your applicant information has been saved successfully.');
                        window.location.href = destination;
                    }
                })
                .catch(function () {
                    showError('Network/server error. Please try again.');
                    if (btnSaveAndContinue) {
                        btnSaveAndContinue.disabled = false;
                        syncBirthDateUi();
                        btnSaveAndContinue.textContent = 'Save & continue';
                    }
                });
        });
    }
})();
