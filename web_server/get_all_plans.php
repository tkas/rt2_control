<?php
header('Content-Type: application/json');
require 'config.php';
require 'connect_db.php';

try {
    $stmt = $pdo->query('SELECT * FROM plan ORDER BY rec_start_time DESC');
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $plans]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>