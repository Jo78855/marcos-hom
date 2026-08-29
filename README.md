# Marco's Home control panel

React/Vite operations panel for the existing customer website at
`https://marcohom.com`. The WordPress site remains the customer-facing website;
this project does not replace its theme, pages, or design.

## Production architecture

- Customer website: `marcohom.com` (existing WordPress installation)
- Control panel: this React application, deployed from `v2/marcos-home-clean`
- Server checkout: `/opt/marcos-home`
- Database: Supabase project `marcos-home-production`
- Canonical operations tables: `mh_orders`, `mh_customers`, `mh_catalog`

The deprecated `/shop` route is intentionally not part of this application.
Orders confirmed through the Marco assistant are written to the same `mh_*`
tables read by the control panel.

## Voice assistant prototype

The current development branch includes a website voice assistant connected to Supabase knowledge data.

- Customer assistant: main storefront (microphone where browser speech recognition is available; text fallback otherwise)
- Assistant knowledge admin: `/admin/assistant`
- Knowledge table: `assistant_offers`
- Current scope: Design 198 components, wall-width tiers, with/without-installation prices

The assistant reads active knowledge rows at page load, so price/component changes made from the assistant admin page do not require code changes.
