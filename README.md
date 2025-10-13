# Grade (Moodle Gradebook Component)

This repository contains the Gradebook component for a Moodle installation. It provides grade calculation, management, import/export, reporting, and grading UI for courses.

The codebase follows Moodle's core architecture: PHP for backend logic, Mustache templates for rendering, and AMD JavaScript modules for interactive client-side features. Automated tests are provided using PHPUnit and Behat.


## Contents
- Overview
- Getting started (developers)
- Project layout and responsibilities
- Backend architecture and conventions
- Frontend (AMD) modules
- Rendering and templates
- Import and export
- Reports
- Grading UI and advanced grading
- Web services (external API)
- Privacy compliance
- Tests
- Upgrading and compatibility
- Contributing and code style


## Overview
- Purpose: Course grade management and reporting.
- Scope: Grade items/categories, calculations, imports/exports, multiple report UIs, advanced grading methods, privacy, and APIs for integration.
- Technologies: PHP (Moodle APIs), Mustache templates, AMD JavaScript.


## Getting started (developers)
1. Place this directory as `grade/` within a Moodle source tree (this is generally part of Moodle core).
2. Use Moodle's standard setup to install dependencies and configure a development site.
3. JavaScript sources live under `amd/src/`; built assets are under `amd/build/` and are generally produced by Moodle's build tooling.
4. Run tests through Moodle's PHPUnit and Behat runners (see Tests section below).

Notes:
- This component relies on Moodle core libraries and environment. It is not a standalone application.
- Built JavaScript is checked in under `amd/build/` so a separate build step is not strictly required for development unless you modify `amd/src/`.


## Project layout and responsibilities

Top-level files:
- `index.php`: Entry point for Gradebook index pages.
- `lib.php`: Core library functions for the Grade component used by other Moodle parts.
- `querylib.php`: Helpers/data-access for Gradebook queries.
- `renderer.php`: PHP renderers that bridge back-end data to Mustache templates.
- `upgrade.txt`, `UPGRADING.md`: Notes for developers and administrators about changes.

Directories:

- `amd/`
  - `src/`: Source AMD JavaScript modules grouped by feature (e.g., `bulkactions`, `comboboxsearch`, `grades`, `searchwidget`).
  - `build/`: Compiled/minified JS produced from `src/`. Subfolders mirror `src/` structure (e.g., `grades`, `searchwidget`). Files with `.map` provide source maps.
  - Purpose: Client-side interactivity for Gradebook pages (UI widgets, actions, dynamic behaviours).

- `classes/`
  - Namespaced PHP classes following Moodle's autoloading.
  - Subdirectories:
    - `component_gradeitem.php`, `component_gradeitems.php`: Core grade item component classes.
    - `external/`: Web service endpoint definitions (e.g., `get_grade_tree.php`, `get_gradable_users.php`, `get_feedback.php`). Implement WS functions callable via Moodle's web services.
    - `form/`: PHP forms for adding categories/items/outcomes (`add_category.php`, `add_item.php`, `add_outcome.php`).
    - `grades/grader/`: Classes used by the grader report and related logic.
    - `local/gradeitem/`: Local helper classes related to grade items.
    - `output/`: Output classes rendered by templates (action bars, toggles, etc.).
    - `privacy/`: Privacy provider implementations (e.g., GDPR compliance interfaces).

- `edit/`
  - UI and handlers for editing grade letters, outcomes, scales, settings, and the grade tree structure.
  - Subdirectories:
    - `letter/`: Manage grade letters (A/B/C…), form and index pages.
    - `outcome/`: Outcomes management.
    - `scale/`: Scale management.
    - `settings/`: Grade settings UI.
    - `tree/`: Edit grade categories/items and tree-specific operations.

- `export/`
  - Export flows, formats, and utilities.
  - Files: `index.php`, `lib.php`, forms for export and key management.
  - Subdirectories for formats:
    - `ods/`, `xls/`, `xml/`, `txt/`: Each contains the implementation for its export format.

- `grading/`
  - Advanced grading methods framework and UI integration.
  - Contains PHP classes, renderers, and many form definitions used when configuring/running advanced grading workflows.

- `import/`
  - Import flows for grades.
  - Files: `index.php`, `lib.php`, forms for import and key management.
  - Subdirectories by mechanism:
    - `csv/`, `direct/`, `xml/`: Implementations for importing grades from different sources.

- `report/`
  - Multiple Gradebook report UIs and logic.
  - Submodules:
    - `grader/`: The main grader report (grid-like per-course overview). Includes PHP, JS, Behat features.
    - `history/`: Historical grades view and audit.
    - `outcomes/`: Outcomes reporting.
    - `overview/`: Student overview report.
    - `singleview/`: Single view report for per-item or per-user editing.
    - `summary/`: Course grade summary.
    - `user/`: User-specific grade report.

- `templates/`
  - Mustache templates used by renderers and JS to output UI. Includes action bars, status icons, grade setup components, weights fields, search widgets, etc.

- `tests/`
  - PHPUnit (`*.php`) and Behat (`*.feature`) tests covering core elements, export/import, reports, privacy, and rendering.
  - `fixtures/` provides sample data files for tests (e.g., CSVs).


## Backend architecture and conventions
- Follows Moodle coding guidelines and architecture.
- `lib.php` provides callback implementations and library functions consumed by Moodle core.
- Classes under `classes/` follow PSR-4-like autoloading via Moodle's naming conventions.
- Forms under `classes/form/` use Moodle Form API for validation and processing.
- External web services under `classes/external/` define `execute_parameters`, `execute`, and `returns` for service functions.
- Rendering is split between `renderer.php` and Mustache templates under `templates/`.


## Frontend (AMD) modules
- Source: `amd/src/` organized by feature.
- Build: Artifacts in `amd/build/` (minified JS and source maps). These are typically generated via Moodle's build tasks.
- Usage: Modules are loaded with `require(['grade/...'], function(module) { ... });` within Moodle pages or via auto-init bindings.
- Common modules include:
  - `bulkactions`: Operations applied to multiple grade items/users.
  - `comboboxsearch`: Enhanced combobox search UI.
  - `grades`: UX helpers for the grader report and editing.
  - `searchwidget`: User/group search components.


## Rendering and templates
- PHP `renderer.php` and output classes (`classes/output/*`) prepare data contexts.
- Templates (`templates/*.mustache`) render the UI; they are logic-less and receive fully prepared context data.
- Client-side templates may also be used by AMD modules for dynamic content.


## Import and export
- Export formats: `ods`, `xls`, `xml`, `txt` – each implements its own formatter and writer.
- Import formats: `csv`, `direct`, `xml` – each parses incoming data and maps to grade items/users with validation and preview steps.
- Keys and publishing: `export/key*.php` allow controlled access to exports.


## Reports
- Reports are modular under `report/` and may include their own JS, templates, and tests.
- Primary entry points are `report/*/index.php` and related library files.
- The grader report (`report/grader/`) provides the grid, inline editing, and bulk operations.


## Grading UI and advanced grading
- The `grading/` directory implements advanced grading methods and integrates them with activities and the Gradebook.
- Numerous forms under `grading/form/` configure rubrics, marking guides, etc.


## Web services (external API)
- Implemented under `classes/external/` with strict parameter and return definitions.
- Provide access to enrolled users, grade trees, feedback, gradeable users, groups for widgets, and more.
- These are callable through Moodle's standard web service frameworks.


## Privacy compliance
- `classes/privacy/` contains providers and data structures for privacy (GDPR) compliance, specifying stored data, purposes, and export/delete behaviours.


## Tests
- PHPUnit tests under `tests/*.php` cover library logic, components, and reports.
- Behat feature files (`*.feature`) cover UI workflows.
- Fixtures under `tests/fixtures/` feed repeatable data sets.
- Execute tests using Moodle's documented test runners from the Moodle root.


## Upgrading and compatibility
- See `upgrade.txt` and `UPGRADING.md` for notes on API changes, deprecations, and migration steps between Moodle versions.
- When upgrading Moodle, review these files to adjust customizations or integrations.


## Contributing and code style
- Follow Moodle coding guidelines (PHPDoc, naming, exceptions, and security practices).
- Prefer clarity and maintainability:
  - Descriptive names for classes, functions, and variables.
  - Early returns for control flow.
  - Avoid catching exceptions without meaningful handling.
  - Keep comments for non-obvious rationale and invariants.
- Add tests for new features and bug fixes.
- When adding JS in `amd/src/`, ensure the corresponding build artifacts are updated.


## Where to look for common tasks
- Add a new export format: create a subfolder under `export/` and wire it into `export/lib.php`.
- Add a new import processor: create a subfolder under `import/` and integrate with `import/lib.php`.
- Extend grader report behaviour: look under `report/grader/` and `amd/src/grades/`.
- Add a new web service: implement in `classes/external/` and register via Moodle's WS configuration.
- Add UI components: create Mustache templates under `templates/` and render via `renderer.php` or output classes.
