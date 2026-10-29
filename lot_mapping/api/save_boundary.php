<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, [
        'ok' => false,
        'error' => 'POST request required.'
    ]);
}

require_once dirname(__DIR__) . '/config/database.php';

$pdo = $pdo ?? null;

if (!$pdo instanceof PDO) {
    respond(500, [
        'ok' => false,
        'error' => 'Database connection unavailable.'
    ]);
}

/*
 * Authentication: the project login must set user_id and role.
 */
$userId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$userId || !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin') {
    respond(403, [
        'ok' => false,
        'error' => 'Administrator login required.'
    ]);
}

/*
 * CSRF: send the same token used by your map.php session.
 */
$submittedToken = (string) (
    $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? $_POST['csrf_token']
    ?? ''
);

$sessionToken = (string) ($_SESSION['map_csrf'] ?? '');

if ($sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)) {
    respond(403, [
        'ok' => false,
        'error' => 'Invalid security token. Refresh the page and try again.'
    ]);
}

/*
 * Read JSON request body, or regular POST fields.
 */
$contentType = strtolower(
    trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])
);

if ($contentType === 'application/json') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw ?: '', true);

    if (!is_array($input)) {
        respond(400, [
            'ok' => false,
            'error' => 'Invalid JSON request.'
        ]);
    }
} else {
    $input = $_POST;
}

$lotId = filter_var(
    $input['lot_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$points = $input['points'] ?? $input['polygon_points'] ?? null;

if (!$lotId || !is_array($points)) {
    respond(422, [
        'ok' => false,
        'error' => 'A valid lot_id and polygon points are required.'
    ]);
}

/*
 * Validate polygon coordinates.
 * Coordinates are normalized from 0 to 1 relative to the map image.
 * Each point must be [x, y].
 */
if (count($points) < 3 || count($points) > 500) {
    respond(422, [
        'ok' => false,
        'error' => 'A boundary must contain between 3 and 500 points.'
    ]);
}

$normalizedPoints = [];

foreach ($points as $point) {
    if (!is_array($point) ||
        !array_key_exists(0, $point) ||
        !array_key_exists(1, $point) ||
        !is_numeric($point[0]) ||
        !is_numeric($point[1])) {
        respond(422, [
            'ok' => false,
            'error' => 'Each point must contain numeric x and y coordinates.'
        ]);
    }

    $x = (float) $point[0];
    $y = (float) $point[1];

    if (!is_finite($x) || !is_finite($y) ||
        $x < 0 || $x > 1 || $y < 0 || $y > 1) {
        respond(422, [
            'ok' => false,
            'error' => 'Coordinates must be between 0 and 1.'
        ]);
    }

    $normalizedPoints[] = [
        round($x, 6),
        round($y, 6)
    ];
}

try {
    $pdo->beginTransaction();

    /*
     * Confirm the lot exists and lock it during the save.
     */
    $lotQuery = $pdo->prepare(
        'SELECT id FROM lots WHERE id = :id FOR UPDATE'
    );
    $lotQuery->execute(['id' => $lotId]);

    if (!$lotQuery->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();

        respond(404, [
            'ok' => false,
            'error' => 'Lot not found.'
        ]);
    }

    /*
     * Update the most recent boundary if one exists.
     * Otherwise create a new boundary.
     * Saved boundaries remain pending until verified by an admin.
     */
    $shapeQuery = $pdo->prepare(
        'SELECT id
         FROM lot_shapes
         WHERE lot_id = :lot_id
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE'
    );
    $shapeQuery->execute(['lot_id' => $lotId]);

    $existingShape = $shapeQuery->fetch(PDO::FETCH_ASSOC);

    $pointsJson = json_encode(
        $normalizedPoints,
        JSON_THROW_ON_ERROR
    );

    if ($existingShape) {
        $save = $pdo->prepare(
            "UPDATE lot_shapes
             SET polygon_points = :points,
                 verification_status = 'pending',
                 verified_by = NULL,
                 verified_at = NULL
             WHERE id = :id"
        );

        $save->execute([
            'points' => $pointsJson,
            'id' => $existingShape['id']
        ]);

        $shapeId = (int) $existingShape['id'];
    } else {
        $save = $pdo->prepare(
            "INSERT INTO lot_shapes
                (lot_id, polygon_points, verification_status, created_by)
             VALUES
                (:lot_id, :points, 'pending', :created_by)"
        );

        $save->execute([
            'lot_id' => $lotId,
            'points' => $pointsJson,
            'created_by' => $userId
        ]);

        $shapeId = (int) $pdo->lastInsertId();
    }

    $pdo->commit();

    respond(200, [
        'ok' => true,
        'message' => 'Boundary saved and marked pending verification.',
        'shape_id' => $shapeId,
        'lot_id' => (int) $lotId,
        'verification_status' => 'pending'
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('LOTMAP save_boundary error: ' . $e->getMessage());

    respond(500, [
        'ok' => false,
        'error' => 'Unable to save boundary. Check the PHP error log and lot_shapes schema.'
    ]);
}
