# Deliz AI Advisor

AI-powered chat advisor for WooCommerce delicatessen shops. Adds a smart chat bubble on product pages that helps visitors with cooking advice, cut selection, and pairing recommendations — in **Hebrew, Russian, Arabic, and English**.

Powered by [Anthropic Claude](https://www.anthropic.com/).

## Features

- 💬 Chat bubble on product pages (fully customizable: colors, position, dimensions, typography)
- 🌐 Automatic language detection — replies in HE / RU / AR / EN in the visitor's language
- 🧠 Product-aware — the advisor knows the current product, price, category, weight, description, and related products
- ⚡ Response cache (7-day TTL by default) — identical first-turn questions are free
- 🛡️ Per-IP hourly rate limit + daily USD cap
- 📊 Statistics dashboard: conversations, unique visitors, conversion rate, revenue attributed, cache hit rate, language mix, top products
- 🛒 Conversion tracking — attributes add-to-cart events and order totals to the conversation that led to them
- 💾 Full conversation log with per-message tokens and 👍/👎 feedback
- 🔄 GitHub self-updater — new releases show up in the native WordPress updates UI
- 🔑 BYOK — use your own Anthropic API key (stored encrypted at rest)

## Requirements

- WordPress 6.0+
- PHP 7.4+
- **WooCommerce** (hard dependency — the plugin won't activate without it)
- An Anthropic API key with credits

## Installation

1. Clone or download into `wp-content/plugins/deliz-ai-advisor/`.
2. Activate in `Plugins` screen.
3. Go to **Deliz AI → Settings**, paste your Anthropic API key, click **Test Connection**.
4. Visit any product page — the chat bubble appears in the corner.

## Configuration

All customization lives under **Deliz AI → Settings** and is split into six tabs:

| Tab | What's there |
| --- | --- |
| **General** | API key, model (Haiku/Sonnet), shop name, daily USD cap, max tokens, which page types to show on |
| **Appearance** | 8 color pickers, position, border radius, panel dimensions, font family, shadow intensity, branding toggle |
| **Content & Languages** | Per-language title, greeting, placeholder, and 3 suggested questions — with a "Copy from Hebrew" quick action |
| **Behavior** | Rate limit, max message length, cache TTL, enabled languages, default language, feedback buttons, related-products inclusion |
| **Prompts** | System prompt template with merge-tag reference and per-language off-topic replies |
| **Advanced** | Debug mode, log retention, IP anonymization, cache/rate-limit maintenance buttons |

## Architecture

- **Autoloader**: PSR-4-ish, maps `Deliz\AI\Advisor\Admin\SettingsPage` → `includes/admin/class-settings-page.php`
- **Database**: 3 custom tables — `wp_deliz_ai_conversations`, `wp_deliz_ai_messages`, `wp_deliz_ai_cache`
- **REST namespace**: `deliz-ai/v1` — routes for `chat`, `feedback`, `test-key`
- **Frontend**: Vanilla ES2020, no jQuery, no build step. CSS custom properties injected inline from settings.
- **Security**: nonce-guarded REST, capability checks on admin, sanitized I/O, encrypted API key (AES-256-CBC via `wp_salt('auth')`)

## Roadmap

- [x] **Phase 0** — Scaffold & git repo
- [x] **Phase 1** — Bootstrap, activator, requirements check
- [x] **Phase 2** — Settings page shell + General tab
- [x] **Phase 3** — Anthropic client + Test Key endpoint
- [x] **Phase 4** — Chat endpoint + PromptBuilder
- [x] **Phase 5** — Frontend widget
- [x] **Phase 6** — Conversation logging + log viewer
- [x] **Phase 7** — Rate limiting + daily USD cap
- [x] **Phase 8** — Response cache
- [x] **Phase 9** — Appearance customization
- [x] **Phase 10** — Per-language content tabs
- [x] **Phase 11** — Language detection + WPML/Polylang
- [x] **Phase 12** — Statistics dashboard
- [x] **Phase 13** — Conversion tracking
- [ ] Phase 14 — Streaming responses (SSE) — planned
- [x] **Phase 15** — GitHub self-updater
- [x] **Phase 16** — Onboarding notice + polish

## License

GPL-2.0-or-later. See [`LICENSE`](./LICENSE).
