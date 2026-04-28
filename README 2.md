# Universe State Compression & Entropy-Aware Data Preservation System

Semester DBMS Project (PostgreSQL + PHP + Bootstrap)

## 1) Database (PostgreSQL)

### Create database

Create a database named `universe_preservation` (or change `config/db.php`).

```zsh
createdb universe_preservation
```

### Run SQL (in order)

```zsh
psql -d universe_preservation -f database/01_schema.sql
psql -d universe_preservation -f database/02_functions_triggers_views.sql
psql -d universe_preservation -f database/03_sample_data.sql
```

Optional query pack:

```zsh
psql -d universe_preservation -f database/04_queries.sql
```

## 2) PHP Frontend (No Framework)

### Option A: XAMPP (recommended for classes)

1. Install XAMPP.
2. Copy this project folder into XAMPP `htdocs/`.
3. Start **Apache** in the XAMPP Control Panel.
4. Open: `http://localhost/DB_PROJECT/`

### Option B: PHP built-in server

```zsh
php -S localhost:8000 -t .
```
Open: `http://localhost:8000/`

### Configure DB connection

Edit `config/db.php` (or set env vars):

- `DB_HOST` (default `127.0.0.1`)
- `DB_PORT` (default `5432`)
- `DB_NAME` (default `universe_preservation`)
- `DB_USER` (default `postgres`)
- `DB_PASS` (default empty)

## 3) Login

Hardcoded admin (as required):

- Username: `admin`
- Password: `admin123`

## 4) Normalization (up to 3NF)

- **1NF**: All tables use atomic columns (no repeating groups). Example: each `state_changes` row represents one change event.
- **2NF**: Non-key attributes depend on the whole key. Example: `state_snapshots` uses a surrogate key `snapshot_id`, and attributes (size, created_at) depend on that snapshot.
- **3NF**: No transitive dependencies: decisions and metrics are separated:
	- `entropy_metrics` depends on `snapshot_id` only.
	- `preservation_decisions` references `entropy_id` and `snapshot_id` instead of duplicating entropy logic.

## 5) Notes on Automation

- Insert into `state_changes` auto-assigns weights and triggers entropy recompute.
- Preservation decisions are assigned from `preservation_rules`.
- `compressed_states` and `archives` rows are materialized based on decision.
- `integrity_checks` becomes `CORRUPTED` if any change is `CORRUPTION`.
- All core tables are audited into `audit_logs` via triggers.
