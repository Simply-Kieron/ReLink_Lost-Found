---
name: Campus Lost & Found System
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#45474c'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#75777d'
  outline-variant: '#c5c6cd'
  surface-tint: '#545f73'
  primary: '#091426'
  on-primary: '#ffffff'
  primary-container: '#1e293b'
  on-primary-container: '#8590a6'
  inverse-primary: '#bcc7de'
  secondary: '#006a61'
  on-secondary: '#ffffff'
  secondary-container: '#86f2e4'
  on-secondary-container: '#006f66'
  tertiary: '#201100'
  on-tertiary: '#ffffff'
  tertiary-container: '#3c2300'
  on-tertiary-container: '#c88000'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d8e3fb'
  primary-fixed-dim: '#bcc7de'
  on-primary-fixed: '#111c2d'
  on-primary-fixed-variant: '#3c475a'
  secondary-fixed: '#89f5e7'
  secondary-fixed-dim: '#6bd8cb'
  on-secondary-fixed: '#00201d'
  on-secondary-fixed-variant: '#005049'
  tertiary-fixed: '#ffddb8'
  tertiary-fixed-dim: '#ffb95f'
  on-tertiary-fixed: '#2a1700'
  on-tertiary-fixed-variant: '#653e00'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  headline-xl:
    fontFamily: Arial
    fontSize: 36px
    fontWeight: '700'
    lineHeight: 44px
  headline-xl-mobile:
    fontFamily: Arial
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 36px
  headline-lg:
    fontFamily: Arial
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
  headline-md:
    fontFamily: Arial
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  headline-sm:
    fontFamily: Arial
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
  body-lg:
    fontFamily: Arial
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Arial
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Arial
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  label-lg:
    fontFamily: Arial
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
  label-md:
    fontFamily: Arial
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
  label-sm:
    fontFamily: Arial
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.02em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-sm: 0.75rem
  gutter-lg: 1.5rem
  margin: 1rem
  margin-tablet: 1.5rem
  margin-desktop: 2rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 0.75rem
  space-lg: 1rem
  space-xl: 1.5rem
---

## Brand & Style

The design system is engineered for educational institutions, faculty, staff, and students. It blends institutional reliability with the effortless, tactile familiarity of a modern consumer utility. The brand personality is organized, reassuring, approachable, and transparent.

The design movement embraces **Tactile Clean Minimalism**:
- Crisp structured hierarchy eliminates anxiety during stressful loss-of-item events.
- Physical affordances via soft layered surfaces, pill-shaped metadata badges, and tactile rounded surfaces make logging and verifying items rapid on mobile screens.
- Generous touch targets and contextual status color codings provide immediate clarity on item custody, verification status, and room locations.

## Colors

The palette establishes an orderly, calm academic environment while utilizing functional status accents to communicate state changes effortlessly.

### Functional Roles
- **Primary (`#1E293B` Deep Collegiate Navy) & Accent Blue (`#3B82F6` Campus Blue)**: Used for primary navigational bars, structural headers, high-emphasis interactions, and the primary 'Report Lost / Found' actions.
- **Secondary (`#0D9488` Emerald Teal)**: Signifies positive resolution, successful verification, 'Returned' status, and matched inventory items.
- **Tertiary (`#F59E0B` Amber Honey)**: Reserved for 'Pending Verification', 'Claim Under Review', custody transfers, and time-sensitive warnings.
- **Urgent / Alert (`#EF4444` Coral Rose)**: Directs immediate attention to high-value lost items, urgent broadcasts, or declined claim challenges.
- **Neutral Surface Palette**:
  - App Background: `#F8FAFC` (Off-white slate canvas)
  - Surface Card Background: `#FFFFFF`
  - Subdued Borders & Dividers: `#E2E8F0`
  - Text Primary: `#0F172A`
  - Text Muted: `#64748B`

## Typography

The typography pairs **Arial** for friendly, crisp headings with **Arial** for dense metadata and highly legible functional UI copy.

- **Arial** brings an approachable, student-friendly tone to category cards, empty states, and section headings.
- **Arial** handles high-density inventory data, room tags, timestamps, and claim dialogues where legibility and neutral weight distribution are essential.
- Numeric details (dates, room numbers, serial markers) always render with tabular numeric figures where applicable.

## Layout & Spacing

The layout is built mobile-first around a fluid container model optimized for handheld, one-handed campus usage.

- **Mobile (<640px)**: 4-column fluid layout with `1rem` outer canvas padding (`margin`) and `0.75rem` gutters. Primary action CTAs stick to the bottom safe-area container.
- **Tablet (640px - 1024px)**: 8-column layout with `1.5rem` margins and `1rem` gutters. Item feed transitions into a dual-column masonry grid.
- **Desktop (>1024px)**: 12-column layout centered with a maximum container width of `1200px` for campus admin and lost-and-found repository staff.
- Vertical rhythm enforces standard multiples of `0.25rem` (4px baseline), keeping cards, chips, and badge groups visually ordered.

## Elevation & Depth

Visual hierarchy uses clean tonal layering complemented by subtle ambient drop shadows.

- **Base Layer (Level 0)**: Background canvas (`#F8FAFC`).
- **Surface Layer (Level 1)**: Primary cards and grouped classroom feeds (`#FFFFFF`) with a 1px solid border (`#E2E8F0`) and ambient shadow: `box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.04), 0 1px 2px -1px rgba(15, 23, 42, 0.04)`.
- **Raised Interactive Layer (Level 2)**: Hovered cards, floating quick-filter trays, and pinned status pills: `box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.06), 0 2px 4px -2px rgba(15, 23, 42, 0.04)`.
- **Overlay & Modal Layer (Level 3)**: Claim modal sheets, image magnification previews, and bottom navigation: `box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 8px 10px -6px rgba(15, 23, 42, 0.05)`.
- **Scrim**: 40% opacity navy backdrop (`rgba(15, 23, 42, 0.4)`) with `backdrop-filter: blur(4px)`.

## Shapes

The design system utilizes soft, organic curves to feel welcoming and approachable.

- **Cards & Surfaces (`rounded-2xl` / 1rem)**: Used for item cards, claim review panels, and photo upload drop zones.
- **Interactive Controls (`rounded-xl` / 0.75rem)**: Text inputs, modal dialogs, and primary action buttons.
- **Badges, Status Indicators, & Chips (`rounded-full` / 9999px)**: Status indicators (`Unclaimed`, `Pending`, `Returned`), category filters, and campus building pills use pure pill geometry.

## Components

### Buttons
- **Primary CTA**: Deep navy (`#1E293B`) or vibrant blue (`#3B82F6`) background, white label text, `0.75rem` vertical by `1.25rem` horizontal padding, `rounded-xl`, subtle tap scale feedback (`active:scale-98`).
- **Secondary CTA**: Neutral border (`#E2E8F0`), surface white background, text `#1E293B`, `rounded-xl`.
- **Claim Action CTA**: Emerald teal (`#0D9488`) solid fill with white text for claim approval or 'Found' actions.

### Status Badge Pills
- Compact pill-shaped elements (`rounded-full`, `0.25rem` vertical, `0.75rem` horizontal padding, `label-sm`).
- **Unclaimed / Open**: Light slate fill (`#F1F5F9`), text `#475569`, border `#CBD5E1`.
- **Pending Claim / In Review**: Amber tint fill (`#FEF3C7`), text `#B45309`, border `#FDE68A`.
- **Returned / Solved**: Teal tint fill (`#CCFBF1`), text `#0F766E`, border `#99F6E4`.
- **Urgent / High-Value**: Rose tint fill (`#FFE4E6`), text `#BE123C`, border `#FECDD3`.

### Location & Room Metadata Chips
- Small neutral pills containing an icon (e.g., room pin, building icon) + text (e.g., "Hall B • Room 302"). 
- Background `#F1F5F9`, text `#334155`, font `label-md`.

### Cards
- **Item Feed Card**: Enclosed within `rounded-2xl` boundaries, with a 1:1 square photo container or fallback iconography thumbnail on top or left, clear headline, location chip, time-ago metadata, and status badge anchored top-right.

### Input Fields
- `rounded-xl`, border 1.5px solid `#E2E8F0`, padding `0.75rem` horizontal and vertical. Focus state highlights border with `#3B82F6` and adds an outer focus ring `rgba(59, 130, 246, 0.15)`.

### Checkboxes & Segmented Pickers
- Segmented control toggling between "Lost Items" and "Found Items" with a sliding tactile pill indicator inside a `#F1F5F9` background container.