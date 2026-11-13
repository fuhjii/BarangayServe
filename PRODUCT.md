# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Inferred from project documentation:** Residents use the web app to request barangay services and check progress without visiting the hall just to ask.
- **Inferred from project documentation:** Authorized barangay staff review submissions, process requests, and communicate progress through status updates and remarks.

## Product Purpose

**Inferred from project documentation:** BarangayServe provides online submission and tracking for barangay service requests. Success means residents can submit a request and follow its progress, while staff can centrally review and process requests.

## Positioning

**Inferred from project documentation:** The system records and tracks requests; it does not approve requests or generate or issue official certificates. Barangay personnel make decisions and prepare and release documents.

## Operating Context

**Inferred from project documentation:** Residents register and sign in, select a service, submit required information, receive a `BR-YYYY-NNNNN` reference number, and view their request status and chronological history. Staff sign in separately, search and filter requests, update each request through the defined workflow, and add remarks. Documents are picked up at the barangay hall.

## Capabilities and Constraints

**Inferred from project documentation:** The two user areas are Resident and Barangay Staff. The request workflow is Submitted, Under Review, Processing, Ready for Pickup, and Completed. Staff accounts are provisioned outside public registration. Services and their questions can be managed by staff. Residents cannot edit or cancel a submitted request.

**Inferred from project documentation:** Online payments, GPS, AI, chat, SMS/email integration, resident-record management, finance, automatic approval, and automatic certificate generation are out of scope. No online income or ID-number collection is intended. The app should support desktop and mobile browsers.

## Brand Commitments

**Inferred from project documentation:** The product name is BarangayServe. The project is a Software Engineering 1 research project at Universidad de Dagupan.

## Evidence on Hand

Project documentation and implementation are present in `README.md`, `docs/IMPLEMENTATION_NOTES.md`, `docs/SRS_TRACEABILITY.md`, and the PHP application. The project includes a logo at `assets/img/logo.svg`, local Bootstrap assets, and bundled Atkinson Hyperlegible Next and Literata fonts. **Inferred constraint:** Do not fabricate testimonials, customer counts, performance guarantees, or claims of official approval or certificate issuance.

## Product Principles

- **Inferred:** Keep residents informed about request status and history.
- **Inferred:** Keep staff decisions and processing under barangay personnel's control.
- **Inferred:** Preserve an auditable record of status changes and remarks.
- **Inferred:** Collect only information needed to process the selected service.
- **Inferred:** Keep services configurable without requiring code changes.
