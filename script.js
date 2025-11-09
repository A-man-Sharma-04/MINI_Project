// Mock data (will be replaced by API later)
let items = [
  { id: 1, type: 'event', title: 'Neighborhood Cleanup', desc: 'Join us this Saturday!', datetime: '2025-11-16T09:00', location: [28.6139, 77.2090], status: 'active' },
  { id: 2, type: 'issue', title: 'Flooded Street', desc: 'After heavy rain near Market Rd', location: [28.6200, 77.2100], status: 'active' },
  { id: 3, type: 'notice', title: 'Water Supply Interruption', desc: 'From 10 AM to 2 PM on Nov 12', status: 'completed' },
  { id: 4, type: 'report', title: 'Vandalism Report', desc: 'Public bench damaged', location: [28.6050, 77.2300], status: 'active' }
];

let map;
let markers = [];

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  initMap();
  renderItemList('events');
});

function initMap() {
  map = L.map('map').setView([28.6139, 77.2090], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);
  refreshMap();
}

function getColor(type) {
  const colors = {
    event: '#3b82f6',
    issue: '#ef4444',
    notice: '#eab308',
    report: '#8b5cf6'
  };
  return colors[type] || '#95a5a6';
}

function refreshMap() {
  markers.forEach(marker => map.removeLayer(marker));
  markers = [];

  const statusFilter = document.getElementById('status-filter').value;
  const typeFilter = document.getElementById('type-filter').value;
  const searchTerm = document.getElementById('search').value.toLowerCase();

  items.forEach(item => {
    if (statusFilter !== 'all' && item.status !== statusFilter) return;
    if (typeFilter !== 'all' && item.type !== typeFilter) return;
    if (searchTerm && !item.title.toLowerCase().includes(searchTerm) && 
        !item.desc.toLowerCase().includes(searchTerm)) return;

    if (item.location) {
      const marker = L.circleMarker(item.location, {
        radius: 10,
        color: getColor(item.type),
        fillColor: getColor(item.type),
        fillOpacity: 0.8
      }).bindPopup(`<b>${item.title}</b><br>${item.desc}<br><i>Status: ${item.status}</i>`);
      marker.addTo(map);
      markers.push(marker);
    }
  });
}

function filterMapItems() {
  refreshMap();
}

function showTab(tab) {
  document.querySelectorAll('.tab-buttons button').forEach(btn => btn.classList.remove('active'));
  event.target.classList.add('active');
  
  const typeMap = { events: 'event', issues: 'issue', notices: 'notice', reports: 'report' };
  renderItemList(tab, typeMap[tab]);
}

function renderItemList(tab, type = null) {
  const list = document.getElementById('item-list');
  let filtered = type ? items.filter(i => i.type === type) : items;

  const status = document.getElementById('status-filter').value;
  const search = document.getElementById('search').value.toLowerCase();
  if (status !== 'all') filtered = filtered.filter(i => i.status === status);
  if (search) filtered = filtered.filter(i => 
    i.title.toLowerCase().includes(search) || i.desc.toLowerCase().includes(search)
  );

  list.innerHTML = filtered.map(item => `
    <div class="item-card">
      <h4>${item.title}</h4>
      <p>${item.desc}</p>
      <p><small>Status: ${item.status}</small></p>
    </div>
  `).join('');
}

// Modal
function showForm() {
  document.getElementById('form-modal').style.display = 'block';
  toggleFormFields();
}

function closeForm() {
  document.getElementById('form-modal').style.display = 'none';
}

function toggleFormFields() {
  const type = document.getElementById('form-type').value;
  let html = '';
  if (type === 'event') {
    html = `
      <input type="datetime-local" id="form-datetime" />
      <input type="text" id="form-location" placeholder="Location (lat,lng) e.g. 28.6139,77.2090" />
      <input type="url" id="form-link" placeholder="Registration Link (optional)" />
      <textarea id="form-coc" placeholder="Code of Conduct (optional)"></textarea>
    `;
  } else if (type === 'issue') {
    html = `
      <input type="text" id="form-location" placeholder="Location (lat,lng)" />
      <select id="form-severity">
        <option value="low">Low</option>
        <option value="medium" selected>Medium</option>
        <option value="high">High</option>
      </select>
    `;
  } else if (type === 'notice') {
    html = `<input type="date" id="form-valid-until" placeholder="Valid Until" />`;
  } else if (type === 'report') {
    html = `<label style="display:block;margin:10px 0;"><input type="checkbox" id="form-confidential" /> Confidential Report</label>`;
  }
  document.getElementById('dynamic-fields').innerHTML = html;
}

function saveNewItem() {
  const type = document.getElementById('form-type').value;
  const title = document.getElementById('form-title').value;
  const desc = document.getElementById('form-desc').value;

  if (!title || !desc) {
    alert('Title and description are required!');
    return;
  }

  const newItem = { id: Date.now(), type, title, desc, status: 'active' };

  if (type === 'event') {
    newItem.datetime = document.getElementById('form-datetime').value;
    const loc = document.getElementById('form-location').value.split(',').map(x => parseFloat(x.trim()));
    if (loc.length === 2 && !isNaN(loc[0])) newItem.location = loc;
    newItem.registrationLink = document.getElementById('form-link').value;
    newItem.codeOfConduct = document.getElementById('form-coc').value;
  } else if (type === 'issue') {
    const loc = document.getElementById('form-location').value.split(',').map(x => parseFloat(x.trim()));
    if (loc.length === 2 && !isNaN(loc[0])) newItem.location = loc;
    newItem.severity = document.getElementById('form-severity').value;
  } else if (type === 'notice') {
    newItem.validUntil = document.getElementById('form-valid-until').value;
  } else if (type === 'report') {
    newItem.confidential = document.getElementById('form-confidential').checked;
  }

  items.push(newItem);
  closeForm();
  renderItemList('events');
  refreshMap();
  alert('Item added successfully!');
}

function logout() {
  if (confirm('Are you sure you want to log out?')) {
    window.location.href = 'index.html';
  }
}