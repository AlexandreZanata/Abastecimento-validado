<?php
session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id'])) {
    die("Acesso não autorizado");
}

$posto_id = $_SESSION['user_id'];
$tipo = $_GET['tipo'] ?? 'hoje';

try {
    // Consulta base
    $sql = "SELECT ap.*, u.name as motorista_name
            FROM abastecimentos_pendentes ap
            JOIN usuarios u ON ap.motorista_id = u.id
            WHERE ap.posto_id = :posto_id
            AND ap.status = 'concluido'";

    // Adicionar filtros conforme o tipo
    if ($tipo === 'hoje') {
        $sql .= " AND DATE(ap.data_assinatura) = CURDATE()";
    } elseif ($tipo === 'personalizado' && isset($_GET['data_inicial']) && isset($_GET['data_final'])) {
        $data_inicial = $_GET['data_inicial'];
        $data_final = $_GET['data_final'];
        $sql .= " AND DATE(ap.data_assinatura) BETWEEN :data_inicial AND :data_final";
    }

    $sql .= " ORDER BY ap.data_assinatura DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':posto_id', $posto_id);

    if ($tipo === 'personalizado' && isset($_GET['data_inicial']) && isset($_GET['data_final'])) {
        $stmt->bindParam(':data_inicial', $data_inicial);
        $stmt->bindParam(':data_final', $data_final);
    }

    $stmt->execute();
    $abastecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($abastecimentos)) {
        echo '
        <div class="text-center py-8">
            <i class="fas fa-check-circle text-gray-300 text-4xl mb-3"></i>
            <p class="text-gray-600">Nenhum abastecimento encontrado no período selecionado.</p>
        </div>';
    } else {
        foreach ($abastecimentos as $abastecimento) {
            echo '
            <div class="border border-gray-200 rounded-xl p-5 shadow-soft abastecimento-item relative border-l-4 border-green-500" data-id="'.$abastecimento['id'].'">
                <div class="absolute top-0 right-0 -mt-2 -mr-2 bg-green-500 text-white rounded-full w-6 h-6 flex items-center justify-center">
                    <i class="fas fa-check text-xs"></i>
                </div>
                <div class="space-y-4">
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700">
                                    Abastecimento concluído e assinado por <span class="font-medium">'.$abastecimento['motorista_name'].'</span>
                                    em '.date('d/m/Y H:i', strtotime($abastecimento['data_assinatura'])).'
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Motorista</label>
                            <input type="text" class="w-full bg-transparent focus:outline-none"
                                   value="'.$abastecimento['motorista_name'].'" readonly>
                        </div>

                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Veículo</label>
                            <input type="text" class="w-full bg-transparent focus:outline-none"
                                   value="'.$abastecimento['veiculo_nome'].' - '.$abastecimento['placa'].'" readonly>
                        </div>

                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                            <label class="block text-xs font-medium text-gray-500 mb-1">KM</label>
                            <input type="text" class="w-full bg-transparent focus:outline-none"
                                   value="'.$abastecimento['km_abastecido'].'" readonly>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Litros</label>
                            <input type="text" class="w-full bg-transparent focus:outline-none"
                                   value="'.$abastecimento['litros'].'" readonly>
                        </div>

                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Combustível</label>
                            <input type="text" class="w-full bg-transparent focus:outline-none"
                                   value="'.$abastecimento['combustivel'].'" readonly>
                        </div>

                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valor (R$)</label>
                            <input type="text" class="w-full bg-transparent focus:outline-none"
                                   value="'.number_format($abastecimento['valor'], 2, ',', '.').'" readonly>
                        </div>
                    </div>
                </div>
            </div>';
        }
    }
} catch (PDOException $e) {
    echo '
    <div class="text-center py-8">
        <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-3"></i>
        <p class="text-gray-600">Erro ao carregar abastecimentos: '.$e->getMessage().'</p>
    </div>';
}
?>