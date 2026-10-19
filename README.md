# Ceuta Me Preocupa (`ceuta-me-preocupa`)

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Python Version](https://img.shields.io/badge/python-3.10%2B-blue)](https://www.python.org/)
[![GDPR Compliant](https://img.shields.io/badge/GDPR-Compliant-green)](https://www.aepd.es)

**Ceuta Me Preocupa** ("Ceuta Concerns Me") is a civic technology initiative and automated data processing pipeline designed to handle citizen concerns, non-urgent incidents, and proposals for improving public administration in the Autonomous City of Ceuta. 

This repository contains the codebase, classification scripts, data pipeline tools, and protocols for collecting, anonymizing, categorizing, and routing civic feedback while maintaining strict privacy standards (GDPR / AEPD guidelines).

---

## Table of Contents
- [Overview & Objectives](#-overview--objectives)
- [System Boundaries & Explicit Limitations](#-system-boundaries--explicit-limitations)
- [Data Architecture & Privacy Model](#-data-architecture--privacy-model)
- [Category & Agency Routing Matrix](#-category--agency-routing-matrix)
- [Data Pipeline & Processing Workflow](#-data-pipeline--processing-workflow)
- [Data Fields & Schema](#-data-fields--schema)
- [Repository Structure](#-repository-structure)
- [Getting Started](#-getting-started)
- [Governance & Safeguards](#-governance--safeguards)
- [References](#-references)

---

## Overview & Objectives

During emergency and transitional situations in Ceuta, effective citizen communication channels are crucial. The main goals of this system are:
1. **Identify Patterns:** Aggregating citizen inputs to identify widespread urban, social, and infrastructure issues.
2. **Actionable Routing:** Suggesting the appropriate public entity (regional or state level) capable of resolving each reported issue.
3. **Verifiable Accountability:** Providing transparent, aggregated reports on responses and actions taken without compromising personal data.

---

## System Boundaries & Explicit Limitations

To manage expectations and avoid procedural misunderstandings, the platform explicitly enforces three boundaries at the user interface level:

1. **Not an Administrative Complaint:** Submitting a proposal or concern does not constitute a formal legal claim or administrative recourse.
2. **No Formal Procedure Initiated:** Submissions do not initiate an official administrative file (*expediente*) nor guarantee the adoption of the proposed measure.
3. **Not an Emergency Channel:** Urgent matters, vital risks, or crimes must be directed immediately to official emergency services (112, Police, INGESA).

---

## Data Architecture & Privacy Model

In compliance with the **Spanish Data Protection Agency (AEPD)** recommendations regarding anonymization vs. pseudonymization, the application strictly separates data into **three distinct tiers**:

| Level | Storage / Tier | Contents | Access Control | Purpose |
| :--- | :--- | :--- | :--- | :--- |
| **Level A** | **Restricted Input** | Original form submissions and optional separate contact details. | Minimal authorized management team only. | Submission receipt, emergency verification, optional direct reply. |
| **Level B** | **Pseudonymized Register** | Random ID, main category, subcategory, proposed entity, status, timestamp, de-identified text, risk tags. | Management team and verified human reviewers. | Processing, classification, routing, and operational tracking. |
| **Level C** | **Aggregated Analytical Output**| Trend metrics, issue counts, summarized & de-identified extracts with small-cell suppression. | Receiving agencies, monitoring bodies, public dashboards. | Decision-making, policy planning, and public transparency. |

> ⚠️ **Privacy Note:** Automatic removal of PII (names, IDs, emails) alone does not guarantee full anonymization. Level B data remains pseudonymized under GDPR rules. A combination of automated text scrubbing, human review, and small-cell suppression is used to eliminate indirect re-identification risks.

---

## Category & Agency Routing Matrix

Submissions are assigned to a primary competent entity within either the **Autonomous City of Ceuta** or the **General State Administration (AGE)**:

| Primary Category | Minimum Subcategories | Proposed Competent Body | Alternative / Derivation Entity |
| :--- | :--- | :--- | :--- |
| **Urban Planning** | Planning, licenses, housing, public works, accessibility, heritage. | Autonomous City: DG for Urban Planning & Heritage | Environment / State General Admin (if state property) |
| **Mobility** | Public transport, traffic, parking, pedestrian routes, loading zones. | Autonomous City: DG for Transport & Urban Mobility | Local Police / Government Delegation (*Delegación del Gobierno*) |
| **Non-University Education**| Schools, enrollment, teaching staff, vocational training, school climate. | Provincial Directorate of Education (Ministry of Education) | Autonomous City: DG for Education / School administration |
| **University** | Teaching, research, scholarships, facilities, campus life. | Competent University Authority | Ministry of Universities / Autonomous City |
| **Economy** | Employment, commerce, entrepreneurship, tourism, economic grants. | Autonomous City: DG for Commerce, Tourism & Employment / Finance | General State Administration (state-level benefits) |
| **Healthcare** | Public health, healthcare access, prevention, facility access. | INGESA Territorial Directorate (Healthcare services) | Autonomous City: DG for Public Health / Emergency channels |
| **Security** | Citizen safety, facility security, local police, crime prevention. | Autonomous City: Local Police / Governance Area | Government Delegation / National Police & Civil Guard |
| **Social Services** | Social care, dependency, inclusion, vulnerability, equality, community support. | Autonomous City: DG for Social Services | DG for Equality / Competent State Services |
| **Environment** | Waste management, street cleaning, noise, green spaces, pollution, sustainability. | Autonomous City: DG for Environment | Urban Services / Port Authority / State Environmental Bodies |

*If jurisdictional competence is uncertain, submissions are tagged as **"Competence Pending Confirmation"** rather than rejected.*

---

## Data Pipeline & Processing Workflow

The processing engine follows a strict multi-step workflow executed via Python scripts and human oversight:

```
[ Citizen Submission ]
         │
         ▼
 ┌──────────────────────┐
 │  Emergency Filter    │ ──► (Triggers immediate redirection to emergency services if risk detected)
 └──────────┬───────────┘
            │
            ▼
 ┌──────────────────────┐
 │ Minimization & PII   │ ──► Replaces DNI, phone, email, names with `[PERSONAL DATA REMOVED]`
 │ Regex Scrubbing      │
 └──────────┬───────────┘
            │
            ▼
 ┌──────────────────────┐
 │ Human Privacy Review │ ──► Manual check on flagged submissions + random sample of unflagged entries
 └──────────┬───────────┘
            │
            ▼
 ┌──────────────────────┐
 │ Dual Classification  │ ──► Python script proposes category/agency ──► Human reviewer confirms
 └──────────┬───────────┘
            │
            ▼
 ┌──────────────────────┐
 │ Deduplication &      │ ──► Groups coordinated campaigns/duplicates while retaining total submission count
 │ Aggregation          │
 └──────────┬───────────┘
            │
            ▼
 ┌──────────────────────┐
 │ Level B / Level C    │ ──► Safe data distribution for agency action and public transparency
 │ Generation           │
 └──────────────────────┘
```

---

## Data Fields & Schema (Level B Register)

Every processed entry in the Level B working register requires the following mandatory fields:

- `random_id` (string): Unique pseudonymous identifier.
- `reception_date` (ISO timestamp): Date and time of submission.
- `main_category` (string): Primary category selected/confirmed.
- `subcategory` (string): Specific subtopic.
- `proposed_agency` (string): Initial entity proposal generated by the engine.
- `confirmed_agency` (string): Final assigned agency after human verification.
- `urgency_level` (enum): `LOW`, `MEDIUM`, `HIGH_PRIORITY`.
- `status` (enum): `PENDING_REVIEW`, `DERIVED`, `ACTION_TAKEN`, `PENDING_COMPETENCE`, `CLOSED`.
- `deidentified_text` (string): Cleaned submission narrative (max 1,000 characters).
- `possible_duplicate` (boolean): Flag for campaign/duplicate detection.
- `requires_privacy_review` (boolean): Flag for potential indirect re-identification risk.
- `action_taken` (string): Summary of public resolution or response.
- `last_updated` (ISO timestamp): Last record modification date.

---

## Repository Structure

```text
ceuta-me-preocupa/
├── data/
│   ├── taxonomy.json           # Categories, subcategories, and agency mapping
│   └── templates/              # Public transparency report templates
├── src/
│   ├── anonymization/          # PII detection and regex scrubbing modules
│   ├── classification/         # Rule-based and automated categorization engine
│   ├── deduplication/          # Clustering and duplicate count tracking algorithms
│   └── export/                 # Level B and Level C dataset generator
├── tests/                      # Unit tests for PII scrubbing and classification
├── docs/                       # Governance, protocol details, and security guides
├── requirements.txt            # Python dependencies
└── README.md                   # Project documentation
```

---

## Getting Started

### Prerequisites
- Python 3.10 or higher
- `pip` package manager

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/David0450/ceuta-me-preocupa.git
   cd ceuta-me-preocupa
   ```

2. **Set up a virtual environment:**
   ```bash
   python -m venv venv
   source venv/bin/activate  # On Windows: venv\Scripts\activate
   ```

3. **Install dependencies:**
   ```bash
   pip install -r requirements.txt
   ```

### Running the Data Scrubbing Pipeline

To run the anonymization and automated categorization pipeline on incoming data samples:

```bash
python -m src.classification.pipeline --input data/samples/raw_input.json --output data/processed/level_b.json
```

---

## Governance & Safeguards

Before deployment, the system adheres to strict operational rules:
- **Human-in-the-Loop:** Automated scripts assist consistency, but final agency derivation and public release *must* be approved by human reviewers.
- **DPO Validation:** Privacy protocols and data retention schedules are validated by the Data Protection Officer (DPO).
- **Monthly Transparency Releases:** Publication of aggregated statistics (Level C) detailing submission volume, main themes, receiving entities, and resolved actions.

---

## References

- **Agencia Española de Protección de Datos (AEPD):** [Anonymization and Pseudonymization Guidelines (2021)](https://www.aepd.es/prensa-y-comunicacion/blog/anonimizacion-y-seudonimizacion)
- **Ciudad Autónoma de Ceuta:** [Autonomy Statute & Competences](https://www.ceuta.es/ceuta/la-institucion/normativa/45-paginas/paginas/estatuto-de-autonomia/11-titulo-ii-de-las-competencias-de-la-ciudad-de-ceuta)
- **INGESA:** [Territorial Directorate Ceuta](https://ingesa.sanidad.gob.es/ceuta/Organizaci-n/Direcci-n-Territorial.html)
- **Jones et al. (2021):** *Interventions promoting employee "speaking-up" within healthcare workplaces.* Health Policy, 125(3), 281–290.

---

## License

This project is licensed under the MIT License — see the [LICENSE](LICENSE.md) file for details.
