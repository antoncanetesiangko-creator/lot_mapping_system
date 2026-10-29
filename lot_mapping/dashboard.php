<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

// Require login.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$fullName = (string) ($_SESSION['full_name'] ?? 'User');
$role = (string) ($_SESSION['role'] ?? 'viewer');

// Create CSRF token for logout.
if (empty($_SESSION['logout_csrf'])) {
    $_SESSION['logout_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

// Only admin can create map areas.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_area'])) {
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals((string) $_SESSION['logout_csrf'], $token)) {
        $error = 'Invalid request. Please refresh the page.';
    } elseif ($role !== 'admin') {
        http_response_code(403);
        $error = 'Only administrators can create map areas.';
    } else {
        $areaName = trim((string) ($_POST['area_name'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($areaName === '') {
            $error = 'Please enter the map area name.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO map_areas
                        (area_name, location, description, created_by)
                     VALUES
                        (:area_name, :location, :description, :created_by)'
                );

                $stmt->execute([
                    'area_name' => $areaName,
                    'location' => $location !== '' ? $location : null,
                    'description' => $description !== '' ? $description : null,
                    'created_by' => $userId,
                ]);

                $areaId = (int) $pdo->lastInsertId();

                $log = $pdo->prepare(
                    'INSERT INTO activity_logs
                        (user_id, action, entity_type, entity_id, details)
                     VALUES
                        (:user_id, :action, :entity_type, :entity_id, :details)'
                );

                $log->execute([
                    'user_id' => $userId,
                    'action' => 'create_map_area',
                    'entity_type' => 'map_area',
                    'entity_id' => $areaId,
                    'details' => json_encode([
                        'area_name' => $areaName,
                        'location' => $location,
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                header('Location: dashboard.php?created=1');
                exit;
            } catch (PDOException $e) {
                error_log('LOTMAP create area error: ' . $e->getMessage());
                $error = 'Unable to create the map area. Please try again.';
            }
        }
    }
}

if (isset($_GET['created']) && $_GET['created'] === '1') {
    $success = 'Map area created successfully.';
}

// Dashboard summary.
try {
    $stmt = $pdo->query(
        'SELECT
            COUNT(*) AS total_lots,
            COALESCE(SUM(status = "available"), 0) AS available_lots,
            COALESCE(SUM(status = "spot_cash"), 0) AS spot_cash_lots,
            COALESCE(SUM(status = "installment_current"), 0) AS current_installments,
            COALESCE(SUM(status = "overdue"), 0) AS overdue_lots,
            COALESCE(SUM(status = "forfeiture_review"), 0) AS forfeiture_lots
         FROM lots'
    );
    $stats = $stmt->fetch() ?: [];

    $stmt = $pdo->query(
        'SELECT
            ma.id,
            ma.area_name,
            ma.location,
            ma.description,
            ma.map_image_path,
            ma.created_at,
            COUNT(l.id) AS total_lots,
            COALESCE(SUM(l.status = "available"), 0) AS available_lots,
            COALESCE(SUM(l.status = "spot_cash"), 0) AS spot_cash_lots,
            COALESCE(SUM(l.status = "installment_current"), 0) AS current_installments,
            COALESCE(SUM(l.status = "overdue"), 0) AS overdue_lots,
            COALESCE(SUM(l.status = "forfeiture_review"), 0) AS forfeiture_lots
         FROM map_areas ma
         LEFT JOIN lots l ON l.map_area_id = ma.id
         WHERE ma.is_active = 1
         GROUP BY
            ma.id, ma.area_name, ma.location, ma.description,
            ma.map_image_path, ma.created_at
         ORDER BY ma.area_name ASC'
    );

    $areas = $stmt->fetchAll();

    $stmt = $pdo->query(
        'SELECT
            al.action,
            al.entity_type,
            al.entity_id,
            al.created_at,
            u.full_name
         FROM activity_logs al
         LEFT JOIN users u ON u.id = al.user_id
         ORDER BY al.id DESC
         LIMIT 8'
    );

    $activities = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('LOTMAP dashboard error: ' . $e->getMessage());
    $stats = [];
    $areas = [];
    $activities = [];
    $error = $error ?: 'Unable to load dashboard information. Check your database tables.';
}

function h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function statusLabel(string $status): string
{
    return match ($status) {
        'available' => 'Available',
        'spot_cash' => 'Spot Cash',
        'installment_current' => 'Installment Current',
        'overdue' => 'Overdue',
        'forfeiture_review' => 'Forfeiture Review',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function activityLabel(string $action): string
{
    return ucwords(str_replace('_', ' ', $action));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | LOTMAP</title>
    <style>
        :root {
            --green: #16834a;
            --green-dark: #116b3b;
            --page: #f3f6fa;
            --border: #e2e8f0;
            --muted: #718096;
            --text: #172033;
            --white: #fff;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--page);
            color: var(--text);
        }

        a { color: inherit; text-decoration: none; }

        .layout { min-height: 100vh; }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 245px;
            padding: 26px 18px;
            background: #14271f;
            color: #fff;
            display: flex;
            flex-direction: column;
            z-index: 5;
        }

        .brand {
            padding: 0 12px 27px;
            font-size: 25px;
            font-weight: 800;
            letter-spacing: -.7px;
        }

        .brand span { color: #5fe19a; }

        .nav-label {
            padding: 12px;
            color: #9db4a7;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .nav-link {
            display: block;
            padding: 13px 14px;
            margin-bottom: 5px;
            border-radius: 9px;
            color: #d7e5dd;
            font-size: 14px;
        }

        .nav-link.active,
        .nav-link:hover {
            background: #234a36;
            color: #fff;
        }

        .sidebar-bottom {
            margin-top: auto;
            border-top: 1px solid #31503f;
            padding-top: 18px;
        }

        .user-name {
            padding: 0 10px 4px;
            font-size: 14px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .user-role {
            padding: 0 10px 15px;
            color: #a8c3b2;
            font-size: 12px;
            text-transform: capitalize;
        }

        .logout-button {
            width: 100%;
            padding: 11px;
            border: 1px solid #456451;
            border-radius: 8px;
            background: transparent;
            color: #fff;
            cursor: pointer;
            font-size: 13px;
        }

        .logout-button:hover { background: #234a36; }

        .main {
            margin-left: 245px;
            padding: 30px;
            max-width: 1700px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 28px;
        }

        .eyebrow {
            margin-bottom: 7px;
            color: var(--green);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(25px, 3vw, 32px);
            letter-spacing: -1px;
        }

        .date {
            color: var(--muted);
            font-size: 13px;
            text-align: right;
        }

        .welcome {
            margin-bottom: 25px;
            padding: 25px;
            border-radius: 15px;
            background: #173b2a;
            color: white;
        }

        .welcome h2 {
            margin: 0 0 9px;
            font-size: 21px;
        }

        .welcome p {
            margin: 0;
            max-width: 650px;
            color: #d0e6d8;
            font-size: 14px;
            line-height: 1.7;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 28px;
        }

        .stat-card {
            min-width: 0;
            padding: 20px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 13px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .stat-value {
            margin-top: 12px;
            font-size: 29px;
            font-weight: 800;
        }

        .stat-card.green { border-top: 3px solid #16a34a; }
        .stat-card.yellow { border-top: 3px solid #eab308; }
        .stat-card.blue { border-top: 3px solid #3b82f6; }
        .stat-card.red { border-top: 3px solid #ef4444; }
        .stat-card.black { border-top: 3px solid #202020; }

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.65fr) minmax(270px, .85fr);
            gap: 20px;
            align-items: start;
        }

        .panel {
            min-width: 0;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 20px;
            border-bottom: 1px solid var(--border);
        }

        .panel-header h2 {
            margin: 0;
            font-size: 17px;
        }

        .panel-body { padding: 20px; }

        .button {
            display: inline-block;
            padding: 10px 13px;
            border: 0;
            border-radius: 8px;
            background: var(--green);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .button:hover { background: var(--green-dark); }

        .area-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .area-card {
            padding: 17px;
            border: 1px solid var(--border);
            border-radius: 11px;
            transition: border-color .15s;
        }

        .area-card:hover { border-color: #77b996; }

        .area-card h3 {
            margin: 0 0 8px;
            font-size: 15px;
            overflow-wrap: anywhere;
        }

        .area-location {
            margin-bottom: 15px;
            color: var(--muted);
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        .area-numbers {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-bottom: 15px;
        }

        .mini-stat {
            padding: 6px 8px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #475569;
            font-size: 11px;
        }

        .mini-stat.available { background: #dcfce7; color: #166534; }
        .mini-stat.overdue { background: #fee2e2; color: #991b1b; }

        .area-open {
            display: inline-block;
            color: var(--green);
            font-size: 12px;
            font-weight: 800;
        }

        .empty {
            padding: 24px 10px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
            text-align: center;
        }

        .activity-item {
            padding: 13px 0;
            border-bottom: 1px solid #edf1f5;
        }

        .activity-item:last-child { border-bottom: 0; }

        .activity-name {
            font-size: 13px;
            font-weight: 700;
        }

        .activity-meta {
            margin-top: 5px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.5;
        }

        .alert {
            padding: 13px 15px;
            margin-bottom: 18px;
            border-radius: 9px;
            font-size: 13px;
            line-height: 1.5;
        }

        .alert.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .area-form {
            display: grid;
            gap: 13px;
        }

        .area-form label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 700;
        }

        .area-form input,
        .area-form textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #d5dde7;
            border-radius: 8px;
            font: inherit;
            font-size: 13px;
        }

        .area-form textarea {
            min-height: 75px;
            resize: vertical;
        }

        .notice {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #e3eaf2;
            border-radius: 10px;
            background: #f8fafc;
            color: #64748b;
            font-size: 12px;
            line-height: 1.7;
        }

        @media (max-width: 1250px) {
            .stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .content-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 760px) {
            .sidebar {
                position: static;
                width: auto;
                min-height: auto;
                padding: 16px;
            }

            .brand { padding-bottom: 12px; }
            .nav-label { padding-top: 8px; }
            .sidebar-bottom { margin-top: 12px; }
            .main { margin-left: 0; padding: 18px; }
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .area-list { grid-template-columns: 1fr; }
            .topbar { align-items: flex-start; }
            .date { max-width: 120px; line-height: 1.5; }
        }
    </style>
</head>
<body>
<div class="layout">

    <aside class="sidebar">
        <div class="brand">LOT<span>MAP</span></div>

        <div class="nav-label">Main Menu</div>
        <a class="nav-link active" href="dashboard.php">Dashboard</a>
        <a class="nav-link" href="map.php">Lot Mapping</a>

        <div class="nav-label">Management</div>
        <?php if ($role === 'admin'): ?>
            <a class="nav-link" href="#map-areas">Map Areas</a>
            <a class="nav-link" href="#create-area">Add Map Area</a>
        <?php endif; ?>

        <div class="sidebar-bottom">
            <div class="user-name"><?= h($fullName) ?></div>
            <div class="user-role"><?= h($role) ?></div>

            <form method="POST" action="logout.php">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= h($_SESSION['logout_csrf']) ?>"
                >
                <button class="logout-button" type="submit">
                    Logout
                </button>
            </form>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <div class="eyebrow">Residential Lot Management</div>
                <h1>Dashboard</h1>
            </div>
            <div class="date">
                <?= h(date('F j, Y')) ?><br>
                Signed in as <?= h($role) ?>
            </div>
        </header>

        <?php if ($error !== ''): ?>
            <div class="alert error"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert success"><?= h($success) ?></div>
        <?php endif; ?>

        <section class="welcome">
            <h2>Welcome back, <?= h($fullName) ?>!</h2>
            <p>
                Manage residential map areas, lot availability, ownership
                records and installment schedules from one place.
            </p>
        </section>

        <section class="stats">
            <div class="stat-card">
                <div class="stat-label">Total Lots</div>
                <div class="stat-value">
                    <?= (int) ($stats['total_lots'] ?? 0) ?>
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-label">Available Lots</div>
                <div class="stat-value">
                    <?= (int) ($stats['available_lots'] ?? 0) ?>
                </div>
            </div>

            <div class="stat-card yellow">
                <div class="stat-label">Spot Cash</div>
                <div class="stat-value">
                    <?= (int) ($stats['spot_cash_lots'] ?? 0) ?>
                </div>
            </div>

            <div class="stat-card blue">
                <div class="stat-label">Current Installments</div>
                <div class="stat-value">
                    <?= (int) ($stats['current_installments'] ?? 0) ?>
                </div>
            </div>

            <div class="stat-card red">
                <div class="stat-label">Overdue Lots</div>
                <div class="stat-value">
                    <?= (int) ($stats['overdue_lots'] ?? 0) ?>
                </div>
            </div>

            <div class="stat-card black">
                <div class="stat-label">Forfeiture Review</div>
                <div class="stat-value">
                    <?= (int) ($stats['forfeiture_lots'] ?? 0) ?>
                </div>
            </div>
        </section>

        <div class="content-grid">
            <section class="panel" id="map-areas">
                <div class="panel-header">
                    <h2>Residential Map Areas</h2>
                    <?php if ($role === 'admin'): ?>
                        <a class="button" href="maps/upload.php">+ Add Area</a>
                    <?php endif; ?>
                </div>

                <div class="panel-body">
                    <?php if (!$areas): ?>
                        <div class="empty">
                            No map areas found.<br>
                            <?php if ($role === 'admin'): ?>
                                Use the Add Map Area form to create your first area.
                            <?php else: ?>
                                Please ask an administrator to create a map area.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="area-list">
                            <?php foreach ($areas as $area): ?>
                                <article class="area-card">
                                    <h3><?= h($area['area_name']) ?></h3>

                                    <div class="area-location">
                                        <?= h($area['location'] ?: 'No location specified') ?>
                                    </div>

                                    <div class="area-numbers">
                                        <span class="mini-stat">
                                            Total: <?= (int) $area['total_lots'] ?>
                                        </span>
                                        <span class="mini-stat available">
                                            Available: <?= (int) $area['available_lots'] ?>
                                        </span>
                                        <span class="mini-stat">
                                            Spot cash: <?= (int) $area['spot_cash_lots'] ?>
                                        </span>
                                        <span class="mini-stat">
                                            Installment: <?= (int) $area['current_installments'] ?>
                                        </span>
                                        <span class="mini-stat overdue">
                                            Overdue: <?= (int) $area['overdue_lots'] ?>
                                        </span>
                                    </div>

                                    <?php if (!empty($area['description'])): ?>
                                        <p class="area-location">
                                            <?= h($area['description']) ?>
                                        </p>
                                    <?php endif; ?>

                                    <a
                                        class="area-open"
                                        href="map.php?area_id=<?= (int) $area['id'] ?>"
                                    >
                                        Open map area &rarr;
                                    </a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <div>
                <?php if ($role === 'admin'): ?>
                    <section class="panel" id="create-area">
                        <div class="panel-header">
                            <h2>Create Map Area</h2>
                        </div>

                        <div class="panel-body">
                            <form class="area-form" method="POST" action="dashboard.php#create-area">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= h($_SESSION['logout_csrf']) ?>"
                                >

                                <label for="area_name">Area Name *</label>
                                <input
                                    id="area_name"
                                    name="area_name"
                                    maxlength="180"
                                    required
                                    placeholder="Enter residential area name"
                                >

                                <label for="location">Location</label>
                                <input
                                    id="location"
                                    name="location"
                                    maxlength="255"
                                    placeholder="City or barangay"
                                >

                                <label for="description">Description</label>
                                <textarea
                                    id="description"
                                    name="description"
                                    placeholder="Optional area details"
                                ></textarea>

                                <button
                                    class="button"
                                    type="submit"
                                    name="create_area"
                                    value="1"
                                >
                                    Create Map Area
                                </button>
                            </form>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="panel" style="margin-top: 20px;">
                    <div class="panel-header">
                        <h2>Recent Activity</h2>
                    </div>

                    <div class="panel-body">
                        <?php if (!$activities): ?>
                            <div class="empty">No recorded activity yet.</div>
                        <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-name">
                                        <?= h(activityLabel((string) $activity['action'])) ?>
                                    </div>
                                    <div class="activity-meta">
                                        <?= h($activity['full_name'] ?: 'Unknown user') ?>
                                        <?php if (!empty($activity['entity_type'])): ?>
                                            &middot; <?= h($activity['entity_type']) ?>
                                        <?php endif; ?>
                                        <br>
                                        <?= h($activity['created_at']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>

        <div class="notice">
            <strong>Reminder:</strong> Forfeiture review is a warning for
            administrator review, not an automatic ownership transfer.
            Preserve past ownership and payment records when handling a backout.
        </div>
    </main>
</div>
</body>
</html>