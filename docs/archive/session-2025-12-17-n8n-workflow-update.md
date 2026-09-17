# Session Summary: n8n Workflow Update
**Date:** 2025-12-17

## Workflow
- **ID:** WO2EWK8G258257r8
- **Name:** code.store org tree
- **URL:** https://n8n.vkosmach.pp.ua/workflow/WO2EWK8G258257r8

## Requirements Implemented

### 1. Remove C-level
- Removed C-level category from organizational structure
- People in C-level section are now skipped during processing

### 2. BDR belongs to Growth team
- BDR category now has `parentId = 5` (Growth)
- Positions containing "bdr" or "business development" are assigned to Growth team

### 3. Split Engineers into Developers and QA
- **Developers** (`parentId = 7`) - Default group for engineering roles
- **QA** (`parentId = 8`) - For positions containing "qa", "quality", or "test"

### 4. Added description (bio) and linkedin_url fields
- JavaScript code now extracts `description` and `linkedin_url` from source data
- Checks for columns: `LinkedIn`, `linkedin_url`, `LinkedIn URL`, `Description`, `Bio`, `About`
- "Append row in sheet" node updated to write these fields to output

## Updated Category Mapping

```javascript
const categories = {
  'PM': 2,
  'BDR': 5,        // BDR belongs to Growth
  'Growth': 5,
  'HR': 6,
  'Developers': 7, // New group for developers
  'QA': 8          // New group for QA
};
```

## Data Flow

1. **Source:** Google Sheet "Corporate tree info" (ID: 1D0o1J3Y4wXdi9fa1MNDK09he1hzXLlMR6OqFPqn-q7I)
2. **Processing:** JavaScript code node transforms data
3. **Output:** Google Sheet "org_struct_code_store" (ID: 1BHpOUBeMpP70DkRWOqAMZcXctQUTPwitUXP6XUr72tI)

## Output Fields

| Field | Description |
|-------|-------------|
| id | Random unique identifier |
| parentId | Parent node for hierarchy |
| first_name | Employee first name |
| last_name | Employee last name |
| department_name | Job title/position |
| img_url | Profile photo URL |
| linkedin_url | LinkedIn profile URL |
| description | Bio/about text |

## Notes

- Workflow uses Manual Trigger - must be executed from n8n UI
- Source sheet needs `Description` column for bio to appear in org chart
- Output sheet needs `linkedin_url` and `description` columns in header row
