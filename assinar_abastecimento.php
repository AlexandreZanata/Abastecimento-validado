<?php
// Definir o fuso horário de Cuiabá/Mato Grosso (-4 horas em relação ao UTC)
date_default_timezone_set('America/Cuiaba');

session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Atualização do mapeamento de secretarias
$secretarias_map = [
    "Gabinete do Prefeito" => "gabinete do prefeito",
    "Gabinete do Vice-Prefeito" => "gabinete do vice-prefeito",
    "Secretaria Municipal da Mulher de Família" => "secretaria da mulher",
    "Secretaria Municipal de Fazenda" => "secretaria de fazenda",
    "Secretaria Municipal de Educação" => "secretaria de educação",
    "Secretaria Municipal de Agricultura e Meio Ambiente" => "secretaria de agricultura e meio ambiente",
    "Secretaria Municipal de Agricultura Familiar e Segurança Alimentar" => "secretaria de agricultura familiar",
    "Secretaria Municipal de Assistência Social" => "secretaria de assistência social",
    "Secretaria Municipal de Desenvolvimento Econômico e Turismo" => "secretaria de desenvolv. econômico",
    "Secretaria Municipal de Administração" => "secretaria de administração",
    "Secretaria Municipal de Governo" => "secretaria de governo",
    "Secretaria Municipal de Infraestrutura, Transportes e Saneamento" => "secretaria municipal de infraestrutura, transportes e saneamento", // Corrigido para corresponder exatamente ao formato no banco
    "Secretaria Municipal de Esporte e Lazer e Juventude" => "secretaria municipal de esporte e lazer e juventude",
    "Secretaria Municipal da Cidade" => "secretaria da cidade",
    "Secretaria Municipal de Saúde" => "secretaria de saúde",
    "Secretaria Municipal de Segurança Pública, Trânsito e Defesa Civil" => "secretaria de segurança pública",
    "Controladoria Geral do Município" => "controladoria geral",
    "Procuradoria Geral do Município" => "procuradoria geral",
    "Secretaria Municipal de Cultura" => "secretaria de cultura",
    "Secretaria Municipal de Planejamento, Ciência, Tecnologia e Inovação" => "secretaria de planejamento e tecnologia",
    "Secretaria Municipal de Obras e Serviços Públicos" => "secretaria de obras e serviços públicos",
];

$abastecimento_id = $_GET['id'] ?? 0;

try {
    // Buscar dados do abastecimento
    $stmt = $conn->prepare("SELECT ap.*, u.name as posto_name
                           FROM abastecimentos_pendentes ap
                           JOIN usuarios u ON ap.posto_id = u.id
                           WHERE ap.id = :id AND ap.motorista_id = :user_id
                           AND ap.status = 'aguardando_assinatura'");
    $stmt->bindParam(':id', $abastecimento_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $abastecimento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$abastecimento) {
        die("Abastecimento não encontrado ou não está pronto para assinatura.");
    }

    // Buscar o valor do prefixo na tabela 'veiculos'
    $stmt_veiculo = $conn->prepare("SELECT veiculo as prefixo FROM veiculos WHERE id = :veiculo_id");
    $stmt_veiculo->bindParam(':veiculo_id', $abastecimento['veiculo_id']);
    $stmt_veiculo->execute();
    $veiculo_data = $stmt_veiculo->fetch(PDO::FETCH_ASSOC);

    // Definir $prefixo com o resultado da consulta, ou um valor padrão para evitar erros
    $prefixo = $veiculo_data['prefixo'] ?? 'Não definido';

    // Processar assinatura
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Processar upload da nota fiscal
        $nota_fiscal_path = '';
        if (isset($_FILES['nota_fiscal']) && $_FILES['nota_fiscal']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/notas_fiscais/';
            if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);

            $extensao = pathinfo($_FILES['nota_fiscal']['name'], PATHINFO_EXTENSION);
            $nomeArquivo = uniqid('nota_') . '.' . $extensao;
            $caminhoCompleto = $uploadDir . $nomeArquivo;

            if (move_uploaded_file($_FILES['nota_fiscal']['tmp_name'], $caminhoCompleto)) {
                $nota_fiscal_path = $caminhoCompleto;
            }
        }

        // Inserir na tabela registro_abastecimento
        $stmt = $conn->prepare("INSERT INTO registro_abastecimento
                               (nome, data, hora, prefixo, placa, veiculo, secretaria,
                                km_abastecido, litros, combustivel, posto_gasolina, valor, nota_fiscal)
                               VALUES
                               (:nome, :data, :hora, :prefixo, :placa, :veiculo, :secretaria,
                                :km_abastecido, :litros, :combustivel, :posto_gasolina, :valor, :nota_fiscal)");

        // Obter data e hora atual no fuso horário de Cuiabá
        $data_atual = date('Y-m-d');
        $hora_atual = date('H:i:s');

        $stmt->bindParam(':nome', $abastecimento['motorista_name']);
        $stmt->bindParam(':data', $data_atual);
        $stmt->bindParam(':hora', $hora_atual);
        $stmt->bindParam(':prefixo', $prefixo);
        $stmt->bindParam(':placa', $abastecimento['placa']);
        $stmt->bindParam(':veiculo', $abastecimento['veiculo_nome']);
        $stmt->bindParam(':secretaria', $abastecimento['secretaria']);
        $stmt->bindParam(':km_abastecido', $abastecimento['km_abastecido']);
        $stmt->bindParam(':litros', $abastecimento['litros']);
        $stmt->bindParam(':combustivel', $abastecimento['combustivel']);
        $stmt->bindParam(':posto_gasolina', $abastecimento['posto_name']);
        $stmt->bindParam(':valor', $abastecimento['valor']);
        $stmt->bindParam(':nota_fiscal', $nota_fiscal_path);

        if ($stmt->execute()) {
            // Atualizar status do abastecimento pendente
            $conn->prepare("UPDATE abastecimentos_pendentes SET status = 'concluido', data_assinatura = NOW() WHERE id = :id")
                 ->execute([':id' => $abastecimento_id]);

            // Registrar o abastecimento na secretaria
            $secretaria = $abastecimento['secretaria'];
            $valor = $abastecimento['valor'];
            $combustivel = $abastecimento['combustivel'];
            $posto_name = $abastecimento['posto_name'];

            // Converter para o formato do banco de dados
            $coluna_combustivel = '';
            $combustivel = trim($combustivel); // Remove espaços extras
            switch(strtolower($combustivel)) { // Converte para minúsculas
                case 'etanol': $coluna_combustivel = 'valor_etanol'; break;
                case 'gasolina': $coluna_combustivel = 'valor_gasolina'; break;
                case 'diesel': $coluna_combustivel = 'valor_diesel'; break;
                case 'diesel s10': case 'diesel-s10': case 'diesels10': $coluna_combustivel = 'valor_diesel_s10'; break;
            }

            if (!empty($coluna_combustivel)) {
                try {
                    $conn->beginTransaction();

                    // Converter o nome da secretaria do veículo para o formato usado no empenhos_secretarias
                    $secretaria_original = $abastecimento['secretaria'];
                    $secretaria_bd = null;

                    // 1. Primeiro tenta uma correspondência direta no mapa
                    if (array_key_exists($secretaria_original, $secretarias_map)) {
                        $secretaria_bd = $secretarias_map[$secretaria_original];
                    } else {
                        // 2. Se não encontrou, tenta uma correspondência sem case-sensitive
                        foreach ($secretarias_map as $key => $value) {
                            if (strcasecmp($key, $secretaria_original) === 0) {
                                $secretaria_bd = $value;
                                break;
                            }
                        }

                        // 3. Se ainda não encontrou, faz uma busca no banco para encontrar a secretaria correta
                        if (!$secretaria_bd) {
                            $stmt_secretarias = $conn->prepare("
                                SELECT DISTINCT secretaria
                                FROM empenhos_secretarias
                                WHERE secretaria LIKE :secretaria_like
                                AND fornecedor = :fornecedor
                                AND status = 'ativo'
                                LIMIT 1
                            ");
                            $search_term = "%" . str_replace(" Municipal ", "%", $secretaria_original) . "%";
                            $stmt_secretarias->bindParam(':secretaria_like', $search_term);
                            $stmt_secretarias->bindParam(':fornecedor', $posto_name);
                            $stmt_secretarias->execute();
                            $resultado_secretaria = $stmt_secretarias->fetch(PDO::FETCH_ASSOC);

                            if ($resultado_secretaria) {
                                $secretaria_bd = $resultado_secretaria['secretaria'];
                            } else {
                                // 4. Se ainda não encontrou, usa o original
                                $secretaria_bd = $secretaria_original;
                            }
                        }
                    }

                    // Adicionar log para debug
                    error_log("Mapeamento de secretaria: Original=$secretaria_original, Mapeada=$secretaria_bd");

                    // Busca por diferentes variações da secretaria e do posto
                    $stmt = $conn->prepare("
                        SELECT id, id_empenho_total, valor_total, $coluna_combustivel
                        FROM empenhos_secretarias
                        WHERE (
                            secretaria = :secretaria_exata OR
                            UPPER(secretaria) = UPPER(:secretaria_exata)
                        )
                        AND (
                            fornecedor = :fornecedor OR
                            UPPER(fornecedor) = UPPER(:fornecedor)
                        )
                        AND status = 'ativo'
                        AND $coluna_combustivel >= :valor
                        LIMIT 1
                    ");
                    $stmt->bindParam(':secretaria_exata', $secretaria_bd);
                    $stmt->bindParam(':fornecedor', $posto_name);
                    $stmt->bindParam(':valor', $valor);
                    $stmt->execute();

                    $empenho_sec = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Se não encontrou, tenta uma busca mais ampla
                    if (!$empenho_sec) {
                        // Exibir todos os empenhos ativos para debug
                        $stmt_debug = $conn->prepare("
                            SELECT id, secretaria, fornecedor, $coluna_combustivel, valor_total
                            FROM empenhos_secretarias
                            WHERE status = 'ativo'
                            ORDER BY secretaria, fornecedor
                        ");
                        $stmt_debug->execute();
                        $todos_empenhos = $stmt_debug->fetchAll(PDO::FETCH_ASSOC);

                        $empenhos_disponiveis = [];
                        foreach ($todos_empenhos as $emp) {
                            $empenhos_disponiveis[] = "ID: {$emp['id']}, Secretaria: {$emp['secretaria']}, Fornecedor: {$emp['fornecedor']}, Saldo: {$emp[$coluna_combustivel]}";
                        }

                        $empenhos_disponiveis_str = implode("\n", $empenhos_disponiveis);
                        error_log("Empenhos disponíveis no sistema:\n$empenhos_disponiveis_str");

                        throw new Exception("Não foi encontrado empenho ativo com saldo suficiente para a secretaria '$secretaria_bd' no posto '$posto_name'. Por favor, verifique se há empenho cadastrado para esta combinação de secretaria e posto.");
                    }

                    // O resto do código permanece o mesmo...
                    // Continuar com as atualizações e verificações como antes

                    // 2. Atualizar valores em empenhos_secretarias
                    $update_sec = $conn->prepare("
                        UPDATE empenhos_secretarias
                        SET $coluna_combustivel = $coluna_combustivel - :valor,
                            valor_total = valor_total - :valor
                        WHERE id = :id
                    ");
                    $update_sec->bindParam(':valor', $valor);
                    $update_sec->bindParam(':id', $empenho_sec['id']);

                    if (!$update_sec->execute() || $update_sec->rowCount() == 0) {
                        throw new Exception("Falha ao descontar valores no empenho da secretaria");
                    }

                    // 3. Buscar e atualizar empenhos_totais
                    $id_empenho_total = $empenho_sec['id_empenho_total'];

                    // Verificar se id_empenho_total é numérico ou string
                    if (is_numeric($id_empenho_total)) {
                        // Buscar por ID numérico
                        $stmt_total = $conn->prepare("
                            SELECT id, valor_total
                            FROM empenhos_totais
                            WHERE id = :id_empenho
                            AND status = 'ativo'
                        ");
                        $stmt_total->bindParam(':id_empenho', $id_empenho_total);
                    } else {
                        // Buscar pelo valor do campo id_empenho_total
                        $stmt_total = $conn->prepare("
                            SELECT id, valor_total
                            FROM empenhos_totais
                            WHERE numero_empenho = :numero_empenho
                            AND status = 'ativo'
                        ");
                        $stmt_total->bindParam(':numero_empenho', $id_empenho_total);
                    }

                    $stmt_total->execute();
                    $empenho_total = $stmt_total->fetch(PDO::FETCH_ASSOC);

                    if (!$empenho_total) {
                        throw new Exception("Empenho total correspondente não encontrado (ID/Número: $id_empenho_total)");
                    }

                    // 4. Atualizar empenhos_totais
                    $update_total = $conn->prepare("
                        UPDATE empenhos_totais
                        SET valor_total = valor_total - :valor
                        WHERE id = :id
                    ");
                    $update_total->bindParam(':valor', $valor);
                    $update_total->bindParam(':id', $empenho_total['id']);

                    if (!$update_total->execute() || $update_total->rowCount() == 0) {
                        throw new Exception("Falha ao descontar valor do empenho total");
                    }

                    $conn->commit();
                    $success_message = "Abastecimento registrado com sucesso e valores descontados dos empenhos!";

                    // Se chegou aqui, o processo foi concluído com sucesso
                    header("Location: autorizar_abastecimento.php?success=1");
                    exit();

                } catch (Exception $e) {
                    $conn->rollBack();
                    $error_message = "Erro ao processar desconto dos empenhos: " . $e->getMessage();
                    error_log("Erro no desconto de empenhos: " . $e->getMessage());
                }
            } else {
                $error_message = "Tipo de combustível inválido ou não identificado: $combustivel";
                error_log("Combustível inválido: $combustivel");
            }

            // Se chegou aqui, houve um erro, então não redirecionamos
            if (isset($error_message)) {
                // Preparar para exibir o erro na página
                $show_error = true;
            }
        } else {
            $error = "Erro ao assinar abastecimento.";
        }
    }

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Assinar Abastecimento</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#4F46E5',
                        'primary-dark': '#4338CA',
                        'secondary': '#F59E0B',
                        'accent': '#10B981',
                        'success': '#10B981',
                        'warning': '#F59E0B',
                        'danger': '#EF4444',
                    },
                    boxShadow: {
                        'soft': '0 4px 24px -6px rgba(0, 0, 0, 0.1)',
                        'hard': '0 8px 24px -6px rgba(79, 70, 229, 0.3)'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            -webkit-tap-highlight-color: transparent;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8fafc;
        }
        .app-container {
            max-width: 480px;
            height: 100dvh;
            margin: 0 auto;
            background: white;
            position: relative;
            overflow: hidden;
        }
        .logo-container {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            margin-bottom: 10px;
        }
        .forms-container {
            height: calc(100dvh - 12rem);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        .forms-container::-webkit-scrollbar {
            display: none;
        }
        .nav-button {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.2s ease;
        }
        .nav-button:hover {
            transform: scale(1.05);
        }
        .input-field {
            transition: all 0.2s ease;
            position: relative;
        }
        .input-field:focus-within {
            border-color: #10B981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        .btn-primary {
            background-color: #10B981;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(16, 185, 129, 0.25);
        }
        .input-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
            width: 1.25rem;
            text-align: center;
        }
        .message-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 600px;
            text-align: center;
            background: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            font-size: 16px;
            font-weight: 500;
            opacity: 1;
            transition: opacity 0.5s ease-in-out;
            z-index: 9999;
        }
        .success {
            border-left: 5px solid #10B981;
            color: #10B981;
        }
        .error {
            border-left: 5px solid #EF4444;
            color: #EF4444;
        }
        #notaFiscalContainer:hover {
            background-color: #f3f4f6;
            border-color: #10B981;
        }
        #notaFiscalInput {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .abastecimento-info {
            background-color: #f9fafb;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .abastecimento-info p {
            margin-bottom: 8px;
            font-size: 15px;
        }
        .abastecimento-info strong {
            color: #4b5563;
        }

        /* Adicione este estilo para a mensagem de erro personalizada */
        .error-message {
            background-color: #FEF2F2;
            border-left: 5px solid #EF4444;
            padding: 12px 16px;
            margin-bottom: 16px;
            border-radius: 8px;
        }

        .error-message h4 {
            color: #B91C1C;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .error-message p {
            color: #7F1D1D;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="app-container relative">
        <!-- Logo Header -->
        <div class="logo-container h-48 rounded-b-3xl flex flex-col items-center justify-center shadow-hard relative">
            <div class="bg-white/20 p-4 rounded-full mb-4">
                <i class="fas fa-signature text-white text-4xl"></i>
            </div>
            <h1 class="text-white text-2xl font-bold">Assinar Abastecimento</h1>
            <a href="autorizar_abastecimento.php" class="nav-button bg-white absolute top-6 left-6 cursor-pointer flex items-center justify-center w-10 h-10 rounded-full shadow-soft hover:shadow-hard transition-shadow">
                <i class="fas fa-chevron-left text-primary text-lg"></i>
            </a>
        </div>

        <!-- Forms Container -->
        <div class="forms-container px-5 pb-6 -mt-10 relative">
            <div class="bg-white rounded-2xl p-6 shadow-hard">
                <?php if (isset($show_error) && isset($error_message)): ?>
                    <div class="error-message">
                        <h4><i class="fas fa-exclamation-triangle mr-2"></i>Não foi possível completar a operação</h4>
                        <p><?= htmlspecialchars($error_message) ?></p>
                    </div>
                <?php endif; ?>

                <div class="abastecimento-info">
                    <p><strong>Posto:</strong> <?= $abastecimento['posto_name'] ?></p>
                    <p><strong>Prefixo:</strong> <?= htmlspecialchars($prefixo) ?></p>
                    <p><strong>Veículo:</strong> <?= $abastecimento['veiculo_nome'] ?> - <?= $abastecimento['placa'] ?></p>
                    <p><strong>KM:</strong> <?= $abastecimento['km_abastecido'] ?></p>
                    <p><strong>Litros:</strong> <?= $abastecimento['litros'] ?></p>
                    <p><strong>Combustível:</strong> <?= $abastecimento['combustivel'] ?></p>
                    <p><strong>Valor:</strong> R$ <?= number_format($abastecimento['valor'], 2, ',', '.') ?></p>
                    <!-- Adicionar informação da secretaria para debug -->
                    <?php if (isset($secretaria_bd)): ?>
                    <p><strong>Secretaria:</strong> <?= htmlspecialchars($secretaria_bd) ?></p>
                    <?php endif; ?>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nota Fiscal (Opcional)</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                            <div id="notaFiscalContainer" class="flex flex-col items-center justify-center py-4 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer" onclick="document.getElementById('notaFiscalInput').click()">
                                <i class="fas fa-camera text-3xl text-success mb-2"></i>
                                <span class="text-sm text-gray-600">Clique para adicionar foto da nota fiscal</span>
                                <input type="file" id="notaFiscalInput" name="nota_fiscal" accept="image/*" class="hidden">
                            </div>
                            <div id="previewContainer" class="mt-3 hidden">
                                <img id="previewImage" src="#" alt="Preview da nota fiscal" class="max-h-40 mx-auto rounded-lg">
                                <button type="button" id="removeImageBtn" class="mt-2 text-sm text-danger flex items-center justify-center gap-1">
                                    <i class="fas fa-trash"></i> Remover foto
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3 px-4 bg-success text-white font-medium rounded-xl hover:bg-green-700 transition duration-200 flex items-center justify-center gap-2">
                        <i class="fas fa-signature"></i>
                        <span>Assinar Abastecimento</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Mostra preview da imagem selecionada
        document.getElementById('notaFiscalInput').addEventListener('change', function() {
            const previewContainer = document.getElementById('previewContainer');
            const previewImage = document.getElementById('previewImage');
            const notaFiscalContainer = document.getElementById('notaFiscalContainer');

            if (this.files && this.files[0]) {
                const file = this.files[0];

                // Verifica se é uma imagem
                if (!file.type.match('image.*')) {
                    alert('Por favor, selecione uma imagem!');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewContainer.classList.remove('hidden');
                    notaFiscalContainer.classList.add('hidden');
                };
                reader.readAsDataURL(file);
            }
        });

        // Remove a imagem selecionada
        document.getElementById('removeImageBtn').addEventListener('click', function() {
            document.getElementById('notaFiscalInput').value = '';
            document.getElementById('previewContainer').classList.add('hidden');
            document.getElementById('notaFiscalContainer').classList.remove('hidden');
        });

        // Desativar botão após clique
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Processando...</span>';
            submitBtn.classList.remove('bg-success', 'hover:bg-green-700');
            submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
        });
    </script>
</body>
</html>
