<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';

$pdo = $pdo ?? null;

if (!$pdo instanceof PDO) {
    http_response_code(500);
    exit('Database connection failed. Check config/database.php.');
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

/*
 * Allow setup only if no admin account exists.
 * Delete this file after creating the admin.
 */
try {
    $adminCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role = 'admin'"
    )->fetchColumn();

    if ($adminCount > 0) {
        http_response_code(403);
        exit(
            'Admin account already exists. For security, ' .
            'create_admin.php is now disabled.'
        );
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Unable to check the users table. Verify the database schema.');
}

if (empty($_SESSION['create_admin_csrf'])) {
    $_SESSION['create_admin_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals((string) $_SESSION['create_admin_csrf'], $csrf)) {
        $error = 'Invalid security token. Refresh the page and try again.';
    } else {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($fullName === '' || $username === '' || $password === '') {
            $error = 'All fields are required.';
        } elseif (strlen($fullName) > 150) {
            $error = 'Full name cannot exceed 150 characters.';
        } elseif (strlen($username) < 4 || strlen($username) > 80) {
            $error = 'Username must contain 4 to 80 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            $error = 'Username may contain letters, numbers, dots, underscores, and hyphens only.';
        } elseif (strlen($password) < 12) {
            $error = 'Password must be at least 12 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            try {
                // Recheck that no admin has been created.
                $adminCount = (int) $pdo->query(
                    "SELECT COUNT(*) FROM users WHERE role = 'admin'"
                )->fetchColumn();

                if ($adminCount > 0) {
                    http_response_code(403);
                    exit('An admin account already exists. Setup is disabled.');
                }

                $check = $pdo->prepare(
                    'SELECT id FROM users WHERE username = :username LIMIT 1'
                );
                $check->execute(['username' => $username]);

                if ($check->fetch()) {
                    $error = 'That username already exists. Choose another one.';
                } else {
                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $insert = $pdo->prepare(
                        "INSERT INTO users
                            (full_name, username, password_hash, role, is_active)
                         VALUES
                            (:full_name, :username, :password_hash, 'admin', 1)"
                    );

                    $insert->execute([
                        'full_name' => $fullName,
                        'username' => $username,
                        'password_hash' => $passwordHash,
                    ]);

                    unset($_SESSION['create_admin_csrf']);

                    $success = 'Admin account created successfully. Delete create_admin.php from your project now, then log in with your new admin credentials.';
                }
            } catch (PDOException $e) {
                $error = 'Database error while creating the admin. Check the PHP error log for details.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create LOTMAP Admin</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            background: #f1f5f9;
            color: #0f172a;
            font-family: Arial, sans-serif;
        }

        .card {
            width: 100%;
            max-width: 440px;
            padding: 30px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 15px 40px rgba(15, 23, 42, .08);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 26px;
        }

        .subtitle {
            margin: 0 0 24px;
            color: #64748b;
            line-height: 1.5;
        }

        label {
            display: block;
            margin: 16px 0 7px;
            font-size: 14px;
            font-weight: 700;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
        }

        input:focus {
            outline: 2px solid #bfdbfe;
            border-color: #2563eb;
        }

        button {
            width: 100%;
            margin-top: 22px;
            padding: 13px;
            border: 0;
            border-radius: 8px;
            background: #1d4ed8;
            color: white;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        button:hover {
            background: #1e40af;
        }

        .message {
            padding: 12px;
            margin: 16px 0;
            border-radius: 8px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .error {
            color: #991b1b;
            background: #fee2e2;
        }

        .success {
            color: #166534;
            background: #dcfce7;
        }

        .warning {
            margin-top: 18px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
<main class="card">
    <h1>Create Admin</h1>
    <p class="subtitle">
        LOTMAP — Residential Lot Mapping System
    </p>

    <?php if ($error !== ''): ?>
        <div class="message error"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="message success"><?= h($success) ?></div>
    <?php else: ?>
        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= h((string) $_SESSION['create_admin_csrf']) ?>"
            >

            <label for="full_name">Full Name</label>
            <input
                type="text"
                id="full_name"
                name="full_name"
                maxlength="150"
                autocomplete="name"
                required
            >

            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                minlength="4"
                maxlength="80"
                pattern="[a-zA-Z0-9_.-]+"
                autocomplete="username"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                minlength="12"
                autocomplete="new-password"
                required
            >

            <label for="confirm_password">Confirm Password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="12"
                autocomplete="new-password"
                required
            >

            <button type="submit">Create Administrator</button>
        </form>

        <p class="warning">
            Use this page only for initial setup. Once the administrator
            is created, delete create_admin.php for security.
        </p>
    <?php endif; ?>
</main>
</body>
</html>