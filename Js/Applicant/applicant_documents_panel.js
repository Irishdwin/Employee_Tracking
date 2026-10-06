(function () {
    'use strict';

    var API = '../../Api/Applicant/';

    var CATEGORIES = [
        { id: 'identity', label: 'Identity Documents', desc: 'Government ID, passport, NBI, etc.' },
        { id: 'academic', label: 'Academic Records', desc: 'Transcripts, diplomas, certifications.' },
        { id: 'employment', label: 'Employment History', desc: 'Resume, CV, bio-data, employment letters.' }
    ];

    var state = {
        allDocs: [],
        selectedCat: 'identity'
    };

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function inferCategory(doc) {
        var c = (doc.doc_category || '').toLowerCase();
        if (c === 'identity' || c === 'academic' || c === 'employment') {
            return c;
        }
        var t = (doc.doc_type || '').toLowerCase();
        if (/identity|passport|nbi|government|national id|driver|license|sss|tin|umid/.test(t)) {
            return 'identity';
        }
        if (/transcript|diploma|school|academic|tor|college|university/.test(t)) {
            return 'academic';
        }
        if (/certif|license|training|seminar|workshop/.test(t) && !/driver/.test(t)) {
            return 'academic';
        }
        if (/resume|cv|bio|employment|work|company|experience|letter/.test(t)) {
            return 'employment';
        }
        return 'employment';
    }

    function formatSize(n) {
        if (n == null || n <= 0) {
            return '';
        }
        if (n < 1024) {
            return n + ' B';
        }
        if (n < 1024 * 1024) {
            return (n / 1024).toFixed(1) + ' KB';
        }
        return (n / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function inferFileType(filename) {
        var source = String(filename || '').toLowerCase();
        var ext = source.split('.').pop();
        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].indexOf(ext) !== -1) return 'image';
        if (ext === 'pdf') return 'pdf';
        return 'other';
    }

    function initDocumentPreview() {
        var previewEl = document.getElementById('applicantDocumentPreviewModal');
        if (!previewEl || previewEl.dataset.bound === '1') return;
        previewEl.dataset.bound = '1';

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.apm-btn-view-doc');
            if (!btn) return;
            e.preventDefault();
            openDocumentPreview(
                btn.getAttribute('data-file-url'),
                btn.getAttribute('data-file-type'),
                btn.getAttribute('data-doc-title')
            );
        });

        previewEl.addEventListener('show.bs.modal', function () {
            window.setTimeout(function () {
                var backs = document.querySelectorAll('.modal-backdrop');
                if (backs.length) backs[backs.length - 1].style.zIndex = '1055';
                previewEl.style.zIndex = '1060';
            }, 0);
        });

        previewEl.addEventListener('hidden.bs.modal', function () {
            var frameEl = document.getElementById('applicantDocumentPreviewFrame');
            var imageEl = document.getElementById('applicantDocumentPreviewImage');
            if (frameEl) {
                frameEl.style.display = 'none';
                frameEl.removeAttribute('src');
            }
            if (imageEl) {
                imageEl.style.display = 'none';
                imageEl.removeAttribute('src');
            }
        });
    }

    function openDocumentPreview(fileUrl, fileType, title) {
        var previewEl = document.getElementById('applicantDocumentPreviewModal');
        var titleEl = document.getElementById('applicantDocumentPreviewTitle');
        var frameEl = document.getElementById('applicantDocumentPreviewFrame');
        var imageEl = document.getElementById('applicantDocumentPreviewImage');
        var emptyEl = document.getElementById('applicantDocumentPreviewEmpty');
        var downloadEl = document.getElementById('applicantDocumentPreviewDownload');
        if (!previewEl || !titleEl || !frameEl || !imageEl || !emptyEl || !downloadEl) return;

        var url = fileUrl || '';
        var type = (fileType || '').toLowerCase();

        titleEl.textContent = title || 'Document Preview';
        downloadEl.href = url;
        downloadEl.classList.toggle('d-none', !url);

        frameEl.style.display = 'none';
        imageEl.style.display = 'none';
        emptyEl.classList.add('d-none');
        frameEl.removeAttribute('src');
        imageEl.removeAttribute('src');

        if (!url) {
            emptyEl.textContent = 'Preview is not available for this file.';
            emptyEl.classList.remove('d-none');
        } else if (type === 'image') {
            imageEl.src = url;
            imageEl.style.display = 'block';
        } else if (type === 'pdf') {
            frameEl.src = url;
            frameEl.style.display = 'block';
        } else {
            emptyEl.textContent = 'Preview is not available for this file type. Use Download / Open File.';
            emptyEl.classList.remove('d-none');
        }

        bootstrap.Modal.getOrCreateInstance(previewEl).show();
    }

    function showToast(msg, ok) {
        var el = document.getElementById('apmToast');
        if (!el) {
            return;
        }
        el.textContent = msg;
        el.classList.remove('is-success', 'is-error');
        el.classList.add(ok ? 'is-success' : 'is-error');
        el.classList.add('is-visible');
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function () {
            el.classList.remove('is-visible');
        }, 4200);
    }

    function uploadXHR(file, docType, docCategory, progressInner, done) {
        var fd = new FormData();
        fd.append('document', file);
        fd.append('doc_type', docType);
        fd.append('doc_category', docCategory);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', API + 'upload_document.php');
        xhr.withCredentials = true;
        xhr.upload.onprogress = function (e) {
            if (!e.lengthComputable || !progressInner) {
                return;
            }
            var pct = Math.round((e.loaded / e.total) * 100);
            progressInner.style.width = pct + '%';
        };
        xhr.onload = function () {
            var d = null;
            try {
                d = JSON.parse(xhr.responseText || '{}');
            } catch (e) {
                d = {};
            }
            if (d && d.success) {
                done(null, d);
            } else {
                done((d && d.message) || 'Upload failed', d);
            }
        };
        xhr.onerror = function () {
            done('Network error');
        };
        xhr.send(fd);
    }

    function loadDocuments(callback) {
        fetch(API + 'my_documents.php', { credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                if (!data.success) {
                    if (callback) {
                        callback();
                    }
                    return;
                }
                state.allDocs = (data.documents || []).map(function (d) {
                    d._cat = inferCategory(d);
                    return d;
                });
                renderDocumentsUi();
                if (callback) {
                    callback();
                }
            })
            .catch(function () {
                if (callback) {
                    callback();
                }
            });
    }

    function renderDocumentsUi() {
        var sidebar = document.getElementById('apmDocSidebar');
        var detail = document.getElementById('apmDocDetail');
        if (!sidebar || !detail) {
            return;
        }

        var counts = { identity: 0, academic: 0, employment: 0 };
        state.allDocs.forEach(function (d) {
            counts[d._cat] = (counts[d._cat] || 0) + 1;
        });

        sidebar.innerHTML = CATEGORIES.map(function (c) {
            var n = counts[c.id] || 0;
            var active = state.selectedCat === c.id ? ' is-active' : '';
            return (
                '<button type="button" class="apm-cat-btn' + active + '" data-cat="' + esc(c.id) + '">' +
                '<span class="apm-cat-label">' + esc(c.label) + '</span>' +
                '<span class="apm-cat-meta">' + esc(c.desc) + '</span>' +
                '<span class="apm-cat-badge">' + n + '</span>' +
                '</button>'
            );
        }).join('');

        sidebar.querySelectorAll('.apm-cat-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                state.selectedCat = btn.getAttribute('data-cat') || 'identity';
                renderDocumentsUi();
            });
        });

        var list = state.allDocs.filter(function (d) {
            return d._cat === state.selectedCat;
        });
        var catLabel = (CATEGORIES.filter(function (c) {
            return c.id === state.selectedCat;
        })[0] || {}).label || 'Documents';

        var cards = list.length ? list.map(function (d) {
            var dl = API + 'download_applicant_document.php?doc_id=' + encodeURIComponent(d.doc_id);
            var fileType = inferFileType(d.original_filename);
            var docTitle = (d.doc_type || 'Document') + ' — ' + (d.original_filename || 'File');
            return (
                '<div class="apm-file-card" data-doc-id="' + esc(d.doc_id) + '">' +
                '<div class="apm-file-icon"><i class="bi bi-file-earmark-text"></i></div>' +
                '<div class="apm-file-body">' +
                '<div class="apm-file-name" title="' + esc(d.original_filename) + '">' + esc(d.original_filename) + '</div>' +
                '<div class="apm-file-meta">' + esc(d.doc_type) + (d.file_size ? ' · ' + formatSize(d.file_size) : '') +
                ' · ' + esc(d.uploaded_at) + '</div>' +
                '</div>' +
                '<div class="apm-file-actions">' +
                '<button type="button" class="apm-icon-btn apm-btn-view-doc" title="View"' +
                ' data-file-url="' + esc(dl) + '"' +
                ' data-file-type="' + esc(fileType) + '"' +
                ' data-doc-title="' + esc(docTitle) + '">' +
                '<i class="bi bi-eye"></i></button>' +
                '<a class="apm-icon-btn" href="' + esc(dl) + '" title="Download" download><i class="bi bi-download"></i></a>' +
                '<button type="button" class="apm-icon-btn apm-btn-delete" data-doc-id="' + esc(d.doc_id) + '" title="Delete">' +
                '<i class="bi bi-trash"></i></button>' +
                '</div></div>'
            );
        }).join('') : '';

        var dropHint = state.selectedCat === 'identity'
            ? 'ID, passport, clearances…'
            : state.selectedCat === 'academic'
                ? 'TOR, diploma, certificates…'
                : 'Resume, CV, employment proofs…';

        detail.innerHTML =
            '<div class="apm-detail-head">' +
            '<h2 class="apm-detail-title">' + esc(catLabel) + '</h2>' +
            '<p class="apm-detail-sub">Files in this category. Drag files here or click to upload.</p>' +
            '</div>' +
            '<label class="apm-dropzone" id="apmDropzone">' +
            '<input type="file" id="apmDropInput" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" multiple class="d-none">' +
            '<div class="apm-drop-inner">' +
            '<i class="bi bi-cloud-arrow-up apm-drop-ico"></i>' +
            '<div class="apm-drop-title">Drop files or click to upload</div>' +
            '<div class="apm-drop-hint">' + esc(dropHint) + '</div>' +
            '<div class="apm-drop-progress" id="apmDropProgress" aria-hidden="true">' +
            '<div class="apm-drop-progress-bar" id="apmDropProgressBar"></div></div></div></label>' +
            '<div class="apm-file-grid">' + (cards || '<div class="apm-empty">No files in this category yet.</div>') + '</div>';

        var dz = document.getElementById('apmDropzone');
        var di = document.getElementById('apmDropInput');
        var pb = document.getElementById('apmDropProgressBar');
        var pr = document.getElementById('apmDropProgress');

        function handleFiles(fileList) {
            if (!fileList || !fileList.length) {
                return;
            }
            var i = 0;
            var anyErr = false;
            function next() {
                if (i >= fileList.length) {
                    loadDocuments(function () {
                        if (!anyErr) {
                            showToast('Upload complete.', true);
                        }
                    });
                    if (pb) {
                        pb.style.width = '0%';
                    }
                    if (pr) {
                        pr.setAttribute('aria-hidden', 'true');
                    }
                    return;
                }
                var f = fileList[i];
                i += 1;
                var base = (f.name || 'document').replace(/\.[^.]+$/, '');
                var docType = base.slice(0, 80) || 'Document';
                if (pr) {
                    pr.setAttribute('aria-hidden', 'false');
                }
                uploadXHR(f, docType, state.selectedCat, pb, function (err) {
                    if (err) {
                        anyErr = true;
                        showToast(err, false);
                    }
                    next();
                });
            }
            next();
        }

        if (di) {
            di.addEventListener('change', function () {
                handleFiles(di.files);
                di.value = '';
            });
        }
        if (dz) {
            dz.addEventListener('dragover', function (e) {
                e.preventDefault();
                dz.classList.add('is-drag');
            });
            dz.addEventListener('dragleave', function () {
                dz.classList.remove('is-drag');
            });
            dz.addEventListener('drop', function (e) {
                e.preventDefault();
                dz.classList.remove('is-drag');
                handleFiles(e.dataTransfer.files);
            });
        }

        detail.querySelectorAll('.apm-btn-delete').forEach(function (b) {
            b.addEventListener('click', function () {
                var id = parseInt(b.getAttribute('data-doc-id'), 10);
                if (!id || !window.confirm('Delete this file?')) {
                    return;
                }
                fetch(API + 'delete_applicant_document.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ doc_id: id })
                })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (d) {
                        if (d && d.success) {
                            showToast('File removed.', true);
                            loadDocuments();
                        } else {
                            showToast((d && d.message) || 'Could not delete.', false);
                        }
                    })
                    .catch(function () {
                        showToast('Network error.', false);
                    });
            });
        });
    }

    function showTabVisual(tab) {
        var p = document.getElementById('apmPanelProfile');
        var d = document.getElementById('apmPanelDocuments');
        var tp = document.getElementById('apmTabProfile');
        var td = document.getElementById('apmTabDocuments');
        var isDoc = tab === 'documents';
        if (p) {
            p.classList.toggle('d-none', isDoc);
        }
        if (d) {
            d.classList.toggle('d-none', !isDoc);
        }
        if (tp) {
            tp.classList.toggle('is-active', !isDoc);
        }
        if (td) {
            td.classList.toggle('is-active', isDoc);
        }
        if (isDoc) {
            loadDocuments();
        }
    }

    function applyHash() {
        var h = (location.hash || '').toLowerCase();
        var tab = h === '#documents' ? 'documents' : 'profile';
        showTabVisual(tab);
    }

    // NOTE: the old bindQuickUploads() function used to live here. It bound
    // a card-level click -> inp.click() handler plus its own 'change'
    // handler directly onto the same .apm-quick-upload "Key documents"
    // cards (Resume/CV, Valid ID, Certifications) that
    // Js/Applicant/applicant_profile_form.js's initQuickUploadHandlers()
    // already fully owns. Since the file input in that markup is nested
    // inside a <label>, clicking the label already opens the file chooser
    // natively with no JS — bindQuickUploads()'s extra inp.click() call on
    // top of that consumed the click's user-activation a second time,
    // which is exactly what produced the "File chooser dialog can only be
    // shown with a user activation" console error. It also caused every
    // selected file to be uploaded twice (this file's uploadXHR() to
    // upload_document.php, and applicant_profile_form.js's handler to
    // upload_applicant_document.php), inserting two rows into
    // applicant_uploaded_docs per upload. Removed; the Key documents cards
    // are handled solely by applicant_profile_form.js now. uploadXHR()
    // itself is kept below since the My Documents tab's dropzone
    // (see renderDocumentsUi()) still uses it.

    function bindHintLink() {
        var h = document.getElementById('goDocsFromHint');
        if (h) {
            h.addEventListener('click', function (e) {
                e.preventDefault();
                location.hash = '#documents';
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var tp = document.getElementById('apmTabProfile');
        var td = document.getElementById('apmTabDocuments');
        if (tp) {
            tp.addEventListener('click', function (e) {
                e.preventDefault();
                location.hash = '#profile';
            });
        }
        if (td) {
            td.addEventListener('click', function (e) {
                e.preventDefault();
                location.hash = '#documents';
            });
        }
        window.addEventListener('hashchange', applyHash);
        bindHintLink();
        initDocumentPreview();
        applyHash();

        window.OnazulApplicantProfile = {
            goDocuments: function () {
                location.hash = '#documents';
            },
            goProfile: function () {
                location.hash = '#profile';
            },
            refreshDocuments: function () {
                loadDocuments();
            }
        };
    });
})();