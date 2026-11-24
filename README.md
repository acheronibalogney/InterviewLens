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

The deterministic 100-point rubric awards up to 25 points for answer length, 35 for STAR components, 20 for filler-word control, and 20 for question key-term coverage. A given answer and question produce the same scores. Each criterion is saved as its own feedback row with a tip.

## Research alignment

- Common questions and typed responses are presented in the practice workspace.
- `App\Services\AnswerEvaluator` awards the paper's 25/35/20/20 points for length, STAR indicators, filler words, and key terms.
- The `users`, `questions`, `attempts`, and `feedback` tables follow the research model. Job seekers can practice and review account-scoped attempts; administrators can create, edit, and deactivate questions. Existing attempts are kept through the schema upgrade.
- Learning resources are seeded with skill keys and recommended from answer weaknesses.
- Laravel routes and Blade views provide the browser tier, application services provide the application tier, and Eloquent models and migrations provide the data tier.

Create an administrator by promoting a trusted account through an authorized database operation; public registration always creates a job seeker. For example, from a secure local environment, run `php artisan tinker` and set the intended account's `role` to `administrator`. The paper's weighted-mean usability questionnaire is a separate study activity; InterviewLens does not invent respondents or survey results.
