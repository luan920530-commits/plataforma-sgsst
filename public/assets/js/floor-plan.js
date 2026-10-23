(() => {
    'use strict';

    const root = document.getElementById('floor-plan-editor');
    const svg = document.getElementById('floor-plan-canvas');
    const form = document.getElementById('floor-plan-form');
    if (!root || !svg || !form) return;

    const NS = 'http://www.w3.org/2000/svg';
    const tools = ['wall', 'door', 'route', 'exit', 'extinguisher', 'first_aid', 'alarm', 'assembly', 'text'];
    const colors = ['#0f172a', '#334155', '#64748b', '#2563eb', '#15803d', '#16a34a', '#dc2626', '#f97316'];
    const labels = {
        wall: 'Muro', door: 'Puerta', route: 'Ruta de evacuación', exit: 'SALIDA',
        extinguisher: 'Extintor', first_aid: 'Botiquín', alarm: 'Alarma',
        assembly: 'Punto de encuentro', text: 'Texto', select: 'Selección'
    };
    const defaultColors = {
        wall: '#334155', door: '#334155', route: '#15803d', exit: '#15803d',
        extinguisher: '#dc2626', first_aid: '#15803d', alarm: '#f97316',
        assembly: '#15803d', text: '#334155'
    };
    const fallback = (() => {
        try {
            const value = JSON.parse(root.dataset.fallbackDesign || '');
            return value && Array.isArray(value.elements) ? value : {format: 1, width: 1000, height: 650, grid: 20, elements: []};
        } catch (error) {
            return {format: 1, width: 1000, height: 650, grid: 20, elements: []};
        }
    })();
    let sequence = Date.now();
    let design = readDesign();
    let activeTool = 'select';
    let selectedId = null;
    let drag = null;
    let history = [];
    let future = [];
    let zoom = 1;
    let showGrid = true;
    let snapEnabled = true;
    let renderPending = false;
    let metadataDirty = root.dataset.resumed === '1';
    let savedSnapshot = snapshot();

    const hidden = document.getElementById('floor-plan-data');
    const status = root.querySelector('[data-editor-status]');
    const cursorLabel = root.querySelector('[data-cursor-position]');
    const exportButton = form.querySelector('[data-export]');
    const exportResult = form.querySelector('[data-export-result]');
    const saveSummary = form.querySelector('[data-save-summary]');

    function readDesign() {
        try {
            const value = JSON.parse(root.dataset.design || '');
            if (!value || !Array.isArray(value.elements)) throw new Error('Estructura incompleta');
            return {
                format: 1,
                width: boundedNumber(value.width, 400, 2400, 1000),
                height: boundedNumber(value.height, 300, 1600, 650),
                grid: boundedNumber(value.grid, 5, 100, 20),
                elements: value.elements.filter(item => item && tools.includes(item.tool)).map(item => ({
                    ...item,
                    id: String(item.id || uniqueId()),
                    label: String(item.label || labels[item.tool] || ''),
                    color: colors.includes(item.color) ? item.color : defaultColors[item.tool],
                    x: Number(item.x), y: Number(item.y),
                    x2: item.x2 === undefined ? undefined : Number(item.x2),
                    y2: item.y2 === undefined ? undefined : Number(item.y2),
                    rotation: Number(item.rotation || 0), stroke: Number(item.stroke || 8)
                }))
            };
        } catch (error) {
            return JSON.parse(JSON.stringify(fallback));
        }
    }

    function uniqueId() {
        sequence += 1;
        return `el_${sequence.toString(36)}_${Math.random().toString(36).slice(2, 7)}`;
    }

    function boundedNumber(value, min, max, defaultValue) {
        const number = Number(value);
        return Number.isFinite(number) ? Math.min(max, Math.max(min, number)) : defaultValue;
    }

    function snapshot() {
        return JSON.stringify(design);
    }

    function copy(value) {
        return JSON.parse(JSON.stringify(value));
    }

    function selected() {
        return design.elements.find(item => item.id === selectedId) || null;
    }

    function svgNode(name, attributes = {}, text = null) {
        const node = document.createElementNS(NS, name);
        Object.entries(attributes).forEach(([key, value]) => {
            if (value !== undefined && value !== null) node.setAttribute(key, String(value));
        });
        if (text !== null) node.textContent = text;
        return node;
    }

    function scheduleRender() {
        if (renderPending) return;
        renderPending = true;
        window.requestAnimationFrame(() => {
            renderPending = false;
            render();
        });
    }

    function render() {
        svg.replaceChildren();
        svg.setAttribute('viewBox', `0 0 ${design.width} ${design.height}`);
        svg.setAttribute('width', design.width);
        svg.setAttribute('height', design.height);
        svg.style.width = `${Math.round(zoom * 100)}%`;
        svg.style.height = 'auto';

        const defs = svgNode('defs');
        const pattern = svgNode('pattern', {
            id: 'floor-grid', width: design.grid, height: design.grid, patternUnits: 'userSpaceOnUse'
        });
        pattern.appendChild(svgNode('path', {
            d: `M ${design.grid} 0 L 0 0 0 ${design.grid}`, fill: 'none',
            stroke: '#dbe4ef', 'stroke-width': 0.7
        }));
        defs.appendChild(pattern);
        svg.appendChild(defs);
        svg.appendChild(svgNode('rect', {width: design.width, height: design.height, fill: '#ffffff', 'data-canvas-background': '1'}));
        if (showGrid) {
            svg.appendChild(svgNode('rect', {width: design.width, height: design.height, fill: 'url(#floor-grid)', 'pointer-events': 'none'}));
        }
        design.elements.forEach(item => svg.appendChild(renderElement(item)));
        renderLineHandles();
        renderTitleBlock();
        updateInspector();
        syncHidden();
        refreshDirty();
    }

    function renderElement(item) {
        const isLine = item.tool === 'wall' || item.tool === 'route';
        const group = svgNode('g', {
            'data-element-id': item.id,
            class: `floor-element${item.id === selectedId ? ' is-selected' : ''}`,
            role: 'button', 'aria-label': `${labels[item.tool]}: ${item.label || ''}`
        });
        if (!isLine) {
            group.setAttribute('transform', `translate(${item.x} ${item.y}) rotate(${item.rotation || 0})`);
            group.appendChild(svgNode('circle', {r: 36, fill: 'transparent'}));
        } else {
            const cx = (item.x + item.x2) / 2;
            const cy = (item.y + item.y2) / 2;
            if (item.rotation) group.setAttribute('transform', `rotate(${item.rotation} ${cx} ${cy})`);
            group.appendChild(svgNode('line', {
                x1: item.x, y1: item.y, x2: item.x2, y2: item.y2,
                stroke: 'transparent', 'stroke-width': Math.max(18, item.stroke + 10), 'pointer-events': 'stroke'
            }));
        }

        if (item.tool === 'wall' || item.tool === 'route') {
            group.appendChild(svgNode('line', {
                x1: item.x, y1: item.y, x2: item.x2, y2: item.y2,
                stroke: item.color, 'stroke-width': item.stroke,
                'stroke-linecap': item.tool === 'wall' ? 'square' : 'round',
                'stroke-dasharray': item.tool === 'route' ? `${Math.max(10, item.stroke * 2)} ${Math.max(7, item.stroke)}` : undefined
            }));
            if (item.tool === 'route') group.appendChild(routeArrow(item));
        } else if (item.tool === 'door') {
            group.appendChild(svgNode('path', {
                d: 'M -20 20 V -20 H 20 M -20 20 A 40 40 0 0 1 20 -20',
                fill: 'none', stroke: item.color, 'stroke-width': Math.max(2, item.stroke / 2), 'stroke-linecap': 'round'
            }));
        } else if (item.tool === 'exit') {
            group.appendChild(svgNode('rect', {x: -28, y: -17, width: 56, height: 34, rx: 4, fill: item.color}));
            group.appendChild(svgNode('text', {x: 0, y: 5, 'text-anchor': 'middle', fill: '#ffffff', 'font-size': 13, 'font-weight': 700}, 'SALIDA'));
        } else if (item.tool === 'extinguisher') {
            group.appendChild(svgNode('rect', {x: -12, y: -18, width: 24, height: 38, rx: 6, fill: item.color}));
            group.appendChild(svgNode('path', {d: 'M -5 -18 V -25 H 8 M 7 -25 Q 22 -20 17 -7', fill: 'none', stroke: item.color, 'stroke-width': 4}));
            group.appendChild(svgNode('text', {x: 0, y: 7, 'text-anchor': 'middle', fill: '#ffffff', 'font-size': 16, 'font-weight': 700}, 'E'));
        } else if (item.tool === 'first_aid') {
            group.appendChild(svgNode('rect', {x: -22, y: -18, width: 44, height: 36, rx: 5, fill: '#ffffff', stroke: item.color, 'stroke-width': 3}));
            group.appendChild(svgNode('path', {d: 'M -12 0 H 12 M 0 -12 V 12', stroke: item.color, 'stroke-width': 7}));
        } else if (item.tool === 'alarm') {
            group.appendChild(svgNode('circle', {cx: 0, cy: 0, r: 19, fill: item.color}));
            group.appendChild(svgNode('path', {d: 'M -26 -12 Q -34 0 -26 12 M 26 -12 Q 34 0 26 12', fill: 'none', stroke: item.color, 'stroke-width': 3}));
            group.appendChild(svgNode('text', {x: 0, y: 6, 'text-anchor': 'middle', fill: '#ffffff', 'font-size': 17, 'font-weight': 700}, 'A'));
        } else if (item.tool === 'assembly') {
            group.appendChild(svgNode('circle', {cx: 0, cy: 0, r: 25, fill: '#ffffff', stroke: item.color, 'stroke-width': 4}));
            group.appendChild(svgNode('circle', {cx: 0, cy: -8, r: 5, fill: item.color}));
            group.appendChild(svgNode('path', {d: 'M -11 15 V 7 Q 0 -2 11 7 V 15 M -19 -17 L -10 -10 M 19 -17 L 10 -10', fill: 'none', stroke: item.color, 'stroke-width': 4, 'stroke-linecap': 'round'}));
        } else if (item.tool === 'text') {
            group.appendChild(svgNode('text', {
                x: 0, y: 0, fill: item.color, 'font-size': Math.max(8, item.stroke),
                'font-weight': 600, 'font-family': 'Arial, sans-serif'
            }, item.label));
        }

        if (!isLine && item.tool !== 'text' && item.label) {
            group.appendChild(svgNode('text', {
                x: 0, y: 48, 'text-anchor': 'middle', fill: item.color, 'font-size': 11,
                'font-weight': 600, 'font-family': 'Arial, sans-serif', stroke: '#ffffff',
                'stroke-width': 3, 'paint-order': 'stroke', 'stroke-linejoin': 'round'
            }, item.label));
        }
        if (item.id === selectedId && !isLine) {
            group.appendChild(svgNode('circle', {r: 36, fill: 'none', stroke: '#2563eb', 'stroke-width': 2, 'stroke-dasharray': '5 4', class: 'editor-only', 'pointer-events': 'none'}));
        }
        return group;
    }

    function routeArrow(item) {
        const angle = Math.atan2(item.y2 - item.y, item.x2 - item.x);
        const size = Math.max(12, item.stroke * 2.2);
        const ux = Math.cos(angle);
        const uy = Math.sin(angle);
        const px = -uy;
        const py = ux;
        const baseX = item.x2 - ux * size;
        const baseY = item.y2 - uy * size;
        const points = [
            [item.x2, item.y2],
            [baseX + px * size * 0.45, baseY + py * size * 0.45],
            [baseX - px * size * 0.45, baseY - py * size * 0.45]
        ].map(point => point.join(',')).join(' ');
        return svgNode('polygon', {points, fill: item.color});
    }

    function renderLineHandles() {
        const item = selected();
        if (!item || !['wall', 'route'].includes(item.tool)) return;
        [['start', item.x, item.y], ['end', item.x2, item.y2]].forEach(([endpoint, x, y]) => {
            svg.appendChild(svgNode('circle', {
                cx: x, cy: y, r: 7, fill: '#ffffff', stroke: '#2563eb', 'stroke-width': 3,
                'data-element-id': item.id, 'data-endpoint': endpoint, class: 'editor-only floor-endpoint'
            }));
        });
    }

    function renderTitleBlock() {
        const name = (document.getElementById('plan-name').value || 'Plano de emergencia').slice(0, 72);
        const scale = (document.getElementById('plan-scale').value || 'Referencial').slice(0, 48);
        const version = Number(root.dataset.version || 0);
        const group = svgNode('g', {'pointer-events': 'none', class: 'plan-title-block'});
        group.appendChild(svgNode('rect', {x: 12, y: 10, width: Math.min(design.width - 24, 560), height: 56, rx: 7, fill: '#ffffff', 'fill-opacity': 0.92, stroke: '#cbd5e1'}));
        group.appendChild(svgNode('text', {x: 24, y: 32, fill: '#0f172a', 'font-size': 16, 'font-weight': 700, 'font-family': 'Arial, sans-serif'}, name));
        group.appendChild(svgNode('text', {x: 24, y: 51, fill: '#475569', 'font-size': 10, 'font-family': 'Arial, sans-serif'}, `${root.dataset.plan} · ${scale}${version ? ` · v${version}` : ''} · Borrador, verificar en sitio`));
        svg.appendChild(group);
    }

    function pointFromEvent(event) {
        const rect = svg.getBoundingClientRect();
        return {
            x: Math.min(design.width, Math.max(0, (event.clientX - rect.left) * design.width / rect.width)),
            y: Math.min(design.height, Math.max(0, (event.clientY - rect.top) * design.height / rect.height))
        };
    }

    function adjusted(point, event) {
        if (!snapEnabled || event.shiftKey) return point;
        return {
            x: Math.min(design.width, Math.max(0, Math.round(point.x / design.grid) * design.grid)),
            y: Math.min(design.height, Math.max(0, Math.round(point.y / design.grid) * design.grid))
        };
    }

    function setTool(tool) {
        activeTool = tool;
        root.querySelectorAll('[data-tool]').forEach(button => {
            const active = button.dataset.tool === tool;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        svg.style.cursor = tool === 'select' ? 'default' : 'crosshair';
        setStatus(tool === 'select' ? 'Haz clic y arrastra para mover. En muros y rutas, arrastra los puntos azules para cambiar sus extremos.' : `${labels[tool]}: ${['wall', 'route'].includes(tool) ? 'arrastra sobre el lienzo para dibujar.' : 'haz clic sobre el lienzo para ubicarlo.'}`);
    }

    function createElement(tool, point, event) {
        const item = {
            id: uniqueId(), tool, x: point.x, y: point.y,
            label: labels[tool], color: defaultColors[tool], rotation: 0,
            stroke: tool === 'text' ? 18 : (tool === 'wall' ? 10 : 8)
        };
        if (['wall', 'route'].includes(tool)) {
            item.x2 = point.x + 140 <= design.width ? point.x + 140 : Math.max(0, point.x - 140);
            item.y2 = point.y;
        }
        design.elements.push(item);
        selectedId = item.id;
        drag = {mode: 'create', before: drag.before, start: adjusted(point, event), original: copy(item), moved: false};
        scheduleRender();
    }

    function onPointerDown(event) {
        if (event.button !== 0) return;
        const rawPoint = pointFromEvent(event);
        const point = adjusted(rawPoint, event);
        const endpointNode = event.target.closest('[data-endpoint]');
        const elementNode = event.target.closest('[data-element-id]');
        const before = snapshot();
        drag = null;

        if (activeTool === 'select') {
            if (!elementNode) {
                selectedId = null;
                render();
                return;
            }
            selectedId = elementNode.dataset.elementId;
            const item = selected();
            if (!item) return;
            drag = {
                mode: endpointNode ? `endpoint-${endpointNode.dataset.endpoint}` : 'move',
                before, start: point, original: copy(item), moved: false
            };
        } else {
            if (design.elements.length >= 1000) {
                setStatus('El plano alcanzó el máximo de 1.000 elementos. Elimina o simplifica elementos antes de añadir más.', true);
                return;
            }
            drag = {before};
            createElement(activeTool, point, event);
        }
        svg.setPointerCapture(event.pointerId);
        event.preventDefault();
        scheduleRender();
    }

    function onPointerMove(event) {
        const rawPoint = pointFromEvent(event);
        const point = adjusted(rawPoint, event);
        cursorLabel.textContent = `X ${Math.round(point.x)} · Y ${Math.round(point.y)}`;
        if (!drag) return;
        const item = selected();
        if (!item) return;
        drag.moved = true;
        if (drag.mode === 'create') {
            if (['wall', 'route'].includes(item.tool)) {
                item.x2 = point.x;
                item.y2 = point.y;
            } else {
                item.x = point.x;
                item.y = point.y;
            }
        } else if (drag.mode.startsWith('endpoint-')) {
            const endpoint = drag.mode === 'endpoint-start' ? 'start' : 'end';
            if (endpoint === 'start') {
                item.x = point.x;
                item.y = point.y;
            } else {
                item.x2 = point.x;
                item.y2 = point.y;
            }
        } else {
            const dx = point.x - drag.start.x;
            const dy = point.y - drag.start.y;
            if (['wall', 'route'].includes(item.tool)) {
                const minX = Math.min(drag.original.x, drag.original.x2);
                const maxX = Math.max(drag.original.x, drag.original.x2);
                const minY = Math.min(drag.original.y, drag.original.y2);
                const maxY = Math.max(drag.original.y, drag.original.y2);
                const boundedX = Math.min(design.width - maxX, Math.max(-minX, dx));
                const boundedY = Math.min(design.height - maxY, Math.max(-minY, dy));
                item.x = drag.original.x + boundedX;
                item.x2 = drag.original.x2 + boundedX;
                item.y = drag.original.y + boundedY;
                item.y2 = drag.original.y2 + boundedY;
            } else {
                item.x = Math.min(design.width, Math.max(0, drag.original.x + dx));
                item.y = Math.min(design.height, Math.max(0, drag.original.y + dy));
            }
        }
        scheduleRender();
        event.preventDefault();
    }

    function finishDrag(event, cancel = false) {
        if (!drag) return;
        const before = drag.before;
        if (cancel) {
            design = JSON.parse(before);
            selectedId = null;
        } else if (before !== snapshot()) {
            pushHistory(before);
        }
        drag = null;
        if (svg.hasPointerCapture(event.pointerId)) svg.releasePointerCapture(event.pointerId);
        render();
    }

    function pushHistory(before) {
        if (before === snapshot()) return;
        history.push(before);
        if (history.length > 60) history.shift();
        future = [];
        refreshDirty();
    }

    function undo() {
        if (!history.length) return;
        future.push(snapshot());
        design = JSON.parse(history.pop());
        if (!selected()) selectedId = null;
        render();
    }

    function redo() {
        if (!future.length) return;
        history.push(snapshot());
        design = JSON.parse(future.pop());
        if (!selected()) selectedId = null;
        render();
    }

    function deleteSelected() {
        const item = selected();
        if (!item) return;
        const before = snapshot();
        design.elements = design.elements.filter(element => element.id !== item.id);
        selectedId = null;
        pushHistory(before);
        render();
        setStatus(`${labels[item.tool]} eliminado. Puedes deshacer la acción.`);
    }

    function updateInspector() {
        const item = selected();
        const empty = root.querySelector('[data-no-selection]');
        const properties = root.querySelector('[data-selection-properties]');
        empty.classList.toggle('hidden', Boolean(item));
        properties.classList.toggle('hidden', !item);
        root.querySelector('[data-undo]').disabled = history.length === 0;
        root.querySelector('[data-redo]').disabled = future.length === 0;
        root.querySelector('[data-delete]').disabled = !item;
        root.querySelector('[data-canvas-width]').value = Math.round(design.width);
        root.querySelector('[data-canvas-height]').value = Math.round(design.height);
        root.querySelector('[data-canvas-grid]').value = Math.round(design.grid);
        if (!item) return;
        root.querySelector('[data-selected-type]').textContent = labels[item.tool];
        root.querySelector('[data-element-label]').value = item.label || '';
        root.querySelector('[data-element-color]').value = item.color;
        root.querySelector('[data-element-stroke]').value = item.stroke;
        root.querySelector('[data-element-rotation]').value = item.rotation || 0;
    }

    function changeSelected(property, value) {
        const item = selected();
        if (!item) return;
        const before = snapshot();
        if (property === 'stroke') value = boundedNumber(value, 2, 30, item.stroke);
        if (property === 'rotation') value = boundedNumber(value, -360, 360, item.rotation || 0);
        if (property === 'label') value = String(value).slice(0, 160);
        item[property] = value;
        pushHistory(before);
        render();
    }

    function changeCanvas(property, value) {
        const limits = {width: [400, 2400], height: [300, 1600], grid: [5, 100]};
        const [min, max] = limits[property];
        const next = Math.round(boundedNumber(value, min, max, design[property]));
        if (next === design[property]) return;
        const before = snapshot();
        design[property] = next;
        design.elements.forEach(item => {
            item.x = Math.min(design.width, Math.max(0, item.x));
            item.y = Math.min(design.height, Math.max(0, item.y));
            if (item.x2 !== undefined) item.x2 = Math.min(design.width, Math.max(0, item.x2));
            if (item.y2 !== undefined) item.y2 = Math.min(design.height, Math.max(0, item.y2));
        });
        pushHistory(before);
        render();
    }

    function refreshDirty() {
        const dirty = metadataDirty || snapshot() !== savedSnapshot;
        root.classList.toggle('has-unsaved-changes', dirty);
        if (exportButton) exportButton.disabled = dirty || root.dataset.canExport !== '1';
        if (saveSummary && dirty) saveSummary.textContent = 'Tienes cambios sin guardar. Guarda una nueva versión antes de exportar.';
        if (saveSummary && !dirty && Number(root.dataset.version || 0) > 0) saveSummary.textContent = `Versión ${root.dataset.version} guardada. Cada cambio posterior creará una versión nueva.`;
    }

    function syncHidden() {
        hidden.value = snapshot();
    }

    function setStatus(message, isError = false) {
        status.textContent = message;
        status.classList.toggle('text-rose-600', isError);
        status.classList.toggle('text-slate-400', !isError);
    }

    function metadataChanged() {
        metadataDirty = true;
        refreshDirty();
        scheduleRender();
    }

    async function exportPng() {
        if (!exportButton || root.dataset.canExport !== '1') return;
        if (metadataDirty || snapshot() !== savedSnapshot) {
            setStatus('Guarda los cambios como una nueva versión antes de exportar.', true);
            return;
        }
        exportButton.disabled = true;
        exportResult.textContent = '';
        setStatus('Generando PNG y preparando el archivo privado…');
        try {
            const blob = await renderPng();
            const data = new FormData();
            data.append('png', blob, fileName());
            const response = await fetch(root.dataset.exportUrl, {
                method: 'POST', body: data, credentials: 'same-origin',
                headers: {'X-CSRF-Token': form.querySelector('[name="_csrf"]').value, 'Accept': 'application/json'}
            });
            const payload = await response.json().catch(() => ({ok: false, message: 'La sesión pudo haber vencido. Recarga la página e inténtalo nuevamente.'}));
            if (!response.ok || !payload.ok) throw new Error(payload.message || 'No fue posible archivar el PNG.');
            triggerDownload(blob);
            setStatus('PNG generado y archivado correctamente.');
            exportResult.className = 'mt-2 text-[11px] leading-5 text-emerald-700';
            exportResult.textContent = `${payload.message} `;
            if (payload.url) {
                const link = document.createElement('a');
                link.className = 'font-semibold underline';
                link.href = payload.url;
                link.textContent = 'Abrir evidencia';
                exportResult.appendChild(link);
            }
        } catch (error) {
            setStatus('No se completó la exportación.', true);
            exportResult.className = 'mt-2 text-[11px] leading-5 text-rose-700';
            exportResult.textContent = error.message;
        } finally {
            refreshDirty();
        }
    }

    function renderPng() {
        return new Promise((resolve, reject) => {
            const clone = svg.cloneNode(true);
            clone.querySelectorAll('.editor-only').forEach(node => node.remove());
            clone.setAttribute('xmlns', NS);
            clone.setAttribute('width', design.width);
            clone.setAttribute('height', design.height);
            clone.removeAttribute('style');
            clone.removeAttribute('tabindex');
            const source = new XMLSerializer().serializeToString(clone);
            const image = new Image();
            image.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = design.width;
                canvas.height = design.height;
                const context = canvas.getContext('2d');
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, design.width, design.height);
                context.drawImage(image, 0, 0, design.width, design.height);
                canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('El navegador no pudo generar el PNG.')), 'image/png');
            };
            image.onerror = () => reject(new Error('El navegador no pudo representar el SVG para exportarlo.'));
            image.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(source)}`;
        });
    }

    function fileName() {
        const base = (document.getElementById('plan-name').value || 'plano-emergencia')
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^A-Za-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 90);
        return `${base || 'plano-emergencia'}-v${root.dataset.version}.png`;
    }

    function triggerDownload(blob) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = fileName();
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 2000);
    }

    root.querySelectorAll('[data-tool]').forEach(button => button.addEventListener('click', () => setTool(button.dataset.tool)));
    root.querySelector('[data-undo]').addEventListener('click', undo);
    root.querySelector('[data-redo]').addEventListener('click', redo);
    root.querySelector('[data-delete]').addEventListener('click', deleteSelected);
    root.querySelector('[data-grid-visible]').addEventListener('change', event => { showGrid = event.target.checked; render(); });
    root.querySelector('[data-snap]').addEventListener('change', event => { snapEnabled = event.target.checked; });
    root.querySelector('[data-zoom-out]').addEventListener('click', () => { zoom = Math.max(0.5, zoom - 0.25); root.querySelector('[data-zoom-label]').textContent = `${zoom * 100}%`; render(); });
    root.querySelector('[data-zoom-in]').addEventListener('click', () => { zoom = Math.min(2, zoom + 0.25); root.querySelector('[data-zoom-label]').textContent = `${zoom * 100}%`; render(); });
    root.querySelector('[data-element-label]').addEventListener('change', event => changeSelected('label', event.target.value));
    root.querySelector('[data-element-color]').addEventListener('change', event => changeSelected('color', event.target.value));
    root.querySelector('[data-element-stroke]').addEventListener('change', event => changeSelected('stroke', event.target.value));
    root.querySelector('[data-element-rotation]').addEventListener('change', event => changeSelected('rotation', event.target.value));
    root.querySelector('[data-canvas-width]').addEventListener('change', event => changeCanvas('width', event.target.value));
    root.querySelector('[data-canvas-height]').addEventListener('change', event => changeCanvas('height', event.target.value));
    root.querySelector('[data-canvas-grid]').addEventListener('change', event => changeCanvas('grid', event.target.value));
    ['plan-name', 'plan-scale', 'plan-notes'].forEach(id => document.getElementById(id).addEventListener('input', metadataChanged));
    svg.addEventListener('pointerdown', onPointerDown);
    svg.addEventListener('pointermove', onPointerMove);
    svg.addEventListener('pointerup', event => finishDrag(event));
    svg.addEventListener('pointercancel', event => finishDrag(event, true));
    if (exportButton) exportButton.addEventListener('click', exportPng);

    form.addEventListener('submit', event => {
        syncHidden();
        if (!design.elements.length) {
            event.preventDefault();
            setStatus('Añade al menos un muro, ruta, señal o texto antes de guardar.', true);
            return;
        }
        if (design.elements.some(item => item.tool === 'text' && !String(item.label).trim())) {
            event.preventDefault();
            setStatus('Los textos no pueden quedar vacíos. Selecciona el texto y completa su contenido.', true);
            return;
        }
        if (design.elements.some(item => ['wall', 'route'].includes(item.tool) && Math.hypot(item.x2 - item.x, item.y2 - item.y) < 1)) {
            event.preventDefault();
            setStatus('Los muros y las rutas deben tener una longitud visible.', true);
            return;
        }
        syncHidden();
    });

    document.addEventListener('keydown', event => {
        const editing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) || document.activeElement.isContentEditable;
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            if (form.querySelector('[data-save]')) form.requestSubmit();
            return;
        }
        if (editing) return;
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') {
            event.preventDefault();
            event.shiftKey ? redo() : undo();
        } else if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'y') {
            event.preventDefault();
            redo();
        } else if ((event.key === 'Delete' || event.key === 'Backspace') && selectedId) {
            event.preventDefault();
            deleteSelected();
        } else if (event.key === 'Escape') {
            selectedId = null;
            setTool('select');
            render();
        }
    });

    window.addEventListener('beforeunload', event => {
        if (metadataDirty || snapshot() !== savedSnapshot) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    setTool('select');
    render();
})();
