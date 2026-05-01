<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();
$pdo = db();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="entropy_metrics_export_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Entropy ID', 'Snapshot ID', 'Universe', 'Version', 'Entropy Score', 'Calculated At']);

$stmt = $pdo->query("SELECT em.entropy_id, em.snapshot_id, u.name AS universe_name, s.version_number, em.entropy_score, em.calculated_at FROM entropy_metrics em JOIN state_snapshots s ON s.snapshot_id = em.snapshot_id JOIN universes u ON u.universe_id = s.universe_id ORDER BY em.calculated_at DESC");

while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['entropy_id'],
        $row['snapshot_id'],
        $row['universe_name'],
        $row['version_number'],
        $row['entropy_score'],
        $row['calculated_at']
    ]);
}
fclose($output);
