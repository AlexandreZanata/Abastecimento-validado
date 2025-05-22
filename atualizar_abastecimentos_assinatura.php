<?php
include '../conexao.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    try {
        $posto_id = $_SESSION['user_id'];

        $stmt = $conn->prepare("SELECT ap.*, u.name as motorista_name, u.cpf as motorista_cpf,
                                u.secretaria as motorista_secretaria, u.profile_photo as motorista_foto,
                                vei.veiculo as nome_veiculo
                                FROM abastecimentos_pendentes ap
                                JOIN usuarios u ON ap.motorista_id = u.id
                                LEFT JOIN veiculos vei ON u.codigo_veiculo = vei.id
                                WHERE ap.posto_id = :posto_id
                                AND ap.status = 'aguardando_assinatura'
                                ORDER BY ap.data_preenchimento DESC");
        $stmt->bindParam(':posto_id', $posto_id);
        $stmt->execute();
        $abastecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($abastecimentos);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>