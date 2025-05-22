<?php
session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'posto') {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $abastecimento_id = $_POST['id'];
    $posto_id = $_SESSION['user_id'];

    try {
        // Verificar se o abastecimento pertence ao posto logado
        $stmt = $conn->prepare("SELECT id FROM abastecimentos_pendentes WHERE id = :id AND posto_id = :posto_id");
        $stmt->bindParam(':id', $abastecimento_id);
        $stmt->bindParam(':posto_id', $posto_id);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => 'Abastecimento não encontrado ou não pertence a este posto']);
            exit();
        }

        // Excluir o abastecimento
        $stmt = $conn->prepare("DELETE FROM abastecimentos_pendentes WHERE id = :id");
        $stmt->bindParam(':id', $abastecimento_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Abastecimento excluído com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir abastecimento']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>