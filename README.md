# Ceuta Me Preocupa

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE.md)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12-ff2d20)](https://laravel.com/)
[![Python](https://img.shields.io/badge/Python-3.10%2B-3776ab)](https://www.python.org/)

**Ceuta Me Preocupa** ("Ceuta concerns me") is a virtual citizen mailbox for the Autonomous City of Ceuta (Spain). Citizens submit non-urgent concerns and improvement proposals through a web form; the system stores them with personal data separated from the working record, uses a locally hosted language model to propose a de-identified text, a category and an urgency level, and gives a team of human reviewers a panel to confirm, correct and route each submission to the competent public body.

The project is developed at the **Cátedra Ceuta-IATEB** (Artificial Intelligence and Emerging Technologies for Well-being), a joint initiative of the Universidad de Granada and the Ciudad Autónoma de Ceuta. It follows the internal *Protocolo del buzón virtual ciudadano de Ceuta*, included in [`web/`](web/).

Live site: <https://ceutamepreocupa.es>

<!-- TODO: authors, ORCID and contact e-mail before submitting the paper -->

---

## Contents

- [What it does](#what-it-does)
- [What it is not](#what-it-is-not)
- [Architecture](#architecture)
- [Privacy model](#privacy-model)
- [Data model](#data-model)
- [Categories](#categories)
- [Repository structure](#repository-structure)
- [Installation](#installation)
- [Running the AI pipeline](#running-the-ai-pipeline)
- [Tests](#tests)
- [Current limitations](#current-limitations)
- [License](#license)

---

## What it does

1. **Collects submissions.** A public form (Spanish) asks for a description, an optional expected outcome, a category and one or two subcategories, an optional area of the city, an optional suggested public body and an optional contact channel. Submissions are rate-limited (3 per minute and 10 per hour per IP) and filtered against bots with a honeypot field and a minimum fill-in time.
2. **Separates personal data on arrival.** The original text and the contact channel are written to their own tables; the working record keeps only the classification and, later, the de-identified text.
3. **Proposes de-identification and classification with AI.** A Python pipeline running Qwen3-8B on local hardware reads pending submissions, removes personal data, flags emergencies by keyword, and proposes a category, up to two subcategories and an urgency level.
4. **Keeps a human in the loop.** Authorised reviewers check every proposal in an admin panel: they confirm or correct privacy redaction, classification, urgency and the competent body, mark duplicates, and move the submission through its states. Every change is logged.
5. **Publishes aggregate metrics.** The public page shows the number of submissions per category.

## What it is not

The form states these limits to citizens before they submit:

- It is **not an administrative complaint or appeal**, and it does not open an official procedure (*expediente*).
- It does **not guarantee** that the proposed measure will be adopted.
- It is **not an emergency channel**: urgent situations must go to 112, the police or the health services.

---

## Architecture

```
 Citizen ──► Web form (Laravel)
                │
                ▼
        ┌───────────────────────────────┐
        │ MySQL / MariaDB               │
        │  sugerencias        (working) │
        │  datos_originales  (restricted)│
        │  contactos (restricted, encrypted)
        └──────┬─────────────────▲──────┘
               │ pending rows    │ propuestas_ia
               ▼                 │
        ┌───────────────────────────────┐
        │ AI pipeline (Python, Qwen3-8B)│
        │  1. regex + LLM de-identification
        │  2. emergency keyword check   │
        │  3. category / urgency proposal│
        └───────────────────────────────┘
               │
               ▼
        Review panel (Laravel + Livewire, reviewers only)
               │
               ▼
        Routing to the competent body · public metrics
```

| Component | Folder | Stack |
|---|---|---|
| Web application (form, review panel, metrics) | [`web/backend`](web/backend) | PHP 8.2, Laravel 12, Laravel Breeze (Blade), Livewire 4, Tailwind CSS, Vite |
| AI pipeline | [`ai`](ai) | Python 3.10+, PyTorch, Hugging Face Transformers, bitsandbytes (4-bit), pandas, PyMySQL |
| Static landing page (first version) | [`web`](web) | HTML, CSS, JavaScript |

The language model runs on infrastructure controlled by the project (the Universidad de Granada supercomputer), so citizen texts are not sent to third-party AI services.

---

## Privacy model

The protocol distinguishes three levels of data. They are separated logically within a single database, by table, by encryption and by access rights:

| Level | Where | Contents | Who can see it |
|---|---|---|---|
| **A – Restricted** | `datos_originales`, `contactos` | Original text and expected outcome as written by the citizen; area of the city; contact channel (encrypted at rest with Laravel's `encrypted` cast) | Management team only |
| **B – Pseudonymised working record** | `sugerencias`, `propuestas_ia`, `historial_sugerencias` | Category, subcategories, competent body, urgency, status, de-identified text, review and audit data | Reviewers |
| **C – Aggregated** | Public page | Counts per category | Everyone |

De-identification combines three layers:

- **Regex detection** of DNI, NIE, IBAN, e-mail addresses, Spanish phone numbers and street addresses ([`DetectorDatosPersonales`](web/backend/app/Support/DetectorDatosPersonales.php) in the web app, and an equivalent step in the pipeline), replaced with `[dato personal eliminado]`.
- **LLM rewriting** to remove indirect identifiers the patterns miss.
- **Human review**: a submission cannot advance until a reviewer has confirmed the de-identified text.

Removing direct identifiers does not make the data anonymous: Level B remains pseudonymised personal data under the GDPR, which is why access to it is restricted to reviewers.

---

## Data model

Main tables (see [`web/backend/database/migrations`](web/backend/database/migrations)):

| Table | Purpose |
|---|---|
| `sugerencias` | Working record: reception date, category chosen by the citizen and current category, competent body, `estado` (`recibido`, `en_revision`, `clasificado`, `derivado`, `cerrado`), `nivel_urgencia` (`ordinaria`, `prioritaria`, `urgente`), de-identified texts, review stamps, `duplicado_de_id`, action taken, soft delete |
| `datos_originales` | Level A, 1:1 with `sugerencias`: original text, expected outcome, suggested body, area |
| `contactos` | Level A, 1:1 with `sugerencias`: encrypted contact channel and consent |
| `propuestas_ia` | Proposals written by the AI pipeline, plus the reviewer's verdict on each field (used to measure model accuracy) |
| `historial_sugerencias` | Audit log of every change made by reviewers |
| `categorias`, `subcategorias`, `sugerencia_subcategoria` | Taxonomy and the pivot linking each submission to 1–2 subcategories |
| `organismos` | Public bodies (`autonomico`, `estatal`, `universitario`, `otro`) |
| `users` | Staff accounts; `es_revisor` grants access to the review panel |

Duplicates are never deleted: they point to the original through `duplicado_de_id`, so the total count of submissions is preserved.

---

## Categories

Nine categories, seeded by [`CategoriaSeeder`](web/backend/database/seeders/CategoriaSeeder.php):

| Category | Subcategories |
|---|---|
| Urbanismo | Planeamiento, Licencias, Vivienda, Obras, Accesibilidad urbana, Patrimonio |
| Movilidad | Transporte público, Tráfico, Aparcamiento, Itinerarios peatonales, Accesibilidad, Carga y descarga |
| Educación no universitaria | Centros, Escolarización, Profesorado, Currículo, Formación profesional, Convivencia escolar |
| Universidad | Docencia, Investigación, Becas, Instalaciones, Vida universitaria |
| Economía | Empleo, Comercio, Emprendimiento, Turismo, Ayudas, Transformación económica |
| Sanidad | Salud pública, Atención sanitaria, Accesibilidad sanitaria, Prevención |
| Seguridad | Seguridad ciudadana, Seguridad de instalaciones, Policía local, Prevención |
| Servicios Sociales | Atención social, Dependencia, Inclusión, Vulnerabilidad, Igualdad, Apoyo comunitario |
| Medioambiente | Residuos, Limpieza, Ruido, Zonas verdes, Contaminación, Sostenibilidad |

Each submission is routed to a body of the Autonomous City or of the General State Administration. When competence is unclear, it is marked as *competencia por confirmar* instead of being rejected.

---

## Repository structure

```text
ceuta-me-preocupa/
├── ai/
│   ├── qwen_chat.py             # AI pipeline: de-identification, emergency check, classification
│   ├── db_config.example.py     # Database settings template (copy to db_config.py)
│   └── requirements.txt
├── web/
│   ├── backend/                 # Laravel application
│   │   ├── app/
│   │   │   ├── Http/Controllers/        # Public form and admin controllers
│   │   │   ├── Models/                  # Sugerencia, DatoOriginal, Contacto, PropuestaIa…
│   │   │   └── Support/DetectorDatosPersonales.php
│   │   ├── database/migrations/ # Schema
│   │   ├── database/seeders/    # Taxonomy and test submissions
│   │   ├── resources/views/     # Blade views and Livewire single-file components
│   │   ├── routes/web.php
│   │   └── tests/
│   ├── index.html, styles.css, script.js   # Static landing page (first version)
│   └── Protocolo del buzón virtual ciudadano de Ceuta.docx / .txt
├── LICENSE.md
└── README.md
```

---

## Installation

### Web application

Requirements: PHP 8.2+, Composer, Node.js 18+ and npm, MySQL or MariaDB.

```bash
git clone https://github.com/David0450/ceuta-me-preocupa.git
cd ceuta-me-preocupa/web/backend

cp .env.example .env            # then set DB_DATABASE, DB_USERNAME, DB_PASSWORD
composer setup                  # composer install, key:generate, migrate, npm install, npm run build
php artisan db:seed             # loads categories and subcategories
composer dev                    # server + queue + Vite on http://localhost:8000
```

Recommended `.env` value for the project: `APP_LOCALE=es` (the timezone already defaults to `Europe/Madrid`). Keep `APP_KEY` safe and unchanged in production: it is the key that encrypts the contact channels, and losing it makes them unreadable.

To load example submissions for development:

```bash
php artisan db:seed --class=SugerenciasPruebaSeeder
```

To give a user access to the review panel, register them and set the flag:

```bash
php artisan tinker
>>> App\Models\User::where('email', 'reviewer@example.com')->update(['es_revisor' => true]);
```

The panel is then available at `/sugerencias` after logging in.

### AI pipeline

Requirements: Python 3.10+, an NVIDIA GPU with CUDA (the model is loaded in 4-bit with bitsandbytes), a Hugging Face token, and network access to the same database as the web application.

```bash
cd ceuta-me-preocupa/ai
python -m venv .venv
source .venv/bin/activate        # Windows: .venv\Scripts\activate
pip install -r requirements.txt

cp db_config.example.py db_config.py   # db_config.py is git-ignored
export HF_TOKEN=...                    # Hugging Face access token
export BUZON_DB_HOST=... BUZON_DB_USER=... BUZON_DB_PASSWORD=... BUZON_DB_NAME=...
```

---

## Running the AI pipeline

```bash
cd ai
python qwen_chat.py
```

Each run takes the most recent unprocessed row from `datos_originales`, de-identifies the text and the expected outcome, checks it for emergency keywords, asks the model for a category, subcategories and urgency, writes the result to `propuestas_ia` and marks the source row as processed. The proposal then appears in the review panel for a human to confirm or correct.

Column and table names are configured at the top of `qwen_chat.py` and in `db_config.py`.

---

## Tests

```bash
cd web/backend
composer test
```

The suite includes unit tests for the personal-data detector (`tests/Unit/DetectorDatosPersonalesTest.php`) and feature tests for the review workflow and authentication.

---

## Current limitations

- The AI pipeline processes one submission per run and is started manually; it is not yet scheduled.
- Emergency detection is keyword-based and only raises priority: it does not replace the warning on the form that the mailbox is not an emergency channel.
- The model is used through prompting; the fine-tuned version is in progress.
- The public metrics show counts per category only; the monthly transparency reports described in the protocol are not generated by the software yet.

---

## License

The source code is released under the [MIT License](LICENSE.md).
