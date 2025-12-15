document.addEventListener('DOMContentLoaded', () => {
  loadProfile();

  const form = document.getElementById('edit-profile-form');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    await saveProfile();
  });
});

async function loadProfile() {
  try {
    const res = await fetch('api/user-profile.php');
    const data = await res.json();
    if (!data.success) throw new Error('Failed to load profile');

    const p = data.profile;
    document.getElementById('name').value = p.name || '';
    document.getElementById('email').value = p.email || '';
    document.getElementById('contact').value = p.contact || '';
    document.getElementById('bio').value = p.bio || '';
    document.getElementById('profile_image').value = p.profile_image || '';
    document.getElementById('banner_image').value = p.banner_image || '';
  } catch (err) {
    console.error(err);
    alert('Unable to load profile. Please retry.');
  }
}

async function saveProfile() {
  const payload = {
    name: document.getElementById('name').value.trim(),
    email: document.getElementById('email').value.trim(),
    contact: document.getElementById('contact').value.trim(),
    bio: document.getElementById('bio').value.trim(),
    profile_image: document.getElementById('profile_image').value.trim(),
    banner_image: document.getElementById('banner_image').value.trim()
  };

  if (!payload.name || !payload.email) {
    alert('Name and email are required');
    return;
  }

  try {
    const res = await fetch('api/update-profile.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      alert('Profile updated');
      window.location.href = 'profile.html';
    } else {
      alert(data.message || 'Update failed');
    }
  } catch (err) {
    console.error(err);
    alert('Unable to save profile. Please retry.');
  }
}
