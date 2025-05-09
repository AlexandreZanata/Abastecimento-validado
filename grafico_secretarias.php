<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'geraladm') {
    header('Location: /login.php');
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

// Mapeamento de secretarias
$secretarias_map = [
    "Gabinete do Prefeito" => "GABINETE DO PREFEITO",
    "Gabinete do Vice-Prefeito" => "GABINETE DO VICE-PREFEITO",
    "Secretaria Municipal da Mulher de Família" => "SECRETARIA DA MULHER",
    "Secretaria Municipal de Fazenda" => "SECRETARIA DE FAZENDA",
    "Secretaria Municipal de Educação" => "SECRETARIA DE EDUCAÇÃO",
    "Secretaria Municipal de Agricultura e Meio Ambiente" => "SECRETARIA DE AGRICULTURA E MEIO AMBIENTE",
    "Secretaria Municipal de Agricultura Familiar e Segurança Alimentar" => "SECRETARIA DE AGRICULTURA FAMILIAR",
    "Secretaria Municipal de Assistência Social" => "SECRETARIA DE ASSISTÊNCIA SOCIAL",
    "Secretaria Municipal de Desenvolvimento Econômico e Turismo" => "SECRETARIA DE DESENV. ECONÔMICO",
    "Secretaria Municipal de Administração" => "SECRETARIA DE ADMINISTRAÇÃO",
    "Secretaria Municipal de Governo" => "SECRETARIA DE GOVERNO",
    "Secretaria Municipal de Infraestrutura, Transportes e Saneamento" => "SECRETARIA DE INFRAESTRUTURA, TRANSPORTE E SANEAMENTO",
    "Secretaria Municipal de Esporte e Lazer e Juventude" => "SECRETARIA DE ESPORTE E LAZER",
    "Secretaria Municipal da Cidade" => "SECRETARIA DA CIDADE",
    "Secretaria Municipal de Saúde" => "SECRETARIA DE SAÚDE",
    "Secretaria Municipal de Segurança Pública, Trânsito e Defesa Civil" => "SECRETARIA DE SEGURANÇA PÚBLICA",
    "Controladoria Geral do Município" => "CONTROLADORIA GERAL",
    "Procuradoria Geral do Município" => "PROCURADORIA GERAL",
    "Secretaria Municipal de Cultura" => "SECRETARIA DE CULTURA",
    "Secretaria Municipal de Planejamento, Ciência, Tecnologia e Inovação" => "SECRETARIA DE PLANEJAMENTO E TECNOLOGIA",
    "Secretaria Municipal de Obras e Serviços Públicos" => "SECRETARIA DE OBRAS E SERVIÇOS PÚBLICOS",
];

// Buscar dados das secretarias
$stmt = $conn->query("
    SELECT
        es.secretaria,
        es.valor_total as saldo_disponivel,
        es.valor_etanol,
        es.valor_gasolina,
        es.valor_diesel,
        es.valor_diesel_s10,
        es.valor_diesel_s500
    FROM empenhos_secretarias es
    ORDER BY es.valor_total DESC
");
$secretarias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gráfico de Secretarias</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .secretaria-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .secretaria-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .combustivel-bar {
            height: 20px;
            border-radius: 4px;
            margin-bottom: 8px;
            position: relative;
        }

        .combustivel-label {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-weight: bold;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.5);
        }

        .combustivel-value {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-weight: bold;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.5);
        }

        .combustivel-color {
            display: inline-block;
            width: 16px;
            height: 16px;
            border-radius: 3px;
            margin-right: 8px;
        }
        
        /* Ajustes para evitar vazamento na tela */
        .container {
            max-width: 95%;
            margin-left: auto;
            margin-right: auto;
        }
        
        @media (min-width: 1024px) {
            .container {
                max-width: 1200px;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Distribuição por Secretaria</h1>
            <a href="cadastro_combustivel.php" class="btn bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                <i class="fas fa-arrow-left mr-2"></i>Voltar
            </a>
        </div>

        <div class="bg-white p-6 rounded-lg shadow mb-8 overflow-x-auto">
            <div style="min-width: 800px;">
                <canvas id="graficoSecretarias"></canvas>
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
        // Dados das secretarias
        const secretariasData = <?php echo json_encode(array_column($secretarias, 'secretaria')); ?>;
        const saldosData = <?php echo json_encode(array_column($secretarias, 'saldo_disponivel')); ?>;
        const etanolData = <?php echo json_encode(array_column($secretarias, 'valor_etanol')); ?>;
        const gasolinaData = <?php echo json_encode(array_column($secretarias, 'valor_gasolina')); ?>;
        const dieselData = <?php echo json_encode(array_column($secretarias, 'valor_diesel')); ?>;
        const dieselS10Data = <?php echo json_encode(array_column($secretarias, 'valor_diesel_s10')); ?>;
        const dieselS500Data = <?php echo json_encode(array_column($secretarias, 'valor_diesel_s500')); ?>;

        // Cores para os combustíveis
        const combustivelColors = {
            etanol: '#4CAF50',
            gasolina: '#FF9800',
            diesel: '#2196F3',
            diesel_s10: '#3F51B5',
            diesel_s500: '#009688'
        };

        // Configurar gráfico horizontal
        const ctx = document.getElementById('graficoSecretarias').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: secretariasData,
                datasets: [{
                    label: 'Saldo Disponível',
                    data: saldosData,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y', // Isso faz o gráfico ser horizontal
                responsive: true,
                maintainAspectRatio: false,
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
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                            }
                        }
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

        // Mostrar detalhes da secretaria selecionada
        function mostrarDetalhesSecretaria(index) {
            const secretaria = secretariasData[index];
            const saldoDisponivel = saldosData[index];
            const etanol = etanolData[index];
            const gasolina = gasolinaData[index];
            const diesel = dieselData[index];
            const dieselS10 = dieselS10Data[index];
            const dieselS500 = dieselS500Data[index];

            // Formatar valores
            const formatarMoeda = (valor) => {
                return valor.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
            };

            // Criar HTML dos detalhes
            const detalhesHTML = `
                <h2 class="text-xl font-bold mb-4 text-center">${secretaria}</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="text-lg font-semibold mb-3">Saldos por Combustível</h3>
                        <div class="space-y-4">
                            <div class="p-3 bg-gray-100 rounded-lg">
                                <div class="flex items-center mb-1">
                                    <span class="combustivel-color" style="background-color: ${combustivelColors.etanol};"></span>
                                    <span class="font-medium">Etanol</span>
                                </div>
                                <div class="text-right font-bold">${formatarMoeda(etanol)}</div>
                            </div>

                            <div class="p-3 bg-gray-100 rounded-lg">
                                <div class="flex items-center mb-1">
                                    <span class="combustivel-color" style="background-color: ${combustivelColors.gasolina};"></span>
                                    <span class="font-medium">Gasolina</span>
                                </div>
                                <div class="text-right font-bold">${formatarMoeda(gasolina)}</div>
                            </div>

                            <div class="p-3 bg-gray-100 rounded-lg">
                                <div class="flex items-center mb-1">
                                    <span class="combustivel-color" style="background-color: ${combustivelColors.diesel};"></span>
                                    <span class="font-medium">Diesel</span>
                                </div>
                                <div class="text-right font-bold">${formatarMoeda(diesel)}</div>
                            </div>

                            <div class="p-3 bg-gray-100 rounded-lg">
                                <div class="flex items-center mb-1">
                                    <span class="combustivel-color" style="background-color: ${combustivelColors.diesel_s10};"></span>
                                    <span class="font-medium">Diesel S10</span>
                                </div>
                                <div class="text-right font-bold">${formatarMoeda(dieselS10)}</div>
                            </div>

                            <div class="p-3 bg-gray-100 rounded-lg">
                                <div class="flex items-center mb-1">
                                    <span class="combustivel-color" style="background-color: ${combustivelColors.diesel_s500};"></span>
                                    <span class="font-medium">Diesel S500</span>
                                </div>
                                <div class="text-right font-bold">${formatarMoeda(dieselS500)}</div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold mb-3">Resumo</h3>
                        <div class="bg-gray-100 p-4 rounded-lg space-y-3">
                            <div class="flex justify-between">
                                <span>Total Etanol:</span>
                                <span class="font-medium">${formatarMoeda(etanol)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Gasolina:</span>
                                <span class="font-medium">${formatarMoeda(gasolina)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Diesel:</span>
                                <span class="font-medium">${formatarMoeda(diesel)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Diesel S10:</span>
                                <span class="font-medium">${formatarMoeda(dieselS10)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Total Diesel S500:</span>
                                <span class="font-medium">${formatarMoeda(dieselS500)}</span>
                            </div>
                            <div class="flex justify-between border-t pt-2">
                                <span class="font-bold">Saldo Total:</span>
                                <span class="font-bold text-blue-600">${formatarMoeda(saldoDisponivel)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <h3 class="text-lg font-semibold mb-3">Últimos Abastecimentos</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-2 px-4 border">Data</th>
                                <th class="py-2 px-4 border">Veículo</th>
                                <th class="py-2 px-4 border">Combustível</th>
                                <th class="py-2 px-4 border">Valor</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaAbastecimentos">
                            <!-- Será preenchido via AJAX -->
                        </tbody>
                    </table>
                </div>
            `;

            document.getElementById('conteudoDetalhes').innerHTML = detalhesHTML;

            // Carregar abastecimentos via AJAX
            fetch(`buscar_abastecimentos_secretaria.php?secretaria=${encodeURIComponent(secretaria)}`)
                .then(response => response.json())
                .then(data => {
                    const tabela = document.getElementById('tabelaAbastecimentos');
                    tabela.innerHTML = data.map(abast => `
                        <tr class="border-b">
                            <td class="py-2 px-4 border">${abast.data}</td>
                            <td class="py-2 px-4 border">${abast.veiculo} (${abast.placa})</td>
                            <td class="py-2 px-4 border">${abast.combustivel}</td>
                            <td class="py-2 px-4 border">${formatarMoeda(parseFloat(abast.valor))}</td>
                        </tr>
                    `).join('');
                });

            document.getElementById('detalhesSecretaria').style.display = 'block';
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
    </script>
</body>
</html>