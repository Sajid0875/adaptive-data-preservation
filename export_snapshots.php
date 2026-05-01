<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();
$pdo = db();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="snapshots_export_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Universe', 'Snapshot ID', 'Version', 'Size (MB)', 'Entropy', 'Decision', 'Reason', 'Integrity', 'Created At']);

$stmt = $pdo->query('SELECT * FROM snapshot_summary ORDER BY snapshot_created_at DESC');
while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['universe_name'],
        $row['snapshot_id'],
        $row['version_number'],
        $row['snapshot_size_mb'],
        $row['entropy_score'],
        $row['decision_type'],
        $row['reason'] ?? 'N/A',
        $row['integrity_status'],
        $row['snapshot_created_at']
    ]);
}
fclose($output);
