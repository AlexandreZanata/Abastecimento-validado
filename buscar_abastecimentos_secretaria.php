<?php
session_start();
include '../conexao.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'geraladm') {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

$secretaria = $_GET['secretaria'] ?? '';

try {
    $stmt = $conn->prepare("
        SELECT 
            data,
            hora,
            veiculo,
            placa,
            combustivel,
            valor
        FROM registro_abastecimento
        WHERE secretaria = :secretaria
        ORDER BY data DESC, hora DESC
        LIMIT 5
    ");
    $stmt->bindParam(':secretaria', $secretaria);
    $stmt->execute();
    
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
}
?>