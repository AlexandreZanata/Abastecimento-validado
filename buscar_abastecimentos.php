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
            r.*,
            v.nome as veiculo_nome,
            u.nome as motorista_name
        FROM registro_abastecimento r
        LEFT JOIN veiculos v ON r.prefixo = v.prefixo OR r.placa = v.placa
        LEFT JOIN usuarios u ON r.nome = u.nome
        WHERE r.secretaria = :secretaria
        ORDER BY r.data DESC, r.hora DESC
        LIMIT 10
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