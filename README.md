# Deliz AI Advisor

AI-powered chat advisor for WooCommerce delicatessen shops. Adds a smart chat bubble on product pages that helps visitors with cooking advice, cut selection, and pairing recommendations — in Hebrew, Russian, Arabic, and English.

Powered by [Anthropic Claude](https://www.anthropic.com/).

## Status

🚧 **Under active development.** Currently: Phase 0 (scaffold).

## Requirements

- WordPress 6.0+
- PHP 7.4+
- WooCommerce (hard dependency)
- An Anthropic API key (BYOK — bring your own key)

## Installation

1. Download/clone into `wp-content/plugins/deliz-ai-advisor/`.
2. Activate in `Plugins` screen.
3. Go to `Deliz AI → Settings` and paste your API key.

## Development

See [`DELIZ-AI-ADVISOR-SPEC.md`](../DELIZ-AI-ADVISOR-SPEC.md) in the parent directory for the full product spec and roadmap.

### Roadmap

- [x] **Phase 0** — Scaffold, git repo, plugin header
- [ ] Phase 1 — Bootstrap, activator, requirements check
- [ ] Phase 2 — Settings page skeleton
- [ ] Phase 3 — Anthropic client + test-key endpoint
- [ ] Phase 4 — Chat endpoint
- [ ] Phase 5 — Widget UI
- [ ] Phase 6 — Conversation logging
- [ ] Phase 7 — Rate limiting + protections
- [ ] Phase 8 — Response cache
- [ ] Phase 9 — Appearance customization
- [ ] Phase 10 — Content tabs (per-language)
- [ ] Phase 11 — Multi-language detection
- [ ] Phase 12 — Statistics dashboard
- [ ] Phase 13 — Conversion tracking
- [ ] Phase 14 — Streaming responses
- [ ] Phase 15 — GitHub self-updater
- [ ] Phase 16 — Polish + onboarding

## License

GPL-2.0-or-later. See [`LICENSE`](./LICENSE).
