<?php

header('Content-Type: application/json');

require_once 'config.php';
require_once 'connect_db.php';

try
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['id'])) {
        throw new Exception('No id provided');
    }

    $id = (int)$input['id'];

    $sql = 'DELETE FROM plan WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(['success' => true]);
}
catch (Throwable $e)
{
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

?>
