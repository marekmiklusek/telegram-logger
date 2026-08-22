# Changelog

All notable changes to `marekmiklusek/telegram-logger` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-08-22

This release fixes several bugs that caused log messages to be dropped without any
trace, and raises the minimum PHP and Laravel versions.

### Upgrading from 1.x

- **PHP 8.4 and Laravel 13 are now required.** On PHP 8.2/8.3 or Laravel 12,
  Composer will keep offering 1.x — that is expected, not a broken update.
- **Republish the config** (`php artisan vendor:publish --tag=telegram-logger-config
  --force`) to pick up the new keys. An existing published config keeps working:
  every new setting falls back to a safe default.
- Nothing in the public API changed. `Log::error()` and friends behave as before.

### Fixed

- Long messages were silently lost. The 4096 character limit was measured in bytes
  instead of characters, and truncation could cut a multibyte character in half or
  break a Markdown entity, which made Telegram reject the whole message.
- `Log::error()` could take the application down. A malformed `, called in` suffix
  in an exception message, or a `null` value in the app config, threw from inside
  the log listener and propagated into the calling code.
- Delivery failures were invisible. The API response was never read, so an invalid
  bot token, a wrong chat ID or a formatting error all looked identical to success.
- Backslashes were not escaped for MarkdownV2, so class names such as
  `App\Models\User` were mangled or rejected by Telegram.
- Non-ASCII context values were sent as `\uXXXX` escapes and became unreadable.
- Invalid UTF-8 anywhere in the context silently dropped the entire context block.
- A very long `app.name` could push the payload past the Telegram limit.
- An outdated published config threw `InvalidArgumentException` on every log call
  because newer keys were missing.
- A disabled `allow_url_fopen` made the package fail silently; it now throws with
  an actionable message.
- Messages containing invalid UTF-8 arrived unformatted, because Telegram rejected
  the MarkdownV2 payload and the package fell back to plain text. Such bytes are
  now replaced before escaping, so formatting survives; valid characters and emoji
  are left untouched.

### Added

- Flood protection: `dedupe_seconds` suppresses an identical message repeated
  within a window, and `max_per_minute` caps how many messages are sent per minute.
  Both are skipped when no cache is available, so logging never stops.
- Redaction: values under `redact_keys` (`password`, `token`, `authorization`, …)
  are replaced with `[REDACTED]`, including in nested arrays.
- `throw_on_failure` surfaces delivery errors instead of swallowing them — useful
  in local development.
- `api_url` for routing through a proxy or a self-hosted Bot API instance.
- Automatic plain text retry: when Telegram rejects the formatting with HTTP 400,
  the message is resent unformatted rather than lost.
- The exception class name is now shown alongside the exception message.
- All settings are configurable through `.env`.
- Test suite with 100% code and type coverage, PHPStan at level max, Pint, Rector
  and a CI matrix over PHP 8.4/8.5 and both dependency stabilities.

### Changed

- Requires PHP `^8.4` and Laravel `^13.0`.
- Depends on `illuminate/support` and `illuminate/contracts` instead of the full
  `laravel/framework`.
- Messages are sent with POST instead of GET, so log content no longer ends up in
  proxy and access logs.
- Boot fails with a clear error when `level` is not a valid PSR-3 level, instead of
  silently sending nothing.

[2.0.0]: https://github.com/marekmiklusek/telegram-logger/releases/tag/v2.0.0
