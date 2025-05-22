<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'posto') {
    header('Location: ../login.php');
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
    die("Erro de conexão: " . $e->getMessage());
}

// Funções úteis
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatarData($data) {
    return date('d/m/Y', strtotime($data));
}

// Obter informações do usuário (posto)
$user_id = $_SESSION['user_id'];
$nome_posto = $_SESSION['name'];

// Filtro de data (padrão: últimos 30 dias)
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d', strtotime('-30 days'));
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');

// Buscar empenhos totais do posto (status ativo) - apenas valor_total
$stmt = $conn->prepare("
    SELECT SUM(valor_total) as saldo_total
    FROM empenhos_totais
    WHERE fornecedor = :nome_posto AND status = 'ativo'
");
$stmt->bindParam(':nome_posto', $nome_posto);
$stmt->execute();
$empenho_total = $stmt->fetch(PDO::FETCH_ASSOC);

// Buscar valores por combustível da tabela empenhos_secretarias
$stmt = $conn->prepare("
    SELECT
        SUM(valor_etanol) as saldo_etanol,
        SUM(valor_gasolina) as saldo_gasolina,
        SUM(valor_diesel) as saldo_diesel,
        SUM(valor_diesel_s10) as saldo_diesel_s10
    FROM empenhos_secretarias
    WHERE fornecedor = :nome_posto AND status = 'ativo'
");
$stmt->bindParam(':nome_posto', $nome_posto);
$stmt->execute();
$saldos_combustiveis = $stmt->fetch(PDO::FETCH_ASSOC);

// Combinar os resultados
$empenho_total['saldo_etanol'] = $saldos_combustiveis['saldo_etanol'] ?? 0;
$empenho_total['saldo_gasolina'] = $saldos_combustiveis['saldo_gasolina'] ?? 0;
$empenho_total['saldo_diesel'] = $saldos_combustiveis['saldo_diesel'] ?? 0;
$empenho_total['saldo_diesel_s10'] = $saldos_combustiveis['saldo_diesel_s10'] ?? 0;

// Buscar gastos diários por secretaria
$stmt = $conn->prepare("
    SELECT ra.data as data_abastecimento,
           ra.secretaria,
           SUM(ra.valor) as valor_gasto,
           COUNT(ra.id) as num_abastecimentos
    FROM registro_abastecimento ra
    WHERE ra.posto_gasolina = :nome_posto
      AND ra.data BETWEEN :data_inicio AND :data_fim
    GROUP BY ra.data, ra.secretaria
    ORDER BY ra.data DESC, ra.secretaria
");
$stmt->bindParam(':nome_posto', $nome_posto);
$stmt->bindParam(':data_inicio', $data_inicio);
$stmt->bindParam(':data_fim', $data_fim);
$stmt->execute();
$gastos_diarios_secretaria = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar se tabela registro_abastecimento tem colunas específicas para combustíveis
$has_combustivel_columns = false;
try {
    $stmt = $conn->prepare("
        SELECT
            combustivel
        FROM registro_abastecimento
        LIMIT 1
    ");
    $stmt->execute();
    $has_combustivel_columns = true;
} catch (PDOException $e) {
    $has_combustivel_columns = false;
}

// Buscar empenhos por secretaria (status ativo)
$stmt = $conn->prepare("
    SELECT secretaria,
           valor_total,
           valor_etanol,
           valor_gasolina,
           valor_diesel,
           valor_diesel_s10
    FROM empenhos_secretarias
    WHERE fornecedor = :nome_posto AND status = 'ativo'
    ORDER BY valor_total DESC
");
$stmt->bindParam(':nome_posto', $nome_posto);
$stmt->execute();
$empenhos_secretarias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular resumo por secretaria (gastos totais no período)
$query = "
    SELECT ra.secretaria,
           SUM(ra.valor) as total_gasto,
           COUNT(DISTINCT ra.data) as dias_com_gasto
    FROM registro_abastecimento ra
    WHERE ra.posto_gasolina = :nome_posto
      AND ra.data BETWEEN :data_inicio AND :data_fim
    GROUP BY ra.secretaria
    ORDER BY total_gasto DESC
";

// Adicionar colunas de combustíveis se existirem
if ($has_combustivel_columns) {
    $query = "
        SELECT ra.secretaria,
               SUM(ra.valor) as total_gasto,
               SUM(CASE WHEN ra.combustivel = 'Etanol' THEN ra.valor ELSE 0 END) as total_etanol,
               SUM(CASE WHEN ra.combustivel = 'Gasolina' THEN ra.valor ELSE 0 END) as total_gasolina,
               SUM(CASE WHEN ra.combustivel = 'Diesel' THEN ra.valor ELSE 0 END) as total_diesel,
               SUM(CASE WHEN ra.combustivel = 'Diesel S10' THEN ra.valor ELSE 0 END) as total_diesel_s10,
               COUNT(DISTINCT ra.data) as dias_com_gasto
        FROM registro_abastecimento ra
        WHERE ra.posto_gasolina = :nome_posto
          AND ra.data BETWEEN :data_inicio AND :data_fim
        GROUP BY ra.secretaria
        ORDER BY total_gasto DESC
    ";
}

$stmt = $conn->prepare($query);
$stmt->bindParam(':nome_posto', $nome_posto);
$stmt->bindParam(':data_inicio', $data_inicio);
$stmt->bindParam(':data_fim', $data_fim);
$stmt->execute();
$resumo_secretarias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Se não existirem colunas de combustíveis, adicionar valores padrão
if (!$has_combustivel_columns) {
    foreach ($resumo_secretarias as $key => $resumo) {
        $resumo_secretarias[$key]['total_etanol'] = 0;
        $resumo_secretarias[$key]['total_gasolina'] = 0;
        $resumo_secretarias[$key]['total_diesel'] = 0;
        $resumo_secretarias[$key]['total_diesel_s10'] = 0;
    }
}

// Calcular projeções e estimativas para cada secretaria
foreach ($resumo_secretarias as $key => $resumo) {
    $secretaria = $resumo['secretaria'];

    // Buscar saldo atual da secretaria
    $saldo_secretaria = 0;
    $empenho_secretaria = null;

    foreach ($empenhos_secretarias as $empenho) {
        if ($empenho['secretaria'] === $secretaria) {
            $saldo_secretaria = $empenho['valor_total'];
            $empenho_secretaria = $empenho;
            break;
        }
    }

    // Calcular média de gasto diário
    $dias_com_gasto = $resumo['dias_com_gasto'] > 0 ? $resumo['dias_com_gasto'] : 1;
    $total_gasto = $resumo['total_gasto'];
    $media_diaria = $total_gasto / $dias_com_gasto;

    // Estimativa de dias até acabar o saldo
    $dias_restantes = $media_diaria > 0 ? floor($saldo_secretaria / $media_diaria) : 999;

    // Projeção de gastos para os próximos 30, 60 e 90 dias
    $projecao_30_dias = $media_diaria * 30;
    $projecao_60_dias = $media_diaria * 60;
    $projecao_90_dias = $media_diaria * 90;

    // Adicionar informações ao array
    $resumo_secretarias[$key]['saldo_atual'] = $saldo_secretaria;
    $resumo_secretarias[$key]['media_diaria'] = $media_diaria;
    $resumo_secretarias[$key]['dias_restantes'] = $dias_restantes;
    $resumo_secretarias[$key]['projecao_30_dias'] = $projecao_30_dias;
    $resumo_secretarias[$key]['projecao_60_dias'] = $projecao_60_dias;
    $resumo_secretarias[$key]['projecao_90_dias'] = $projecao_90_dias;

    if ($empenho_secretaria) {
        $resumo_secretarias[$key]['valor_etanol_saldo'] = $empenho_secretaria['valor_etanol'];
        $resumo_secretarias[$key]['valor_gasolina_saldo'] = $empenho_secretaria['valor_gasolina'];
        $resumo_secretarias[$key]['valor_diesel_saldo'] = $empenho_secretaria['valor_diesel'];
        $resumo_secretarias[$key]['valor_diesel_s10_saldo'] = $empenho_secretaria['valor_diesel_s10'];
    } else {
        $resumo_secretarias[$key]['valor_etanol_saldo'] = 0;
        $resumo_secretarias[$key]['valor_gasolina_saldo'] = 0;
        $resumo_secretarias[$key]['valor_diesel_saldo'] = 0;
        $resumo_secretarias[$key]['valor_diesel_s10_saldo'] = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Abastecimentos - <?php echo htmlspecialchars($nome_posto); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body, html {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }

        .container {
            width: 100%;
            height: 100vh;
            padding: 20px;
            background-color: #f8fafc;
        }

        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
            min-height: 400px;
        }

        .grafico-container {
            height: 100%;
            width: 100%;
            min-height: 400px;
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .secretaria-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .secretaria-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .secretaria-detalhes {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 40px 60px rgba(0,0,0,0.2);
            width: 94%;
            max-width: 1000px;
            max-height: 97vh;
            overflow-y: auto;
        }

        /* Cores específicas para combustíveis */
        .bg-etanol {
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
        }

        .bg-gasolina {
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
        }

        .bg-diesel {
            background: linear-gradient(135deg, #2196F3 0%, #1565C0 100%);
        }

        .bg-diesel-s10 {
            background: linear-gradient(135deg, #3F51B5 0%, #283593 100%);
        }

        .bg-gradient-primary {
            background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
        }

        .bg-gradient-success {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        }

        .bg-gradient-danger {
            background: linear-gradient(135deg, #EF4444 0%, #B91C1C 100%);
        }

        .bg-gradient-warning {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
        }

        table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }

        table th, table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        table th {
            background-color: #f3f4f6;
            position: sticky;
            top: 0;
            font-weight: 600;
            color: #374151;
        }

        table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        table tr:hover {
            background-color: #f0fdf4;
        }

        .tabs {
            display: flex;
            margin-bottom: 20px;
            gap: 8px;
        }

        .tab-button {
            padding: 10px 20px;
            background-color: #e5e7eb;
            border: none;
            cursor: pointer;
            border-radius: 8px;
            font-weight: 500;
            color: #4b5563;
            transition: all 0.2s ease;
        }

        .tab-button.active {
            background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.3);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .tab-content.active {
            display: block;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .saldo-destaque {
            font-size: 1.5rem;
            font-weight: bold;
            background-color: #e6ffed;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            color: #065f46;
        }

        .saldo-destaque::before {
            content: "R$";
            margin-right: 0.5rem;
            font-size: 1.2rem;
        }

        .saldo-total {
            background-color: #d1fae5;
            color: #065f46;
            font-weight: bold;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        /* Cores específicas para dias restantes */
        .dias-critico {
            color: #ffffff;
            background-color: #ef4444;
        }

        .dias-alerta {
            color: #ffffff;
            background-color: #f59e0b;
        }

        .dias-normal {
            color: #ffffff;
            background-color: #10b981;
        }

        /* Estilo para nota fiscal */
        .nota-fiscal {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .nota-fiscal-header {
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .nota-fiscal-footer {
            border-top: 1px solid #ccc;
            padding-top: 10px;
            margin-top: 15px;
        }

        /* Estilo para filtro de data */
        .filtro-container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Relatório de Abastecimentos</h1>
                <p class="text-gray-600">Posto: <?php echo htmlspecialchars($nome_posto); ?></p>
            </div>
            <a href="../menu_posto.php" class="btn bg-gradient-primary text-white px-6 py-3 rounded-lg shadow hover:shadow-md">
                <i class="fas fa-arrow-left mr-2"></i>Voltar
            </a>
        </div>

        <!-- Filtro de Data -->
        <div class="filtro-container">
            <h2 class="text-lg font-semibold mb-4">Filtrar por Período</h2>
            <form action="" method="GET" class="flex items-center space-x-4">
                <div class="flex items-center">
                    <label for="data_inicio" class="mr-2">De:</label>
                    <input type="date" id="data_inicio" name="data_inicio" value="<?php echo $data_inicio; ?>" class="border rounded px-3 py-1">
                </div>
                <div class="flex items-center">
                    <label for="data_fim" class="mr-2">Até:</label>
                    <input type="date" id="data_fim" name="data_fim" value="<?php echo $data_fim; ?>" class="border rounded px-3 py-1">
                </div>
                <button type="submit" class="bg-blue-500 text-white px-4 py-1 rounded hover:bg-blue-600">Filtrar</button>
            </form>
        </div>

        <!-- Resumo Geral -->
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <h2 class="text-xl font-bold mb-4 text-gray-800">Resumo dos Empenhos</h2>

            <!-- Saldo Total Destacado -->
            <div class="flex flex-col md:flex-row justify-between items-center bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl mb-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Saldo Total de Empenhos</h3>
                    <p class="text-sm text-gray-500">Todos os empenhos ativos</p>
                </div>
                <div class="mt-3 md:mt-0">
                    <span class="text-2xl font-bold text-indigo-700">
                        <?php echo formatarMoeda($empenho_total['saldo_total'] ?? 0); ?>
                    </span>
                </div>
            </div>

            <!-- Distribuição por Combustível -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-etanol text-white p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold">Etanol</span>
                        <span class="font-bold"><?php echo formatarMoeda($empenho_total['saldo_etanol'] ?? 0); ?></span>
                    </div>
                </div>
                <div class="bg-gasolina text-white p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold">Gasolina</span>
                        <span class="font-bold"><?php echo formatarMoeda($empenho_total['saldo_gasolina'] ?? 0); ?></span>
                    </div>
                </div>
                <div class="bg-diesel text-white p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold">Diesel</span>
                        <span class="font-bold"><?php echo formatarMoeda($empenho_total['saldo_diesel'] ?? 0); ?></span>
                    </div>
                </div>
                <div class="bg-diesel-s10 text-white p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold">Diesel S10</span>
                        <span class="font-bold"><?php echo formatarMoeda($empenho_total['saldo_diesel_s10'] ?? 0); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estrutura de abas -->
        <div class="tabs">
            <button class="tab-button active" onclick="mostrarAba('resumo', event)">Resumo Secretarias</button>
            <button class="tab-button" onclick="mostrarAba('diario', event)">Gastos Diários</button>
            <button class="tab-button" onclick="mostrarAba('projecoes', event)">Projeções</button>
            <button class="tab-button" onclick="mostrarAba('nota-fiscal', event)">Nota Fiscal</button>
        </div>

        <!-- Conteúdo das abas -->
        <!-- 1. ABA RESUMO SECRETARIAS -->
        <div id="aba-resumo" class="tab-content active">
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-4 text-gray-800">Resumo por Secretaria</h2>

                <?php if (empty($resumo_secretarias)): ?>
                    <div class="bg-gray-100 p-4 rounded text-center">
                        <p>Nenhum registro de abastecimento encontrado para o período selecionado.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr>
                                    <th>Secretaria</th>
                                    <th>Saldo Atual</th>
                                    <th>Total Gasto</th>
                                    <th>Média Diária</th>
                                    <th>Dias Restantes</th>
                                    <th>Distribuição</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resumo_secretarias as $resumo): ?>
                                    <?php
                                    // Determinar classe para dias restantes
                                    $dias_class = 'dias-normal';
                                    if ($resumo['dias_restantes'] <= 15) {
                                        $dias_class = 'dias-critico';
                                    } elseif ($resumo['dias_restantes'] <= 30) {
                                        $dias_class = 'dias-alerta';
                                    }
                                    ?>
                                    <tr>
                                        <td class="font-medium"><?php echo htmlspecialchars($resumo['secretaria']); ?></td>
                                        <td class="font-bold text-green-700"><?php echo formatarMoeda($resumo['saldo_atual']); ?></td>
                                        <td class="text-red-600 font-medium"><?php echo formatarMoeda($resumo['total_gasto']); ?></td>
                                        <td><?php echo formatarMoeda($resumo['media_diaria']); ?></td>
                                        <td>
                                            <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $dias_class; ?>">
                                                <?php echo $resumo['dias_restantes']; ?> dias
                                            </span>
                                        </td>
                                        <td>
                                            <div class="flex space-x-2">
                                                <?php if ($resumo['total_etanol'] > 0): ?>
                                                <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">
                                                    E: <?php echo formatarMoeda($resumo['total_etanol']); ?>
                                                </span>
                                                <?php endif; ?>

                                                <?php if ($resumo['total_gasolina'] > 0): ?>
                                                <span class="bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full">
                                                    G: <?php echo formatarMoeda($resumo['total_gasolina']); ?>
                                                </span>
                                                <?php endif; ?>

                                                <?php if ($resumo['total_diesel'] > 0): ?>
                                                <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                                    D: <?php echo formatarMoeda($resumo['total_diesel']); ?>
                                                </span>
                                                <?php endif; ?>

                                                <?php if ($resumo['total_diesel_s10'] > 0): ?>
                                                <span class="bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded-full">
                                                    DS10: <?php echo formatarMoeda($resumo['total_diesel_s10']); ?>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Gráfico de resumo -->
                    <div class="chart-container mt-8">
                        <canvas id="graficoResumo"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2. ABA GASTOS DIÁRIOS -->
        <div id="aba-diario" class="tab-content">
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-4 text-gray-800">Gastos Diários por Secretaria</h2>

                <?php if (empty($gastos_diarios_secretaria)): ?>
                    <div class="bg-gray-100 p-4 rounded text-center">
                        <p>Nenhum registro de abastecimento encontrado para o período selecionado.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Secretaria</th>
                                    <th>Valor Total</th>
                                    <th>Número de Abastecimentos</th>
                                    <th>Detalhes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $data_atual = '';
                                foreach ($gastos_diarios_secretaria as $gasto):
                                    $nova_data = $gasto['data_abastecimento'];
                                    $novo_dia = ($data_atual != $nova_data);

                                    if ($novo_dia) {
                                        $data_atual = $nova_data;
                                    }
                                ?>
                                    <tr <?php echo $novo_dia ? 'class="border-t-2 border-indigo-100"' : ''; ?>>
                                        <td><?php echo $novo_dia ? formatarData($gasto['data_abastecimento']) : ''; ?></td>
                                        <td class="font-medium"><?php echo htmlspecialchars($gasto['secretaria']); ?></td>
                                        <td class="font-bold text-red-600"><?php echo formatarMoeda($gasto['valor_gasto']); ?></td>
                                        <td class="text-center"><?php echo $gasto['num_abastecimentos']; ?></td>
                                        <td>
                                            <button onclick="mostrarDetalhesGasto('<?php echo $gasto['data_abastecimento']; ?>', '<?php echo htmlspecialchars($gasto['secretaria']); ?>')"
                                                    class="bg-blue-500 hover:bg-blue-600 text-white text-xs px-2 py-1 rounded">
                                                Ver Detalhes
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Gráfico de gastos diários -->
                    <div class="chart-container mt-8">
                        <canvas id="graficoDiario"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 3. ABA PROJEÇÕES -->
        <div id="aba-projecoes" class="tab-content">
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-4 text-gray-800">Projeções de Gastos</h2>

                <?php if (empty($resumo_secretarias)): ?>
                    <div class="bg-gray-100 p-4 rounded text-center">
                        <p>Nenhum dado disponível para projeções.</p>
                    </div>
                <?php else: ?>
                    <!-- Grid de cards para projeções -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($resumo_secretarias as $resumo): ?>
                            <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                                <div class="px-4 py-5 sm:p-6">
                                    <h3 class="text-lg font-bold text-gray-900 mb-2">
                                        <?php echo htmlspecialchars($resumo['secretaria']); ?>
                                    </h3>

                                    <div class="mt-1 flex justify-between items-center">
                                        <span class="text-sm text-gray-500">Saldo Atual</span>
                                        <span class="font-bold text-green-600"><?php echo formatarMoeda($resumo['saldo_atual']); ?></span>
                                    </div>

                                    <div class="mt-1 flex justify-between items-center">
                                        <span class="text-sm text-gray-500">Média Diária</span>
                                        <span class="font-medium"><?php echo formatarMoeda($resumo['media_diaria']); ?></span>
                                    </div>

                                    <div class="mt-4 pt-4 border-t border-gray-200">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm font-medium">Dias Restantes</span>
                                            <span class="px-3 py-1 rounded-full text-sm font-bold
                                                <?php
                                                if ($resumo['dias_restantes'] <= 15) echo 'dias-critico';
                                                elseif ($resumo['dias_restantes'] <= 30) echo 'dias-alerta';
                                                else echo 'dias-normal';
                                                ?>">
                                                <?php echo $resumo['dias_restantes']; ?> dias
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mt-4 space-y-3">
                                        <div class="flex justify-between items-center bg-gray-50 p-2 rounded">
                                            <span class="text-xs">Projeção 30 dias</span>
                                            <span class="text-sm font-medium"><?php echo formatarMoeda($resumo['projecao_30_dias']); ?></span>
                                        </div>
                                        <div class="flex justify-between items-center bg-gray-50 p-2 rounded">
                                            <span class="text-xs">Projeção 60 dias</span>
                                            <span class="text-sm font-medium"><?php echo formatarMoeda($resumo['projecao_60_dias']); ?></span>
                                        </div>
                                        <div class="flex justify-between items-center bg-gray-50 p-2 rounded">
                                            <span class="text-xs">Projeção 90 dias</span>
                                            <span class="text-sm font-medium"><?php echo formatarMoeda($resumo['projecao_90_dias']); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="px-4 py-4 sm:px-6 bg-gray-50 border-t border-gray-200">
                                    <div class="text-sm">
                                        <?php if ($resumo['dias_restantes'] <= 30): ?>
                                            <div class="font-medium text-red-600">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                Alerta: Saldo será esgotado em breve!
                                            </div>
                                        <?php else: ?>
                                            <div class="font-medium text-green-600">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                Saldo em níveis normais
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Gráfico de projeções -->
                    <div class="chart-container mt-8">
                        <canvas id="graficoProjecoes"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 4. ABA NOTA FISCAL -->
        <div id="aba-nota-fiscal" class="tab-content">
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-4 text-gray-800">Nota Fiscal Diária por Secretaria</h2>

                <div class="mb-4">
                    <label for="data_nota" class="block mb-2 text-sm font-medium text-gray-700">Selecione a data:</label>
                    <input type="date" id="data_nota" name="data_nota" value="<?php echo date('Y-m-d'); ?>"
                           class="border rounded px-3 py-1 mb-2">

                    <label for="secretaria_nota" class="block mb-2 text-sm font-medium text-gray-700">Selecione a secretaria:</label>
                    <select id="secretaria_nota" name="secretaria_nota" class="border rounded px-3 py-1">
                        <option value="">Selecione...</option>
                        <?php
                        $secretarias = array_unique(array_column($gastos_diarios_secretaria, 'secretaria'));
                        foreach ($secretarias as $secretaria) {
                            echo '<option value="' . htmlspecialchars($secretaria) . '">' . htmlspecialchars($secretaria) . '</option>';
                        }
                        ?>
                    </select>

                    <button onclick="gerarNotaFiscal()" class="ml-2 bg-blue-500 text-white px-4 py-1 rounded hover:bg-blue-600">
                        Gerar Nota
                    </button>
                </div>

                <!-- Modelo de Nota Fiscal -->
                <div id="nota_fiscal_container" class="nota-fiscal hidden">
                    <div class="nota-fiscal-header">
                        <div class="flex justify-between">
                            <div>
                                <h3 class="font-bold text-lg"><?php echo htmlspecialchars($nome_posto); ?></h3>
                                <p class="text-sm">CNPJ: XX.XXX.XXX/0001-XX</p>
                                <p class="text-sm">Endereço: Av. Principal, 1000</p>
                            </div>
                            <div>
                                <h3 class="font-bold text-lg">NOTA FISCAL DIÁRIA</h3>
                                <p class="text-sm">Data: <span id="nota_data"></span></p>
                                <p class="text-sm">Nº: <span id="nota_numero">00001</span></p>
                            </div>
                        </div>
                    </div>

                    <div class="my-4">
                        <h4 class="font-bold">DESTINATÁRIO:</h4>
                        <p>Prefeitura Municipal</p>
                        <p>Secretaria: <span id="nota_secretaria"></span></p>
                    </div>

                    <div class="my-4">
                        <h4 class="font-bold mb-2">PRODUTOS/SERVIÇOS:</h4>
                        <table class="min-w-full border">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border p-2">Combustível</th>
                                    <th class="border p-2">Quantidade</th>
                                    <th class="border p-2">Valor Unit.</th>
                                    <th class="border p-2">Valor Total</th>
                                </tr>
                            </thead>
                            <tbody id="nota_itens">
                                <!-- Será preenchido pelo JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end">
                        <div class="text-right">
                            <p class="text-lg font-bold">TOTAL: <span id="nota_total"></span></p>
                        </div>
                    </div>

                    <div class="nota-fiscal-footer mt-8">
                        <p class="text-center text-sm">Documento gerado eletronicamente em <?php echo date('d/m/Y \à\s H:i:s'); ?></p>
                        <p class="text-center text-sm">Este documento não possui valor fiscal</p>
                    </div>
                </div>

                <div id="nota_fiscal_placeholder" class="bg-gray-100 p-4 rounded text-center">
                    <p>Selecione uma data e secretaria para gerar a nota fiscal.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Janela modal para detalhes de gastos -->
    <div id="detalhesGasto" class="secretaria-detalhes">
        <button onclick="fecharDetalhes()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-xl">
            <i class="fas fa-times"></i>
        </button>
        <div id="conteudoDetalhes"></div>
    </div>

    <script>
        // Função para mostrar abas
        function mostrarAba(aba, event) {
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            event.currentTarget.classList.add('active');
            document.getElementById(`aba-${aba}`).classList.add('active');
        }

        // Função para formatar moeda
        const formatarMoeda = (valor) => {
            if (isNaN(valor) || valor === null) {
                return 'R$ 0,00';
            }
            return 'R$ ' + parseFloat(valor).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };

        // Função para formatar data
        const formatarData = (dataStr) => {
            const data = new Date(dataStr);
            return data.toLocaleDateString('pt-BR');
        };

        // Função para mostrar detalhes de gastos
        function mostrarDetalhesGasto(data, secretaria) {
            // Normalmente aqui seria feita uma requisição AJAX para buscar os detalhes
            // Para este exemplo, vamos criar um conteúdo estático

            const detalhesHTML = `
                <div class="mb-6">
                    <div class="text-center">
                        <h2 class="text-2xl font-bold text-gray-800 mb-2">Detalhes de Abastecimento</h2>
                        <p class="text-gray-600">Data: ${formatarData(data)} | Secretaria: ${secretaria}</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th>Veículo</th>
                                <th>Placa</th>
                                <th>Combustível</th>
                                <th>Litros</th>
                                <th>Valor Unit.</th>
                                <th>Valor Total</th>
                            </tr>
                        </thead>
                        <tbody id="tabela-detalhes-gasto">
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    Carregando detalhes...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            `;

            document.getElementById('conteudoDetalhes').innerHTML = detalhesHTML;
            document.getElementById('detalhesGasto').style.display = 'block';

            // Simulação de chamada AJAX
            setTimeout(() => {
                document.getElementById('tabela-detalhes-gasto').innerHTML = `
                    <tr>
                        <td>Fiat Uno</td>
                        <td>ABC-1234</td>
                        <td>Gasolina</td>
                        <td>32,5</td>
                        <td>R$ 5,79</td>
                        <td>R$ 188,18</td>
                    </tr>
                    <tr>
                        <td>Ford Ranger</td>
                        <td>DEF-5678</td>
                        <td>Diesel S10</td>
                        <td>45,2</td>
                        <td>R$ 6,29</td>
                        <td>R$ 284,31</td>
                    </tr>
                    <tr>
                        <td>Volkswagen Gol</td>
                        <td>GHI-9012</td>
                        <td>Etanol</td>
                        <td>28,7</td>
                        <td>R$ 3,89</td>
                        <td>R$ 111,64</td>
                    </tr>
                `;
            }, 500);
        }

        // Função para fechar detalhes
        function fecharDetalhes() {
            document.getElementById('detalhesGasto').style.display = 'none';
        }

        // Função para gerar nota fiscal
        function gerarNotaFiscal() {
            const data = document.getElementById('data_nota').value;
            const secretaria = document.getElementById('secretaria_nota').value;

            if (!data || !secretaria) {
                alert('Por favor, selecione data e secretaria.');
                return;
            }

            document.getElementById('nota_data').textContent = formatarData(data);
            document.getElementById('nota_secretaria').textContent = secretaria;

            // Criar dados fictícios para a nota
            const itensHTML = `
                <tr>
                    <td class="border p-2">Gasolina Comum</td>
                    <td class="border p-2 text-center">145,70 litros</td>
                    <td class="border p-2 text-right">R$ 5,79</td>
                    <td class="border p-2 text-right">R$ 843,60</td>
                </tr>
                <tr>
                    <td class="border p-2">Diesel S10</td>
                    <td class="border p-2 text-center">87,50 litros</td>
                    <td class="border p-2 text-right">R$ 6,29</td>
                    <td class="border p-2 text-right">R$ 550,38</td>
                </tr>
                <tr>
                    <td class="border p-2">Etanol</td>
                    <td class="border p-2 text-center">63,20 litros</td>
                    <td class="border p-2 text-right">R$ 3,89</td>
                    <td class="border p-2 text-right">R$ 245,85</td>
                </tr>
            `;

            document.getElementById('nota_itens').innerHTML = itensHTML;
            document.getElementById('nota_total').textContent = 'R$ 1.639,83';

            document.getElementById('nota_fiscal_placeholder').classList.add('hidden');
            document.getElementById('nota_fiscal_container').classList.remove('hidden');
        }

        // Dados para os gráficos
        const resumoSecretarias = <?php echo json_encode($resumo_secretarias); ?>;

        // Preparar dados para o gráfico de resumo
        const secretariasLabels = resumoSecretarias.map(item => item.secretaria);
        const totalGastoData = resumoSecretarias.map(item => parseFloat(item.total_gasto));
        const saldoAtualData = resumoSecretarias.map(item => parseFloat(item.saldo_atual));

        // Gráfico de resumo
        const ctxResumo = document.getElementById('graficoResumo').getContext('2d');
        new Chart(ctxResumo, {
            type: 'bar',
            data: {
                labels: secretariasLabels,
                datasets: [
                    {
                        label: 'Saldo Atual',
                        data: saldoAtualData,
                        backgroundColor: '#10b981',
                        borderColor: '#059669',
                        borderWidth: 1
                    },
                    {
                        label: 'Total Gasto no Período',
                        data: totalGastoData,
                        backgroundColor: '#ef4444',
                        borderColor: '#b91c1c',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    },
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.raw || 0;
                                return label + ': ' + formatarMoeda(value);
                            }
                        }
                    }
                }
            }
        });

        // Preparar dados para o gráfico de projeções
        const projecao30DiasData = resumoSecretarias.map(item => parseFloat(item.projecao_30_dias));
        const projecao60DiasData = resumoSecretarias.map(item => parseFloat(item.projecao_60_dias));
        const projecao90DiasData = resumoSecretarias.map(item => parseFloat(item.projecao_90_dias));

        // Gráfico de projeções
        const ctxProjecoes = document.getElementById('graficoProjecoes').getContext('2d');
        new Chart(ctxProjecoes, {
            type: 'bar',
            data: {
                labels: secretariasLabels,
                datasets: [
                    {
                        label: 'Projeção 30 dias',
                        data: projecao30DiasData,
                        backgroundColor: '#3b82f6',
                        borderColor: '#2563eb',
                        borderWidth: 1
                    },
                    {
                        label: 'Projeção 60 dias',
                        data: projecao60DiasData,
                        backgroundColor: '#8b5cf6',
                        borderColor: '#7c3aed',
                        borderWidth: 1
                    },
                    {
                        label: 'Projeção 90 dias',
                        data: projecao90DiasData,
                        backgroundColor: '#ec4899',
                        borderColor: '#db2777',
                        borderWidth: 1
                    },
                    {
                        label: 'Saldo Atual',
                        data: saldoAtualData,
                        backgroundColor: '#10b981',
                        borderColor: '#059669',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    },
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.raw || 0;
                                return label + ': ' + formatarMoeda(value);
                            }
                        }
                    }
                }
            }
        });

        // Preparar dados para o gráfico diário
        const gastosData = <?php echo json_encode($gastos_diarios_secretaria); ?>;
        const datasUnicas = [...new Set(gastosData.map(item => item.data_abastecimento))];
        const secretariasUnicas = [...new Set(gastosData.map(item => item.secretaria))];

        const dadosPorData = {};
        datasUnicas.forEach(data => {
            dadosPorData[data] = {};
            secretariasUnicas.forEach(secretaria => {
                dadosPorData[data][secretaria] = 0;
            });

            gastosData.forEach(gasto => {
                if (gasto.data_abastecimento === data) {
                    dadosPorData[data][gasto.secretaria] = parseFloat(gasto.valor_gasto);
                }
            });
        });

        const datasetsDiario = secretariasUnicas.map((secretaria, index) => {
            // Gerar cores diferentes para cada secretaria
            const r = Math.floor(Math.random() * 200) + 55;
            const g = Math.floor(Math.random() * 200) + 55;
            const b = Math.floor(Math.random() * 200) + 55;
            const color = `rgba(${r}, ${g}, ${b}, 0.7)`;
            const borderColor = `rgba(${r}, ${g}, ${b}, 1)`;

            return {
                label: secretaria,
                data: datasUnicas.map(data => dadosPorData[data][secretaria]),
                backgroundColor: color,
                borderColor: borderColor,
                borderWidth: 1
            };
        });

        // Formatar datas para exibição
        const datasFormatadas = datasUnicas.map(data => formatarData(data));

        // Gráfico de gastos diários
        const ctxDiario = document.getElementById('graficoDiario').getContext('2d');
        new Chart(ctxDiario, {
            type: 'bar',
            data: {
                labels: datasFormatadas,
                datasets: datasetsDiario
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        stacked: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    },
                    x: {
                        stacked: true
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.raw || 0;
                                return label + ': ' + formatarMoeda(value);
                            }
                        }
                    }
                }
            }
        });

        // Fechar modais ao clicar fora
        document.addEventListener('click', function(e) {
            const detalhes = document.getElementById('detalhesGasto');
            if (detalhes.style.display === 'block' && !detalhes.contains(e.target)) {
                fecharDetalhes();
            }
        });
    </script>
</body>
</html>
