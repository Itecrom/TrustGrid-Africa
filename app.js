(() => {
  const body = document.body;
  const baseUrl = (body.dataset.baseUrl || '').replace(/\/$/, '');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const apiUrl = (path) => `${baseUrl}${path}`;
  const escapeClass = (value) => String(value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

  const request = async (path, options = {}) => {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    if (options.body) headers.set('Content-Type', 'application/json');
    if (options.method && options.method !== 'GET') headers.set('X-CSRF-Token', csrfToken);
    let response;
    try {
      response = await fetch(apiUrl(path), { ...options, headers, credentials: 'same-origin' });
    } catch {
      throw new Error('Could not reach the TrustGrid service. Check your connection and try again.');
    }

    let payload;
    try {
      payload = await response.json();
    } catch {
      throw new Error('The server returned an unexpected response. Please try again.');
    }

    if (!response.ok) {
      if (response.status === 401 && !path.startsWith('/auth/')) {
        window.location.assign(apiUrl('/login.php'));
      }
      throw new Error(payload?.error?.message || 'The request could not be completed.');
    }
    return payload.data;
  };

  const removeAsset = async (asset, button) => {
    const confirmed = window.confirm(
      `Remove "${asset.name}" from your workspace? Its findings, review results and assessment history will also be permanently deleted. This cannot be undone.`
    );
    if (!confirmed) return;

    button.disabled = true;
    let deleted = false;
    try {
      await request(`/api/assets/${asset.id}`, { method: 'DELETE' });
      deleted = true;
      allAssets = (await request('/api/assets')).assets;
      renderAssets(document.querySelector('[data-filter].active')?.dataset.filter || 'all');
      await Promise.all([loadAssetOptions(), loadDashboardStats()]);
      if (document.querySelector('[data-workspace-page="findings"]')) {
        await loadFindings();
      }
    } catch (error) {
      window.alert(deleted
        ? `The asset was removed, but the register could not refresh. Reload the page to see the updated list. ${error.message}`
        : `The asset could not be removed: ${error.message}`);
      button.disabled = false;
    }
  };

  const make = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };

  const message = (container, text, isError = false) => {
    if (!container) return;
    container.textContent = text;
    container.classList.toggle('is-error', isError);
    container.classList.toggle('is-success', !isError && text !== '');
  };

  const bindMenu = () => {
    const toggle = document.querySelector('.menu-toggle');
    const nav = document.getElementById('primaryNav');
    toggle?.addEventListener('click', () => {
      const expanded = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', String(!expanded));
      toggle.setAttribute('aria-label', expanded ? 'Open navigation' : 'Close navigation');
      nav?.classList.toggle('is-open', !expanded);
    });
    nav?.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        toggle?.setAttribute('aria-expanded', 'false');
        toggle?.setAttribute('aria-label', 'Open navigation');
        nav.classList.remove('is-open');
      });
    });
  };

  const bindMotion = () => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches
      || !('IntersectionObserver' in window)) {
      return;
    }

    const elements = document.querySelectorAll('[data-reveal]');
    if (elements.length === 0) return;

    document.documentElement.classList.add('motion-ready');
    const observer = new IntersectionObserver((entries, currentObserver) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          currentObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -36px 0px' });

    elements.forEach((element) => observer.observe(element));
  };

  const bindLogout = () => {
    document.querySelectorAll('[data-logout]').forEach((button) => {
      button.addEventListener('click', async () => {
        button.disabled = true;
        try {
          await request('/api/auth/logout', { method: 'POST', body: JSON.stringify({}) });
          window.location.assign(apiUrl('/login.php'));
        } catch (error) {
          button.disabled = false;
          window.alert(error.message);
        }
      });
    });
  };

  const bindAuthForms = () => {
    document.querySelectorAll('[data-auth-form]').forEach((form) => {
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = form.querySelector('[type="submit"]');
        const feedback = form.querySelector('[data-form-message]');
        const data = Object.fromEntries(new FormData(form).entries());
        if (data.country === 'OTHER') data.country = 'ZZ';
        submit.disabled = true;
        message(feedback, 'Securing your workspace…');
        try {
          await request(form.dataset.endpoint, { method: 'POST', body: JSON.stringify(data) });
          window.location.assign(apiUrl('/dashboard.php'));
        } catch (error) {
          message(feedback, error.message, true);
          submit.disabled = false;
        }
      });
    });
  };

  const severityClass = (severity) => escapeClass(severity || 'unassessed');

  const fillStats = (data) => {
    const assets = data.assets || {};
    const findings = data.findings || {};
    document.querySelectorAll('[data-stat]').forEach((node) => {
      const key = node.dataset.stat;
      const value = key === 'assets' ? assets.total_assets
        : key === 'assessments' ? data.assessments
          : key === 'critical' ? findings.critical_count
            : key === 'resolved' ? findings.resolved_count : 0;
      node.textContent = Number(value || 0).toLocaleString();
    });
  };

  const makeTag = (value) => make('span', `tag ${severityClass(value)}`, value || 'Unassessed');

  const drawTrend = (points) => {
    const chart = document.getElementById('riskChart');
    if (!chart) return;
    chart.replaceChildren();
    if (!points.length) {
      chart.append(make('p', 'empty-chart', 'Risk trends will appear after your first assessment.'));
      return;
    }
    const values = points.map((point) => Number(point.average_score || 0));
    points.forEach((point) => {
      const wrap = make('div', 'risk-bar-wrap');
      const bar = make('div', 'risk-bar');
      const value = Number(point.average_score || 0);
      bar.classList.add(`score-${Math.max(0, Math.min(20, Math.round(value / 5))) * 5}`);
      bar.title = `${value}/100 risk score`;
      wrap.append(bar, make('span', 'risk-bar-value', String(value)), make('span', '', point.month));
      chart.append(wrap);
    });
  };

  const renderDashboard = (data) => {
    fillStats(data);
    drawTrend(data.risk_trend || []);
    const findingList = document.getElementById('findingList');
    if (findingList) {
      findingList.replaceChildren();
      if (!data.recent_findings?.length) {
        findingList.append(make('p', 'empty-state', 'No open findings. Add a manual finding to track an issue; safe website checks appear on the asset register.'));
      } else {
        data.recent_findings.forEach((finding) => {
          const item = make('article', 'item');
          const head = make('div', 'finding-item-head');
          head.append(make('strong', '', finding.title), makeTag(finding.severity));
          item.append(head, make('p', 'finding-asset', finding.asset_name));
          findingList.append(item);
        });
      }
    }

    const table = document.getElementById('assetTableBody');
    if (table) {
      table.replaceChildren();
      if (!data.recent_assets?.length) {
        const row = make('tr');
        const cell = make('td', 'empty-state', 'No assets registered yet. Add an organisation-owned or authorised asset to get started.');
        cell.colSpan = 6;
        row.append(cell);
        table.append(row);
      } else {
        data.recent_assets.forEach((asset) => {
          const row = make('tr');
          [asset.name, asset.type, asset.owner_team || '—'].forEach((value) => row.append(make('td', '', value)));
          const risk = make('td');
          risk.append(makeTag(asset.risk_level));
          row.append(risk, make('td', '', asset.status));
          table.append(row);
        });
      }
    }
  };

  let allAssets = [];
  const renderAssets = (filter = 'all') => {
    const table = document.getElementById('assetList');
    if (!table) return;
    const filtered = filter === 'all' ? allAssets : allAssets.filter((asset) => asset.type === filter);
    table.replaceChildren();
    if (!filtered.length) {
      const row = make('tr');
      const cell = make('td', 'empty-state', allAssets.length
        ? 'No assets match this filter.'
        : 'No assets yet. Add your first authorised asset using the form.');
      cell.colSpan = 6;
      row.append(cell);
      table.append(row);
      return;
    }

    filtered.forEach((asset) => {
      const row = make('tr');
      [asset.name, asset.type, asset.target].forEach((value) => row.append(make('td', '', value)));
      const risk = make('td');
      risk.append(makeTag(asset.current_risk));
      const status = make('td');
      status.textContent = asset.status;
      const manage = make('td');
      let hasManageAction = false;
      if (['Website', 'API'].includes(asset.type) && asset.latest_scan_id) {
        const previous = make('button', 'scan-history-link', `Last review · ${asset.latest_scan_at}`);
        previous.type = 'button';
        previous.addEventListener('click', () => viewLatestWebReview(asset));
        manage.append(previous);
        hasManageAction = true;
      }
      if (document.body.dataset.readOnly !== 'true') {
        if (['Website', 'API'].includes(asset.type)) {
          const assetLabel = asset.type === 'API' ? 'API' : 'website';
          const reviewLabel = asset.type === 'API' ? 'Start safe API review' : 'Start safe review';
          const scanActions = make('div', 'asset-review-actions');
          const scanButton = make(
            'button',
            'button button-primary button-small',
            asset.status === 'Archived' ? 'Archived' : reviewLabel
          );
          scanButton.type = 'button';
          scanButton.disabled = asset.status === 'Archived';
          if (scanButton.disabled) scanButton.title = 'Archived assets cannot be reviewed.';
          scanButton.dataset.scanAsset = String(asset.id);
          scanButton.setAttribute('aria-label', `Start safe ${assetLabel} review for ${asset.name}`);
          scanButton.addEventListener('click', () => startWebReview(asset, scanButton));
          scanActions.append(scanButton);
          manage.append(scanActions);
          hasManageAction = true;
        } else if (asset.type === 'Database') {
          if (asset.status === 'Archived') {
            manage.append(make('span', 'tag unassessed', 'Archived'));
          } else {
            const reviewLink = make('a', 'button button-secondary button-small', 'Start guided review');
            reviewLink.href = `assessments.php?asset_id=${encodeURIComponent(asset.id)}&review=database`;
            reviewLink.setAttribute('aria-label', `Start guided database review for ${asset.name}`);
            manage.append(reviewLink);
          }
          hasManageAction = true;
        }
        const selector = make('select', 'status-select');
        selector.setAttribute('aria-label', `Update status for ${asset.name}`);
        ['Active', 'Monitoring', 'Needs review', 'Archived'].forEach((value) => selector.add(new Option(value, value)));
        selector.value = asset.status;
        selector.addEventListener('change', async () => {
          selector.disabled = true;
          try {
            await request(`/api/assets/${asset.id}`, {
              method: 'PATCH',
              body: JSON.stringify({ status: selector.value }),
            });
            allAssets = (await request('/api/assets')).assets;
            renderAssets(document.querySelector('[data-filter].active')?.dataset.filter || 'all');
            loadDashboardStats();
          } catch (error) {
            window.alert(error.message);
            selector.value = asset.status;
          } finally {
            selector.disabled = false;
          }
        });
        manage.append(selector);
        const deleteButton = make('button', 'asset-delete-button', 'Remove asset');
        deleteButton.type = 'button';
        deleteButton.addEventListener('click', () => removeAsset(asset, deleteButton));
        manage.append(deleteButton);
        hasManageAction = true;
      }
      if (!hasManageAction) {
        manage.textContent = '—';
      }
      row.append(risk, status, manage);
      table.append(row);
    });
  };

  const bindAssetForm = () => {
    const form = document.getElementById('assetForm');
    if (!form) return;
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const submit = form.querySelector('[type="submit"]');
      const feedback = form.querySelector('[data-form-message]');
      const data = Object.fromEntries(new FormData(form).entries());
      data.authorization_confirmed = form.elements.authorization_confirmed.checked;
      submit.disabled = true;
      message(feedback, 'Adding authorised asset…');
      let saved = false;
      try {
        await request('/api/assets', { method: 'POST', body: JSON.stringify(data) });
        saved = true;
        form.reset();
        allAssets = (await request('/api/assets')).assets;
        renderAssets(document.querySelector('[data-filter].active')?.dataset.filter || 'all');
        message(feedback, 'Asset added to your organisation register.');
        try {
          await Promise.all([loadDashboardStats(), loadAssetOptions()]);
        } catch (error) {
          message(feedback, `Asset added, but some workspace data could not refresh: ${error.message}`, true);
        }
      } catch (error) {
        message(
          feedback,
          saved
            ? `Asset was saved, but the register could not refresh. Reload the page to view it. ${error.message}`
            : error.message,
          true
        );
      } finally {
        submit.disabled = false;
      }
    });
  };

  const renderWebReview = (asset, scan) => {
    const panel = document.getElementById('webReviewPanel');
    const title = document.getElementById('webReviewTitle');
    const summary = document.getElementById('webReviewSummary');
    const score = document.getElementById('webReviewScore');
    const checks = document.getElementById('webReviewChecks');
    if (!panel || !title || !summary || !score || !checks) return;

    panel.hidden = false;
    title.textContent = `${asset.name} · ${asset.target}`;
    const findingSummary = scan.findings_created > 0
      ? ` ${scan.findings_created} finding(s) were added automatically.`
      : ' No automatic follow-up findings were needed.';
    const limits = asset.type === 'API'
      ? ' Only the configured HTTPS endpoint was requested; response bodies were not stored or inspected.'
      : ' Only the public homepage was checked; the site was not crawled or exploited.';
    summary.textContent = `${scan.summary}${findingSummary} Completed ${scan.completed_at}. Risk indicator: ${scan.risk_score}/100 (${scan.risk_level || 'recorded'}).${limits}`;
    score.className = `tag ${severityClass(scan.risk_level)}`;
    score.textContent = `${scan.risk_level || 'Risk'} · ${scan.risk_score}/100`;
    checks.replaceChildren();
    (scan.checks || []).forEach((check) => {
      const card = make('article', `web-check web-check-${escapeClass(check.status)}`);
      const heading = make('div', 'web-check-heading');
      heading.append(make('strong', '', check.title), make('span', `tag ${severityClass(check.severity)}`, check.status.replace('_', ' ')));
      card.append(heading, make('p', '', check.evidence), make('p', 'web-check-action', check.recommendation));
      checks.append(card);
    });
    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };

  const startWebReview = async (asset, button) => {
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Reviewing…';
    try {
      const result = await request(`/api/assets/${asset.id}/scan`, {
        method: 'POST',
        body: JSON.stringify({ authorization_confirmed: true }),
      });
      renderWebReview(asset, result.scan);
      try {
        allAssets = (await request('/api/assets')).assets;
        renderAssets(document.querySelector('[data-filter].active')?.dataset.filter || 'all');
        await loadDashboardStats();
      } catch (error) {
        const summary = document.getElementById('webReviewSummary');
        if (summary) summary.textContent += ` The review was saved, but workspace data could not refresh: ${error.message}`;
      }
    } catch (error) {
      const panel = document.getElementById('webReviewPanel');
      const summary = document.getElementById('webReviewSummary');
      if (panel && summary) {
        panel.hidden = false;
        document.getElementById('webReviewTitle').textContent = `Review unavailable · ${asset.name}`;
        summary.textContent = error.message;
        document.getElementById('webReviewScore').textContent = '';
        document.getElementById('webReviewChecks').replaceChildren();
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    } finally {
      button.disabled = false;
      button.textContent = originalText;
    }
  };

  const viewLatestWebReview = async (asset) => {
    try {
      const result = await request(`/api/assets/${asset.id}/scan`);
      if (result.scan) renderWebReview(asset, result.scan);
    } catch (error) {
      window.alert(error.message);
    }
  };

  const bindAssetFilters = () => {
    document.querySelectorAll('[data-filter]').forEach((button) => {
      button.addEventListener('click', () => {
        document.querySelectorAll('[data-filter]').forEach((node) => node.classList.remove('active'));
        button.classList.add('active');
        renderAssets(button.dataset.filter);
      });
    });
  };

  const loadAssetOptions = async () => {
    const assets = (await request('/api/assets')).assets;
    const query = new URLSearchParams(window.location.search);
    document.querySelectorAll('form [name="asset_id"]').forEach((select) => {
      const currentValue = select.value;
      select.replaceChildren(new Option('Choose an authorised asset', ''));
      assets.filter((asset) => asset.status !== 'Archived').forEach((asset) => {
        select.add(new Option(`${asset.name} · ${asset.target}`, asset.id));
      });
      if (currentValue) {
        select.value = currentValue;
      } else if (query.get('asset_id') && assets.some((asset) => String(asset.id) === query.get('asset_id'))) {
        select.value = query.get('asset_id');
      }
    });
    const guidedForm = document.getElementById('guidedAssessmentForm');
    const select = guidedForm?.querySelector('[name="asset_id"]');
    if (guidedForm && select) {
      const updateFrameworkOptions = (selected = []) => {
        const selectedAsset = assets.find((asset) => String(asset.id) === select.value);
        const frameworks = assessmentCatalog.frameworks.filter(
          (framework) => framework.id !== 'database_resilience' || selectedAsset?.type === 'Database'
        );
        renderFrameworkOptions(frameworks);
        frameworks.forEach((framework) => {
          if (selected.includes(framework.id)) {
            const checkbox = guidedForm.querySelector(`[name="frameworks"][value="${framework.id}"]`);
            if (checkbox) checkbox.checked = true;
          }
        });
      };
      if (select.dataset.frameworkFilterBound !== 'true') {
        select.addEventListener('change', () => {
          const selected = [...guidedForm.querySelectorAll('[name="frameworks"]:checked')]
            .map((checkbox) => checkbox.value);
          updateFrameworkOptions(selected);
        });
        select.dataset.frameworkFilterBound = 'true';
      }
      updateFrameworkOptions();
      if (query.get('review') === 'database') {
        const asset = assets.find((item) => String(item.id) === query.get('asset_id') && item.type === 'Database');
        if (asset) {
          guidedForm.elements.title.value = `Database resilience review · ${asset.name}`;
          guidedForm.elements.scope.value = 'Evidence-based review of database access, data protection, updates, backups and recovery. Confirm controls with the responsible database or service provider; do not enter credentials, secrets or sensitive data.';
          ['nist_csf_2', 'cis_controls_8_1', 'iso_27001_2022', 'malawi_community', 'database_resilience'].forEach((frameworkId) => {
            const checkbox = guidedForm.querySelector(`[name="frameworks"][value="${frameworkId}"]`);
            if (checkbox) checkbox.checked = true;
          });
        }
      }
    }
    const findingsFilter = document.getElementById('findingsAssetFilter');
    if (findingsFilter) {
      findingsFilter.replaceChildren(new Option('All assets', ''));
      assets.forEach((asset) => findingsFilter.add(new Option(asset.name, asset.id)));
      findingsFilter.value = selectedFindingsAssetId;
    }
    renderEngagementOptions();
  };

  const bindAssessmentForm = () => {
    const form = document.getElementById('assessmentForm');
    if (!form) return;
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('[type="submit"]');
      const feedback = form.querySelector('[data-form-message]');
      const data = Object.fromEntries(new FormData(form).entries());
      data.risk_score = Number(data.risk_score);
      button.disabled = true;
      message(feedback, 'Recording manual assessment…');
      try {
        await request('/api/assessments', { method: 'POST', body: JSON.stringify(data) });
        form.reset();
        message(feedback, 'Manual assessment recorded.');
        allAssets = (await request('/api/assets')).assets;
        renderAssets(document.querySelector('[data-filter].active')?.dataset.filter || 'all');
        loadDashboardStats();
      } catch (error) {
        message(feedback, error.message, true);
      } finally {
        button.disabled = false;
      }
    });
  };

  const bindFindingForm = () => {
    const form = document.getElementById('findingForm');
    const assetSelect = form?.elements.asset_id;
    assetSelect?.addEventListener('change', () => {
      const filter = document.getElementById('findingsAssetFilter');
      if (!filter) return;
      filter.value = assetSelect.value;
      document.getElementById('findingsList')?.replaceChildren(make('p', 'loading-state', 'Loading findings for this asset…'));
      loadFindings(assetSelect.value || '').catch((error) => {
        const list = document.getElementById('findingsList');
        if (list) list.replaceChildren(make('p', 'empty-state is-error', error.message));
      });
    });
    if (!form) return;
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('[type="submit"]');
      const feedback = form.querySelector('[data-form-message]');
      const data = Object.fromEntries(new FormData(form).entries());
      button.disabled = true;
      message(feedback, 'Recording finding…');
      try {
        await request('/api/findings', { method: 'POST', body: JSON.stringify(data) });
        form.reset();
        message(feedback, 'Finding added to your organisation register.');
        await loadFindings();
        loadDashboardStats();
      } catch (error) {
        message(feedback, error.message, true);
      } finally {
        button.disabled = false;
      }
    });
  };

  let assessmentCatalog = null;
  let activeGuidedAssessment = null;
  let allEngagements = [];
  const assessmentAnswerLabels = {
    implemented: 'Yes, in place',
    partial: 'Partly in place',
    not_implemented: 'Not in place',
    not_sure: 'Not sure',
    not_applicable: 'Not applicable',
  };

  const renderFrameworkOptions = (frameworks) => {
    const container = document.getElementById('assessmentFrameworks');
    if (!container) return;
    container.replaceChildren();
    frameworks.forEach((framework) => {
      const label = make('label', 'framework-option');
      const checkbox = make('input');
      checkbox.type = 'checkbox';
      checkbox.name = 'frameworks';
      checkbox.value = framework.id;
      const text = make('span');
      text.append(make('strong', '', framework.name), make('small', '', framework.description));
      label.append(checkbox, text);
      container.append(label);
    });
  };

  const renderEngagementOptions = () => {
    const form = document.getElementById('guidedAssessmentForm');
    const select = form?.querySelector('[name="engagement_id"]');
    if (!select) return;
    const assetId = form.elements.asset_id.value;
    const previousValue = select.value;
    select.replaceChildren(new Option('No engagement link', ''));
    allEngagements
      .filter((engagement) => engagement.status === 'Active' && String(engagement.asset_id) === assetId)
      .forEach((engagement) => {
        select.add(new Option(`${engagement.title} · ${engagement.start_date} to ${engagement.end_date}`, engagement.id));
      });
    if ([...select.options].some((option) => option.value === previousValue)) {
      select.value = previousValue;
    }
  };

  const renderAssessmentEngagements = () => {
    const list = document.getElementById('assessmentEngagementList');
    if (!list) return;
    list.replaceChildren();
    if (allEngagements.length === 0) {
      list.append(make('p', 'empty-state', 'No engagement records yet. Document written permission and scope before beginning work.'));
      return;
    }
    allEngagements.forEach((engagement) => {
      const card = make('article', 'assessment-engagement-card');
      const details = make('div', 'assessment-engagement-details');
      const heading = make('div', 'assessment-engagement-heading');
      heading.append(make('h3', '', engagement.title), make('span', `tag ${engagement.status === 'Active' ? 'low' : 'unassessed'}`, engagement.status));
      details.append(
        heading,
        make('p', '', `${engagement.asset_name} · ${engagement.start_date} to ${engagement.end_date} · Lead: ${engagement.lead_name}`),
        make('p', '', `Authorisation: ${engagement.authorization_note} · ${engagement.assessment_count} assessment(s)`)
      );
      const disclosure = make('details', 'assessment-engagement-scope');
      disclosure.append(make('summary', '', 'View authorised scope and rules'));
      [
        ['In scope', engagement.scope_text],
        ['Out of scope', engagement.exclusions],
        ['Rules of engagement', engagement.rules_of_engagement],
      ].forEach(([label, value]) => {
        const paragraph = make('p');
        paragraph.append(make('strong', '', `${label}: `), document.createTextNode(value));
        disclosure.append(paragraph);
      });
      details.append(disclosure);
      card.append(details);
      const actions = make('div', 'assessment-engagement-actions');
      if (engagement.status === 'Active') {
        const start = make('button', 'button button-secondary button-small', 'Start assessment');
        start.type = 'button';
        start.addEventListener('click', () => {
          const form = document.getElementById('guidedAssessmentForm');
          if (!form) return;
          form.elements.asset_id.value = String(engagement.asset_id);
          form.elements.asset_id.dispatchEvent(new Event('change', { bubbles: true }));
          form.elements.title.value = engagement.title;
          form.elements.scope.value = engagement.scope_text;
          renderEngagementOptions();
          form.elements.engagement_id.value = String(engagement.id);
          form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
        actions.append(start);
      }
      if (engagement.status === 'Active') {
        const close = make('button', 'button button-secondary button-small', 'Close engagement');
        close.type = 'button';
        close.addEventListener('click', async () => {
          if (!window.confirm(`Close "${engagement.title}" and prevent further draft responses under it?`)) return;
          close.disabled = true;
          try {
            await request(`/api/assessments/engagements/${engagement.id}`, {
              method: 'PATCH',
              body: JSON.stringify({ status: 'Completed' }),
            });
            await loadAssessmentEngagements();
          } catch (error) {
            message(document.querySelector('#assessmentEngagementForm [data-form-message]'), error.message, true);
            close.disabled = false;
          }
        });
        actions.append(close);
      }
      if (['Active', 'Planned'].includes(engagement.status)) {
        const cancel = make('button', 'button button-secondary button-small', 'Cancel engagement');
        cancel.type = 'button';
        cancel.addEventListener('click', async () => {
          if (!window.confirm(`Cancel "${engagement.title}"?`)) return;
          cancel.disabled = true;
          try {
            await request(`/api/assessments/engagements/${engagement.id}`, {
              method: 'PATCH',
              body: JSON.stringify({ status: 'Cancelled' }),
            });
            await loadAssessmentEngagements();
          } catch (error) {
            message(document.querySelector('#assessmentEngagementForm [data-form-message]'), error.message, true);
            cancel.disabled = false;
          }
        });
        actions.append(cancel);
      }
      card.append(actions);
      list.append(card);
    });
  };

  const loadAssessmentEngagements = async () => {
    if (!document.getElementById('assessmentEngagementList')) return;
    const result = await request('/api/assessments/engagements');
    allEngagements = result.engagements;
    renderAssessmentEngagements();
    renderEngagementOptions();
  };

  const renderGuidedAssessmentList = (assessments) => {
    const list = document.getElementById('guidedAssessmentList');
    if (!list) return;
    list.replaceChildren();
    if (assessments.length === 0) {
      list.append(make('p', 'empty-state', 'No guided assessments yet. Start one above to create a practical, evidence-backed action plan.'));
      return;
    }
    assessments.forEach((assessment) => {
      const card = make('article', 'assessment-history-card');
      const details = make('div');
      details.append(
        make('h3', '', assessment.title),
        make('p', '', `${assessment.asset_name} · ${assessment.answered_count} questions answered · ${assessment.frameworks.length} baseline(s)${assessment.engagement_title ? ` · ${assessment.engagement_title}` : ''}`)
      );
      if (assessment.status === 'Completed') {
        details.append(make('p', 'assessment-history-result', `${assessment.overall_score}% safeguards in place · ${assessment.findings_count} follow-up item(s)`));
      }
      const action = make(
        'button',
        'button button-secondary button-small',
        assessment.status === 'Completed'
          ? 'View report'
          : assessment.status === 'Cancelled' ? 'View cancelled' : 'Continue assessment'
      );
      action.type = 'button';
      action.addEventListener('click', () => openGuidedAssessment(assessment.id));
      card.append(details, action);
      list.append(card);
    });
  };

  const collectGuidedAssessmentAnswers = () => {
    const controls = document.getElementById('guidedAssessmentControls');
    if (!controls) return [];
    return [...controls.querySelectorAll('[data-control-id]')].map((card) => ({
      control_id: card.dataset.controlId,
      answer: card.querySelector('[data-answer]')?.value || '',
      notes: card.querySelector('[data-answer-notes]')?.value.trim() || '',
    }));
  };

  const updateGuidedAssessmentProgress = () => {
    const progress = document.getElementById('guidedAssessmentProgress');
    if (!progress || !activeGuidedAssessment) return;
    const controls = activeGuidedAssessment.controls || [];
    const answers = collectGuidedAssessmentAnswers();
    const answered = answers.filter((answer) => answer.answer !== '').length;
    progress.replaceChildren();
    const label = make('span', '', `${answered} of ${controls.length} questions answered`);
    const track = make('span', 'assessment-progress-track');
    const bar = make('span', 'assessment-progress-bar');
    bar.style.width = `${controls.length ? Math.round((answered / controls.length) * 100) : 0}%`;
    track.append(bar);
    progress.append(label, track);
  };

  const renderGuidedAssessmentDetail = (assessment) => {
    activeGuidedAssessment = assessment;
    const workspace = document.getElementById('guidedAssessmentWorkspace');
    if (!workspace) return;
    workspace.hidden = false;
    document.getElementById('guidedAssessmentAsset').textContent = assessment.asset_name;
    document.getElementById('guidedAssessmentTitle').textContent = assessment.title;
    document.getElementById('guidedAssessmentScope').textContent = assessment.scope;
    const engagementInfo = document.getElementById('guidedAssessmentEngagement');
    if (engagementInfo) {
      engagementInfo.textContent = assessment.engagement_title
        ? `Authorised engagement: ${assessment.engagement_title} · ${assessment.engagement_start_date} to ${assessment.engagement_end_date} · Lead: ${assessment.engagement_lead}. In scope: ${assessment.engagement_scope} Out of scope: ${assessment.engagement_exclusions} Rules: ${assessment.rules_of_engagement} Authorisation: ${assessment.engagement_authorization_note}`
        : 'No engagement is linked. Confirm the organisation’s authorisation and scope before any technical work.';
    }
    const status = document.getElementById('guidedAssessmentStatus');
    status.className = `tag ${assessment.status === 'Completed' ? 'low' : assessment.status === 'Cancelled' ? 'unassessed' : 'moderate'}`;
    status.textContent = assessment.status;
    const frameworkTags = document.getElementById('guidedAssessmentFrameworkTags');
    frameworkTags.replaceChildren();
    assessment.frameworks.forEach((id) => {
      const framework = assessmentCatalog.frameworks.find((item) => item.id === id);
      if (framework) frameworkTags.append(make('span', 'tag unassessed', framework.name));
    });

    const completed = assessment.status === 'Completed';
    const cancelled = assessment.status === 'Cancelled';
    const readOnly = completed || cancelled;
    const cards = document.getElementById('guidedAssessmentControls');
    cards.replaceChildren();
    let activeArea = '';
    assessment.controls.forEach((control) => {
      if (control.area !== activeArea) {
        activeArea = control.area;
        cards.append(make('h3', 'assessment-area-heading', activeArea));
      }
      const card = make('article', 'guided-control-card');
      card.dataset.controlId = control.id;
      const previous = assessment.answers[control.id] || {};
      const header = make('div', 'guided-control-heading');
      header.append(make('span', 'assessment-control-title', control.title), make('span', 'assessment-weight', `Priority ${control.weight}/5`));
      card.append(
        header,
        make('p', 'guided-control-question', control.question),
        make('p', 'guided-control-guidance', control.guidance),
        make('p', 'guided-control-mapping', `Mapped themes: ${(control.mapped_baselines || []).join(' · ')}`)
      );
      const label = make('label', 'guided-answer-label', 'How does this work today?');
      const select = make('select');
      select.dataset.answer = 'true';
      select.setAttribute('aria-label', `Answer: ${control.title}`);
      select.add(new Option('Choose the closest answer', ''));
      assessmentCatalog.answers.forEach((answer) => select.add(new Option(answer.label, answer.id)));
      select.value = previous.answer || '';
      select.disabled = readOnly;
      label.append(select);
      card.append(label);
      const notesLabel = make('label', 'guided-notes-label', 'Evidence or a short example (optional)');
      const notes = make('textarea');
      notes.rows = 2;
      notes.maxLength = 4000;
      notes.placeholder = 'What did you see, or who can confirm this? Do not include passwords or secrets.';
      notes.value = previous.notes || '';
      notes.dataset.answerNotes = 'true';
      notes.disabled = readOnly;
      notesLabel.append(notes);
      card.append(notesLabel);
      if (readOnly && previous.answer) {
        card.append(make('p', `assessment-answer-result assessment-answer-${escapeClass(previous.answer)}`, `Response: ${assessmentAnswerLabels[previous.answer]}`));
      }
      cards.append(card);
    });
    cards.querySelectorAll('[data-answer]').forEach((select) => select.addEventListener('change', updateGuidedAssessmentProgress));
    cards.querySelectorAll('[data-answer-notes]').forEach((notes) => notes.addEventListener('input', updateGuidedAssessmentProgress));
    updateGuidedAssessmentProgress();

    const actions = document.getElementById('guidedAssessmentActions');
    actions.hidden = readOnly;
    const readOnlyMessage = document.getElementById('guidedAssessmentReadOnlyMessage');
    if (readOnlyMessage) {
      readOnlyMessage.hidden = !cancelled;
      readOnlyMessage.textContent = cancelled ? 'This draft was cancelled and is read-only. No findings were generated.' : '';
    }
    const results = document.getElementById('guidedAssessmentResults');
    results.hidden = !completed;
    if (completed) {
      const gaps = assessment.findings || [];
      const pdfLink = document.getElementById('downloadGuidedAssessmentPdf');
      if (pdfLink) {
        pdfLink.href = `assessment-pdf.php?id=${encodeURIComponent(assessment.id)}`;
      }
      document.getElementById('guidedAssessmentResultSummary').textContent =
        `${assessment.overall_score}% weighted safeguards recorded as in place. ${gaps.length} follow-up item(s) were added to the Findings register. Completed ${assessment.completed_at}.`;
      const stats = document.getElementById('guidedAssessmentResultStats');
      stats.replaceChildren(
        make('article', 'mini-card', `${assessment.overall_score}% safeguards in place`),
        make('article', 'mini-card', `${assessment.controls.length} questions reviewed`),
        make('article', 'mini-card', `${gaps.length} follow-up items`)
      );
      const findingList = document.getElementById('guidedAssessmentResultFindings');
      findingList.replaceChildren();
      if (!gaps.length) {
        findingList.append(make('p', 'empty-state', 'No follow-up actions were identified by these answers.'));
      } else {
        gaps.forEach((finding) => {
          const item = make('article', 'finding-card');
          const heading = make('div', 'finding-card-top');
          heading.append(make('h4', '', finding.title), makeTag(finding.severity));
          item.append(heading, make('p', 'finding-description', finding.recommendation));
          findingList.append(item);
        });
      }
      workspace.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  const openGuidedAssessment = async (id) => {
    const workspace = document.getElementById('guidedAssessmentWorkspace');
    const messageNode = document.getElementById('guidedAssessmentMessage');
    if (workspace) workspace.hidden = false;
    if (messageNode) message(messageNode, 'Loading assessment…');
    try {
      const result = await request(`/api/assessments/guided/${id}`);
      renderGuidedAssessmentDetail(result.assessment);
      if (messageNode) message(messageNode, '');
    } catch (error) {
      if (messageNode) message(messageNode, error.message, true);
    }
  };

  const bindGuidedAssessment = () => {
    const form = document.getElementById('guidedAssessmentForm');
    form?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('[type="submit"]');
      const feedback = form.querySelector('[data-form-message]');
      const data = Object.fromEntries(new FormData(form).entries());
      data.frameworks = [...form.querySelectorAll('[name="frameworks"]:checked')].map((checkbox) => checkbox.value);
      if (data.frameworks.length === 0) {
        message(feedback, 'Select at least one assessment baseline.', true);
        return;
      }
      button.disabled = true;
      message(feedback, 'Preparing your assessment…');
      try {
        const result = await request('/api/assessments/guided', {
          method: 'POST',
          body: JSON.stringify(data),
        });
        form.reset();
        message(feedback, 'Assessment started. Your progress is saved as you go.');
        await loadGuidedAssessments();
        await loadAssessmentEngagements();
        await openGuidedAssessment(result.id);
      } catch (error) {
        message(feedback, error.message, true);
      } finally {
        button.disabled = false;
      }
    });

    document.getElementById('saveAssessmentAnswers')?.addEventListener('click', async (event) => {
      if (!activeGuidedAssessment) return;
      const button = event.currentTarget;
      const answers = collectGuidedAssessmentAnswers().filter((answer) => answer.answer !== '');
      button.disabled = true;
      try {
        await request(`/api/assessments/guided/${activeGuidedAssessment.id}/answers`, {
          method: 'POST',
          body: JSON.stringify({ answers }),
        });
        message(document.getElementById('guidedAssessmentMessage'), 'Your answers are saved. You can return to this draft later.');
        await loadGuidedAssessments();
      } catch (error) {
        message(document.getElementById('guidedAssessmentMessage'), error.message, true);
      } finally {
        button.disabled = false;
      }
    });

    document.getElementById('completeGuidedAssessment')?.addEventListener('click', async (event) => {
      if (!activeGuidedAssessment) return;
      const answers = collectGuidedAssessmentAnswers();
      const unanswered = answers.filter((answer) => answer.answer === '').length;
      if (unanswered > 0) {
        message(document.getElementById('guidedAssessmentMessage'), `Please answer the ${unanswered} remaining question(s), or select “Not applicable”.`, true);
        document.querySelector('[data-control-id] [data-answer]:invalid')?.focus();
        document.querySelector('.guided-control-card select[data-answer]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      if (!window.confirm('Complete this assessment and add follow-up items to the Findings register? Completed assessments are read-only.')) return;
      const button = event.currentTarget;
      button.disabled = true;
      try {
        await request(`/api/assessments/guided/${activeGuidedAssessment.id}/complete`, {
          method: 'POST',
          body: JSON.stringify({ answers }),
        });
        message(document.getElementById('guidedAssessmentMessage'), 'Assessment completed. Your summary and action plan are ready.');
        await loadGuidedAssessments();
        await openGuidedAssessment(activeGuidedAssessment.id);
      } catch (error) {
        message(document.getElementById('guidedAssessmentMessage'), error.message, true);
      } finally {
        button.disabled = false;
      }
    });

    document.getElementById('cancelGuidedAssessment')?.addEventListener('click', async (event) => {
      if (!activeGuidedAssessment || activeGuidedAssessment.status !== 'Draft') return;
      if (!window.confirm('Cancel this draft? Its saved responses will remain read-only, and no findings will be created.')) return;
      const button = event.currentTarget;
      button.disabled = true;
      try {
        await request(`/api/assessments/guided/${activeGuidedAssessment.id}/cancel`, { method: 'POST' });
        await loadGuidedAssessments();
        await openGuidedAssessment(activeGuidedAssessment.id);
      } catch (error) {
        message(document.getElementById('guidedAssessmentMessage'), error.message, true);
      } finally {
        button.disabled = false;
      }
    });

    document.getElementById('printGuidedAssessment')?.addEventListener('click', () => {
      document.body.classList.add('printing-assessment');
      window.print();
    });
    window.addEventListener('afterprint', () => document.body.classList.remove('printing-assessment'));
  };

  const bindEngagementForm = () => {
    const form = document.getElementById('assessmentEngagementForm');
    if (!form) return;
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('[type="submit"]');
      const feedback = form.querySelector('[data-form-message]');
      const data = Object.fromEntries(new FormData(form).entries());
      data.authorization_confirmed = form.elements.authorization_confirmed.checked;
      if (data.end_date < data.start_date) {
        message(feedback, 'The end date must be on or after the start date.', true);
        return;
      }
      button.disabled = true;
      message(feedback, 'Recording the authorised engagement…');
      try {
        await request('/api/assessments/engagements', {
          method: 'POST',
          body: JSON.stringify(data),
        });
        form.reset();
        message(feedback, 'Engagement recorded. Keep the written approval available to the team.');
        await loadAssessmentEngagements();
      } catch (error) {
        message(feedback, error.message, true);
      } finally {
        button.disabled = false;
      }
    });
  };

  const loadGuidedAssessments = async () => {
    const list = document.getElementById('guidedAssessmentList');
    if (!list) return [];
    const result = await request('/api/assessments/guided');
    renderGuidedAssessmentList(result.assessments);
    return result.assessments;
  };

  let allFindings = [];
  let selectedFindingsAssetId = '';
  const renderFindings = (filter = 'all') => {
    const list = document.getElementById('findingsList');
    if (!list) return;
    const visible = filter === 'all' ? allFindings : allFindings.filter((finding) => finding.status === filter);
    list.replaceChildren();
    if (!visible.length) {
      list.append(make('p', 'empty-state', allFindings.length
        ? 'No findings in this status.'
        : selectedFindingsAssetId
          ? 'No findings have been recorded for this asset yet. Run a safe website review or add a manual finding.'
          : 'No findings have been recorded yet. Run a safe website review or add a manual finding.'));
      return;
    }
    visible.forEach((finding) => {
      const card = make('article', 'finding-card');
      const top = make('div', 'finding-card-top');
      const heading = make('div');
      heading.append(make('p', 'finding-asset', finding.asset_name), make('h3', '', finding.title));
      const tags = make('div', 'finding-tags');
      tags.append(makeTag(finding.severity), make('span', `tag status-${escapeClass(finding.status)}`, finding.status));
      top.append(heading, tags);
      card.append(top, make('p', 'finding-description', finding.description));
      if (finding.evidence) {
        const evidence = make('p', 'finding-evidence');
        evidence.append(make('strong', '', 'Evidence noted: '), document.createTextNode(finding.evidence));
        card.append(evidence);
      }
      const recommendation = make('p', 'finding-recommendation');
      recommendation.append(make('strong', '', 'Recommended action: '), document.createTextNode(finding.recommendation));
      card.append(recommendation);
      if (document.querySelector('[data-workspace-page="findings"]') && document.body.dataset.readOnly !== 'true') {
        const selector = make('select', 'status-select');
        selector.setAttribute('aria-label', `Update status for ${finding.title}`);
        ['Open', 'In progress', 'Resolved'].forEach((status) => selector.add(new Option(status, status)));
        selector.value = finding.status;
        selector.addEventListener('change', async () => {
          selector.disabled = true;
          try {
            await request(`/api/findings/${finding.id}`, {
              method: 'PATCH',
              body: JSON.stringify({ status: selector.value }),
            });
            await loadFindings();
            loadDashboardStats();
          } catch (error) {
            window.alert(error.message);
            selector.value = finding.status;
          } finally {
            selector.disabled = false;
          }
        });
        const controls = make('div', 'finding-controls');
        controls.append(make('span', '', 'Update status'), selector);
        card.append(controls);
      }
      list.append(card);
    });
  };

  const renderReport = (assets, findings) => {
    const assetTable = document.getElementById('reportAssets');
    if (assetTable) {
      assetTable.replaceChildren();
      if (!assets.length) {
        const row = make('tr');
        const cell = make('td', 'empty-state', 'No registered assets.');
        cell.colSpan = 4;
        row.append(cell);
        assetTable.append(row);
      } else {
        assets.forEach((asset) => {
          const row = make('tr');
          [asset.name, asset.type].forEach((value) => row.append(make('td', '', value)));
          const risk = make('td');
          risk.append(makeTag(asset.current_risk));
          row.append(risk, make('td', '', asset.status));
          assetTable.append(row);
        });
      }
    }
    const reportFindings = document.getElementById('reportFindings');
    if (reportFindings) {
      reportFindings.replaceChildren();
      const open = findings.filter((finding) => finding.status !== 'Resolved');
      if (!open.length) reportFindings.append(make('p', 'empty-state', 'No open findings recorded.'));
      open.forEach((finding) => {
        const item = make('div', 'report-finding-row');
        const label = make('div');
        label.append(make('strong', '', finding.title), make('span', 'finding-asset', finding.asset_name));
        item.append(label, makeTag(finding.severity), make('p', 'finding-recommendation', finding.recommendation));
        reportFindings.append(item);
      });
    }
  };

  const loadDashboardStats = async () => {
    const page = document.querySelector('[data-workspace-page="dashboard"], [data-workspace-page="reports"]');
    if (!page) return null;
    const data = await request('/api/dashboard');
    fillStats(data);
    return data;
  };

  const loadFindings = async (assetId = selectedFindingsAssetId) => {
    selectedFindingsAssetId = String(assetId || '');
    const query = selectedFindingsAssetId
      ? `?asset_id=${encodeURIComponent(selectedFindingsAssetId)}`
      : '';
    const result = await request(`/api/findings${query}`);
    allFindings = result.findings;
    renderFindings(document.querySelector('[data-finding-filter].active')?.dataset.findingFilter || 'all');
    const summary = document.getElementById('findingsSummary');
    if (summary) {
      const open = allFindings.filter((finding) => finding.status === 'Open').length;
      const progress = allFindings.filter((finding) => finding.status === 'In progress').length;
      const resolved = allFindings.filter((finding) => finding.status === 'Resolved').length;
      const selectedAsset = allAssets.find((asset) => String(asset.id) === selectedFindingsAssetId);
      summary.replaceChildren(
        ...(selectedAsset ? [make('span', '', `Findings for ${selectedAsset.name}`)] : []),
        make('span', '', `${allFindings.length} total`),
        make('span', '', `${open} open`),
        make('span', '', `${progress} in progress`),
        make('span', '', `${resolved} resolved`)
      );
    }
    return result.findings;
  };

  const loadWorkspace = async () => {
    const page = document.querySelector('[data-workspace-page]');
    if (!page) return;
    try {
      if (page.dataset.workspacePage === 'assessments') {
        const catalog = await request('/api/assessments/catalog');
        assessmentCatalog = catalog;
        renderFrameworkOptions(catalog.frameworks);
      }
      const dashboardPromise = loadDashboardStats();
      const assetsPromise = request('/api/assets').then((result) => {
        allAssets = result.assets;
        renderAssets();
        return result.assets;
      });
      const findingsPromise = loadFindings();
      const [dashboard, assets, findings] = await Promise.all([dashboardPromise, assetsPromise, findingsPromise]);
      if (page.dataset.workspacePage === 'dashboard' && dashboard) renderDashboard(dashboard);
      if (page.dataset.workspacePage === 'reports') renderReport(assets, findings);
      await loadAssetOptions();
      if (page.dataset.workspacePage === 'assessments') {
        await loadGuidedAssessments();
        await loadAssessmentEngagements();
        document.querySelector('#guidedAssessmentForm [name="asset_id"]')
          ?.addEventListener('change', renderEngagementOptions);
      }
      const findingsAssetFilter = document.getElementById('findingsAssetFilter');
      findingsAssetFilter?.addEventListener('change', () => {
        document.getElementById('findingsList')?.replaceChildren(make('p', 'loading-state', 'Loading findings for this asset…'));
        loadFindings(findingsAssetFilter.value).catch((error) => {
          const list = document.getElementById('findingsList');
          if (list) list.replaceChildren(make('p', 'empty-state is-error', error.message));
        });
      });
    } catch (error) {
      page.querySelectorAll('.loading-state').forEach((node) => {
        node.textContent = error.message;
        node.classList.add('is-error');
      });
    }
  };

  document.querySelectorAll('[data-finding-filter]').forEach((button) => {
    button.addEventListener('click', () => {
      document.querySelectorAll('[data-finding-filter]').forEach((node) => node.classList.remove('active'));
      button.classList.add('active');
      renderFindings(button.dataset.findingFilter);
    });
  });
  document.querySelector('[data-print-report]')?.addEventListener('click', () => window.print());
  bindMenu();
  bindMotion();
  bindLogout();
  bindAuthForms();
  bindAssetForm();
  bindAssetFilters();
  bindAssessmentForm();
  bindFindingForm();
  bindGuidedAssessment();
  bindEngagementForm();
  loadWorkspace();
})();
