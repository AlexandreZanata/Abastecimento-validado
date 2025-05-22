<?php
session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'posto') {
    header("HTTP/1.1 403 Forbidden");
    exit('Acesso negado');
}

$posto_id = $_SESSION['user_id'];
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

try {
    if ($tipo === 'pendentes') {
        // Buscar abastecimentos pendentes para este posto
        $stmt = $conn->prepare("SELECT ap.*, u.name as motorista_name, u.cpf as motorista_cpf,
                               u.secretaria as motorista_secretaria, u.profile_photo as motorista_foto,
                               u.codigo_veiculo, v.tanque as tanque_veiculo, v.combustivel as combustivel_veiculo, vei.veiculo as nome_veiculo
                               FROM abastecimentos_pendentes ap
                               JOIN usuarios u ON ap.motorista_id = u.id
                               JOIN veiculos v ON ap.veiculo_id = v.id
                               LEFT JOIN veiculos vei ON u.codigo_veiculo = vei.id
                               WHERE ap.posto_id = :posto_id
                               AND ap.status = 'aguardando_posto'
                               ORDER BY ap.data_criacao DESC");
        $stmt->bindParam(':posto_id', $posto_id);
        $stmt->execute();
        $abastecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($abastecimentos)) {
            echo '<div class="text-center py-8">
                    <i class="fas fa-check-circle text-gray-300 text-4xl mb-3"></i>
                    <p class="text-gray-600">Nenhum abastecimento pendente para este posto.</p>
                </div>';
        } else {
            echo '<div class="space-y-6">';
            foreach ($abastecimentos as $abastecimento) {
                include 'template_abastecimento_pendente.php';
            }
            echo '</div>';
        }
    } 
    else if ($tipo === 'aguardando_assinatura') {
        // Buscar abastecimentos aguardando assinatura
        $stmt = $conn->prepare("SELECT ap.*, u.name as motorista_name, u.cpf as motorista_cpf,
                               u.secretaria as motorista_secretaria, u.profile_photo as motorista_foto,
                               u.codigo_veiculo, vei.veiculo as nome_veiculo
                               FROM abastecimentos_pendentes ap
                               JOIN usuarios u ON ap.motorista_id = u.id
                               LEFT JOIN veiculos vei ON u.codigo_veiculo = vei.id
                               WHERE ap.posto_id = :posto_id
                               AND ap.status = 'aguardando_assinatura'
                               ORDER BY ap.data_preenchimento DESC");
        $stmt->bindParam(':posto_id', $posto_id);
        $stmt->execute();
        $abastecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($abastecimentos)) {
            echo '<div class="text-center py-8">
                    <i class="fas fa-check-circle text-gray-300 text-4xl mb-3"></i>
                    <p class="text-gray-600">Nenhum abastecimento aguardando assinatura.</p>
                </div>';
        } else {
            echo '<div class="space-y-6">';
            foreach ($abastecimentos as $abastecimento) {
                include 'template_abastecimento_assinatura.php';
            }
            echo '</div>';
        }
    }
    
} catch (PDOException $e) {
    echo '<div class="text-center py-8">
            <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-3"></i>
            <p class="text-gray-600">Erro ao carregar dados: ' . $e->getMessage() . '</p>
          </div>';
}

function formatarCPF($cpf) {
    if (empty($cpf)) return 'Não informado';
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}
?>