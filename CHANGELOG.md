# Changelog

All notable changes to `vulcaire/staffapp-laravel` are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project uses [semantic versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.0 - 2026-11-19

First public release of the AssignPoint Laravel integration.

- Service provider, published configuration, facade and scoped client binding for `vulcaire/staffapp-php` `^0.1`
- Opt-in webhook route and signature middleware
- Reconcile command and queued mirror pushes with exponential backoff
- Payload helpers that send only the fields AssignPoint accepts
- Supports Laravel 11 and 12 on PHP 8.2 or newer
