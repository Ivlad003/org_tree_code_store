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
