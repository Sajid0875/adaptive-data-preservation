# Project Submission

## Group Information

**Group Number:** [Add assigned group number from the project proposal spreadsheet]

| Name | Roll Number |
| --- | --- |
| Sajid Islam | 24P-0745 |
| Azwar Khan | 24P-0546 |
| Abbas Bajwa | 24P-0545 |

## Project Title

**Universe State Compression & Entropy-Aware Data Preservation System**

This application is a DBMS project that tracks universe/state snapshots, records state changes, calculates entropy scores, and automatically recommends whether each snapshot should be discarded, compressed, preserved, or archived. It also includes integrity checks, audit logs, snapshot comparisons, and export features through a PHP web interface backed by PostgreSQL.

## GitHub Repository

https://github.com/Sajid0875/adaptive-data-preservation

## Technologies Used

- PHP
- PostgreSQL
- SQL, PL/pgSQL functions, triggers, and views
- HTML
- CSS
- JavaScript
- Chart.js
- Apache/XAMPP or PHP built-in development server

## Installation and Run Steps

### 1. Clone the Repository

```bash
git clone https://github.com/Sajid0875/adaptive-data-preservation.git
cd adaptive-data-preservation
```

### 2. Install Requirements

Make sure the following are installed:

- PHP 8 or later
- PostgreSQL
- `psql` command-line tool
- PHP PostgreSQL/PDO extension enabled

### 3. Create the Database

Create a PostgreSQL database named `universe_preservation`:

```bash
createdb universe_preservation
```

If you want to use another database name, update `config/db.php` or set the `DB_NAME` environment variable.

### 4. Load the Database Files

Run the SQL files in this order:

```bash
psql -d universe_preservation -f database/01_schema.sql
psql -d universe_preservation -f database/02_functions_triggers_views.sql
psql -d universe_preservation -f database/03_sample_data.sql
```

Optional query pack:

```bash
psql -d universe_preservation -f database/04_queries.sql
```

### 5. Configure Database Connection

The application reads database settings from `config/db.php`.

Default settings:

```text
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=universe_preservation
DB_USER=current system user, or postgres
DB_PASS=
```

You can either edit `config/db.php` directly or set environment variables before starting the app.

Example:

```bash
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_NAME=universe_preservation
export DB_USER=postgres
export DB_PASS=your_password
```

### 6. Run the Application

Using the PHP built-in server:

```bash
php -S 127.0.0.1:8000 -t .
```

Open the application in a browser:

```text
http://127.0.0.1:8000/
```

### Docker Virtual Environment

If you want an isolated environment for this project, install Docker Desktop and run:

```bash
docker compose up --build
```

This starts:

- a PHP 8.3 app container on `http://127.0.0.1:8000/`
- a PostgreSQL 16 database container
- automatic database loading from:
  - `database/01_schema.sql`
  - `database/02_functions_triggers_views.sql`
  - `database/03_sample_data.sql`

Docker database settings:

```text
DB_HOST=db
DB_PORT=5432
DB_NAME=universe_preservation
DB_USER=postgres
DB_PASS=postgres
```

To stop the environment:

```bash
docker compose down
```

To reset the database and reload the SQL files:

```bash
docker compose down -v
docker compose up --build
```

For XAMPP:

1. Copy the project folder into the XAMPP `htdocs` directory.
2. Start Apache from the XAMPP Control Panel.
3. Make sure PostgreSQL is running and the database files have been loaded.
4. Open the project URL in the browser, for example:

```text
http://localhost/adaptive-data-preservation/
```

### 7. Login Credentials

```text
Username: admin
Password: admin123
```
