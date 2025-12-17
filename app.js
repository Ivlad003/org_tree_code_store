// Configuration
const DATA_URL = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vTKqR9KxEHV1Lr2acNGfHxU0qO3CPWwfxaTdp9BD_n6T3X48n-MxCL65ocaTn180TQfO9x5OkjJwPYN/pub?output=csv';

// Security: Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Security: Validate URL protocol
function isValidUrl(string) {
    if (!string) return false;
    try {
        const url = new URL(string);
        return url.protocol === 'https:' || url.protocol === 'http:';
    } catch {
        return false;
    }
}

// Parse CSV to array of objects (RFC 4180 compliant with escaped quotes)
function parseCSV(csv) {
    const lines = csv.trim().split('\n');
    const headers = lines[0].split(',').map(h => h.trim());

    return lines.slice(1).map(line => {
        const values = [];
        let current = '';
        let inQuotes = false;
        let i = 0;

        while (i < line.length) {
            const char = line[i];
            if (char === '"') {
                if (inQuotes && line[i + 1] === '"') {
                    current += '"';  // Escaped quote
                    i++;
                } else {
                    inQuotes = !inQuotes;
                }
            } else if (char === ',' && !inQuotes) {
                values.push(current.trim());
                current = '';
            } else {
                current += char;
            }
            i++;
        }
        values.push(current.trim());

        const obj = {};
        headers.forEach((header, idx) => {
            obj[header] = values[idx] || '';
        });
        return obj;
    });
}

// Fetch and parse data
async function loadData() {
    try {
        const response = await fetch(DATA_URL);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        const csv = await response.text();
        const data = parseCSV(csv);

        // Filter out empty/invalid rows and fix common data issues
        return data.filter(row => {
            // Skip rows without id (empty rows)
            if (!row.id || row.id.trim() === '') return false;
            return true;
        }).map(row => {
            // Fix name parsing issues (comma in first_name like "Zenyk, Haiduk")
            if (row.first_name && row.first_name.includes(',')) {
                const parts = row.first_name.split(',').map(s => s.trim());
                row.first_name = parts[0];
                if (!row.last_name && parts[1]) {
                    row.last_name = parts[1];
                }
            }
            // Fix QA category node mislabeled as BDR (id=8)
            if (row.id === '8' && row.first_name === 'QA' && row.department_name === 'BDR') {
                row.department_name = 'QA';
            }
            return row;
        });
    } catch (error) {
        console.error('Failed to load data:', error);
        return [];
    }
}

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

// Modal elements (initialized after DOM ready)
let modalBackdrop, modalImg, modalName, modalDepartment, modalDescription, modalLinkedin, modalClose;

// Initialize modal elements and event listeners
function initModal() {
    modalBackdrop = document.getElementById('modal-backdrop');
    modalImg = document.getElementById('modal-img');
    modalName = document.getElementById('modal-name');
    modalDepartment = document.getElementById('modal-department');
    modalDescription = document.getElementById('modal-description');
    modalLinkedin = document.getElementById('modal-linkedin');
    modalClose = document.getElementById('modal-close');

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
}

// Open modal with person data
function openModal(person) {
    const fullName = [person.first_name, person.last_name].filter(Boolean).join(' ') || 'Unknown';

    modalName.textContent = fullName;
    modalDepartment.textContent = person.department_name || '';
    modalDescription.textContent = person.description || '';

    if (person.img_url && isValidUrl(person.img_url)) {
        modalImg.src = person.img_url;
        modalImg.alt = fullName;
        modalImg.style.display = 'block';
    } else {
        modalImg.style.display = 'none';
    }

    if (isValidUrl(person.linkedin_url)) {
        modalLinkedin.href = person.linkedin_url;
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

// Initialize app
async function init() {
    const container = document.getElementById('chart-container');
    container.innerHTML = '<div class="loading">Loading organization chart...</div>';

    const data = await loadData();

    if (data.length === 0) {
        container.innerHTML = '<div class="error">Failed to load organization data. Please refresh the page.</div>';
        return;
    }

    container.innerHTML = '';

    // Initialize chart
    chart = new d3.OrgChart()
        .container('#chart-container')
        .data(data)
        .nodeWidth(() => 280)
        .nodeHeight(() => 120)
        .compactMarginBetween(() => 25)
        .compactMarginPair(() => 50)
        .siblingsMargin(() => 25)
        .childrenMargin(() => 60)
        .neighbourMargin(() => 80)
        .compact(true)
        .rootMargin(40)
        .duration(400)
        .setActiveNodeCentered(true)
        .nodeContent((node) => {
            const person = node.data;
            const fullName = escapeHtml([person.first_name, person.last_name].filter(Boolean).join(' ') || 'Unknown');
            const department = escapeHtml(person.department_name || '');
            const description = escapeHtml(person.description || '');
            const initials = escapeHtml(getInitials(person.first_name, person.last_name));
            const imgUrl = person.img_url || '';
            const linkedinUrl = person.linkedin_url || '';

            const photoHTML = isValidUrl(imgUrl)
                ? `<img class="node-photo" src="${escapeHtml(imgUrl)}" alt="${fullName}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                   <div class="node-photo-placeholder" style="display:none;">${initials}</div>`
                : `<div class="node-photo-placeholder">${initials}</div>`;

            const linkedinHTML = isValidUrl(linkedinUrl)
                ? `<a class="node-linkedin" href="${escapeHtml(linkedinUrl)}" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation();">${linkedinSVG}</a>`
                : '';

            const descriptionHTML = description
                ? `<div class="node-description">${description}</div>`
                : '';

            return `
                <div class="node-card" data-id="${escapeHtml(person.id || '')}">
                    ${photoHTML}
                    <div class="node-info">
                        <div class="node-name">${fullName}</div>
                        <div class="node-department">${department}</div>
                        ${descriptionHTML}
                    </div>
                    ${linkedinHTML}
                </div>
            `;
        })
        .onNodeClick((node) => {
            openModal(node.data);
        })
        .render();
}

// Start app when DOM ready
document.addEventListener('DOMContentLoaded', () => {
    initModal();
    init();
});
