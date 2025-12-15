// settings.js
let currentUser = null;
let activityPage = 1;
let activityHasMore = true;
let activityLoading = false;
const ACTIVITY_LIMIT = 10;

document.addEventListener('DOMContentLoaded', async () => {
    await checkAuth();
    bindTabs();
    loadAccount();
    loadActivity(true);
    window.addEventListener('scroll', handleActivityScroll);
});

async function checkAuth() {
    try {
        const res = await fetch('api/check-session.php');
        const data = await res.json();
        if (!data.authenticated) {
            window.location.href = 'login.html';
            return;
        }
        currentUser = data.user;
        document.getElementById('settings-user-chip').textContent = currentUser.name.charAt(0).toUpperCase();
    } catch (e) {
        console.error('Auth failed', e);
        window.location.href = 'login.html';
    }
}

function bindTabs() {
    document.querySelectorAll('.settings-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.settings-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(`settings-${btn.dataset.settingsTab}`).classList.add('active');
        });
    });
}

async function loadAccount() {
    try {
        const res = await fetch('api/user-profile.php');
        const data = await res.json();
        if (data.success) {
            document.getElementById('settings-name').textContent = data.profile.name || '—';
            document.getElementById('settings-email').textContent = data.profile.email || '—';
            document.getElementById('settings-city').textContent = data.profile.city || '—';
            document.getElementById('settings-reputation').textContent = data.stats.reputation ?? 0;
            // cache stats for activity
            window.__activityStats = data.stats;
            renderActivityStats();
        }
    } catch (e) {
        console.error('Failed to load account', e);
    }
}

function renderActivityStats() {
    const stats = window.__activityStats || {};
    document.getElementById('settings-likes-given').textContent = stats.likes_given ?? 0;
    document.getElementById('settings-comments-given').textContent = stats.comments_given ?? 0;
    document.getElementById('settings-shares-given').textContent = stats.shares_given ?? 0;
    document.getElementById('settings-likes-received').textContent = stats.likes_received ?? 0;
    document.getElementById('settings-comments-received').textContent = stats.comments_received ?? 0;
    document.getElementById('settings-shares-received').textContent = stats.shares_received ?? 0;
}

async function loadActivity(reset = false) {
    if (activityLoading) return;
    if (reset) {
        activityPage = 1;
        activityHasMore = true;
        document.getElementById('settings-activity-list').innerHTML = '';
    }
    if (!activityHasMore) return;

    activityLoading = true;
    toggleActivityLoading(true);

    try {
        const res = await fetch(`api/recent-activity.php?page=${activityPage}&limit=${ACTIVITY_LIMIT}`);
        const data = await res.json();
        if (data.success) {
            renderActivity(data.activity, activityPage > 1);
            activityHasMore = data.has_more;
            activityPage += 1;
        }
    } catch (e) {
        console.error('Failed to load activity', e);
    } finally {
        activityLoading = false;
        toggleActivityLoading(false);
    }
}

function renderActivity(items, append = false) {
    const list = document.getElementById('settings-activity-list');
    if (!append) list.innerHTML = '';

    if ((!items || items.length === 0) && !append) {
        list.innerHTML = '<p class="activity-empty">No recent activity yet.</p>';
        return;
    }

    items.forEach(act => {
        const el = document.createElement('div');
        el.className = 'activity-item';
        const label = formatActivityLabel(act);
        const mediaHtml = act.media ? `<div class="activity-media"><img src="${act.media}" alt="media preview"></div>` : '';
        el.innerHTML = `<p>${label}</p><small>${formatDate(act.created_at)}</small>${mediaHtml}`;
        list.appendChild(el);
    });
}

function formatActivityLabel(act) {
    const title = act.target_title || 'an item';
    switch (act.type) {
        case 'posted':
            return `You posted "${title}"`;
        case 'commented':
            return `You commented on "${title}"`;
        case 'liked':
            return `You liked "${title}"`;
        case 'shared':
            return `You shared "${title}"`;
        default:
            return `${act.type} ${act.target_type} "${title}"`;
    }
}

function handleActivityScroll() {
    const nearBottom = window.innerHeight + window.scrollY >= document.body.offsetHeight - 200;
    if (nearBottom && activityHasMore && !activityLoading) {
        loadActivity();
    }
}

function toggleActivityLoading(show) {
    const indicator = document.getElementById('settings-activity-loading');
    if (!indicator) return;
    indicator.style.display = show ? 'block' : 'none';
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleString();
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        fetch('auth/logout.php', { method: 'POST' }).then(() => window.location.href = 'index.html');
    }
}
