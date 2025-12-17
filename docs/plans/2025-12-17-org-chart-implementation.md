# Org Chart Redesign Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace iframe-based org chart with custom d3-org-chart implementation featuring full names, LinkedIn links, profile modals, and fixed photo scaling.

**Architecture:** Single-page app with vanilla JS. Fetches CSV from Google Sheets, parses to JSON, renders interactive org chart with d3-org-chart library. Modal overlay for profile details.

**Tech Stack:** HTML5, CSS3, Vanilla JavaScript, D3.js v7, d3-org-chart v3, d3-flextree

---

### Task 1: Create Base HTML Structure

**Files:**
- Modify: `index.html` (replace entire content)

**Step 1: Write the HTML file**

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organization Chart</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div id="chart-container"></div>

    <!-- Modal -->
    <div id="modal-backdrop" class="modal-backdrop hidden">
        <div class="modal-content">
            <button class="modal-close" id="modal-close">&times;</button>
            <div class="modal-photo">
                <img id="modal-img" src="" alt="Profile photo">
            </div>
            <h2 id="modal-name"></h2>
            <p id="modal-department" class="modal-department"></p>
            <p id="modal-description" class="modal-description"></p>
            <a id="modal-linkedin" class="modal-linkedin-btn" href="" target="_blank" rel="noopener noreferrer">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                </svg>
                View LinkedIn
            </a>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/d3-org-chart@3"></script>
    <script src="https://cdn.jsdelivr.net/npm/d3-flextree@2.1.2/build/d3-flextree.js"></script>
    <script src="app.js"></script>
</body>
</html>
```

**Step 2: Verify file saved**

Open `index.html` in browser — should show empty page with no console errors about missing CSS/JS files (they don't exist yet, but CDN scripts should load).

**Step 3: Commit**

```bash
git add index.html
git commit -m "feat: add base HTML structure with modal and CDN scripts"
```

---

### Task 2: Create CSS Styles

**Files:**
- Create: `styles.css`

**Step 1: Write the CSS file**

```css
/* Reset and base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background-color: #ffffff;
    overflow: hidden;
}

#chart-container {
    width: 100vw;
    height: 100vh;
}

/* Node card styles - applied via JS nodeContent */
.node-card {
    font-family: 'Inter', sans-serif;
    background-color: #ffffff;
    border-radius: 10px;
    border: 1px solid #E4E2E9;
    padding: 12px 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: box-shadow 0.2s ease;
}

.node-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.node-photo {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    object-position: center;
    flex-shrink: 0;
    background-color: #E4E2E9;
}

.node-info {
    flex: 1;
    min-width: 0;
}

.node-name {
    font-size: 14px;
    font-weight: 500;
    color: #08011E;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.node-department {
    font-size: 11px;
    color: #716E7B;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.node-linkedin {
    width: 24px;
    height: 24px;
    color: #0A66C2;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: background-color 0.2s ease;
}

.node-linkedin:hover {
    background-color: #E8F4FC;
}

.node-linkedin svg {
    width: 18px;
    height: 18px;
}

/* Placeholder avatar */
.node-photo-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #E4E2E9;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 600;
    color: #716E7B;
    flex-shrink: 0;
}

/* Modal styles */
.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 1;
    transition: opacity 0.2s ease;
}

.modal-backdrop.hidden {
    opacity: 0;
    pointer-events: none;
}

.modal-content {
    background: #ffffff;
    border-radius: 16px;
    padding: 32px;
    max-width: 400px;
    width: 90%;
    text-align: center;
    position: relative;
    transform: scale(1);
    transition: transform 0.2s ease;
}

.modal-backdrop.hidden .modal-content {
    transform: scale(0.95);
}

.modal-close {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 32px;
    height: 32px;
    border: none;
    background: #f5f5f5;
    border-radius: 50%;
    font-size: 24px;
    line-height: 1;
    cursor: pointer;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    background: #e5e5e5;
    color: #333;
}

.modal-photo {
    width: 150px;
    height: 150px;
    margin: 0 auto 20px;
    border-radius: 50%;
    overflow: hidden;
    background-color: #E4E2E9;
}

.modal-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}

#modal-name {
    font-size: 24px;
    font-weight: 600;
    color: #08011E;
    margin-bottom: 4px;
}

.modal-department {
    font-size: 14px;
    color: #716E7B;
    margin-bottom: 16px;
}

.modal-description {
    font-size: 14px;
    color: #444;
    line-height: 1.5;
    margin-bottom: 20px;
    padding: 16px;
    background: #f9f9f9;
    border-radius: 8px;
}

.modal-description:empty {
    display: none;
}

.modal-linkedin-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background-color: #0A66C2;
    color: #ffffff;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    transition: background-color 0.2s ease;
}

.modal-linkedin-btn:hover {
    background-color: #004182;
}

.modal-linkedin-btn.hidden {
    display: none;
}

/* Chart link styles override */
.link {
    stroke: #E4E2E9 !important;
}
```

**Step 2: Verify styles load**

Open `index.html` in browser — page should have white background, no visual elements yet but no console errors.

**Step 3: Commit**

```bash
git add styles.css
git commit -m "feat: add CSS styles for node cards and modal"
```

---

### Task 3: Create JavaScript App - Data Loading

**Files:**
- Create: `app.js`

**Step 1: Write CSV fetching and parsing logic**

```javascript
// Configuration
const DATA_URL = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vTKqR9KxEHV1Lr2acNGfHxU0qO3CPWwfxaTdp9BD_n6T3X48n-MxCL65ocaTn180TQfO9x5OkjJwPYN/pub?output=csv';

// Parse CSV to array of objects
function parseCSV(csv) {
    const lines = csv.trim().split('\n');
    const headers = lines[0].split(',').map(h => h.trim());

    return lines.slice(1).map(line => {
        const values = [];
        let current = '';
        let inQuotes = false;

        for (let char of line) {
            if (char === '"') {
                inQuotes = !inQuotes;
            } else if (char === ',' && !inQuotes) {
                values.push(current.trim());
                current = '';
            } else {
                current += char;
            }
        }
        values.push(current.trim());

        const obj = {};
        headers.forEach((header, i) => {
            obj[header] = values[i] || '';
        });
        return obj;
    });
}

// Fetch and parse data
async function loadData() {
    try {
        const response = await fetch(DATA_URL);
        const csv = await response.text();
        return parseCSV(csv);
    } catch (error) {
        console.error('Failed to load data:', error);
        return [];
    }
}

// Initialize app
async function init() {
    const data = await loadData();
    console.log('Loaded data:', data);

    if (data.length === 0) {
        console.error('No data loaded');
        return;
    }

    // Chart initialization will go here
}

// Start app when DOM ready
document.addEventListener('DOMContentLoaded', init);
```

**Step 2: Verify data loads**

Open `index.html` in browser, open DevTools Console — should see "Loaded data:" with array of employee objects.

**Step 3: Commit**

```bash
git add app.js
git commit -m "feat: add CSV data loading from Google Sheets"
```

---

### Task 4: Add Chart Initialization

**Files:**
- Modify: `app.js` (add chart setup)

**Step 1: Add chart initialization to init function**

Replace the `init` function and add helper:

```javascript
// Get initials for placeholder avatar
function getInitials(firstName, lastName) {
    const first = firstName ? firstName.charAt(0).toUpperCase() : '';
    const last = lastName ? lastName.charAt(0).toUpperCase() : '';
    return first + last || '?';
}

// LinkedIn icon SVG
const linkedinSVG = `<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
</svg>`;

let chart;

// Initialize app
async function init() {
    const data = await loadData();
    console.log('Loaded data:', data);

    if (data.length === 0) {
        console.error('No data loaded');
        return;
    }

    // Initialize chart
    chart = new d3.OrgChart()
        .container('#chart-container')
        .data(data)
        .nodeWidth(() => 250)
        .nodeHeight(() => 80)
        .compactMarginBetween(() => 25)
        .compactMarginPair(() => 50)
        .siblingsMargin(() => 25)
        .childrenMargin(() => 60)
        .neighbourMargin(() => 80)
        .compact(true)
        .rootMargin(40)
        .duration(400)
        .setActiveNodeCentered(true)
        .nodeContent((d) => {
            const data = d.data;
            const fullName = [data.first_name, data.last_name].filter(Boolean).join(' ') || 'Unknown';
            const department = data.department_name || '';
            const imgUrl = data.img_url || '';
            const linkedinUrl = data.linkedin_url || '';

            const photoHTML = imgUrl
                ? `<img class="node-photo" src="${imgUrl}" alt="${fullName}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                   <div class="node-photo-placeholder" style="display:none;">${getInitials(data.first_name, data.last_name)}</div>`
                : `<div class="node-photo-placeholder">${getInitials(data.first_name, data.last_name)}</div>`;

            const linkedinHTML = linkedinUrl
                ? `<a class="node-linkedin" href="${linkedinUrl}" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation();">${linkedinSVG}</a>`
                : '';

            return `
                <div class="node-card" data-id="${data.id}">
                    ${photoHTML}
                    <div class="node-info">
                        <div class="node-name">${fullName}</div>
                        <div class="node-department">${department}</div>
                    </div>
                    ${linkedinHTML}
                </div>
            `;
        })
        .onNodeClick((d) => {
            openModal(d.data);
        })
        .render();
}
```

**Step 2: Verify chart renders**

Open `index.html` in browser — should see org chart with employee cards showing photos, names, departments, and LinkedIn icons.

**Step 3: Commit**

```bash
git add app.js
git commit -m "feat: add d3-org-chart initialization with custom node template"
```

---

### Task 5: Add Modal Functionality

**Files:**
- Modify: `app.js` (add modal logic at end of file)

**Step 1: Add modal functions after the init function**

```javascript
// Modal elements
const modalBackdrop = document.getElementById('modal-backdrop');
const modalImg = document.getElementById('modal-img');
const modalName = document.getElementById('modal-name');
const modalDepartment = document.getElementById('modal-department');
const modalDescription = document.getElementById('modal-description');
const modalLinkedin = document.getElementById('modal-linkedin');
const modalClose = document.getElementById('modal-close');

// Open modal with person data
function openModal(data) {
    const fullName = [data.first_name, data.last_name].filter(Boolean).join(' ') || 'Unknown';

    modalName.textContent = fullName;
    modalDepartment.textContent = data.department_name || '';
    modalDescription.textContent = data.description || '';

    if (data.img_url) {
        modalImg.src = data.img_url;
        modalImg.alt = fullName;
        modalImg.style.display = 'block';
    } else {
        modalImg.style.display = 'none';
    }

    if (data.linkedin_url) {
        modalLinkedin.href = data.linkedin_url;
        modalLinkedin.classList.remove('hidden');
    } else {
        modalLinkedin.classList.add('hidden');
    }

    modalBackdrop.classList.remove('hidden');
}

// Close modal
function closeModal() {
    modalBackdrop.classList.add('hidden');
}

// Modal event listeners
modalClose.addEventListener('click', closeModal);

modalBackdrop.addEventListener('click', (e) => {
    if (e.target === modalBackdrop) {
        closeModal();
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modalBackdrop.classList.contains('hidden')) {
        closeModal();
    }
});
```

**Step 2: Verify modal works**

Open `index.html` in browser:
1. Click on any person card — modal should open with their details
2. Click X — modal closes
3. Click backdrop — modal closes
4. Press Escape — modal closes
5. Click LinkedIn icon on card — should open LinkedIn in new tab (not open modal)

**Step 3: Commit**

```bash
git add app.js
git commit -m "feat: add modal dialog for profile details"
```

---

### Task 6: Final Testing and Cleanup

**Files:**
- Review all files

**Step 1: Test all features**

Open `index.html` and verify:
- [ ] Chart loads with all employees from Google Sheet
- [ ] Cards show: photo (not stretched), full name, department, LinkedIn icon (if URL exists)
- [ ] Clicking card opens modal
- [ ] Modal shows: large photo, full name, department, description (if exists), LinkedIn button (if URL exists)
- [ ] Modal closes with X, backdrop click, or Escape
- [ ] Clicking LinkedIn icon on card opens LinkedIn directly (doesn't open modal)
- [ ] Chart navigation works: zoom, pan, expand/collapse

**Step 2: Commit final state**

```bash
git add -A
git commit -m "feat: complete org chart implementation

- Custom d3-org-chart with full names and LinkedIn icons
- Profile modal with photo, description, and LinkedIn link
- Fixed photo scaling with object-fit: cover
- Responsive modal with multiple close methods"
```

---

## Google Sheet Updates Required

Before testing LinkedIn and description features, add these columns to your Google Sheet:

| Column Name | Purpose | Example Value |
|-------------|---------|---------------|
| `linkedin_url` | LinkedIn profile URL | `https://linkedin.com/in/username` |
| `description` | Bio/about text | `Senior developer with 5 years experience...` |

The code handles missing values gracefully — features simply hide when data is empty.
