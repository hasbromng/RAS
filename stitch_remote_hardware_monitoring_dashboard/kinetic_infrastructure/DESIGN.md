---
name: Kinetic Infrastructure
colors:
  surface: '#111316'
  surface-dim: '#111316'
  surface-bright: '#37393d'
  surface-container-lowest: '#0c0e11'
  surface-container-low: '#1a1c1f'
  surface-container: '#1e2023'
  surface-container-high: '#282a2d'
  surface-container-highest: '#333538'
  on-surface: '#e2e2e6'
  on-surface-variant: '#bbc9cf'
  inverse-surface: '#e2e2e6'
  inverse-on-surface: '#2f3034'
  outline: '#859399'
  outline-variant: '#3c494e'
  surface-tint: '#4cd6ff'
  primary: '#a4e6ff'
  on-primary: '#003543'
  primary-container: '#00d1ff'
  on-primary-container: '#00566a'
  inverse-primary: '#00677f'
  secondary: '#c7fff0'
  on-secondary: '#00382f'
  secondary-container: '#00f2d1'
  on-secondary-container: '#006a5a'
  tertiary: '#ffd59c'
  on-tertiary: '#442b00'
  tertiary-container: '#feb127'
  on-tertiary-container: '#6b4700'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#b7eaff'
  primary-fixed-dim: '#4cd6ff'
  on-primary-fixed: '#001f28'
  on-primary-fixed-variant: '#004e60'
  secondary-fixed: '#26fedc'
  secondary-fixed-dim: '#00dfc1'
  on-secondary-fixed: '#00201a'
  on-secondary-fixed-variant: '#005144'
  tertiary-fixed: '#ffddb1'
  tertiary-fixed-dim: '#ffba49'
  on-tertiary-fixed: '#291800'
  on-tertiary-fixed-variant: '#624000'
  background: '#111316'
  on-background: '#e2e2e6'
  surface-variant: '#333538'
typography:
  display-lg:
    fontFamily: Hanken Grotesk
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Hanken Grotesk
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-sm:
    fontFamily: JetBrains Mono
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: 0.05em
  data-lg:
    fontFamily: JetBrains Mono
    fontSize: 18px
    fontWeight: '600'
    lineHeight: 24px
  data-sm:
    fontFamily: JetBrains Mono
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  unit: 4px
  gutter: 16px
  margin-mobile: 16px
  margin-desktop: 32px
  container-max: 1440px
---

## Brand & Style
The design system is engineered for high-density information environments where precision and reliability are paramount. The brand personality is clinical, efficient, and authoritative, catering to systems engineers who require split-second data interpretation.

The visual style follows a **Modern Corporate / Technical** approach with a focus on high-contrast legibility. It utilizes deep charcoal surfaces to minimize eye strain during long-term monitoring, while employing sharp, vibrant accents to draw attention to critical system states. The aesthetic avoids unnecessary ornamentation, favoring structural integrity and functional clarity.

## Colors
The palette is rooted in a deep charcoal foundation to create a stable, non-distracting environment for data-heavy interfaces. 

- **Primary & Secondary:** A high-vibrancy Electric Blue and Teal duo are used exclusively for active states, primary actions, and key data visualizations.
- **Status Colors:** These use a high-chroma range to ensure immediate recognition. Success (Green), Warning (Amber), and Error (Red) are mapped to semantic tokens to indicate hardware health.
- **Neutrals:** The background is a near-black charcoal, while nested surfaces and containers use slightly lighter grey steps to provide subtle depth without breaking the dark-mode immersion.

## Typography
This design system employs a dual-font strategy to differentiate between structural UI and technical data.

- **Inter & Hanken Grotesk:** Used for interface labels, navigation, and body copy. These provide a humanistic yet professional tone that ensures high legibility in dense layouts.
- **JetBrains Mono:** Used for all technical values, IP addresses, serial numbers, and logs. The monospaced nature ensures that numerical values align vertically in tables, facilitating rapid scanning and comparison of hardware specs.
- **Scale:** Typography remains relatively compact to allow for maximum data density. Display styles are used sparingly for dashboard-level summaries.

## Layout & Spacing
The layout follows a **Fluid Grid** model optimized for wide-screen monitoring stations. 

- **Grid:** A 12-column system is used for desktop, collapsing to 4 columns on mobile. 
- **Density:** The spacing rhythm is tight, using a 4px baseline unit. This "compact" density is necessary for displaying multiple hardware status cards and real-time graphs on a single viewport.
- **Modules:** Content is organized into modular cards that can be rearranged or resized. Padding within cards is typically 16px (4 units) to maintain a balance between density and readability.

## Elevation & Depth
Depth in this design system is communicated through **Tonal Layers** and **Low-Contrast Outlines** rather than traditional shadows.

- **Layering:** The base background is the darkest layer. Component cards sit one level higher in a lighter charcoal. High-priority modals or popovers sit at the highest level with a subtle 1px border in a lighter neutral (e.g., #333A42).
- **Interactivity:** Hover states for interactive elements (like data rows) are indicated by a subtle background shift (3-5% lighter) rather than a lift, maintaining the "flat-technical" aesthetic.
- **Glassmorphism:** Used only for global navigation sidebars to provide a sense of place over scrolling content, utilizing a very subtle backdrop blur (8px).

## Shapes
The design system utilizes **Soft** corners to maintain a modern feel while appearing more "engineered" than "friendly." 

- **Radius:** A consistent 4px (0.25rem) radius is applied to cards, buttons, and input fields. 
- **Strictness:** Circular elements are reserved strictly for status indicators (LED-style dots) and progress rings.
- **Visuals:** Data visualizations like sparklines should use straight lines or very minor smoothing to avoid looking like consumer-grade marketing charts.

## Components
- **Data Grids:** The core component. Rows should have a 40px height with monospaced data values. Zebra-striping is used for readability in large datasets.
- **Hardware Cards:** These feature a "header" section with a status dot (success/warning/error) and a sub-grid of technical specs using `label-sm` for titles and `data-sm` for values.
- **Sparklines:** Real-time data streams rendered as 1px lines using the Primary or Secondary accent colors. No fill area beneath the line to keep the UI clean.
- **Progress Rings:** Used for disk and memory usage. The ring thickness is 4px. Use a neutral track and a colored "active" segment that changes from Primary (Blue) to Status Warning (Amber) as it fills beyond 80%.
- **Buttons:** Primary buttons are solid Electric Blue with black text for maximum contrast. Secondary buttons are outlined with 1px borders.
- **Inputs:** Terminal-style text fields with a 1px border and a monospaced cursor. Active states are indicated by a full primary color border.