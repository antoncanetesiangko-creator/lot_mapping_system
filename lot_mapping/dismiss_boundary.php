<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'message' => 'POST required.']);
}

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    respond(403, ['ok' => false, 'message' => 'Admin permission required.']);
}

$csrf = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$sessionCsrf = (string) ($_SESSION['map_csrf'] ?? '');

if (
    $sessionCsrf === ''
    || $csrf === ''
    || !hash_equals($sessionCsrf, $csrf)
) {
    respond(403, ['ok' => false, 'message' => 'Invalid CSRF token.']);
}

$input = json_decode(
    (string) file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    respond(400, ['ok' => false, 'message' => 'Invalid JSON.']);
}

$areaId = filter_var($input['area_id'] ?? null, FILTER_VALIDATE_INT);
$candidateId = trim((string) ($input['candidate_id'] ?? ''));

if (!$areaId || $areaId < 1 || $candidateId === '') {
    respond(422, ['ok' => false, 'message' => 'Missing area or candidate ID.']);
}

if (strlen($candidateId) > 200) {
    respond(422, ['ok' => false, 'message' => 'Candidate ID too long.']);
}

try {
    $stmt = $pdo->prepare(
        'SELECT map_image_path FROM map_areas WHERE id = :area_id LIMIT 1'
    );
    $stmt->execute(['area_id' => $areaId]);
    $area = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$area || empty($area['map_image_path'])) {
        respond(404, ['ok' => false, 'message' => 'Map area not found.']);
    }

    $mapImageName = basename(
        str_replace('\\', '/', (string) $area['map_image_path'])
    );

    $detectionKey = hash('sha256', $mapImageName);
    $directory = __DIR__ . '/uploads/detections';
    $jsonPath = $directory . '/' . $detectionKey . '.json';
    $dismissedPath = $directory . '/' . $detectionKey . '.dismissed.json';

    if (!is_file($jsonPath)) {
        respond(404, ['ok' => false, 'message' => 'Detection file not found.']);
    }

    $decoded = json_decode(
        (string) file_get_contents($jsonPath),
        true
    );

    $detectedImageName = is_array($decoded)
        ? basename(str_replace('\\', '/', (string) ($decoded['image'] ?? '')))
        : '';

    if (
        !is_array($decoded)
        || !isset($decoded['polygons'])
        || !is_array($decoded['polygons'])
        || $detectedImageName !== $mapImageName
    ) {
        respond(409, ['ok' => false, 'message' => 'Detection data mismatch.']);
    }

    $candidateExists = false;

    foreach ($decoded['polygons'] as $index => $polygon) {
        if (!is_array($polygon)) {
            continue;
        }

        $id = (string) ($polygon['id'] ?? ('candidate-' . ($index + 1)));

        if (hash_equals($id, $candidateId)) {
            $candidateExists = true;
            break;
        }
    }

    if (!$candidateExists) {
        respond(404, ['ok' => false, 'message' => 'Candidate not found.']);
    }

    $dismissed = [];

    if (is_file($dismissedPath)) {
        $stored = json_decode(
            (string) file_get_contents($dismissedPath),
            true
        );

        if (is_array($stored) && isset($stored['ids']) && is_array($stored['ids'])) {
            $dismissed = array_map('strval', $stored['ids']);
        }
    }

    if (!in_array($candidateId, $dismissed, true)) {
        $dismissed[] = $candidateId;
    }

    $result = file_put_contents(
        $dismissedPath,
        json_encode(
            ['ids' => array_values($dismissed)],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ),
        LOCK_EX
    );

    if ($result === false) {
        respond(500, ['ok' => false, 'message' => 'Could not save dismissal.']);
    }

    respond(200, ['ok' => true, 'message' => 'Candidate dismissed.']);
} catch (Throwable $e) {
    error_log('Dismiss boundary error: ' . $e->getMessage());
    respond(500, ['ok' => false, 'message' => 'Server error.']);
}