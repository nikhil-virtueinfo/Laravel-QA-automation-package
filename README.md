# Autonomous Laravel System-Test & Deep QA Framework

[![Latest Version on Packagist](https://img.shields.io/packagist/v/deep-qa/laravel-system-test-framework.svg?style=flat-square)](https://packagist.org/packages/deep-qa/laravel-system-test-framework)
[![Total Downloads](https://img.shields.io/packagist/dt/deep-qa/laravel-system-test-framework.svg?style=flat-square)](https://packagist.org/packages/deep-qa/laravel-system-test-framework)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

Autonomous 360° Codebase Review, Micro-UI Contract & Dusk Test Suite Generator for Laravel applications (CMS Admin, Frontend, and REST APIs).

---

## 🌟 Key Capabilities

* **Zero-AI & 100% Deterministic:** Deep inspection without third-party LLM API keys or hallucination risks.
* **Micro-UI & Computed CSS Checking:** Verifies exact button colors (e.g. Blue vs Black), typography, labels, and placeholders.
* **Automatic Dependency Provisioning:** Auto-detects and installs Laravel Dusk & Chrome driver if missing.
* **Autonomous 360° Review:**
  * **CMS Admin:** Scans CRUD, Form inputs, validations, AJAX switches, sorting, and DataTables.
  * **Frontend:** Scans authentication forms, search/filter bars, and interactive widgets.
  * **REST API:** Generates Bearer token barriers (401), empty payload validations (422), and response schema contracts (200).
* **Live Admin Web Dashboard:** Interactive UI at `/admin/system-tests` with progress indicators, screenshot evidence, and defect comparisons.

---

## 🚀 Installation

Require the package as a development dependency:

```bash
composer require deep-qa/laravel-system-test-framework --dev
```

### Run Setup Scaffolding:

```bash
php artisan system-test:install
```

This single command:
1. Detects PHP and Laravel versions.
2. Auto-installs `laravel/dusk` and provisions Chrome Driver if missing.
3. Initializes test directories under `tests/Browser/DeepQA/` and `storage/app/system-tests/`.
4. Publishes configuration to `config/system-test.php`.
5. Publishes the live Admin Dashboard views (`index.blade.php`, `report.blade.php`).
6. Publishes `SystemTestController.php` and auto-registers the web routes.

---

## 🛠️ Usage

### 1. Perform 360° Codebase Review & Generate Tests

Inspect the entire project across CMS, Front, and API:

```bash
php artisan system-test:generate-deep-tests
```

**Targeted Generation Options:**
```bash
# Generate only REST API test contracts
php artisan system-test:generate-deep-tests --type=api

# Generate only CMS Admin panel tests
php artisan system-test:generate-deep-tests --type=cms

# Generate only Frontend Web tests
php artisan system-test:generate-deep-tests --type=front

# Inspect and generate for a single specific route URI
php artisan system-test:generate-deep-tests --route=listing-category/create
```

---

### 2. Run All System Tests

Execute all 7 full-system test suites:

```bash
php artisan system-test:run
```

**Or run in visible headed mode:**
```bash
php artisan system-test:run --headed
```

**Or access the live visual dashboard:**
Navigate to `http://localhost:8000/admin/system-tests` in your browser.

---

## 📁 Generated Test Directory Architecture

```text
tests/
├── Browser/
│   └── DeepQA/
│       ├── Admin/               # CMS Admin CRUD lifecycle & UI style tests
│       └── Front/               # Public web & interactive widget tests
└── Feature/
    └── DeepQA/
        └── Api/                 # REST API schema & authentication contracts
```

---

## 📝 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

