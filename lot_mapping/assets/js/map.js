(() => {
    const clientSelect = document.getElementById('client-select');
    const newClientFields = document.getElementById('new-client-fields');
    const ownershipType = document.getElementById('ownership-type');
    const installmentFields = document.getElementById('installment-fields');

    function updateClientFields() {
        if (!clientSelect || !newClientFields) return;
        const existingClient = clientSelect.value !== '';
        newClientFields.style.display = existingClient ? 'none' : 'block';

        ['first_name', 'last_name'].forEach(name => {
            const field = newClientFields.querySelector(`[name="${name}"]`);
            if (field) field.required = !existingClient;
        });
    }

    function updateInstallmentFields() {
        if (!ownershipType || !installmentFields) return;
        const isInstallment = ownershipType.value === 'installment';
        installmentFields.style.display = isInstallment ? 'block' : 'none';

        installmentFields.querySelectorAll('input').forEach(field => {
            field.required = isInstallment;
        });
    }

    if (clientSelect) clientSelect.addEventListener('change', updateClientFields);
    if (ownershipType) ownershipType.addEventListener('change', updateInstallmentFields);

    updateClientFields();
    updateInstallmentFields();
    

const detectedOverlay = document.getElementById('detected-lot-overlay');
const candidateInput = document.getElementById('candidate-id-input');
const candidateMessage = document.getElementById('selected-candidate-message');
const saveBoundaryForm = document.getElementById('save-boundary-form');
const polygonPointsInput = document.getElementById('editor-polygon-points');
const lotSelect = document.getElementById('boundary-lot-select');

const editorStatus = document.getElementById('editor-status');
const finishButton = document.getElementById('editor-finish');

const selectButton = document.getElementById('editor-select');
const drawButton = document.getElementById('editor-draw');
const editButton = document.getElementById('editor-edit');
const undoButton = document.getElementById('editor-undo');
const clearButton = document.getElementById('editor-clear');
const cancelButton = document.getElementById('editor-cancel');
const deleteButton = document.getElementById('delete-polygon');

if (detectedOverlay) {
    let mode = 'select';
    let selectedPolygon = null;
    let editMode = false;
    let drawingPoints = [];
    let draftPolygon = null;
    let vertexHandles = [];
    let originalPoints = null;
    let draggingHandle = null;

    const svgNS = 'http://www.w3.org/2000/svg';

    function status(message) {
        if (editorStatus) editorStatus.textContent = message;
    }

    function getPoints(polygon) {
        return (polygon.getAttribute('points') || '')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .map(pair => {
                const values = pair.split(',');
                return {
                    x: Number(values[0]),
                    y: Number(values[1])
                };
            })
            .filter(p => Number.isFinite(p.x) && Number.isFinite(p.y));
    }

    function setPoints(polygon, points) {
        polygon.setAttribute(
            'points',
            points.map(p => `${p.x},${p.y}`).join(' ')
        );
    }

    function eventToSvgPoint(event) {
        const point = detectedOverlay.createSVGPoint();
        point.x = event.clientX;
        point.y = event.clientY;

        const matrix = detectedOverlay.getScreenCTM();
        if (!matrix) return null;

        const result = point.matrixTransform(matrix.inverse());
        return { x: result.x, y: result.y };
    }

function getNormalizedPoints(polygon) {
    const points = getPoints(polygon);
    const svg = detectedOverlay;

    const viewBox = svg.viewBox.baseVal;

    const width = viewBox.width || svg.clientWidth;
    const height = viewBox.height || svg.clientHeight;

    if (!width || !height) {
        throw new Error('Unable to determine map dimensions.');
    }

    return points.map(point => ({
        x: (point.x - viewBox.x) / width,
        y: (point.y - viewBox.y) / height
    }));
}
function removeDraft() {
    if (draftPolygon) {
        draftPolygon.remove();
        draftPolygon = null;
    }

    drawingPoints = [];

    detectedOverlay.style.cursor = 'default';
}

    function clearHandles() {
        vertexHandles.forEach(handle => handle.remove());
        vertexHandles = [];
        draggingHandle = null;
    }


    function clearSelection() {
    detectedOverlay.querySelectorAll(
        '.detected-polygon, .saved-lot-polygon'
    ).forEach(polygon => {
        polygon.classList.remove('selected');
    });

    clearHandles();
    selectedPolygon = null;
    originalPoints = null;

    if (candidateInput) candidateInput.value = '';
    if (polygonPointsInput) polygonPointsInput.value = '';
}

    
function selectPolygon(polygon) {
    clearSelection();
    selectedPolygon = polygon;

    polygon.classList.add('selected');
    originalPoints = getPoints(polygon);

    if (polygon.classList.contains('detected-polygon')) {
        const candidateId = polygon.dataset.candidate || '';

        if (candidateInput) {
            candidateInput.value = candidateId;
        }

        if (candidateMessage) {
            candidateMessage.textContent = candidateId
                ? `Selected boundary: ${candidateId}. Select the correct lot.`
                : 'This polygon has no candidate ID.';
        }

        status('Detected boundary selected.');
    } else {
        const shapeId = Number(polygon.dataset.shapeId);
        const lotId = Number(polygon.dataset.lotId);

        if (!shapeId || !lotId) {
            status('Missing saved boundary ID or lot ID.');
            return;
        }

        if (candidateInput) candidateInput.value = '';
        if (lotSelect) lotSelect.value = String(lotId);

        if (editMode) {
            const actionInput = saveBoundaryForm?.querySelector(
                'input[name="action"]'
            );

            if (actionInput) {
                actionInput.value = 'update_boundary';
            }

            let shapeIdInput = saveBoundaryForm?.querySelector(
                'input[name="shape_id"]'
            );

            if (saveBoundaryForm && !shapeIdInput) {
                shapeIdInput = document.createElement('input');
                shapeIdInput.type = 'hidden';
                shapeIdInput.name = 'shape_id';
                saveBoundaryForm.appendChild(shapeIdInput);
            }

            if (shapeIdInput) {
                shapeIdInput.value = String(shapeId);
            }

            if (candidateMessage) {
                candidateMessage.textContent =
                    `Editing existing boundary: Shape ${shapeId}.`;
            }

            status('Saved boundary selected for editing.');
        } else {
            if (candidateMessage) {
                candidateMessage.textContent =
                    `Lot ${lotId} selected. Click Start Edit to modify its boundary.`;
            }

            status('Saved boundary selected. Edit mode is off.');
        }
    }

    if (polygonPointsInput) {
        polygonPointsInput.value = JSON.stringify(originalPoints);
    }
}


    
function startDrawing() {
    clearSelection();
    removeDraft();

    mode = 'draw';

    draftPolygon = document.createElementNS(
        'http://www.w3.org/2000/svg',
        'polygon'
    );

    draftPolygon.setAttribute('class', 'editor-draft');
    draftPolygon.setAttribute('fill', 'rgba(0, 150, 255, 0.20)');
    draftPolygon.setAttribute('stroke', '#0096ff');
    draftPolygon.setAttribute('stroke-width', '3');
    draftPolygon.setAttribute('pointer-events', 'none');

    detectedOverlay.appendChild(draftPolygon);

    detectedOverlay.style.cursor = 'crosshair';

    status('DRAW MODE ACTIVE — click at least 3 points on the map.');
}

function finishDrawing() {
    if (mode !== 'draw') {
        status('Click Draw Boundary first.');
        return;
    }

    if (drawingPoints.length < 3) {
        status('Add at least 3 points before finishing.');
        return;
    }

    setPoints(draftPolygon, drawingPoints);

    draftPolygon.setAttribute('pointer-events', 'all');
    draftPolygon.setAttribute('fill', 'rgba(0, 150, 255, 0.20)');
    draftPolygon.setAttribute('stroke', '#0096ff');
    draftPolygon.setAttribute('stroke-width', '3');
    draftPolygon.classList.remove('editor-draft');
    draftPolygon.classList.add('detected-polygon');
    draftPolygon.dataset.manualDraft = '1';

    selectedPolygon = draftPolygon;
    originalPoints = getPoints(draftPolygon);

    draftPolygon = null;
    drawingPoints = [];
    mode = 'select';

    detectedOverlay.style.cursor = 'default';

    if (candidateInput) candidateInput.value = '';

    if (polygonPointsInput) {
        polygonPointsInput.value = JSON.stringify(originalPoints);
    }

    if (candidateMessage) {
        candidateMessage.textContent =
            'Manual boundary created. PHP save support is still required.';
    }

    status('Drawing finished. Select Edit Vertices to adjust the boundary.');
}


    function enableVertexEditing() {
        if (!selectedPolygon) {
            status('Select a polygon first.');
            return;
        }

        const points = getPoints(selectedPolygon);
        if (points.length < 3) {
            status('The selected polygon has invalid points.');
            return;
        }

        clearHandles();
        mode = 'edit';

        points.forEach((point, index) => {
            const handle = document.createElementNS(svgNS, 'circle');
            handle.setAttribute('cx', point.x);
            handle.setAttribute('cy', point.y);
            handle.setAttribute('r', '7');
            handle.setAttribute('fill', '#ffffff');
            handle.setAttribute('stroke', '#007bff');
            handle.setAttribute('stroke-width', '3');
            handle.style.cursor = 'move';
            handle.style.touchAction = 'none';
            handle.dataset.vertexIndex = String(index);

            detectedOverlay.appendChild(handle);
            vertexHandles.push(handle);
        });

        status('Drag the circular handles to edit the boundary.');
    }

    detectedOverlay.addEventListener('click', event => {
        if (mode === 'draw') {
            if (event.target.closest('circle')) return;

            const point = eventToSvgPoint(event);
            if (!point) return;

            drawingPoints.push(point);

console.log('Drawing point:', point);
console.log('Total points:', drawingPoints.length);

setPoints(draftPolygon, drawingPoints);
status(`${drawingPoints.length} point(s) added.`);
return;
        }

        if (mode === 'edit') return;

const polygon = event.target.closest(
    '.detected-polygon, .saved-lot-polygon'
);


if (!polygon || !detectedOverlay.contains(polygon)) return;

// Shift + Click: add/remove a polygon from bulk selection.
if (event.shiftKey) {
    polygon.classList.toggle('bulk-delete-selected');
    status(
        `${detectedOverlay.querySelectorAll(
            '.detected-polygon.bulk-delete-selected'
        ).length} polygon(s) selected for deletion.`
    );
    return;
}

// Normal click: clear bulk selection and select one polygon.
detectedOverlay.querySelectorAll('.bulk-delete-selected')
    .forEach(item => item.classList.remove('bulk-delete-selected'));

selectPolygon(polygon);

// Existing saved polygons can open their lot detail page.
if (polygon.classList.contains('saved-lot-polygon')) {
    const lotId = Number(polygon.dataset.lotId);
    const areaId = Number(detectedOverlay.dataset.areaId);

    if (lotId > 0 && areaId > 0) {
        const url = new URL('map.php', window.location.href);
        url.searchParams.set('area_id', String(areaId));
        url.searchParams.set('lot_id', String(lotId));

        window.location.href = url.toString();
    }
}
});

    detectedOverlay.addEventListener('pointerdown', event => {
        if (mode !== 'edit') return;

        const handle = event.target.closest('circle[data-vertex-index]');
        if (!handle) return;

        draggingHandle = handle;
        handle.setPointerCapture?.(event.pointerId);
        event.preventDefault();
    });

    detectedOverlay.addEventListener('pointermove', event => {
        if (mode !== 'edit' || !draggingHandle || !selectedPolygon) return;

        const point = eventToSvgPoint(event);
        if (!point) return;

        const index = Number(draggingHandle.dataset.vertexIndex);
        const points = getPoints(selectedPolygon);

        if (!points[index]) return;

        points[index] = point;
        setPoints(selectedPolygon, points);

        draggingHandle.setAttribute('cx', point.x);
        draggingHandle.setAttribute('cy', point.y);

        if (polygonPointsInput) {
            polygonPointsInput.value = JSON.stringify(points);
        }
    });

    detectedOverlay.addEventListener('pointerup', () => {
        draggingHandle = null;
    });

    detectedOverlay.addEventListener('pointercancel', () => {
        draggingHandle = null;
    });

    if (selectButton) {
        selectButton.addEventListener('click', () => {
            mode = 'select';
            clearHandles();
            status('Select a detected or saved polygon.');
        });
    }

    if (drawButton) {
        drawButton.addEventListener('click', startDrawing);
    }

    if (finishButton) {
        finishButton.addEventListener('click', finishDrawing);
    }

    if (editButton) {
        editButton.addEventListener('click', enableVertexEditing);
    }

    if (undoButton) {
        undoButton.addEventListener('click', () => {
            if (mode === 'draw' && drawingPoints.length) {
                drawingPoints.pop();
                setPoints(draftPolygon, drawingPoints);
                status('Last drawing point removed.');
                return;
            }

            if (mode === 'edit' && selectedPolygon && originalPoints) {
                setPoints(selectedPolygon, originalPoints);
                enableVertexEditing();
                status('Boundary restored to its original points.');
                return;
            }

            status('Undo is available while drawing or editing.');
        });
    }

    if (clearButton) {
        clearButton.addEventListener('click', () => {
            removeDraft();
            clearSelection();
            mode = 'select';
            status('Draft cleared. Saved database boundaries were not deleted.');
        });
    }

    if (cancelButton) {
        cancelButton.addEventListener('click', () => {
            removeDraft();

            if (selectedPolygon && originalPoints) {
                setPoints(selectedPolygon, originalPoints);
            }

            clearSelection();
            mode = 'select';
            status('Editing cancelled.');
        });
    }

    
if (deleteButton) {
    deleteButton.addEventListener('click', async () => {
        const bulkSelected = Array.from(
            detectedOverlay.querySelectorAll(
                '.detected-polygon.bulk-delete-selected'
            )
        );

        // Use bulk selection if multiple polygons were selected.
        const polygonsToDelete = bulkSelected.length > 0
            ? bulkSelected
            : selectedPolygon
                ? [selectedPolygon]
                : [];

        if (polygonsToDelete.length === 0) {
            status('Select one polygon, or Shift + Click multiple polygons first.');
            return;
        }

        // Never delete saved lot boundaries through this endpoint.
        if (polygonsToDelete.some(polygon =>
            polygon.classList.contains('saved-lot-polygon')
        )) {
            status('Saved boundaries must be deleted through a protected PHP endpoint.');
            return;
        }

        // Require valid candidate IDs for every selected detected polygon.
        const items = polygonsToDelete.map(polygon => ({
            polygon,
            candidateId: polygon.dataset.candidate || ''
        }));

        if (items.some(item => !item.candidateId)) {
            status('One or more selected polygons have no valid candidate ID.');
            return;
        }

        const uniqueItems = [
            ...new Map(items.map(item => [item.candidateId, item])).values()
        ];

        if (!confirm(
            `Permanently hide ${uniqueItems.length} detected polygon(s) from this map?`
        )) {
            return;
        }

        const areaId = Number(detectedOverlay.dataset.areaId || 0);
        deleteButton.disabled = true;

        try {
            // Process each candidate using the existing protected endpoint.
            for (const item of uniqueItems) {
                const response = await fetch('dismiss_boundary.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token':
    document.querySelector('meta[name="map-csrf"]')?.content || ''
                    },
                    body: JSON.stringify({
                        area_id: areaId,
                        candidate_id: item.candidateId
                    })
                });

                const result = await response.json();

                if (!response.ok || !result.ok) {
                    throw new Error(
                        result.message ||
                        `Failed to remove polygon ${item.candidateId}.`
                    );
                }

                item.polygon.remove();
            }

            clearSelection();
            mode = 'select';

            status(
                `${uniqueItems.length} detected polygon(s) removed successfully.`
            );
        } catch (error) {
            status(
                error.message ||
                'An error occurred while removing polygons. Some may already have been removed.'
            );
        } finally {
            deleteButton.disabled = false;
        }
    });
}



if (saveBoundaryForm) {
    saveBoundaryForm.addEventListener('submit', async event => {
        event.preventDefault();

        const selectedLot = lotSelect?.value || '';

        if (!selectedLot) {
            alert('Please select the correct lot.');
            return;
        }

        if (!selectedPolygon) {
            alert('Please select or draw a boundary first.');
            return;
        }

        let normalizedPoints;

        try {
            normalizedPoints = getNormalizedPoints(selectedPolygon);
        } catch (error) {
            alert(error.message || 'Unable to read boundary coordinates.');
            return;
        }

        if (
            normalizedPoints.length < 3 ||
            normalizedPoints.length > 500 ||
            normalizedPoints.some(p =>
                !Number.isFinite(p.x) ||
                !Number.isFinite(p.y) ||
                p.x < 0 || p.x > 1 ||
                p.y < 0 || p.y > 1
            )
        ) {
            alert('Invalid boundary. Please draw at least 3 valid points.');
            return;
        }

        const pointsInput = document.getElementById(
            'editor-polygon-points'
        );

        if (pointsInput) {
            pointsInput.value = JSON.stringify(
                normalizedPoints.map(p => [p.x, p.y])
            );
        }

        const saveButton = document.getElementById('save-boundary');

        if (saveButton) {
            saveButton.disabled = true;
            saveButton.textContent = 'Saving...';
        }

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: new FormData(saveBoundaryForm),
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json'
                }
            });

            const responseText = await response.text();
            let result;

            try {
                result = JSON.parse(responseText);
            } catch {
                throw new Error(
                    'The server did not return valid JSON. Check PHP errors.'
                );
            }

            if (
                !response.ok ||
                result.ok !== true
            ) {
                throw new Error(
                    result.error || result.message || 'Failed to save boundary.'
                );
            }

            alert(result.message || 'Boundary saved successfully.');

            // Reload the same map area so the saved database shape is displayed.
            window.location.reload();

        } catch (error) {
            console.error('LOTMAP boundary save error:', error);
            alert(error.message || 'Failed to save boundary.');
               } finally {
            if (saveButton) {
                saveButton.disabled = false;
                saveButton.textContent = 'Save Boundary';
            }
        }
    });
}
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('addLotModal');
    const openButton = document.getElementById('openAddLotModal');

    if (!modal || !openButton) return;

    openButton.addEventListener('click', function () {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    });

    modal.querySelectorAll('[data-close-lot-modal]').forEach(function (button) {
        button.addEventListener('click', function () {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('assignOwnerModal');
    const openButton = document.getElementById('openAssignOwnerModal');

    if (modal && openButton) {
        openButton.addEventListener('click', function () {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        });

        modal.querySelectorAll('[data-close-owner-modal]').forEach(function (el) {
            el.addEventListener('click', function () {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
            });
        });
    }

    const clientSelect = document.getElementById('client-select');
    const clientFields = document.getElementById('new-client-fields');
    const firstName = document.getElementById('owner-first-name');
    const lastName = document.getElementById('owner-last-name');

    function updateClientFields() {
        if (!clientSelect || !clientFields) return;

        const existingClient = clientSelect.value !== '';
        clientFields.style.display = existingClient ? 'none' : 'block';

        if (firstName) firstName.required = !existingClient;
        if (lastName) lastName.required = !existingClient;
    }

    const ownershipType = document.getElementById('ownership-type');
    const installmentFields = document.getElementById('installment-fields');
    const firstDueDate = document.getElementById('first-due-date');
    const termMonths = document.getElementById('term-months');

    function updateInstallmentFields() {
        if (!ownershipType || !installmentFields) return;

        const installment = ownershipType.value === 'installment';
        installmentFields.style.display = installment ? 'block' : 'none';

        if (firstDueDate) firstDueDate.required = installment;
        if (termMonths) termMonths.required = installment;
    }

    if (clientSelect) {
        clientSelect.addEventListener('change', updateClientFields);
        updateClientFields();
    }

    if (ownershipType) {
        ownershipType.addEventListener('change', updateInstallmentFields);
        updateInstallmentFields();
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }
    }); 
});
}

const ownershipModal = document.getElementById('ownershipHistoryModal');

function closeOwnershipModal() {
    if (!ownershipModal) return;

    ownershipModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('ownership-modal-open');
}

function openOwnershipModal() {
    if (!ownershipModal) return;

    ownershipModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('ownership-modal-open');
}

if (ownershipModal) {
    ownershipModal.querySelectorAll('[data-close-ownership-modal]')
        .forEach(button => {
            button.addEventListener('click', closeOwnershipModal);
        });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeOwnershipModal();
        }
    });

    const params = new URLSearchParams(window.location.search);
    const lotId = Number(params.get('lot_id'));

    // Open only when PHP has loaded a valid selected lot.
    if (lotId > 0) {
        openOwnershipModal();
    }
}

/* =========================================================
   LOTMAP: NUMERIC LABEL + POLYGON RESIZE CONTROLS
   Add before the final })(); of assets/js/map.js
   ========================================================= */
const lotEditorSvg = document.getElementById('detected-lot-overlay');

if (lotEditorSvg && !document.getElementById('lot-transform-toolbar')) {
    let activeShape = null;
    let activeLabel = null;
    let draggingLabel = false;
    let labelDragOffset = { x: 0, y: 0 };

    const svgNS = 'http://www.w3.org/2000/svg';

    const toolbar = document.createElement('div');
    toolbar.id = 'lot-transform-toolbar';
    toolbar.style.cssText = [
        'display:flex',
        'flex-wrap:wrap',
        'gap:8px',
        'align-items:center',
        'padding:10px',
        'margin:10px 0',
        'border:1px solid #d1d5db',
        'border-radius:8px',
        'background:#fff'
    ].join(';');

    toolbar.innerHTML = `
        <strong style="width:100%">Lot Label &amp; Size Controls</strong>
        <button type="button" data-lot-tool="label">+ Numeric Label</button>
        <button type="button" data-lot-tool="rotate-left">↶ Rotate</button>
        <button type="button" data-lot-tool="rotate-right">↷ Rotate</button>
        <button type="button" data-lot-tool="label-smaller">A−</button>
        <button type="button" data-lot-tool="label-larger">A+</button>
        <button type="button" data-lot-tool="label-min">Minimize Label</button>
        <button type="button" data-lot-tool="label-max">Maximize Label</button>
        <button type="button" data-lot-tool="polygon-smaller">− Shrink Polygon</button>
        <button type="button" data-lot-tool="polygon-larger">+ Enlarge Polygon</button>
        <span id="lot-transform-message" role="status"
              style="font-size:13px;color:#374151"></span>
    `;

    lotEditorSvg.parentNode.insertBefore(toolbar, lotEditorSvg);

    toolbar.querySelectorAll('button').forEach(button => {
        button.style.cssText =
            'padding:7px 10px;border:1px solid #9ca3af;' +
            'border-radius:5px;background:#f9fafb;cursor:pointer';
    });

    const message = toolbar.querySelector('#lot-transform-message');

    function showMessage(text) {
        message.textContent = text;
    }

    function svgCoordinates(event) {
        const point = lotEditorSvg.createSVGPoint();
        point.x = event.clientX;
        point.y = event.clientY;

        const matrix = lotEditorSvg.getScreenCTM();
        if (!matrix) return null;

        return point.matrixTransform(matrix.inverse());
    }

    function selectShape(shape) {
        activeShape = shape;
        activeLabel = null;

        if (shape) {
            showMessage('Polygon selected. Choose a size control.');
        }
    }

    function getPolygonPoints(shape) {
        const raw = shape.getAttribute('points');
        if (!raw) return [];

        return raw.trim().split(/[\s,]+/).map(Number).reduce(
            (points, value, index, values) => {
                if (index % 2 === 0 && Number.isFinite(values[index + 1])) {
                    points.push({ x: value, y: values[index + 1] });
                }
                return points;
            },
            []
        );
    }

    function setPolygonPoints(shape, points) {
        shape.setAttribute(
            'points',
            points.map(point => `${point.x},${point.y}`).join(' ')
        );
    }

    function resizePolygon(factor) {
        if (!activeShape || !lotEditorSvg.contains(activeShape)) {
            showMessage('Select a polygon first.');
            return;
        }

        const points = getPolygonPoints(activeShape);

        if (points.length < 3) {
            showMessage('This polygon has no editable points.');
            return;
        }

        const center = {
            x: points.reduce((sum, point) => sum + point.x, 0) / points.length,
            y: points.reduce((sum, point) => sum + point.y, 0) / points.length
        };

        const resized = points.map(point => ({
            x: center.x + (point.x - center.x) * factor,
            y: center.y + (point.y - center.y) * factor
        }));

        setPolygonPoints(activeShape, resized);
        showMessage('Polygon resized. Save the boundary using your existing save workflow.');
    }

    function updateLabelTransform(label) {
        const x = Number(label.getAttribute('x')) || 0;
        const y = Number(label.getAttribute('y')) || 0;
        const angle = Number(label.dataset.angle) || 0;

        label.setAttribute(
            'transform',
            `rotate(${angle} ${x} ${y})`
        );
    }

    function addNumericLabel() {
        const value = window.prompt('Enter the numeric lot label:');

        if (value === null) return;

        const numericLabel = value.trim();

        if (!/^\d+$/.test(numericLabel)) {
            showMessage('Please enter numbers only, such as 1, 103, or 205.');
            return;
        }

        const viewBox = lotEditorSvg.viewBox.baseVal;
        const x = viewBox.width ? viewBox.width / 2 : 100;
        const y = viewBox.height ? viewBox.height / 2 : 100;

        const label = document.createElementNS(svgNS, 'text');
        label.textContent = numericLabel;
        label.setAttribute('x', x);
        label.setAttribute('y', y);
        label.setAttribute('font-size', '18');
        label.setAttribute('font-weight', '700');
        label.setAttribute('text-anchor', 'middle');
        label.setAttribute('dominant-baseline', 'middle');
        label.setAttribute('fill', '#111827');
        label.setAttribute('stroke', '#ffffff');
        label.setAttribute('stroke-width', '0.8');
        label.setAttribute('paint-order', 'stroke');
        label.style.cursor = 'move';
        label.style.userSelect = 'none';
        label.dataset.angle = '0';
        label.dataset.originalSize = '18';
        label.dataset.minimized = 'false';
        label.classList.add('lot-numeric-label');

        lotEditorSvg.appendChild(label);
        activeLabel = label;
        activeShape = null;

        showMessage('Label added. Drag it to move; select it to rotate or resize.');
    }

    lotEditorSvg.addEventListener('click', event => {
        const label = event.target.closest('.lot-numeric-label');

        if (label) {
            activeLabel = label;
            activeShape = null;
            showMessage(`Label "${label.textContent}" selected.`);
            return;
        }

        const shape = event.target.closest(
            '.detected-polygon, .saved-lot-polygon'
        );

        if (shape && lotEditorSvg.contains(shape)) {
            selectShape(shape);
        }
    });

    lotEditorSvg.addEventListener('pointerdown', event => {
        const label = event.target.closest('.lot-numeric-label');
        if (!label || event.button !== 0) return;

        const point = svgCoordinates(event);
        if (!point) return;

        activeLabel = label;
        activeShape = null;
        draggingLabel = true;

        labelDragOffset = {
            x: point.x - (Number(label.getAttribute('x')) || 0),
            y: point.y - (Number(label.getAttribute('y')) || 0)
        };

        label.setPointerCapture?.(event.pointerId);
        event.preventDefault();
    });

    lotEditorSvg.addEventListener('pointermove', event => {
        if (!draggingLabel || !activeLabel) return;

        const point = svgCoordinates(event);
        if (!point) return;

        activeLabel.setAttribute('x', point.x - labelDragOffset.x);
        activeLabel.setAttribute('y', point.y - labelDragOffset.y);
        updateLabelTransform(activeLabel);
    });

    lotEditorSvg.addEventListener('pointerup', () => {
        if (draggingLabel) {
            draggingLabel = false;
            showMessage('Label moved. Persistent saving will be connected to the database next.');
        }
    });

    toolbar.addEventListener('click', event => {
        const button = event.target.closest('[data-lot-tool]');
        if (!button) return;

        switch (button.dataset.lotTool) {
            case 'label':
                addNumericLabel();
                break;

            case 'rotate-left':
            case 'rotate-right': {
                if (!activeLabel) {
                    showMessage('Select a label first.');
                    break;
                }

                const angle = Number(activeLabel.dataset.angle) || 0;
                activeLabel.dataset.angle =
                    String(angle + (button.dataset.lotTool === 'rotate-right' ? 15 : -15));

                updateLabelTransform(activeLabel);
                showMessage(`Label rotation: ${activeLabel.dataset.angle}°`);
                break;
            }

            case 'label-smaller':
            case 'label-larger': {
                if (!activeLabel) {
                    showMessage('Select a label first.');
                    break;
                }

                const current = Number(activeLabel.getAttribute('font-size')) || 18;
                const next = button.dataset.lotTool === 'label-larger'
                    ? Math.min(current + 2, 96)
                    : Math.max(current - 2, 6);

                activeLabel.setAttribute('font-size', next);
                activeLabel.dataset.minimized = 'false';
                showMessage(`Label size: ${next}`);
                break;
            }

            case 'label-min':
            case 'label-max': {
                if (!activeLabel) {
                    showMessage('Select a label first.');
                    break;
                }

                const original = Number(activeLabel.dataset.originalSize) || 18;
                const minimized = button.dataset.lotTool === 'label-min';

                activeLabel.setAttribute(
                    'font-size',
                    minimized ? Math.max(6, original * 0.5) : original
                );

                activeLabel.dataset.minimized = String(minimized);
                showMessage(minimized ? 'Label minimized.' : 'Label restored to normal size.');
                break;
            }

            case 'polygon-smaller':
                resizePolygon(0.9);
                break;

            case 'polygon-larger':
                resizePolygon(1.1);
                break;
        }
    });
}

})();