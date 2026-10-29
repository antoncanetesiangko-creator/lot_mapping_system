<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role = (string) ($_SESSION['role'] ?? 'viewer');
$canManage = $role === 'admin';
$canRecordPayment = in_array($role, ['admin', 'staff'], true);

if (empty($_SESSION['map_csrf'])) {
    $_SESSION['map_csrf'] = bin2hex(random_bytes(32));
}

function h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function goToArea(int $areaId, string $message): never
{
    header('Location: map.php?area_id=' . $areaId . '&message=' . urlencode($message));
    exit;
}

function logActivity(
    PDO $pdo,
    int $userId,
    string $action,
    string $entityType,
    int $entityId,
    array $details = []
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO activity_logs
            (user_id, action, entity_type, entity_id, details, ip_address)
         VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip)'
    );

    $stmt->execute([
        'user_id' => $userId,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'details' => $details
            ? json_encode($details, JSON_UNESCAPED_UNICODE)
            : null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function createMonthlyDueDates(string $firstDueDate, int $term): array{
    $first = new DateTimeImmutable($firstDueDate);
    $dates = [];

    // Keep the original day-of-month where possible. If the target month
    // is shorter, use that month's last day.
    $day = (int) $first->format('d');
    $year = (int) $first->format('Y');
    $month = (int) $first->format('m');

    for ($i = 0; $i < $term; $i++) {
        $monthIndex = ($year * 12 + ($month - 1)) + $i;
        $targetYear = intdiv($monthIndex, 12);
        $targetMonth = ($monthIndex % 12) + 1;

        $lastDay = cal_days_in_month(CAL_GREGORIAN, $targetMonth, $targetYear);
        $targetDay = min($day, $lastDay);

        $dates[] = sprintf(
            '%04d-%02d-%02d',
            $targetYear,
            $targetMonth,
            $targetDay
        );
    }

    return $dates;
}

$areaId = filter_input(INPUT_GET, 'area_id', FILTER_VALIDATE_INT);
$areaId = $areaId && $areaId > 0 ? (int) $areaId : 0;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals((string) $_SESSION['map_csrf'], $token)) {
        $error = 'Invalid request. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $postedAreaId = filter_var(
            $_POST['area_id'] ?? null,
            FILTER_VALIDATE_INT
        );
        $postedAreaId = $postedAreaId && $postedAreaId > 0
            ? (int) $postedAreaId
            : 0;

        if ($postedAreaId > 0) {
            $areaId = $postedAreaId;
        }

        try {
            if ($action === 'create_lot') {
                if (!$canManage) {
                    throw new RuntimeException('Only an administrator can add lots.');
                }

                $block = trim((string) ($_POST['block_number'] ?? ''));
                $lotNumber = trim((string) ($_POST['lot_number'] ?? ''));
                $sqmInput = trim((string) ($_POST['lot_area_sqm'] ?? ''));
                $priceInput = trim((string) ($_POST['price'] ?? ''));
                $notes = trim((string) ($_POST['notes'] ?? ''));

                if ($areaId < 1 || $block === '' || $lotNumber === '') {
                    throw new RuntimeException('Choose an area and enter both block and lot numbers.');
                }

                $sqm = $sqmInput === '' ? null : filter_var($sqmInput, FILTER_VALIDATE_FLOAT);
                $price = $priceInput === '' ? null : filter_var($priceInput, FILTER_VALIDATE_FLOAT);

                if ($sqmInput !== '' && ($sqm === false || $sqm <= 0)) {
                    throw new RuntimeException('Lot area must be a positive number.');
                }
                if ($priceInput !== '' && ($price === false || $price < 0)) {
                    throw new RuntimeException('Price must be zero or a positive number.');
                }

                $check = $pdo->prepare(
                    'SELECT id FROM map_areas WHERE id = :id AND is_active = 1'
                );
                $check->execute(['id' => $areaId]);

                if (!$check->fetch()) {
                    throw new RuntimeException('The selected map area does not exist or is inactive.');
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO lots
                        (map_area_id, block_number, lot_number, lot_area_sqm,
                         price, notes, created_by)
                     VALUES
                        (:area_id, :block, :lot_number, :sqm, :price, :notes, :user_id)'
                );
                $stmt->execute([
                    'area_id' => $areaId,
                    'block' => $block,
                    'lot_number' => $lotNumber,
                    'sqm' => $sqm,
                    'price' => $price,
                    'notes' => $notes !== '' ? $notes : null,
                    'user_id' => $userId,
                ]);

                $lotId = (int) $pdo->lastInsertId();
                logActivity($pdo, $userId, 'create_lot', 'lot', $lotId, [
                    'block_number' => $block,
                    'lot_number' => $lotNumber,
                    'map_area_id' => $areaId,
                ]);

                goToArea($areaId, 'Lot created successfully.');
            }

/* HOLD LOT — admin only */
if (($_POST['action'] ?? '') === 'hold_lot') {
    if (!$canManage) {
        http_response_code(403);
        exit('Admin permission required.');
    }

    $lotId = filter_input(INPUT_POST, 'lot_id', FILTER_VALIDATE_INT);
    $areaId = (int)($_POST['area_id'] ?? 0);
    $holdReason = trim((string)($_POST['hold_reason'] ?? ''));

    if (!$lotId || $lotId < 1 || $areaId < 1) {
        goToArea($areaId, 'Invalid lot or area.');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT id, status, notes
             FROM lots
             WHERE id = :lot_id
               AND map_area_id = :area_id
             FOR UPDATE"
        );
        $stmt->execute([
            'lot_id' => $lotId,
            'area_id' => $areaId,
        ]);

        $lot = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lot) {
            throw new RuntimeException('Lot not found in this area.');
        }

        if ($lot['status'] === 'held') {
            throw new RuntimeException('This lot is already held.');
        }

        $stmt = $pdo->prepare(
            "UPDATE lots
             SET status = 'held'
             WHERE id = :lot_id
               AND map_area_id = :area_id"
        );
        $stmt->execute([
            'lot_id' => $lotId,
            'area_id' => $areaId,
        ]);

        logActivity($pdo, $userId, 'hold_lot', 'lot', $lotId, [
            'previous_status' => $lot['status'],
            'reason' => $holdReason !== '' ? $holdReason : null,
        ]);

        $pdo->commit();
        goToArea($areaId, 'Lot placed on hold.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Hold lot failed: ' . $e->getMessage());
        goToArea($areaId, 'Unable to hold lot. Check the error log.');
    }
}


/* RELEASE HOLD — admin only */
if (($_POST['action'] ?? '') === 'release_hold') {
    if (!$canManage) {
        http_response_code(403);
        exit('Admin permission required.');
    }

    $lotId = filter_input(INPUT_POST, 'lot_id', FILTER_VALIDATE_INT);
    $areaId = (int)($_POST['area_id'] ?? 0);

    if (!$lotId || $lotId < 1 || $areaId < 1) {
        goToArea($areaId, 'Invalid lot or area.');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT id, status
             FROM lots
             WHERE id = :lot_id
               AND map_area_id = :area_id
             FOR UPDATE"
        );
        $stmt->execute([
            'lot_id' => $lotId,
            'area_id' => $areaId,
        ]);

        $lot = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lot || $lot['status'] !== 'held') {
            throw new RuntimeException('This lot is not currently held.');
        }

        $stmt = $pdo->prepare(
            "SELECT oh.ownership_type
             FROM ownership_history oh
             WHERE oh.lot_id = :lot_id
               AND oh.ownership_status = 'active'
             ORDER BY oh.start_date DESC, oh.id DESC
             LIMIT 1"
        );
        $stmt->execute(['lot_id' => $lotId]);
        $ownership = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($ownership === null) {
            $newStatus = 'available';
        } elseif ($ownership['ownership_type'] === 'spot_cash') {
            $newStatus = 'spot_cash';
        } else {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) 
                 FROM installment_payments ip
                 INNER JOIN installments ins
                    ON ins.id = ip.installment_id
                 INNER JOIN ownership_history oh
                    ON oh.id = ins.ownership_id
                 WHERE oh.lot_id = :lot_id
                   AND oh.ownership_status = 'active'
                   AND ip.payment_date IS NULL
                   AND ip.due_date < CURDATE()"
            );
            $stmt->execute(['lot_id' => $lotId]);

            $newStatus = (int)$stmt->fetchColumn() > 0
                ? 'overdue'
                : 'installment_current';
        }

        $stmt = $pdo->prepare(
            "UPDATE lots
             SET status = :status
             WHERE id = :lot_id
               AND map_area_id = :area_id"
        );
        $stmt->execute([
            'status' => $newStatus,
            'lot_id' => $lotId,
            'area_id' => $areaId,
        ]);

        logActivity($pdo, $userId, 'release_hold', 'lot', $lotId, [
            'previous_status' => 'held',
            'new_status' => $newStatus,
        ]);

        $pdo->commit();
        goToArea($areaId, 'Hold released. Lot status: ' . $newStatus);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Release hold failed: ' . $e->getMessage());
        goToArea($areaId, 'Unable to release hold. Check the error log.');
    }
}
            if ($action === 'assign_owner') {
                if (!$canManage) {
                    throw new RuntimeException('Only an administrator can assign ownership.');
                }

                $lotId = filter_var($_POST['lot_id'] ?? null, FILTER_VALIDATE_INT);
                $clientId = filter_var($_POST['client_id'] ?? null, FILTER_VALIDATE_INT);
                $firstName = trim((string) ($_POST['first_name'] ?? ''));
                $middleName = trim((string) ($_POST['middle_name'] ?? ''));
                $lastName = trim((string) ($_POST['last_name'] ?? ''));
                $suffix = trim((string) ($_POST['suffix'] ?? ''));
                $contact = trim((string) ($_POST['contact_number'] ?? ''));
                $email = trim((string) ($_POST['email'] ?? ''));
                $address = trim((string) ($_POST['address'] ?? ''));
                $type = (string) ($_POST['ownership_type'] ?? '');
                $startDate = (string) ($_POST['start_date'] ?? '');
                $firstDueDate = (string) ($_POST['first_due_date'] ?? '');
                $termInput = filter_var($_POST['term_months'] ?? null, FILTER_VALIDATE_INT);

                if (!$lotId || $lotId < 1) {
                    throw new RuntimeException('Invalid lot selected.');
                }
                if (!in_array($type, ['spot_cash', 'installment'], true)) {
                    throw new RuntimeException('Choose spot cash or installment.');
                }
                if ($startDate === '') {
                    throw new RuntimeException('Ownership start date is required.');
                }

                $dateCheck = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
                if (!$dateCheck || $dateCheck->format('Y-m-d') !== $startDate) {
                    throw new RuntimeException('Invalid ownership start date.');
                }

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'SELECT id, status FROM lots
                     WHERE id = :lot_id AND map_area_id = :area_id
                     FOR UPDATE'
                );
                $stmt->execute(['lot_id' => $lotId, 'area_id' => $areaId]);
                $lot = $stmt->fetch();

                if (!$lot) {
                    throw new RuntimeException('Lot not found in this map area.');
                }
                if ($lot['status'] !== 'available') {
                    throw new RuntimeException('Only available lots can be assigned to a new owner.');
                }

                $active = $pdo->prepare(
                    "SELECT id FROM ownership_history
                     WHERE lot_id = :lot_id AND ownership_status = 'active'
                     LIMIT 1"
                );
                $active->execute(['lot_id' => $lotId]);

                if ($active->fetch()) {
                    throw new RuntimeException('This lot already has an active ownership record.');
                }

                if ($clientId && $clientId > 0) {
                    $clientCheck = $pdo->prepare(
                        'SELECT id FROM clients WHERE id = :id'
                    );
                    $clientCheck->execute(['id' => $clientId]);

                    if (!$clientCheck->fetch()) {
                        throw new RuntimeException('Selected client does not exist.');
                    }
                } else {
                    if ($firstName === '' || $lastName === '') {
                        throw new RuntimeException('Enter the new client first and last name, or select an existing client.');
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO clients
                            (first_name, middle_name, last_name, suffix,
                             contact_number, email, address, created_by)
                         VALUES
                            (:first_name, :middle_name, :last_name, :suffix,
                             :contact, :email, :address, :user_id)'
                    );
                    $stmt->execute([
                        'first_name' => $firstName,
                        'middle_name' => $middleName !== '' ? $middleName : null,
                        'last_name' => $lastName,
                        'suffix' => $suffix !== '' ? $suffix : null,
                        'contact' => $contact !== '' ? $contact : null,
                        'email' => $email !== '' ? $email : null,
                        'address' => $address !== '' ? $address : null,
                        'user_id' => $userId,
                    ]);

                    $clientId = (int) $pdo->lastInsertId();
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO ownership_history
                        (lot_id, client_id, ownership_type, start_date,
                         ownership_status, recorded_by)
                     VALUES
                        (:lot_id, :client_id, :type, :start_date, :status, :user_id)'
                );
                $stmt->execute([
                    'lot_id' => $lotId,
                    'client_id' => $clientId,
                    'type' => $type,
                    'start_date' => $startDate,
                    'status' => 'active',
                    'user_id' => $userId,
                ]);

                $ownershipId = (int) $pdo->lastInsertId();

                if ($type === 'installment') {
                    $term = $termInput === false ? 0 : (int) $termInput;

                    if ($term < 1 || $term > 600) {
                        throw new RuntimeException('Installment term must be between 1 and 600 months.');
                    }

                    $dueDateCheck = DateTimeImmutable::createFromFormat('!Y-m-d', $firstDueDate);
                    if (
                        !$dueDateCheck ||
                        $dueDateCheck->format('Y-m-d') !== $firstDueDate
                    ) {
                        throw new RuntimeException('Enter a valid first installment due date.');
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO installments
                            (ownership_id, first_due_date, term_months, created_by)
                         VALUES (:ownership_id, :first_due_date, :term, :user_id)'
                    );
                    $stmt->execute([
                        'ownership_id' => $ownershipId,
                        'first_due_date' => $firstDueDate,
                        'term' => $term,
                        'user_id' => $userId,
                    ]);
                    $installmentId = (int) $pdo->lastInsertId();

                    $paymentStmt = $pdo->prepare(
                        'INSERT INTO installment_payments
                            (installment_id, installment_number, due_date)
                         VALUES (:installment_id, :number, :due_date)'
                    );

                    foreach (createMonthlyDueDates($firstDueDate, $term) as $index => $dueDate) {
                        $paymentStmt->execute([
                            'installment_id' => $installmentId,
                            'number' => $index + 1,
                            'due_date' => $dueDate,
                        ]);
                    }
                }

                $newStatus = $type === 'spot_cash' ? 'spot_cash' : 'installment_current';
                $stmt = $pdo->prepare(
                    'UPDATE lots SET status = :status WHERE id = :id'
                );
                $stmt->execute(['status' => $newStatus, 'id' => $lotId]);

                logActivity($pdo, $userId, 'assign_owner', 'ownership', $ownershipId, [
                    'lot_id' => $lotId,
                    'client_id' => $clientId,
                    'ownership_type' => $type,
                ]);

                $pdo->commit();
                goToArea($areaId, 'Owner assigned successfully.');
            }

            if ($action === 'record_payment') {
                if (!$canRecordPayment) {
                    throw new RuntimeException('You do not have permission to record payments.');
                }

                $paymentId = filter_var($_POST['payment_id'] ?? null, FILTER_VALIDATE_INT);
                $paymentDate = (string) ($_POST['payment_date'] ?? '');

                $dateCheck = DateTimeImmutable::createFromFormat('!Y-m-d', $paymentDate);
                if (
                    !$paymentId || $paymentId < 1 ||
                    !$dateCheck || $dateCheck->format('Y-m-d') !== $paymentDate
                ) {
                    throw new RuntimeException('Select a valid installment and payment date.');
                }

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "SELECT ip.id, ip.payment_date, ip.installment_number,
                            l.id AS lot_id
                     FROM installment_payments ip
                     JOIN installments ins ON ins.id = ip.installment_id
                     JOIN ownership_history oh ON oh.id = ins.ownership_id
                     JOIN lots l ON l.id = oh.lot_id
                     WHERE ip.id = :payment_id
                       AND l.map_area_id = :area_id
                       AND oh.ownership_status = 'active'
                     FOR UPDATE"
                );
                $stmt->execute(['payment_id' => $paymentId, 'area_id' => $areaId]);
                $payment = $stmt->fetch();

                if (!$payment) {
                    throw new RuntimeException('Payment schedule not found for an active owner in this area.');
                }
                if ($payment['payment_date'] !== null) {
                    throw new RuntimeException('This installment already has a payment date.');
                }

                $stmt = $pdo->prepare(
                    'UPDATE installment_payments
                     SET payment_date = :payment_date, recorded_by = :user_id
                     WHERE id = :id AND payment_date IS NULL'
                );
                $stmt->execute([
                    'payment_date' => $paymentDate,
                    'user_id' => $userId,
                    'id' => $paymentId,
                ]);

                logActivity($pdo, $userId, 'record_payment', 'installment_payment', $paymentId, [
                    'payment_date' => $paymentDate,
                    'installment_number' => (int) $payment['installment_number'],
                ]);

                // Refresh lot status from its active installment schedule.
                $statusStmt = $pdo->prepare(
                    "SELECT
                        COUNT(*) AS unpaid_overdue,
                        MIN(ip.due_date) AS earliest_unpaid_due
                     FROM installment_payments ip
                     JOIN installments ins ON ins.id = ip.installment_id
                     JOIN ownership_history oh ON oh.id = ins.ownership_id
                     WHERE oh.lot_id = :lot_id
                       AND oh.ownership_status = 'active'
                       AND ip.payment_date IS NULL
                       AND ip.due_date < CURDATE()"
                );
                $statusStmt->execute(['lot_id' => (int) $payment['lot_id']]);
                $dueInfo = $statusStmt->fetch();

                $newStatus = ((int) ($dueInfo['unpaid_overdue'] ?? 0) > 0)
                    ? 'overdue'
                    : 'installment_current';

                $stmt = $pdo->prepare(
                    'UPDATE lots SET status = :status WHERE id = :lot_id'
                );
                $stmt->execute([
                    'status' => $newStatus,
                    'lot_id' => (int) $payment['lot_id'],
                ]);

                $pdo->commit();
                goToArea($areaId, 'Payment date recorded.');
            }

if ($action === 'edit_payment_date') {
    if (!$canRecordPayment) {
        throw new RuntimeException(
            'You do not have permission to edit payment dates.'
        );
    }

    $paymentId = filter_var(
        $_POST['payment_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $paymentDate = (string) ($_POST['payment_date'] ?? '');

    $dateCheck = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $paymentDate
    );

    if (
        !$paymentId || $paymentId < 1 ||
        !$dateCheck ||
        $dateCheck->format('Y-m-d') !== $paymentDate
    ) {
        throw new RuntimeException(
            'Select a valid payment date.'
        );
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT
            ip.id,
            ip.payment_date,
            ip.installment_number,
            l.id AS lot_id
         FROM installment_payments ip
         INNER JOIN installments ins
            ON ins.id = ip.installment_id
         INNER JOIN ownership_history oh
            ON oh.id = ins.ownership_id
         INNER JOIN lots l
            ON l.id = oh.lot_id
         WHERE ip.id = :payment_id
           AND l.map_area_id = :area_id
           AND oh.ownership_status = 'active'
         FOR UPDATE"
    );

    $stmt->execute([
        'payment_id' => $paymentId,
        'area_id' => $areaId,
    ]);

    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new RuntimeException(
            'Payment schedule not found for an active owner in this area.'
        );
    }

    if ($payment['payment_date'] === null) {
        throw new RuntimeException(
            'This installment has no recorded payment date to edit.'
        );
    }

    $oldPaymentDate = $payment['payment_date'];

    $stmt = $pdo->prepare(
        "UPDATE installment_payments
         SET payment_date = :payment_date,
             recorded_by = :user_id
         WHERE id = :id
           AND payment_date IS NOT NULL"
    );

    $stmt->execute([
        'payment_date' => $paymentDate,
        'user_id' => $userId,
        'id' => $paymentId,
    ]);

    logActivity(
        $pdo,
        $userId,
        'edit_payment_date',
        'installment_payment',
        $paymentId,
        [
            'old_payment_date' => $oldPaymentDate,
            'new_payment_date' => $paymentDate,
            'installment_number' =>
                (int) $payment['installment_number'],
        ]
    );

    // Recalculate the lot status after editing the date.
    $statusStmt = $pdo->prepare(
        "SELECT COUNT(*) AS unpaid_overdue
         FROM installment_payments ip
         INNER JOIN installments ins
            ON ins.id = ip.installment_id
         INNER JOIN ownership_history oh
            ON oh.id = ins.ownership_id
         WHERE oh.lot_id = :lot_id
           AND oh.ownership_status = 'active'
           AND ip.payment_date IS NULL
           AND ip.due_date < CURDATE()"
    );

    $statusStmt->execute([
        'lot_id' => (int) $payment['lot_id'],
    ]);

    $dueInfo = $statusStmt->fetch(PDO::FETCH_ASSOC);

    $newStatus = (
        (int) ($dueInfo['unpaid_overdue'] ?? 0) > 0
    ) ? 'overdue' : 'installment_current';

    $stmt = $pdo->prepare(
        'UPDATE lots
         SET status = :status
         WHERE id = :lot_id'
    );

    $stmt->execute([
        'status' => $newStatus,
        'lot_id' => (int) $payment['lot_id'],
    ]);

    $pdo->commit();

    goToArea(
        $areaId,
        'Payment date updated successfully.'
    );
}

if ($action === 'cancel_payment_date') {
    if (!$canRecordPayment) {
        throw new RuntimeException(
            'You do not have permission to cancel a payment date.'
        );
    }

    $paymentId = filter_var(
        $_POST['payment_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$paymentId || $paymentId < 1) {
        throw new RuntimeException('Invalid installment payment.');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT
            ip.id,
            ip.payment_date,
            ip.installment_number,
            l.id AS lot_id
         FROM installment_payments ip
         INNER JOIN installments ins
            ON ins.id = ip.installment_id
         INNER JOIN ownership_history oh
            ON oh.id = ins.ownership_id
         INNER JOIN lots l
            ON l.id = oh.lot_id
         WHERE ip.id = :payment_id
           AND l.map_area_id = :area_id
           AND oh.ownership_status = 'active'
         FOR UPDATE"
    );

    $stmt->execute([
        'payment_id' => $paymentId,
        'area_id' => $areaId,
    ]);

    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment || $payment['payment_date'] === null) {
        throw new RuntimeException(
            'Recorded payment not found, or it has already been cancelled.'
        );
    }

    $oldPaymentDate = $payment['payment_date'];

    $stmt = $pdo->prepare(
        "UPDATE installment_payments
         SET payment_date = NULL,
             recorded_by = NULL
         WHERE id = :id
           AND payment_date IS NOT NULL"
    );

    $stmt->execute(['id' => $paymentId]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'The payment date could not be cancelled.'
        );
    }

    logActivity(
        $pdo,
        $userId,
        'cancel_payment_date',
        'installment_payment',
        $paymentId,
        [
            'old_payment_date' => $oldPaymentDate,
            'installment_number' =>
                (int) $payment['installment_number'],
        ]
    );

    // Recalculate the lot status from unpaid overdue installments.
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS unpaid_overdue
         FROM installment_payments ip
         INNER JOIN installments ins
            ON ins.id = ip.installment_id
         INNER JOIN ownership_history oh
            ON oh.id = ins.ownership_id
         WHERE oh.lot_id = :lot_id
           AND oh.ownership_status = 'active'
           AND ip.payment_date IS NULL
           AND ip.due_date < CURDATE()"
    );

    $stmt->execute([
        'lot_id' => (int) $payment['lot_id'],
    ]);

    $dueInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    $newStatus =
        (int) ($dueInfo['unpaid_overdue'] ?? 0) > 0
            ? 'overdue'
            : 'installment_current';

    $stmt = $pdo->prepare(
        'UPDATE lots SET status = :status WHERE id = :lot_id'
    );

    $stmt->execute([
        'status' => $newStatus,
        'lot_id' => (int) $payment['lot_id'],
    ]);

    $pdo->commit();

    goToArea(
        $areaId,
        'Payment date cancelled. The installment is now unpaid.'
    );
}
            if ($action === 'backout') {
                if (!$canManage) {
                    throw new RuntimeException('Only an administrator can process a backout.');
                }

                $lotId = filter_var($_POST['lot_id'] ?? null, FILTER_VALIDATE_INT);
                $backoutDate = (string) ($_POST['backout_date'] ?? '');
                $reason = trim((string) ($_POST['backout_reason'] ?? ''));

                $dateCheck = DateTimeImmutable::createFromFormat('!Y-m-d', $backoutDate);
                if (
                    !$lotId || $lotId < 1 ||
                    !$dateCheck || $dateCheck->format('Y-m-d') !== $backoutDate ||
                    $reason === ''
                ) {
                    throw new RuntimeException('Select a valid backout date and enter a reason.');
                }

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "SELECT oh.id
                     FROM ownership_history oh
                     JOIN lots l ON l.id = oh.lot_id
                     WHERE oh.lot_id = :lot_id
                       AND l.map_area_id = :area_id
                       AND oh.ownership_status = 'active'
                     FOR UPDATE"
                );
                $stmt->execute(['lot_id' => $lotId, 'area_id' => $areaId]);
                $ownership = $stmt->fetch();

                if (!$ownership) {
                    throw new RuntimeException('No active ownership record exists for this lot.');
                }

                $stmt = $pdo->prepare(
                    "UPDATE ownership_history
                     SET ownership_status = 'backed_out',
                         end_date = :end_date,
                         backout_date = :backout_date,
                         backout_reason = :reason
                     WHERE id = :id AND ownership_status = 'active'"
                );
                $stmt->execute([
                    'end_date' => $backoutDate,
                    'backout_date' => $backoutDate,
                    'reason' => $reason,
                    'id' => (int) $ownership['id'],
                ]);

                $stmt = $pdo->prepare(
                    "UPDATE lots SET status = 'available'
                     WHERE id = :lot_id AND map_area_id = :area_id"
                );
                $stmt->execute(['lot_id' => $lotId, 'area_id' => $areaId]);

                logActivity($pdo, $userId, 'backout_ownership', 'ownership',
                    (int) $ownership['id'], [
                        'lot_id' => $lotId,
                        'backout_date' => $backoutDate,
                        'reason' => $reason,
                    ]);

                $pdo->commit();
                goToArea($areaId, 'Backout recorded. Previous ownership and payment records were preserved.');
            }

            
if (in_array($action, ['save_boundary', 'update_boundary'], true)) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        if (!$canManage) {
            throw new RuntimeException(
                'Only an administrator can save lot boundaries.'
            );
        }

        $lotId = filter_var(
            $_POST['lot_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        
        $postedAreaId = filter_var(
            $_POST['area_id'] ?? null,
             FILTER_VALIDATE_INT
        );


        if (!$lotId || $lotId < 1) {
            throw new RuntimeException('Select a valid lot.');
        }

        if (!$postedAreaId || $postedAreaId < 1 || $postedAreaId !== $areaId) {
            throw new RuntimeException('Invalid map area.');
        }

        // Confirm that the selected lot belongs to this map area.
        $stmt = $pdo->prepare(
            'SELECT id
             FROM lots
             WHERE id = :lot_id
               AND map_area_id = :area_id'
        );

        $stmt->execute([
            'lot_id' => $lotId,
            'area_id' => $areaId,
        ]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException(
                'The selected lot does not belong to this map area.'
            );
        }

        // Read the polygon edited in JavaScript.
        $rawPoints = $_POST['polygon_points'] ?? '';

        if (!is_string($rawPoints) || $rawPoints === '') {
            throw new RuntimeException(
                'No boundary points were submitted.'
            );
        }

        try {
            $points = json_decode(
                $rawPoints,
                true,
                16,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new RuntimeException(
                'The boundary coordinates are not valid JSON.'
            );
        }

        if (
            !is_array($points) ||
            !array_is_list($points) ||
            count($points) < 3 ||
            count($points) > 500
        ) {
            throw new RuntimeException(
                'A boundary must contain between 3 and 500 points.'
            );
        }

        
        // Validate normalized coordinates.
        $validatedPoints = [];

        foreach ($points as $point) {
            if (!is_array($point)) {
                throw new RuntimeException(
                    'The boundary contains invalid coordinates.'
                );
            }

            // Accept both [x, y] and {"x": x, "y": y}.
            if (
                array_is_list($point) &&
                count($point) === 2 &&
                isset($point[0], $point[1])
            ) {
                $xRaw = $point[0];
                $yRaw = $point[1];
            } elseif (
                array_key_exists('x', $point) &&
                array_key_exists('y', $point)
            ) {
                $xRaw = $point['x'];
                $yRaw = $point['y'];
            } else {
                throw new RuntimeException(
                    'The boundary contains invalid coordinates.'
                );
            }

            if (!is_numeric($xRaw) || !is_numeric($yRaw)) {
                throw new RuntimeException(
                    'The boundary contains non-numeric coordinates.'
                );
            }

            $x = (float) $xRaw;
            $y = (float) $yRaw;

            if (
                !is_finite($x) ||
                !is_finite($y) ||
                $x < 0 || $x > 1 ||
                $y < 0 || $y > 1
            ) {
                throw new RuntimeException(
                    'Coordinates must be normalized between 0 and 1.'
                );
            }

            // Store every point in the database as [x, y].
            $validatedPoints[] = [$x, $y];
        }

        $polygonJson = json_encode(
            $validatedPoints,
            JSON_THROW_ON_ERROR
        );


        
$pdo->beginTransaction();

if ($action === 'update_boundary') {
    $shapeId = filter_var(
        $_POST['shape_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$shapeId || $shapeId < 1) {
        throw new RuntimeException(
            'Invalid saved boundary ID.'
        );
    }

    // Confirm this saved boundary belongs to the selected lot and area.
    $stmt = $pdo->prepare(
        'SELECT ls.id
         FROM lot_shapes AS ls
         INNER JOIN lots AS l ON l.id = ls.lot_id
         WHERE ls.id = :shape_id
           AND ls.lot_id = :lot_id
           AND l.map_area_id = :area_id
         LIMIT 1'
    );

    $stmt->execute([
        'shape_id' => $shapeId,
        'lot_id' => $lotId,
        'area_id' => $areaId,
    ]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException(
            'The saved boundary does not belong to this lot and map area.'
        );
    }

    // Update the existing record; do not create a duplicate.
    $stmt = $pdo->prepare(
        "UPDATE lot_shapes
         SET polygon_points = :points,
             verification_status = 'pending',
             verified_by = NULL,
             verified_at = NULL
         WHERE id = :shape_id
           AND lot_id = :lot_id"
    );

    $stmt->execute([
        'points' => $polygonJson,
        'shape_id' => $shapeId,
        'lot_id' => $lotId,
    ]);

    logActivity(
        $pdo,
        $userId,
        'update_boundary',
        'lot_shape',
        $shapeId,
        [
            'lot_id' => $lotId,
            'map_area_id' => $areaId,
            'verification_status' => 'pending',
        ]
    );

    $message = 'Existing boundary updated. Verification is pending.';
} else {
    // New boundary: keep the original INSERT behavior.
    $stmt = $pdo->prepare(
        "INSERT INTO lot_shapes
            (lot_id, polygon_points, verification_status, created_by)
         VALUES
            (:lot_id, :points, 'pending', :user_id)"
    );

    $stmt->execute([
        'lot_id' => $lotId,
        'points' => $polygonJson,
        'user_id' => $userId,
    ]);

    $shapeId = (int) $pdo->lastInsertId();

    logActivity(
        $pdo,
        $userId,
        'save_boundary',
        'lot_shape',
        $shapeId,
        [
            'lot_id' => $lotId,
            'map_area_id' => $areaId,
            'verification_status' => 'pending',
        ]
    );

    $message = 'New boundary saved as pending verification.';
}

$pdo->commit();

echo json_encode([
    'ok' => true,
    'message' => $message,
    'shape' => [
        'id' => $shapeId,
        'lot_id' => $lotId,
        'polygon_points' => $validatedPoints,
        'verification_status' => 'pending',
    ],
], JSON_THROW_ON_ERROR);

exit;


    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        http_response_code(
            $e instanceof RuntimeException ? 400 : 500
        );

        if (!($e instanceof RuntimeException)) {
            error_log('LOTMAP save_boundary error: ' . $e->getMessage());
        }

        echo json_encode([
            'ok' => false,
            'error' => $e instanceof RuntimeException
                ? $e->getMessage()
                : 'A server error occurred while saving the boundary.',
        ]);

        exit;
    }
}



if ($action === 'verify_boundary') {
    if (!$canManage) {
        throw new RuntimeException(
            'Only an administrator can verify lot boundaries.'
        );
    }

    $shapeId = filter_var(
        $_POST['shape_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$shapeId || $shapeId < 1 || $areaId < 1) {
        throw new RuntimeException(
            'Invalid boundary or map area.'
        );
    }

    // Verify only a boundary belonging to a lot in this map area.
    $stmt = $pdo->prepare(
        "UPDATE lot_shapes AS ls
         INNER JOIN lots AS l ON l.id = ls.lot_id
         SET ls.verification_status = 'verified',
             ls.verified_by = :user_id,
             ls.verified_at = NOW()
         WHERE ls.id = :shape_id
           AND l.map_area_id = :area_id
           AND ls.verification_status = 'pending'"
    );

    $stmt->execute([
        'user_id' => $userId,
        'shape_id' => $shapeId,
        'area_id' => $areaId,
    ]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Boundary not found in this area, or it has already been verified.'
        );
    }

    logActivity(
        $pdo,
        $userId,
        'verify_boundary',
        'lot_shape',
        (int) $shapeId,
        [
            'map_area_id' => $areaId,
            'verification_status' => 'verified',
        ]
    );

    goToArea(
        $areaId,
        'Boundary verified successfully.'
    );
}

if ($action === 'edit_lot') {
    if (!$canManage) {
        throw new RuntimeException(
            'Only an administrator can edit lot details.'
        );
    }

    $lotId = filter_var(
        $_POST['lot_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $block = trim((string) ($_POST['block_number'] ?? ''));
    $lotNumber = trim((string) ($_POST['lot_number'] ?? ''));
    $sqmInput = trim((string) ($_POST['lot_area_sqm'] ?? ''));
    $priceInput = trim((string) ($_POST['price'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if (!$lotId || $lotId < 1 || $areaId < 1) {
        throw new RuntimeException('Invalid lot or area.');
    }

    if ($block === '' || $lotNumber === '') {
        throw new RuntimeException(
            'Block number and lot number are required.'
        );
    }

    if (mb_strlen($block) > 50 || mb_strlen($lotNumber) > 50) {
        throw new RuntimeException(
            'Block number and lot number must not exceed 50 characters.'
        );
    }

    $sqm = null;
    if ($sqmInput !== '') {
        if (
            !is_numeric($sqmInput) ||
            !is_finite((float) $sqmInput) ||
            (float) $sqmInput <= 0
        ) {
            throw new RuntimeException(
                'Lot area must be a positive number.'
            );
        }
        $sqm = (float) $sqmInput;
    }

    $price = null;
    if ($priceInput !== '') {
        if (
            !is_numeric($priceInput) ||
            !is_finite((float) $priceInput) ||
            (float) $priceInput < 0
        ) {
            throw new RuntimeException(
                'Price must be zero or a positive number.'
            );
        }
        $price = (float) $priceInput;
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM lots
         WHERE id = :lot_id AND map_area_id = :area_id'
    );
    $stmt->execute([
        'lot_id' => $lotId,
        'area_id' => $areaId,
    ]);

    if (!$stmt->fetch()) {
        throw new RuntimeException('Lot not found in this area.');
    }

    // Exclude the current lot so unchanged values are allowed.
    $stmt = $pdo->prepare(
        'SELECT id FROM lots
         WHERE map_area_id = :area_id
           AND block_number = :block
           AND lot_number = :lot_number
           AND id <> :lot_id
         LIMIT 1'
    );
    $stmt->execute([
        'area_id' => $areaId,
        'block' => $block,
        'lot_number' => $lotNumber,
        'lot_id' => $lotId,
    ]);

    if ($stmt->fetch()) {
        throw new RuntimeException(
            'That block number and lot number already exist in this area.'
        );
    }

    $stmt = $pdo->prepare(
        'UPDATE lots
         SET block_number = :block,
             lot_number = :lot_number,
             lot_area_sqm = :sqm,
             price = :price,
             notes = :notes
         WHERE id = :lot_id AND map_area_id = :area_id'
    );

    $stmt->execute([
        'block' => $block,
        'lot_number' => $lotNumber,
        'sqm' => $sqm,
        'price' => $price,
        'notes' => $notes !== '' ? $notes : null,
        'lot_id' => $lotId,
        'area_id' => $areaId,
    ]);

    logActivity($pdo, $userId, 'edit_lot', 'lot', $lotId, [
        'block_number' => $block,
        'lot_number' => $lotNumber,
        'lot_area_sqm' => $sqm,
        'price' => $price,
    ]);

    goToArea($areaId, 'Lot details updated successfully.');
}

if ($action === 'edit_client') {
    if (!$canManage) {
        throw new RuntimeException(
            'Only an administrator can edit client details.'
        );
    }

    $clientId = filter_var(
        $_POST['client_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $middleName = trim((string) ($_POST['middle_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $suffix = trim((string) ($_POST['suffix'] ?? ''));
    $contact = trim((string) ($_POST['contact_number'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));

    if (!$clientId || $clientId < 1) {
        throw new RuntimeException('Invalid client.');
    }

    if ($firstName === '' || $lastName === '') {
        throw new RuntimeException(
            'First name and last name are required.'
        );
    }

    // Allow Unicode letters, spaces, apostrophes, and hyphens.
    $namePattern = "/^[\\p{L}\\p{M}][\\p{L}\\p{M} '\\-]*$/u";

    foreach ([
        'First name' => $firstName,
        'Middle name' => $middleName,
        'Last name' => $lastName,
    ] as $label => $name) {
        if (
            $name !== '' &&
            !preg_match($namePattern, $name)
        ) {
            throw new RuntimeException(
                $label . ' may contain letters, spaces, hyphens, and apostrophes only.'
            );
        }
    }

    if (
        mb_strlen($firstName) > 100 ||
        mb_strlen($middleName) > 100 ||
        mb_strlen($lastName) > 100 ||
        mb_strlen($suffix) > 20
    ) {
        throw new RuntimeException('One or more name fields are too long.');
    }

    if ($contact !== '' && !preg_match('/^[0-9]{11}$/', $contact)) {
        throw new RuntimeException(
            'Contact number must contain exactly 11 digits, or be left blank.'
        );
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
    }

    if (mb_strlen($email) > 190) {
        throw new RuntimeException('Email must not exceed 190 characters.');
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM clients WHERE id = :id'
    );
    $stmt->execute(['id' => $clientId]);

    if (!$stmt->fetch()) {
        throw new RuntimeException('Client not found.');
    }

    $stmt = $pdo->prepare(
        'UPDATE clients
         SET first_name = :first_name,
             middle_name = :middle_name,
             last_name = :last_name,
             suffix = :suffix,
             contact_number = :contact,
             email = :email,
             address = :address
         WHERE id = :id'
    );

    $stmt->execute([
        'first_name' => $firstName,
        'middle_name' => $middleName !== '' ? $middleName : null,
        'last_name' => $lastName,
        'suffix' => $suffix !== '' ? $suffix : null,
        'contact' => $contact !== '' ? $contact : null,
        'email' => $email !== '' ? $email : null,
        'address' => $address !== '' ? $address : null,
        'id' => $clientId,
    ]);

    logActivity($pdo, $userId, 'edit_client', 'client', $clientId, [
        'first_name' => $firstName,
        'last_name' => $lastName,
    ]);

    goToArea($areaId, 'Client details updated successfully.');
}



            throw new RuntimeException('Unknown action.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof RuntimeException) {
                $error = $e->getMessage();
            } elseif ($e instanceof PDOException && $e->getCode() === '23000') {
                $error = 'Duplicate block and lot number in this area, or a related record already exists.';
            } else {
                error_log('LOTMAP map error: ' . $e->getMessage());
                $error = 'An operation failed. Check the PHP error log for details.';
            }
        }
    }
}

if (isset($_GET['message'])) {
    $success = (string) $_GET['message'];
}

// Load active map areas.
$areas = $pdo->query(
    'SELECT id, area_name, location
     FROM map_areas
     WHERE is_active = 1
     ORDER BY area_name'
)->fetchAll();

$area = null;
$lots = [];
$selectedLot = null;
$payments = [];
$history = [];
$clients = [];

if ($areaId > 0) {
    $stmt = $pdo->prepare(
        'SELECT id, area_name, location, description, map_image_path
         FROM map_areas
         WHERE id = :id AND is_active = 1'
    );
    $stmt->execute(['id' => $areaId]);
    $area = $stmt->fetch() ?: null;
}

if (!$area && $areas) {
    $areaId = (int) $areas[0]['id'];

    $stmt = $pdo->prepare(
        'SELECT id, area_name, location, description, map_image_path
         FROM map_areas
         WHERE id = :id AND is_active = 1
         LIMIT 1'
    );

    $stmt->execute(['id' => $areaId]);
    $area = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}



if ($area) {
    $stmt = $pdo->prepare(
        "SELECT l.*,
                oh.id AS active_ownership_id,
                oh.client_id AS active_client_id,
                oh.ownership_type AS active_ownership_type,
                oh.start_date AS ownership_start_date,
                oh.ownership_status AS active_ownership_status,
                c.first_name, c.middle_name, c.last_name, c.suffix,
                c.contact_number,

                (
                    SELECT COUNT(*)
                    FROM installment_payments ip
                    INNER JOIN installments ins
                        ON ins.id = ip.installment_id
                    WHERE ins.ownership_id = oh.id
                      AND ip.payment_date IS NOT NULL
                ) AS payments_made,

                (
                    SELECT COUNT(*)
                    FROM installment_payments ip
                    INNER JOIN installments ins
                        ON ins.id = ip.installment_id
                    WHERE ins.ownership_id = oh.id
                ) AS payments_total,

                (
                    SELECT COUNT(*)
                    FROM installment_payments ip
                    INNER JOIN installments ins
                        ON ins.id = ip.installment_id
                    WHERE ins.ownership_id = oh.id
                      AND ip.payment_date IS NULL
                      AND ip.due_date < CURDATE()
                ) AS unpaid_overdue_count

         FROM lots l

         LEFT JOIN ownership_history oh
           ON oh.lot_id = l.id
          AND oh.ownership_status = 'active'

         LEFT JOIN clients c
           ON c.id = oh.client_id

         WHERE l.map_area_id = :area_id

         ORDER BY l.block_number, l.lot_number"
    );

    $stmt->execute([
        'area_id' => (int) $area['id']
    ]);

    $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);


    $selectedLotId = filter_input(
        INPUT_GET,
        'lot_id',
        FILTER_VALIDATE_INT
    );

    if ($selectedLotId && $selectedLotId > 0) {
        $stmt = $pdo->prepare(
            'SELECT l.*, ma.area_name
             FROM lots l
             INNER JOIN map_areas ma ON ma.id = l.map_area_id
             WHERE l.id = :lot_id
               AND l.map_area_id = :area_id'
        );

        $stmt->execute([
            'lot_id' => (int)$selectedLotId,
            'area_id' => (int)$area['id']
        ]);

        $selectedLot = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($selectedLot) {
            // Load the current active ownership.
            $stmt = $pdo->prepare(
                "SELECT oh.*, c.first_name, c.middle_name, c.last_name,
                        c.suffix, c.contact_number, c.email, c.address,
                        ins.id AS installment_id,
                        ins.first_due_date, ins.term_months
                 FROM ownership_history oh
                 INNER JOIN clients c ON c.id = oh.client_id
                 LEFT JOIN installments ins ON ins.ownership_id = oh.id
                 WHERE oh.lot_id = :lot_id
                   AND oh.ownership_status = 'active'
                 ORDER BY oh.start_date DESC, oh.id DESC
                 LIMIT 1"
            );

            $stmt->execute([
                'lot_id' => (int)$selectedLotId
            ]);

            $currentOwnership =
                $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            // Admin and staff can view ownership history.
            $history = [];

            if (in_array($role, ['admin', 'staff'], true)) {
                $stmt = $pdo->prepare(
                    'SELECT oh.*, c.first_name, c.middle_name,
                            c.last_name, c.suffix, c.contact_number,
                            c.email, c.address,
                            ins.id AS installment_id,
                            ins.first_due_date, ins.term_months
                     FROM ownership_history oh
                     INNER JOIN clients c ON c.id = oh.client_id
                     LEFT JOIN installments ins
                        ON ins.ownership_id = oh.id
                     WHERE oh.lot_id = :lot_id
                     ORDER BY oh.id DESC'
                );

                $stmt->execute([
                    'lot_id' => (int)$selectedLotId
                ]);

                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Only load payments for the active installment ownership.
            $payments = [];

            $currentOwnershipId = (int)(
                $currentOwnership['id'] ?? 0
            );

            $isCurrentInstallment =
                $currentOwnership !== null
                && ($currentOwnership['ownership_type'] ?? '') === 'installment'
                && $currentOwnershipId > 0;

            if (
                $isCurrentInstallment
                && in_array($role, ['admin', 'staff'], true)
            ) {
                $stmt = $pdo->prepare(
                    "SELECT ip.id, ip.installment_number,
                            ip.due_date, ip.payment_date, ip.recorded_by
                     FROM installment_payments ip
                     INNER JOIN installments ins
                        ON ins.id = ip.installment_id
                     WHERE ins.ownership_id = :ownership_id
                     ORDER BY ip.installment_number ASC"
                );

                $stmt->execute([
                    'ownership_id' => $currentOwnershipId
                ]);

                $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }

    if ($canManage) {
        $clients = $pdo->query(
            'SELECT id, first_name, middle_name, last_name,
                    suffix, contact_number
             FROM clients
             ORDER BY last_name, first_name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}

$detectionData = [];
$detectedPolygons = [];
$detectionMessage = '';

if ($area && !empty($area['map_image_path'])) {
    $mapImageName = basename(
        str_replace(
            '\\',
            '/',
            (string) $area['map_image_path']
        )
    );

    $detectionKey = hash('sha256', $mapImageName);

    $detectionDirectory = __DIR__ . '/uploads/detections';
    $jsonPath = $detectionDirectory . '/' . $detectionKey . '.json';

    if (!is_file($jsonPath)) {
        $detectionMessage =
            'No detection file found for this map. Run detection for this image first.';
    } else {
        $jsonContent = file_get_contents($jsonPath);

        $decoded = is_string($jsonContent)
            ? json_decode($jsonContent, true)
            : null;

        $detectedImageName = is_array($decoded)
            ? basename(
                str_replace(
                    '\\',
                    '/',
                    (string) ($decoded['image'] ?? '')
                )
            )
            : '';

        $validDetection = (
            is_array($decoded)
            && isset($decoded['polygons'])
            && is_array($decoded['polygons'])
            && $mapImageName === $detectedImageName
        );

        if ($validDetection) {
            $detectionData = $decoded;
            $dismissedCandidates = [];

$dismissedFile = $detectionDirectory
    . '/'
    . $detectionKey
    . '.dismissed.json';

if (is_file($dismissedFile)) {
    $dismissedContent = file_get_contents($dismissedFile);
    $dismissedDecoded = is_string($dismissedContent)
        ? json_decode($dismissedContent, true)
        : null;

    if (
        is_array($dismissedDecoded)
        && isset($dismissedDecoded['ids'])
        && is_array($dismissedDecoded['ids'])
    ) {
        $dismissedCandidates = array_map(
            'strval',
            $dismissedDecoded['ids']
        );
    }
}

$detectedPolygons = array_values(array_filter(
    $decoded['polygons'],
    static function ($polygon, $index) use ($dismissedCandidates): bool {
        if (!is_array($polygon)) {
            return false;
        }

        $candidateId = (string) (
            $polygon['id'] ?? ('candidate-' . ($index + 1))
        );

        return !in_array(
            $candidateId,
            $dismissedCandidates,
            true
        );
    },
    ARRAY_FILTER_USE_BOTH
));

            $detectionMessage = count($detectedPolygons)
                . ' candidate boundaries loaded. Admin review is required.';
        } else {
            $detectionMessage =
                'Detection data does not match this map. Run detection for this image.';
        }
    }
}
$savedShapes = [];

if ($area && $areaId > 0) {
    $stmt = $pdo->prepare(
        "SELECT
            ls.id AS shape_id,
            ls.lot_id,
            ls.polygon_points,
            ls.verification_status,
            l.block_number,
            l.lot_number,
            l.status,
            oh.ownership_type AS active_ownership_type,
            oh.start_date AS purchase_date,
(
    SELECT MIN(ip.due_date)
    FROM installment_payments ip
    INNER JOIN installments ins2
        ON ins2.id = ip.installment_id
    INNER JOIN ownership_history oh2
        ON oh2.id = ins2.ownership_id
    WHERE oh2.lot_id = l.id
      AND oh2.ownership_status = 'active'
      AND ip.payment_date IS NULL
      AND ip.due_date < CURDATE()
) AS earliest_unpaid_due
        FROM lot_shapes AS ls
        INNER JOIN lots AS l
            ON l.id = ls.lot_id
        LEFT JOIN ownership_history AS oh
            ON oh.id = (
                SELECT oh2.id
                FROM ownership_history AS oh2
                WHERE oh2.lot_id = l.id
                  AND oh2.ownership_status = 'active'
                ORDER BY oh2.start_date DESC, oh2.id DESC
                LIMIT 1
            )
        INNER JOIN (
            SELECT lot_id, MAX(id) AS latest_shape_id
            FROM lot_shapes
            WHERE verification_status <> 'rejected'
            GROUP BY lot_id
        ) AS newest
            ON newest.latest_shape_id = ls.id
        WHERE l.map_area_id = :area_id
          AND ls.verification_status <> 'rejected'
        ORDER BY l.block_number, l.lot_number"
    );

    $stmt->execute([
        'area_id' => (int) $area['id']
    ]);

    $savedShapes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function lotStatusLabel(string $status): string
{
    return match ($status) {
        'available' => 'Available',
        'held' => 'Held',
        'spot_cash' => 'Spot Cash',
        'installment_current' => 'Installment Current',
        'overdue' => 'Overdue',
        'forfeiture_review' => 'Forfeiture Review',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}



function lotPolygonFillColor(array $shape): string
{
    $status = strtolower(
        trim((string) ($shape['status'] ?? 'available'))
    );

    $ownershipType = strtolower(
        trim((string) ($shape['active_ownership_type'] ?? ''))
    );

    // HELD: White
    if ($status === 'held') {
        return '#ffffff';
    }

    // Available: Green
    if ($status === 'available') {
        return '#16a34a';
    }

    // Spot cash: Pink
    if (
        $ownershipType === 'spot_cash' ||
        $status === 'spot_cash'
    ) {
        return '#ec4899';
    }

    // Unknown status
    if (
        $ownershipType !== 'installment' &&
        !in_array(
            $status,
            ['installment_current', 'overdue', 'forfeiture_review'],
            true
        )
    ) {
        return '#6b7280';
    }

    $earliestUnpaidDue = trim(
        (string) ($shape['earliest_unpaid_due'] ?? '')
    );

    if ($earliestUnpaidDue === '') {
        return '#2563eb';
    }

    try {
        $dueDate = new DateTimeImmutable($earliestUnpaidDue);
        $today = new DateTimeImmutable('today');

        if ($dueDate >= $today) {
            return '#2563eb';
        }

        if ($today > $dueDate->modify('+3 months')) {
            return '#111827';
        }

        return '#dc2626';
    } catch (Throwable $e) {
        return '#2563eb';
    }
}

require __DIR__ . '/includes/map-header.php';
require __DIR__ . '/map-content.php';
require __DIR__ . '/includes/map-footer.php';