(() => {
  const body = document.body;
  const baseUrl = (body.dataset.baseUrl || '').replace(/\/$/, '');
  let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const apiUrl = (path) => `${baseUrl}${path}`;
  const escapeClass = (value) => String(value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

  const request = async (path, options = {}) => {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    if (options.body && !(options.body instanceof FormData)) headers.set('Content-Type', 'application/json');
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

  const loadHumanChallenge = async (form) => {
    const container = form.querySelector('[data-human-challenge]');
    if (!container) return;
    const label = container.querySelector('span');
    try {
      const challenge = await request('/api/public/challenge');
      label.textContent = `Human check: ${challenge.question}`;
      form.elements.challenge_id.value = challenge.challenge_id;
      form.elements.challenge_answer.value = '';
    } catch (error) {
      label.textContent = error.message;
    }
  };

  const renderPagination = (containerId, pagination, onPage) => {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.replaceChildren();
    if (!pagination || pagination.pages <= 1) return;
    const nav = make('nav', 'pagination');
    nav.setAttribute('aria-label', 'Pages');
    const add = (page, label, active = false) => {
      const button = make('button', active ? 'active' : '', label);
      button.type = 'button';
      button.disabled = active;
      button.addEventListener('click', () => onPage(page));
      nav.append(button);
    };
    if (pagination.page > 1) add(pagination.page - 1, 'Previous');
    const first = Math.max(1, Math.min(pagination.page - 2, pagination.pages - 4));
    const last = Math.min(pagination.pages, first + 4);
    for (let page = first; page <= last; page++) add(page, String(page), page === pagination.page);
    if (pagination.page < pagination.pages) add(pagination.page + 1, 'Next');
    container.append(nav);
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
    document.addEventListener('click', (event) => {
      document.querySelectorAll('.account-menu[open]').forEach((menu) => {
        if (!menu.contains(event.target)) menu.removeAttribute('open');
      });
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') document.querySelectorAll('.account-menu[open]').forEach((menu) => menu.removeAttribute('open'));
    });
    document.querySelectorAll('.account-menu .nav-avatar-image').forEach((image) => {
      const setAvatarVisibility = (available) => {
        image.hidden = !available;
        const fallback = image.parentElement?.querySelector('.avatar-initial');
        if (fallback) fallback.hidden = available;
      };
      image.addEventListener('load', () => setAvatarVisibility(true));
      image.addEventListener('error', () => setAvatarVisibility(false));
      if (image.complete) setAvatarVisibility(image.naturalWidth > 0);
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
        const isRegistration = form.dataset.endpoint === '/api/auth/register';
        submit.disabled = true;
        message(feedback, isRegistration ? 'Submitting your workspace request…' : 'Securing your session…');
        try {
          await request(form.dataset.endpoint, { method: 'POST', body: JSON.stringify(data) });
          window.location.assign(apiUrl(isRegistration ? '/pending.php' : '/dashboard.php'));
        } catch (error) {
          message(feedback, error.message, true);
          submit.disabled = false;
          if (isRegistration) loadHumanChallenge(form);
        }
      });
        loadHumanChallenge(form);
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
  const activeScanPolls = new Set();
  const localIsoDate = (date) => {
    const adjusted = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return adjusted.toISOString().slice(0, 10);
  };
  const dateAfterDays = (days) => {
    const date = new Date();
    date.setDate(date.getDate() + days);
    return localIsoDate(date);
  };

  const renderScanSchedule = (asset, container) => {
    const details = make('details', 'scan-schedule-details');
    const isActive = asset.scan_schedule_status === 'Active';
    const summary = make(
      'summary',
      '',
      isActive
        ? `Automatic checks · ${asset.scan_schedule_frequency} until ${asset.scan_schedule_expires_at.slice(0, 10)}`
        : asset.scan_schedule_status === 'Paused'
          ? 'Automatic checks · Paused'
          : 'Schedule automatic checks'
    );
    details.append(summary);

    const form = document.createElement('form');
    form.className = 'scan-schedule-form';
    const frequencyLabel = make('label', '', 'Frequency');
    const frequency = document.createElement('select');
    frequency.name = 'frequency';
    frequency.add(new Option('Weekly', 'weekly'));
    frequency.add(new Option('Daily', 'daily'));
    frequency.value = asset.scan_schedule_frequency || 'weekly';
    frequencyLabel.append(frequency);

    const endLabel = make('label', '', 'Authorised through');
    const endDate = document.createElement('input');
    endDate.type = 'date';
    endDate.name = 'authorized_until';
    endDate.min = dateAfterDays(frequency.value === 'weekly' ? 7 : 1);
    endDate.max = dateAfterDays(90);
    endDate.value = asset.scan_schedule_expires_at?.slice(0, 10) || dateAfterDays(30);
    endDate.required = true;
    endLabel.append(endDate);
    frequency.addEventListener('change', () => {
      endDate.min = dateAfterDays(frequency.value === 'weekly' ? 7 : 1);
      if (endDate.value < endDate.min) endDate.value = endDate.min;
    });

    const authorization = make('label', 'checkbox-label', '');
    const authorizationInput = document.createElement('input');
    authorizationInput.type = 'checkbox';
    authorizationInput.name = 'authorization_confirmed';
    authorizationInput.required = true;
    const authorizationText = make('span', '', 'I authorize recurring safe reviews until this date.');
    authorization.append(authorizationInput, authorizationText);

    const feedback = make('p', 'form-message', '');
    feedback.dataset.formMessage = '';
    feedback.setAttribute('role', 'status');
    const save = make('button', 'button button-secondary button-small', isActive ? 'Update schedule' : 'Enable schedule');
    save.type = 'submit';
    form.append(frequencyLabel, endLabel, authorization, feedback, save);
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      save.disabled = true;
      try {
        await request(`/api/assets/${asset.id}/scan-schedule`, {
          method: 'PUT',
          body: JSON.stringify({
            frequency: frequency.value,
            authorized_until: endDate.value,
            authorization_confirmed: authorizationInput.checked,
          }),
        });
        allAssets = (await request('/api/assets')).assets;
        renderAssets(document.querySelector('[data-filter].active')?.dataset.filter || 'all');
      } catch (error) {
        message(feedback, error.message, true);
        save.disabled = false;
      }
    });
    details.append(form, make('small', 'scan-schedule-note', 'Runs only this read-only Website/API review. Schedules expire within 90 days; the worker must be configured by the service operator.'));

    if (isActive) {
      const pause = make('button', 'scan-schedule-pause', 'Pause schedule');
      pause.type = 'button';
      pause.addEventListener('click', async () => {
        pause.disabled = true;
        try {
          await request(`/api/assets/${asset.id}/scan-schedule`, { method: 'DELETE' });
          allAssets = (await request('/api/assets')).assets;
          renderAssets(document.querySelector('[data-filter].active')?.dataset.filter || 'all');
        } catch (error) {
          window.alert(error.message);
          pause.disabled = false;
        }
      });
      details.append(pause);
    }
    container.append(details);
  };

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
          const reviewLabel = asset.type === 'API' ? 'Queue safe API review' : 'Queue safe review';
          const scanActions = make('div', 'asset-review-actions');
          const jobPending = ['Queued', 'Running'].includes(asset.latest_job_status);
          const scanButton = make(
            'button',
            'button button-primary button-small',
            asset.status === 'Archived' ? 'Archived' : jobPending ? `Review ${asset.latest_job_status.toLowerCase()}` : reviewLabel
          );
          scanButton.type = 'button';
          scanButton.disabled = asset.status === 'Archived' || jobPending;
          if (asset.status === 'Archived') scanButton.title = 'Archived assets cannot be reviewed.';
          else if (jobPending) scanButton.title = 'A review job is already queued or running.';
          scanButton.dataset.scanAsset = String(asset.id);
          scanButton.setAttribute('aria-label', `Queue safe ${assetLabel} review for ${asset.name}`);
          scanButton.addEventListener('click', () => startWebReview(asset, scanButton));
          scanActions.append(scanButton);
          if (jobPending) {
            scanActions.append(make('small', 'scan-job-status', `Review ${asset.latest_job_status.toLowerCase()}`));
            monitorScanJob(asset, Number(asset.latest_job_id));
          }
          manage.append(scanActions);
          renderScanSchedule(asset, manage);
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
    summary.classList.remove('is-error');
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

  const renderScanJobStatus = (asset, text, isError = false) => {
    const panel = document.getElementById('webReviewPanel');
    const summary = document.getElementById('webReviewSummary');
    const title = document.getElementById('webReviewTitle');
    const score = document.getElementById('webReviewScore');
    const checks = document.getElementById('webReviewChecks');
    if (!panel || !summary || !title || !score || !checks) return;
    panel.hidden = false;
    title.textContent = `${asset.name} · ${asset.target}`;
    summary.textContent = text;
    summary.classList.toggle('is-error', isError);
    score.textContent = '';
    checks.replaceChildren();
  };

  const monitorScanJob = async (asset, jobId) => {
    const pollKey = `${asset.id}:${jobId}`;
    if (!jobId || activeScanPolls.has(pollKey)) return;
    activeScanPolls.add(pollKey);
    try {
      for (let attempt = 0; attempt < 40; attempt += 1) {
        if (attempt > 0) await new Promise((resolve) => window.setTimeout(resolve, 3000));
        const result = await request(`/api/assets/${asset.id}/scan-jobs/${jobId}`);
        if (result.job.status === 'Completed') {
          const scan = await request(`/api/assets/${asset.id}/scan`);
          if (scan.scan) renderWebReview(asset, scan.scan);
          allAssets = (await request('/api/assets')).assets;
          renderAssets(document.querySelector('[data-filter].active')?.dataset.filter || 'all');
          await loadDashboardStats();
          return;
        }
        if (['Failed', 'Cancelled'].includes(result.job.status)) {
          renderScanJobStatus(
            asset,
            result.job.error_message || `The review job was ${result.job.status.toLowerCase()}.`,
            result.job.status === 'Failed'
          );
          return;
        }
        renderScanJobStatus(
          asset,
          `Safe review ${result.job.status.toLowerCase()}. The scan worker processes queued jobs in the background.`
        );
      }
      renderScanJobStatus(asset, 'The review is still queued or running. You can leave this page; its status will be available when you return.');
    } catch (error) {
      renderScanJobStatus(asset, `Could not check the review status: ${error.message}`, true);
    } finally {
      activeScanPolls.delete(pollKey);
    }
  };

  const startWebReview = async (asset, button) => {
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Queueing…';
    try {
      const result = await request(`/api/assets/${asset.id}/scan`, {
        method: 'POST',
        body: JSON.stringify({ authorization_confirmed: true }),
      });
      renderScanJobStatus(asset, 'The authorised review is queued. A background worker will process it shortly.');
      await monitorScanJob(asset, Number(result.job.id));
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
        summary.classList.add('is-error');
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
        guidedAssessmentPage = 1;
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

  const loadProfileDirectory = async (page = 1) => {
    const container = document.getElementById('profileDirectory');
    if (!container) return;
    try {
      const result = await request(`/api/profiles?page=${page}`);
      container.replaceChildren();
      if (result.profiles.length === 0) {
        container.append(make('p', 'empty-state', 'No active organisation profiles are available.'));
      }
      result.profiles.forEach((profile) => {
        const card = make('article', 'profile-directory-card');
        if (profile.avatar_path) {
          const avatar = make('img', 'profile-directory-avatar');
          avatar.src = apiUrl(`/${profile.avatar_path.replace(/^\/+/, '')}`);
          avatar.alt = `${profile.full_name} profile picture`;
          avatar.loading = 'lazy';
          card.append(avatar);
        } else {
          card.append(make('span', 'profile-directory-avatar profile-directory-initial', profile.full_name.slice(0, 1)));
        }
        const details = make('div', 'profile-directory-details');
        details.append(make('h3', '', profile.full_name));
        const affiliation = [profile.work, profile.school].filter(Boolean).join(' / ');
        if (affiliation) details.append(make('p', 'profile-directory-affiliation', affiliation));
        if (profile.bio) details.append(make('p', 'profile-directory-bio', profile.bio));
        if (profile.qualifications) details.append(make('p', 'profile-directory-qualifications', profile.qualifications));
        card.append(details);
        container.append(card);
      });
      renderPagination('profileDirectoryPagination', result.pagination, loadProfileDirectory);
    } catch (error) {
      container.replaceChildren(make('p', 'form-message is-error', error.message));
    }
  };

  const loadProfile = async () => {
    const form = document.getElementById('profileForm');
    if (!form) return;
    try {
      const { profile } = await request('/api/profile');
      ['full_name', 'bio', 'school', 'work', 'qualifications'].forEach((key) => {
        form.elements[key].value = profile[key] || '';
      });
      if (profile.avatar_path) {
        const avatar = document.getElementById('profileAvatarPreview');
        avatar.src = apiUrl(`/${profile.avatar_path.replace(/^\/+/, '')}`);
        avatar.hidden = false;
      }
    } catch (error) {
      message(form.querySelector('[data-form-message]'), error.message, true);
    }
    await loadProfileDirectory();

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('[type="submit"]');
      const data = Object.fromEntries(new FormData(form).entries());
      button.disabled = true;
      message(form.querySelector('[data-form-message]'), 'Saving your profile…');
      try {
        const result = await request('/api/profile', { method: 'POST', body: JSON.stringify(data) });
        const navName = document.querySelector('[data-account-name]');
        if (navName) {
          navName.textContent = data.full_name;
          const accountMenu = navName.closest('.account-menu');
          accountMenu?.querySelector('summary')?.setAttribute('aria-label', `Account options for ${data.full_name}`);
          const initial = accountMenu?.querySelector('.avatar-initial');
          if (initial) initial.textContent = [...data.full_name.trim()][0]?.toLocaleUpperCase() || '';
        }
        if (result.csrf_token) {
          csrfToken = result.csrf_token;
          document.querySelector('meta[name="csrf-token"]').content = csrfToken;
        }
        form.elements.current_password.value = '';
        form.elements.new_password.value = '';
        message(form.querySelector('[data-form-message]'), 'Your profile was updated.');
        await loadProfileDirectory();
      } catch (error) {
        message(form.querySelector('[data-form-message]'), error.message, true);
      } finally {
        button.disabled = false;
      }
    });

    const avatarInput = document.getElementById('profileAvatarInput');
    const cropDialog = document.getElementById('avatarCropDialog');
    const cropCanvas = document.getElementById('avatarCropCanvas');
    const cropZoom = document.getElementById('avatarCropZoom');
    const cropSave = document.getElementById('avatarCropSave');
    if (avatarInput && cropDialog && cropCanvas && cropZoom && cropSave) {
      const context = cropCanvas.getContext('2d');
      const cropImage = new Image();
      const cropSize = cropCanvas.width;
      let sourceUrl = '';
      let panX = 0;
      let panY = 0;
      let dragging = false;
      let lastX = 0;
      let lastY = 0;
      const drawCrop = () => {
        if (!context || !cropImage.naturalWidth) return;
        const zoom = Number(cropZoom.value);
        const scale = Math.max(cropSize / cropImage.naturalWidth, cropSize / cropImage.naturalHeight) * zoom;
        const width = cropImage.naturalWidth * scale;
        const height = cropImage.naturalHeight * scale;
        const centeredX = (cropSize - width) / 2;
        const centeredY = (cropSize - height) / 2;
        panX = Math.max(cropSize - width - centeredX, Math.min(-centeredX, panX));
        panY = Math.max(cropSize - height - centeredY, Math.min(-centeredY, panY));
        context.clearRect(0, 0, cropSize, cropSize);
        context.drawImage(cropImage, centeredX + panX, centeredY + panY, width, height);
      };
      const releaseCropSource = () => {
        if (sourceUrl) URL.revokeObjectURL(sourceUrl);
        sourceUrl = '';
        cropImage.removeAttribute('src');
        avatarInput.value = '';
      };
      cropDialog.addEventListener('close', releaseCropSource);
      avatarInput.addEventListener('change', () => {
        const file = avatarInput.files?.[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
          message(form.querySelector('[data-form-message]'), 'Choose a JPEG, PNG or WebP image smaller than 2 MB.', true);
          avatarInput.value = '';
          return;
        }
        sourceUrl = URL.createObjectURL(file);
        cropZoom.value = '1';
        panX = 0;
        panY = 0;
        cropImage.onload = () => {
          drawCrop();
          cropDialog.showModal();
        };
        cropImage.onerror = () => {
          releaseCropSource();
          message(form.querySelector('[data-form-message]'), 'The selected image could not be opened.', true);
        };
        cropImage.src = sourceUrl;
      });
      cropZoom.addEventListener('input', drawCrop);
      cropCanvas.addEventListener('pointerdown', (event) => {
        if (!cropImage.naturalWidth) return;
        dragging = true;
        lastX = event.clientX;
        lastY = event.clientY;
        cropCanvas.setPointerCapture(event.pointerId);
      });
      cropCanvas.addEventListener('pointermove', (event) => {
        if (!dragging) return;
        const rect = cropCanvas.getBoundingClientRect();
        const ratio = cropSize / rect.width;
        panX += (event.clientX - lastX) * ratio;
        panY += (event.clientY - lastY) * ratio;
        lastX = event.clientX;
        lastY = event.clientY;
        drawCrop();
      });
      cropCanvas.addEventListener('pointerup', () => { dragging = false; });
      cropCanvas.addEventListener('pointercancel', () => { dragging = false; });
      document.getElementById('avatarCropCancel')?.addEventListener('click', () => cropDialog.close());
      cropSave.addEventListener('click', async () => {
        if (!context || !cropImage.naturalWidth) return;
        cropSave.disabled = true;
        try {
          const blob = await new Promise((resolve, reject) => {
            cropCanvas.toBlob((result) => result ? resolve(result) : reject(new Error('The cropped photo could not be prepared.')), 'image/jpeg', 0.9);
          });
          const formData = new FormData();
          formData.append('avatar', new File([blob], 'profile-photo.jpg', { type: 'image/jpeg' }));
          const result = await request('/api/profile/avatar', { method: 'POST', body: formData });
          const avatar = document.getElementById('profileAvatarPreview');
          avatar.src = `${result.avatar_path}?v=${Date.now()}`;
          avatar.hidden = false;
          const navAvatar = document.querySelector('.account-menu .nav-avatar-image');
          if (navAvatar) {
            navAvatar.src = `${result.avatar_path}?v=${Date.now()}`;
            navAvatar.hidden = false;
            const fallback = navAvatar.parentElement?.querySelector('.avatar-initial');
            if (fallback) fallback.hidden = true;
          } else {
            const navAvatarContainer = document.querySelector('.account-menu .avatar');
            if (navAvatarContainer) {
              const navImage = new Image();
              navImage.className = 'nav-avatar-image';
              navImage.alt = '';
              navImage.src = `${result.avatar_path}?v=${Date.now()}`;
              navImage.addEventListener('load', () => {
                navImage.hidden = false;
                const fallback = navAvatarContainer.querySelector('.avatar-initial');
                if (fallback) fallback.hidden = true;
              });
              navImage.addEventListener('error', () => navImage.remove());
              navImage.hidden = true;
              navAvatarContainer.append(navImage);
            }
          }
          message(form.querySelector('[data-form-message]'), 'Profile photo updated.');
          cropDialog.close();
          await loadProfileDirectory();
        } catch (error) {
          message(form.querySelector('[data-form-message]'), error.message, true);
        } finally {
          cropSave.disabled = false;
        }
      });
    }
  };

  const bindNewsComments = () => {
    const form = document.getElementById('newsCommentForm');
    if (!form) return;
    loadHumanChallenge(form);
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('[type="submit"]');
      const data = Object.fromEntries(new FormData(form).entries());
      button.disabled = true;
      try {
        const result = await request(`/api/news/${encodeURIComponent(form.dataset.newsSlug)}/comments`, {
          method: 'POST',
          body: JSON.stringify(data),
        });
        form.reset();
        message(form.querySelector('[data-form-message]'), result.message);
        await loadHumanChallenge(form);
      } catch (error) {
        message(form.querySelector('[data-form-message]'), error.message, true);
        await loadHumanChallenge(form);
      } finally {
        button.disabled = false;
      }
    });
  };

  const bindNewsletterForm = () => {
    const form = document.getElementById('newsletterForm');
    if (!form) return;
    loadHumanChallenge(form);
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('[type="submit"]');
      const data = Object.fromEntries(new FormData(form).entries());
      data.consent = form.elements.consent.checked;
      button.disabled = true;
      try {
        const result = await request('/api/newsletter/subscribe', {
          method: 'POST',
          body: JSON.stringify(data),
        });
        form.reset();
        message(form.querySelector('[data-form-message]'), result.message);
        await loadHumanChallenge(form);
      } catch (error) {
        message(form.querySelector('[data-form-message]'), error.message, true);
        await loadHumanChallenge(form);
      } finally {
        button.disabled = false;
      }
    });
  };

  const bindWhatsAppWidget = () => {
    const widget = document.querySelector('[data-whatsapp-widget]');
    if (!widget) return;
    const panel = widget.querySelector('#whatsappChatPanel');
    const toggle = widget.querySelector('[data-whatsapp-toggle]');
    const close = widget.querySelector('[data-whatsapp-close]');
    const setOpen = (open) => {
      panel.hidden = !open;
      toggle.setAttribute('aria-expanded', String(open));
      if (open) widget.querySelector('input[name="name"]').focus();
    };
    toggle.addEventListener('click', () => setOpen(panel.hidden));
    close.addEventListener('click', () => setOpen(false));
    widget.querySelector('[data-whatsapp-form]').addEventListener('submit', (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const name = form.elements.name.value.trim();
      const text = form.elements.message.value.trim();
      const messageText = `Hello TrustGrid Africa, my name is ${name}.\n\n${text}`;
      const url = `https://wa.me/265992919716?text=${encodeURIComponent(messageText)}`;
      window.open(url, '_blank', 'noopener,noreferrer');
    });
  };

  const bindRichEditor = () => {
    const editor = document.getElementById('adminNewsEditor');
    if (!editor) return;
    document.querySelectorAll('[data-editor-command]').forEach((button) => {
      button.addEventListener('click', () => {
        editor.focus();
        document.execCommand(button.dataset.editorCommand, false, button.dataset.editorValue || null);
      });
    });
    document.querySelectorAll('[data-editor-block]').forEach((button) => {
      button.addEventListener('click', () => {
        editor.focus();
        document.execCommand('formatBlock', false, button.dataset.editorBlock);
      });
    });
  };

  const bindSuperAdmin = () => {
    if (!document.querySelector('[data-super-admin-page]')) return;
    let workspacePage = 1;
    let commentPage = 1;
    let subscriberPage = 1;
    let newsPage = 1;
    let logPage = 1;
    const adminTabs = [...document.querySelectorAll('[data-admin-tab]')];
    const adminPanels = [...document.querySelectorAll('[data-admin-panel]')];
    const adminTabList = document.querySelector('.admin-nav');
    const updateAdminTabOrientation = () => {
      adminTabList?.setAttribute('aria-orientation', window.matchMedia('(max-width:760px)').matches ? 'horizontal' : 'vertical');
    };
    updateAdminTabOrientation();
    window.matchMedia('(max-width:760px)').addEventListener?.('change', updateAdminTabOrientation);
    const selectAdminTab = (name, updateHash = true) => {
      const selected = adminTabs.find((tab) => tab.dataset.adminTab === name) || adminTabs[0];
      adminTabs.forEach((tab) => {
        const active = tab === selected;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', String(active));
        tab.tabIndex = active ? 0 : -1;
      });
      adminPanels.forEach((panel) => {
        panel.hidden = panel.dataset.adminPanel !== selected.dataset.adminTab;
      });
      if (updateHash && window.location.hash !== `#${selected.dataset.adminTab}`) {
        window.history.replaceState(null, '', `#${selected.dataset.adminTab}`);
      }
    };
    adminTabs.forEach((tab, index) => {
      tab.addEventListener('click', () => selectAdminTab(tab.dataset.adminTab));
      tab.addEventListener('keydown', (event) => {
        const horizontal = window.matchMedia('(max-width:760px)').matches;
        const forwardKey = horizontal ? 'ArrowRight' : 'ArrowDown';
        const backwardKey = horizontal ? 'ArrowLeft' : 'ArrowUp';
        if (![forwardKey, backwardKey, 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const nextIndex = event.key === 'Home'
          ? 0
          : event.key === 'End'
            ? adminTabs.length - 1
            : (index + (event.key === forwardKey ? 1 : -1) + adminTabs.length) % adminTabs.length;
        adminTabs[nextIndex].focus();
        selectAdminTab(adminTabs[nextIndex].dataset.adminTab);
      });
    });
    document.querySelectorAll('[data-admin-go]').forEach((button) => {
      button.addEventListener('click', () => {
        selectAdminTab(button.dataset.adminGo);
        document.querySelector('.admin-sidebar')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      });
    });
    window.addEventListener('hashchange', () => selectAdminTab(window.location.hash.slice(1), false));
    selectAdminTab(window.location.hash.slice(1), false);

    const showError = (element, error) => {
      if (element) element.replaceChildren(make('p', 'empty-state is-error', error.message));
    };

    const loadSummary = async () => {
      try {
        const result = await request('/api/admin/summary');
        const counts = result.counts;
        const container = document.getElementById('adminStats');
        container.replaceChildren(
          ...[
            ['Pending workspaces', counts.pending_workspaces],
            ['Approved workspaces', counts.active_workspaces],
            ['Active users', counts.active_users],
            ['Published articles', counts.published_articles],
            ['Comments to review', counts.pending_comments],
            ['Newsletter subscribers', counts.active_subscribers],
          ].map(([label, value]) => {
            const card = make('article', 'stat-card');
            card.append(make('span', '', label), make('strong', '', String(value)));
            return card;
          })
        );
        const workspaceBadge = document.querySelector('[data-admin-count="workspaces"]');
        const commentBadge = document.querySelector('[data-admin-count="comments"]');
        if (workspaceBadge) workspaceBadge.textContent = counts.pending_workspaces > 0 ? String(counts.pending_workspaces) : '';
        if (commentBadge) commentBadge.textContent = counts.pending_comments > 0 ? String(counts.pending_comments) : '';
      } catch (error) {
        showError(document.getElementById('adminStats'), error);
      }
    };

    const loadWorkspaces = async () => {
      const list = document.getElementById('adminWorkspaces');
      const status = document.getElementById('adminWorkspaceStatus').value;
      try {
        const result = await request(`/api/admin/workspaces?status=${encodeURIComponent(status)}&page=${workspacePage}`);
        list.replaceChildren();
        if (!result.workspaces.length) list.append(make('p', 'empty-state', 'No workspaces in this status.'));
        result.workspaces.forEach((workspace) => {
          const item = make('article', 'admin-record');
          const info = make('div');
          info.append(
            make('h3', '', workspace.name),
            make('p', '', `${workspace.creator_name} · ${workspace.creator_email} · ${workspace.country} · ${workspace.created_at}`)
          );
          item.append(info, make('span', `tag ${workspace.status === 'active' ? 'low' : 'unassessed'}`, workspace.status));
          if (workspace.status === 'pending') {
            const actions = make('div', 'admin-record-actions');
            [['approve', 'Approve'], ['reject', 'Reject']].forEach(([decision, label]) => {
              const button = make('button', decision === 'approve' ? 'button button-primary button-small' : 'button button-secondary button-small', label);
              button.type = 'button';
              button.addEventListener('click', async () => {
                if (decision === 'reject' && !window.confirm(`Reject workspace request "${workspace.name}"?`)) return;
                button.disabled = true;
                try {
                  await request(`/api/admin/workspaces/${workspace.id}/decision`, {
                    method: 'POST',
                    body: JSON.stringify({ decision }),
                  });
                  await Promise.all([loadWorkspaces(), loadSummary(), loadLogs()]);
                } catch (error) {
                  window.alert(error.message);
                  button.disabled = false;
                }
              });
              actions.append(button);
            });
            item.append(actions);
          }
          list.append(item);
        });
        renderPagination('adminWorkspacePagination', result.pagination, (page) => {
          workspacePage = page;
          loadWorkspaces();
        });
      } catch (error) {
        showError(list, error);
      }
    };

    const loadComments = async () => {
      const list = document.getElementById('adminComments');
      const status = document.getElementById('adminCommentStatus').value;
      try {
        const result = await request(`/api/admin/comments?status=${encodeURIComponent(status)}&page=${commentPage}`);
        list.replaceChildren();
        if (!result.comments.length) list.append(make('p', 'empty-state', 'No comments need review in this status.'));
        result.comments.forEach((comment) => {
          const item = make('article', 'admin-record');
          const info = make('div');
          info.append(
            make('h3', '', comment.article_title),
            make('p', '', `${comment.display_name} · ${comment.email} · ${comment.created_at}`),
            make('p', 'admin-comment-text', comment.body_text)
          );
          item.append(info, make('span', `tag ${comment.status === 'Approved' ? 'low' : 'unassessed'}`, comment.status));
          if (comment.status === 'Pending') {
            const actions = make('div', 'admin-record-actions');
            [['approve', 'Approve'], ['reject', 'Reject']].forEach(([decision, label]) => {
              const button = make('button', 'button button-secondary button-small', label);
              button.type = 'button';
              button.addEventListener('click', async () => {
                button.disabled = true;
                try {
                  await request(`/api/admin/comments/${comment.id}/decision`, {
                    method: 'POST',
                    body: JSON.stringify({ decision }),
                  });
                  await Promise.all([loadComments(), loadSummary(), loadLogs()]);
                } catch (error) {
                  window.alert(error.message);
                  button.disabled = false;
                }
              });
              actions.append(button);
            });
            item.append(actions);
          }
          list.append(item);
        });
        renderPagination('adminCommentPagination', result.pagination, (page) => {
          commentPage = page;
          loadComments();
        });
      } catch (error) {
        showError(list, error);
      }
    };

    const loadSubscribers = async () => {
      const list = document.getElementById('adminSubscribers');
      const status = document.getElementById('adminSubscriberStatus').value;
      try {
        const result = await request(`/api/admin/subscribers?status=${encodeURIComponent(status)}&page=${subscriberPage}`);
        list.replaceChildren();
        if (!result.subscribers.length) list.append(make('p', 'empty-state', 'No subscribers in this status.'));
        result.subscribers.forEach((subscriber) => {
          const item = make('article', 'admin-record');
          const info = make('div');
          info.append(
            make('h3', '', subscriber.email),
            make('p', '', `${subscriber.name || 'No name provided'} · Opted in ${subscriber.consent_at}`)
          );
          item.append(info, make('span', `tag ${subscriber.status === 'Active' ? 'low' : 'unassessed'}`, subscriber.status));
          if (subscriber.status === 'Active') {
            const actions = make('div', 'admin-record-actions');
            const unsubscribe = make('button', 'button button-secondary button-small', 'Unsubscribe');
            unsubscribe.type = 'button';
            unsubscribe.addEventListener('click', async () => {
              if (!window.confirm(`Unsubscribe ${subscriber.email} from future updates?`)) return;
              unsubscribe.disabled = true;
              try {
                await request(`/api/admin/subscribers/${subscriber.id}/unsubscribe`, { method: 'POST', body: '{}' });
                await Promise.all([loadSubscribers(), loadSummary(), loadLogs()]);
              } catch (error) {
                window.alert(error.message);
                unsubscribe.disabled = false;
              }
            });
            actions.append(unsubscribe);
            item.append(actions);
          }
          list.append(item);
        });
        renderPagination('adminSubscriberPagination', result.pagination, (page) => {
          subscriberPage = page;
          loadSubscribers();
        });
      } catch (error) {
        showError(list, error);
      }
    };

    const form = document.getElementById('adminNewsForm');
    const editor = document.getElementById('adminNewsEditor');
    const imagePreview = document.getElementById('adminNewsImagePreview');
    const resetNewsForm = () => {
      form.reset();
      editor.replaceChildren();
      form.elements.id.value = '';
      form.elements.featured_image_path.value = '';
      imagePreview.removeAttribute('src');
      imagePreview.hidden = true;
      message(form.querySelector('[data-form-message]'), '');
    };

    const loadNews = async () => {
      const list = document.getElementById('adminNewsList');
      try {
        const result = await request(`/api/admin/news?page=${newsPage}`);
        list.replaceChildren();
        if (!result.articles.length) list.append(make('p', 'empty-state', 'No articles yet. Create one above.'));
        result.articles.forEach((article) => {
          const item = make('article', 'admin-record');
          const info = make('div');
          info.append(
            make('h3', '', article.title),
            make('p', '', `${article.status} · ${article.author_name} · ${article.updated_at}`)
          );
          if (article.status === 'Published') {
            const link = make('a', 'text-link', `/${'news'}/${article.slug}`);
            link.href = apiUrl(`/news/${encodeURIComponent(article.slug)}`);
            info.append(link);
          }
          const actions = make('div', 'admin-record-actions');
          const edit = make('button', 'button button-secondary button-small', 'Edit');
          edit.type = 'button';
          edit.addEventListener('click', async () => {
            try {
              const detail = await request(`/api/admin/news/${article.id}`);
              const record = detail.article;
              form.elements.id.value = record.id;
              form.elements.title.value = record.title;
              form.elements.summary.value = record.summary;
              form.elements.status.value = record.status === 'Archived' ? 'Draft' : record.status;
              form.elements.featured_image_path.value = record.featured_image_path;
              editor.innerHTML = record.body_html;
              imagePreview.src = record.featured_image_path ? apiUrl(`/${record.featured_image_path}`) : '';
              imagePreview.hidden = !record.featured_image_path;
              form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch (error) {
              window.alert(error.message);
            }
          });
          actions.append(edit);
          const remove = make('button', 'button button-secondary button-small', 'Delete');
          remove.type = 'button';
          remove.addEventListener('click', async () => {
            if (!window.confirm(`Permanently delete "${article.title}" and its comments?`)) return;
            remove.disabled = true;
            try {
              await request(`/api/admin/news/${article.id}`, { method: 'DELETE' });
              await Promise.all([loadNews(), loadSummary(), loadLogs()]);
            } catch (error) {
              window.alert(error.message);
              remove.disabled = false;
            }
          });
          actions.append(remove);
          item.append(info, make('span', `tag ${article.status === 'Published' ? 'low' : 'unassessed'}`, article.status), actions);
          list.append(item);
        });
        renderPagination('adminNewsPagination', result.pagination, (page) => {
          newsPage = page;
          loadNews();
        });
      } catch (error) {
        showError(list, error);
      }
    };

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('[type="submit"]');
      const data = Object.fromEntries(new FormData(form).entries());
      data.body_html = editor.innerHTML;
      if (!data.body_html.trim()) {
        message(form.querySelector('[data-form-message]'), 'Write the article before saving.', true);
        return;
      }
      button.disabled = true;
      message(form.querySelector('[data-form-message]'), 'Saving article…');
      try {
        const id = form.elements.id.value;
        if (id) {
          await request(`/api/admin/news/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
        } else {
          await request('/api/admin/news', { method: 'POST', body: JSON.stringify(data) });
        }
        resetNewsForm();
        message(form.querySelector('[data-form-message]'), 'Article saved.');
        await Promise.all([loadNews(), loadSummary(), loadLogs()]);
      } catch (error) {
        message(form.querySelector('[data-form-message]'), error.message, true);
      } finally {
        button.disabled = false;
      }
    });

    document.getElementById('adminNewsReset').addEventListener('click', resetNewsForm);
    form.elements.image.addEventListener('change', async (event) => {
      const file = event.currentTarget.files?.[0];
      if (!file) return;
      const upload = new FormData();
      upload.append('image', file);
      message(form.querySelector('[data-form-message]'), 'Uploading featured image…');
      try {
        const result = await request('/api/admin/news-image', { method: 'POST', body: upload });
        form.elements.featured_image_path.value = result.featured_image_path;
        imagePreview.src = apiUrl(`/${result.featured_image_path}`);
        imagePreview.hidden = false;
        message(form.querySelector('[data-form-message]'), 'Featured image uploaded.');
      } catch (error) {
        message(form.querySelector('[data-form-message]'), error.message, true);
        event.currentTarget.value = '';
      }
    });

    const loadLogs = async () => {
      const list = document.getElementById('adminLogs');
      try {
        const result = await request(`/api/admin/logs?page=${logPage}`);
        list.replaceChildren();
        if (!result.logs.length) list.append(make('p', 'empty-state', 'No user activity recorded yet.'));
        result.logs.forEach((log) => {
          const row = make('article', 'admin-log-row');
          row.append(
            make('time', '', log.created_at),
            make('strong', '', log.action),
            make('span', '', log.actor_name || 'Public visitor'),
            make('span', '', log.organization_name || 'Platform'),
            make('span', '', `${log.target_type}${log.target_id ? ` #${log.target_id}` : ''}`)
          );
          list.append(row);
        });
        renderPagination('adminLogPagination', result.pagination, (page) => {
          logPage = page;
          loadLogs();
        });
      } catch (error) {
        showError(list, error);
      }
    };

    document.getElementById('adminWorkspaceStatus').addEventListener('change', () => {
      workspacePage = 1;
      loadWorkspaces();
    });
    document.getElementById('adminCommentStatus').addEventListener('change', () => {
      commentPage = 1;
      loadComments();
    });
    document.getElementById('adminSubscriberStatus').addEventListener('change', () => {
      subscriberPage = 1;
      loadSubscribers();
    });
    bindRichEditor();
    Promise.all([loadSummary(), loadWorkspaces(), loadComments(), loadSubscribers(), loadNews(), loadLogs()]);
  };

  const loadGuidedAssessments = async () => {
    const list = document.getElementById('guidedAssessmentList');
    if (!list) return [];
    const result = await request(`/api/assessments/guided?page=${guidedAssessmentPage}`);
    renderGuidedAssessmentList(result.assessments);
    renderPagination('guidedAssessmentPagination', result.pagination, (page) => {
      guidedAssessmentPage = page;
      loadGuidedAssessments().catch((error) => message(document.getElementById('guidedAssessmentMessage'), error.message, true));
    });
    return result.assessments;
  };

  let allFindings = [];
  let selectedFindingsAssetId = '';
  let selectedFindingsStatus = '';
  let findingsPage = 1;
  let guidedAssessmentPage = 1;
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
        const deleteButton = make('button', 'finding-delete-button', 'Delete finding');
        deleteButton.type = 'button';
        deleteButton.setAttribute('aria-label', `Delete finding: ${finding.title}`);
        deleteButton.addEventListener('click', async () => {
          if (!window.confirm(`Permanently delete "${finding.title}" from this organisation's findings? This cannot be undone.`)) return;
          deleteButton.disabled = true;
          try {
            await request(`/api/findings/${finding.id}`, { method: 'DELETE' });
            await Promise.all([loadFindings(), loadDashboardStats()]);
          } catch (error) {
            window.alert(`The finding could not be deleted: ${error.message}`);
            deleteButton.disabled = false;
          }
        });
        controls.append(deleteButton);
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
    const nextAssetId = String(assetId || '');
    if (nextAssetId !== selectedFindingsAssetId) findingsPage = 1;
    selectedFindingsAssetId = nextAssetId;
    const params = new URLSearchParams({ page: String(findingsPage) });
    if (selectedFindingsAssetId) params.set('asset_id', selectedFindingsAssetId);
    if (selectedFindingsStatus) params.set('status', selectedFindingsStatus);
    const query = `?${params}`;
    const result = await request(`/api/findings${query}`);
    allFindings = result.findings;
    findingsPage = result.pagination.page;
    renderPagination('findingsPagination', result.pagination, (page) => {
      findingsPage = page;
      loadFindings(selectedFindingsAssetId).catch((error) => message(document.getElementById('findingsSummary'), error.message, true));
    });
    renderFindings(document.querySelector('[data-finding-filter].active')?.dataset.findingFilter || 'all');
    const summary = document.getElementById('findingsSummary');
    if (summary) {
      const open = allFindings.filter((finding) => finding.status === 'Open').length;
      const progress = allFindings.filter((finding) => finding.status === 'In progress').length;
      const resolved = allFindings.filter((finding) => finding.status === 'Resolved').length;
      const selectedAsset = allAssets.find((asset) => String(asset.id) === selectedFindingsAssetId);
      summary.replaceChildren(
        ...(selectedAsset ? [make('span', '', `Findings for ${selectedAsset.name}`)] : []),
        make('span', '', `${result.summary.total} total`),
        make('span', '', `${result.summary.open} open`),
        make('span', '', `${result.summary.in_progress} in progress`),
        make('span', '', `${result.summary.resolved} resolved`)
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
      selectedFindingsStatus = button.dataset.findingFilter === 'all' ? '' : button.dataset.findingFilter;
      findingsPage = 1;
      loadFindings().catch((error) => {
        const list = document.getElementById('findingsList');
        if (list) list.replaceChildren(make('p', 'empty-state is-error', error.message));
      });
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
  bindNewsComments();
  bindNewsletterForm();
  bindWhatsAppWidget();
  loadProfile();
  bindSuperAdmin();
  loadWorkspace();
})();
