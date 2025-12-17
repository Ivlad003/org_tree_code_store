# Org Chart Redesign — Design Document

## Overview

Replace iframe-based org chart with custom implementation using d3-org-chart library. Add new features: full names, LinkedIn links, profile modal, and fix photo scaling.

## Project Structure

```
index.html      — HTML structure, CDN scripts, modal markup
styles.css      — Card styles, modal styles, photo fixes
app.js          — Fetch CSV, parse data, init chart, click handlers
```

**Dependencies (CDN):**
- D3.js v7
- d3-org-chart v3
- d3-flextree

## Data Source

**Google Sheet URL:**
```
https://docs.google.com/spreadsheets/d/e/2PACX-1vTKqR9KxEHV1Lr2acNGfHxU0qO3CPWwfxaTdp9BD_n6T3X48n-MxCL65ocaTn180TQfO9x5OkjJwPYN/pub?output=csv
```

**Required Columns:**

| Column | Status | Purpose |
|--------|--------|---------|
| `id` | Exists | Unique identifier |
| `parentId` | Exists | Hierarchy link |
| `first_name` | Exists | First name |
| `last_name` | Exists | Last name |
| `department_name` | Exists | Job title/department |
| `img_url` | Exists | Photo URL |
| `linkedin_url` | NEW | LinkedIn profile URL |
| `description` | NEW | Bio/about text |

## Node Card Design

```
┌─────────────────────────────┐
│  ┌────┐                     │
│  │foto│  First Last    [in] │
│  └────┘  Department         │
└─────────────────────────────┘
```

**Specifications:**
- Photo: 40x40px circle, `object-fit: cover`
- Name: `{first_name} {last_name}` on one line
- Department: Smaller gray text below name
- LinkedIn icon: Shows only if `linkedin_url` exists
- Card size: ~220x85px

## Modal Design

```
┌────────────────────────────────────────┐
│                                    [×] │
│         ┌──────────────┐               │
│         │              │               │
│         │  Large Photo │               │
│         │   150x150    │               │
│         │              │               │
│         └──────────────┘               │
│                                        │
│        First Name Last Name            │
│           Department                   │
│                                        │
│   ──────────────────────────────────   │
│   "Short bio or description text       │
│    about the person and their role"    │
│   ──────────────────────────────────   │
│                                        │
│        ┌────────────────────┐          │
│        │  View LinkedIn     │          │
│        └────────────────────┘          │
└────────────────────────────────────────┘
```

**Specifications:**
- Backdrop: Semi-transparent dark overlay
- Photo: 150x150px circle, `object-fit: cover`
- Name: Large centered text
- Department: Gray text below name
- Description: Shows only if field has content
- LinkedIn button: Shows only if `linkedin_url` exists, opens new tab

## Interactions

**Card:**
- Click card → Opens modal
- Click LinkedIn icon → Opens LinkedIn in new tab (no modal)
- Click expand/collapse → Shows/hides subordinates

**Modal:**
- Click X button → Closes modal
- Click backdrop → Closes modal
- Press Escape → Closes modal
- Click LinkedIn button → Opens LinkedIn in new tab

**Chart Navigation (built-in):**
- Mouse wheel → Zoom
- Drag → Pan
- Click node → Auto-center

## Edge Cases

- No `linkedin_url` → Hide LinkedIn icon on card, hide button in modal
- No `description` → Hide description section in modal
- No `img_url` → Show placeholder with initials

## Photo Fix

Problem: Photos are stretched/distorted.

Solution:
- Fixed-size circular container (40x40 on card, 150x150 in modal)
- `object-fit: cover` — scales image to fill container
- `object-position: center` — centers the crop on face area
