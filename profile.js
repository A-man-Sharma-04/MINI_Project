// profile.js
let currentUser = null;
let profilePostsCache = new Map();
let editModal = null;
let editForm = null;

document.addEventListener('DOMContentLoaded', async function() {
    await checkAuth(); // ensure currentUser is set before dependent calls
    setupEventListeners();
    primePlaceholders();
    loadProfileContent(); // Load the default profile content (posts)

    // cache modal elements
    editModal = document.getElementById('post-edit-modal');
    editForm = document.getElementById('post-edit-form');
    if (editForm) {
        editForm.addEventListener('submit', handleEditSubmit);
        document.getElementById('post-edit-cancel').addEventListener('click', closeEditModal);
    }
});

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

function updateUI() {
    if (!currentUser) return;

    document.getElementById('profile-user-initial').textContent = currentUser.name.charAt(0).toUpperCase();
    document.getElementById('profile-user-name').textContent = currentUser.name;
    document.getElementById('profile-name-display').textContent = currentUser.name;
}

function setupEventListeners() {
    // Profile sub-tabs
    document.querySelectorAll('.profile-tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const profileTab = this.getAttribute('data-profile-tab');
            showProfileTab(profileTab);
        });
    });
}

function showProfileTab(profileTab) {
    // Update active button
    document.querySelectorAll('.profile-tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-profile-tab="${profileTab}"]`).classList.add('active');

    // Show/hide content
    document.querySelectorAll('.profile-tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(`profile-${profileTab}-content`).classList.add('active');

    // Load sub-tab content
    switch(profileTab) {
        case 'posts':
            loadProfilePosts();
            break;
        case 'followers':
            loadProfileFollowers();
            break;
        case 'following':
            loadProfileFollowing();
            break;
    }
}

// Load user profile summary (stats)
async function loadProfileContent() {
    try {
        const response = await fetch('api/user-profile.php');
        const data = await response.json();

        if (data.success) {
            document.getElementById('profile-posts-count').textContent = data.stats.total_items;
            document.getElementById('profile-followers-count').textContent = data.stats.followers_count;
            document.getElementById('profile-following-count').textContent = data.stats.following_count;
            document.getElementById('profile-reputation').textContent = data.stats.reputation;

            // Bio
            document.getElementById('profile-bio').textContent = data.profile.bio || '';

            // Name (fresh from DB in case it changed)
            if (data.profile.name) {
                document.getElementById('profile-name-display').textContent = data.profile.name;
                document.getElementById('profile-user-name').textContent = data.profile.name;
                document.getElementById('profile-user-initial').textContent = data.profile.name.charAt(0).toUpperCase();
            }

            setBannerImage(data.profile.banner_image);
            setAvatar(data.profile.profile_image, data.profile.name || 'User');

            // Immediately load posts on first view
            loadProfilePosts();
        }
    } catch (error) {
        console.error('Failed to load profile summary:', error);
    }
}

// Pre-set placeholders to reduce flash/jump
function primePlaceholders() {
    document.getElementById('profile-bio').textContent = ''; // keep empty to avoid default flash
    document.getElementById('profile-posts-count').textContent = '–';
    document.getElementById('profile-followers-count').textContent = '–';
    document.getElementById('profile-following-count').textContent = '–';
    document.getElementById('profile-reputation').textContent = '–';
}

function setBannerImage(url) {
    const bannerEl = document.getElementById('profile-banner-image');
    if (!url) {
        bannerEl.src = 'https://via.placeholder.com/800x200/3b82f6/white?text=Banner';
        return;
    }
    const img = new Image();
    img.onload = () => { bannerEl.src = url; };
    img.onerror = () => { bannerEl.src = 'https://via.placeholder.com/800x200/3b82f6/white?text=Banner'; };
    img.src = url;
}

function setAvatar(imageUrl, name) {
    const avatarEl = document.getElementById('profile-avatar');
    const initial = (name || 'U').charAt(0).toUpperCase();
    avatarEl.textContent = initial;
    avatarEl.style.backgroundImage = '';

    if (!imageUrl) return;

    const img = new Image();
    img.onload = () => {
        avatarEl.style.backgroundImage = `url(${imageUrl})`;
        avatarEl.textContent = ''; // hide initial when image loads
    };
    img.onerror = () => {
        avatarEl.style.backgroundImage = '';
        avatarEl.textContent = initial; // fallback to initial
    };
    img.src = imageUrl;
}

// Load profile posts
async function loadProfilePosts() {
    try {
        const response = await fetch(`api/user-posts.php?user_id=${currentUser.id}`);
        const data = await response.json();

        if (data.success) {
            const list = document.getElementById('profile-posts-list');
            list.innerHTML = '';
            profilePostsCache.clear();

            data.posts.forEach(post => {
                profilePostsCache.set(post.id, post);
                const postElement = document.createElement('div');
                postElement.className = 'profile-post';
                postElement.innerHTML = `
                    <div class="post-header">
                        <div class="post-avatar">${currentUser.name.charAt(0)}</div>
                        <div>
                            <div class="post-title">${post.title}</div>
                            <div class="post-time">${formatDate(post.created_at)}${post.updated_at ? ` • edited ${formatDateTime(post.updated_at)}` : ''}</div>
                        </div>
                    </div>
                    <p>${post.description}</p>
                    <div class="post-meta">
                        <span class="item-type" style="background: ${getTypeColor(post.type)}">${post.type}</span>
                        <span>📍 ${post.city || 'Unknown'}</span>
                    </div>
                    <div class="post-actions">
                        <button class="post-btn post-edit-btn" data-post-id="${post.id}">Edit</button>
                        <button class="post-btn post-delete-btn" data-post-id="${post.id}">Delete</button>
                    </div>
                `;
                list.appendChild(postElement);
            });
        }
    } catch (error) {
        console.error('Failed to load profile posts:', error);
    }
}

// Load profile followers
async function loadProfileFollowers() {
    try {
        const response = await fetch(`api/user-followers.php?user_id=${currentUser.id}`);
        const data = await response.json();

        if (data.success) {
            const list = document.getElementById('profile-followers-list');
            list.innerHTML = '';

            data.followers.forEach(follower => {
                const followerElement = document.createElement('div');
                followerElement.className = 'profile-follower';
                followerElement.innerHTML = `
                    <div class="follower-info">
                        <div class="follower-avatar">${follower.name.charAt(0)}</div>
                        <div>
                            <div class="follower-name">${follower.name}</div>
                            <div class="follower-email">${follower.email}</div>
                        </div>
                    </div>
                    <button class="follow-btn toggle-follow ${follower.is_following ? 'following' : ''}" data-user-id="${follower.id}">${follower.is_following ? 'Following' : 'Follow'}</button>
                `;
                list.appendChild(followerElement);
            });
        }
    } catch (error) {
        console.error('Failed to load followers:', error);
    }

        function formatDateTime(dateString) {
            return new Date(dateString).toLocaleString();
        }
}

// Load profile following
async function loadProfileFollowing() {
    try {
        const response = await fetch(`api/user-following.php?user_id=${currentUser.id}`);
        const data = await response.json();

        if (data.success) {
            const list = document.getElementById('profile-following-list');
            list.innerHTML = '';

            data.following.forEach(followed => {
                const followedElement = document.createElement('div');
                followedElement.className = 'profile-followed';
                followedElement.innerHTML = `
                    <div class="followed-info">
                        <div class="followed-avatar">${followed.name.charAt(0)}</div>
                        <div>
                            <div class="followed-name">${followed.name}</div>
                            <div class="followed-email">${followed.email}</div>
                        </div>
                    </div>
                    <button class="unfollow-btn toggle-follow following" data-user-id="${followed.id}">Unfollow</button>
                `;
                list.appendChild(followedElement);
            });
        }
    } catch (error) {
        console.error('Failed to load following:', error);
    }
}

document.addEventListener('click', async (e) => {
    const toggleBtn = e.target.closest('.toggle-follow');
    if (!toggleBtn) return;
    const userId = toggleBtn.dataset.userId;
    const currentlyFollowing = toggleBtn.classList.contains('following');
    toggleBtn.disabled = true;
    toggleBtn.textContent = currentlyFollowing ? 'Unfollowing...' : 'Following...';
    const result = await toggleFollow(userId);
    if (result && result.success) {
        const nowFollowing = !currentlyFollowing;
        toggleBtn.classList.toggle('following', nowFollowing);
        toggleBtn.textContent = nowFollowing ? 'Following' : 'Follow';
        loadProfileContent();
    } else {
        toggleBtn.textContent = currentlyFollowing ? 'Unfollow' : 'Follow';
        alert('Failed to update follow state');
    }
    toggleBtn.disabled = false;
});

async function toggleFollow(targetUserId) {
    try {
        const res = await fetch('api/follow-toggle.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ target_user_id: Number(targetUserId) })
        });
        return await res.json();
    } catch (error) {
        console.error('Failed to toggle follow:', error);
    }
    return null;
}

// Post edit/delete handlers (self-only page)
document.addEventListener('click', async (e) => {
    const editBtn = e.target.closest('.post-edit-btn');
    const deleteBtn = e.target.closest('.post-delete-btn');

    if (editBtn) {
        const postId = Number(editBtn.dataset.postId);
        await handleEditPost(postId);
        return;
    }

    if (deleteBtn) {
        const postId = Number(deleteBtn.dataset.postId);
        await handleDeletePost(postId);
    }
});

async function handleEditPost(postId) {
    const post = profilePostsCache.get(postId);
    if (!post) return;
    const allowed = ['event','issue','notice','report'];
    const typeSelect = document.getElementById('post-edit-type');

    document.getElementById('post-edit-id').value = postId;
    document.getElementById('post-edit-title').value = post.title || '';
    document.getElementById('post-edit-description').value = post.description || '';
    document.getElementById('post-edit-city').value = post.city || '';
    document.getElementById('post-edit-state').value = post.state || '';
    document.getElementById('post-edit-country').value = post.country || '';
    if (allowed.includes(post.type)) {
        typeSelect.value = post.type;
    }
    openEditModal();
}

function openEditModal() {
    if (editModal) editModal.style.display = 'flex';
}

function closeEditModal() {
    if (editModal) editModal.style.display = 'none';
}

async function handleEditSubmit(e) {
    e.preventDefault();
    const postId = Number(document.getElementById('post-edit-id').value);
    const newTitle = document.getElementById('post-edit-title').value.trim();
    const newDescription = document.getElementById('post-edit-description').value.trim();
    const newType = document.getElementById('post-edit-type').value;
    const newCity = document.getElementById('post-edit-city').value.trim();
    const newState = document.getElementById('post-edit-state').value.trim();
    const newCountry = document.getElementById('post-edit-country').value.trim();

    if (!newTitle || !newDescription) {
        alert('Title and description are required');
        return;
    }

    try {
        const res = await fetch('api/update-item.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                item_id: postId,
                title: newTitle,
                description: newDescription,
                type: newType,
                city: newCity,
                state: newState,
                country: newCountry
            })
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Failed to update post');
            return;
        }
        closeEditModal();
        await loadProfilePosts();
    } catch (err) {
        console.error('Failed to edit post', err);
        alert('Failed to update post');
    }
}

async function handleDeletePost(postId) {
    if (!confirm('Delete this post? This cannot be undone.')) return;
    try {
        const res = await fetch('api/delete-item.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: postId })
        });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Failed to delete post');
            return;
        }
        profilePostsCache.delete(postId);
        await loadProfilePosts();
        await loadProfileContent();
    } catch (err) {
        console.error('Failed to delete post', err);
        alert('Failed to delete post');
    }
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

// Format date for display
function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString();
}

// Logout function (can be reused)
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        fetch('auth/logout.php', { method: 'POST' })
            .then(() => {
                window.location.href = 'index.html';
            });
    }
}