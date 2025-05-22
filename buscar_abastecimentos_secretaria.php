<?php
session_start();
require_once 'conexao.php';

if (!isset($_GET['secretaria'])) {
    die("Secretaria não especificada");
}

$secretaria = $_GET['secretaria'];

try {
    $stmt = $conn->prepare("
        SELECT
            data_abastecimento as data,
            veiculo,
            placa,
            combustivel,
            valor
        FROM abastecimentos
        WHERE secretaria = :secretaria
        ORDER BY data_abastecimento DESC
        LIMIT 10
    ");
    $stmt->bindParam(':secretaria', $secretaria);
    $stmt->execute();

    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($resultados);
} catch (PDOException $e) {
    die("Erro ao buscar abastecimentos: " . $e->getMessage());
}
