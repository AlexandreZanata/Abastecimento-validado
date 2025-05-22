<?php
include '../conexao.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Não autorizado
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("SELECT ap.id, ap.km_abastecido, ap.status, u.name as posto_name, ap.litros, ap.combustivel, ap.valor
                            FROM abastecimentos_pendentes ap
                            JOIN usuarios u ON ap.posto_id = u.id
                            WHERE ap.motorista_id = :user_id
                            AND ap.status IN ('aguardando_posto', 'aguardando_assinatura')
                            ORDER BY ap.data_criacao DESC");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $abastecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($abastecimentos);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar abastecimentos pendentes: ' . $e->getMessage()]);
}
?>