<?php
// buscar_secretarias_fornecedor.php
header('Content-Type: application/json');

$host = "localhost"; 
$dbname = "workflow_system"; 
$username = "root"; 
$password = ""; 

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => "Erro de conexão: " . $e->getMessage()]));
}

if (!isset($_GET['fornecedor'])) {
    die(json_encode(['error' => 'Fornecedor não especificado']));
}

$fornecedor = $_GET['fornecedor'];

try {
    $stmt = $conn->prepare("
        SELECT
            secretaria,
            valor_total,
            valor_etanol,
            valor_gasolina,
            valor_diesel,
            valor_diesel_s10
        FROM empenhos_secretarias
        WHERE fornecedor = :fornecedor
        ORDER BY valor_total DESC
    ");
    $stmt->bindParam(':fornecedor', $fornecedor);
    $stmt->execute();

    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($resultados);
} catch (PDOException $e) {
    die(json_encode(['error' => "Erro na consulta: " . $e->getMessage()]));
}
?>