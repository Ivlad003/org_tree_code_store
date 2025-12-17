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

// Start app when DOM ready
document.addEventListener('DOMContentLoaded', init);
