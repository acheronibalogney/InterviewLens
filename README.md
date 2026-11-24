# InterviewLens

InterviewLens is a Laravel web application for job seekers to practice typed interview answers. It provides a curated question bank, STAR-based AI feedback, account-scoped practice history, and learning resources matched to detected weaknesses.

The evaluator reviews the structure and wording of text only. It does not verify an answer's truth, assess spoken delivery, or predict hiring outcomes. Its scoring thresholds and phrase checks are heuristics and should be tuned through the study's respondent evaluation.

## Requirements

- PHP 8.5.12 or newer with Composer-supported Laravel extensions
- Composer 2
- Node.js and npm to build the frontend assets
- SQLite with `pdo_sqlite` for local testing, or MySQL 8.0+ for a shared installation
- A Gemini API key for AI interview feedback

Run `php -m` to inspect loaded PHP extensions and `composer check-platform-reqs` after installing dependencies. For SQLite, PHP must load both `sqlite3` and `pdo_sqlite`. For MySQL, PHP must load `pdo_mysql`.

## Local setup (Windows PowerShell)

From the project directory, install PHP dependencies, create the environment file, and generate the application key:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Edit `.env` and set the local application URL, SQLite database, and Gemini credentials. Keep the API key private.

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

GEMINI_API_KEY=your-google-ai-studio-api-key
GEMINI_MODEL=gemini-3.8-flash
GEMINI_FALLBACK_MODEL=gemini-3.5-flash-lite
```

Create the SQLite database, install and build the frontend assets, migrate and seed the database, then start the local server:

```powershell
if (-not (Test-Path database\database.sqlite)) { New-Item -ItemType File database\database.sqlite | Out-Null }
npm install
npm run build
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000), create an account, and start a practice question. `php artisan serve` prints the address it is using. The interview form displays a live loading indicator, submits without a manual refresh, and opens the saved results automatically when the review is complete. PHP is allowed up to 120 seconds for the interview request because model responses can take longer than the default 30-second limit.

### SQLite extension setup on Windows

If Artisan reports that it cannot find the SQLite driver, check the active PHP configuration and enable `extension=sqlite3` and `extension=pdo_sqlite` in its `php.ini`. Confirm the same PHP installation is used by the terminal and server:

```powershell
php --ini
php -m | Select-String 'sqlite|pdo'
```

### MySQL setup

Create an empty MySQL database, then set these values in `.env` before migrating:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=interviewlens
DB_USERNAME=your-local-database-user
DB_PASSWORD=your-local-database-password
```

Ensure PHP has `pdo_mysql` enabled, then run `php artisan migrate --seed`.

## Gemini API configuration

Create a Gemini API key in [Google AI Studio](https://aistudio.google.com/app/apikey), then put it in `GEMINI_API_KEY` in your local `.env` file. InterviewLens sends answers from the signed-in user's browser to Laravel, then Laravel calls Gemini with the key kept server-side. Do not put the key in JavaScript, commit it, or share it in screenshots.

`GEMINI_MODEL` is the primary model. If it is temporarily unavailable or rate-limited, `GEMINI_FALLBACK_MODEL` is tried automatically. The default fallback is Gemini 3.5 Flash-Lite. For a Gemini 503, the primary request is retried once after a short wait before the app tries the fallback model. Both model requests have short timeouts so the web server can return a useful error before PHP's request limit. A project-wide quota limit can still affect both models. A 401 or 403 indicates an API key or project-access problem, and may require reviewing the key's project settings or billing access in Google AI Studio.

## Frontend changes

The application serves its checked-in InterviewLens styles and scripts from `public/css/intervielens.css` and `public/js/intervielens.js`. Run `npm install` and `npm run build` when changing Vite-managed assets or preparing a fresh checkout.

## Database and saved practice

Run `php artisan migrate --seed` to create the application tables and add the starter question bank and learning resources. Completed answers and their four STAR feedback rows are saved in a database transaction and belong to the signed-in account. If AI feedback fails, InterviewLens shows an error and does not save a partial attempt.

The interviewer scores Situation, Task, Action, and Result separately from 1 to 25 using strict evidence anchors. Zero is not a valid score. If the rule-based evaluator does not detect clear evidence for a STAR component, its AI score is capped at 5/25. A data migration recalibrates older attempts that had full scores despite missing detected components, and saved STAR feedback is normalized to the 1–25 range.

Public registration always creates a job seeker. To create an administrator, promote a trusted account through an authorized database operation. For a secure local setup, run `php artisan tinker` and set the intended account's `role` to `administrator`.

## Automated checks

Run the feature suite with:

```powershell
php artisan test --compact
```

Or call PHPUnit directly:

```powershell
vendor/bin/phpunit
```

GitHub Actions runs the suite on pushes and pull requests with an in-memory SQLite database.

## Research alignment

- `App\Services\AnswerEvaluator` awards the paper's 25/35/20/20 points for answer length, STAR components, filler-word control, and question-key-term coverage.
- The `users`, `questions`, `attempts`, and `feedback` tables follow the research model. Job seekers can practice and review account-scoped attempts; administrators can create, edit, and deactivate questions.
- Learning resources are seeded with skill keys and recommended from answer weaknesses.
- Laravel routes and Blade views provide the browser tier, application services provide the application tier, and Eloquent models and migrations provide the data tier.
- The paper's weighted-mean usability questionnaire is a separate study activity; InterviewLens does not invent respondents or survey results.
