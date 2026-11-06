const yearNode = document.getElementById('year');
if (yearNode) {
  yearNode.textContent = new Date().getFullYear();
}

const assetData = [
  { name: 'Public website', type: 'Website', owner: 'Operations', risk: 'High', status: 'Monitoring' },
  { name: 'Member portal', type: 'Web application', owner: 'Product', risk: 'Medium', status: 'Active' },
  { name: 'Partner API', type: 'API', owner: 'Engineering', risk: 'High', status: 'Needs review' },
  { name: 'Finance database', type: 'Database', owner: 'Finance', risk: 'Critical', status: 'Needs review' },
  { name: 'Mail gateway', type: 'Server', owner: 'IT', risk: 'Low', status: 'Active' },
  { name: 'Learning portal', type: 'Website', owner: 'Education', risk: 'Medium', status: 'Monitoring' },
  { name: 'Clinic records API', type: 'API', owner: 'Health', risk: 'High', status: 'Active' }
];

const findings = [
  { title: 'Missing HSTS on public site', severity: 'High', detail: 'HTTPS is enforced, but HSTS is absent on the primary production endpoint.' },
  { title: 'Weak admin credential policy', severity: 'Critical', detail: 'Password aging and MFA enforcement are inconsistent across admin accounts.' },
  { title: 'Sensitive files exposed', severity: 'Medium', detail: 'Public directory indexing is leaking backup and configuration fragments.' },
  { title: 'Expired staging certificate', severity: 'Medium', detail: 'The staging environment certificate expired and needs renewal.' }
];

const renderFindings = () => {
  const list = document.getElementById('findingList');
  if (!list) return;

  list.innerHTML = findings
    .map(
      (finding) => `
        <div class="item">
          <strong>${finding.title}</strong>
          <span class="tag ${finding.severity.toLowerCase()}">${finding.severity}</span>
          <p>${finding.detail}</p>
        </div>
      `
    )
    .join('');
};

const renderAssetTable = (filter = 'all') => {
  const body = document.getElementById('assetTableBody');
  if (body) {
    const items = filter === 'all' ? assetData : assetData.filter((asset) => asset.type === filter || asset.name === filter);
    body.innerHTML = items
      .map(
        (asset) => `
          <tr>
            <td>${asset.name}</td>
            <td>${asset.type}</td>
            <td>${asset.owner}</td>
            <td><span class="tag ${asset.risk.toLowerCase()}">${asset.risk}</span></td>
            <td>${asset.status}</td>
          </tr>
        `
      )
      .join('');
  }

  const assetList = document.getElementById('assetList');
  if (assetList) {
    const items = filter === 'all' ? assetData : assetData.filter((asset) => asset.type === filter);
    assetList.innerHTML = items
      .map(
        (asset) => `
          <tr>
            <td>${asset.name}</td>
            <td>${asset.type}</td>
            <td>${asset.owner}</td>
            <td><span class="tag ${asset.risk.toLowerCase()}">${asset.risk}</span></td>
            <td>${asset.status}</td>
          </tr>
        `
      )
      .join('');
  }
};

const renderRiskChart = () => {
  const chart = document.getElementById('riskChart');
  if (!chart) return;

  const points = [
    { label: 'Jan', value: 65 },
    { label: 'Feb', value: 58 },
    { label: 'Mar', value: 72 },
    { label: 'Apr', value: 63 },
    { label: 'May', value: 55 },
    { label: 'Jun', value: 49 }
  ];

  const maxValue = 100;
  chart.innerHTML = points
    .map(
      ({ label, value }) => `
        <div class="risk-bar-wrap">
          <div class="risk-bar" style="height:${(value / maxValue) * 100}%"></div>
          <span>${label}</span>
        </div>
      `
    )
    .join('');
};

const bindAssetFilters = () => {
  document.querySelectorAll('[data-filter]').forEach((button) => {
    button.addEventListener('click', () => {
      document.querySelectorAll('[data-filter]').forEach((node) => node.classList.remove('active'));
      button.classList.add('active');
      renderAssetTable(button.dataset.filter);
    });
  });
};

const bindAssetForm = () => {
  const form = document.getElementById('assetForm');
  if (!form) return;

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const formData = new FormData(form);
    const newAsset = {
      name: formData.get('name'),
      type: formData.get('type'),
      owner: formData.get('owner'),
      risk: 'Low',
      status: formData.get('status')
    };

    assetData.unshift(newAsset);
    renderAssetTable('all');
    form.reset();
  });
};

renderFindings();
renderRiskChart();
renderAssetTable('all');
bindAssetFilters();
bindAssetForm();
