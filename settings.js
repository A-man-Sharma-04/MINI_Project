// settings.js
let currentUser = null;

document.addEventListener('DOMContentLoaded', async () => {
    await checkAuth();
    bindTabs();
    loadAccount();
    loadActivity();
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

async function loadActivity() {
    try {
        const res = await fetch('api/recent-activity.php');
        const data = await res.json();
        if (data.success) {
            const list = document.getElementById('settings-activity-list');
            list.innerHTML = '';
            data.activity.forEach(act => {
                const el = document.createElement('div');
                el.className = 'activity-item';
                el.innerHTML = `<p>${act.type} ${act.target_type} "${act.target_title}"</p><small>${formatDate(act.created_at)}</small>`;
                list.appendChild(el);
            });
        }
    } catch (e) {
        console.error('Failed to load activity', e);
    }
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleString();
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        fetch('auth/logout.php', { method: 'POST' }).then(() => window.location.href = 'index.html');
    }
}
