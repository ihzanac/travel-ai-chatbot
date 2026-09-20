async function adminPost(action, payload = {}) {
    const res = await fetch('../api/admin/index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...payload })
    });
    const data = await res.json();
    if (!res.ok || !data.success) throw new Error(data.error || 'Request failed');
    return data;
}

function esc(v) {
    const d = document.createElement('div');
    d.textContent = v ?? '';
    return d.innerHTML;
}

function showAdminFeedback(message, variant = 'success') {
    const el = document.getElementById('adminFeedback');
    if (!el) {
        window.alert(message);
        return;
    }
    el.textContent = message;
    el.className = `admin-feedback alert mb-3 py-2 px-3 alert-${variant}`;
    el.classList.remove('d-none');
    if (window.__adminFeedbackTimer) {
        window.clearTimeout(window.__adminFeedbackTimer);
    }
    window.__adminFeedbackTimer = window.setTimeout(() => {
        el.classList.add('d-none');
    }, 4500);
}

let allUsers = [];
let allPlaces = [];

function renderUsersTable(users) {
    const wrap = document.getElementById('userTableWrap');
    wrap.innerHTML = `<div class="table-responsive"><table class="table table-dark table-sm">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Admin</th><th></th></tr></thead>
        <tbody>
            ${users.map((u) => `<tr>
                <td>${u.id}</td><td>${esc(u.full_name)}</td><td>${esc(u.email)}</td>
                <td>
                    <select class="form-select form-select-sm" onchange="toggleAdmin(${u.id}, this.value)">
                        <option value="0" ${u.is_admin == 0 ? 'selected' : ''}>User</option>
                        <option value="1" ${u.is_admin == 1 ? 'selected' : ''}>Admin</option>
                    </select>
                </td>
                <td><button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${u.id})">Delete</button></td>
            </tr>`).join('')}
        </tbody>
    </table></div>`;
}

function renderPlacesTable(places) {
    const wrap = document.getElementById('placeTableWrap');
    wrap.innerHTML = `<div class="table-responsive"><table class="table table-dark table-sm">
        <thead><tr><th>ID</th><th>District</th><th>Place</th><th>Category</th><th>Budget</th><th></th></tr></thead>
        <tbody>
            ${places.map((p) => `<tr>
                <td>${p.id}</td><td>${esc(p.district)}</td><td>${esc(p.place_name)}</td><td>${esc(p.category)}</td><td>${p.avg_budget_lkr}</td>
                <td>
                    <button class="btn btn-sm btn-outline-info me-1" onclick='editPlace(${JSON.stringify(p).replace(/'/g, '&#39;')})'>Edit</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deletePlace(${p.id})">Delete</button>
                </td>
            </tr>`).join('')}
        </tbody>
    </table></div>`;
}

function applyUserFilters() {
    const nameQ = (document.getElementById('userSearchInput').value || '').toLowerCase().trim();
    const emailQ = (document.getElementById('userEmailSearchInput').value || '').toLowerCase().trim();
    const filtered = allUsers.filter((u) => {
        const okName = nameQ === '' || (u.full_name || '').toLowerCase().includes(nameQ);
        const okEmail = emailQ === '' || (u.email || '').toLowerCase().includes(emailQ);
        return okName && okEmail;
    });
    renderUsersTable(filtered);
}

function applyPlaceFilters() {
    const placeQ = (document.getElementById('placeSearchInput').value || '').toLowerCase().trim();
    const districtQ = (document.getElementById('placeDistrictFilter').value || '').toLowerCase().trim();
    const filtered = allPlaces.filter((p) => {
        const okPlace = placeQ === '' || (p.place_name || '').toLowerCase().includes(placeQ);
        const districtText = `${p.district || ''} ${p.category || ''}`.toLowerCase();
        const okDistrict = districtQ === '' || districtText.includes(districtQ);
        return okPlace && okDistrict;
    });
    renderPlacesTable(filtered);
}


async function loadDashboardStats() {
    const data = await adminPost('dashboard_stats');
    document.getElementById('statUsers').textContent = data.stats.total_users;
    document.getElementById('statPlaces').textContent = data.stats.total_places;
    document.getElementById('statChats').textContent = data.stats.total_chats;
    document.getElementById('statTodayUsers').textContent = data.stats.today_users;
}

async function loadDistricts() {
    const data = await adminPost('district_list');
    const wrap = document.getElementById('districtTableWrap');
    const districtSelect = document.getElementById('placeDistrict');
    districtSelect.innerHTML = '<option value="">Select district</option>';
    data.districts.forEach((d) => {
        const opt = document.createElement('option');
        opt.value = d.district_name;
        opt.textContent = d.district_name;
        districtSelect.appendChild(opt);
    });

    wrap.innerHTML = `<table class="table table-dark table-sm">
        <thead><tr><th>ID</th><th>District</th><th></th></tr></thead>
        <tbody>
            ${data.districts.map((d) => `<tr>
                <td>${esc(d.id)}</td>
                <td>${esc(d.district_name)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-info me-1" onclick='editDistrict(${Number(d.id)}, ${JSON.stringify(String(d.district_name ?? ''))})'>Edit</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteDistrict(${Number(d.id)})">Delete</button>
                </td>
            </tr>`).join('')}
        </tbody>
    </table>`;
}

window.editDistrict = (id, name) => {
    document.getElementById('districtId').value = id;
    document.getElementById('districtName').value = name;
    document.getElementById('districtName').focus({ preventScroll: true });
    document.getElementById('districtForm')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

window.deleteDistrict = async (id) => {
    if (!confirm('Delete district?')) return;
    await adminPost('district_delete', { id });
    await loadDistricts();
};

async function loadPlaces() {
    const data = await adminPost('place_list');
    allPlaces = data.places || [];
    applyPlaceFilters();
}

window.editPlace = (p) => {
    document.getElementById('placeId').value = p.id;
    document.getElementById('placeDistrict').value = p.district;
    document.getElementById('placeName').value = p.place_name;
    document.getElementById('placeCategory').value = p.category;
    document.getElementById('placeDescription').value = p.description;
    document.getElementById('placeBestTime').value = p.best_time;
    document.getElementById('placeBudget').value = p.avg_budget_lkr;
};

window.deletePlace = async (id) => {
    if (!confirm('Delete place?')) return;
    await adminPost('place_delete', { id });
    await loadPlaces();
};

async function loadUsers() {
    const data = await adminPost('user_list');
    allUsers = data.users || [];
    applyUserFilters();
}


window.toggleAdmin = async (id, isAdmin) => {
    await adminPost('user_admin_toggle', { id, is_admin: Number(isAdmin) });
    await loadUsers();
};

window.deleteUser = async (id) => {
    if (!confirm('Delete user?')) return;
    try {
        await adminPost('user_delete', { id });
        showAdminFeedback(`User #${id} deleted successfully.`);
        await loadUsers();
        await loadDashboardStats();
    } catch (err) {
        showAdminFeedback(err.message || 'Could not delete user.', 'danger');
    }
};


document.getElementById('districtForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    await adminPost('district_save', {
        id: Number(document.getElementById('districtId').value || 0),
        district_name: document.getElementById('districtName').value.trim()
    });
    document.getElementById('districtId').value = '';
    document.getElementById('districtName').value = '';
    await loadDistricts();
});

document.getElementById('placeForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    await adminPost('place_save', {
        id: Number(document.getElementById('placeId').value || 0),
        district: document.getElementById('placeDistrict').value,
        place_name: document.getElementById('placeName').value.trim(),
        category: document.getElementById('placeCategory').value.trim(),
        description: document.getElementById('placeDescription').value.trim(),
        best_time: document.getElementById('placeBestTime').value.trim(),
        avg_budget_lkr: Number(document.getElementById('placeBudget').value || 0)
    });
    document.getElementById('placeForm').reset();
    document.getElementById('placeId').value = '';
    await loadPlaces();
});

document.getElementById('userCreateForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    await adminPost('user_create', {
        full_name: document.getElementById('newUserName').value.trim(),
        email: document.getElementById('newUserEmail').value.trim(),
        password: document.getElementById('newUserPassword').value,
        is_admin: Number(document.getElementById('newUserAdminFlag').value || 0)
    });
    document.getElementById('userCreateForm').reset();
    await loadUsers();
    await loadDashboardStats();
});

document.getElementById('userSearchInput').addEventListener('input', applyUserFilters);
document.getElementById('userEmailSearchInput').addEventListener('input', applyUserFilters);
document.getElementById('placeSearchInput').addEventListener('input', applyPlaceFilters);
document.getElementById('placeDistrictFilter').addEventListener('input', applyPlaceFilters);

(async function init() {
    await loadDashboardStats();
    await loadDistricts();
    await loadPlaces();
    await loadUsers();
})();
