/**
 * Applicant job board — filter toggles + fetch get_jobs.php without page reload.
 */
(function (global) {
  'use strict';

  var cfg = {
    apiUrl: '../../Api/Applicant/get_jobs.php',
    listContainerId: 'job-listings-container',
    previewPanelId: 'job-preview-panel',
    countElId: 'jbJobCount',
    btnAllId: 'btnAllOpenRoles',
    btnMatchId: 'btnMatchProfile',
    keywordInputId: 'seekKeyword',
    locationInputId: 'whereMindanaoInput',
    classificationInputId: 'jbClass',
    workTypeInputId: 'filterWorkType',
    postedWithinInputId: 'filterPostedWithin',
    searchBtnId: 'jbBtnSearch',
    emptyMessage: 'No jobs found matching your search.',
    applyBtnId: null,
    saveBtnId: null,
    listItemClass: 'jb-list-item',
    activeFilterClass: 'active',
  };

  var state = {
    filter: 'all',
    keyword: '',
    location: '',
    jobs: [],
    selectedJobId: null,
    requestSequence: 0,
    fetchController: null,
  };

  function esc(text) {
    if (text == null) return '';
    var d = document.createElement('div');
    d.textContent = String(text);
    return d.innerHTML;
  }

  function $(id) {
    return id ? document.getElementById(id) : null;
  }

  function setActiveFilter(filter) {
    state.filter = filter === 'match' ? 'match' : (filter === 'search' ? 'search' : 'all');
    var btnAll = $(cfg.btnAllId);
    var btnMatch = $(cfg.btnMatchId);
    if (btnAll) {
      btnAll.classList.toggle(cfg.activeFilterClass, state.filter === 'all');
      btnAll.setAttribute('aria-current', state.filter === 'all' ? 'true' : 'false');
    }
    if (btnMatch) {
      btnMatch.classList.toggle(cfg.activeFilterClass, state.filter === 'match');
      btnMatch.setAttribute('aria-current', state.filter === 'match' ? 'true' : 'false');
    }
    document.querySelectorAll('.jw-link-mini').forEach(function (el) {
      if (el.id === cfg.btnAllId) el.classList.toggle('active', state.filter === 'all');
      if (el.id === cfg.btnMatchId) el.classList.toggle('active', state.filter === 'match');
    });
  }

  function readSearchInputs() {
    var sk = $(cfg.keywordInputId);
    var wi = $(cfg.locationInputId);
    var wt = $(cfg.workTypeInputId);
    var pw = $(cfg.postedWithinInputId);
    if (state.filter !== 'match') {
      state.keyword = sk && sk.value ? sk.value.trim() : '';
    }
    state.location = wi && wi.value ? wi.value.trim() : '';
    return {
      keyword: state.keyword,
      location: state.location,
      work_type: wt && wt.value ? wt.value : '',
      since_days: pw && parseInt(pw.value, 10) > 0 ? pw.value : '',
    };
  }

  function buildRequestParams(filterType) {
    var inputs = readSearchInputs();
    var params = new URLSearchParams();
    if (filterType === 'match') {
      params.set('scope', 'match');
      params.set('filter', 'match');
    } else if (filterType === 'search' && inputs.keyword) {
      params.set('scope', 'search');
      params.set('filter', 'search');
      params.set('keyword', inputs.keyword);
    } else {
      params.set('scope', 'all');
      params.set('filter', 'all');
    }
    if (inputs.location) params.set('location', inputs.location);
    if (inputs.work_type) params.set('work_type', inputs.work_type);
    if (inputs.since_days) params.set('since_days', inputs.since_days);
    var cls = $(cfg.classificationInputId);
    if (cls && cls.value.trim() && filterType === 'all') {
      params.set('classification', cls.value.trim());
    }
    return params;
  }

  function positionLabel(job) {
    if (!job) return 'Open Role';
    var p = String(job.job_title || job.position || '').trim();
    if (p && p !== '0') return p;
    return 'Open Role';
  }

  function jobsFingerprint(jobs) {
    return (jobs || []).map(function (j) {
      return [
        j.id,
        j.position || j.job_title || '',
        j.company_name || '',
        j.location || '',
        j.work_shift || j.work_schedule || '',
      ].join('|');
    }).join('\n');
  }

  function relativePosted(job) {
    var ts = job.posted_ts;
    if (!ts) return job.date_posted_label || job.date_posted || '';
    var diff = Math.floor(Date.now() / 1000) - ts;
    if (diff < 3600) return Math.max(1, Math.floor(diff / 60)) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 86400 * 7) return Math.floor(diff / 86400) + 'd ago';
    return job.date_posted_label || '';
  }

  function renderPreview(job) {
    var panel = $(cfg.previewPanelId);
    if (!panel) return;

    if (!job) {
      panel.innerHTML =
        '<div class="jb-detail-empty text-center text-muted py-5">' +
        '<i class="bi bi-arrow-left-circle d-lg-none mb-2" style="font-size:2rem;"></i>' +
        '<p class="mb-0">Select a job on the left to see the full description.</p></div>';
      return;
    }

    var slotsHtml = '';
    if (global.JobWallSlots && typeof global.JobWallSlots.renderDeploymentSlots === 'function') {
      slotsHtml = global.JobWallSlots.renderDeploymentSlots(job.deployment_slots || []);
    }

    if (cfg.listItemClass === 'jw-list-card') {
      panel.innerHTML =
        '<div class="jw-detail-head">' +
        '<h3 class="jw-detail-title">' + esc(positionLabel(job)) + '</h3>' +
        '<p class="jw-detail-co">' + esc(job.company_name) + '</p>' +
        '</div>' +
        '<ul class="jw-detail-meta list-unstyled">' +
        '<li><i class="bi bi-clock"></i> ' + esc(job.work_shift || job.work_schedule || 'Shift not specified') + '</li>' +
        '<li><i class="bi bi-geo-alt"></i> ' + esc(job.location || 'Location TBD') + '</li>' +
        '<li><i class="bi bi-tag"></i> ' + esc(job.job_category || 'General') + '</li>' +
        '</ul>' +
        '<div class="jw-detail-actions d-flex gap-2">' +
        '<button type="button" class="btn btn-primary" data-action="apply" data-job-id="' + job.id + '">Apply</button>' +
        '<button type="button" class="btn btn-outline-secondary" data-action="save" data-job-id="' + job.id + '">Save</button>' +
        '</div>' +
        ((job.qualifications || '').trim()
          ? '<div class="jw-detail-section mt-3"><h4>Overview</h4><p>' + esc(job.qualifications) + '</p></div>'
          : '') +
        slotsHtml;
      bindPreviewActions();
      return;
    }

    var qual = (job.qualifications || '').trim();
    var skills = (job.required_skills || '').trim();
    panel.innerHTML =
      '<div class="jb-detail-head">' +
      '<div class="min-w-0">' +
      '<div class="jb-detail-title">' + esc(positionLabel(job)) + '</div>' +
      '<div class="jb-detail-co">' + esc(job.company_name) + '</div>' +
      '</div></div>' +
      '<div class="jb-detail-meta">' +
      '<div class="jb-meta-row"><i class="bi bi-geo-alt"></i><span>' + esc(job.location || 'Location TBD') + '</span></div>' +
      '<div class="jb-meta-row"><i class="bi bi-clock"></i><span>' + esc(job.work_shift || job.work_schedule || 'Shift not specified') + '</span></div>' +
      '<div class="jb-meta-row"><i class="bi bi-tag"></i><span>' + esc(job.job_category || 'General') + '</span></div>' +
      '<div class="jb-meta-row"><i class="bi bi-calendar3"></i><span>Posted ' + esc(job.date_posted_label || job.date_posted || '—') + '</span></div>' +
      '</div>' +
      '<div class="jb-cta-row">' +
      '<button type="button" class="jb-btn-apply" data-action="apply" data-job-id="' + job.id + '">' +
      '<i class="bi bi-box-arrow-up-right"></i> Apply</button>' +
      '<button type="button" class="jb-btn-save" data-action="save" data-job-id="' + job.id + '">Save</button>' +
      '</div>' +
      '<div class="jb-detail-section"><h3>Role overview</h3>' +
      (qual ? '<p style="white-space:pre-wrap">' + esc(qual) + '</p>' : '<p class="text-muted mb-0">No description provided.</p>') +
      '</div>' +
      (skills ? '<div class="jb-detail-section"><h3>Skills & qualifications</h3><p style="white-space:pre-wrap">' + esc(skills) + '</p></div>' : '') +
      slotsHtml;

    bindPreviewActions();
  }

  function bindPreviewActions() {
    var panel = $(cfg.previewPanelId);
    if (!panel) return;
    panel.querySelectorAll('[data-action]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.stopPropagation();
        var id = parseInt(el.getAttribute('data-job-id'), 10);
        var action = el.getAttribute('data-action');
        if (action === 'apply' && typeof cfg.onApply === 'function') cfg.onApply(id);
        if (action === 'save' && typeof cfg.onSave === 'function') cfg.onSave(id);
      });
    });
  }

  function selectJob(jobId, scrollIntoView) {
    state.selectedJobId = jobId;
    var job = state.jobs.find(function (j) { return j.id === jobId; });
    var list = $(cfg.listContainerId);
    if (list) {
      list.querySelectorAll('[data-job-id]').forEach(function (el) {
        el.classList.toggle('active', parseInt(el.getAttribute('data-job-id'), 10) === jobId);
      });
    }
    renderPreview(job || null);
    if (scrollIntoView && job && window.innerWidth < 992) {
      var panel = $(cfg.previewPanelId);
      if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    if (global.ApplicantJobFilter && typeof cfg.onSelect === 'function') {
      cfg.onSelect(job);
    }
  }

  function renderJobCard(job) {
    var active = job.id === state.selectedJobId ? ' active' : '';
    var isJw = cfg.listItemClass === 'jw-list-card';

    if (isJw) {
      var loc = job.location || 'Mindanao';
      return (
        '<button type="button" class="jw-list-card' + active + '" role="option" data-job-id="' + job.id + '" aria-selected="' + (active ? 'true' : 'false') + '">' +
        (job.is_new_to_you ? '<span class="jw-badge-new">NEW TO YOU</span>' : '') +
        '<div class="jw-title">' + esc(positionLabel(job)) + '</div>' +
        '<div class="jw-company">' + esc(job.company_name) + '</div>' +
        '<div class="jw-meta-line"><i class="bi bi-geo-alt"></i><span>' + esc(loc) + '</span></div>' +
        '<div class="jw-meta-line"><i class="bi bi-clock"></i><span>' + esc(job.work_shift || job.work_schedule || 'Shift TBD') + '</span></div>' +
        '</button>'
      );
    }

    var badges = [];
    if (job.is_new_to_you) {
      badges.push('<span class="badge bg-primary rounded-pill me-1">NEW TO YOU</span>');
    }
    if (job.matches_preference) {
      badges.push('<span class="jb-badge jb-badge-match">Match</span>');
    }
    return (
      '<button type="button" class="' + cfg.listItemClass + active + '" data-job-id="' + job.id + '">' +
      (badges.length ? '<div class="jb-li-badges">' + badges.join('') + '</div>' : '') +
      '<div class="jb-li-title">' + esc(positionLabel(job)) + '</div>' +
      '<div class="jb-li-co">' + esc(job.company_name) + '</div>' +
      '<div class="jb-li-meta">' +
      '<span><i class="bi bi-geo-alt me-1"></i>' + esc(job.location || 'Location TBD') + '</span>' +
      '<span><i class="bi bi-clock me-1"></i>' + esc(job.work_shift || job.work_schedule || 'Shift TBD') + '</span>' +
      '</div>' +
      '<div class="d-flex gap-2 mt-2">' +
      '<span class="btn btn-sm btn-primary py-0 px-2" data-action="apply" data-job-id="' + job.id + '">Apply</span>' +
      '<span class="btn btn-sm btn-outline-secondary py-0 px-2" data-action="save" data-job-id="' + job.id + '">Save</span>' +
      '</div>' +
      '<div class="jb-li-meta mt-1"><span>' + relativePosted(job) + '</span></div>' +
      '</button>'
    );
  }

  function renderJobList() {
    var list = $(cfg.listContainerId);
    if (!list) return;

    if (!state.jobs.length) {
      var emptyMsg = state.filter === 'match'
        ? 'No matching jobs found.'
        : (state.filter === 'search' ? 'No jobs found.' : cfg.emptyMessage);
      list.innerHTML = '<div class="alert alert-info m-2 mb-0">' + esc(emptyMsg) + '</div>';
      renderPreview(null);
      return;
    }

    list.innerHTML = state.jobs.map(renderJobCard).join('');

    list.querySelectorAll('[data-job-id]').forEach(function (el) {
      if (el.getAttribute('data-action')) return;
      el.addEventListener('click', function () {
        selectJob(parseInt(el.getAttribute('data-job-id'), 10), true);
      });
    });

    list.querySelectorAll('[data-action]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.stopPropagation();
        var id = parseInt(el.getAttribute('data-job-id'), 10);
        var action = el.getAttribute('data-action');
        if (action === 'apply' && typeof cfg.onApply === 'function') cfg.onApply(id);
        if (action === 'save' && typeof cfg.onSave === 'function') cfg.onSave(id);
      });
    });

    var pick = state.selectedJobId && state.jobs.some(function (j) { return j.id === state.selectedJobId; })
      ? state.selectedJobId
      : state.jobs[0].id;
    selectJob(pick, false);
  }

  function loadJobs(filterType, options) {
    options = options || {};
    var silent = !!options.silent;
    if (filterType === undefined || filterType === null) {
      filterType = state.filter;
    }
    if (filterType === 'match') {
      filterType = 'match';
    } else if (filterType === 'search') {
      filterType = 'search';
    } else {
      filterType = 'all';
    }
    readSearchInputs();
    if (filterType === 'search' && !state.keyword) {
      filterType = 'all';
    }
    if (filterType === 'match') {
      var sk = $(cfg.keywordInputId);
      if (sk) sk.value = '';
      state.keyword = '';
    }
    setActiveFilter(filterType);

    var list = $(cfg.listContainerId);
    if (!silent && list) {
      list.innerHTML =
        '<div class="text-center text-muted py-5 px-3">' +
        '<i class="bi bi-hourglass-split" style="font-size:2rem;"></i>' +
        '<p class="mt-2 mb-0 small">Loading jobs…</p></div>';
    }

    var seqId = ++state.requestSequence;
    if (state.fetchController) {
      try { state.fetchController.abort(); } catch (e) {}
    }
    state.fetchController = typeof AbortController !== 'undefined' ? new AbortController() : null;

    var params = buildRequestParams(filterType);
    var url = cfg.apiUrl + '?' + params.toString();
    var fetchOpts = { credentials: 'same-origin' };
    if (state.fetchController) fetchOpts.signal = state.fetchController.signal;

    return fetch(url, fetchOpts)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (seqId !== state.requestSequence) return data;
        if (!data || !data.success) {
          if (!silent && list) {
            list.innerHTML = '<div class="alert alert-danger m-2 mb-0">' +
              esc((data && data.message) || 'Failed to load jobs.') + '</div>';
          }
          return data;
        }
        var nextJobs = data.jobs || [];
        if (silent && jobsFingerprint(nextJobs) === jobsFingerprint(state.jobs)) {
          var countEl = $(cfg.countElId);
          if (countEl) countEl.textContent = String(nextJobs.length);
          return data;
        }
        state.jobs = nextJobs;
        var countEl2 = $(cfg.countElId);
        if (countEl2) countEl2.textContent = String(state.jobs.length);
        renderJobList();
        return data;
      })
      .catch(function (err) {
        if (seqId !== state.requestSequence) return;
        if (err && err.name === 'AbortError') return;
        if (!silent && list) {
          list.innerHTML = '<div class="alert alert-danger m-2 mb-0">Error loading jobs.</div>';
        }
      });
  }

  function bindToggleLinks() {
    var btnAll = $(cfg.btnAllId);
    var btnMatch = $(cfg.btnMatchId);
    if (btnAll) {
      btnAll.addEventListener('click', function (e) {
        e.preventDefault();
        var sk = $(cfg.keywordInputId);
        var wi = $(cfg.locationInputId);
        var wt = $(cfg.workTypeInputId);
        var pw = $(cfg.postedWithinInputId);
        if (sk) sk.value = '';
        if (wi) wi.value = '';
        if (wt) wt.value = '';
        if (pw) pw.value = '0';
        state.keyword = '';
        state.location = '';
        loadJobs('all');
      });
    }
    if (btnMatch) {
      btnMatch.addEventListener('click', function (e) {
        e.preventDefault();
        loadJobs('match');
      });
    }
    var searchBtn = $(cfg.searchBtnId);
    if (searchBtn) {
      searchBtn.addEventListener('click', function (e) {
        e.preventDefault();
        readSearchInputs();
        loadJobs(state.keyword ? 'search' : 'all');
      });
    }
    var sk = $(cfg.keywordInputId);
    if (sk) {
      sk.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          readSearchInputs();
          loadJobs(state.keyword ? 'search' : 'all');
        }
      });
    }
  }

  function init(options) {
    if (options) {
      Object.keys(options).forEach(function (k) { cfg[k] = options[k]; });
    }
    bindToggleLinks();
    return loadJobs(state.filter);
  }

  global.ApplicantJobFilter = {
    init: init,
    loadJobs: loadJobs,
    selectJob: selectJob,
    getJobs: function () { return state.jobs.slice(); },
    getSelectedJobId: function () { return state.selectedJobId; },
    getFilter: function () { return state.filter; },
  };
  global.loadJobs = loadJobs;
})(window);