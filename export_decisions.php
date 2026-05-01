<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();
$pdo = db();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="decisions_export_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Decision ID', 'Snapshot ID', 'Universe', 'Version', 'Decision Type', 'Is Manual', 'Manual Reason', 'Decided At', 'System Reason']);

$stmt = $pdo->query("SELECT pd.decision_id, pd.snapshot_id, u.name AS universe_name, s.version_number, pd.decision_type, pd.is_manual, pd.manual_reason, pd.decided_at, pd.reason FROM preservation_decisions pd JOIN state_snapshots s ON s.snapshot_id = pd.snapshot_id JOIN universes u ON u.universe_id = s.universe_id ORDER BY pd.decided_at DESC");

while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['decision_id'],
        $row['snapshot_id'],
        $row['universe_name'],
        $row['version_number'],
        $row['decision_type'],
        $row['is_manual'] ? 'Yes' : 'No',
        $row['manual_reason'] ?? '',
        $row['decided_at'],
        $row['reason'] ?? ''
    ]);
}
fclose($output);
