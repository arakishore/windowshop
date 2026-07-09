# Changelog

All notable changes to WindowShop are documented here.

This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) conventions. Versions should use semantic versioning once public releases begin.

## [Unreleased]

### Added

- Initial Laravel 12 project structure.
- Extended users schema with public UUID, mobile verification, account status, login metadata, and soft deletes.
- Administrator profile schema with audit fields.
- User session tracking schema.
- Permanent login-attempt logging schema.
- Initial architecture, database, security, audit, integration, and testing documentation.
- UI/UX, business rules, performance, deployment, and master data documentation.
- Architecture Decision Record catalogue covering authentication, users, UUIDs, database standards, sessions, soft deletes, statuses, and audit logging.

### Changed

- Established database comments for non-obvious business fields and allowed values.
- Established PHP 8.2 backed enums as the application convention for enum-like values.
- Renamed the living V1 planning document to `00_Project_Backlog.md`.

### Security

- Defined session tracking, permanent login-attempt history, and secure authentication baselines.
