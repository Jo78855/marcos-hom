# Marcos Home

React/Vite storefront and admin tools for Marco's Home.

## Voice assistant prototype

The current development branch includes a website voice assistant connected to Supabase knowledge data.

- Customer assistant: main storefront (microphone where browser speech recognition is available; text fallback otherwise)
- Assistant knowledge admin: `/admin/assistant`
- Knowledge table: `assistant_offers`
- Current scope: Design 198 components, wall-width tiers, with/without-installation prices

The assistant reads active knowledge rows at page load, so price/component changes made from the assistant admin page do not require code changes.
