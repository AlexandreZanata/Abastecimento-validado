<?php
session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Verifica se o usuário é motorista
if ($_SESSION['role'] != 'user') {
    header("Location: ../unauthorized.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$secretaria = $_SESSION['secretaria'];

try {
    // Buscar dados do veículo do usuário
    $stmt = $conn->prepare("SELECT v.id, v.veiculo, v.tipo, v.placa, v.tanque 
                           FROM veiculos v
                           JOIN usuarios u ON v.id = u.codigo_veiculo
                           WHERE u.id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $veiculo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$veiculo) {
        die("Nenhum veículo vinculado ao usuário.");
    }

    // Buscar postos disponíveis (usuários com role 'posto')
    $postos = $conn->query("SELECT id, name FROM usuarios WHERE role = 'posto'")->fetchAll(PDO::FETCH_ASSOC);

    // Processar autorização de abastecimento
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['autorizar_abastecimento'])) {
        $posto_id = $_POST['posto_id'];
        $km_abastecido = $_POST['km_abastecido'];

        // Inserir na tabela de abastecimentos pendentes
        $stmt = $conn->prepare("INSERT INTO abastecimentos_pendentes 
                               (motorista_id, motorista_name, veiculo_id, veiculo_nome, placa, posto_id, 
                                km_abastecido, secretaria, status, data_criacao)
                               VALUES 
                               (:motorista_id, :motorista_name, :veiculo_id, :veiculo_nome, :placa, :posto_id, 
                                :km_abastecido, :secretaria, 'aguardando_posto', NOW())");
        
        $stmt->bindParam(':motorista_id', $user_id);
        $stmt->bindParam(':motorista_name', $user_name);
        $stmt->bindParam(':veiculo_id', $veiculo['id']);
        $stmt->bindParam(':veiculo_nome', $veiculo['tipo']);
        $stmt->bindParam(':placa', $veiculo['placa']);
        $stmt->bindParam(':posto_id', $posto_id);
        $stmt->bindParam(':km_abastecido', $km_abastecido);
        $stmt->bindParam(':secretaria', $secretaria);
        
        if ($stmt->execute()) {
            $msg = "Abastecimento autorizado com sucesso! Aguarde o posto preencher os dados.";
        } else {
            $msg = "Erro ao autorizar abastecimento.";
        }
    }

    // Buscar abastecimentos pendentes do usuário
    $abastecimentos = $conn->prepare("SELECT ap.*, u.name as posto_name 
                                     FROM abastecimentos_pendentes ap
                                     JOIN usuarios u ON ap.posto_id = u.id
                                     WHERE ap.motorista_id = :user_id 
                                     AND ap.status IN ('aguardando_posto', 'aguardando_assinatura')
                                     ORDER BY ap.data_criacao DESC");
    $abastecimentos->bindParam(':user_id', $user_id);
    $abastecimentos->execute();
    $abastecimentos = $abastecimentos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Autorizar Abastecimento</title>
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
        .error-text {
            color: #EF4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
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
        .abastecimento-item {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 0.5rem;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="app-container relative">
        <!-- Logo Header -->
        <div class="logo-container h-48 rounded-b-3xl flex flex-col items-center justify-center shadow-hard relative">
            <div class="bg-white/20 p-4 rounded-full mb-4">
                <i class="fas fa-gas-pump text-white text-4xl"></i>
            </div>
            <h1 class="text-white text-2xl font-bold">Autorizar Abastecimento</h1>
        </div>

        <!-- Forms Container -->
        <div class="forms-container px-5 pb-6 -mt-10 relative">
            <div class="bg-white rounded-2xl p-6 shadow-hard">
                <?php if (isset($msg)): ?>
                    <div class="mb-4 p-3 bg-blue-100 text-blue-800 rounded"><?= $msg ?></div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Veículo</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                            <input type="text" class="w-full bg-transparent focus:outline-none" 
                                   value="<?= $veiculo['tipo'] ?> - <?= $veiculo['placa'] ?>" readonly>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">KM Atual</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                            <input type="number" name="km_abastecido" class="w-full bg-transparent focus:outline-none" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Posto de Combustível</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                            <select name="posto_id" class="w-full bg-transparent focus:outline-none appearance-none" required>
                                <option value="">Selecione um posto</option>
                                <?php foreach ($postos as $posto): ?>
                                    <option value="<?= $posto['id'] ?>"><?= $posto['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" name="autorizar_abastecimento" 
                            class="w-full py-3 px-4 bg-success text-white font-medium rounded-xl hover:bg-green-700 transition duration-200 flex items-center justify-center gap-2">
                        <i class="fas fa-check-circle"></i>
                        <span>Autorizar Abastecimento</span>
                    </button>
                </form>

                <!-- Abastecimentos Pendentes integrados no mesmo container -->
                <div class="mt-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-3">Abastecimentos Pendentes</h2>
                    
                    <?php if (empty($abastecimentos)): ?>
                        <p class="text-gray-500 text-sm">Nenhum abastecimento pendente.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($abastecimentos as $abastecimento): ?>
                                <div class="abastecimento-item" data-id="<?= $abastecimento['id'] ?>">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium text-gray-800">Posto: <?= $abastecimento['posto_name'] ?></p>
                                            <p class="text-sm text-gray-600">KM: <?= $abastecimento['km_abastecido'] ?></p>
                                            <p class="text-sm">
                                                Status: 
                                                <span class="<?= 
                                                    $abastecimento['status'] == 'aguardando_posto' ? 'text-yellow-600' : 
                                                    ($abastecimento['status'] == 'aguardando_assinatura' ? 'text-blue-600' : 'text-gray-600')
                                                ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $abastecimento['status'])) ?>
                                                </span>
                                            </p>
                                        </div>
                                        
                                        <?php if ($abastecimento['status'] == 'aguardando_assinatura'): ?>
                                            <a href="assinar_abastecimento.php?id=<?= $abastecimento['id'] ?>" 
                                               class="py-1 px-3 bg-success text-white text-xs font-medium rounded-lg hover:bg-green-700 transition duration-200 flex items-center justify-center gap-1">
                                               <i class="fas fa-signature text-xs"></i>
                                               <span>Assinar</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($abastecimento['status'] == 'aguardando_assinatura'): ?>
                                        <div class="mt-2 pt-2 border-t border-gray-200">
                                            <p class="text-sm"><span class="font-medium">Litros:</span> <?= $abastecimento['litros'] ?? '--' ?></p>
                                            <p class="text-sm"><span class="font-medium">Combustível:</span> <?= $abastecimento['combustivel'] ?? '--' ?></p>
                                            <p class="text-sm"><span class="font-medium">Valor:</span> R$ <?= number_format($abastecimento['valor'] ?? 0, 2, ',', '.') ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Função para atualizar apenas os status dos abastecimentos
        function atualizarStatusAbastecimentos() {
            fetch('buscar_abastecimentos.php')
                .then(response => response.json())
                .then(data => {
                    data.forEach(abastecimento => {
                        const item = document.querySelector(`.abastecimento-item[data-id="${abastecimento.id}"]`);
                        if (item) {
                            // Atualiza o status
                            const statusElement = item.querySelector('.status-text');
                            if (statusElement) {
                                statusElement.textContent = abastecimento.status;
                                statusElement.className = 'text-sm ' + 
                                    (abastecimento.status === 'aguardando_posto' ? 'text-yellow-600' : 
                                     (abastecimento.status === 'aguardando_assinatura' ? 'text-blue-600' : 'text-gray-600'));
                            }
                            
                            // Atualiza o botão de assinar se necessário
                            const assinarBtn = item.querySelector('.assinar-btn');
                            if (assinarBtn) {
                                if (abastecimento.status === 'aguardando_assinatura') {
                                    assinarBtn.classList.remove('hidden');
                                } else {
                                    assinarBtn.classList.add('hidden');
                                }
                            }
                            
                            // Atualiza detalhes se estiver aguardando assinatura
                            if (abastecimento.status === 'aguardando_assinatura') {
                                const detalhesDiv = item.querySelector('.detalhes-abastecimento');
                                if (detalhesDiv) {
                                    detalhesDiv.innerHTML = `
                                        <div class="mt-2 pt-2 border-t border-gray-200">
                                            <p class="text-sm"><span class="font-medium">Litros:</span> ${abastecimento.litros || '--'}</p>
                                            <p class="text-sm"><span class="font-medium">Combustível:</span> ${abastecimento.combustivel || '--'}</p>
                                            <p class="text-sm"><span class="font-medium">Valor:</span> R$ ${abastecimento.valor ? parseFloat(abastecimento.valor).toFixed(2).replace('.', ',') : '0,00'}</p>
                                        </div>
                                    `;
                                }
                            }
                        }
                    });
                })
                .catch(error => console.error('Erro:', error));
        }

        // Atualizar a cada 5 segundos
        setInterval(atualizarStatusAbastecimentos, 5000);

        // Disparar também quando a página carrega
        document.addEventListener('DOMContentLoaded', atualizarStatusAbastecimentos);
    </script>
</body>
</html>