(() => {
    'use strict';

    const root = document.getElementById('floor-plan-editor');
    const svg = document.getElementById('floor-plan-canvas');
    const form = document.getElementById('floor-plan-form');
    if (!root || !svg || !form) return;

    const NS = 'http://www.w3.org/2000/svg';
    const palette = root.querySelector('[data-floor-palette]');
    const signLibrary = window.FLOOR_PLAN_SIGNS || {};
    const legacySigns = {exit: 'E002', extinguisher: 'F001', first_aid: 'E003', alarm: 'F005', assembly: 'E007'};
    const categoryColors = {emergency: '#237f52', fire: '#b71f2e', mandatory: '#005387', prohibition: '#b71f2e', warning: '#f9a800'};
    const tools = ['wall', 'door', 'route', 'exit', 'extinguisher', 'first_aid', 'alarm', 'assembly', 'text',
        ...Object.keys(signLibrary).map(code => `iso_${code.toLowerCase()}`)];
    const colors = ['#0f172a', '#334155', '#64748b', '#2563eb', '#15803d', '#16a34a', '#dc2626', '#f97316',
        '#237f52', '#b71f2e', '#005387', '#f9a800'];
    const labels = {
        wall: 'Muro', door: 'Puerta', route: 'Ruta de evacuación', exit: 'Salida de emergencia',
        extinguisher: 'Extintor de incendios', first_aid: 'Primeros auxilios', alarm: 'Pulsador de alarma de incendios',
        assembly: 'Punto de encuentro de evacuación', text: 'Texto y datos', select: 'Seleccionar y mover'
    };
    Object.entries(signLibrary).forEach(([code, sign]) => { labels[`iso_${code.toLowerCase()}`] = sign.name; });
    const signSources = new Map();
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
                    color: signCodeForTool(item.tool) ? defaultColorForTool(item.tool) : (colors.includes(item.color) ? item.color : '#334155'),
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

    function signCodeForTool(tool) {
        if (legacySigns[tool]) return legacySigns[tool];
        if (!/^iso_[efmpw][0-9]{3}$/.test(tool)) return null;
        const code = tool.slice(4).toUpperCase();
        return signLibrary[code] ? code : null;
    }

    function defaultColorForTool(tool) {
        const code = signCodeForTool(tool);
        if (code) return categoryColors[signLibrary[code].category] || '#334155';
        return tool === 'route' ? '#237f52' : '#334155';
    }

    function sourceSign(code) {
        if (signSources.has(code)) return signSources.get(code);
        const source = new DOMParser().parseFromString(signLibrary[code].svg, 'image/svg+xml');
        if (source.querySelector('parsererror') || source.documentElement.nodeName.toLowerCase() !== 'svg') {
            throw new Error(`No fue posible leer el pictograma ${code}.`);
        }
        signSources.set(code, source.documentElement);
        return source.documentElement;
    }

    /** Clona un SVG confiable y reasigna IDs para que varias copias no compartan clipPaths. */
    function cloneSign(code, instanceId) {
        const clone = document.importNode(sourceSign(code), true);
        const nodes = [clone, ...clone.querySelectorAll('*')];
        const ids = new Map();
        let sequence = 0;
        nodes.forEach(node => {
            const oldId = node.getAttribute('id');
            if (!oldId) return;
            sequence += 1;
            const newId = `fp-${instanceId}-${sequence}`;
            ids.set(oldId, newId);
            node.setAttribute('id', newId);
        });
        nodes.forEach(node => {
            [...node.attributes].forEach(attribute => {
                if (attribute.name === 'id') return;
                let value = attribute.value;
                ids.forEach((newId, oldId) => {
                    value = value.replaceAll(`url(#${oldId})`, `url(#${newId})`);
                    if (value === `#${oldId}`) value = `#${newId}`;
                });
                if (value !== attribute.value) node.setAttribute(attribute.name, value);
            });
        });
        return clone;
    }

    function appendIsoSign(group, item) {
        const code = signCodeForTool(item.tool);
        if (!code) return;
        const size = Math.max(40, item.stroke * 5);
        const sign = cloneSign(code, item.id);
        sign.setAttribute('x', -size / 2);
        sign.setAttribute('y', -size / 2);
        sign.setAttribute('width', size);
        sign.setAttribute('height', size);
        sign.setAttribute('class', 'floor-safety-sign');
        group.appendChild(sign);
    }

    function basicIcon(tool) {
        const icon = svgNode('svg', {viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 1.8, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true'});
        const paths = {
            select: [['path', {d: 'M5 3l13 9-6 1.5L9 20z'}]],
            wall: [['path', {d: 'M4 5h16v14H4zM4 10h16M4 15h16M9 5v5M15 10v5M9 15v4'}]],
            door: [['path', {d: 'M5 20V4h10v16M5 20h15M5 20a15 15 0 0 0 15-15'}]],
            route: [['path', {d: 'M3 12h15M13 6l6 6-6 6'}]],
            text: [['path', {d: 'M5 5h14M12 5v14M8 19h8'}]]
        };
        (paths[tool] || paths.select).forEach(([name, attributes]) => icon.appendChild(svgNode(name, attributes)));
        return icon;
    }

    function mountPaletteIcons() {
        root.querySelectorAll('[data-basic-icon]').forEach(slot => slot.replaceChildren(basicIcon(slot.dataset.basicIcon)));
        root.querySelectorAll('[data-sign-icon], [data-category-icon]').forEach(slot => {
            const sign = signLibrary[slot.dataset.signIcon || slot.dataset.categoryIcon];
            if (!sign) return;
            const image = document.createElement('img');
            image.alt = '';
            image.loading = 'lazy';
            image.decoding = 'async';
            image.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(sign.svg)}`;
            slot.replaceChildren(image);
        });
    }

    let catalogCategory = 'emergency';
    let tooltipTarget = null;
    const tooltip = document.createElement('div');
    tooltip.id = 'floor-tool-tooltip';
    tooltip.className = 'floor-tool-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    tooltip.hidden = true;
    document.body.appendChild(tooltip);

    function setCatalogOpen(open, categoryKey = null) {
        if (!palette) return;
        if (categoryKey && palette.querySelector(`[data-category-toggle="${categoryKey}"]`)) catalogCategory = categoryKey;
        palette.classList.toggle('is-catalog-open', open);
        const drawer = palette.querySelector('[data-catalog-drawer]');
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        const toggle = palette.querySelector('[data-catalog-toggle]');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.dataset.name = open ? 'Ocultar catálogo' : 'Mostrar catálogo';
        palette.querySelectorAll('[data-category-toggle]').forEach(button => {
            const current = open && button.dataset.categoryToggle === catalogCategory;
            button.classList.toggle('is-current', current);
            button.setAttribute('aria-expanded', current ? 'true' : 'false');
        });
        if (open && categoryKey) {
            palette.querySelectorAll('[data-tool-group]').forEach(group => { group.open = group.dataset.toolGroup === catalogCategory; });
            const categoryButton = palette.querySelector(`[data-category-toggle="${catalogCategory}"]`);
            palette.querySelector('[data-catalog-title]').textContent = categoryButton?.dataset.name || 'Señalización';
        }
        if (!open) {
            hideToolTooltip();
            if (drawer.contains(document.activeElement)) toggle.focus();
        }
    }

    function hideToolTooltip() {
        if (tooltipTarget) tooltipTarget.removeAttribute('aria-describedby');
        tooltipTarget = null;
        tooltip.hidden = true;
    }

    function showToolTooltip(target) {
        if (!target?.dataset.name) return;
        hideToolTooltip();
        tooltipTarget = target;
        const name = document.createElement('strong');
        name.textContent = target.dataset.name;
        tooltip.appendChild(name);
        const sign = target.querySelector('[data-sign-icon]');
        if (sign) {
            const reference = document.createElement('span');
            reference.textContent = `ISO 7010 ${sign.dataset.signIcon}`;
            tooltip.appendChild(reference);
        }
        if (target.dataset.description) {
            const description = document.createElement('p');
            description.textContent = target.dataset.description;
            tooltip.appendChild(description);
        }
        target.setAttribute('aria-describedby', tooltip.id);
        tooltip.hidden = false;
        const rect = target.getBoundingClientRect();
        const width = tooltip.offsetWidth || 240;
        const height = tooltip.offsetHeight || 80;
        let left = rect.right + 10;
        let top = rect.top;
        if (left + width > window.innerWidth - 8) {
            left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));
            top = rect.bottom + 8;
        }
        tooltip.style.left = `${Math.max(8, left)}px`;
        tooltip.style.top = `${Math.max(8, Math.min(top, window.innerHeight - height - 8))}px`;
    }

    function mountPaletteInteractions() {
        if (!palette) return;
        const tooltipSelector = '[data-tool], [data-category-toggle], [data-catalog-toggle], [data-search-toggle], [data-catalog-close]';
        palette.addEventListener('pointerover', event => {
            if (event.pointerType === 'touch') return;
            const target = event.target.closest(tooltipSelector);
            if (target && !target.contains(event.relatedTarget)) showToolTooltip(target);
        });
        palette.addEventListener('pointerout', event => {
            const target = event.target.closest(tooltipSelector);
            if (target && !target.contains(event.relatedTarget)) hideToolTooltip();
        });
        palette.addEventListener('focusin', event => {
            const target = event.target.closest(tooltipSelector);
            if (target) showToolTooltip(target);
        });
        palette.addEventListener('focusout', event => {
            const target = event.target.closest(tooltipSelector);
            if (target && !target.contains(event.relatedTarget)) hideToolTooltip();
        });
        palette.querySelector('[data-catalog-toggle]').addEventListener('click', () => {
            setCatalogOpen(!palette.classList.contains('is-catalog-open'), catalogCategory);
        });
        palette.querySelector('[data-catalog-close]').addEventListener('click', () => setCatalogOpen(false));
        palette.querySelector('[data-search-toggle]').addEventListener('click', () => {
            setCatalogOpen(true, catalogCategory);
            window.setTimeout(() => palette.querySelector('[data-tool-search]').focus(), 0);
        });
        palette.querySelectorAll('[data-category-toggle]').forEach(button => button.addEventListener('click', () => {
            const key = button.dataset.categoryToggle;
            const opening = !palette.classList.contains('is-catalog-open') || catalogCategory !== key;
            const input = palette.querySelector('[data-tool-search]');
            input.value = '';
            filterTools();
            setCatalogOpen(opening, key);
        }));
        setCatalogOpen(false);
    }

    function normalizeSearch(value) {
        return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    }

    function filterTools() {
        const input = root.querySelector('[data-tool-search]');
        const query = normalizeSearch(input.value);
        let visible = 0;
        root.querySelectorAll('[data-tool-group]').forEach(group => {
            let groupVisible = 0;
            group.querySelectorAll('[data-tool]').forEach(button => {
                const matches = !query || normalizeSearch(button.dataset.search).includes(query);
                button.classList.toggle('hidden', !matches);
                if (matches) groupVisible += 1;
            });
            group.classList.toggle('hidden', groupVisible === 0);
            visible += groupVisible;
            if (group.tagName === 'DETAILS') {
                if (query) {
                    if (!group.dataset.wasOpen) group.dataset.wasOpen = group.open ? '1' : '0';
                    group.open = groupVisible > 0;
                } else if (group.dataset.wasOpen) {
                    group.open = group.dataset.wasOpen === '1';
                    delete group.dataset.wasOpen;
                }
            }
        });
        root.querySelector('[data-tool-empty]').classList.toggle('hidden', visible !== 0);
        root.querySelector('[data-search-count]').textContent = query ? `${visible}` : '';
        const title = root.querySelector('[data-catalog-title]');
        if (query) title.textContent = `Resultados · ${visible}`;
        else {
            const category = root.querySelector(`[data-category-toggle="${catalogCategory}"]`);
            title.textContent = category?.dataset.name || 'Señalización';
        }
        input.setAttribute('aria-label', query ? `${visible} señales encontradas` : 'Buscar señal por nombre o código ISO');
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
        } else if (item.tool === 'text') {
            group.appendChild(svgNode('text', {
                x: 0, y: 0, fill: item.color, 'font-size': Math.max(8, item.stroke),
                'font-weight': 600, 'font-family': 'Arial, sans-serif'
            }, item.label));
        } else if (signCodeForTool(item.tool)) {
            appendIsoSign(group, item);
        }

        const signSize = signCodeForTool(item.tool) ? Math.max(40, item.stroke * 5) : 72;
        if (!isLine && item.tool !== 'text' && item.label) {
            group.appendChild(svgNode('text', {
                x: 0, y: Math.max(48, signSize / 2 + 16), 'text-anchor': 'middle', fill: item.color, 'font-size': 11,
                'font-weight': 600, 'font-family': 'Arial, sans-serif', stroke: '#ffffff',
                'stroke-width': 3, 'paint-order': 'stroke', 'stroke-linejoin': 'round'
            }, item.label));
        }
        if (item.id === selectedId && !isLine) {
            group.appendChild(svgNode('circle', {r: Math.max(36, signSize / 2 + 6), fill: 'none', stroke: '#2563eb', 'stroke-width': 2, 'stroke-dasharray': '5 4', class: 'editor-only', 'pointer-events': 'none'}));
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
        if (tool !== 'select' && !tools.includes(tool)) {
            setStatus('La biblioteca de señalización no se cargó completa. Recarga la página antes de continuar.', true);
            return;
        }
        activeTool = tool;
        let activeButton = null;
        root.querySelectorAll('[data-tool]').forEach(button => {
            const active = button.dataset.tool === tool;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            if (active) activeButton = button;
        });
        const code = signCodeForTool(tool);
        const category = code ? signLibrary[code].category : null;
        root.querySelectorAll('[data-category-toggle]').forEach(button => {
            button.classList.toggle('has-active-tool', button.dataset.categoryToggle === category);
        });
        const name = activeButton?.dataset.name || labels[tool] || tool;
        const description = activeButton?.dataset.description || 'Selecciona y edita elementos del plano.';
        root.querySelector('[data-active-tool-name]').textContent = name;
        root.querySelector('[data-active-tool-description]').textContent = description;
        root.querySelector('[data-canvas-tool]').textContent = name;
        svg.style.cursor = tool === 'select' ? 'default' : 'crosshair';
        setStatus(tool === 'select' ? 'Haz clic y arrastra para mover. En muros y rutas, arrastra los puntos azules para cambiar sus extremos.' : `${name}: ${['wall', 'route'].includes(tool) ? 'arrastra sobre el lienzo para dibujar.' : 'haz clic sobre el lienzo para ubicarlo.'}`);
    }

    function createElement(tool, point, event) {
        const item = {
            id: uniqueId(), tool, x: point.x, y: point.y,
            label: labels[tool], color: defaultColorForTool(tool), rotation: 0,
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
        root.querySelector('[data-color-field]').classList.add('hidden');
        root.querySelector('[data-standard-color-note]').classList.add('hidden');
        if (!item) return;
        const signCode = signCodeForTool(item.tool);
        root.querySelector('[data-selected-type]').textContent = `${labels[item.tool]}${signCode ? ` · ISO 7010 ${signCode}` : ''}`;
        root.querySelector('[data-element-label]').value = item.label || '';
        root.querySelector('[data-element-color]').value = item.color;
        root.querySelector('[data-element-color]').disabled = Boolean(signCode);
        root.querySelector('[data-color-field]').classList.toggle('hidden', Boolean(signCode));
        root.querySelector('[data-standard-color-note]').classList.toggle('hidden', !signCode);
        root.querySelector('[data-element-stroke]').value = item.stroke;
        root.querySelector('[data-element-rotation]').value = item.rotation || 0;
    }

    function changeSelected(property, value) {
        const item = selected();
        if (!item || (property === 'color' && signCodeForTool(item.tool))) return;
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

    const toolSearch = root.querySelector('[data-tool-search]');
    toolSearch.addEventListener('input', filterTools);
    toolSearch.addEventListener('keydown', event => {
        if (event.key === 'Enter') event.preventDefault();
        if (event.key === 'Escape') { toolSearch.value = ''; filterTools(); }
    });
    root.querySelectorAll('[data-tool]').forEach(button => button.addEventListener('click', () => {
        setTool(button.dataset.tool);
        if (button.closest('.floor-tool-rail')) setCatalogOpen(false);
    }));
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
        if (event.key === 'Escape' && palette?.classList.contains('is-catalog-open') && document.activeElement !== toolSearch) {
            setCatalogOpen(false);
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

    mountPaletteIcons();
    mountPaletteInteractions();
    setTool('select');
    render();
})();
