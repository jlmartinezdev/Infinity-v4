---
name: Infinity ISP
description: Staff operating panel — dark control rail, utilitarian gray surfaces, violet only on action.
colors:
  control-violet: "#9333ea"
  control-violet-hover: "#7e22ce"
  control-violet-focus: "#a855f7"
  control-violet-active: "#c084fc"
  mark-pink: "#db2777"
  rail: "#111827"
  rail-border: "#1f2937"
  rail-text: "#e5e7eb"
  surface: "#ffffff"
  surface-muted: "#f9fafb"
  surface-dark: "#1f2937"
  page-dark: "#111827"
  ink: "#111827"
  ink-muted: "#6b7280"
  ink-on-dark: "#f3f4f6"
  stroke: "#e5e7eb"
  stroke-dark: "#4b5563"
  danger: "#dc2626"
  success: "#16a34a"
  warning: "#d97706"
typography:
  headline:
    fontFamily: "ui-sans-serif, system-ui, sans-serif, Apple Color Emoji, Segoe UI Emoji, Segoe UI Symbol, Noto Color Emoji"
    fontSize: "1.25rem"
    fontWeight: 700
    lineHeight: 1.4
  title:
    fontFamily: "ui-sans-serif, system-ui, sans-serif, Apple Color Emoji, Segoe UI Emoji, Segoe UI Symbol, Noto Color Emoji"
    fontSize: "1rem"
    fontWeight: 600
    lineHeight: 1.5
  body:
    fontFamily: "ui-sans-serif, system-ui, sans-serif, Apple Color Emoji, Segoe UI Emoji, Segoe UI Symbol, Noto Color Emoji"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.25rem
  label:
    fontFamily: "ui-sans-serif, system-ui, sans-serif, Apple Color Emoji, Segoe UI Emoji, Segoe UI Symbol, Noto Color Emoji"
    fontSize: "0.875rem"
    fontWeight: 500
    lineHeight: 1.25rem
  mono:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, Liberation Mono, Courier New, monospace"
    fontSize: "0.75rem"
    fontWeight: 400
    lineHeight: 1rem
rounded:
  lg: "0.5rem"
  full: "9999px"
spacing:
  sm: "0.5rem"
  md: "1rem"
  lg: "1.5rem"
  rail: "16rem"
  rail-collapsed: "5rem"
  header: "4rem"
components:
  button-primary:
    backgroundColor: "{colors.control-violet}"
    textColor: "#ffffff"
    rounded: "{rounded.lg}"
    padding: "0.5rem 1rem"
    typography: "{typography.label}"
  button-primary-hover:
    backgroundColor: "{colors.control-violet-hover}"
    textColor: "#ffffff"
  button-secondary:
    backgroundColor: "#e5e7eb"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "0.5rem 1rem"
  button-secondary-dark:
    backgroundColor: "{colors.surface-dark}"
    textColor: "{colors.ink-on-dark}"
    rounded: "{rounded.lg}"
    padding: "0.5rem 1rem"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "0.625rem 1rem"
  input-dark:
    backgroundColor: "#374151"
    textColor: "{colors.ink-on-dark}"
    rounded: "{rounded.lg}"
    padding: "0.625rem 1rem"
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "{spacing.md}"
  nav-item-active:
    backgroundColor: "rgba(88, 28, 135, 0.2)"
    textColor: "{colors.control-violet-active}"
    rounded: "{rounded.lg}"
    padding: "0.625rem 0.75rem"
---

# Design System: Infinity ISP

## Overview

**Creative North Star: "La sala de control"**

Infinity ISP is a staff operating panel, not a product site. The dark rail (always `#111827`) is the control room wall; the content pane is the work surface—lists, forms, maps, cobros. Personality is utilitarian and precise: comfortable density, almost no ornament, color only on action and state.

The system is Tailwind gray + one violet accent, class-based light/dark on the content pane, and a single 8px corner. It does not pretend to be a crafted brand world. Future screens stay inside this room; they do not introduce a landing, a glass layer, or a second identity.

**Key Characteristics:**
- Dark rail that never follows the content theme
- Violet reserved for primary action, active nav, and focus
- System-ui sans at 14px for work text; 20px bold only for the product name and page titles
- Flat surfaces; depth from gray steps and a 1px stroke, not shadow
- `rounded-lg` (8px) on almost every control

## Colors

One accent, a gray operating scale, and semantic statuses. Light and dark are the same roles at different steps, not two palettes.

### Primary
- **Violeta de control** (`#9333ea`): Primary buttons, badges on the rail, the start of the 8×8 mark gradient. Use it so an operator can find the next action. Hover is `#7e22ce`. Focus ring is `#a855f7` at 20% fill. Active nav text on the rail is `#c084fc` over `purple-900/20`.

### Secondary
- **Rosa de marca** (`#db2777`): Only the far stop of the sidebar mark gradient (`from-purple-600 to-pink-600`). Not a second CTA color.

### Neutral
- **Rail** (`#111827`): Sidebar background; also the dark page canvas and light-mode ink.
- **Rail border / hover** (`#1f2937`): Sidebar hairline, nav hover wash, dark cards.
- **Page light** (`#f9fafb`): Content canvas in light mode.
- **Surface** (`#ffffff` / `#1f2937` dark): Cards, tables, header.
- **Stroke** (`#e5e7eb` / `#4b5563` dark): Default borders.
- **Ink muted** (`#6b7280`): Secondary copy, placeholders, timestamps.

### Named Rules
**The Control Violet Rule.** Violet is for action, the current nav item, a selected find-row, and focus. It is not a section wash, a hero, or a decorative gradient except on the 8×8 mark. A selected find-row reuses the active-nav wash; it is not a new accent.

**The Dark Rail Rule.** The sidebar stays `#111827` in light and dark. Only the content pane and header follow `html.dark`.

## Typography

**Display Font:** ui-sans-serif / system-ui (no custom face loaded in the panel)
**Body Font:** the same stack
**Label/Mono Font:** ui-monospace stack, only on recibos, IPs, coordinates, and codes

**Character:** The pairing is the platform UI face. No display serif, no marketing geometric. Hierarchy is weight and 14px vs 20px, not a second family.

### Hierarchy
- **Headline** (700, 1.25rem / 1.4): Product name in the rail and page titles.
- **Title** (600, 1rem / 1.5): Card and section headings.
- **Body** (400, 0.875rem / 1.25rem): Tables, descriptions, helper text. Default work size.
- **Label** (500, 0.875rem / 1.25rem): Nav items, buttons, field labels.
- **Mono** (400, 0.75rem): Technical strings; never body copy.

### Named Rules
**The Work-Size Rule.** Body and controls are 14px. Do not introduce a 16px “comfortable reading” default on staff lists and forms.

## Layout

Desktop is a fixed left rail 16rem expanded / 5rem collapsed, with the main shell offset to match (`lg` 1024px and up). Below `lg` the rail becomes a modal overlay. The header is sticky, 4rem tall, with `shadow-sm` leftover that is not a system token.

Content padding is `1rem` growing to `1.5rem` / `2rem` at `sm` / `lg`. Forms and index tables sit in a white (or gray-800) panel with a 1px stroke. Rhythm is Tailwind 4/8/16: `gap-2`, `space-y-1` in nav, `space-y-4` in forms.

A find+inspect split-pane (dense list + locked inspector) is a surface form, not a system grid. Do not extract its column widths into spacing tokens. The Usuarios Hotspot contract stays in `.impeccable/surfaces/resources-views-hotspot-index-blade-php.md`.

## Elevation & Depth

Flat by doctrine. Depth is a gray step plus a 1px border. Do not add a shadow scale.

Some incumbent controls still ship `shadow-sm` (header, inputs) or `shadow-lg` (dropdowns). Treat those as leftovers: do not extend them, and do not introduce new drop shadows on cards or buttons.

### Named Rules
**The Flat-By-Default Rule.** Surfaces are flush. Separation is stroke and tone, never a lift.

## Shapes

Gently curved work controls: **8px** (`rounded-lg`) on buttons, inputs, cards, nav rows, and the 8×8 mark. Full pills (`rounded-full`) only for avatars and numeric badges. Login uses `rounded-xl` on the auth card; do not spread that radius into the authenticated shell.

Borders are 1px `gray-200` / `gray-600`. No pills-as-buttons, no FAB, no squircle.

### Named Rules
**The Eight-Pixel Rule.** If it is a control in the panel, it is 8px. Full-round is for round objects (avatar, count), not for actions.

## Components

Refined and restrained: the same control everywhere, violet only on the primary.

### Buttons
- **Shape:** 8px corners; padding 8px 16px; 14px medium.
- **Primary:** Violeta de control on white label; hover `#7e22ce`; focus ring 2px `#a855f7`.
- **Secondary:** Gray fill (`#e5e7eb` / `#374151` dark), ink text, no violet.
- **Danger:** Text or bordered `#dc2626`, not a red fill unless the action is destructive and confirmed.

### Cards / Containers
- **Corner Style:** 8px
- **Background:** Surface white / gray-800
- **Shadow Strategy:** none (see Flat-By-Default)
- **Border:** 1px stroke
- **Internal Padding:** 16px typical; tables may flush the grid and keep padding on the toolbar

### Inputs / Fields
- **Style:** 8px, 1px stroke, 10px 16px padding, surface fill (`#374151` in dark).
- **Focus:** Border `#a855f7` and ring `2px` at 20% violet. Not a glow.
- **Error:** Border and message `#dc2626` / `#f87171` in dark.

### Navigation
- **Style:** Dark rail, 14px medium, 20px icons, 10px 12px rows, 8px corners.
- **Default:** `#e5e7eb` text, transparent row.
- **Hover:** `#1f2937` wash.
- **Active:** `#c084fc` text on `purple-900/20`.
- **Selected find-row:** Reuses Active. Same wash as the rail, not a list-specific color.
- **Mobile:** Same rail as a full-height overlay from `lg` down.

### Mark (signature)
8×8 rounded square, `linear-gradient(to bottom right, #9333ea, #db2777)`. The only place pink appears. Sit it beside “Infinity ISP” in 20px bold gray-100.

## Do's and Don'ts

### Do:
- **Do** keep the sidebar `#111827` regardless of `html.dark`.
- **Do** use Violeta de control for the one primary action on a screen.
- **Do** set body and form controls at 14px system-ui.
- **Do** use 8px radius and a 1px gray stroke to separate surfaces.

### Don't:
- **Don't** design a marketing landing, glassmorphism, or illustration into the staff panel.
- **Don't** introduce a second brand accent (blue, green) for primary actions — those appear in a few legacy screens and are not the system.
- **Don't** add drop shadows as a way to make a new card “finish.”
- **Don't** load a display or marketing typeface for the panel.
