<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'geraladm') {
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

// Função para buscar o nome do usuário no banco de dados
function buscarNomeUsuario($conn, $userId) {
    try {
        $stmt = $conn->prepare("SELECT name FROM usuarios WHERE id = :id");
        $stmt->bindParam(':id', $userId);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['name'];
        }
        return 'Usuário desconhecido';
    } catch (PDOException $e) {
        return 'Usuário desconhecido';
    }
}

// Verificar e obter o nome do usuário
if (!isset($_SESSION['name'])) {
    if (isset($_SESSION['user_id'])) {
        // Buscar o nome do usuário no banco de dados
        $_SESSION['name'] = buscarNomeUsuario($conn, $_SESSION['user_id']);
    } else {
        die("Nome do usuário não encontrado na sessão");
    }
}

$stmt = $conn->query("
    SELECT
        es.secretaria,
        es.valor_total as saldo_disponivel,
        es.valor_etanol,
        es.valor_gasolina,
        es.valor_diesel,
        es.valor_diesel_s10
    FROM empenhos_secretarias es
    ORDER BY es.valor_total DESC
");
$secretarias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtFornecedores = $conn->query("
    SELECT
        es.fornecedor,
        SUM(es.valor_total) as saldo_total,
        SUM(es.valor_etanol) as valor_etanol,
        SUM(es.valor_gasolina) as valor_gasolina,
        SUM(es.valor_diesel) as valor_diesel,
        SUM(es.valor_diesel_s10) as valor_diesel_s10
    FROM empenhos_secretarias es
    GROUP BY es.fornecedor
    ORDER BY saldo_total DESC
");
$fornecedores = $stmtFornecedores->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distribuição de Combustíveis</title>
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
            height: calc(100vh - 350px);
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

        .secretaria-card, .fornecedor-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .secretaria-card:hover, .fornecedor-card:hover {
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

        .fornecedor-bar {
            height: 28px;
            border-radius: 8px;
            margin-bottom: 12px;
            position: relative;
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .fornecedor-bar:hover {
            transform: scaleX(1.02);
        }

        .fornecedor-label {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
            font-size: 0.9rem;
        }

        .fornecedor-value {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
            font-size: 0.9rem;
        }

        .combustivel-bar {
            height: 58px;
            border-radius: 8px;
            margin-bottom: 12px;
            position: relative;
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .combustivel-bar:hover {
            transform: scaleX(1.02);
        }

        .combustivel-label {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
            font-size: 1.3rem;
        }

        .combustivel-value {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
            font-size: 1.3rem;
        }

        .combustivel-color {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 6px;
            margin-right: 10px;
            vertical-align: middle;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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

        /* Adicione no seu bloco de estilo */
        .saldo-destaque {
            font-size: 1.5rem;
            font-weight: bold;
            background-color: #e6ffed;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            color: #065f46; /* verde escuro */
        }

        .saldo-destaque::before {
            content: "R$";
            margin-right: 0.5rem;
            font-size: 1.2rem;
        }

        .saldo-total {
            background-color: #d1fae5; /* verde claro */
            color: #065f46; /* verde escuro */
            font-weight: bold;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        /* Cores específicas para combustíveis nas colunas */
        .col-etanol {
            background-color: rgba(76, 175, 80, 0.1);
            border-left: 4px solid #4CAF50;
        }

        .col-gasolina {
            background-color: rgba(255, 152, 0, 0.1);
            border-left: 4px solid #FF9800;
        }

        .col-diesel {
            background-color: rgba(33, 150, 243, 0.1);
            border-left: 4px solid #2196F3;
        }

        .col-diesel-s10 {
            background-color: rgba(63, 81, 181, 0.1);
            border-left: 4px solid #3F51B5;
        }

        /* Estilos para a tabela de secretarias */
        .tabela-secretarias {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .tabela-secretarias th {
            background-color: #4F46E5;
            color: white;
            font-weight: 500;
            padding: 12px 16px;
        }

        .tabela-secretarias td {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .tabela-secretarias tr:last-child td {
            border-bottom: none;
        }

        .tabela-secretarias tr:hover {
            background-color: #f0f9ff;
        }

        /* Melhorias para as células de combustível */
        .celula-combustivel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 500;
            color: white;
            text-shadow: 0 1px 1px rgba(0,0,0,0.2);
        }

        /* Cores mais vibrantes para os combustíveis */
        .celula-etanol {
            background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
        }

        .celula-gasolina {
            background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);
        }

        .celula-diesel {
            background: linear-gradient(135deg, #2196F3 0%, #1565C0 100%);
        }

        .celula-diesel-s10 {
            background: linear-gradient(135deg, #3F51B5 0%, #283593 100%);
        }

        /* Estilo para o cabeçalho da tabela */
        .tabela-cabecalho {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        /* Estilo para o saldo total - similar aos combustíveis mas com cor diferente */
        .bg-saldo-total {
            background: linear-gradient(135deg, #6B7280 0%, #4B5563 100%);
        }

        /* Ajuste de largura para os containers */
        .fornecedor-container {
            min-width: 600px; /* Largura mínima maior */
        }

        /* Ajuste de largura para as colunas da tabela */
        .tabela-secretarias th:nth-child(1),
        .tabela-secretarias td:nth-child(1) {
            width: 30%; /* Menos espaço para o nome da secretaria */
        }

        .tabela-secretarias th:nth-child(3),
        .tabela-secretarias td:nth-child(3) {
            width: 50%; /* Mais espaço para os combustíveis */
        }

        /* Estilo para o total geral */
        .total-geral {
            background-color: #e6ffed;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-weight: bold;
            color: #065f46;
            border-left: 4px solid #10B981;
        }

        /* 1. Para destacar o campo "Distribuição por Combustível" na parte Secretarias */
        .text-lg.font-semibold.mb-4.text-gray-700.border-b.pb-2:contains("Distribuição por Combustível") {
            font-size: 1.8rem !important; /* Bem maior */
            font-weight: 700 !important;
            color: #065f46 !important; /* Verde mais escuro */
            text-align: center !important;
            padding: 12px 0 !important;
            margin-bottom: 16px !important;
            border-bottom: 3px solid #10B981 !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
        }

        /* 2. Para o valor abaixo do nome da secretaria no container com o estilo do "Saldo Total" */
        .saldo-destaque {
            font-size: 2.2rem !important; /* Aumentado para mais destaque */
            font-weight: 800 !important;
            background-color: #d1fae5 !important; /* Verde claro como Saldo Total */
            color: #065f46 !important; /* Verde escuro */
            padding: 1rem 1.5rem !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: auto !important;
            min-width: 200px !important;
        }

        /* 3. Para o campo "Total Geral:" com a mesma cor do "Saldo Total" */
        .total-geral {
            background-color: #d1fae5 !important; /* Verde claro como Saldo Total */
            color: #065f46 !important; /* Verde escuro */
            font-size: 1.2rem !important;
            font-weight: 700 !important;
            padding: 0.75rem 1.5rem !important;
            border-radius: 10px !important;
            border-left: 4px solid #10B981 !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
        }

        /* 4. Para o "Saldo Total" na tabela do fornecedor com estilo do mini container de combustível mas maior */
        .bg-saldo-total {
            background: linear-gradient(135deg, #34D399 0%, #059669 100%) !important; /* Novo tom de verde */
            border-radius: 10px !important;
            font-size: 1.4rem !important;
            font-weight: 700 !important;
            padding: 1rem !important;
            margin: 10px 0 !important;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1) !important;
        }

        /* Ajuste para combustível destacado */
        h3:contains("Distribuição por Combustível") {
            font-size: 1.8rem !important;
            color: #065f46 !important;
            text-align: center !important;
            border-bottom: 3px solid #10B981 !important;
            padding-bottom: 12px !important;
            margin-bottom: 20px !important;
            font-weight: 700 !important;
        }

        /* Estilo para a célula de saldo total na tabela */
        .tabela-secretarias td:nth-child(2) {
            padding: 0 !important;
        }

        .tabela-secretarias td:nth-child(2) > div {
            background: linear-gradient(135deg, #34D399 0%, #059669 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            margin: 0.25rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Distribuição de Combustíveis</h1>
                <p class="text-gray-600">Visão geral dos saldos disponíveis por secretaria e fornecedor</p>
            </div>
            <a href="cadastro_combustivel.php" class="btn bg-gradient-primary text-white px-6 py-3 rounded-lg shadow hover:shadow-md">
                <i class="fas fa-arrow-left mr-2"></i>Voltar
            </a>
        </div>

        <!-- Adicione uma estrutura de abas no HTML para separar as visualizações -->
        <div class="tabs">
            <button class="tab-button active" onclick="mostrarAba('secretarias', event)">Secretarias</button>
            <button class="tab-button" onclick="mostrarAba('fornecedores', event)">Fornecedores</button>
        </div>

        <!-- Conteúdo das abas -->
        <div id="aba-secretarias" class="tab-content active">
            <div class="bg-white p-6 rounded-lg shadow h-full">
                <h2 class="text-xl font-bold mb-4 text-gray-800">Por Secretaria</h2>
                <div class="grafico-container">
                    <canvas id="graficoSecretarias"></canvas>
                </div>
            </div>
        </div>

        <div id="aba-fornecedores" class="tab-content">
            <div class="bg-white p-6 rounded-lg shadow h-full fornecedor-container">
                <h2 class="text-xl font-bold mb-4 text-gray-800">Por Posto (Fornecedor)</h2>
                <div class="grafico-container">
                    <canvas id="graficoFornecedores"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div id="detalhesSecretaria" class="secretaria-detalhes">
        <button onclick="fecharDetalhes()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-xl">
            <i class="fas fa-times"></i>
        </button>
        <div id="conteudoDetalhes"></div>
    </div>

    <script>
        // Ignorar erro do WebSocket (Live Server do VSCode)
        if (window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost') {
            window.addEventListener('error', function(e) {
                if (e.message.includes('WebSocket') && e.message.includes('127.0.0.1.5500')) {
                    e.preventDefault();
                    console.log('Ignorando erro do WebSocket do Live Server');
                }
            });
        }

        // Adicione o script para alternar as abas
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

        // Dados das secretarias
        const secretariasData = <?php echo json_encode(array_column($secretarias, 'secretaria')); ?>;
        const saldosData = <?php echo json_encode(array_column($secretarias, 'saldo_disponivel')); ?>;
        const etanolData = <?php echo json_encode(array_column($secretarias, 'valor_etanol')); ?>;
        const gasolinaData = <?php echo json_encode(array_column($secretarias, 'valor_gasolina')); ?>;
        const dieselData = <?php echo json_encode(array_column($secretarias, 'valor_diesel')); ?>;
        const dieselS10Data = <?php echo json_encode(array_column($secretarias, 'valor_diesel_s10')); ?>;

        // Dados dos fornecedores
        const fornecedoresData = <?php echo json_encode(array_column($fornecedores, 'fornecedor')); ?>;
        const fornecedoresSaldos = <?php echo json_encode(array_column($fornecedores, 'saldo_total')); ?>;
        const fornecedoresEtanol = <?php echo json_encode(array_column($fornecedores, 'valor_etanol')); ?>;
        const fornecedoresGasolina = <?php echo json_encode(array_column($fornecedores, 'valor_gasolina')); ?>;
        const fornecedoresDiesel = <?php echo json_encode(array_column($fornecedores, 'valor_diesel')); ?>;
        const fornecedoresDieselS10 = <?php echo json_encode(array_column($fornecedores, 'valor_diesel_s10')); ?>;

        // Cores para os combustíveis - versão mais vibrante
        const combustivelColors = {
            etanol: '#4CAF50',
            gasolina: '#FF9800',
            diesel: '#2196F3',
            diesel_s10: '#3F51B5'
        };

        // Cores para os gráficos principais
        const chartColors = {
            secretarias: {
                background: 'rgba(16, 185, 129, 0.7)', // verde
                border: 'rgba(5, 150, 105, 1)', // verde mais escuro
                hover: 'rgba(16, 185, 129, 1)'
            },
            fornecedores: {
                background: 'rgba(99, 102, 241, 0.7)', // roxo
                border: 'rgba(79, 70, 229, 1)', // roxo mais escuro
                hover: 'rgba(99, 102, 241, 1)'
            }
        };

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

        // Gráfico de secretarias - configuração atualizada
        const ctxSecretarias = document.getElementById('graficoSecretarias').getContext('2d');
        const chartSecretarias = new Chart(ctxSecretarias, {
            type: 'bar',
            data: {
                labels: secretariasData,
                datasets: [{
                    label: 'Saldo Disponível',
                    data: saldosData,
                    backgroundColor: chartColors.secretarias.background,
                    borderColor: chartColors.secretarias.border,
                    borderWidth: 1,
                    hoverBackgroundColor: chartColors.secretarias.hover
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.raw || 0;
                                return `${label}: ${formatarMoeda(value)}`;
                            },
                            afterLabel: function(context) {
                                const datasetIndex = context.datasetIndex;
                                const dataIndex = context.dataIndex;
                                let additionalInfo = '';

                                if (context.chart.canvas.id === 'graficoSecretarias') {
                                    additionalInfo = [
                                        `Etanol: ${formatarMoeda(etanolData[dataIndex])}`,
                                        `Gasolina: ${formatarMoeda(gasolinaData[dataIndex])}`,
                                        `Diesel: ${formatarMoeda(dieselData[dataIndex])}`,
                                        `Diesel S10: ${formatarMoeda(dieselS10Data[dataIndex])}`
                                    ].join('\n');
                                } else if (context.chart.canvas.id === 'graficoFornecedores') {
                                    additionalInfo = [
                                        `Etanol: ${formatarMoeda(fornecedoresEtanol[dataIndex])}`,
                                        `Gasolina: ${formatarMoeda(fornecedoresGasolina[dataIndex])}`,
                                        `Diesel: ${formatarMoeda(fornecedoresDiesel[dataIndex])}`,
                                        `Diesel S10: ${formatarMoeda(fornecedoresDieselS10[dataIndex])}`
                                    ].join('\n');
                                }

                                return additionalInfo;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            display: false
                        },
                        ticks: {
                            callback: function(value) {
                                return formatarMoeda(value);
                            }
                        }
                    },
                    y: {
                        position: 'left', // Garante que o eixo esteja alinhado à esquerda
                        grid: {
                            display: false
                        },
                        ticks: {
                            autoSkip: false,
                            maxRotation: 0,
                            align: 'left', // Alinha o texto à esquerda
                            color: '#000000', 
                            font: {
                                size: 14, 
                            },
                            callback: function(value) {
                                if (this.getLabelForValue(value)) {
                                    const label = this.getLabelForValue(value);
                                    if (label.length > 45) {
                                        return label.substring(0, 42) + '...';
                                    }
                                    return label;
                                }
                                return '';
                            }
                        }
                    }
                },
                elements: {
                    bar: {
                        borderWidth: 1,
                        borderRadius: 6,
                        barPercentage: 0.6, // Barras mais estreitas
                        categoryPercentage: 0.8
                    }
                },
                onClick: (e, activeEls) => {
                    if (activeEls.length > 0) {
                        const index = activeEls[0].index;
                        mostrarDetalhesSecretaria(index);
                    }
                }
            }
        });

        // Gráfico de fornecedores - configuração atualizada
        const ctxFornecedores = document.getElementById('graficoFornecedores').getContext('2d');
        const chartFornecedores = new Chart(ctxFornecedores, {
            type: 'bar',
            data: {
                labels: fornecedoresData,
                datasets: [{
                    label: 'Saldo Disponível',
                    data: fornecedoresSaldos,
                    backgroundColor: chartColors.fornecedores.background,
                    borderColor: chartColors.fornecedores.border,
                    borderWidth: 1,
                    hoverBackgroundColor: chartColors.fornecedores.hover
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.raw || 0;
                                return `${label}: ${formatarMoeda(value)}`;
                            },
                            afterLabel: function(context) {
                                const datasetIndex = context.datasetIndex;
                                const dataIndex = context.dataIndex;
                                let additionalInfo = '';

                                if (context.chart.canvas.id === 'graficoSecretarias') {
                                    additionalInfo = [
                                        `Etanol: ${formatarMoeda(etanolData[dataIndex])}`,
                                        `Gasolina: ${formatarMoeda(gasolinaData[dataIndex])}`,
                                        `Diesel: ${formatarMoeda(dieselData[dataIndex])}`,
                                        `Diesel S10: ${formatarMoeda(dieselS10Data[dataIndex])}`
                                    ].join('\n');
                                } else if (context.chart.canvas.id === 'graficoFornecedores') {
                                    additionalInfo = [
                                        `Etanol: ${formatarMoeda(fornecedoresEtanol[dataIndex])}`,
                                        `Gasolina: ${formatarMoeda(fornecedoresGasolina[dataIndex])}`,
                                        `Diesel: ${formatarMoeda(fornecedoresDiesel[dataIndex])}`,
                                        `Diesel S10: ${formatarMoeda(fornecedoresDieselS10[dataIndex])}`
                                    ].join('\n');
                                }

                                return additionalInfo;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            display: false
                        },
                        ticks: {
                            callback: function(value) {
                                return formatarMoeda(value);
                            }
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            autoSkip: false,
                            maxRotation: 0,
                            align: 'start', 
                            color: '#000000', 
                            font: {
                                size: 14, 
                            },
                            callback: function(value) {
                                if (this.getLabelForValue(value)) {
                                    const label = this.getLabelForValue(value);
                                    if (label.length > 45) {
                                        return label.substring(0, 42) + '...';
                                    }
                                    return label;
                                }
                                return '';
                            }
                        }
                    }
                },
                elements: {
                    bar: {
                        borderWidth: 1,
                        borderRadius: 6,
                        barPercentage: 0.6, // Barras mais estreitas
                        categoryPercentage: 0.8
                    }
                },
                onClick: (e, activeEls) => {
                    if (activeEls.length > 0) {
                        const index = activeEls[0].index;
                        mostrarDetalhesFornecedor(index);
                    }
                }
            }
        });

        // Mostrar detalhes da secretaria selecionada
        function mostrarDetalhesSecretaria(index) {
            const secretaria = secretariasData[index];
            const saldoDisponivel = saldosData[index];
            const etanol = etanolData[index];
            const gasolina = gasolinaData[index];
            const diesel = dieselData[index];
            const dieselS10 = dieselS10Data[index];

            const detalhesHTML = `
                <div class="mb-6">
                    <div class="text-center">
                        <h2 class="text-2xl font-bold text-gray-800 mb-2">${secretaria}</h2>
                        <div class="combustivel-bar bg-saldo-total">
                            <span class="combustivel-label">Saldo Total</span>
                            <span class="combustivel-value">${formatarMoeda(saldoDisponivel)}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="text-lg font-semibold mb-4 text-gray-700 border-b pb-2">Distribuição por Combustível</h3>
                        <div class="space-y-4">
                            <div class="combustivel-bar bg-etanol">
                                <span class="combustivel-label">Etanol</span>
                                <span class="combustivel-value">${formatarMoeda(etanol)}</span>
                            </div>

                            <div class="combustivel-bar bg-gasolina">
                                <span class="combustivel-label">Gasolina</span>
                                <span class="combustivel-value">${formatarMoeda(gasolina)}</span>
                            </div>

                            <div class="combustivel-bar bg-diesel">
                                <span class="combustivel-label">Diesel</span>
                                <span class="combustivel-value">${formatarMoeda(diesel)}</span>
                            </div>

                            <div class="combustivel-bar bg-diesel-s10">
                                <span class="combustivel-label">Diesel S10</span>
                                <span class="combustivel-value">${formatarMoeda(dieselS10)}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold mb-4 text-gray-700 border-b pb-2">Resumo Financeiro</h3>
                        <div class="bg-gray-50 p-4 rounded-lg space-y-3 border border-gray-200">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <div class="flex items-center">
                                    <span class="combustivel-color bg-etanol"></span>
                                    <span>Etanol</span>
                                </div>
                                <span class="font-medium">${formatarMoeda(etanol)}</span>
                            </div>

                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <div class="flex items-center">
                                    <span class="combustivel-color bg-gasolina"></span>
                                    <span>Gasolina</span>
                                </div>
                                <span class="font-medium">${formatarMoeda(gasolina)}</span>
                            </div>

                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <div class="flex items-center">
                                    <span class="combustivel-color bg-diesel"></span>
                                    <span>Diesel</span>
                                </div>
                                <span class="font-medium">${formatarMoeda(diesel)}</span>
                            </div>

                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <div class="flex items-center">
                                    <span class="combustivel-color bg-diesel-s10"></span>
                                    <span>Diesel S10</span>
                                </div>
                                <span class="font-medium">${formatarMoeda(dieselS10)}</span>
                            </div>

                            <div class="flex justify-between items-center pt-3">
                                <span class="font-bold text-gray-800">Total Geral:</span>
                                <span class="total-geral">${formatarMoeda(saldoDisponivel)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('conteudoDetalhes').innerHTML = detalhesHTML;
            document.getElementById('detalhesSecretaria').style.display = 'block';
        }

        // Função para mostrar detalhes do fornecedor
        function mostrarDetalhesFornecedor(index) {
            const fornecedor = fornecedoresData[index];
            const saldoTotal = fornecedoresSaldos[index];
            const etanol = fornecedoresEtanol[index];
            const gasolina = fornecedoresGasolina[index];
            const diesel = fornecedoresDiesel[index];
            const dieselS10 = fornecedoresDieselS10[index];

            const detalhesHTML = `
                <div class="mb-6">
                    <div class="text-center">
                        <h2 class="text-2xl font-bold text-gray-800 mb-2">${fornecedor}</h2>
                        <div class="combustivel-bar bg-saldo-total">
                            <span class="combustivel-label">Saldo Total</span>
                            <span class="combustivel-value">${formatarMoeda(saldoTotal)}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="text-lg font-semibold mb-4 text-gray-700 border-b pb-2">Distribuição por Combustível</h3>
                        <div class="space-y-4">
                            <div class="combustivel-bar bg-etanol">
                                <span class="combustivel-label">Etanol</span>
                                <span class="combustivel-value">${formatarMoeda(etanol)}</span>
                            </div>

                            <div class="combustivel-bar bg-gasolina">
                                <span class="combustivel-label">Gasolina</span>
                                <span class="combustivel-value">${formatarMoeda(gasolina)}</span>
                            </div>

                            <div class="combustivel-bar bg-diesel">
                                <span class="combustivel-label">Diesel</span>
                                <span class="combustivel-value">${formatarMoeda(diesel)}</span>
                            </div>

                            <div class="combustivel-bar bg-diesel-s10">
                                <span class="combustivel-label">Diesel S10</span>
                                <span class="combustivel-value">${formatarMoeda(dieselS10)}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold mb-4 text-gray-700 border-b pb-2">Resumo Financeiro</h3>
                        <div class="bg-gray-50 p-4 rounded-lg space-y-3 border border-gray-200">
                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <div class="flex items-center">
                                    <span class="combustivel-color bg-etanol"></span>
                                    <span>Etanol</span>
                                </div>
                                <span class="font-medium">${formatarMoeda(etanol)}</span>
                            </div>

                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <div class="flex items-center">
                                    <span class="combustivel-color bg-gasolina"></span>
                                    <span>Gasolina</span>
                                </div>
                                <span class="font-medium">${formatarMoeda(gasolina)}</span>
                            </div>

                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <div class="flex items-center">
                                    <span class="combustivel-color bg-diesel"></span>
                                    <span>Diesel</span>
                                </div>
                                <span class="font-medium">${formatarMoeda(diesel)}</span>
                            </div>

                            <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                <div class="flex items-center">
                                    <span class="combustivel-color bg-diesel-s10"></span>
                                    <span>Diesel S10</span>
                                </div>
                                <span class="font-medium">${formatarMoeda(dieselS10)}</span>
                            </div>

                            <div class="flex justify-between items-center pt-3">
                                <span class="font-bold text-gray-800">Total Geral:</span>
                                <span class="total-geral">${formatarMoeda(saldoTotal)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <h3 class="text-lg font-semibold mb-3 mt-6">Secretarias Associadas</h3>
                <div class="overflow-x-auto tabela-secretarias">
                    <table class="min-w-full bg-white">
                        <thead class="tabela-cabecalho">
                            <tr>
                                <th class="text-left">Secretaria</th>
                                <th class="text-right">Saldo Total</th>
                                <th class="text-center">Combustíveis</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaSecretariasFornecedor">
                            <!-- Será preenchido via AJAX -->
                        </tbody>
                    </table>
                </div>
            `;

            document.getElementById('conteudoDetalhes').innerHTML = detalhesHTML;

            // Carregar secretarias associadas ao fornecedor via AJAX
            fetch(`buscar_secretarias_fornecedor.php?fornecedor=${encodeURIComponent(fornecedor)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro na requisição');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }

                    const tabela = document.getElementById('tabelaSecretariasFornecedor');
                    if (data.length === 0) {
                        tabela.innerHTML = '<tr><td colspan="3" class="py-3 px-4 text-center">Nenhuma secretaria associada encontrada</td></tr>';
                    } else {
                        tabela.innerHTML = data.map(secretaria => `
                            <tr class="border-b hover:bg-blue-50">
                                <td class="py-3 px-4 font-medium text-gray-800">
                                    ${secretaria.secretaria}
                                </td>
                                <td class="py-3 px-4 text-right font-bold">
                                    <div>${formatarMoeda(parseFloat(secretaria.valor_total))}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="celula-combustivel celula-etanol">
                                            <span>Etanol</span>
                                            <span>${formatarMoeda(parseFloat(secretaria.valor_etanol))}</span>
                                        </div>
                                        <div class="celula-combustivel celula-gasolina">
                                            <span>Gasolina</span>
                                            <span>${formatarMoeda(parseFloat(secretaria.valor_gasolina))}</span>
                                        </div>
                                        <div class="celula-combustivel celula-diesel">
                                            <span>Diesel</span>
                                            <span>${formatarMoeda(parseFloat(secretaria.valor_diesel))}</span>
                                        </div>
                                        <div class="celula-combustivel celula-diesel-s10">
                                            <span>Diesel S10</span>
                                            <span>${formatarMoeda(parseFloat(secretaria.valor_diesel_s10))}</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        `).join('');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    const tabela = document.getElementById('tabelaSecretariasFornecedor');
                    tabela.innerHTML = `<tr><td colspan="3" class="py-3 px-4 text-center text-red-500">Erro ao carregar secretarias: ${error.message}</td></tr>`;
                });

            document.getElementById('detalhesSecretaria').style.display = 'block';
        }

        // Função auxiliar para determinar a cor do combustível predominante
        function getCombustivelColorClass(secretaria) {
            const valores = {
                'bg-etanol': parseFloat(secretaria.valor_etanol),
                'bg-gasolina': parseFloat(secretaria.valor_gasolina),
                'bg-diesel': parseFloat(secretaria.valor_diesel),
                'bg-diesel-s10': parseFloat(secretaria.valor_diesel_s10)
            };

            const maxCombustivel = Object.keys(valores).reduce((a, b) => valores[a] > valores[b] ? a : b);
            return valores[maxCombustivel] > 0 ? maxCombustivel : 'bg-gray-300';
        }

        function fecharDetalhes() {
            document.getElementById('detalhesSecretaria').style.display = 'none';
        }

        // Fechar ao clicar fora
        document.addEventListener('click', function(e) {
            const detalhes = document.getElementById('detalhesSecretaria');
            if (detalhes.style.display === 'block' && !detalhes.contains(e.target)) {
                fecharDetalhes();
            }
        });

        // Atualize as configurações dos gráficos para incluir o filtro de nomes longos
        const chartOptions = {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            aspectRatio: 0.6, // Reduz o tamanho dos gráficos
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Saldo Disponível: R$ ' + context.raw.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        display: false
                    },
                    ticks: {
                        callback: function(value) {
                            return formatarMoeda(value);
                        }
                    }
                },
                y: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        callback: function(value) {
                          
                            if (value.length > 45) {
                                return value.substring(0, 42) + '...';
                            }
                            return value;
                        },
                        autoSkip: false,
                        maxRotation: 0,
                        font: {
                            size: 20
                        }
                    }
                }
            },
            elements: {
                bar: {
                    borderWidth: 1,
                    borderRadius: 6,
                    barPercentage: 0.6,
                    categoryPercentage: 0.8
                }
            },
            onClick: (e, activeEls) => {
                if (activeEls.length > 0) {
                    const index = activeEls[0].index;
                    if (this.config.type === 'bar' && this.config.data.labels[index]) {
                        if (this.canvas.id === 'graficoSecretarias') {
                            mostrarDetalhesSecretaria(index);
                        } else if (this.canvas.id === 'graficoFornecedores') {
                            mostrarDetalhesFornecedor(index);
                        }
                    }
                }
            }
        };
    </script>
</body>
</html>
