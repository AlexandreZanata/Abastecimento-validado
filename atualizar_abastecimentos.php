<?php
// atualizar_abastecimentos.php
session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id'])) {
    exit();
}

$posto_id = $_SESSION['user_id'];

try {
    // Buscar abastecimentos pendentes para este posto
    $stmt = $conn->prepare("SELECT ap.*, u.name as motorista_name, v.tanque as tanque_veiculo
                           FROM abastecimentos_pendentes ap
                           JOIN usuarios u ON ap.motorista_id = u.id
                           JOIN veiculos v ON ap.veiculo_id = v.id
                           WHERE ap.posto_id = :posto_id
                           AND ap.status = 'aguardando_posto'
                           ORDER BY ap.data_criacao DESC");
    $stmt->bindParam(':posto_id', $posto_id);
    $stmt->execute();
    $abastecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar abastecimentos já preenchidos (aguardando assinatura ou concluídos)
    $abastecimentos_preenchidos = $conn->prepare("SELECT ap.*, u.name as motorista_name
                                                 FROM abastecimentos_pendentes ap
                                                 JOIN usuarios u ON ap.motorista_id = u.id
                                                 WHERE ap.posto_id = :posto_id
                                                 AND (ap.status = 'aguardando_assinatura' OR ap.status = 'concluido')
                                                 ORDER BY
                                                     CASE WHEN ap.status = 'aguardando_assinatura' THEN 0 ELSE 1 END,
                                                     ap.data_preenchimento DESC");
    $abastecimentos_preenchidos->bindParam(':posto_id', $posto_id);
    $abastecimentos_preenchidos->execute();
    $abastecimentos_preenchidos = $abastecimentos_preenchidos->fetchAll(PDO::FETCH_ASSOC);

    // Retornar como JSON
    header('Content-Type: application/json');
    echo json_encode([
        'pendentes' => $abastecimentos,
        'preenchidos' => $abastecimentos_preenchidos
    ]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro ao carregar abastecimentos']);
}
?>