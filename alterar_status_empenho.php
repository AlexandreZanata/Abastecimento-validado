<?php
session_start();

// Verificar se o usuário está logado e é admin
if (!isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado']);
    exit();
}

$host = "localhost"; 
$dbname = "workflow_system"; 
$username = "root"; 
$password = ""; 

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão: ' . $e->getMessage()]);
    exit();
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

// Validar dados recebidos
$required_fields = ['id', 'tabela', 'novo_status', 'numero_empenho'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
        exit();
    }
}

$id = $_POST['id'];
$tabela = $_POST['tabela'];
$novo_status = $_POST['novo_status'];
$numero_empenho = $_POST['numero_empenho'];
$usuario = $_SESSION['username'] ?? 'Sistema';

// Validar tabela permitida
if (!in_array($tabela, ['empenhos_totais', 'empenhos_secretarias'])) {
    echo json_encode(['success' => false, 'message' => 'Tabela inválida']);
    exit();
}

try {
    // Iniciar transação
    $conn->beginTransaction();
    
    // Atualizar status do empenho
    $stmt = $conn->prepare("UPDATE $tabela SET status = :status WHERE id = :id");
    $stmt->bindParam(':status', $novo_status);
    $stmt->bindParam(':id', $id);
    
    if (!$stmt->execute()) {
        throw new Exception("Falha ao atualizar status");
    }
    
    // Registrar a ação na tabela ativar_empenho
    $acao = $novo_status === 'ativo' ? 'ativar' : 'desativar';
    $stmt = $conn->prepare("INSERT INTO ativar_empenho (id_empenho, tabela, acao, usuario) 
                           VALUES (:id_empenho, :tabela, :acao, :usuario)");
    $stmt->bindParam(':id_empenho', $id);
    $stmt->bindParam(':tabela', $tabela);
    $stmt->bindParam(':acao', $acao);
    $stmt->bindParam(':usuario', $usuario);
    
    if (!$stmt->execute()) {
        throw new Exception("Falha ao registrar ação");
    }
    
    // Commit da transação
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Empenho $numero_empenho " . ($novo_status === 'ativo' ? 'ativado' : 'desativado') . " com sucesso!"
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Erro ao alterar status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => "Erro ao alterar status: " . $e->getMessage()
    ]);
}