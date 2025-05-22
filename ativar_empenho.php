<?php
session_start();

// Verificar se o usuário é geraladm
if (!isset($_SESSION['role'])) {
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

// Processar ativação/desativação de empenho via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $tabela = $_POST['tabela'];
    $novo_status = $_POST['novo_status'];
    $numero_empenho = $_POST['numero_empenho'];
    $usuario = $_SESSION['user_name'] ?? 'sistema';
    $acao = $novo_status === 'ativo' ? 'Ativar' : 'Desativar';

    try {
        // Atualizar status do empenho
        $stmt = $conn->prepare("UPDATE $tabela SET status = :status WHERE id = :id");
        $stmt->bindParam(':status', $novo_status);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        // Registrar ação na tabela ativar_empenho
        $stmt = $conn->prepare("INSERT INTO ativar_empenho (id_empenho, tabela, acao, usuario) VALUES (:id_empenho, :tabela, :acao, :usuario)");
        $stmt->bindParam(':id_empenho', $id);
        $stmt->bindParam(':tabela', $tabela);
        $stmt->bindParam(':acao', $acao);
        $stmt->bindParam(':usuario', $usuario);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => "Empenho $numero_empenho $acao com sucesso!"]);
        exit();
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => "Erro ao atualizar status: " . $e->getMessage()]);
        exit();
    }
}

// Processar exclusão de empenho via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_id'])) {
    $id = $_POST['excluir_id'];
    $tabela = $_POST['tabela'];
    $numero_empenho = $_POST['numero_empenho'];
    $usuario = $_SESSION['user_name'] ?? 'sistema';

    try {
        // Primeiro registrar a ação na tabela ativar_empenho
        $stmt = $conn->prepare("INSERT INTO ativar_empenho (id_empenho, tabela, acao, usuario) VALUES (:id_empenho, :tabela, :acao, :usuario)");
        $stmt->bindParam(':id_empenho', $id);
        $stmt->bindParam(':tabela', $tabela);
        $stmt->bindValue(':acao', 'Excluir');
        $stmt->bindParam(':usuario', $usuario);
        $stmt->execute();

        // Agora excluir o empenho
        $stmt = $conn->prepare("DELETE FROM $tabela WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => "Empenho $numero_empenho excluído com sucesso!"]);
        exit();
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => "Erro ao excluir empenho: " . $e->getMessage()]);
        exit();
    }
}

// Buscar empenhos totais com status
function buscarEmpenhosTotais($conn, $termo = '') {
    try {
        if (!empty($termo)) {
            $stmt = $conn->prepare("SELECT id, numero_empenho, fornecedor, status FROM empenhos_totais
                                   WHERE numero_empenho LIKE :termo OR fornecedor LIKE :termo
                                   ORDER BY id DESC");
            $stmt->bindValue(':termo', '%' . $termo . '%');
        } else {
            $stmt = $conn->prepare("SELECT id, numero_empenho, fornecedor, status FROM empenhos_totais
                                   ORDER BY id DESC");
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Buscar empenhos por secretaria com status
function buscarEmpenhosSecretarias($conn, $termo = '') {
    try {
        if (!empty($termo)) {
            $stmt = $conn->prepare("SELECT es.id, es.secretaria, es.numero_empenho, es.fornecedor, es.status,
                                   et.numero_empenho as numero_empenho_total
                                   FROM empenhos_secretarias es
                                   JOIN empenhos_totais et ON es.id_empenho_total = et.id
                                   WHERE es.numero_empenho LIKE :termo OR es.fornecedor LIKE :termo
                                   OR et.numero_empenho LIKE :termo OR es.secretaria LIKE :termo
                                   ORDER BY es.id DESC");
            $stmt->bindValue(':termo', '%' . $termo . '%');
        } else {
            $stmt = $conn->prepare("SELECT es.id, es.secretaria, es.numero_empenho, es.fornecedor, es.status,
                                   et.numero_empenho as numero_empenho_total
                                   FROM empenhos_secretarias es
                                   JOIN empenhos_totais et ON es.id_empenho_total = et.id
                                   ORDER BY es.id DESC");
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Buscar empenhos por termo para autocomplete
if (isset($_GET['termo'])) {
    $termo = $_GET['termo'];
    $empenhos_totais = buscarEmpenhosTotais($conn, $termo);
    $empenhos_secretarias = buscarEmpenhosSecretarias($conn, $termo);

    $resultados = array_merge($empenhos_totais, $empenhos_secretarias);
    echo json_encode($resultados);
    exit();
}

// Mensagens de sessão
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);

// Termo de pesquisa
$termo_pesquisa = $_GET['pesquisa'] ?? '';
$empenhos_totais = buscarEmpenhosTotais($conn, $termo_pesquisa);
$empenhos_secretarias = buscarEmpenhosSecretarias($conn, $termo_pesquisa);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Empenhos - Combustível</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #4338CA;
            --primary-light: #6366F1;
            --secondary-color: #10B981;
            --danger-color: #EF4444;
            --background-color: #F9FAFB;
            --card-bg: #FFFFFF;
            --text-dark: #1F2937;
            --text-light: #6B7280;
            --border-color: #E5E7EB;
        }

        .header-gradient {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .header-gradient h1 {
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .card {
            background: var(--card-bg);
            border-radius: 1rem;
            padding: 1.5rem;
            margin: 1.5rem 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #EEF2FF;
        }

        .input-field {
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 0.75rem;
            width: 100%;
            background: white;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .input-field:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            outline: none;
        }

        .btn {
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background-color: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
            border: none;
        }

        .btn-secondary:hover {
            background-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
            border: none;
        }

        .btn-danger:hover {
            background-color: #DC2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .message-container {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .message-container.error {
            background-color: #FEE2E2;
            color: #B91C1C;
            border-left: 4px solid #EF4444;
        }

        .message-container.success {
            background-color: #D1FAE5;
            color: #065F46;
            border-left: 4px solid #10B981;
        }

        .message-container i {
            font-size: 1.25rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-ativo {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .status-inativo {
            background-color: #FEE2E2;
            color: #B91C1C;
        }

        .autocomplete {
            position: relative;
        }

        .autocomplete-items {
            position: absolute;
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            background: white;
            width: 100%;
            z-index: 1000;
            margin-top: 0.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            max-height: 300px;
            overflow-y: auto;
        }

        .autocomplete-item {
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            border-bottom: 1px solid var(--border-color);
        }

        .autocomplete-item:last-child {
            border-bottom: none;
        }

        .autocomplete-item:hover {
            background-color: #F8FAFC;
        }

        .autocomplete-item.active {
            background-color: var(--primary-light);
            color: white;
        }

        .autocomplete-number {
            font-weight: 600;
            color: var(--primary-color);
        }

        .autocomplete-fornecedor {
            font-size: 0.875rem;
            color: var(--text-light);
        }

        .no-results {
            padding: 1rem;
            color: var(--text-light);
            font-style: italic;
        }

        /* Substitua o CSS existente da tabela por este */
        .table-container {
            width: 100%;
            overflow-x: auto;
            margin-top: 1rem;
        }

        .table-fixed {
            width: 100%;
            border-collapse: collapse;
        }

        .table-fixed th {
            background-color: #f3f4f6;
            font-weight: 600;
            text-align: left;
            padding: 0.75rem 1rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table-fixed td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }

        .table-fixed tr:hover {
            background-color: #f9fafb;
        }

        .actions-cell {
            width: 180px;
            text-align: right;
            position: relative;
            white-space: nowrap;
        }

        .action-buttons {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .delete-btn {
            color: #ef4444;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 50%;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
        }

        .delete-btn:hover {
            background-color: rgba(239, 68, 68, 0.1);
            transform: scale(1.1);
        }

        .status-btn {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }

        .status-btn.btn-danger {
            background-color: #ef4444;
            color: white;
        }

        .status-btn.btn-secondary {
            background-color: #10b981;
            color: white;
        }

        .status-btn i {
            font-size: 0.75rem;
        }

        .tab-container {
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .tab-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .tab-button {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
            background-color: #E5E7EB;
            color: var(--text-dark);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }

        .tab-button.active {
            background-color: var(--primary-color);
            color: white;
        }

        .tab-button:hover:not(.active) {
            background-color: #D1D5DB;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .btn {
            margin-right: 0.5rem;
        }

        @media (max-width: 768px) {
            .table-container {
                border: 0;
            }

            .table-fixed {
                border: 0;
            }

            .table-fixed thead {
                display: none;
            }

            .table-fixed tr {
                margin-bottom: 1rem;
                display: block;
                border: 1px solid #e5e7eb;
                border-radius: 0.5rem;
            }

            .table-fixed td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 1rem;
                border-bottom: 1px solid #e5e7eb;
                width: 100% !important;
            }

            .table-fixed td:last-child {
                border-bottom: 0;
            }

            .table-fixed td::before {
                content: attr(data-label);
                font-weight: 600;
                margin-right: 1rem;
                color: #4b5563;
            }

            .actions-cell {
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="header-gradient text-white">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <i class="fas fa-gas-pump text-2xl text-white bg-primary-500 p-2 rounded-lg"></i>
                <h1 class="text-xl font-bold">Gerenciar Empenhos - Combustível</h1>
            </div>
            <div class="flex items-center space-x-3">
                <a href="cadastro_combustivel.php" class="btn btn-secondary">
                    <i class="fas fa-plus"></i>
                    <span class="hidden md:inline">Novo Empenho</span>
                </a>
                <a href="../menugeraladm.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="hidden md:inline">Sair</span>
                </a>
                <a href="cadastro_combustivel.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i>
                    <span class="hidden md:inline">Voltar</span>
                </a>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-6">
        <?php if (!empty($error_message)): ?>
            <div class="message-container error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error_message ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="message-container success">
                <i class="fas fa-check-circle"></i>
                <?= $success_message ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 class="section-title">
                <i class="fas fa-search mr-2"></i>
                Pesquisar Empenhos
            </h2>

            <form method="GET" class="mb-6">
                <div class="autocomplete">
                    <input type="text" name="pesquisa" id="pesquisaEmpenho" class="input-field"
                           placeholder="Digite o número do empenho, fornecedor ou secretaria"
                           value="<?= htmlspecialchars($termo_pesquisa) ?>">
                    <button type="submit" class="btn btn-primary mt-2">
                        <i class="fas fa-search mr-2"></i>Pesquisar
                    </button>
                    <?php if (!empty($termo_pesquisa)): ?>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-danger mt-2 ml-2">
                            <i class="fas fa-times mr-2"></i>Limpar
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="tab-container">
                <div class="tab-buttons">
                    <button type="button" class="tab-button active" data-tab="tab-totais">
                        <i class="fas fa-file-invoice-dollar mr-1"></i>
                        Empenhos Totais
                    </button>
                    <button type="button" class="tab-button" data-tab="tab-secretarias">
                        <i class="fas fa-building mr-1"></i>
                        Empenhos por Secretaria
                    </button>
                </div>
            </div>

            <!-- Aba de Empenhos Totais -->
            <div id="tab-totais" class="tab-content active">
                <h3 class="font-semibold text-lg mb-4">Empenhos Totais</h3>

                <?php if (empty($empenhos_totais)): ?>
                    <p class="text-gray-500">Nenhum empenho total encontrado.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table-fixed">
                            <thead>
                                <tr>
                                    <th style="width: 20%;">Número</th>
                                    <th style="width: 30%;">Fornecedor</th>
                                    <th style="width: 15%;">Status</th>
                                    <th style="width: 10%;" class="actions-cell">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($empenhos_totais as $empenho): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($empenho['numero_empenho']) ?></td>
                                        <td><?= htmlspecialchars($empenho['fornecedor']) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $empenho['status'] ?>">
                                                <?= ucfirst($empenho['status']) ?>
                                            </span>
                                        </td>
                                        <td class="actions-cell">
                                            <div class="action-buttons">
                                                <button type="button"
                                                        class="status-btn btn-<?= $empenho['status'] === 'ativo' ? 'danger' : 'secondary' ?> alterar-status"
                                                        data-id="<?= $empenho['id'] ?>"
                                                        data-tabela="empenhos_totais"
                                                        data-status-atual="<?= $empenho['status'] ?>"
                                                        data-numero="<?= htmlspecialchars($empenho['numero_empenho']) ?>">
                                                    <i class="fas fa-<?= $empenho['status'] === 'ativo' ? 'times' : 'check' ?>"></i>
                                                    <span><?= $empenho['status'] === 'ativo' ? 'Desativar' : 'Ativar' ?></span>
                                                </button>
                                                <button class="delete-btn excluir-empenho"
                                                        data-id="<?= $empenho['id'] ?>"
                                                        data-tabela="empenhos_totais"
                                                        data-numero="<?= htmlspecialchars($empenho['numero_empenho']) ?>"
                                                        title="Excluir empenho">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Aba de Empenhos por Secretaria -->
            <div id="tab-secretarias" class="tab-content">
                <h3 class="font-semibold text-lg mb-4">Empenhos por Secretaria</h3>

                <?php if (empty($empenhos_secretarias)): ?>
                    <p class="text-gray-500">Nenhum empenho por secretaria encontrado.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table-fixed">
                            <thead>
                                <tr>
                                    <th style="width: 20%;">Número</th>
                                    <th style="width: 25%;">Secretaria</th>
                                    <th style="width: 25%;">Fornecedor</th>
                                    <th style="width: 15%;">Status</th>
                                    <th style="width: 10%;" class="actions-cell">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($empenhos_secretarias as $empenho): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($empenho['numero_empenho']) ?></td>
                                        <td><?= htmlspecialchars($empenho['secretaria']) ?></td>
                                        <td><?= htmlspecialchars($empenho['fornecedor']) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $empenho['status'] ?>">
                                                <?= ucfirst($empenho['status']) ?>
                                            </span>
                                        </td>
                                        <td class="actions-cell">
                                            <div class="action-buttons">
                                                <button type="button"
                                                        class="status-btn btn-<?= $empenho['status'] === 'ativo' ? 'danger' : 'secondary' ?> alterar-status"
                                                        data-id="<?= $empenho['id'] ?>"
                                                        data-tabela="empenhos_secretarias"
                                                        data-status-atual="<?= $empenho['status'] ?>"
                                                        data-numero="<?= htmlspecialchars($empenho['numero_empenho']) ?>">
                                                    <i class="fas fa-<?= $empenho['status'] === 'ativo' ? 'times' : 'check' ?>"></i>
                                                    <span><?= $empenho['status'] === 'ativo' ? 'Desativar' : 'Ativar' ?></span>
                                                </button>
                                                <button class="delete-btn excluir-empenho"
                                                        data-id="<?= $empenho['id'] ?>"
                                                        data-tabela="empenhos_secretarias"
                                                        data-numero="<?= htmlspecialchars($empenho['numero_empenho']) ?>"
                                                        title="Excluir empenho">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
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
                                const tipo = empenho.secretaria ? 'Secretaria' : 'Total';
                                const descricao = empenho.secretaria ? empenho.secretaria : empenho.fornecedor;

                                html += `
                                <div class="autocomplete-item"
                                     data-numero="${empenho.numero_empenho}"
                                     data-descricao="${descricao}">
                                    <div class="autocomplete-number">
                                        ${empenho.numero_empenho} (${tipo})
                                    </div>
                                    <div class="autocomplete-fornecedor">
                                        ${descricao}
                                    </div>
                                </div>`;
                            });
                        } else {
                            html += '<div class="no-results">Nenhum empenho encontrado</div>';
                        }

                        html += '</div>';

                        // Remove qualquer autocomplete anterior
                        $(".autocomplete-items").remove();

                        // Adiciona os novos resultados
                        $(html).insertAfter("#pesquisaEmpenho");

                        // Configura o clique nos itens
                        $(".autocomplete-item").on("click", function() {
                            const numero = $(this).data("numero");
                            $("#pesquisaEmpenho").val(numero);
                            $(".autocomplete-items").remove();
                            $("form").submit();
                        });
                    });
                } else {
                    $(".autocomplete-items").remove();
                }
            });

            // Fechar autocomplete ao clicar fora
            $(document).on("click", function(e) {
                if (!$(e.target).closest(".autocomplete").length) {
                    $(".autocomplete-items").remove();
                }
            });

            // Controle das abas
            $(".tab-button").on("click", function() {
                const tabId = $(this).data("tab");

                // Remove a classe active de todas as abas e botões
                $(".tab-button, .tab-content").removeClass("active");

                // Adiciona a classe active apenas na aba e botão clicados
                $(this).addClass("active");
                $("#" + tabId).addClass("active");
            });

            // Função para alterar status via AJAX
            $(".alterar-status").on("click", function() {
                const id = $(this).data("id");
                const tabela = $(this).data("tabela");
                const statusAtual = $(this).data("status-atual");
                const numeroEmpenho = $(this).data("numero");
                const novoStatus = statusAtual === 'ativo' ? 'inativo' : 'ativo';
                const acao = statusAtual === 'ativo' ? 'Desativar' : 'Ativar';

                // Confirmação
                Swal.fire({
                    title: 'Confirmar ação',
                    html: `Você realmente deseja ${acao.toLowerCase()} o empenho <b>${numeroEmpenho}</b>?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: `Sim, ${acao}`,
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '<?= $_SERVER['PHP_SELF'] ?>',
                            method: 'POST',
                            data: {
                                id: id,
                                tabela: tabela,
                                novo_status: novoStatus,
                                numero_empenho: numeroEmpenho
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire(
                                        'Sucesso!',
                                        response.message,
                                        'success'
                                    ).then(() => {
                                        // Atualiza a interface sem recarregar
                                        const btn = $(`button[data-id="${id}"][data-tabela="${tabela}"].alterar-status`);
                                        const novoStatus = btn.data("status-atual") === 'ativo' ? 'inativo' : 'ativo';

                                        // Atualiza os dados do botão
                                        btn.data("status-atual", novoStatus);

                                        // Atualiza a aparência do botão de status
                                        btn.removeClass('btn-danger btn-secondary')
                                           .addClass(novoStatus === 'ativo' ? 'btn-danger' : 'btn-secondary');

                                        // Atualiza o ícone e texto do botão de status
                                        btn.find('i').attr('class', `fas fa-${novoStatus === 'ativo' ? 'times' : 'check'}`);
                                        btn.find('span').text(novoStatus === 'ativo' ? 'Desativar' : 'Ativar');

                                        // Atualiza o badge de status
                                        const badge = btn.closest('tr').find('.status-badge');
                                        badge.removeClass('status-ativo status-inativo')
                                             .addClass(`status-${novoStatus}`)
                                             .text(novoStatus === 'ativo' ? 'Ativo' : 'Inativo');
                                    });
                                } else {
                                    Swal.fire(
                                        'Erro!',
                                        response.message,
                                        'error'
                                    );
                                }
                            },
                            error: function() {
                                Swal.fire(
                                    'Erro!',
                                    'Ocorreu um erro ao tentar alterar o status.',
                                    'error'
                                );
                            }
                        });
                    }
                });
            });

            // Função para excluir empenho via AJAX
            $(document).on('click', '.excluir-empenho', function() {
                const id = $(this).data("id");
                const tabela = $(this).data("tabela");
                const numeroEmpenho = $(this).data("numero");

                Swal.fire({
                    title: 'Confirmar exclusão',
                    html: `Você realmente deseja excluir o empenho <b>${numeroEmpenho}</b>?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sim, excluir!',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '<?= $_SERVER['PHP_SELF'] ?>',
                            method: 'POST',
                            data: {
                                excluir_id: id,
                                tabela: tabela,
                                numero_empenho: numeroEmpenho
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire(
                                        'Excluído!',
                                        response.message,
                                        'success'
                                    ).then(() => {
                                        // Remove a linha da tabela
                                        $(`button[data-id="${id}"][data-tabela="${tabela}"]`).closest('tr').remove();
                                    });
                                } else {
                                    Swal.fire(
                                        'Erro!',
                                        response.message,
                                        'error'
                                    );
                                }
                            },
                            error: function() {
                                Swal.fire(
                                    'Erro!',
                                    'Ocorreu um erro ao tentar excluir o empenho.',
                                    'error'
                                );
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
