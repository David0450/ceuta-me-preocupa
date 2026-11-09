# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project follows [Semantic Versioning](https://semver.org/).

Versions 1.x were released from the web application's own repository and are
tagged here as `web-v1.x`. Version 2.0.0 is the first release of the monorepo.

## [2.0.1] - 2026-11-09

### Fixed
- Release 2.0.0 was tagged on an earlier commit by mistake and does not
  contain the changes listed below. Version 2.0.1 contains them.

## [2.0.0] - 2026-11-09

### Added
- Monorepo that brings together the web application (`web/`) and the AI
  pipeline (`ai/`), keeping the history of both original repositories.
- AI pipeline (`ai/qwen_chat.py`) running Qwen3-8B locally in 4-bit: reads
  pending submissions from the database, removes direct identifiers with
  regular expressions and an LLM rewriting step, masks offensive language,
  checks for emergency keywords, proposes a category, up to two subcategories
  and an urgency level, and stores the proposal in `propuestas_ia`.
- `procesado` column in `datos_originales` (new migration) so the pipeline
  knows which submissions it has already handled.
- Test suite for the AI pipeline (`ai/tests`) that runs without a GPU, model
  or database.
- GitHub Actions workflow running the web and AI test suites on every push
  and pull request.
- `CITATION.cff`, `CHANGELOG.md`, architecture diagram and full installation
  instructions in the README.

### Fixed
- AI proposals stored with the old urgency values (`alta`, `media`) were
  ignored by the review panel, so an emergency detected by the AI did not move
  up the list. A migration converts them to `urgente` and `ordinaria`.

### Changed
- The AI pipeline only produces the urgency values used by the web
  application (`ordinaria`, `prioritaria`, `urgente`); any other value from
  the model falls back to `ordinaria`, and detected emergencies are proposed
  as `urgente`.
- The AI pipeline resolves subcategories from the `subcategorias` table
  instead of only from subcategories already used by earlier submissions.
- The static landing page moved to `web/legacy-landing` and is no longer
  deployed; the Laravel application serves the public site.
- The code is released under the MIT License, held by the Cátedra
  Ceuta-IATEB, Universidad de Granada.

## [1.2.0] - 2026-10-09 (`web-v1.2`)

### Added
- Public section with the number of submissions per category.

## [1.1.1] - 2026-10-07 (`web-v1.1.1`)

### Fixed
- Time zone mismatch between Laravel and MySQL timestamps.

## [1.1.0] - 2026-10-07 (`web-v1.1`)

### Added
- Review panel for authorised reviewers: privacy and classification review,
  urgency, competent body, duplicates, state changes and audit log.

## [1.0.0] - 2026-10-01 (`web-v1.0`)

### Added
- Laravel application with the public submission form, separation of
  personal data into restricted tables, encrypted contact channel and a
  login-protected admin area.

[2.0.1]: https://github.com/David0450/ceuta-me-preocupa/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/David0450/ceuta-me-preocupa/compare/web-v1.2...v2.0.0
[1.2.0]: https://github.com/David0450/ceuta-me-preocupa/compare/web-v1.1.1...web-v1.2
[1.1.1]: https://github.com/David0450/ceuta-me-preocupa/compare/web-v1.1...web-v1.1.1
[1.1.0]: https://github.com/David0450/ceuta-me-preocupa/compare/web-v1.0...web-v1.1
[1.0.0]: https://github.com/David0450/ceuta-me-preocupa/releases/tag/web-v1.0
