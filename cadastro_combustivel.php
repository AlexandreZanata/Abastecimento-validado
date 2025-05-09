<?php
session_start();

// Verificar se o usuário é geraladm
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'geraladm') {
    header('Location: ../login.php');
    exit();
}

// Conexão com o banco de dados
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

// Função para buscar empenhos totais
function buscarEmpenhosTotais($conn) {
    try {
        $stmt = $conn->query("SELECT id, numero_empenho, fornecedor FROM empenhos_totais ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Função para buscar empenhos por termo
function buscarEmpenhosPorTermo($conn, $termo) {
    try {
        $stmt = $conn->prepare("SELECT id, numero_empenho, fornecedor FROM empenhos_totais
                               WHERE numero_empenho LIKE :termo ORDER BY id DESC");
        $stmt->bindValue(':termo', '%' . $termo . '%');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Função para buscar detalhes do empenho
function buscarDetalhesEmpenho($conn, $id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM empenhos_totais WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

$empenhos_totais = buscarEmpenhosTotais($conn);

// Buscar empenhos por termo se for uma requisição AJAX
if (isset($_GET['termo'])) {
    $termo = $_GET['termo'];
    $empenhos = buscarEmpenhosPorTermo($conn, $termo);
    echo json_encode($empenhos);
    exit();
}

// Buscar detalhes do empenho se for uma requisição AJAX
if (isset($_GET['id_empenho'])) {
    $id = $_GET['id_empenho'];
    $empenho = buscarDetalhesEmpenho($conn, $id);
    echo json_encode($empenho);
    exit();
}

// Variáveis para mensagens
$error_message = "";
$success_message = "";
$validation_errors = [];

// Processar formulário de empenho total
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar_empenho'])) {
    // Dados do formulário
    $orgao = $_POST['orgao'] ?? '';
    $unidade = $_POST['unidade'] ?? '';
    $numero_empenho = $_POST['numero_empenho'] ?? '';
    $vigencia_inicio = $_POST['vigencia_inicio'] ?? '';
    $vigencia_fim = $_POST['vigencia_fim'] ?? '';
    $fornecedor = $_POST['fornecedor'] ?? '';
    $valor_total = $_POST['valor_total'] ?? '0';

    // Validar campos obrigatórios
    $required_fields = [
        'orgao' => 'Órgão',
        'unidade' => 'Unidade',
        'numero_empenho' => 'Número do Empenho',
        'vigencia_inicio' => 'Vigência (início)',
        'vigencia_fim' => 'Vigência (fim)',
        'fornecedor' => 'Fornecedor (Posto de gasolina)',
        'valor_total' => 'Valor Total'
    ];

    foreach ($required_fields as $field => $name) {
        if (empty($_POST[$field])) {
            $validation_errors[] = "O campo $name é obrigatório";
        }
    }

    // Se não houver erros de validação, processar
    if (empty($validation_errors)) {
        try {
            $conn->beginTransaction();

            // Inserir empenho total
            $sql_total = "INSERT INTO empenhos_totais (
                orgao, unidade, numero_empenho, vigencia_inicio, vigencia_fim,
                fornecedor, valor_total, usuario_cadastro
            ) VALUES (
                :orgao, :unidade, :numero_empenho, :vigencia_inicio, :vigencia_fim,
                :fornecedor, :valor_total, :usuario_cadastro
            )";

            $stmt_total = $conn->prepare($sql_total);
            $stmt_total->execute([
                ':orgao' => $orgao,
                ':unidade' => $unidade,
                ':numero_empenho' => $numero_empenho,
                ':vigencia_inicio' => $vigencia_inicio,
                ':vigencia_fim' => $vigencia_fim,
                ':fornecedor' => $fornecedor,
                ':valor_total' => $valor_total,
                ':usuario_cadastro' => $_SESSION['name'] ?? 'Usuário desconhecido'
            ]);

            $conn->commit();
            $success_message = "Empenho total cadastrado com sucesso!";
            $_POST = [];
            $empenhos_totais = buscarEmpenhosTotais($conn); // Atualizar lista de empenhos

        } catch (PDOException $e) {
            $conn->rollBack();
            $error_message = "Erro ao cadastrar empenho: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $validation_errors);
    }
}

// Processar formulário de secretaria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar_secretaria'])) {
    $id_empenho_total = $_POST['id_empenho_total'] ?? '';
    $secretaria = $_POST['secretaria'] ?? '';
    $numero_ordem_fornecimento = $_POST['numero_ordem_fornecimento'] ?? '';
    $data_ordem_fornecimento = $_POST['data_ordem_fornecimento'] ?? '';
    $valor_total = $_POST['s_valor_total'] ?? '0';
    $valor_etanol = $_POST['s_valor_etanol'] ?? '0';
    $valor_gasolina = $_POST['s_valor_gasolina'] ?? '0';
    $valor_diesel = $_POST['s_valor_diesel'] ?? '0';
    $valor_diesel_s10 = $_POST['s_valor_diesel_s10'] ?? '0';

    // Validar campos obrigatórios
    if (empty($id_empenho_total)) {
        $validation_errors[] = "Selecione um empenho total";
    }
    if (empty($secretaria)) {
        $validation_errors[] = "Selecione uma secretaria";
    }
    if (empty($numero_ordem_fornecimento)) {
        $validation_errors[] = "Informe o número da ordem de fornecimento";
    }
    if (empty($data_ordem_fornecimento)) {
        $validation_errors[] = "Informe a data da ordem de fornecimento";
    }

    if (empty($validation_errors)) {
        try {
            // Buscar dados do empenho total
            $stmt = $conn->prepare("SELECT * FROM empenhos_totais WHERE id = :id");
            $stmt->bindParam(':id', $id_empenho_total);
            $stmt->execute();
            $empenho_total = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$empenho_total) {
                throw new Exception("Empenho total não encontrado");
            }

            // Inserir secretaria com os novos campos
            $sql_secretaria = "INSERT INTO empenhos_secretarias (
                id_empenho_total, orgao, unidade, secretaria, numero_empenho,
                numero_ordem_fornecimento, data_ordem_fornecimento,
                fornecedor, vigencia_inicio, vigencia_fim,
                valor_total, valor_etanol, valor_gasolina,
                valor_diesel, valor_diesel_s10,
                usuario_cadastro
            ) VALUES (
                :id_empenho_total, :orgao, :unidade, :secretaria, :numero_empenho,
                :numero_ordem_fornecimento, :data_ordem_fornecimento,
                :fornecedor, :vigencia_inicio, :vigencia_fim,
                :valor_total, :valor_etanol, :valor_gasolina,
                :valor_diesel, :valor_diesel_s10,
                :usuario_cadastro
            )";

            $stmt_secretaria = $conn->prepare($sql_secretaria);
            $stmt_secretaria->execute([
                ':id_empenho_total' => $id_empenho_total,
                ':orgao' => $empenho_total['orgao'],
                ':unidade' => $empenho_total['unidade'],
                ':secretaria' => $secretaria,
                ':numero_empenho' => $empenho_total['numero_empenho'],
                ':numero_ordem_fornecimento' => $numero_ordem_fornecimento,
                ':data_ordem_fornecimento' => $data_ordem_fornecimento,
                ':fornecedor' => $empenho_total['fornecedor'],
                ':vigencia_inicio' => $empenho_total['vigencia_inicio'],
                ':vigencia_fim' => $empenho_total['vigencia_fim'],
                ':valor_total' => $valor_total,
                ':valor_etanol' => $valor_etanol,
                ':valor_gasolina' => $valor_gasolina,
                ':valor_diesel' => $valor_diesel,
                ':valor_diesel_s10' => $valor_diesel_s10,
                ':usuario_cadastro' => $_SESSION['name'] ?? 'Usuário desconhecido'
            ]);

            $success_message = "Secretaria cadastrada com sucesso no empenho!";
        } catch (Exception $e) {
            $error_message = "Erro ao cadastrar secretaria: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $validation_errors);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Empenhos - Combustível</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Estilos mantidos iguais */
        :root {
            --primary-color: #4338CA;
            --secondary-color: #10B981;
            --danger-color: #EF4444;
            --background-color: #F9FAFB;
            --card-bg: #FFFFFF;
            --text-dark: #1F2937;
            --text-light: #6B7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-color);
            color: var(--text-dark);
            line-height: 1.5;
            padding-bottom: 4rem;
        }

        .header-gradient {
            background: linear-gradient(135deg, #4F46E5, #4338CA);
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .card {
            background: var(--card-bg);
            border-radius: 1rem;
            padding: 1.25rem;
            margin: 1rem 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .btn {
            border-radius: 0.75rem;
            padding: 0.65rem 1.25rem;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #4B5563;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #059669;
            transform: translateY(-1px);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #DC2626;
            transform: translateY(-1px);
        }

        .input-field {
            border: 1px solid #E5E7EB;
            border-radius: 0.5rem;
            padding: 0.65rem 0.75rem;
            width: 100%;
            background: white;
            font-size: 0.9rem;
            transition: border-color 0.2s ease;
        }

        .input-field:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(107, 114, 128, 0.1);
            outline: none;
        }

        .message-container {
            position: fixed;
            top: 4rem;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 24rem;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            font-size: 0.9rem;
            animation: slideIn 0.3s ease;
            z-index: 1000;
        }

        @keyframes slideIn {
            from { transform: translateX(-50%) translateY(-10px); opacity: 0; }
            to { transform: translateX(-50%) translateY(0); opacity: 1; }
        }

        .success {
            color: var(--secondary-color);
            border: 1px solid var(--secondary-color);
        }

        .error {
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }

        .floating-action-btn {
            position: fixed;
            bottom: 1.25rem;
            right: 1.25rem;
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }

        .floating-action-btn:hover {
            background-color: #4B5563;
            transform: scale(1.05);
        }

        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            color: var(--text-dark);
            background: #F3F4F6;
        }

        .badge i {
            margin-right: 0.25rem;
        }

        .stats-card {
            border-radius: 0.75rem;
            padding: 1rem;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.2s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .icon-circle {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,0.05);
        }

        .secretaria-item {
            border: 1px solid #E5E7EB;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            transition: all 0.2s ease;
        }

        .secretaria-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .combustivel-icon {
            width: 1.5rem;
            height: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.5rem;
            color: #6B7280;
        }

        .valor-input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #E5E7EB;
            border-radius: 0.5rem;
            text-align: right;
        }

        .valor-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        /* Estilo para o autocomplete */
        .autocomplete {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .autocomplete-items {
            position: absolute;
            border: 1px solid #d4d4d4;
            border-bottom: none;
            border-top: none;
            z-index: 99;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            max-height: 200px;
            overflow-y: auto;
        }

        .autocomplete-items div {
            padding: 10px;
            cursor: pointer;
            background-color: #fff;
            border-bottom: 1px solid #d4d4d4;
        }

        .autocomplete-items div:hover {
            background-color: #e9e9e9;
        }

        .autocomplete-active {
            background-color: var(--primary-color) !important;
            color: #ffffff;
        }

        /* Estilo para a visualização do empenho */
        .empenho-info {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
            border: 1px solid #e9ecef;
        }

        .empenho-info p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .empenho-info strong {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .card {
                padding: 1rem;
                margin: 0.5rem 0;
            }
            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }
            .floating-action-btn {
                width: 2.5rem;
                height: 2.5rem;
            }
            .stats-card {
                padding: 0.75rem;
            }
        }
        /* Adicione este estilo */
        .empenho-info {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 1rem;
            border: 1px solid #e9ecef;
        }

        .empenho-info p {
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            padding: 0.5rem;
            background-color: white;
            border-radius: 0.25rem;
            border: 1px solid #e9ecef;
        }

        .empenho-info strong {
            color: var(--primary-color);
            min-width: 100px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="header-gradient text-white">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <i class="fas fa-gas-pump text-xl"></i>
                <h1 class="text-lg font-semibold">Cadastro de Empenhos - Combustível</h1>
            </div>
            <div class="flex items-center space-x-2">
                <a href="grafico_secretarias.php" class="btn btn-secondary"><i class="fas fa-file-alt mr-1"></i>Relatório</a>
                <a href="menugeraladm.php" class="btn btn-danger"><i class="fas fa-sign-out-alt mr-1"></i>Sair</a>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-6">
        <?php if (!empty($error_message)): ?>
            <div class="message-container error"><?= $error_message ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="message-container success"><?= $success_message ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 class="text-xl font-semibold mb-4">Cadastro de Empenho - Total</h2>
            <form method="POST" id="formEmpenho">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Órgão</label>
                        <input type="text" name="orgao" class="input-field" value="<?= htmlspecialchars($_POST['orgao'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unidade</label>
                        <input type="text" name="unidade" class="input-field" value="<?= htmlspecialchars($_POST['unidade'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Número do Empenho</label>
                        <input type="text" name="numero_empenho" class="input-field" value="<?= htmlspecialchars($_POST['numero_empenho'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Vigência</label>
                        <div class="flex space-x-2">
                            <input type="date" name="vigencia_inicio" class="input-field" value="<?= htmlspecialchars($_POST['vigencia_inicio'] ?? '') ?>" required>
                            <input type="date" name="vigencia_fim" class="input-field" value="<?= htmlspecialchars($_POST['vigencia_fim'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fornecedor (Posto de gasolina)</label>
                        <input type="text" name="fornecedor" class="input-field" value="<?= htmlspecialchars($_POST['fornecedor'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valor Total</label>
                        <input type="number" step="0.01" name="valor_total" id="valor_total" class="input-field" value="<?= htmlspecialchars($_POST['valor_total'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="mt-8 flex justify-end">
                    <button type="submit" name="cadastrar_empenho" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i>Cadastrar Empenho
                    </button>
                </div>
            </form>
        </div>

        <!-- Formulário para adicionar secretaria a um empenho existente -->
        <div class="card mt-6">
            <h2 class="text-xl font-semibold mb-4">Adicionar Secretaria ao Empenho</h2>
            <form method="POST" id="formSecretaria">
                <div class="grid grid-cols-1 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pesquisar Empenho</label>
                        <div class="autocomplete">
                            <input type="text" id="pesquisaEmpenho" class="input-field" placeholder="Digite o número do empenho">
                            <input type="hidden" name="id_empenho_total" id="id_empenho_total">
                        </div>
                        <div id="empenhoInfo" class="empenho-info mt-4" style="display: none;">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p><strong>Número:</strong> <span id="infoNumero"></span></p>
                                    <p><strong>Fornecedor:</strong> <span id="infoFornecedor"></span></p>
                                </div>
                                <div>
                                    <p><strong>Vigência:</strong> <span id="infoVigencia"></span></p>
                                    <p><strong>Valor Total:</strong> R$ <span id="infoValorTotal"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Secretaria</label>
                        <select name="secretaria" class="input-field" required>
                            <option value="">Selecione uma secretaria</option>
                            <?php foreach ($secretarias_map as $key => $value): ?>
                                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($value) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Número da Ordem de Fornecimento</label>
                        <input type="text" name="numero_ordem_fornecimento" class="input-field" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data da Ordem de Fornecimento</label>
                        <input type="date" name="data_ordem_fornecimento" class="input-field" required>
                    </div>
                </div>

                <h3 class="text-lg font-semibold mt-6 mb-4">Valores por Combustível</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valor Total</label>
                        <input type="number" step="0.01" name="s_valor_total" class="input-field" value="0">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                    <div class="flex items-center">
                        <div class="combustivel-icon">
                            <i class="fas fa-leaf"></i>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Etanol</label>
                            <input type="number" step="0.01" name="s_valor_etanol" class="input-field" value="0">
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="combustivel-icon">
                            <i class="fas fa-gas-pump"></i>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Gasolina</label>
                            <input type="number" step="0.01" name="s_valor_gasolina" class="input-field" value="0">
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="combustivel-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Diesel</label>
                            <input type="number" step="0.01" name="s_valor_diesel" class="input-field" value="0">
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="combustivel-icon">
                            <i class="fas fa-truck-pickup"></i>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Diesel S10</label>
                            <input type="number" step="0.01" name="s_valor_diesel_s10" class="input-field" value="0">
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end">
                    <button type="submit" name="cadastrar_secretaria" class="btn btn-primary">
                        <i class="fas fa-plus mr-1"></i>Adicionar Secretaria
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            // Fechar mensagens após 5 segundos
            setTimeout(() => {
                $('.message-container').fadeOut();
            }, 5000);

            // Autocomplete para pesquisa de empenhos
            $("#pesquisaEmpenho").on("input", function() {
                const termo = $(this).val();
                if (termo.length >= 2) {
                    $.get("?termo=" + termo, function(data) {
                        const empenhos = JSON.parse(data);
                        let html = '<div class="autocomplete-items">';

                        if (empenhos.length > 0) {
                            empenhos.forEach(empenho => {
                                html += `<div data-id="${empenho.id}" data-numero="${empenho.numero_empenho}" data-fornecedor="${empenho.fornecedor}">
                                    ${empenho.numero_empenho} - ${empenho.fornecedor}
                                </div>`;
                            });
                        } else {
                            html += '<div>Nenhum empenho encontrado</div>';
                        }

                        html += '</div>';

                        // Remove qualquer autocomplete anterior
                        $(".autocomplete-items").remove();

                        // Adiciona os novos resultados
                        $(html).insertAfter("#pesquisaEmpenho");

                        // Configura o clique nos itens
                        $(".autocomplete-items div").on("click", function() {
                            const id = $(this).data("id");
                            const numero = $(this).data("numero");
                            const fornecedor = $(this).data("fornecedor");

                            $("#pesquisaEmpenho").val(numero);
                            $("#id_empenho_total").val(id);
                            $(".autocomplete-items").remove();

                            // Buscar detalhes do empenho
                            $.get("?id_empenho=" + id, function(data) {
                                const empenho = JSON.parse(data);
                                if (empenho) {
                                    $("#infoNumero").text(empenho.numero_empenho);
                                    $("#infoFornecedor").text(empenho.fornecedor);
                                    $("#infoVigencia").text(formatarData(empenho.vigencia_inicio) + " a " + formatarData(empenho.vigencia_fim));
                                    $("#infoValorTotal").text(parseFloat(empenho.valor_total).toFixed(2).replace('.', ','));
                                    $("#empenhoInfo").show();

                                    // Atualizar o campo hidden com o ID do empenho
                                    $("#id_empenho_total").val(empenho.id);
                                }
                            });
                        });
                    });
                } else {
                    $(".autocomplete-items").remove();
                    $("#id_empenho_total").val("");
                    $("#empenhoInfo").hide();
                }
            });

            // Fechar autocomplete ao clicar fora
            $(document).on("click", function(e) {
                if (!$(e.target).closest(".autocomplete").length) {
                    $(".autocomplete-items").remove();
                }
            });

            // Função para formatar data
            function formatarData(dataStr) {
                if (!dataStr) return '';
                const data = new Date(dataStr);
                return data.toLocaleDateString('pt-BR');
            }
        });
    </script>
</body>
</html>
