-- 01_schema.sql
-- Universe State Compression & Entropy-Aware Data Preservation System
-- PostgreSQL schema (tables + constraints)

BEGIN;

-- Idempotent reset for class/demo use. Comment out in production.
DROP TABLE IF EXISTS audit_logs CASCADE;
DROP TABLE IF EXISTS integrity_checks CASCADE;
DROP TABLE IF EXISTS archives CASCADE;
DROP TABLE IF EXISTS compressed_states CASCADE;
DROP TABLE IF EXISTS preservation_decisions CASCADE;
DROP TABLE IF EXISTS preservation_rules CASCADE;
DROP TABLE IF EXISTS entropy_metrics CASCADE;
DROP TABLE IF EXISTS state_changes CASCADE;
DROP TABLE IF EXISTS state_snapshots CASCADE;
DROP TABLE IF EXISTS universes CASCADE;

-- 1) universes
CREATE TABLE universes (
  universe_id BIGSERIAL PRIMARY KEY,
  name TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  CONSTRAINT universes_name_nonempty CHECK (BTRIM(name) <> '')
);

-- 2) state_snapshots
CREATE TABLE state_snapshots (
  snapshot_id BIGSERIAL PRIMARY KEY,
  universe_id BIGINT NOT NULL REFERENCES universes(universe_id) ON DELETE CASCADE,
  version_number INTEGER NOT NULL,
  snapshot_size_mb NUMERIC(12,3) NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  CONSTRAINT state_snapshots_version_positive CHECK (version_number > 0),
  CONSTRAINT state_snapshots_size_positive CHECK (snapshot_size_mb > 0)
);
CREATE UNIQUE INDEX ux_state_snapshots_universe_version
  ON state_snapshots(universe_id, version_number);
CREATE INDEX ix_state_snapshots_universe_id ON state_snapshots(universe_id);

-- 3) state_changes
CREATE TABLE state_changes (
  change_id BIGSERIAL PRIMARY KEY,
  snapshot_id BIGINT NOT NULL REFERENCES state_snapshots(snapshot_id) ON DELETE CASCADE,
  change_type TEXT NOT NULL,
  change_weight INTEGER NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  CONSTRAINT state_changes_change_type_chk CHECK (change_type IN ('CREATE','UPDATE','DELETE','CORRUPTION')),
  CONSTRAINT state_changes_change_weight_positive CHECK (change_weight > 0)
);
CREATE INDEX ix_state_changes_snapshot_id ON state_changes(snapshot_id);
CREATE INDEX ix_state_changes_type ON state_changes(change_type);

-- 4) entropy_metrics (one per snapshot)
CREATE TABLE entropy_metrics (
  entropy_id BIGSERIAL PRIMARY KEY,
  snapshot_id BIGINT NOT NULL UNIQUE REFERENCES state_snapshots(snapshot_id) ON DELETE CASCADE,
  entropy_score NUMERIC(18,6) NOT NULL,
  calculated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  CONSTRAINT entropy_metrics_entropy_nonneg CHECK (entropy_score >= 0)
);

-- 5) preservation_rules
CREATE TABLE preservation_rules (
  rule_id BIGSERIAL PRIMARY KEY,
  min_entropy NUMERIC(18,6) NOT NULL,
  max_entropy NUMERIC(18,6) NULL,
  decision_type TEXT NOT NULL,
  CONSTRAINT preservation_rules_range_chk CHECK (max_entropy IS NULL OR max_entropy >= min_entropy),
  CONSTRAINT preservation_rules_min_nonneg CHECK (min_entropy >= 0),
  CONSTRAINT preservation_rules_decision_type_chk CHECK (decision_type IN ('DISCARD','COMPRESS','PRESERVE','ARCHIVE'))
);
CREATE INDEX ix_preservation_rules_min ON preservation_rules(min_entropy);

-- 6) preservation_decisions
CREATE TABLE preservation_decisions (
  decision_id BIGSERIAL PRIMARY KEY,
  snapshot_id BIGINT NOT NULL UNIQUE REFERENCES state_snapshots(snapshot_id) ON DELETE CASCADE,
  entropy_id BIGINT NOT NULL UNIQUE REFERENCES entropy_metrics(entropy_id) ON DELETE CASCADE,
  decision_type TEXT NOT NULL,
  decided_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  is_manual BOOLEAN DEFAULT FALSE,
  manual_reason TEXT,
  overridden_at TIMESTAMPTZ NULL,
  reason TEXT,
  CONSTRAINT preservation_decisions_decision_type_chk CHECK (decision_type IN ('DISCARD','COMPRESS','PRESERVE','ARCHIVE'))
);

-- 7) compressed_states
CREATE TABLE compressed_states (
  compressed_id BIGSERIAL PRIMARY KEY,
  snapshot_id BIGINT NOT NULL UNIQUE REFERENCES state_snapshots(snapshot_id) ON DELETE CASCADE,
  compression_ratio NUMERIC(10,4) NOT NULL,
  compressed_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  CONSTRAINT compressed_states_ratio_chk CHECK (compression_ratio > 0 AND compression_ratio <= 1)
);

-- 8) archives
CREATE TABLE archives (
  archive_id BIGSERIAL PRIMARY KEY,
  snapshot_id BIGINT NOT NULL UNIQUE REFERENCES state_snapshots(snapshot_id) ON DELETE CASCADE,
  archive_location TEXT NOT NULL,
  archived_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  CONSTRAINT archives_location_nonempty CHECK (BTRIM(archive_location) <> '')
);

-- 9) integrity_checks
CREATE TABLE integrity_checks (
  check_id BIGSERIAL PRIMARY KEY,
  snapshot_id BIGINT NOT NULL UNIQUE REFERENCES state_snapshots(snapshot_id) ON DELETE CASCADE,
  status TEXT NOT NULL,
  checked_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  CONSTRAINT integrity_checks_status_chk CHECK (status IN ('VALID','CORRUPTED'))
);

-- 10) audit_logs
CREATE TABLE audit_logs (
  log_id BIGSERIAL PRIMARY KEY,
  action TEXT NOT NULL,
  entity_name TEXT NOT NULL,
  entity_id BIGINT NULL,
  timestamp TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  CONSTRAINT audit_logs_action_nonempty CHECK (BTRIM(action) <> ''),
  CONSTRAINT audit_logs_entity_nonempty CHECK (BTRIM(entity_name) <> '')
);
CREATE INDEX ix_audit_logs_timestamp ON audit_logs(timestamp);
CREATE INDEX ix_audit_logs_entity ON audit_logs(entity_name, entity_id);

-- Default preservation rules (as specified)
INSERT INTO preservation_rules (min_entropy, max_entropy, decision_type) VALUES
  (0.00, 0.00, 'DISCARD'),
  (0.01, 0.30, 'COMPRESS'),
  (0.31, 0.70, 'PRESERVE'),
  (0.70, NULL, 'ARCHIVE');

COMMIT;
