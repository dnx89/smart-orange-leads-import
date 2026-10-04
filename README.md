# Leads import

A page that imports a CSV file of leads into MySQL. Every line of the file is stored — the
ones with problems too — together with a status and the list of problems found in it, and the
reading is cut into steps, so however long the file is, no request runs past PHP's standard
`max_execution_time = 30`. A file is accepted up to 128 MB.

Laravel 12, PHP 8.3, MySQL 8.0, nginx, all in Docker.

## Running it

Requirements: Docker with Compose v2, and `make` (optional — every target is one
`docker compose` command, see the `Makefile`).

```bash
git clone <repository-url> leads-import
cd leads-import
make up
```

The first start builds the image, installs the Composer packages, creates `.env` from
`.env.example`, generates the application key, runs the migrations and creates the demo user.

Open <http://localhost:8090> and sign in:

| Email | Password |
|---|---|
| `admin@example.com` | `password` |

Ports used on the host: `8090` (web), `3310` (MySQL).

## Importing a file

**Новий імпорт** (new import) → choose a `.csv` file → **Завантажити** (upload).

The file must be UTF-8, comma-separated, and start with this header:

```
external_id,created_at,first_name,last_name,phone,email,city,source,utm_campaign,product,budget_uah,status,manager,comment,next_contact_at
```

A file with any other header is rejected before a single row is stored. XLSX is not accepted
(see [Not included](#not-included)).

**Історія завантажень** (upload history) lists every import: who, when, which file, and how many rows ended up
`OK`, `Попередження` (warning) and `Помилка` (error). An import's page shows how many rows
carry each problem, the rows themselves with a filter by status and by problem, and a link
to download the result as CSV.

## How it stays within 30 seconds

1. **The file goes up in one request.** Only reading it is split, because only reading is PHP
   script time: nginx receives the whole request body before PHP starts, so the transfer takes
   as long as the connection needs without making the script long. The upload limits in
   `php.ini` and in nginx are raised past the largest accepted file for this.
2. **Rows are read in steps.** The page calls the processing endpoint again and again. Each
   call reads rows for at most `min(max_execution_time − 5, 5)` seconds, then reports the byte
   position it reached (`ftell`) and returns. The next call continues from that position
   (`fseek`), so a longer file only means more steps, never a longer request.
3. **The position lives in the session**, under a key of the import's id, and is dropped when the
   import ends. It is state of one run, not part of the upload's record, so it is not a column.
4. **One step is one transaction.** The rows a step read and the counters it increased are
   committed together, so the counters can never claim more rows than are stored.
5. **Rows go to the database in batches** of 1 000 per `INSERT`.

Measured on the provided file (100 000 rows, 25.9 MB, `APP_DEBUG=true`, on the development
machine): two steps, 8.1 s in total.

## What is checked in a row

Every problem found is recorded as a flag on the row; a row can carry several. Only a
**critical** flag makes the row an error, the rest make it a warning.

| Column | Flag raised when | Critical |
|---|---|---|
| (whole row) | not exactly 15 columns | yes |
| `external_id` | empty, or not `LD-` followed by 6 digits | |
| `created_at` | empty, or not `YYYY-MM-DD HH:MM:SS`, or a date that does not exist | |
| `first_name`, `last_name` | empty, or longer than 100 characters | |
| `phone` | empty, or not `380` followed by 9 digits | yes |
| `email` | empty, fails the same RFC check as Laravel's `email` rule, or longer than 255 | |
| `city`, `source`, `product` | empty, or longer than 100 characters | |
| `utm_campaign`, `manager`, `comment` | longer than 255 characters | |
| `budget_uah` | not a whole number after removing spaces (`23 700` and `23 700` with a no-break space are accepted) | |
| `status` | not one of `new`, `contacted`, `in_progress`, `qualified`, `proposal_sent`, `won`, `lost`, `spam` | |
| `next_contact_at` | not `YYYY-MM-DD HH:MM:SS` (may be empty) | |

`external_id` is not checked for duplicates: the file is stored as it is.

Result on the provided file: **97 691** OK, **1 496** warnings, **813** errors (all of them
invalid phones). Flags: phone 813, email 995, first name 516.

## Database

| Table | What it holds |
|---|---|
| `imports` | one row per upload: who, when, file name and size, status, row counters, the failure reason |
| `import_rows` | every line of every file: its line number, status, flags (a bitmask) and the 15 values exactly as they stood in the file |
| `users` | accounts |

The structure is in the migrations (`database/migrations/`) and, as plain SQL, in
[`database/schema.sql`](database/schema.sql). After changing a migration, regenerate the
SQL with `make schema`.

## Settings that matter

| Where | Setting | Value |
|---|---|---|
| `docker/php/php.ini` | `max_execution_time` | 30 |
| | `upload_max_filesize` / `post_max_size` | 160M / 160M |
| | `memory_limit` | 256M |
| `docker/nginx/default.conf` | `client_max_body_size` | 160m |
| `app/Http/Requests/StoreImportRequest.php` | `MAX_BYTES` — largest accepted file | 128 MB |
| `app/Services/Import/ImportStep.php` | `BATCH_SIZE` — rows per `INSERT` | 1 000 |
| | seconds per step — constructor argument | `max_execution_time` − 5, at most 5 |

There is no `config/import.php`: none of these is changed per environment, so each sits in the
class that reads it. The file's expected columns are the keys of `ImportRow::SOURCE_COLUMNS`.

`memory_limit` is above PHP's 128M default because with `APP_DEBUG=true` Laravel keeps up to 101
queries of a request for its error page, bindings included, and one step sends dozens of
1 000-row inserts.

## Tests

```bash
make test
```

PHPUnit, against a separate database `leads_import_test` that the container creates on its
first start. Each test runs in a transaction that is rolled back afterwards.

## Useful commands

| Command | What it does |
|---|---|
| `make up` / `make down` | start and wait until healthy / stop, keeping the data |
| `make build` | rebuild the image and start |
| `make fresh` | drop the database volume and start from scratch — deletes all data |
| `make test` | run the tests |
| `make schema` | regenerate `database/schema.sql` |
| `make shell` | a shell in the PHP container |
| `make xdebug` | start with Xdebug enabled (port 9003, IDE key `PHPSTORM`) |
| `make artisan COMMAND="…"` | any Artisan command |

## Not included

- **XLSX.** Converting an XLSX file is one sequential pass over its compressed sheet, which
  cannot be resumed in the middle. Measured on the provided file: 12.3 s for 100 000 rows; at that
  rate (an estimate, not measured) a file of about 250 000 rows would no longer convert within
  one 30-second request. Save the
  sheet as CSV instead (Google Sheets: *File → Download → Comma-separated values*).
