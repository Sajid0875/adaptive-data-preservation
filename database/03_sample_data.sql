-- 03_sample_data.sql
-- Sample data: at least 5 universes + snapshots + changes

BEGIN;

-- Universes (>= 5)
INSERT INTO universes(name, description) VALUES
  ('Andromeda Ops', 'Temporal lifecycle management for distributed services'),
  ('Orion Ledger', 'Versioned financial-like records with preservation rules'),
  ('Helios Telemetry', 'High-frequency telemetry snapshots and change tracking'),
  ('Nexus Registry', 'Master data registry with periodic compaction'),
  ('Aurora Archive', 'Archive-heavy workloads for compliance and retention');

-- Snapshots
-- Keep sizes realistic (MB). Created_at auto.
INSERT INTO state_snapshots(universe_id, version_number, snapshot_size_mb) VALUES
  (1, 1, 50.000),
  (1, 2, 50.000),
  (2, 1, 10.000),
  (2, 2, 10.000),
  (3, 1, 5.000),
  (3, 2, 5.000),
  (4, 1, 100.000),
  (4, 2, 100.000),
  (5, 1, 8.000),
  (5, 2, 8.000);

-- Changes (weights auto via trigger if supplied <=0)
-- Snapshot 1 (Universe 1, v1): no changes => entropy=0 => DISCARD

-- Snapshot 2 (Universe 1, v2): small change => COMPRESS range
INSERT INTO state_changes(snapshot_id, change_type, change_weight) VALUES
  (2, 'UPDATE', 0); -- weight set to 2

-- Snapshot 3 (Universe 2, v1): moderate changes => PRESERVE
INSERT INTO state_changes(snapshot_id, change_type, change_weight) VALUES
  (3, 'CREATE', 0),
  (3, 'UPDATE', 0),
  (3, 'DELETE', 0);

-- Snapshot 4 (Universe 2, v2): high changes => ARCHIVE
INSERT INTO state_changes(snapshot_id, change_type, change_weight) VALUES
  (4, 'CORRUPTION', 0),
  (4, 'DELETE', 0),
  (4, 'UPDATE', 0),
  (4, 'UPDATE', 0);

-- Snapshot 5 (Universe 3, v1): COMPRESS
INSERT INTO state_changes(snapshot_id, change_type, change_weight) VALUES
  (5, 'CREATE', 0);

-- Snapshot 6 (Universe 3, v2): ARCHIVE
INSERT INTO state_changes(snapshot_id, change_type, change_weight) VALUES
  (6, 'CORRUPTION', 0),
  (6, 'CORRUPTION', 0);

-- Snapshot 7 (Universe 4, v1): DISCARD (no changes)

-- Snapshot 8 (Universe 4, v2): PRESERVE
INSERT INTO state_changes(snapshot_id, change_type, change_weight) VALUES
  (8, 'UPDATE', 0),
  (8, 'UPDATE', 0),
  (8, 'UPDATE', 0),
  (8, 'CREATE', 0);

-- Snapshot 9 (Universe 5, v1): COMPRESS
INSERT INTO state_changes(snapshot_id, change_type, change_weight) VALUES
  (9, 'UPDATE', 0);

-- Snapshot 10 (Universe 5, v2): ARCHIVE
INSERT INTO state_changes(snapshot_id, change_type, change_weight) VALUES
  (10, 'DELETE', 0),
  (10, 'DELETE', 0),
  (10, 'CORRUPTION', 0);

COMMIT;
