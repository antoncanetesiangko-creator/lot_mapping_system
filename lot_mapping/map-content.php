<main>
    <div class="heading">
        <div>
            <h1>Residential Lot Mapping</h1>
            <div class="muted">Choose an area to manage lots and review ownership records.</div>
        </div>
        

<div class="header-actions">
    <?php if ($canManage && $lots): ?>
        <button type="button" id="openAssignOwnerModal"
                class="btn-assign-owner">
            + Assign Owner
        </button>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <button type="button" id="openAddLotModal"
                class="btn-add-lot">
            + Add Lot
        </button>
    <?php endif; ?>
</div>


<!-- ADD LOT MODAL: hidden hangtod i-click ang button -->
<div id="addLotModal" class="lot-modal" aria-hidden="true">

    <div class="lot-modal-backdrop" data-close-lot-modal></div>

    <div class="lot-modal-content"
         role="dialog"
         aria-modal="true"
         aria-labelledby="addLotTitle">

        <div class="lot-modal-header">
            <div>
                <h2 id="addLotTitle">Add New Lot</h2>
                <p>Enter the lot information below.</p>
            </div>

            <button type="button"
                    class="lot-modal-close"
                    data-close-lot-modal>
                &times;
            </button>
        </div>

        <form method="post">
            <input type="hidden" name="csrf_token"
                   value="<?= h($_SESSION['map_csrf'] ?? '') ?>">

            <input type="hidden" name="action" value="create_lot">

            <input type="hidden" name="area_id"
                   value="<?= (int)($areaId ?? $_GET['area_id'] ?? 0) ?>">

            <div class="lot-form-grid">
                <div>
                    <label for="lotBlockNumber">Block Number *</label>
                    <input id="lotBlockNumber"
                           name="block_number"
                           maxlength="50"
                           required>
                </div>

                <div>
                    <label for="lotNumber">Lot Number *</label>
                    <input id="lotNumber"
                           name="lot_number"
                           maxlength="50"
                           required>
                </div>

                <div>
                    <label for="lotArea">Area (sqm)</label>
                    <input id="lotArea"
                           name="lot_area_sqm"
                           type="number"
                           min="0.01"
                           step="0.01">
                </div>

                <div>
                    <label for="lotPrice">Price</label>
                    <input id="lotPrice"
                           name="price"
                           type="number"
                           min="0"
                           step="0.01">
                </div>

                <div class="lot-notes-field">
                    <label for="lotNotes">Notes</label>
                    <textarea id="lotNotes"
                              name="notes"
                              rows="3"></textarea>
                </div>
            </div>

            <div class="lot-modal-footer">
                <button type="button"
                        class="btn-cancel-lot"
                        data-close-lot-modal>
                    Cancel
                </button>

                <button type="submit" class="btn-save-lot">
                    Save Lot
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($canManage && $lots): ?>
<div id="assignOwnerModal" class="lot-modal" aria-hidden="true">
    <div class="lot-modal-backdrop" data-close-owner-modal></div>

    <div class="lot-modal-content owner-modal-content"
         role="dialog" aria-modal="true"
         aria-labelledby="assignOwnerTitle">

        <div class="lot-modal-header">
            <div>
                <h2 id="assignOwnerTitle">Assign Owner</h2>
                <p>Select an available lot and assign its owner.</p>
            </div>

            <button type="button" class="lot-modal-close"
                    data-close-owner-modal aria-label="Close">
                &times;
            </button>
        </div>

        <form method="post" id="owner-form">
            <input type="hidden" name="csrf_token"
                   value="<?= h($_SESSION['map_csrf']) ?>">
            <input type="hidden" name="action" value="assign_owner">
            <input type="hidden" name="area_id" value="<?= (int)$areaId ?>">

            <div class="owner-form-grid">
                <div class="owner-full">
                    <label for="owner-lot-select">Available Lot *</label>
                    <select name="lot_id" id="owner-lot-select" required>
                        <option value="">Select lot</option>
                        <?php foreach ($lots as $lot): ?>
                            <?php if ($lot['status'] === 'available'): ?>
                                <option value="<?= (int)$lot['id'] ?>">
                                    Block <?= h($lot['block_number']) ?>
                                    — Lot <?= h($lot['lot_number']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="owner-full">
                    <label for="client-select">Existing Client (optional)</label>
                    <select name="client_id" id="client-select">
                        <option value="">Create a new client below</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int)$client['id'] ?>">
                                <?= h($client['last_name'] . ', ' .
                                    $client['first_name'] .
                                    (!empty($client['middle_name'])
                                        ? ' ' . $client['middle_name'] : '') .
                                    (!empty($client['contact_number'])
                                        ? ' — ' . $client['contact_number'] : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="new-client-fields" class="owner-full">
                    <div class="owner-client-grid">
                        <div>
                            <label for="owner-first-name">First Name *</label>
                            <input id="owner-first-name" name="first_name"
                                   maxlength="100">
                        </div>
                        <div>
                            <label for="owner-last-name">Last Name *</label>
                            <input id="owner-last-name" name="last_name"
                                   maxlength="100">
                        </div>
                        <div>
                            <label for="owner-middle-name">Middle Name</label>
                            <input id="owner-middle-name" name="middle_name"
                                   maxlength="100">
                        </div>
                        <div>
                            <label for="owner-suffix">Suffix</label>
                            <input id="owner-suffix" name="suffix"
                                   maxlength="20">
                        </div>
                        <div>
                            <label for="owner-contact">Contact Number</label>
                            <input id="owner-contact" name="contact_number"
                                   maxlength="40">
                        </div>
                        <div>
                            <label for="owner-email">Email</label>
                            <input id="owner-email" name="email"
                                   type="email" maxlength="190">
                        </div>
                        <div class="owner-full">
                            <label for="owner-address">Address</label>
                            <textarea id="owner-address" name="address"
                                      rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="ownership-type">Ownership Type *</label>
                    <select name="ownership_type" id="ownership-type" required>
                        <option value="installment">Installment</option>
                        <option value="spot_cash">Spot Cash</option>
                    </select>
                </div>

                <div>
                    <label for="ownership-start-date">Ownership Start Date *</label>
                    <input id="ownership-start-date" type="date"
                           name="start_date" required
                           value="<?= h(date('Y-m-d')) ?>">
                </div>

                <div id="installment-fields" class="owner-full">
                    <div class="owner-client-grid">
                        <div>
                            <label for="first-due-date">First Installment Due Date *</label>
                            <input id="first-due-date" type="date"
                                   name="first_due_date"
                                   value="<?= h(date('Y-m-d')) ?>">
                        </div>
                        <div>
                            <label for="term-months">Term (months) *</label>
                            <input id="term-months" type="number"
                                   name="term_months" min="1" max="600"
                                   value="12">
                        </div>
                    </div>
                </div>
            </div>

            <div class="lot-modal-footer">
                <button type="button" class="btn-cancel-lot"
                        data-close-owner-modal>Cancel</button>
                <button type="submit" class="btn-save-lot">
                    Assign Ownership
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
        <div style="min-width:230px">
            <label for="area-select" style="margin-top:0">Map Area</label>
            <select id="area-select" onchange="if(this.value){location.href='map.php?area_id='+encodeURIComponent(this.value)}">
                <?php foreach ($areas as $item): ?>
                    <option value="<?= (int) $item['id'] ?>"
                        <?= $area && (int)$area['id'] === (int)$item['id'] ? 'selected' : '' ?>>
                        <?= h($item['area_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if ($error !== ''): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="alert success"><?= h($success) ?></div><?php endif; ?>

   
        
<section class="panel">
    <h2><?= h($area['area_name']) ?></h2>

    <div class="muted">
        <?= h($area['location'] ?: 'Location not specified') ?>
    </div>

    <?php if (!empty($area['description'])): ?>
        <p class="muted"><?= h($area['description']) ?></p>
    <?php endif; ?>

    <?php if (!empty($area['map_image_path'])): ?>
        <div class="boundary-editor-toolbar">
            <h3>Boundary Editor</h3>

<button
    type="button"
    id="editor-start-edit"
    style="background-color: #2563EB; color: #FFFFFF; border: none; padding: 10px 18px; border-radius: 6px; cursor: pointer; font-weight: 600;"
>
    ✏️ Start Edit
</button>

<button
    type="button"
    id="editor-finish-edit"
    style="background-color: #16A34A; color: #FFFFFF; border: none; padding: 10px 18px; border-radius: 6px; cursor: pointer; font-weight: 600;"
    hidden
>
    ✓ Finish Edit
</button>

            
<div class="editor-buttons">
    <button type="button" id="editor-select"
        style="background:#2563EB;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:600;">
        🖱️ Tig Pislit
    </button>

    <button type="button" id="editor-draw"
        style="background:#0D9488;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:600;">
        📐 Tig Sukat
    </button>

    <button type="button" id="editor-edit"
        style="background:#7C3AED;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:600;">
        ✏️ Pag han-ay
    </button>

    <button type="button" id="delete-polygon" class="btn-danger"
        style="background:#DC2626;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:600;">
        🗑️ Hawa Earth
    </button>

    <button type="button" id="editor-undo"
        style="background:#475569;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:600;">
        ↶ Tigbalik
    </button>



    <button type="button" id="editor-cancel"
        style="background:#64748B;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:600;">
        ✕ Cancel Edit
    </button>

    <button type="button" id="cancel-selected-label" class="btn-danger"
        style="background:#B91C1C;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:600;">
        🏷️ Remove Text Label
    </button>
</div>
<div class="editor-buttons label-polygon-controls">
    <button type="button" id="add-text-label"
        style="background:#1D4ED8;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:600;">
        ＋ 🏷️ Text Label
    </button>

    <button type="button" id="rotate-label-left"
        style="background:#374151;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:600;">
        ↶ Rotate Label
    </button>

    <button type="button" id="rotate-label-right"
        style="background:#374151;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:600;">
        ↷ Rotate Label
    </button>

    <button type="button" id="minimize-label"
        style="background:#374151;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:600;">
        − Minimize Label
    </button>

    <button type="button" id="maximize-label"
        style="background:#374151;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:600;">
        ＋ Maximize Label
    </button>

    <button type="button" id="editor-drag-polygon"
        style="background:#0F766E;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:600;">
        ✥ Drag Polygon
    </button>

    <button type="button" id="polygon-smaller"
        style="background:#475569;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:600;">
        − Shrink Polygon
    </button>

    <button type="button" id="polygon-larger"
        style="background:#475569;color:white;border:0;border-radius:8px;padding:10px 16px;font-weight:600;">
        ＋ Enlarge Polygon
    </button>
</div>

    <span id="label-polygon-status"
          role="status"
          aria-live="polite"></span>
</div>
        <div class="map-editor">
            <img
                id="lot-map-image"
                src="<?= h($area['map_image_path']) ?>"
                alt="Residential lot map"
            >

           <svg
    id="detected-lot-overlay"
    data-area-id="<?= (int)$areaId ?>"
    viewBox="0 0 <?= max(1, (int)($detectionData['image_width'] ?? 1)) ?> <?= max(1, (int)($detectionData['image_height'] ?? 1)) ?>"
    preserveAspectRatio="none"
    aria-label="Detected candidate lot boundaries"
>
                <?php foreach ($detectedPolygons as $index => $polygon): ?>
                    <?php
                    $points = $polygon['points'] ?? [];

                    if (!is_array($points) || count($points) < 3) {
                        continue;
                    }

                    $svgPoints = [];

                    foreach ($points as $point) {
                        if (
                            !is_array($point) ||
                            !isset($point[0], $point[1]) ||
                            !is_numeric($point[0]) ||
                            !is_numeric($point[1])
                        ) {
                            continue;
                        }

                        $x = max(0, min(1, (float) $point[0]));
                        $y = max(0, min(1, (float) $point[1]));

                        $svgPoints[] = sprintf(
                            '%.3f,%.3f',
                            $x * max(1, (int)($detectionData['image_width'] ?? 1)),
                            $y * max(1, (int)($detectionData['image_height'] ?? 1))
                        );
                    }
                    ?>

                    <?php if (count($svgPoints) >= 3): ?>
                        <polygon
                        data-points="<?= h(json_encode($shapePoints, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>"
                            class="detected-polygon"
                            points="<?= h(implode(' ', $svgPoints)) ?>"
                            data-candidate="<?= h($polygon['id'] ?? ('candidate-' . ($index + 1))) ?>"
                        ></polygon>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php
$imageWidth = max(
    1,
    (int)($detectionData['image_width'] ?? 1)
);

$imageHeight = max(
    1,
    (int)($detectionData['image_height'] ?? 1)
);
?>

<?php foreach ($savedShapes as $shape): ?>
    <?php
    $shapePoints = json_decode(
        (string)$shape['polygon_points'],
        true
    );

    if (!is_array($shapePoints) || count($shapePoints) < 3) {
        continue;
    }

    $svgPoints = [];
    $sumX = 0.0;
    $sumY = 0.0;
    $validCount = 0;

    foreach ($shapePoints as $point) {
        if (
            !is_array($point) ||
            !isset($point[0], $point[1]) ||
            !is_numeric($point[0]) ||
            !is_numeric($point[1])
        ) {
            continue;
        }

        $x = (float)$point[0];
        $y = (float)$point[1];

        if (
            !is_finite($x) ||
            !is_finite($y) ||
            $x < 0 || $x > 1 ||
            $y < 0 || $y > 1
        ) {
            continue;
        }

        $svgPoints[] = sprintf(
            '%.3f,%.3f',
            $x * $imageWidth,
            $y * $imageHeight
        );

        $sumX += $x * $imageWidth;
        $sumY += $y * $imageHeight;
        $validCount++;
    }

    if ($validCount < 3) {
        continue;
    }

   $debugStatus = strtolower(trim((string)($shape['status'] ?? 'missing')));
$fillColor = lotPolygonFillColor($shape);

if ($debugStatus === 'held') {
    $fillColor = '#fffb00';
}
    $label = 'B' . $shape['block_number']
       . ' / L' . $shape['lot_number'];
    $centerX = $sumX / $validCount;
    $centerY = $sumY / $validCount;
    ?>

    
    <polygon
    class="saved-lot-polygon"
    points="<?= h(implode(' ', $svgPoints)) ?>"
    fill="<?= h($fillColor) ?>"
    data-shape-id="<?= (int)$shape['shape_id'] ?>"
    data-lot-id="<?= (int)$shape['lot_id'] ?>"
    data-verification="<?= h($shape['verification_status']) ?>"
    ></polygon>


    <text
    class="saved-lot-label"
    x="<?= h((string)$centerX) ?>"
    y="<?= h((string)$centerY) ?>"
    text-anchor="middle"
    dominant-baseline="middle"
><?= h($label) ?></text>
<?php endforeach; ?>
            </svg>
        </div>

        <div class="detection-info">
            <?= h($detectionMessage ?: 'No detection results available.') ?>
            <p style="margin-bottom:0">
                Green polygons are unverified candidate boundaries.
                Review them before assigning them to lot records.
            </p>
        </div>
                
<?php if ($canManage && $lots && $detectedPolygons): ?>
    <div class="panel" style="margin-top:16px">
        <h2>Save Detected Boundary</h2>
        <p class="muted">
            Click a green polygon on the map, select the correct lot,
            then save. The boundary will remain pending verification.
        </p>


    <p id="editor-status" class="muted" aria-live="polite">
        Select a detected polygon or choose Draw Boundary.
    </p>

    <p class="muted">
        Draw: click the map to add points, then click Finish Drawing.
        Edit: drag the circular handles to adjust the selected boundary.
    </p>

    <button type="button" id="editor-finish">
        Finish Drawing
    </button>
</div>

        <form method="post" id="save-boundary-form">
            <input type="hidden" name="csrf_token"
                   value="<?= h($_SESSION['map_csrf']) ?>">
            <input type="hidden" name="action" value="save_boundary">
            <input type="hidden" name="area_id" value="<?= (int)$areaId ?>">
            <input type="hidden" name="candidate_id"
                   id="candidate-id-input" value="">
<input
    type="hidden"
    name="polygon_points"
    id="editor-polygon-points"
    value=""
>
            <p id="selected-candidate-message" class="muted">
                No candidate selected. Click a polygon first.
            </p>

            <label for="boundary-lot-select">Assign to Lot *</label>
            <select name="lot_id" id="boundary-lot-select" required>
                <option value="">Select the correct lot</option>
                <?php foreach ($lots as $lot): ?>
                    <option value="<?= (int)$lot['id'] ?>">
                        Block <?= h($lot['block_number']) ?>
                        — Lot <?= h($lot['lot_number']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" id="save-boundary" style="margin-top:14px">
    Save Boundary
</button>
        </form>
    </div>

    <div class="panel" style="margin-top:16px">
        <h2>Saved Boundaries &amp; Verification</h2>

        <?php if (empty($savedShapes)): ?>
            <p class="muted">
                No saved boundaries for this map area yet.
                Select a detected polygon and save it first.
            </p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Block</th>
                            <th>Lot</th>
                            <th>Boundary Status</th>
                            <?php if ($canManage): ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($savedShapes as $shape): ?>
                            <tr>
                                <td><?= h($shape['block_number']) ?></td>
                                <td><?= h($shape['lot_number']) ?></td>
                                <td>
                                    <?= h(ucfirst(
                                        (string)$shape['verification_status']
                                    )) ?>
                                </td>

                                <?php if ($canManage): ?>
                                    <td>
                                        <?php if (
                                            $shape['verification_status'] === 'pending'
                                        ): ?>
                                            <form method="post">
                                                <input type="hidden"
                                                       name="csrf_token"
                                                       value="<?= h($_SESSION['map_csrf']) ?>">
                                                <input type="hidden"
                                                       name="action"
                                                       value="verify_boundary">
                                                <input type="hidden"
                                                       name="area_id"
                                                       value="<?= (int)$areaId ?>">
                                                <input type="hidden"
                                                       name="shape_id"
                                                       value="<?= (int)$shape['shape_id'] ?>">

                                                <button type="submit"
                                                    onclick="return confirm('Verify this saved boundary?');">
                                                    Verify Boundary
                                                </button>
                                            </form>
                                        <?php elseif (
                                            $shape['verification_status'] === 'verified'
                                        ): ?>
                                            <span class="badge available">Verified</span>
                                        <?php else: ?>
                                            <span class="muted">
                                                <?= h($shape['verification_status']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
            </div>
<?php endif; ?>
<?php else: ?>
    <div class="empty">
        No map image is associated with this area.
    </div>
<?php endif; ?>
</section>

        <div class="grid">
            <section class="panel">
                <h2>Lots in This Area</h2>
                <?php if (!$lots): ?>
                    <div class="empty">No lots have been added to this area yet.</div>
                <?php else: ?>
                    <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Block</th><th>Lot</th><th>Area (sqm)</th>
                            <th>Status</th><th>Current Owner</th><th>Installments</th><th>Details</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lots as $lot): ?>
                            <tr>
                                <td><?= h($lot['block_number']) ?></td>
                                <td><?= h($lot['lot_number']) ?></td>
                                <td><?= h($lot['lot_area_sqm'] ?? '—') ?></td>
                                <td><span class="badge <?= h($lot['status']) ?>"><?= h(lotStatusLabel((string)$lot['status'])) ?></span></td>
                                <td>
                                    <?php if (!empty($lot['active_ownership_id'])): ?>
                                        <?= h(trim($lot['first_name'] . ' ' . $lot['last_name'])) ?>
                                    <?php else: ?>
                                        <span class="muted">No active owner</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)($lot['payments_total'] ?? 0) > 0): ?>
                                        <?= (int)$lot['payments_made'] ?> / <?= (int)$lot['payments_total'] ?> paid
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><a href="map.php?area_id=<?= $areaId ?>&lot_id=<?= (int)$lot['id'] ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </section>

<?php if (!empty($selectedLot) && is_array($selectedLot)): ?>
    <section class="panel">
        <h2>
            Lot Details: Block <?= h($selectedLot['block_number'] ?? '—') ?>
            — Lot <?= h($selectedLot['lot_number'] ?? '—') ?>
        </h2>

        <div class="detail-list">
            <div class="detail">
                <small>Map Area</small>
                <strong>
                    <?= h($selectedLot['area_name'] ?? 'Not assigned') ?>
                </strong>
            </div>

            <div class="detail">
                <small>Lot Size</small>
                <strong>
                    <?php if (
                        isset($selectedLot['lot_area_sqm']) &&
                        $selectedLot['lot_area_sqm'] !== ''
                    ): ?>
                        <?= h($selectedLot['lot_area_sqm']) ?> sqm
                    <?php else: ?>
                        Not entered
                    <?php endif; ?>
                </strong>
            </div>
            <div class="detail">
                <small>Current Status</small>
                <strong>
                    <?php
                    $status = $selectedLot['status'] ?? null;
                    echo h(
                        ($status !== null && $status !== '')
                            ? lotStatusLabel((string) $status)
                            : 'Unknown'
                    );
                    ?>
                </strong>
            </div>
        </div>
        
<?php if ($canManage): ?>
    <?php if (($selectedLot['status'] ?? '') === 'held'): ?>
        <form method="post"
              onsubmit="return confirm('Release the hold on this lot?');">
            <input type="hidden" name="csrf_token"
                   value="<?= h($_SESSION['map_csrf']) ?>">
            <input type="hidden" name="action" value="release_hold">
            <input type="hidden" name="area_id"
                   value="<?= (int)$areaId ?>">
            <input type="hidden" name="lot_id"
                   value="<?= (int)$selectedLot['id'] ?>">

            <button type="submit">Release Hold</button>
        </form>
    <?php else: ?>
        <form method="post"
              onsubmit="return confirm('Place this lot on hold?');">
            <input type="hidden" name="csrf_token"
                   value="<?= h($_SESSION['map_csrf']) ?>">
            <input type="hidden" name="action" value="hold_lot">
            <input type="hidden" name="area_id"
                   value="<?= (int)$areaId ?>">
            <input type="hidden" name="lot_id"
                   value="<?= (int)$selectedLot['id'] ?>">

            <label for="hold-reason">Reason for Hold</label>
            <input type="text" id="hold-reason"
                   name="hold_reason" maxlength="1000">

            <button type="submit">Hold Lot</button>
        </form>
    <?php endif; ?>
<?php endif; ?>

    </section>
<?php endif; ?>

<?php if (
    in_array($role, ['admin', 'staff'], true)
    && !empty($selectedLot)
    && is_array($selectedLot)
): ?>
<div id="ownershipHistoryModal"
     class="ownership-modal"
     aria-hidden="true">

    <div class="ownership-modal-backdrop"
         data-close-ownership-modal></div>

    <section class="ownership-modal-content"
             role="dialog"
             aria-modal="true"
             aria-labelledby="ownershipHistoryTitle">

        <div class="ownership-modal-header">
            <div>
                <h2 id="ownershipHistoryTitle">Ownership History</h2>
                <p class="muted">
                    Block <?= h($selectedLot['block_number'] ?? '—') ?>
                    — Lot <?= h($selectedLot['lot_number'] ?? '—') ?>
                </p>
            </div>

            <button type="button"
                    class="ownership-modal-close"
                    data-close-ownership-modal
                    aria-label="Close">
                &times;
            </button>
        </div>

        <?php if (empty($history)): ?>
            <div class="empty">
                No ownership history has been recorded for this lot.
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Owner</th>
                            <th>Ownership Type</th>
                            <th>Start Date</th>
                            <th>Status</th>
                            <th>Contact Number</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $record): ?>
                            <tr>
                                <td>
                                    <?= h(trim(
                                        ($record['first_name'] ?? '') . ' ' .
                                        ($record['middle_name'] ?? '') . ' ' .
                                        ($record['last_name'] ?? '') . ' ' .
                                        ($record['suffix'] ?? '')
                                    )) ?>
                                </td>
                                <td>
                                    <?= h(ucwords(str_replace(
                                        '_',
                                        ' ',
                                        (string)($record['ownership_type'] ?? '')
                                    ))) ?>
                                </td>
                                <td><?= h($record['start_date'] ?? '—') ?></td>
                                <td>
                                    <?= h(ucwords(str_replace(
                                        '_',
                                        ' ',
                                        (string)($record['ownership_status'] ?? '')
                                    ))) ?>
                                </td>
                                <td><?= h($record['contact_number'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </section>
</div>
<?php endif; ?>

        <?php if ($canManage && !empty($selectedLot['status']) && $selectedLot['status'] !== 'available'): ?>
            <h3 style="margin-top:22px">Record Backout</h3>
            <p class="muted">
                This closes the active ownership and makes the lot available again.
                Past owner and payment records remain in the database.
            </p>

            <form method="post" onsubmit="return confirm('Confirm backout? The active ownership will be closed and the lot marked available.');">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['map_csrf']) ?>">
                <input type="hidden" name="action" value="backout">
                <input type="hidden" name="area_id" value="<?= (int)$areaId ?>">
                <input type="hidden" name="lot_id" value="<?= (int)$selectedLot['id'] ?>">

                <div class="form-grid">
                    <div>
                        <label>Backout Date</label>
                        <input type="date" name="backout_date" required value="<?= h(date('Y-m-d')) ?>">
                    </div>
                    <div>
                        <label>Reason *</label>
                        <input name="backout_reason" required maxlength="1000">
                    </div>
                </div>

                <button type="submit" style="margin-top:14px">Confirm Backout</button>
            </form>
        <?php endif; ?>
    </section>

    <?php
    $isCurrentInstallment =
        isset($currentOwnership)
        && $currentOwnership !== null
        && ($currentOwnership['ownership_type'] ?? '') === 'installment';
    ?>

    
<?php if (in_array($role, ['admin', 'staff'], true) && $isCurrentInstallment): ?>
    <section class="panel">
        <h2>Current Owner — Installment Payment Schedule</h2>
        <p class="muted">
            This schedule belongs only to the current active installment ownership.
        </p>

        <?php if (empty($payments)): ?>
            <div class="empty">
                No installment payment schedule exists for this ownership.
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Installment</th>
                            <th>Due Date</th>
                            <th>Payment Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <?php
                                $paymentId = (int) $payment['id'];
                                $paymentDate = $payment['payment_date'] ?? null;
                                $editFormId = 'edit-payment-' . $paymentId;
                            ?>
                            <tr>
                                <td>
                                    <?= (int) $payment['installment_number'] ?>
                                </td>

                                <td>
                                    <?= h($payment['due_date']) ?>
                                </td>

                                <td>
                                    <?= h($paymentDate ?: 'Unpaid') ?>
                                </td>

                                <td>
                                    <?php if ($canRecordPayment && $paymentDate === null): ?>
                                        <form class="inline-form" method="post">
                                            <input type="hidden" name="csrf_token"
                                                value="<?= h($_SESSION['map_csrf']) ?>">
                                            <input type="hidden" name="action"
                                                value="record_payment">
                                            <input type="hidden" name="area_id"
                                                value="<?= (int) $areaId ?>">
                                            <input type="hidden" name="payment_id"
                                                value="<?= $paymentId ?>">

                                            <input type="date" name="payment_date"
                                                value="<?= h(date('Y-m-d')) ?>"
                                                required>

                                            <button type="submit">Record Paid</button>
                                        </form>

                                    
<?php elseif ($canRecordPayment && $paymentDate !== null): ?>
    <button
        type="button"
        onclick="document.getElementById('<?= h($editFormId) ?>').hidden = false; this.hidden = true;">
        Edit Date
    </button>

    <form
        id="<?= h($editFormId) ?>"
        class="inline-form"
        method="post"
        hidden
        onsubmit="return confirm('Save the corrected payment date?');">

        <input type="hidden" name="csrf_token"
            value="<?= h($_SESSION['map_csrf']) ?>">
        <input type="hidden" name="action"
            value="edit_payment_date">
        <input type="hidden" name="area_id"
            value="<?= (int) $areaId ?>">
        <input type="hidden" name="payment_id"
            value="<?= $paymentId ?>">

        <input type="date"
            name="payment_date"
            value="<?= h($paymentDate) ?>"
            required>

        <button type="submit">Save Date</button>

        <button
            type="button"
            onclick="this.form.hidden = true; this.form.previousElementSibling.hidden = false;">
            Close Edit
        </button>
    </form>

    <form
        class="inline-form"
        method="post"
        onsubmit="return confirm('Cancel this recorded payment date? The installment will become unpaid again.');">

        <input type="hidden" name="csrf_token"
            value="<?= h($_SESSION['map_csrf']) ?>">
        <input type="hidden" name="action"
            value="cancel_payment_date">
        <input type="hidden" name="area_id"
            value="<?= (int) $areaId ?>">
        <input type="hidden" name="payment_id"
            value="<?= $paymentId ?>">

        <button type="submit">Cancel Payment Date</button>
    </form>

<?php else: ?>
    <span class="muted">Read-only</span>
<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
</main>