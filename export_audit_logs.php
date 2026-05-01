<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();
$pdo = db();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="audit_logs_export_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Log ID', 'Action', 'Entity Name', 'Entity ID', 'Timestamp']);

$stmt = $pdo->query("SELECT log_id, action, entity_name, entity_id, timestamp FROM audit_logs ORDER BY timestamp DESC");

while ($row = $stmt->fetch()) {
    fputcsv($output, [
        $row['log_id'],
        $row['action'],
        $row['entity_name'],
        $row['entity_id'] ?? 'NULL',
        $row['timestamp']
    ]);
}
fclose($output);
