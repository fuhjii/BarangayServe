---
version: 1
slug: "resident-dashboard-php"
primary_target: "resident/dashboard.php"
related_targets: ["index.php","resident/services.php","resident/request_new.php","resident/request.php","resident/requests.php","resident/history.php","staff/dashboard.php","staff/requests.php","staff/request.php","staff/history.php","staff/services.php","staff/service_edit.php","staff/service_fields.php","staff/field_edit.php","login.php","register.php","account.php"]
---

# BarangayServe visual redesign

## Scope and mode
Full existing web application redesign, centered on the Operate surfaces: public entry, Resident Area, and Staff Area. Preserve the PHP/MySQL/Bootstrap architecture, all routes, forms, copy facts, authentication, request workflow, and service management. Replace frontend presentation and update the approved logo. The public home remains a task-oriented entry, not a marketing landing page.

## Audience and task
Residents submit service requests and follow the status, reference number, remarks, and pickup instructions. Barangay staff find, review, and process requests, then record status changes and remarks. Staff must reach the oldest Submitted request quickly; residents should immediately understand their current request and next step. Keep all existing states, validation, empty results, and error recovery.

## Direction contract

THESIS: Civic Wayfinding makes the next service step as easy to find as the right public office; reject generic KPI dashboards and repeated card grids.

OWN-WORLD: Deep civic-green navigation, limestone page field, pale sage route bands, charcoal text, terracotta actions, and marigold current-step/focus marks. Use bundled Atkinson Hyperlegible Next for UI and Literata sparingly for titles. One functional, labeled route line connects the five request stages; controls remain familiar web controls.

STORY: Residents see their current case, next step, and pickup details. Staff see the oldest Submitted work first and can filter, review, update, and audit it. Color never carries status alone.

FIRST VIEWPORT: Public home opens with a direct service explanation, account action, and real five-step workflow. Resident dashboard leads with current request/progress or a useful empty state. Staff dashboard leads with oldest Submitted queue, then compact status totals. Use open work areas, readable tables, and grouped forms, not a card grid.

FORM: Civic Wayfinding, user-selected from the direction hand; seed:497ea65c605232e0. One shared identity across public, resident, and staff routes; navigation and first-screen priority adapt to each role.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Responsive and interaction intent
Use labeled navigation, stable page titles, clear active route, visible focus, semantic tables, and standard Bootstrap-compatible controls. Desktop queue tables stay scan-friendly; mobile tables stack with programmatic headers, forms become one column, navigation collapses accessibly, and the status route becomes vertical. Keep target size comfortable and honor reduced-motion preferences.

## Boundaries and open decisions
No backend, database, copy-claim, or workflow changes. Preserve existing real content and use clearly synthetic examples only in design previews. No external assets or fonts are required. Logo artwork may be replaced within the approved civic identity. No product-specific WCAG conformance level has been confirmed; preserve and improve the existing accessibility support.
