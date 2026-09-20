<?php
session_start();
if (!isset($_SESSION['admin_user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Travel AI Chatbot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../common/css/variables.css?v=1">
    <link rel="stylesheet" href="css/admin.css?v=3">
</head>
<body class="admin-body">
    <div class="container-fluid py-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 m-0">Admin Panel</h1>
            <div>
                <span class="me-2">Hi, <?= htmlspecialchars((string) $_SESSION['admin_name']) ?></span>
                <a class="btn btn-sm btn-outline-light" href="logout.php">Logout</a>
            </div>
        </div>
        <div id="adminFeedback" class="admin-feedback alert alert-success mb-3 py-2 px-3 d-none" role="status" aria-live="polite"></div>
        <div class="row g-2 mb-3" id="dashboardCards">
            <div class="col-6 col-md-3"><div class="card admin-card"><div class="card-body py-3"><div class="small text-secondary">👥 Total Users</div><div id="statUsers" class="fs-4 fw-bold">0</div></div></div></div>
            <div class="col-6 col-md-3"><div class="card admin-card"><div class="card-body py-3"><div class="small text-secondary">📍 Total Places</div><div id="statPlaces" class="fs-4 fw-bold">0</div></div></div></div>
            <div class="col-6 col-md-3"><div class="card admin-card"><div class="card-body py-3"><div class="small text-secondary">💬 Total Chats</div><div id="statChats" class="fs-4 fw-bold">0</div></div></div></div>
            <div class="col-6 col-md-3"><div class="card admin-card"><div class="card-body py-3"><div class="small text-secondary">🆕 Today New Users</div><div id="statTodayUsers" class="fs-4 fw-bold">0</div></div></div></div>
        </div>

        <ul class="nav nav-tabs mb-3" id="adminTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#districtsTab" type="button">Districts</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#placesTab" type="button">Places</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#usersTab" type="button">Users</button></li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="districtsTab">
                <div class="card admin-card mb-3">
                    <div class="card-body">
                        <form id="districtForm" class="row g-2">
                            <input type="hidden" id="districtId">
                            <div class="col-md-8"><input id="districtName" class="form-control" placeholder="District name" required></div>
                            <div class="col-md-4 d-grid"><button class="btn btn-primary" type="submit">Save District</button></div>
                        </form>
                    </div>
                </div>
                <div class="card admin-card"><div class="card-body"><div id="districtTableWrap"></div></div></div>
            </div>

            <div class="tab-pane fade" id="placesTab">
                <div class="card admin-card mb-3">
                    <div class="card-body">
                        <form id="placeForm" class="row g-2">
                            <input type="hidden" id="placeId">
                            <div class="col-md-3"><select id="placeDistrict" class="form-select" required></select></div>
                            <div class="col-md-3"><input id="placeName" class="form-control" placeholder="Place name" required></div>
                            <div class="col-md-2"><input id="placeCategory" class="form-control" placeholder="Category" required></div>
                            <div class="col-md-2"><input id="placeBestTime" class="form-control" placeholder="Best time" required></div>
                            <div class="col-md-2"><input id="placeBudget" class="form-control" type="number" min="0" placeholder="Budget LKR" required></div>
                            <div class="col-12"><textarea id="placeDescription" class="form-control" rows="2" placeholder="Description" required></textarea></div>
                            <div class="col-12 d-grid"><button class="btn btn-primary" type="submit">Save Place</button></div>
                        </form>
                    </div>
                </div>
                <div class="card admin-card mb-3">
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6"><input id="placeSearchInput" class="form-control" placeholder="Search place by name"></div>
                            <div class="col-md-6"><input id="placeDistrictFilter" class="form-control" placeholder="Filter by district/category"></div>
                        </div>
                    </div>
                </div>
                <div class="card admin-card"><div class="card-body"><div id="placeTableWrap"></div></div></div>
            </div>

            <div class="tab-pane fade" id="usersTab">
                <div class="card admin-card mb-3">
                    <div class="card-body">
                        <form id="userCreateForm" class="row g-2">
                            <div class="col-md-3"><input id="newUserName" class="form-control" placeholder="Full name" required></div>
                            <div class="col-md-3"><input id="newUserEmail" type="email" class="form-control" placeholder="Email" required></div>
                            <div class="col-md-3"><input id="newUserPassword" type="password" class="form-control" placeholder="Password (min 6)" minlength="6" required></div>
                            <div class="col-md-2">
                                <select id="newUserAdminFlag" class="form-select">
                                    <option value="0">Normal User</option>
                                    <option value="1">Admin</option>
                                </select>
                            </div>
                            <div class="col-md-1 d-grid"><button class="btn btn-primary" type="submit">Add</button></div>
                        </form>
                    </div>
                </div>
                <div class="card admin-card mb-3">
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6"><input id="userSearchInput" class="form-control" placeholder="Search user by name"></div>
                            <div class="col-md-6"><input id="userEmailSearchInput" class="form-control" placeholder="Search user by email"></div>
                        </div>
                    </div>
                </div>
                <div class="card admin-card"><div class="card-body"><div id="userTableWrap"></div></div></div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/admin.js?v=6"></script>
</body>
</html>
