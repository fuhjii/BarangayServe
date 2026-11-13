---
target: BarangayServe Resident and Staff Areas
total_score: 28
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 1
target_identity: "file:C:\\xampp\\htdocs\\barangayserve\\barangayserve\\BarangayServe Resident and Staff Areas"
timestamp: 2026-11-04T03-44-06Z
slug: barangayserve-resident-and-staff-areas
---
Method: dual-agent (A: /root/assessment_a · B: /root/assessment_b)

# Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3/4 | Counts, states, history and pickup notice show progress; update recency is not prominent. |
| 2 | Match with Real World | 3/4 | Barangay hall, reference numbers and resident language fit; “Under Review” could be explained. |
| 3 | User Control and Freedom | 3/4 | Filters and pagination help; residents cannot edit/cancel submitted requests per product constraints. |
| 4 | Consistency and Standards | 3/4 | Shared patterns help, but five equal status tiles serve two distinct roles. |
| 5 | Error Prevention | 3/4 | Labels, validation and confirmation help; staff status updates have no obvious undo. |
| 6 | Recognition Rather Than Recall | 3/4 | References, labels and details persist through the workflow. |
| 7 | Flexibility and Efficiency | 2/4 | Staff filtering exists; no bulk triage is evident. |
| 8 | Aesthetic and Minimalist Design | 3/4 | Restrained palette and legible fonts; repeated framing flattens task priority. |
| 9 | Error Recovery | 3/4 | Empty/no-results states offer next steps; form recovery not established. |
| 10 | Help and Documentation | 2/4 | Inline guidance exists, but no contextual help path is visible. |
| **Total** | | **28/40** | **Good (70%).** |

# Design Specificity Verdict

The interface has BarangayServe-specific cues: request and pickup flow, reference-number stub, workflow labels, and separate staff area. Generic quality is concentrated in the dashboards, which share the same five-tile status KPI treatment despite different resident and staff priorities. Overall it is more restrained than a typical generic admin dashboard; the card concern is moderate, not pervasive.

The deterministic detector returned `[]` (exit 0) for both `resident/` and `staff/`, with no rules, locations or false positives. This does not assess role-specific hierarchy or usability.

Live browser inspection and overlay were unavailable: browser inventory was empty and XAMPP was inactive. No server was started and no overlay is available. Findings are from source/style review; rendered desktop/mobile behavior remains unverified.

# Overall Impression

The product workflow and copy are grounded, but dashboards prioritize uniform summaries over the next useful action. Residents need “what should I know next?”; staff need “which request should I act on first?”

# What's Working

- Reference-number stub and pickup information support the real barangay-hall handoff.
- Status badges pair color with explicit text.
- Empty states explain what is missing and often point to the next action.

# Priority Issues

1. **[P1] Equal status tiles obscure role priorities.** Both dashboards show five equal linked stages. Residents need the current request/next step; staff need oldest Submitted work. Lead with the role-specific action and keep counts secondary. Files: `resident/dashboard.php`, `staff/dashboard.php`, `includes/components.php`. Suggested: `$impeccable layout`.
2. **[P2] Repeated panel framing makes dashboard sections feel like peer widgets.** Reduce framing where sections form one queue; reserve strong containers for separate tasks. This is selective simplification, not removing all cards. Files: dashboard pages and `.panel-flush` in `assets/css/app.css`. Suggested: `$impeccable distill`.
3. **[P2] Staff filters expose too many equal-priority choices.** Reference/name, service, status, date-from, date-to and actions compete. Keep search/service/status primary; disclose date range as advanced filters, show active filters, retain one-step clear. Files: `staff/requests.php`, `.filter-bar` in `assets/css/app.css`. Suggested: `$impeccable layout`.
4. **[P2] Status terms lack contextual explanation.** Explain “Under Review” and the next expected action in plain language beside the tracker, without promising turnaround times. File: `includes/components.php`. Suggested: `$impeccable clarify`.

# Cognitive Load and Emotional Journey

The staff filters create a high-choice surface; grouping basic search/status controls separately from date refinement would ease scanning. The staff dashboard also makes users interpret counts before reaching waiting work. Residents get a clear empty-state action and reference stub; Ready for Pickup includes practical location/hours. While waiting, status history exists but update recency is not foregrounded. Show recorded update time without implying an SLA.

# Persona Red Flags

- **Alex (staff power user):** Requests appear to require individual opening/updating; no bulk triage or keyboard acceleration is evident. Filters slow quick scanning.
- **Sam (keyboard/screen-reader user):** Labels, focus styles, captions and status text are positive source signals. At narrow widths CSS-generated `data-label` text accompanies visually clipped table headers; rendered screen-reader and keyboard behavior still needs checking.

# Minor Observations

- Show the recorded latest status-change time in rows/details where it helps residents.
- Mobile CSS stacks tables and narrows status tiles, but rendered output was not inspected.
- Five statuses are useful as a summary; the issue is equal prominence across both roles.

# Questions to Consider

- Should the resident dashboard answer “what happens next?” before showing all five status buckets?
- Could staff see oldest Submitted work first, with counts in a compact secondary strip?
- Which dashboard sections are separate jobs that warrant separate framed panels?
