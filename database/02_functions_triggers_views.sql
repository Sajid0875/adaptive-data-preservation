-- 02_functions_triggers_views.sql
-- Stored functions, triggers, and views

BEGIN;

-- =========
-- Helpers
-- =========

CREATE OR REPLACE FUNCTION fn_change_weight(p_change_type TEXT)
RETURNS INTEGER
LANGUAGE plpgsql
AS $$
BEGIN
  CASE p_change_type
    WHEN 'CREATE' THEN RETURN 1;
    WHEN 'UPDATE' THEN RETURN 2;
    WHEN 'DELETE' THEN RETURN 3;
    WHEN 'CORRUPTION' THEN RETURN 5;
    ELSE
      RAISE EXCEPTION 'Invalid change_type: %', p_change_type;
  END CASE;
END;
$$;

-- =========
-- Required Functions
-- =========

-- calculate_entropy(snapshot_id)
-- Entropy = (SUM(change_weight) * COUNT(state_changes)) / snapshot_size_mb
CREATE OR REPLACE FUNCTION calculate_entropy(p_snapshot_id BIGINT)
RETURNS NUMERIC(18,6)
LANGUAGE plpgsql
AS $$
DECLARE
  v_total_weight NUMERIC(18,6);
  v_change_count INTEGER;
  v_size_mb NUMERIC(12,3);
  v_entropy NUMERIC(18,6);
BEGIN
  SELECT snapshot_size_mb INTO v_size_mb
  FROM state_snapshots
  WHERE snapshot_id = p_snapshot_id;

  IF v_size_mb IS NULL THEN
    RAISE EXCEPTION 'Snapshot % not found', p_snapshot_id;
  END IF;

  SELECT COALESCE(SUM(change_weight), 0), COUNT(*) INTO v_total_weight, v_change_count
  FROM state_changes
  WHERE snapshot_id = p_snapshot_id;

  v_entropy := (v_total_weight * v_change_count) / v_size_mb;
  IF v_entropy < 0 THEN
    v_entropy := 0;
  END IF;

  RETURN ROUND(v_entropy, 6);
END;
$$;

-- decide_preservation(snapshot_id)
-- Uses preservation_rules to decide and upsert preservation_decisions.
CREATE OR REPLACE FUNCTION decide_preservation(p_snapshot_id BIGINT)
RETURNS TEXT
LANGUAGE plpgsql
AS $$
DECLARE
  v_entropy_id BIGINT;
  v_entropy_score NUMERIC(18,6);
  v_decision TEXT;
  v_reason TEXT;
BEGIN
  -- Ensure entropy exists
  SELECT entropy_id, entropy_score INTO v_entropy_id, v_entropy_score
  FROM entropy_metrics
  WHERE snapshot_id = p_snapshot_id;

  IF v_entropy_id IS NULL THEN
    v_entropy_score := calculate_entropy(p_snapshot_id);
    INSERT INTO entropy_metrics(snapshot_id, entropy_score, calculated_at)
    VALUES (p_snapshot_id, v_entropy_score, NOW())
    ON CONFLICT (snapshot_id)
    DO UPDATE SET entropy_score = EXCLUDED.entropy_score, calculated_at = EXCLUDED.calculated_at
    RETURNING entropy_id INTO v_entropy_id;
  END IF;

  -- Decide via rules
  SELECT pr.decision_type INTO v_decision
  FROM preservation_rules pr
  WHERE (
    (pr.max_entropy IS NOT NULL AND v_entropy_score BETWEEN pr.min_entropy AND pr.max_entropy)
    OR (pr.max_entropy IS NULL AND v_entropy_score > pr.min_entropy)
    OR (v_entropy_score = 0 AND pr.min_entropy = 0 AND pr.max_entropy = 0)
  )
  ORDER BY pr.min_entropy DESC
  LIMIT 1;

  -- Fallback for tiny non-zero entropies (e.g., 0.001)
  IF v_decision IS NULL THEN
    IF v_entropy_score < 0.01 THEN
      v_decision := 'DISCARD';
    ELSE
      v_decision := 'PRESERVE';
    END IF;
  END IF;
  
  -- Auto-generate reason
  IF v_decision = 'DISCARD' THEN
    v_reason := 'Entropy is zero or very low, snapshot contains minimal changes.';
  ELSIF v_decision = 'COMPRESS' THEN
    v_reason := 'Entropy is low, snapshot can be compressed to save storage.';
  ELSIF v_decision = 'PRESERVE' THEN
    v_reason := 'Entropy is moderate, snapshot should remain actively available.';
  ELSIF v_decision = 'ARCHIVE' THEN
    v_reason := 'Entropy is high, snapshot should be archived for long-term preservation.';
  ELSE
    v_reason := 'System auto-decision based on threshold evaluation.';
  END IF;

  -- Ensure we don't overwrite manual overrides with an automatic pass unless needed,
  -- but since triggers recompute all, we should probably just update the decision_type and reason if it's not manual.
  -- Wait, the prompt implies "update decide_preservation() function so every automatic decision stores a reason"
  -- We'll just do a standard upsert for now, but preserve manual flag if it exists? Actually, the prompt doesn't specify not overwriting manual decisions automatically, but we should be careful.
  -- Let's just update the rule logic as asked:
  INSERT INTO preservation_decisions(snapshot_id, entropy_id, decision_type, decided_at, reason)
  VALUES (p_snapshot_id, v_entropy_id, v_decision, NOW(), v_reason)
  ON CONFLICT (snapshot_id)
  DO UPDATE SET 
    entropy_id = EXCLUDED.entropy_id, 
    decision_type = CASE WHEN preservation_decisions.is_manual THEN preservation_decisions.decision_type ELSE EXCLUDED.decision_type END,
    decided_at = EXCLUDED.decided_at,
    reason = CASE WHEN preservation_decisions.is_manual THEN preservation_decisions.reason ELSE EXCLUDED.reason END;

  RETURN v_decision;
END;
$$;

-- =========
-- Integrity check maintenance
-- =========

CREATE OR REPLACE FUNCTION fn_update_integrity(p_snapshot_id BIGINT)
RETURNS VOID
LANGUAGE plpgsql
AS $$
DECLARE
  v_has_corruption BOOLEAN;
  v_status TEXT;
BEGIN
  SELECT EXISTS(
    SELECT 1 FROM state_changes
    WHERE snapshot_id = p_snapshot_id
      AND change_type = 'CORRUPTION'
  ) INTO v_has_corruption;

  v_status := CASE WHEN v_has_corruption THEN 'CORRUPTED' ELSE 'VALID' END;

  INSERT INTO integrity_checks(snapshot_id, status, checked_at)
  VALUES (p_snapshot_id, v_status, NOW())
  ON CONFLICT (snapshot_id)
  DO UPDATE SET status = EXCLUDED.status, checked_at = EXCLUDED.checked_at;
END;
$$;

-- =========
-- Compression / Archive materialization
-- =========

CREATE OR REPLACE FUNCTION fn_materialize_preservation(p_snapshot_id BIGINT)
RETURNS VOID
LANGUAGE plpgsql
AS $$
DECLARE
  v_decision TEXT;
  v_entropy NUMERIC(18,6);
  v_ratio NUMERIC(10,4);
  v_location TEXT;
BEGIN
  SELECT pd.decision_type, em.entropy_score INTO v_decision, v_entropy
  FROM preservation_decisions pd
  JOIN entropy_metrics em ON em.entropy_id = pd.entropy_id
  WHERE pd.snapshot_id = p_snapshot_id;

  IF v_decision IS NULL THEN
    RETURN;
  END IF;

  -- COMPRESS => insert/update compressed_states
  IF v_decision = 'COMPRESS' THEN
    -- simple, academically defensible mapping: higher entropy => worse compression
    v_ratio := LEAST(1.0, GREATEST(0.10, 1.0 - (v_entropy * 0.50)));
    INSERT INTO compressed_states(snapshot_id, compression_ratio, compressed_at)
    VALUES (p_snapshot_id, v_ratio, NOW())
    ON CONFLICT (snapshot_id)
    DO UPDATE SET compression_ratio = EXCLUDED.compression_ratio, compressed_at = EXCLUDED.compressed_at;

    -- If previously archived, keep archive row (history) but do not create new here.
  ELSIF v_decision = 'ARCHIVE' THEN
    -- Generate a deterministic location string; in real systems this points to object storage.
    v_location := '/archives/universe_snapshot_' || p_snapshot_id || '_' || to_char(NOW(), 'YYYYMMDD_HH24MISS') || '.bin';
    INSERT INTO archives(snapshot_id, archive_location, archived_at)
    VALUES (p_snapshot_id, v_location, NOW())
    ON CONFLICT (snapshot_id)
    DO UPDATE SET archive_location = EXCLUDED.archive_location, archived_at = EXCLUDED.archived_at;
  END IF;
END;
$$;

-- =========
-- Triggers
-- =========

-- Auto-assign change_weight based on change_type
CREATE OR REPLACE FUNCTION trg_state_changes_set_weight()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
  IF NEW.change_weight IS NULL OR NEW.change_weight <= 0 THEN
    NEW.change_weight := fn_change_weight(NEW.change_type);
  END IF;
  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS bi_state_changes_set_weight ON state_changes;
CREATE TRIGGER bi_state_changes_set_weight
BEFORE INSERT OR UPDATE OF change_type, change_weight
ON state_changes
FOR EACH ROW
EXECUTE FUNCTION trg_state_changes_set_weight();

-- Recompute entropy + decide + integrity + materialize
CREATE OR REPLACE FUNCTION fn_recompute_all(p_snapshot_id BIGINT)
RETURNS VOID
LANGUAGE plpgsql
AS $$
DECLARE
  v_entropy NUMERIC(18,6);
  v_decision TEXT;
BEGIN
  v_entropy := calculate_entropy(p_snapshot_id);

  INSERT INTO entropy_metrics(snapshot_id, entropy_score, calculated_at)
  VALUES (p_snapshot_id, v_entropy, NOW())
  ON CONFLICT (snapshot_id)
  DO UPDATE SET entropy_score = EXCLUDED.entropy_score, calculated_at = EXCLUDED.calculated_at;

  v_decision := decide_preservation(p_snapshot_id);
  PERFORM fn_update_integrity(p_snapshot_id);
  PERFORM fn_materialize_preservation(p_snapshot_id);
END;
$$;

CREATE OR REPLACE FUNCTION trg_state_changes_after_write()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
  v_snapshot_id BIGINT;
BEGIN
  v_snapshot_id := COALESCE(NEW.snapshot_id, OLD.snapshot_id);
  PERFORM fn_recompute_all(v_snapshot_id);
  RETURN COALESCE(NEW, OLD);
END;
$$;

DROP TRIGGER IF EXISTS aiud_state_changes_after_write ON state_changes;
CREATE TRIGGER aiud_state_changes_after_write
AFTER INSERT OR UPDATE OR DELETE
ON state_changes
FOR EACH ROW
EXECUTE FUNCTION trg_state_changes_after_write();

-- Ensure entropy/decision/integrity rows exist immediately after a snapshot is created
CREATE OR REPLACE FUNCTION trg_state_snapshots_after_insert()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
  PERFORM fn_recompute_all(NEW.snapshot_id);
  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS ai_state_snapshots_after_insert ON state_snapshots;
CREATE TRIGGER ai_state_snapshots_after_insert
AFTER INSERT
ON state_snapshots
FOR EACH ROW
EXECUTE FUNCTION trg_state_snapshots_after_insert();

-- =========
-- Audit logging (auto-log actions into audit_logs)
-- =========

CREATE OR REPLACE FUNCTION fn_audit_log()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
  v_entity_id BIGINT;
BEGIN
  -- Best-effort entity_id lookup (expects conventional PK names)
  v_entity_id := NULL;

  IF TG_OP = 'INSERT' THEN
    BEGIN
      v_entity_id := (to_jsonb(NEW)->>(TG_ARGV[0]))::BIGINT;
    EXCEPTION WHEN OTHERS THEN
      v_entity_id := NULL;
    END;
  ELSIF TG_OP = 'UPDATE' THEN
    BEGIN
      v_entity_id := (to_jsonb(NEW)->>(TG_ARGV[0]))::BIGINT;
    EXCEPTION WHEN OTHERS THEN
      v_entity_id := NULL;
    END;
  ELSIF TG_OP = 'DELETE' THEN
    BEGIN
      v_entity_id := (to_jsonb(OLD)->>(TG_ARGV[0]))::BIGINT;
    EXCEPTION WHEN OTHERS THEN
      v_entity_id := NULL;
    END;
  END IF;

  INSERT INTO audit_logs(action, entity_name, entity_id, timestamp)
  VALUES (TG_OP, TG_TABLE_NAME, v_entity_id, NOW());

  RETURN COALESCE(NEW, OLD);
END;
$$;

-- Attach audit triggers
DO $$
BEGIN
  -- preservation_rules
  EXECUTE 'DROP TRIGGER IF EXISTS audit_preservation_rules ON preservation_rules';
  EXECUTE 'CREATE TRIGGER audit_preservation_rules AFTER INSERT OR UPDATE OR DELETE ON preservation_rules FOR EACH ROW EXECUTE FUNCTION fn_audit_log(''rule_id'')';

  -- universes
  EXECUTE 'DROP TRIGGER IF EXISTS audit_universes ON universes';
  EXECUTE 'CREATE TRIGGER audit_universes AFTER INSERT OR UPDATE OR DELETE ON universes FOR EACH ROW EXECUTE FUNCTION fn_audit_log(''universe_id'')';

  -- state_snapshots
  EXECUTE 'DROP TRIGGER IF EXISTS audit_state_snapshots ON state_snapshots';
  EXECUTE 'CREATE TRIGGER audit_state_snapshots AFTER INSERT OR UPDATE OR DELETE ON state_snapshots FOR EACH ROW EXECUTE FUNCTION fn_audit_log(''snapshot_id'')';

  -- state_changes
  EXECUTE 'DROP TRIGGER IF EXISTS audit_state_changes ON state_changes';
  EXECUTE 'CREATE TRIGGER audit_state_changes AFTER INSERT OR UPDATE OR DELETE ON state_changes FOR EACH ROW EXECUTE FUNCTION fn_audit_log(''change_id'')';

  -- entropy_metrics
  EXECUTE 'DROP TRIGGER IF EXISTS audit_entropy_metrics ON entropy_metrics';
  EXECUTE 'CREATE TRIGGER audit_entropy_metrics AFTER INSERT OR UPDATE OR DELETE ON entropy_metrics FOR EACH ROW EXECUTE FUNCTION fn_audit_log(''entropy_id'')';

  -- preservation_decisions
  EXECUTE 'DROP TRIGGER IF EXISTS audit_preservation_decisions ON preservation_decisions';
  EXECUTE 'CREATE TRIGGER audit_preservation_decisions AFTER INSERT OR UPDATE OR DELETE ON preservation_decisions FOR EACH ROW EXECUTE FUNCTION fn_audit_log(''decision_id'')';

  -- compressed_states
  EXECUTE 'DROP TRIGGER IF EXISTS audit_compressed_states ON compressed_states';
  EXECUTE 'CREATE TRIGGER audit_compressed_states AFTER INSERT OR UPDATE OR DELETE ON compressed_states FOR EACH ROW EXECUTE FUNCTION fn_audit_log(''compressed_id'')';

  -- archives
  EXECUTE 'DROP TRIGGER IF EXISTS audit_archives ON archives';
  EXECUTE 'CREATE TRIGGER audit_archives AFTER INSERT OR UPDATE OR DELETE ON archives FOR EACH ROW EXECUTE FUNCTION fn_audit_log(''archive_id'')';

  -- integrity_checks
  EXECUTE 'DROP TRIGGER IF EXISTS audit_integrity_checks ON integrity_checks';
  EXECUTE 'CREATE TRIGGER audit_integrity_checks AFTER INSERT OR UPDATE OR DELETE ON integrity_checks FOR EACH ROW EXECUTE FUNCTION fn_audit_log(''check_id'')';

END $$;

-- =========
-- Views (required)
-- =========

-- snapshot_summary: per snapshot with entropy, decision, integrity
CREATE OR REPLACE VIEW snapshot_summary AS
SELECT
  u.universe_id,
  u.name AS universe_name,
  s.snapshot_id,
  s.version_number,
  s.snapshot_size_mb,
  s.created_at AS snapshot_created_at,
  em.entropy_score,
  em.calculated_at,
  pd.decision_type,
  pd.decided_at,
  pd.is_manual,
  pd.manual_reason,
  pd.overridden_at,
  pd.reason,
  ic.status AS integrity_status,
  ic.checked_at,
  cs.compression_ratio,
  cs.compressed_at,
  a.archive_location,
  a.archived_at
FROM universes u
JOIN state_snapshots s ON s.universe_id = u.universe_id
LEFT JOIN entropy_metrics em ON em.snapshot_id = s.snapshot_id
LEFT JOIN preservation_decisions pd ON pd.snapshot_id = s.snapshot_id
LEFT JOIN integrity_checks ic ON ic.snapshot_id = s.snapshot_id
LEFT JOIN compressed_states cs ON cs.snapshot_id = s.snapshot_id
LEFT JOIN archives a ON a.snapshot_id = s.snapshot_id;

-- entropy_trends: time series per universe
CREATE OR REPLACE VIEW entropy_trends AS
SELECT
  u.universe_id,
  u.name AS universe_name,
  date_trunc('day', s.created_at) AS day,
  AVG(em.entropy_score) AS avg_entropy,
  MAX(em.entropy_score) AS max_entropy,
  COUNT(*) AS snapshots_count
FROM universes u
JOIN state_snapshots s ON s.universe_id = u.universe_id
JOIN entropy_metrics em ON em.snapshot_id = s.snapshot_id
GROUP BY u.universe_id, u.name, date_trunc('day', s.created_at)
ORDER BY day ASC;

-- preservation_stats: counts per decision type
CREATE OR REPLACE VIEW preservation_stats AS
SELECT
  pd.decision_type,
  COUNT(*) AS decisions_count
FROM preservation_decisions pd
GROUP BY pd.decision_type
ORDER BY decisions_count DESC;

COMMIT;
