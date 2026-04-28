-- 04_queries.sql
-- Complex queries (required)

-- 1) Top unstable universes (highest average entropy)
SELECT
  u.universe_id,
  u.name AS universe_name,
  ROUND(AVG(em.entropy_score), 6) AS avg_entropy,
  ROUND(MAX(em.entropy_score), 6) AS max_entropy,
  COUNT(*) AS snapshots
FROM universes u
JOIN state_snapshots s ON s.universe_id = u.universe_id
JOIN entropy_metrics em ON em.snapshot_id = s.snapshot_id
GROUP BY u.universe_id, u.name
ORDER BY avg_entropy DESC
LIMIT 5;

-- 2) Most archived snapshots (by universe)
SELECT
  u.universe_id,
  u.name AS universe_name,
  COUNT(a.archive_id) AS archived_snapshots
FROM universes u
JOIN state_snapshots s ON s.universe_id = u.universe_id
LEFT JOIN archives a ON a.snapshot_id = s.snapshot_id
GROUP BY u.universe_id, u.name
ORDER BY archived_snapshots DESC, u.universe_id ASC;

-- 3) Entropy trend over time (daily)
SELECT *
FROM entropy_trends
ORDER BY universe_id, day;

-- Extra: Recent snapshots with full summary
SELECT *
FROM snapshot_summary
ORDER BY snapshot_created_at DESC
LIMIT 20;
