# InterviewLens

InterviewLens is a Laravel web application for job seekers to practice typed interview answers. It provides a curated question bank, explainable rule-based feedback, account-scoped practice history, and learning resources matched to detected weaknesses.

The evaluator reviews the structure and wording of text only. It does not verify an answer's truth, assess spoken delivery, or predict hiring outcomes. Its scoring thresholds and phrase checks are heuristics and should be tuned through the study's respondent evaluation.

## Requirements

- PHP 8.5.12 or newer with `mbstring`, `pdo_mysql`, and `pdo_sqlite`
- Composer 2
- MySQL 8.0+ for a shared or deployed installation
- Node.js and npm only if rebuilding frontend assets

## Set up for local testing

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Set `DB_CONNECTION=sqlite`, `DB_DATABASE=database/database.sqlite`, and file or array session/cache stores in `.env` for local practice without a MySQL server. Create the empty SQLite file if it does not exist, then run:

```powershell
New-Item -ItemType File -Force database\database.sqlite
php artisan migrate --seed
php artisan serve
```

On Windows installations where SQLite extensions are not enabled in `php.ini`, run Artisan with the bundled extensions:

```powershell
php -d extension=sqlite3 -d extension=pdo_sqlite artisan migrate --seed
php -d extension=sqlite3 -d extension=pdo_sqlite artisan serve
```

Open the address printed by Artisan and create a practice account. For MySQL, keep `DB_CONNECTION=mysql` in `.env.example` and configure the host, database, username, and password in the local `.env` before running migrations. Never commit `.env` or production credentials.

## Automated checks

Run the feature suite with SQLite support enabled:

```powershell
php -d extension=sqlite3 -d extension=pdo_sqlite vendor/bin/phpunit
```

GitHub Actions runs the same suite for pushes and pull requests using PHP 8.5 and an in-memory SQLite database.

## Scoring rubric

The 100-point score combines answer length (up to 25 points), structure (up to 35 points for behavioral prompts), filler-word frequency (up to 20 points), and question-related term coverage (up to 20 points). Non-behavioral prompts receive a smaller structure score for concrete examples. Feedback describes the detected patterns and suggests a next step.

## Research alignment

- Common questions and typed responses are presented in the practice workspace.
- `App\Services\AnswerEvaluator` evaluates length, STAR indicators, filler words, and question-related terms.
- The `evaluations` table stores feedback and scores for account-scoped history.
- Learning resources are seeded with skill keys and recommended from answer weaknesses.
- Laravel routes and Blade views provide the browser tier, application services provide the application tier, and Eloquent models and migrations provide the data tier.

The paper's SUS-inspired and ISO 9126 usability evaluation still needs to be conducted separately with job seekers or fresh graduates. InterviewLens does not claim to collect or analyze those respondent survey results.
