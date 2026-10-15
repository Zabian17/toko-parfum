# Toko Albi Parfum — Design System & Visual Direction

## Brand Essence
**Albi Parfum** is an artisanal luxury perfumery based in Jakarta, curating premium extrait de parfum and eau de parfum. The aesthetic is modern quiet luxury: editorial, tactile, restrained, and timeless (inspired by Byredo, Le Labo, and Tom Ford Private Blend).

## Visual Dials
- **ENERGY:** 2/3 — Refined and intentional, avoiding cheap frantic animations or neon glows.
- **RHYTHM:** 2/3 — Structured editorial cadence, alternating between light breathing spaces and focused focal points.
- **MOTION:** 1/3 — Pure compositor micro-transitions (subtle fade & transform <= 250ms), no looping bouncing orbs.

## Color Palette
- **Base Surfaces:**
  - Canvas: `#FBFBF9` (Warm Alabaster Silk)
  - Surface White: `#FFFFFF`
  - Subtle Neutral: `#F3F2EE` (Warm Parchment)
  - Border Hairline: `rgba(22, 22, 22, 0.08)` / `#E7E5DF`
- **Typography & Dark Contrast:**
  - Ink Charcoal: `#161616` (Deep Obsidian Velvet)
  - Secondary Slate: `#555450`
  - Subtle Metadata: `#888680`
- **Accent & Brand Soul:**
  - Artisan Bronze Gold: `#9E7A36`
  - Gold Light / Hover: `#BFA05A`
  - Gold Tint (5-10%): `rgba(158, 122, 54, 0.08)`
- **Feedback & Semantics:**
  - Success: `#2E7D32` (Forest Green)
  - Danger / Error: `#C62828` (Crimson Wine)
  - Warning / Stock: `#E65100` (Amber Rust)

## Typography
- **Serif (Headings, Product Titles, Editorial Moments):** `Playfair Display`, serif.
- **Sans-Serif (Body, Controls, Form Inputs, Micro-copy):** `Inter`, system-ui, sans-serif.
- **Data & Numbers:** Tabular numerals `font-feature-settings: "tnum" 1` for prices, dates, tracking numbers.

## Elevation & Shadows
- Discrete ground planes:
  - Low: `0 2px 6px rgba(0, 0, 0, 0.04)`
  - Medium: `0 8px 24px -4px rgba(0, 0, 0, 0.07)`
  - Floating / Modal: `0 20px 48px -8px rgba(0, 0, 0, 0.12)`
- No fuzzy infinite glow or neon shadows.

## Component Rules
1. **Product Cards**: Focus on the fragrance bottle and typography. Elegant image framing (1:1), delicate tags, no loud yellow supermarket block buttons.
2. **Buttons**: High contrast. Primary action is solid obsidian `#161616` with light text or clean antique gold.
3. **Admin Dashboard**: Sleek carbon sidebar (`#111215`), minimalist metric tiles with tabular stats, clean data tables with crisp borders.
