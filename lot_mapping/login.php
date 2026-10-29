<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

// Redirect if already logged in.
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (
        !hash_equals(
            (string)$_SESSION['login_csrf'],
            $submittedToken
        )
    ) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, full_name, username, password_hash, role, is_active
                 FROM users
                 WHERE username = :username
                 LIMIT 1'
            );

            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if (
                $user &&
                (int)$user['is_active'] === 1 &&
                password_verify($password, $user['password_hash'])
            ) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                unset($_SESSION['login_csrf']);

                // Record successful login.
                $log = $pdo->prepare(
                    'INSERT INTO activity_logs
                        (user_id, action, entity_type, entity_id, ip_address)
                     VALUES
                        (:user_id, :action, :entity_type, :entity_id, :ip)'
                );

                $log->execute([
                    'user_id' => (int)$user['id'],
                    'action' => 'login',
                    'entity_type' => 'user',
                    'entity_id' => (int)$user['id'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);

                header('Location: dashboard.php');
                exit;
            }

            // Use a generic message to avoid revealing account details.
            $error = 'Invalid username or password, or the account is inactive.';
        } catch (PDOException $e) {
            error_log('LOTMAP login error: ' . $e->getMessage());
            $error = 'Unable to sign in right now. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In | LOTMAP</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Arial, Helvetica, sans-serif;
            color: #172033;
            background: #eef2f7;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 34px;
            background: #fff;
            border: 1px solid #e1e7ef;
            border-radius: 18px;
            box-shadow: 0 16px 45px rgba(24, 39, 75, .09);
        }

        .brand {
            margin: 0 0 8px;
            font-size: 27px;
            font-weight: 800;
            letter-spacing: -.8px;
        }

        .brand span { color: #16834a; }

        .subtitle {
            margin: 0 0 28px;
            color: #697586;
            font-size: 14px;
            line-height: 1.6;
        }

        label {
            display: block;
            margin: 17px 0 8px;
            font-size: 13px;
            font-weight: 700;
        }

        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #d6dde7;
            border-radius: 9px;
            outline: none;
            font-size: 15px;
        }

        input:focus {
            border-color: #16834a;
            box-shadow: 0 0 0 3px rgba(22, 131, 74, .12);
        }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 14px;
            border: 0;
            border-radius: 9px;
            background: #16834a;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        button:hover { background: #116b3b; }

        .error {
            padding: 12px;
            margin: 0 0 16px;
            border: 1px solid #fecaca;
            border-radius: 9px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 13px;
            line-height: 1.5;
        }

        .footer {
            margin-top: 22px;
            text-align: center;
            color: #8a94a4;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <main class="login-card">
        <h1 class="brand">LOT<span>MAP</span></h1>
        <p class="subtitle">
            Residential Lot Mapping and Ownership Management System
        </p>

        <?php if ($error !== ''): ?>
            <div class="error" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    (string)$_SESSION['login_csrf'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <label for="username">Username</label>
            <input
                id="username"
                name="username"
                type="text"
                value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="username"
                maxlength="80"
                required
                autofocus
            >

            <label for="password">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
            >

            <button type="submit">Sign In</button>
        </form>

        <div class="footer">
            LOTMAP &copy; <?= date('Y') ?>. Authorized access only.
        </div>
    </main>
</body>
</html>