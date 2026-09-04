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
        var iso = (birthDateInput.value || '').trim();
        var age = ageFromIsoDate(iso);
        var under = iso && age !== null && age >= 0 && age < 18;
        birthDateError.classList.toggle('is-visible', !!under);
        birthDateInput.classList.toggle('is-invalid', !!under);
        if (btnSaveAndContinue) {
            btnSaveAndContinue.disabled = !!under;
            btnSaveAndContinue.setAttribute('aria-disabled', under ? 'true' : 'false');
        }
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
        } else {
            delete uploadedDocsMap[docType];
            if (statusSpan) {
                if (docType === 'Resume') statusSpan.textContent = 'PDF, Word, or image';
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
        if (downloadBtn) downloadBtn.href = viewUrl;

        if (frame) { frame.style.display = 'none'; frame.src = ''; }
        if (img) { img.style.display = 'none'; img.src = ''; }
        if (empty) { empty.classList.add('d-none'); }

        fetch(viewUrl, { method: 'HEAD', credentials: 'include' })
            .then(function (r) {
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
            var progressBar = card.querySelector('.apm-upload-progress-inner');

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
                    var formData = new FormData();
                    formData.append('document', file);
                    formData.append('document_type', docType);
                    formData.append('document_category', docCategory);

                    if (progressBar) progressBar.style.width = '30%';

                    fetch(UPLOAD_DOC_API, {
                        method: 'POST',
                        credentials: 'include',
                        headers: { 'Accept': 'application/json' },
                        body: formData
                    })
                    .then(function (r) { return r.json().catch(function () { return null; }); })
                    .then(function (res) {
                        if (progressBar) progressBar.style.width = '100%';
                        setTimeout(function () { if (progressBar) progressBar.style.width = '0%'; }, 500);

                        if (res && res.success) {
                            updateQuickDocCardUI(docType, {
                                DocID: res.doc_id || res.document_id,
                                OriginalFileName: file.name
                            });
                        } else {
                            alert((res && res.message) ? res.message : 'Failed to upload document.');
                        }
                    })
                    .catch(function () {
                        if (progressBar) progressBar.style.width = '0%';
                        alert('Network error while uploading document.');
                    });
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

        if (classificationSelect && p.preferred_classification) {
            classificationSelect.value = p.preferred_classification;
            if (window.ApplicantProfileCascades) {
                ApplicantProfileCascades.populateSubClassificationOptions(p.preferred_classification, p.preferred_sub_classification || '');
            }
        } else if (subClassificationSelect && p.preferred_sub_classification) {
            subClassificationSelect.value = p.preferred_sub_classification;
        }
        ensureCascadesReady().then(function () {
            if (window.ApplicantProfileCascades && p.job_category) {
                return ApplicantProfileCascades.loadJobCategories(p.job_category);
            }
            if (jobCategorySelect && p.job_category) {
                jobCategorySelect.value = p.job_category;
            }
        });
        if (p.profile_visibility) {
            var pv = String(p.profile_visibility);
            form.querySelectorAll('input[name="profileVisibility"]').forEach(function (r) {
                r.checked = r.value === pv;
            });
        }

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

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            hideError();

            var expMode = document.querySelector('input[name="expMode"]:checked');
            var hasRecentExperience = expMode ? expMode.value === '1' : false;
            var visRadio = document.querySelector('input[name="profileVisibility"]:checked');
            var profileVisibility = visRadio ? visRadio.value : '';

            var birthIso = birthDateInput ? birthDateInput.value.trim() : '';
            var addressValues = window.ApplicantProfileCascades
                ? ApplicantProfileCascades.getAddressValues()
                : {
                    street_address: streetAddressInput ? streetAddressInput.value.trim() : '',
                    city: '',
                    state_province: '',
                    barangay: '',
                    postal_code: postalCodeInput ? postalCodeInput.value.trim() : '',
                    country: 'Philippines'
                };
            var streetAddress = addressValues.street_address;
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
                street_address: streetAddress,
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
                job_category: jobCategoryValue,
                profile_visibility: profileVisibility
            };
            if (!payload.home_location && computedHomeLocation) payload.home_location = computedHomeLocation;

            if (!payload.first_name || !payload.last_name) {
                showError('Please complete the required name fields.');
                return;
            }

            var namePattern = /^[A-Za-zÑñ\s\.\'\-]+$/;
            if (!namePattern.test(payload.first_name) || !namePattern.test(payload.last_name) || (payload.middle_name && !namePattern.test(payload.middle_name))) {
                showError('Names can only contain letters, spaces, periods, apostrophes, and hyphens.');
                return;
            }

            if (!payload.street_address || !payload.state_province || !payload.city || !payload.barangay || !payload.postal_code) {
                showError('Please complete your full address: province, city/municipality, barangay, street address, and postal code.');
                return;
            }
            if (!birthIso || !/^\d{4}-\d{2}-\d{2}$/.test(birthIso)) {
                showError('Please enter your date of birth.');
                return;
            }
            var ageYears = ageFromIsoDate(birthIso);
            if (ageYears === null || ageYears < 0) {
                showError('Invalid date of birth.');
                return;
            }
            if (ageYears < 18) {
                syncBirthDateUi();
                showError('You must be at least 18 years old to register.');
                return;
            }
            if (!payload.job_category) {
                showError('Please select a job category.');
                return;
            }
            if (!payload.preferred_classification || !payload.preferred_sub_classification) {
                showError('Please select your preferred classification and sub-classification.');
                return;
            }
            if (!payload.profile_visibility) {
                showError('Please choose a profile visibility option.');
                return;
            }
            if (hasRecentExperience) {
                if (!payload.job_title || !payload.company_name) {
                    showError('Job title and company name are required when you have experience.');
                    return;
                }
                if (!payload.experience_start_month || !payload.experience_start_year) {
                    showError('Please select your experience start month and year.');
                    return;
                }
                if (!payload.still_in_role) {
                    if (!payload.experience_end_month || !payload.experience_end_year) {
                        showError('Please select your experience end month and year (or mark “Still in role”).');
                        return;
                    }
                }
            }

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
                        showError((d && d.message) ? d.message : 'Failed to save profile.');
                        if (btnSaveAndContinue) {
                            btnSaveAndContinue.disabled = false;
                            syncBirthDateUi();
                            btnSaveAndContinue.textContent = 'Save & continue';
                        }
                        return;
                    }
                    window.location.href = (d.redirect) ? d.redirect : DASHBOARD;
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