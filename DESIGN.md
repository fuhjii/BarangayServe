---
name: BarangayServe
description: Civic service requests and status tracking for residents and barangay staff.
colors:
  civic-green: "#123d34"
  terracotta: "#c94f32"
  limestone: "#f6f2e8"
  paper: "#fffdf7"
  sage: "#e7e9d8"
  marigold: "#e7b33e"
  charcoal: "#202923"
  muted-green: "#4e5d53"
  line: "#d8dace"
  status-submitted: "#536574"
  status-review: "#80580b"
  status-processing: "#285e78"
  status-ready: "#17664d"
  status-completed: "#344b3e"
typography:
  display:
    fontFamily: "Literata, Georgia, Times New Roman, serif"
    fontSize: "clamp(2.5rem, 1.6rem + 3.2vw, 4rem)"
    fontWeight: 700
    lineHeight: 1.12
    letterSpacing: "-0.025em"
  body:
    fontFamily: "Atkinson Hyperlegible Next, system-ui, Segoe UI, Roboto, sans-serif"
    fontSize: "1.0625rem"
    fontWeight: 400
    lineHeight: 1.6
  title:
    fontFamily: "Atkinson Hyperlegible Next, system-ui, Segoe UI, Roboto, sans-serif"
    fontSize: "1.22rem"
    fontWeight: 650
    lineHeight: 1.25
rounded:
  flat: "0px"
  control: "2px"
  subtle: "3px"
components:
  button-primary:
    backgroundColor: "{colors.terracotta}"
    textColor: "#ffffff"
    rounded: "{rounded.control}"
    height: "2.75rem"
    padding: "0.6rem 1rem"
  field:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.charcoal}"
    rounded: "{rounded.control}"
    height: "2.9rem"
    padding: "0.65rem 0.8rem"
  active-navigation:
    backgroundColor: "#ffffff1a"
    textColor: "{colors.paper}"
    rounded: "{rounded.control}"
    height: "2.9rem"
    padding: "0.65rem 0.8rem"
---

# Design System: BarangayServe

## Overview

**Creative North Star: "Civic Wayfinding"**

BarangayServe should feel like a clear, dependable public service counter. Residents need to know where to start and what happens next; staff need to find the next request in the queue and record its progress. The interface uses civic green as its shared wayfinding anchor, warm limestone as the page field, and an ordered route through the request stages.

The service remains a working PHP and Bootstrap application. Familiar web controls, open work areas, compact status routes, and readable tables carry the tasks. The new geometric doorway-and-route logo is a product mark, not an official barangay seal.

**Key Characteristics:**
- Deep green navigation with distinct Resident and Staff labels.
- Warm limestone page ground, sage grouping surfaces, and terracotta actions.
- Status is written in text and reinforced by position and color.
- Open lists and tables replace repeated card grids.

## Colors

The palette pairs grounded greens and warm paper neutrals with a clear action color; status colors add meaning without replacing labels.

### Primary
- **Civic Green** (#123d34): shared signed-in navigation, headings, active links, and request references.
- **Terracotta** (#c94f32): primary task actions on light surfaces.

### Secondary
- **Marigold** (#e7b33e): current route step, keyboard focus marker, and public navigation action.
- **Status Blue** (#285e78): Processing status.

### Neutral
- **Limestone** (#f6f2e8): main page background.
- **Paper** (#fffdf7): form fields and authentication surface.
- **Sage** (#e7e9d8): grouped controls, information bands, and table headings.
- **Charcoal** (#202923): primary body text.
- **Muted Green** (#4e5d53): secondary text.
- **Line** (#d8dace): quiet dividers and borders.

### Named Rules
**The Status-in-Words Rule.** Every status remains readable as text; color is reinforcement only.

## Typography

**Display Font:** Literata (with Georgia and Times New Roman fallbacks)
**Body Font:** Atkinson Hyperlegible Next (with system sans-serif fallbacks)
**Label/Mono Font:** Atkinson Hyperlegible Next; no decorative monospace voice.

**Character:** Literata adds a restrained civic editorial voice to large page titles. Atkinson Hyperlegible Next handles navigation, forms, tables, and body copy for clear scanning.

### Hierarchy
- **Display** (700, responsive 2.5–4rem, 1.12): public home heading.
- **Headline** (700, 2–2.65rem, 1.12): page titles.
- **Title** (650, 1.22rem, 1.25): section headings and important data labels.
- **Body** (400, 1.0625rem, 1.6): instructions and page copy; prose is constrained around 65–70 characters per line where appropriate.
- **Label** (700, 0.78–0.95rem): form labels, table headings, and quiet navigation section labels.

### Named Rules
**The Working-Sans Rule.** Use Atkinson for controls, labels, data, and body copy; reserve Literata for prominent page titles.

## Layout

Signed-in screens use a 258px civic-green left rail on desktop, with the account menu anchored at the bottom. The main work area fills the remaining width; public entry content is capped at 1440px. Public pages keep a horizontal top navigation. At 992px and below, authenticated navigation returns to a collapsible top bar; columns collapse at 768px, and compact filters and headings adapt again below 480px. Tables scroll or stack with visible row labels on narrow screens. Forms stay grouped and become one column. Use open sections, aligned edges, and consistent gaps instead of wrapping every task in a card.

## Elevation & Depth

The system is flat by default. Dividers, pale sage surfaces, and spacing establish grouping. A soft shadow is reserved for the account dropdown; focus is shown with a visible green outline. Do not add ambient shadows to routine panels or tables.

### Named Rules
**The Open-Surface Rule.** Use borders and tonal surfaces only where they clarify a boundary or related set of controls.

## Shapes

Controls use near-square corners: 2px for buttons, fields, badges, and navigation; a subtle 3px base radius remains available for existing elements. Tables, route bands, and section dividers stay square. Keep borders thin and neutral; avoid thick decorative rails.

## Components

### Buttons
- **Shape:** near-square, 2px radius; minimum height 2.75rem (2.3rem for compact table actions).
- **Primary:** terracotta on light surfaces; white text. Public navigation uses marigold to remain visible against green.
- **Hover / Focus:** deepen the action color on hover; keyboard focus remains clearly visible.
- **Secondary:** outlined civic green or neutral outline for lower-priority actions.

### Chips
- **Style:** short rectangular status markers with a pale semantic tint and dark readable text.
- **State:** include the full status label; use color as a secondary cue.

### Cards / Containers
- **Corner Style:** square work sections; authentication panels use a subtle 3px radius.
- **Background:** transparent work areas, sage grouping strips, and paper form surfaces.
- **Shadow Strategy:** none for ordinary sections; dropdown shadow only.
- **Border:** thin neutral dividers for lists and tables.
- **Internal Padding:** compact controls; open page sections use vertical spacing instead of nested containers.

### Inputs / Fields
- **Style:** limestone-white paper surface, 1px muted-green border, 2px radius, minimum height 2.9rem.
- **Focus:** civic-green border and a visible 3px translucent focus ring.
- **Error / Disabled:** red border and readable inline error text; error summaries link to their fields.

### Navigation
- **Style:** deep green with white labels. The active destination uses a subtle white tint and marigold marker; desktop signed-in links form a vertical rail and mobile links use the Bootstrap collapse control.

### Request Route
The five workflow stages form one continuous, labeled route. Residents see their current step and prior completed steps; Staff can filter the queue by each status. On phones, the resident route stacks vertically.

## Do's and Don'ts

### Do:
- **Do** lead the Resident dashboard with the latest or pickup-ready request, its status route, and the next useful action.
- **Do** lead the Staff dashboard with the oldest Submitted requests, then show compact status totals.
- **Do** keep reference numbers tabular and easy to copy or scan.
- **Do** use labels, status text, and programmatic table headings alongside visual cues.
- **Do** keep the public home task-oriented and explain the real five-stage process.

### Don't:
- **Don't** present the application as an approval or certificate-issuing service.
- **Don't** turn each section into an identical rounded card or metric tile.
- **Don't** use gradients as decoration, purple/blue gradient backgrounds, or color-only status signals.
- **Don't** add decorative icons to controls whose labels already name the action.
- **Don't** use thick colored side rails, oversized pills, or ornamental elements that compete with the request workflow.
