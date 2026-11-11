# Compliance and Accessibility Report

## Scope checked

The audit covered application source, shared layout, project assets, design exports, project documentation, external resource references, package manifests, and vendor directories.

## Changes made

- Added a plain-language [Privacy Policy](../pages/privacy-policy.php) and [Terms of Use](../pages/terms.php). The school must fill in the clearly marked contact details and data-retention period/process before real users' information is collected.
- Corrected the footer and consent-form links to point to the policy and terms pages.
- Added the required consent check to staff found-item uploads, in addition to the existing registration, lost-report, and claim checks.
- Added a meaningful fallback alternative text for stored images whose alt text is blank.
- Replaced the Tailwind CDN runtime with the project's own local stylesheet. The pages make no third-party requests for scripts, styles, fonts, embeds, or tracking.
- Replaced generated Stitch HTML exports with local screenshot preview pages and removed their remote Tailwind runtime, Google Fonts/Material Symbols links, remote image URLs, and prototype scripts.
- Updated design typography specifications and stack guidance to use the local CSS stack rather than external fonts or Tailwind.
- Confirmed the app configures one PHP session cookie. No cookie notice is required for this session cookie alone.

## Stack scan findings and resolution

| File | Finding | Resolution |
|---|---|---|
| `design/Stitch Classroom Lost&Found Claim Pass/code.html` | Tailwind CDN, Google Fonts and Material Symbols, remote Google-hosted images, and generated prototype scripts | Replaced with a local screenshot preview page using `screen.png` and `assets/site.css`. |
| `design/Stitch Classroom Lost&Found Claims/code.html` | Tailwind CDN, Google Fonts and Material Symbols, remote Google-hosted images, and generated prototype scripts | Replaced with a local screenshot preview page using `screen.png` and `assets/site.css`. |
| `design/Stitch Classroom Lost&Found Item Detail/code.html` | Tailwind CDN, Google Fonts and Material Symbols, remote Google-hosted images, and generated prototype scripts | Replaced with a local screenshot preview page using `screen.png` and `assets/site.css`. |
| `design/Stitch Classroom Lost&Found Report Found Item/code.html` | Tailwind CDN, Google Fonts and Material Symbols, remote Google-hosted images, and generated prototype scripts | Replaced with a local screenshot preview page using `screen.png` and `assets/site.css`. |
| `design/*/DESIGN.md` | Generated typography specifications named externally hosted font families | Replaced with the system Arial font already used by the app. |

No Composer/npm manifests, vendor directories, JavaScript frameworks, tracking scripts, or embedded third-party content were found. The only external URL retained is the optional Google Stitch MCP endpoint in `.vscode/mcp.json`; project guidance explicitly permits Stitch for design-time prototyping only. It is not loaded by or required by the application.

## Accessibility checks

- Form inputs, text areas, and selects have associated labels. Hidden CSRF and action fields are not user-facing controls; the remove-image checkbox is inside its descriptive label.
- Rendered item images have alternative text, using the submitted text or a useful item-title fallback. Privacy placeholders for sensitive found-item images have an accessible name.
- The shared layout has a skip link, semantic header, navigation, main landmark, and footer. Public legal pages use a single page heading followed by ordered section headings.
- Forms and navigation can be used with a keyboard. The local stylesheet provides visible keyboard focus indicators, a visible-on-focus skip link, responsive layout rules, and high-contrast text and control colors.
- Buttons and actions use descriptive labels. Existing item-specific edit/delete actions are accompanied by the item heading.

## Remaining risks and actions

- The school must complete the marked contact and retention details and review this draft against its policies and applicable requirements before production use. This report does not make a legal-compliance determination.
- The app does not record a consent timestamp or a copy/version of the terms accepted. The required checkboxes validate acknowledgement during the relevant submission, but the current database schema does not preserve that acknowledgement.
- Uploaded item descriptions and images may be shown publicly. Users and staff should avoid including unnecessary personal information in public descriptions or photos.
- The app's session cookie and database-backed records require deployment-specific HTTPS, server access controls, backups, and retention/deletion procedures. Those operational controls are outside this source audit.
