
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="map-csrf" content="<?= h($_SESSION['map_csrf'] ?? '') ?>">
    <title>Lot Mapping | LOTMAP</title>
    <link rel="stylesheet" href="assets/css/map.css">
</head>
<body>

<header class="topbar">
    <a class="brand" href="dashboard.php" style="color:white">
        LOT<span>MAP</span>
    </a>

    <nav class="top-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="map.php">Lot Mapping</a>

        <span>
            <?= h($_SESSION['full_name'] ?? 'User') ?>
            (<?= h($role) ?>)
        </span>

        <form method="post" action="logout.php"
              style="display:inline;margin:0">
            <?php
            if (empty($_SESSION['logout_csrf'])) {
                $_SESSION['logout_csrf'] = bin2hex(random_bytes(32));
            }
            ?>
            <input type="hidden" name="csrf_token"
                   value="<?= h($_SESSION['logout_csrf']) ?>">
            <button type="submit">Logout</button>
        </form>
    </nav>
</header>
