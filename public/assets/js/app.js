// Department colour palette
const DEPT_COLORS = {
    pms:        '#3b82f6',
    pm:         '#3b82f6',
    qa:         '#8b5cf6',
    developers: '#10b981',
    developer:  '#10b981',
    dev:        '#10b981',
    hr:         '#f59e0b',
    growth:     '#e8315b',
    bdr:        '#06b6d4',
    founders:   '#cc2f09',
    cto:        '#94a3b8',
    ceo:        '#94a3b8',
    coo:        '#94a3b8',
    gm:         '#94a3b8',
    default:    '#7a8098',
};

function deptColor(name) {
    if (!name) return DEPT_COLORS.default;
    const key = name.toLowerCase().trim();
    if (DEPT_COLORS[key]) return DEPT_COLORS[key];
    for (const [k, v] of Object.entries(DEPT_COLORS)) {
        if (key.includes(k)) return v;
    }
    return DEPT_COLORS.default;
}

// ── Security helpers ────────────────────────────────────────────────────────
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function isValidUrl(str) {
    if (!str) return false;
    try {
        const u = new URL(str);
        return u.protocol === 'https:' || u.protocol === 'http:';
    } catch { return false; }
}

// Photo sources may be relative (e.g. "avatar.php?id=10000"), so resolve them
// against the page URL before validating — unlike isValidUrl, which expects
// absolute URLs (used for external links like LinkedIn).
function isValidImgSrc(str) {
    if (!str) return false;
    try {
        const u = new URL(str, window.location.href);
        return u.protocol === 'https:' || u.protocol === 'http:';
    } catch { return false; }
}

// ── Data loading & tree restructuring ───────────────────────────────────────
function processRows(rows) {
    rows = rows.filter(r => r.id && String(r.id).trim());
    return rows.map(r => {
        // Fix comma-split names (e.g. "Zenyk, Haiduk" parsed into first_name)
        if (r.first_name && r.first_name.includes(',')) {
            const [a, b] = r.first_name.split(',').map(s => s.trim());
            r.first_name = a;
            if (!r.last_name && b) r.last_name = b;
        }
        // Fix QA category node mislabelled as BDR (id=8) in legacy CSV
        if (String(r.id) === '8' && r.first_name === 'QA' && r.department_name === 'BDR') {
            r.department_name = 'QA';
        }
        return r;
    });
}

async function loadData() {
    // Data is injected server-side by index.php (window.ORG_DATA).
    if (typeof window !== 'undefined' && Array.isArray(window.ORG_DATA)) {
        return processRows(window.ORG_DATA);
    }
    console.error('window.ORG_DATA missing — this page must be served by index.php');
    return [];
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function initials(first, last) {
    return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

// A "group node" is a category placeholder (no last_name, name matches dept list)
function isGroupNode(person) {
    const groupNames = ['pms', 'qa', 'developers', 'developer', 'hr', 'growth', 'bdr', 'founders', 'code.store'];
    const name = (person.first_name || '').toLowerCase().trim();
    return groupNames.includes(name) && !person.last_name;
}

// ── LinkedIn SVG ──────────────────────────────────────────────────────────────
const LI_SVG = `<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
  <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
</svg>`;

// ── App state ──────────────────────────────────────────────────────────────────
let chart;
let allData     = [];
let activeDept  = 'all';
let searchQuery = '';
let isCompact      = localStorage.getItem('orgchart_compact') !== 'false'; // default true
let isTreeExpanded = false;

// ── Filters ────────────────────────────────────────────────────────────────────
function getFilteredData() {
    let data = allData;

    if (activeDept !== 'all') {
        const deptIds = new Set(
            data.filter(r => (r.department_name || '').toLowerCase().trim() === activeDept).map(r => r.id)
        );
        const keep = new Set(deptIds);
        function addParents(id) {
            const node = data.find(r => r.id === id);
            if (!node || !node.parentId) return;
            keep.add(node.parentId);
            addParents(node.parentId);
        }
        deptIds.forEach(addParents);
        data = data.filter(r => keep.has(r.id));
    }

    if (searchQuery) {
        const q = searchQuery.toLowerCase();
        const matched = new Set(
            data.filter(r => {
                const full = `${r.first_name} ${r.last_name} ${r.department_name} ${r.description}`.toLowerCase();
                return full.includes(q);
            }).map(r => r.id)
        );
        function addParents(id) {
            const node = data.find(r => r.id === id);
            if (!node || !node.parentId) return;
            matched.add(node.parentId);
            addParents(node.parentId);
        }
        [...matched].forEach(addParents);
        data = data.filter(r => matched.has(r.id));
    }

    return data;
}

// Build id → [childIds] map from current dataset
function buildChildrenMap(data) {
    const map = new Map();
    data.forEach(r => {
        if (r.parentId) {
            if (!map.has(r.parentId)) map.set(r.parentId, []);
            map.get(r.parentId).push(r.id);
        }
    });
    return map;
}

// ── Chart render ──────────────────────────────────────────────────────────────
function renderChart(data) {
    const noResults = document.getElementById('no-results');

    if (data.length === 0) {
        noResults.classList.add('visible');
        if (chart) chart.data([]).render();
        return;
    }
    noResults.classList.remove('visible');

    const peopleCount = data.filter(r => !isGroupNode(r)).length;
    document.getElementById('people-count').textContent = `${peopleCount} people`;

    const childrenMap = buildChildrenMap(data);

    if (!chart) {
        // Remove any SVG left behind by a previous chart instance
        d3.select('#chart-container').selectAll('svg').remove();

        // d3-org-chart mutates data objects directly (_expanded etc.).
        // Clear that state so initialExpandLevel(1) applies cleanly on the fresh instance.
        allData.forEach(d => { delete d._expanded; });

        chart = new d3.OrgChart()
            .container('#chart-container')
            .nodeWidth(() => 260)
            .nodeHeight(() => 100)
            .compactMarginBetween(() => 60)
            .compactMarginPair(() => 32)
            .siblingsMargin(() => 48)
            .childrenMargin(() => 60)
            .neighbourMargin(() => 60)
            .compact(isCompact)
            .initialExpandLevel(1)
            .rootMargin(40)
            .duration(300)
            .setActiveNodeCentered(false)
            // ── Click handling strategy ─────────────────────────────────────
            // node-button-g is positioned via layoutBindings.top.buttonX/Y (default:
            // width/2, height → translate(130,96) = bottom-center of card).
            // We override buttonX/Y to 0,0 so the group sits at card origin, then
            // set nodeButtonWidth/Height to cover the full card (260×96).
            // The invisible rect (pointer-events:all, opacity:0) then intercepts every
            // click on a parent card and fires the library's built-in expand/collapse.
            // Leaf nodes: library sets display:none on node-button-g automatically.
            .nodeButtonWidth(() => 260)
            .nodeButtonHeight(() => 96)
            .nodeButtonX(() => 0)
            .nodeButtonY(() => 0)
            .buttonContent(() => '')
            .linkUpdate(function () {
                // Respect light/dark theme via CSS variable
                const linkColor = getComputedStyle(document.documentElement)
                    .getPropertyValue('--link-color').trim() || 'rgba(255,255,255,0.07)';
                d3.select(this).attr('stroke', linkColor).attr('stroke-width', 1.5);
            })
            .nodeContent((node) => {
                const p       = node.data;
                const isGroup = isGroupNode(p);
                const hasKids = childrenMap.has(p.id);
                // _expanded is the canonical state flag mutated by the library
                const isOpen  = !!p._expanded;

                const fullName = escapeHtml(
                    isGroup
                        ? (p.first_name || p.department_name || 'Group')
                        : ([p.first_name, p.last_name].filter(Boolean).join(' ') || 'Unknown')
                );
                const role  = escapeHtml(p.department_name || '');
                const ini   = escapeHtml(initials(p.first_name, p.last_name));
                const color = deptColor(p.department_name);

                const photoHTML = isGroup
                    ? ''
                    : (isValidImgSrc(p.img_url)
                        ? `<img class="node-photo" src="${escapeHtml(p.img_url)}" alt="${fullName}"
                              onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                           <div class="node-avatar" style="display:none;--dept-color:${color}">${ini}</div>`
                        : `<div class="node-avatar" style="--dept-color:${color}">${ini}</div>`);

                // LinkedIn: position:relative + z-index lifts the <a> above the
                // node-button-rect overlay so the link remains clickable
                const liHTML = !isGroup && isValidUrl(p.linkedin_url)
                    ? `<a class="node-li" href="${escapeHtml(p.linkedin_url)}"
                          target="_blank" rel="noopener noreferrer"
                          style="position:relative;z-index:10"
                          title="LinkedIn">${LI_SVG}</a>`
                    : '';

                const chevron = hasKids
                    ? `<div class="node-expand-icon">${isOpen ? '▾' : '▸'}</div>`
                    : '';

                const cardClass = [
                    'node-card',
                    isGroup            ? 'is-group'     : '',
                    hasKids            ? 'has-children' : '',
                    hasKids && !isOpen ? 'collapsed'    : '',
                ].filter(Boolean).join(' ');

                //${liHTML}
                return `
                    <div class="${cardClass}" style="--dept-color:${color}" data-id="${escapeHtml(p.id || '')}">
                        ${photoHTML}
                        <div class="node-info">
                            <div class="node-name">${fullName}</div>
                            <div class="node-role">${role}</div>
                        </div>
                        ${chevron}
                    </div>
                `;
            })
            .onNodeClick((node) => {
                // Fires only for leaf nodes (node-button-g is hidden for them).
                // Parent nodes are handled by the button overlay → onExpandOrCollapse.
                if (!isGroupNode(node.data) && !childrenMap.has(node.data.id)) {
                    openModal(node.data);
                }
            })
            .onExpandOrCollapse(() => {
                // Re-render to flip chevron ▸ ↔ ▾ after state change
                chart.render();
            });
    }

    chart.data(data).render();

    // Patch layoutBindings once after first render to move node-button-g to
    // card origin (0,0). This must run after render() because the chart state
    // needs to be initialised first.
    if (!chart._buttonPatchApplied) {
        const bindings = chart.layoutBindings();
        bindings.top.buttonX = () => 0;
        bindings.top.buttonY = () => 0;
        chart.layoutBindings(bindings).render();
        chart._buttonPatchApplied = true;
    }

}

// ── Dept filter dropdown options ───────────────────────────────────────────────
function buildDeptFilters() {
    const depts = [...new Set(
        allData
            .filter(r => !isGroupNode(r) && r.department_name)
            .map(r => r.department_name.trim())
    )].sort();

    const menu     = document.getElementById('dept-menu');
    const dropdown = document.getElementById('dept-dropdown');
    const trigger  = document.getElementById('dept-trigger');

    depts.forEach(dept => {
        const btn         = document.createElement('button');
        btn.className     = 'dept-option';
        btn.dataset.dept  = dept.toLowerCase();
        btn.dataset.label = dept;
        btn.setAttribute('role', 'option');
        const color       = deptColor(dept);
        btn.innerHTML     = `<span class="dot" style="background:${color}"></span>${escapeHtml(dept)}`;
        btn.addEventListener('click', () => {
            chart = null; // collapse tree before re-rendering
            setDept(dept.toLowerCase());
            dropdown.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
        });
        menu.appendChild(btn);
    });
}

function setDept(dept) {
    activeDept = dept;

    // Update active state in menu
    document.querySelectorAll('.dept-option').forEach(b => {
        b.classList.toggle('active', b.dataset.dept === dept);
    });

    // Update trigger label + dot
    const triggerLabel = document.getElementById('dept-trigger-label');
    const triggerDot   = document.getElementById('dept-trigger-dot');
    const activeOption = document.querySelector(`.dept-option[data-dept="${dept}"]`);
    if (activeOption) {
        triggerLabel.textContent        = activeOption.dataset.label || dept;
        const dot                       = activeOption.querySelector('.dot');
        triggerDot.style.background     = dot ? dot.style.background : 'var(--text-muted)';
    }

    applyFilters();
}

function applyFilters() {
    const isFiltered = activeDept !== 'all' || !!searchQuery;

    // When clearing all filters, force chart recreation so initialExpandLevel(1) is reapplied
    if (!isFiltered) chart = null;

    renderChart(getFilteredData());

    // Expand all nodes so matching people are visible
    if (isFiltered && chart) {
        chart.expandAll().render();
        isTreeExpanded = true;
    } else {
        isTreeExpanded = false;
    }
    syncTreeBtn();
}

// ── Modal ─────────────────────────────────────────────────────────────────────
function openModal(person) {
    const fullName = [person.first_name, person.last_name].filter(Boolean).join(' ') || 'Unknown';
    const color    = deptColor(person.department_name);

    document.getElementById('modal-name').textContent        = fullName;
    document.getElementById('modal-dept-text').textContent   = person.department_name || '';
    //document.getElementById('modal-dept-dot').style.background = color;
    document.getElementById('modal-description').textContent = person.description || '';

    const photo  = document.getElementById('modal-photo');
    const avatar = document.getElementById('modal-avatar');

    if (person.img_url && isValidImgSrc(person.img_url)) {
        photo.src            = person.img_url;
        photo.alt            = fullName;
        photo.style.display  = 'block';
        avatar.style.display = 'none';
        photo.onerror = () => {
            photo.style.display  = 'none';
            avatar.style.display = 'flex';
        };
    } else {
        photo.style.display  = 'none';
        avatar.style.display = 'flex';
    }

    avatar.textContent = initials(person.first_name, person.last_name);
    avatar.style.color = color;

    const li = document.getElementById('modal-linkedin');
    if (isValidUrl(person.linkedin_url)) {
        li.href = person.linkedin_url;
        li.classList.remove('hidden');
    } else {
        li.classList.add('hidden');
    }

    document.getElementById('modal-backdrop').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('modal-backdrop').classList.add('hidden');
}

// ── Dept filter wiring ────────────────────────────────────────────────────────
function initFilters() {
    const dropdown = document.getElementById('dept-dropdown');
    const trigger  = document.getElementById('dept-trigger');
    const menu     = document.getElementById('dept-menu');

    // Toggle open/close
    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = dropdown.classList.toggle('open');
        trigger.setAttribute('aria-expanded', String(isOpen));
    });

    // Close when clicking outside
    document.addEventListener('click', () => {
        dropdown.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
    });

    // Prevent menu clicks from bubbling to the document handler
    menu.addEventListener('click', (e) => e.stopPropagation());

    // "All" option (static in HTML)
    menu.querySelector('[data-dept="all"]').addEventListener('click', () => {
        chart = null; // collapse tree before re-rendering
        setDept('all');
        dropdown.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
    });
}

// ── Search ────────────────────────────────────────────────────────────────────
function initSearch() {
    const input    = document.getElementById('search');
    const clearBtn = document.getElementById('search-clear');
    let timer;

    input.addEventListener('input', () => {
        clearTimeout(timer);
        clearBtn.classList.toggle('visible', !!input.value);
        timer = setTimeout(() => {
            searchQuery = input.value.trim();
            applyFilters();
        }, 180);
    });

    clearBtn.addEventListener('click', () => {
        input.value = '';
        input.focus();
        clearBtn.classList.remove('visible');
        searchQuery = '';
        chart = null; // collapse tree before re-rendering
        applyFilters();
    });
}

// ── Tree expand/collapse toggle ───────────────────────────────────────────────
function syncTreeBtn() {
    const btn = document.getElementById('btn-tree');
    btn.textContent = isTreeExpanded ? 'COLLAPSE' : 'EXPAND';
    btn.title       = isTreeExpanded ? 'Collapse all' : 'Expand all';
}

// ── Controls (zoom + theme + compact toggle) ──────────────────────────────────
function initControls() {
    document.getElementById('btn-zoom-in').addEventListener('click',  () => chart?.zoomIn());
    document.getElementById('btn-zoom-out').addEventListener('click', () => chart?.zoomOut());
    document.getElementById('btn-fit').addEventListener('click',      () => chart?.fit());

    document.getElementById('btn-tree').addEventListener('click', () => {
        if (!chart) return;
        isTreeExpanded = !isTreeExpanded;
        if (isTreeExpanded) {
            chart.expandAll().render();
        } else {
            // Recreate the chart so initialExpandLevel(1) is applied cleanly
            chart = null;
            renderChart(getFilteredData());
        }
        syncTreeBtn();
    });

    // ── Theme ──────────────────────────────────────────────────────────────
    let isLight = localStorage.getItem('orgchart_theme') !== 'dark'; // default: light
    const themeBtn = document.getElementById('btn-theme');

    // Apply saved theme on load
    document.documentElement.classList.toggle('light', isLight);
    themeBtn.textContent = isLight ? '🌙' : '☀️';
    themeBtn.title       = isLight ? 'Switch to dark theme' : 'Switch to light theme';

    themeBtn.addEventListener('click', () => {
        isLight = !isLight;
        localStorage.setItem('orgchart_theme', isLight ? 'light' : 'dark');
        document.documentElement.classList.toggle('light', isLight);
        themeBtn.textContent = isLight ? '🌙' : '☀️';
        themeBtn.title       = isLight ? 'Switch to dark theme' : 'Switch to light theme';
        // Re-render to update link stroke colours (read from CSS var at render time)
        if (chart) chart.render();
    });

    // ── Compact toggle ─────────────────────────────────────────────────────
    const compactBtn = document.getElementById('btn-compact');
    compactBtn.textContent = isCompact ? 'COMPACT' : 'SPREAD';

    compactBtn.addEventListener('click', () => {
        isCompact = !isCompact;
        localStorage.setItem('orgchart_compact', String(isCompact));
        compactBtn.textContent = isCompact ? 'COMPACT' : 'SPREAD';
        // Destroy and recreate chart to apply new compact setting
        chart = null;
        applyFilters();
    });
}

// ── Bootstrap ─────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('modal-close').addEventListener('click', closeModal);
    document.getElementById('modal-backdrop').addEventListener('click', e => {
        if (e.target === document.getElementById('modal-backdrop')) closeModal();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeModal();
    });

    initSearch();
    initControls();

    allData = await loadData();
    document.getElementById('loading-msg')?.remove();

    if (allData.length === 0) {
        document.getElementById('chart-container').innerHTML =
            '<div class="state-msg"><span style="color:#e8315b">⚠</span><span>Failed to load team data.</span></div>';
        return;
    }

    initFilters();
    buildDeptFilters();
    renderChart(getFilteredData());
});
