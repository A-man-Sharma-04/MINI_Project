// dashboard.js
let map;
let markers = [];
let currentUser = null;
let currentTab = 'home'; // Default tab
let currentFeedView = 'for-you'; // Default feed view
let feedPage = 1;
let feedHasMore = true;
let trendingPage = 1;
let trendingHasMore = true;

// Initialize dashboard when page loads
document.addEventListener('DOMContentLoaded', function() {
    checkAuth();
    initMap();
    loadUserData();
    setupEventListeners();
    showTab('home'); // Show home tab by default
});

// Check if user is authenticated
async function checkAuth() {
    try {
        const response = await fetch('api/check-session.php');
        const data = await response.json();

        if (!data.authenticated) {
            window.location.href = 'login.html';
            return;
        }

        currentUser = data.user;
        updateUI();

    } catch (error) {
        console.error('Auth check failed:', error);
        window.location.href = 'login.html';
    }
}

// Update UI elements with user data
function updateUI() {
    if (!currentUser) return;

    // Update header elements
    document.getElementById('user-initial').textContent = currentUser.name.charAt(0).toUpperCase();
    document.getElementById('user-initial-large').textContent = currentUser.name.charAt(0).toUpperCase();
    document.getElementById('user-name-display').textContent = currentUser.name;
    document.getElementById('home-user-name').textContent = currentUser.name;
}

// Initialize map
function initMap() {
    map = L.map('map').setView([28.6139, 77.2090], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Add click event to map for location selection
    map.on('click', onMapClick);
}

// Click handler for map (for location selection in forms)
function onMapClick(e) {
    if (document.getElementById('form-modal').style.display === 'flex') {
        document.getElementById('form-lat').value = e.latlng.lat.toFixed(6);
        document.getElementById('form-lng').value = e.latlng.lng.toFixed(6);
    }
}

// Setup event listeners
function setupEventListeners() {
    // Main navigation
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = this.getAttribute('data-tab');
            showTab(tab);
        });
    });

    // Feed view toggle
    document.querySelectorAll('.segment-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const view = this.getAttribute('data-feed-view');
            switchFeedView(view);
        });
    });

    // Profile dropdown
    document.getElementById('profile-trigger').addEventListener('click', toggleProfileDropdown);

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.profile-dropdown')) {
            document.getElementById('profile-menu').style.display = 'none';
        }
    });

    // Image preview for file uploads
    document.getElementById('form-images').addEventListener('change', previewImages);
}

// Show different main tabs
function showTab(tab) {
    // Update active nav button
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');

    // Show/hide content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(`${tab}-tab`).classList.add('active');

    // Load tab-specific content
    switch(tab) {
        case 'home':
            loadHomeContent();
            break;
        case 'map':
            loadMapContent();
            break;
        case 'feed':
            loadFeedContent();
            break;
        case 'trending':
            loadTrendingContent();
            break;
        // Removed profile case
    }

    currentTab = tab;
}

// Switch between feed views (For You / Following)
function switchFeedView(view) {
    currentFeedView = view;

    // Update active button
    document.querySelectorAll('.segment-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-feed-view="${view}"]`).classList.add('active');

    // Load feed content
    loadFeedContent();
}

// Toggle profile dropdown menu
function toggleProfileDropdown() {
    const menu = document.getElementById('profile-menu');
    const isVisible = menu.style.display === 'block';
    menu.style.display = isVisible ? 'none' : 'block';
}

// Load home content
function loadHomeContent() {
    // Load recent activity
    loadRecentActivity();
}

// Load map content
function loadMapContent() {
    // Load map items with current filters
    loadMapItems();
}

// Load feed content
function loadFeedContent() {
    feedPage = 1;
    feedHasMore = true;
    loadFeedItems(currentFeedView, true);
    bindFeedActions();
}

// Load trending content
function loadTrendingContent() {
    trendingPage = 1;
    trendingHasMore = true;
    loadTrendingItems(true);
}

// Load recent activity for home tab
async function loadRecentActivity() {
    try {
        const response = await fetch(`api/recent-activity.php?user_id=${currentUser.id}`);
        const data = await response.json();

        if (data.success) {
            const list = document.getElementById('recent-activity-list');
            list.innerHTML = '';

            data.activity.forEach(item => {
                const activityElement = document.createElement('div');
                activityElement.className = 'activity-item';
                const verb = item.type;
                activityElement.innerHTML = `
                    <p>${verb} ${item.target_type} "${item.target_title}"</p>
                    <small>${formatDate(item.created_at)}</small>
                `;
                list.appendChild(activityElement);
            });
        }
    } catch (error) {
        console.error('Failed to load recent activity:', error);
    }
}

// Load map items with filters
async function loadMapItems() {
    try {
        const params = new URLSearchParams();
        params.append('type', document.getElementById('map-type-filter').value);
        params.append('status', document.getElementById('map-status-filter').value);
        params.append('severity', document.getElementById('map-severity-filter').value);

        const response = await fetch(`api/get-items.php?${params}`);
        const data = await response.json();

        if (data.success) {
            renderMapItems(data.items);
        }
    } catch (error) {
        console.error('Failed to load map items:', error);
    }
}

// Render items on map
function renderMapItems(items) {
    // Clear existing markers
    markers.forEach(marker => map.removeLayer(marker));
    markers = [];

    items.forEach(item => {
        if (item.location_lat && item.location_lng) {
            const marker = L.marker([item.location_lat, item.location_lng]).addTo(map);
            marker.bindPopup(`
                <b>${item.title}</b><br>
                ${item.description}<br>
                <small>${item.type} - ${item.status}</small>
            `);
            markers.push(marker);
        }
    });
}

// dashboard.js

// ... other functions ...

// Function to load user-specific data (e.g., stats, settings) for the dashboard
async function loadUserData() {
    // Example: Load user stats for the home page
    if (!currentUser) {
        console.warn("Cannot load user data: user not authenticated yet.");
        return; // Exit if currentUser is not set
    }

    try {
        // Example API call - adjust endpoint as needed
        // const response = await fetch(`api/user-stats.php?user_id=${currentUser.id}`);
        // const data = await response.json();
        // if (data.success) {
        //     // Update UI elements with user stats
        //     document.getElementById('user-stats-element').textContent = data.stats.someValue;
        // }
        console.log("Loading user data for dashboard..."); // Placeholder
    } catch (error) {
        console.error('Failed to load user data:', error);
        // Optionally handle errors, e.g., show a message to the user
    }
}

// ... rest of your dashboard.js functions ...
// Load feed items
async function loadFeedItems(view, reset = false) {
    try {
        if (reset) {
            feedPage = 1;
        }
        const params = new URLSearchParams();
        params.append('view', view);
        params.append('page', feedPage);
        params.append('limit', 10);

        const response = await fetch(`api/get-feed.php?${params}`);
        const data = await response.json();

        if (data.success) {
            if (reset) {
                renderFeedItems(data.items, false);
            } else {
                renderFeedItems(data.items, true);
            }
            feedHasMore = !!data.has_more;
            document.getElementById('feed-load-more').style.display = feedHasMore ? 'block' : 'none';
            feedPage += 1;
        }
    } catch (error) {
        console.error('Failed to load feed:', error);
    }
}

function loadMoreFeed() {
    if (!feedHasMore) return;
    loadFeedItems(currentFeedView, false);
}

// Render feed items
function renderFeedItems(items, append = false) {
    const list = document.getElementById('feed-list');
    if (!append) {
        list.innerHTML = '';
    }

    items.forEach(item => {
        const feedElement = document.createElement('div');
        feedElement.className = 'feed-item';
        const isSelf = currentUser && currentUser.id === item.user_id;
        const isFollowing = Boolean(Number(item.is_following));
        const mediaSrc = getFirstMedia(item.media_urls);
        feedElement.innerHTML = `
            <div class="feed-header">
                <div class="feed-header-left">
                    <div class="user-avatar">${item.user_name.charAt(0)}</div>
                    <div>
                        <div class="user-name">${item.user_name}</div>
                        <div class="item-time">${formatDate(item.created_at)}</div>
                    </div>
                </div>
                ${isSelf ? '' : `<button class="btn-follow ${isFollowing ? 'following' : ''}" data-user-id="${item.user_id}" aria-pressed="${isFollowing}" aria-label="${isFollowing ? 'Unfollow' : 'Follow'} ${item.user_name}">${isFollowing ? 'Following' : 'Follow'}</button>`}
            </div>
            <div class="feed-content">
                <h4>${item.title}</h4>
                <p>${item.description}</p>
                <div class="item-meta">
                    <span class="item-type" style="background: ${getTypeColor(item.type)}">${item.type}</span>
                    <span class="item-location">📍 ${item.city || 'Unknown'}</span>
                </div>
                ${mediaSrc ? `<img class="feed-media" src="${mediaSrc}" alt="media" />` : ''}
            </div>
            <div class="feed-actions">
                <button class="btn-like" data-id="${item.id}" data-count="${item.like_count ?? 0}">👍 ${item.like_count ?? 0}</button>
                <button class="btn-comment" data-id="${item.id}" data-count="${item.comment_count ?? 0}">💬 ${item.comment_count ?? 0}</button>
                <button class="btn-share" data-id="${item.id}" data-count="${item.share_count ?? 0}">📤 ${item.share_count ?? 0}</button>
            </div>
            <form class="comment-form" data-id="${item.id}">
                <input class="comment-input" type="text" placeholder="Add a comment" aria-label="Add a comment" />
                <button type="submit" class="btn-submit-comment">Post</button>
            </form>
        `;
        list.appendChild(feedElement);
    });
}

function bindFeedActions() {
    const list = document.getElementById('feed-list');
    if (!list || list.dataset.bound === 'true') return;

    list.dataset.bound = 'true';
    list.addEventListener('click', async (e) => {
        const likeBtn = e.target.closest('.btn-like');
        const commentBtn = e.target.closest('.btn-comment');
        const followBtn = e.target.closest('.btn-follow');
        const shareBtn = e.target.closest('.btn-share');
        if (likeBtn) {
            const itemId = likeBtn.dataset.id;
            setLoading(likeBtn, true, '👍 ...');
            const newCount = await reactToItem(itemId);
            if (typeof newCount === 'number') {
                likeBtn.dataset.count = newCount;
                likeBtn.textContent = `👍 ${newCount}`;
            }
            setLoading(likeBtn, false, `👍 ${likeBtn.dataset.count || ''}`);
        } else if (commentBtn) {
            const feedItem = commentBtn.closest('.feed-item');
            const form = feedItem?.querySelector('.comment-form');
            if (form) {
                form.classList.toggle('open');
                const input = form.querySelector('.comment-input');
                if (form.classList.contains('open')) {
                    input?.focus();
                }
            }
        } else if (followBtn) {
            const userId = followBtn.dataset.userId;
            const currentlyFollowing = followBtn.classList.contains('following');
            setLoading(followBtn, true, currentlyFollowing ? 'Unfollowing...' : 'Following...');
            await toggleFollow(userId);
            followBtn.classList.toggle('following');
            const nowFollowing = !currentlyFollowing;
            followBtn.setAttribute('aria-pressed', String(nowFollowing));
            followBtn.textContent = nowFollowing ? 'Following' : 'Follow';
            setLoading(followBtn, false, currentlyFollowing ? 'Following' : 'Follow');
        } else if (shareBtn) {
            const itemId = shareBtn.dataset.id;
            setLoading(shareBtn, true, 'Sharing...');
            const reshare = await shareItem(itemId);
            if (reshare && typeof reshare.share_count === 'number') {
                shareBtn.dataset.count = reshare.share_count;
                shareBtn.textContent = `📤 ${reshare.share_count}`;
            }
            setLoading(shareBtn, false, `📤 ${shareBtn.dataset.count || ''}`);
        }
    });

    list.addEventListener('submit', async (e) => {
        const form = e.target.closest('.comment-form');
        if (!form) return;
        e.preventDefault();
        const itemId = form.dataset.id;
        const input = form.querySelector('.comment-input');
        const submitBtn = form.querySelector('.btn-submit-comment');
        const text = input.value.trim();
        if (!text) return;

        setLoading(submitBtn, true, 'Posting...');
        const newCount = await commentOnItem(itemId, text);
        input.value = '';
        form.classList.remove('open');
        const commentBtn = form.parentElement?.querySelector('.btn-comment');
        if (commentBtn && typeof newCount === 'number') {
            commentBtn.dataset.count = newCount;
            commentBtn.textContent = `💬 ${newCount}`;
        }
        setLoading(submitBtn, false, 'Post');
    });
}

async function reactToItem(itemId) {
    try {
        const res = await fetch('api/react-item.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: Number(itemId), reaction_type: 'like' })
        });
        const data = await res.json();
        if (data.success) return data.like_count;
    } catch (error) {
        console.error('Failed to react:', error);
    }
    showToast('Failed to like');
    return null;
}

async function commentOnItem(itemId, body) {
    try {
        const res = await fetch('api/comment-item.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: Number(itemId), body })
        });
        const data = await res.json();
        if (data.success) return data.comment_count;
    } catch (error) {
        console.error('Failed to comment:', error);
    }
    showToast('Failed to comment');
    return null;
}

async function shareItem(itemId) {
    try {
        const res = await fetch('api/share-item.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: Number(itemId) })
        });
        const data = await res.json();
        if (data.success) return data;
    } catch (error) {
        console.error('Failed to share:', error);
    }
    showToast('Failed to share');
    return null;
}

async function toggleFollow(targetUserId) {
    try {
        await fetch('api/follow-toggle.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ target_user_id: Number(targetUserId) })
        });
    } catch (error) {
        console.error('Failed to toggle follow:', error);
    }
}

function setLoading(button, isLoading, labelWhenReady) {
    if (!button) return;
    if (isLoading) {
        button.dataset.originalText = button.textContent;
        button.textContent = labelWhenReady || '...';
        button.disabled = true;
    } else {
        button.textContent = labelWhenReady || button.dataset.originalText || 'Submit';
        button.disabled = false;
    }
}

// Load trending items
async function loadTrendingItems(reset = true) {
    try {
        if (reset) {
            trendingPage = 1;
        }
        const params = new URLSearchParams();
        params.append('location', document.getElementById('trending-location-filter').value);
        params.append('sort', document.getElementById('trending-sort-filter').value);
        params.append('page', trendingPage);
        params.append('limit', 10);

        const response = await fetch(`api/get-trending.php?${params}`);
        const data = await response.json();

        if (data.success) {
            renderTrendingItems(data.items, !reset ? true : false);
            trendingHasMore = !!data.has_more;
            document.getElementById('trending-load-more').style.display = trendingHasMore ? 'block' : 'none';
            trendingPage += 1;
        }
    } catch (error) {
        console.error('Failed to load trending items:', error);
    }
}

function loadMoreTrending() {
    if (!trendingHasMore) return;
    loadTrendingItems(false);
}

// Render trending items
function renderTrendingItems(items, append = false) {
    const list = document.getElementById('trending-list');
    if (!append) {
        list.innerHTML = '';
    }

    items.forEach(item => {
        const trendingElement = document.createElement('div');
        trendingElement.className = 'trending-item';
        const mediaSrc = getFirstMedia(item.media_urls);
        trendingElement.innerHTML = `
            <div class="trending-header">
                <span class="trending-rank">#${item.rank}</span>
                <span class="item-type" style="background: ${getTypeColor(item.type)}">${item.type}</span>
            </div>
            <h4>${item.title}</h4>
            <p>${item.description}</p>
            ${mediaSrc ? `<img class="trending-media" src="${mediaSrc}" alt="media" />` : ''}
            <div class="trending-meta">
                <span>📍 ${item.city || 'Unknown'}</span>
                <span>👤 ${item.user_name}</span>
                <span>👍 ${item.like_count ?? 0} • 💬 ${item.comment_count ?? 0} • 📤 ${item.share_count ?? 0}</span>
                <span>📈 ${item.engagement_count} interactions</span>
            </div>
        `;
        list.appendChild(trendingElement);
    });
}

// Get color for item type
function getTypeColor(type) {
    const colors = {
        'event': '#3b82f6',
        'issue': '#ef4444',
        'notice': '#eab308',
        'report': '#8b5cf6'
    };
    return colors[type] || '#6b7280';
}

function getFirstMedia(mediaField) {
    if (!mediaField) return null;
    try {
        const arr = typeof mediaField === 'string' ? JSON.parse(mediaField) : mediaField;
        if (Array.isArray(arr) && arr.length > 0) return arr[0];
    } catch (e) {
        console.warn('Failed to parse media_urls', e);
    }
    return null;
}

// Format date for display
function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString();
}

function showToast(message) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.position = 'fixed';
        container.style.top = '1rem';
        container.style.right = '1rem';
        container.style.zIndex = '2000';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.textContent = message;
    toast.style.background = '#111827';
    toast.style.color = '#f8fafc';
    toast.style.padding = '0.75rem 1rem';
    toast.style.marginTop = '0.5rem';
    toast.style.borderRadius = '8px';
    toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Filter map items (when filters change)
function filterMapItems() {
    loadMapItems();
}

// Show form modal
function showForm() {
    document.getElementById('form-modal').style.display = 'flex';
    toggleFormFields();
}

// Close form modal
function closeForm() {
    document.getElementById('form-modal').style.display = 'none';
    // Reset form
    document.getElementById('form-modal').querySelector('form')?.reset();
}

// Toggle form fields based on item type
function toggleFormFields() {
    const type = document.getElementById('form-type').value;
    const dynamicFields = document.getElementById('dynamic-fields');

    let html = '';

    switch(type) {
        case 'event':
            html = `
                <div class="form-section">
                    <label for="form-date">Date & Time</label>
                    <input type="datetime-local" id="form-date" />
                </div>
                <div class="form-section">
                    <label for="form-link">Registration Link</label>
                    <input type="url" id="form-link" placeholder="https://example.com/register" />
                </div>
                <div class="form-section">
                    <label for="form-coc">Code of Conduct</label>
                    <textarea id="form-coc" placeholder="Event guidelines..."></textarea>
                </div>
            `;
            break;

        case 'issue':
            html = `
                <div class="form-section">
                    <label for="form-category">Category</label>
                    <select id="form-category">
                        <option value="infrastructure">Infrastructure</option>
                        <option value="safety">Safety</option>
                        <option value="environment">Environment</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-section">
                    <label for="form-urgency">Urgency</label>
                    <select id="form-urgency">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
            `;
            break;

        case 'notice':
            html = `
                <div class="form-section">
                    <label for="form-valid-until">Valid Until</label>
                    <input type="date" id="form-valid-until" />
                </div>
                <div class="form-section">
                    <label for="form-contact">Contact Information</label>
                    <input type="text" id="form-contact" placeholder="Email, phone, or website" />
                </div>
            `;
            break;

        case 'report':
            html = `
                <div class="form-section">
                    <label>
                        <input type="checkbox" id="form-confidential"> Confidential Report
                    </label>
                </div>
                <div class="form-section">
                    <label for="form-priority">Priority</label>
                    <select id="form-priority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            `;
            break;
    }

    dynamicFields.innerHTML = html;
}

// Preview uploaded images
function previewImages(event) {
    const files = event.target.files;
    const previewContainer = document.getElementById('image-preview');
    previewContainer.innerHTML = '';

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'image-preview-item';
                previewContainer.appendChild(img);
            }
            reader.readAsDataURL(file);
        }
    }
}

// Get current location for form
function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                document.getElementById('form-lat').value = position.coords.latitude.toFixed(6);
                document.getElementById('form-lng').value = position.coords.longitude.toFixed(6);
            },
            function(error) {
                alert('Unable to get your location: ' + error.message);
            }
        );
    } else {
        alert('Geolocation is not supported by your browser.');
    }
}

// Save new item
async function saveNewItem() {
    const formData = new FormData();

    // Basic fields
    formData.append('type', document.getElementById('form-type').value);
    formData.append('title', document.getElementById('form-title').value);
    formData.append('description', document.getElementById('form-desc').value);
    formData.append('latitude', document.getElementById('form-lat').value);
    formData.append('longitude', document.getElementById('form-lng').value);
    formData.append('severity', document.getElementById('form-severity').value);

    // Type-specific fields
    const type = document.getElementById('form-type').value;
    switch(type) {
        case 'event':
            formData.append('date', document.getElementById('form-date').value);
            formData.append('link', document.getElementById('form-link').value);
            formData.append('code_of_conduct', document.getElementById('form-coc').value);
            break;
        case 'issue':
            formData.append('category', document.getElementById('form-category').value);
            formData.append('urgency', document.getElementById('form-urgency').value);
            break;
        case 'notice':
            formData.append('valid_until', document.getElementById('form-valid-until').value);
            formData.append('contact_info', document.getElementById('form-contact').value);
            break;
        case 'report':
            formData.append('confidential', document.getElementById('form-confidential').checked);
            formData.append('priority', document.getElementById('form-priority').value);
            break;
    }

    // Append images
    const imageFiles = document.getElementById('form-images').files;
    for (let i = 0; i < imageFiles.length; i++) {
        formData.append('images[]', imageFiles[i]);
    }

    try {
        const response = await fetch('api/create-item.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            closeForm();
            // Refresh current tab content
            if (currentTab === 'home') loadHomeContent();
            else if (currentTab === 'map') loadMapContent();
            else if (currentTab === 'feed') loadFeedContent();
            else if (currentTab === 'trending') loadTrendingContent();

            alert('Item created successfully!');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error saving item:', error);
        alert('Failed to save item. Please try again.');
    }
}

// Map control functions
function centerMap() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                map.setView([position.coords.latitude, position.coords.longitude], 15);
            },
            function() {
                map.setView([28.6139, 77.2090], 12); // Default view
            }
        );
    } else {
        map.setView([28.6139, 77.2090], 12);
    }
}

function zoomIn() {
    map.zoomIn();
}

function zoomOut() {
    map.zoomOut();
}

// Navigation helper functions
function goToMapTab() {
    showTab('map');
}

function goToFeedTab() {
    showTab('feed');
}

function goToTrendingTab() {
    showTab('trending');
}

// Logout function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        fetch('auth/logout.php', { method: 'POST' })
            .then(() => {
                window.location.href = 'index.html';
            });
    }
}