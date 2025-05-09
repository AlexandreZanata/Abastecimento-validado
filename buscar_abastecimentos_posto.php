<?php
session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id'])) {
    exit();
}

$posto_id = $_SESSION['user_id'];
$tipo = $_GET['tipo'] ?? '';

try {
    if ($tipo == 'pendentes') {
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

        if (empty($abastecimentos)) {
            echo '<p class="text-gray-600">Nenhum abastecimento pendente para este posto.</p>';
        } else {
            foreach ($abastecimentos as $abastecimento) {
                echo '<div class="border rounded-lg p-4">';
                echo '<form method="POST" class="space-y-3">';
                echo '<input type="hidden" name="abastecimento_id" value="' . $abastecimento['id'] . '">';
                
                echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
                echo '<div>';
                echo '<label class="block text-gray-700 mb-1">Motorista</label>';
                echo '<input type="text" class="w-full p-2 border rounded bg-gray-50" ';
                echo 'value="' . $abastecimento['motorista_name'] . '" readonly>';
                echo '</div>';
                
                echo '<div>';
                echo '<label class="block text-gray-700 mb-1">Veículo</label>';
                echo '<input type="text" class="w-full p-2 border rounded bg-gray-50" ';
                echo 'value="' . $abastecimento['veiculo_nome'] . ' - ' . $abastecimento['placa'] . '" readonly>';
                echo '</div>';
                
                echo '<div>';
                echo '<label class="block text-gray-700 mb-1">KM</label>';
                echo '<input type="text" class="w-full p-2 border rounded bg-gray-50" ';
                echo 'value="' . $abastecimento['km_abastecido'] . '" readonly>';
                echo '</div>';
                echo '</div>';
                
                echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
                echo '<div>';
                echo '<label class="block text-gray-700 mb-1">Litros*</label>';
                echo '<input type="text" name="litros" class="w-full p-2 border rounded" ';
                echo 'placeholder="Ex: 30.50" required>';
                echo '<p class="text-xs text-gray-500 mt-1">Capacidade do tanque: ' . $abastecimento['tanque_veiculo'] . ' litros</p>';
                echo '</div>';
                
                echo '<div>';
                echo '<label class="block text-gray-700 mb-1">Combustível*</label>';
                echo '<select name="combustivel" class="w-full p-2 border rounded" required>';
                echo '<option value="">Selecione</option>';
                echo '<option value="Gasolina">Gasolina</option>';
                echo '<option value="Etanol">Etanol</option>';
                echo '<option value="Diesel">Diesel</option>';
                echo '<option value="GNV">GNV</option>';
                echo '</select>';
                echo '</div>';
                
                echo '<div>';
                echo '<label class="block text-gray-700 mb-1">Valor (R$)*</label>';
                echo '<input type="text" name="valor" class="w-full p-2 border rounded" ';
                echo 'placeholder="Ex: 150.75" required>';
                echo '</div>';
                echo '</div>';
                
                echo '<div class="pt-2">';
                echo '<button type="submit" name="preencher_abastecimento" ';
                echo 'class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition">';
                echo 'Registrar Abastecimento</button>';
                echo '</div>';
                echo '</form>';
                echo '</div>';
            }
        }
    } elseif ($tipo == 'assinatura') {
        $stmt = $conn->prepare("SELECT ap.*, u.name as motorista_name 
                               FROM abastecimentos_pendentes ap
                               JOIN usuarios u ON ap.motorista_id = u.id
                               WHERE ap.posto_id = :posto_id 
                               AND ap.status = 'aguardando_assinatura'
                               ORDER BY ap.data_preenchimento DESC");
        $stmt->bindParam(':posto_id', $posto_id);
        $stmt->execute();
        $abastecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($abastecimentos)) {
            echo '<p class="text-gray-600">Nenhum abastecimento aguardando assinatura.</p>';
        } else {
            foreach ($abastecimentos as $abastecimento) {
                echo '<div class="border rounded-lg p-4">';
                echo '<form method="POST" class="space-y-3">';
                echo '<input type="hidden" name="abastecimento_id" value="' . $abastecimento['id'] . '">';
                
                echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
                echo '<div>';
                echo '<label class="block text-gray-700 mb-1">Motorista</label>';
                echo '<input type="text" class="w-full p-2 border rounded bg-gray-50" ';
                echo 'value="' . $abastecimento['motorista_name'] . '" readonly>';
                echo '</div>';
                
                echo '<div>';
                echo '<label class="block text-gray-700 mb-1">Veículo</label>';
                echo '<input type="text" class="w-full p-2 border rounded bg-gray-50" ';
                echo 'value="' . $abastecimento['veiculo_nome'] . ' - ' . $abastecimento['placa'] . '" readonly>';
                echo '</div>';
                
                echo '<div>';
                echo '<label class="block text-gray-700 mb-1">KM</label>';
                echo '<input type="text" class="w-full p-2 border rounded bg-gray-50" ';
                echo 'value="' . $abastecimento['km_abastecido'] . '" readonly>';
                echo '</div>';
                echo '</div>';
                
                echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
                echo '<div>';
                echo '<label class="block text-gray-700 mb-1">Litros*</label>';
                echo '<input type="text" name="litros" class="w-full p-2 border rounded" ';
                echo 'value="' . $abastecimento['litros'] . '" required>';
                echo '</div>';
                
                echo '<div>';
                echo '<label class="block text-gray-700 mb-1">Combustível*</label>';
                echo '<select name="combustivel" class="w-full p-2 border rounded" required>';
                echo '<option value="' . $abastecimento['combustivel'] . '" selected>' . $abastecimento['combustivel'] . '</option>';
                echo '<option value="Gasolina">Gasolina</option>';
                echo '<option value="Etanol">Etanol</option>';
                echo '<option value="Diesel">Diesel</option>';
                echo '<option value="GNV">GNV</option>';
                echo '</select>';
                echo '</div>';
                
                echo '<div>';
                echo '<label class="block text-gray-700 mb-1">Valor (R$)*</label>';
                echo '<input type="text" name="valor" class="w-full p-2 border rounded" ';
                echo 'value="' . number_format($abastecimento['valor'], 2, '.', '') . '" required>';
                echo '</div>';
                echo '</div>';
                
                echo '<div class="pt-2">';
                echo '<button type="submit" name="editar_abastecimento" ';
                echo 'class="bg-yellow-600 text-white py-2 px-4 rounded hover:bg-yellow-700 transition">';
                echo 'Atualizar Dados</button>';
                echo '</div>';
                echo '</form>';
                echo '</div>';
            }
        }
    }
} catch (PDOException $e) {
    echo '<p class="text-red-600">Erro ao carregar abastecimentos</p>';
}
?>