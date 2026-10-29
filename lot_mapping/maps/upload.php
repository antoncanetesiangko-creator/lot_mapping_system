<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    exit('Database connection failed. Check config/database.php');
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$userId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$userId) {
    http_response_code(401);
    exit('Please log in first.');
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Only administrators can upload residential maps.');
}

if (empty($_SESSION['upload_map_csrf'])) {
    $_SESSION['upload_map_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals((string) $_SESSION['upload_map_csrf'], $token)) {
        $error = 'Invalid security token. Refresh the page.';
    } else {
        $areaName = trim((string) ($_POST['area_name'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($areaName === '' || mb_strlen($areaName) > 150) {
            $error = 'Area name is required and must not exceed 150 characters.';
        } elseif (mb_strlen($location) > 255) {
            $error = 'Location must not exceed 255 characters.';
        } elseif (mb_strlen($description) > 2000) {
            $error = 'Description must not exceed 2,000 characters.';
        } elseif (
            !isset($_FILES['map_image']) ||
            !is_array($_FILES['map_image']) ||
            $_FILES['map_image']['error'] !== UPLOAD_ERR_OK
        ) {
            $error = 'Choose a map image to upload.';
        } else {
            $file = $_FILES['map_image'];
            $maxSize = 10 * 1024 * 1024; // 10 MB

            if ($file['size'] <= 0 || $file['size'] > $maxSize) {
                $error = 'Image must be smaller than 10 MB.';
            } elseif (!is_uploaded_file($file['tmp_name'])) {
                $error = 'Invalid uploaded file.';
            } else {
                $imageInfo = @getimagesize($file['tmp_name']);
                $mime = $imageInfo['mime'] ?? '';

                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                ];

                if (
                    !$imageInfo ||
                    !isset($allowedTypes[$mime])
                ) {
                    $error = 'Only valid JPG, PNG, and WEBP images are allowed.';
                } else {
                    $width = (int) $imageInfo[0];
                    $height = (int) $imageInfo[1];

                    if ($width < 100 || $height < 100) {
                        $error = 'The map image is too small. Use an image at least 100 × 100 pixels.';
                    } elseif ($width > 12000 || $height > 12000) {
                        $error = 'Image dimensions are too large.';
                    } else {
                        $projectRoot = dirname(__DIR__);
                        $uploadDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'uploads';

                        if (
                            !is_dir($uploadDirectory) &&
                            !mkdir($uploadDirectory, 0755, true) &&
                            !is_dir($uploadDirectory)
                        ) {
                            $error = 'Could not create the uploads folder.';
                        } else {
                            /*
                             * Prevent scripts in the uploads directory
                             * from executing on Apache.
                             */
                            $htaccess = $uploadDirectory . DIRECTORY_SEPARATOR . '.htaccess';

                            if (!is_file($htaccess)) {
                                $rules = "Options -Indexes\n"
                                    . "<FilesMatch \"\\.(php[0-9]?|phtml|phar|cgi|pl|asp|aspx|sh)$\">\n"
                                    . "Require all denied\n"
                                    . "</FilesMatch>\n";

                                if (file_put_contents($htaccess, $rules, LOCK_EX) === false) {
                                    $error = 'Could not secure the uploads folder. Check folder permissions.';
                                }
                            }

                            if ($error === '') {
                                $filename = bin2hex(random_bytes(20))
                                    . '.' . $allowedTypes[$mime];

                                $destination = $uploadDirectory
                                    . DIRECTORY_SEPARATOR . $filename;

                                // Relative URL stored in map_areas.
                                $relativePath = 'uploads/' . $filename;

                                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                                    $error = 'Failed to save the uploaded image.';
                                } else {
                                    try {
  
$stmt = $pdo->prepare("
    INSERT INTO map_areas (
        area_name,
        location,
        description,
        map_image_path,
        map_image_width,
        map_image_height,
        created_by,
        is_active
    ) VALUES (
        :area_name,
        :location,
        :description,
        :map_image_path,
        :map_image_width,
        :map_image_height,
        :created_by,
        1
    )
");

$stmt->execute([
    'area_name'        => $areaName,
    'location'         => $location !== '' ? $location : null,
    'description'      => $description !== '' ? $description : null,
    'map_image_path'   => $relativePath,
    'map_image_width'  => $width,
    'map_image_height' => $height,
    'created_by'       => (int) $userId,
]);

$areaId = (int) $pdo->lastInsertId();

header('Location: ../map.php?area_id=' . $areaId);
exit;
                                    } catch (Throwable $e) {
                                        if ($pdo->inTransaction()) {
                                            $pdo->rollBack();
                                        }

                                        if (is_file($destination)) {
                                            unlink($destination);
                                        }

                                        error_log('LOTMAP map upload error: ' . $e->getMessage());
                                        $error = 'The image was uploaded, but the database save failed. Check your map_areas table structure and PHP error log.';
                                    }
                                }
                            }
                        }
                    }
                }
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
    <title>Upload Residential Map | LOTMAP</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            min-height: 100vh;
            background: #f1f5f9;
            color: #0f172a;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 720px;
            margin: 30px auto;
        }

        .panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, .06);
        }

        h1 { margin-top: 0; font-size: 27px; }
        .muted { color: #64748b; line-height: 1.6; }

        label {
            display: block;
            margin: 18px 0 7px;
            font-weight: 700;
            font-size: 14px;
        }

        input[type="text"], textarea, input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            font: inherit;
        }

        textarea { min-height: 100px; resize: vertical; }

        .preview {
            display: none;
            margin-top: 14px;
            max-width: 100%;
            max-height: 380px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            object-fit: contain;
        }

        button, .back {
            display: inline-block;
            border: 0;
            border-radius: 8px;
            padding: 12px 17px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        button {
            margin-top: 22px;
            width: 100%;
            background: #1d4ed8;
            color: #fff;
        }

        .back {
            margin-bottom: 18px;
            background: #e2e8f0;
            color: #0f172a;
        }

        .notice {
            padding: 12px;
            margin: 16px 0;
            border-radius: 8px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .error { background: #fee2e2; color: #991b1b; }

        .hint {
            margin-top: 7px;
            color: #64748b;
            font-size: 12px;
        }
    </style>
</head>
<body>
<main class="container">
    <a class="back" href="../map.php">← Back to LOTMAP</a>

    <section class="panel">
        <h1>Upload Residential Map</h1>
        <p class="muted">
            Upload an actual subdivision or residential area map.
            After saving, you can open the area in the map editor.
        </p>

        <?php if ($error !== ''): ?>
            <div class="notice error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= h((string) $_SESSION['upload_map_csrf']) ?>"
            >

            <label for="area_name">Residential Area Name *</label>
            <input
                type="text"
                id="area_name"
                name="area_name"
                maxlength="150"
                required
                value="<?= h((string) ($_POST['area_name'] ?? '')) ?>"
                placeholder="e.g. Baluan Residential Area"
            >

            <label for="location">Location</label>
            <input
                type="text"
                id="location"
                name="location"
                maxlength="255"
                value="<?= h((string) ($_POST['location'] ?? '')) ?>"
                placeholder="e.g. General Santos City"
            >

            <label for="description">Description</label>
            <textarea
                id="description"
                name="description"
                maxlength="2000"
                placeholder="Optional notes about this area"
            ><?= h((string) ($_POST['description'] ?? '')) ?></textarea>

            <label for="map_image">Map Image *</label>
            <input
                type="file"
                id="map_image"
                name="map_image"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                required
            >
            <div class="hint">JPG, PNG, or WEBP · Maximum 10 MB.</div>

            <img id="preview" class="preview" alt="Selected map preview">

            <button type="submit">Upload and Save Map</button>
        </form>
    </section>
</main>

<script>
'use strict';

const fileInput = document.getElementById('map_image');
const preview = document.getElementById('preview');

fileInput.addEventListener('change', () => {
    const file = fileInput.files && fileInput.files[0];

    if (!file) {
        preview.removeAttribute('src');
        preview.style.display = 'none';
        return;
    }

    const allowed = ['image/jpeg', 'image/png', 'image/webp'];

    if (!allowed.includes(file.type) || file.size > 10 * 1024 * 1024) {
        alert('Choose a JPG, PNG, or WEBP image up to 10 MB.');
        fileInput.value = '';
        preview.removeAttribute('src');
        preview.style.display = 'none';
        return;
    }

    const reader = new FileReader();

    reader.onload = event => {
        preview.src = event.target.result;
        preview.style.display = 'block';
    };

    reader.readAsDataURL(file);
});
</script>
</body>
</html>
