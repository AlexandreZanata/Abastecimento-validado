<?php
session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SESSION['role'] != 'posto') {
    header("Location: ../unauthorized.php");
    exit();
}

$posto_id = $_SESSION['user_id'];

try {
    // Buscar preços dos combustíveis (adicionar esta consulta)
    $precos_combustiveis = $conn->query("SELECT posto_nome, tipo_combustivel, preco FROM postos_precos")->fetchAll(PDO::FETCH_ASSOC);
    $precos_json = json_encode($precos_combustiveis);

    // Buscar saldos das secretarias - MODIFICADO
    $secretarias_saldos = $conn->query("
        SELECT
            secretaria,
            valor_etanol,
            valor_gasolina,
            valor_diesel,
            valor_diesel_s10,
            status
        FROM empenhos_secretarias
    ")->fetchAll(PDO::FETCH_ASSOC);
    $saldos_json = json_encode($secretarias_saldos);

    // Inicializar variáveis de saldo com zero
    $saldo_gasolina = 0;
    $saldo_etanol = 0;
    $saldo_diesel = 0;
    $saldo_diesel_s10 = 0;

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

    // Processar preenchimento do abastecimento
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['preencher_abastecimento'])) {
            $abastecimento_id = $_POST['abastecimento_id'];
            $litros = str_replace(',', '.', $_POST['litros']);
            $combustivel = $_POST['combustivel'];
            $valor = str_replace(',', '.', $_POST['valor']);

            // Validar litros (usando a capacidade do tanque do veículo)
            $stmt = $conn->prepare("SELECT v.tanque FROM abastecimentos_pendentes ap
                                   JOIN veiculos v ON ap.veiculo_id = v.id
                                   WHERE ap.id = :id");
            $stmt->bindParam(':id', $abastecimento_id);
            $stmt->execute();
            $tanque = $stmt->fetchColumn();

            if ($litros > $tanque) {
                $error = "A quantidade de litros não pode exceder a capacidade do tanque ($tanque litros).";
            } else {
                // Verificar status do empenho e saldo da secretaria
                $secretaria = $conn->query("SELECT secretaria FROM usuarios WHERE id = (SELECT motorista_id FROM abastecimentos_pendentes WHERE id = $abastecimento_id)")->fetchColumn();
                $status_empenho = $conn->query("SELECT status FROM empenhos_secretarias WHERE secretaria = '$secretaria'")->fetchColumn();

                if ($status_empenho != 'ativo') {
                    $error = "O empenho desta secretaria está inativo. Não é possível realizar abastecimentos.";
                } else {
                    // Mapeia os possíveis valores de combustível para os nomes de colunas corretos no banco de dados
                $mapa_combustiveis = [
                    'Gasolina' => 'valor_gasolina',
                    'Etanol' => 'valor_etanol',
                    'Diesel' => 'valor_diesel',
                    'Diesel S10' => 'valor_diesel_s10',
                    'Diesel-S10' => 'valor_diesel_s10', // Inclui o caso com o hífen
                ];

                // Verifica se o combustível fornecido está no mapa
                if (array_key_exists($combustivel, $mapa_combustiveis)) {
                    $coluna_combustivel = $mapa_combustiveis[$combustivel];

                    // Consulta o saldo com o nome da coluna mapeada
                    $saldo = $conn->query("SELECT $coluna_combustivel FROM empenhos_secretarias WHERE secretaria = '$secretaria'")->fetchColumn();
                } else {
                    // Retorna erro se o combustível for inválido
                    die("Erro: Tipo de combustível inválido.");
                }

                    if ($valor > $saldo) {
                        $error = "A secretaria não possui saldo suficiente para este abastecimento (Saldo disponível: R$ " . number_format($saldo, 2, ',', '.') . ")";
                    } else {
                        // Atualizar abastecimento
                        $stmt = $conn->prepare("UPDATE abastecimentos_pendentes
                                               SET litros = :litros,
                                                   combustivel = :combustivel,
                                                   valor = :valor,
                                                   status = 'aguardando_assinatura',
                                                   data_preenchimento = NOW()
                                               WHERE id = :id");
                        $stmt->bindParam(':litros', $litros);
                        $stmt->bindParam(':combustivel', $combustivel);
                        $stmt->bindParam(':valor', $valor);
                        $stmt->bindParam(':id', $abastecimento_id);

                        if ($stmt->execute()) {
                            $success = "Abastecimento registrado com sucesso! Aguardando assinatura do motorista.";
                            // Recarregar os dados após atualização
                            header("Location: posto_abastecimento.php");
                            exit();
                        } else {
                            $error = "Erro ao registrar abastecimento.";
                        }
                    }
                }
            }
        }
    }

    // Buscar abastecimentos já preenchidos (aguardando assinatura ou concluídos)
    $abastecimentos_preenchidos = $conn->prepare("SELECT ap.*, u.name as motorista_name, u.cpf as motorista_cpf,
                                                 u.secretaria as motorista_secretaria, u.profile_photo as motorista_foto,
                                                 u.codigo_veiculo, vei.veiculo as nome_veiculo
                                                 FROM abastecimentos_pendentes ap
                                                 JOIN usuarios u ON ap.motorista_id = u.id
                                                 LEFT JOIN veiculos vei ON u.codigo_veiculo = vei.id
                                                 WHERE ap.posto_id = :posto_id
                                                 AND (ap.status = 'aguardando_assinatura' OR ap.status = 'concluido')
                                                 ORDER BY
                                                     CASE WHEN ap.status = 'aguardando_assinatura' THEN 0 ELSE 1 END,
                                                     ap.data_preenchimento DESC");
    $abastecimentos_preenchidos->bindParam(':posto_id', $posto_id);
    $abastecimentos_preenchidos->execute();
    $abastecimentos_preenchidos = $abastecimentos_preenchidos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Posto - Registrar Abastecimento</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        .btn-secondary {
            background-color: #F59E0B;
            transition: all 0.2s ease;
        }
        .btn-secondary:hover {
            background-color: #D97706;
            transform: translateY(-1px);
            box-shadow: 0 6px 12px rgba(245, 158, 11, 0.25);
        }
        .logo-container {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
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
        @media (max-width: 640px) {
            .mobile-flex-col {
                flex-direction: column;
            }
        }
        .assinatura-status {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: #10B981;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .concluido {
            border-left: 4px solid #10B981;
        }
        .aguardando {
            border-left: 4px solid #F59E0B;
        }

        .custom-alert {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 500px;
            padding: 15px 20px;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            z-index: 10000;
            border-left: 4px solid #EF4444;
            animation: slideIn 0.3s ease-out forwards;
        }

        .custom-alert.error {
            border-left-color: #EF4444;
            color: #EF4444;
        }

        .custom-alert i {
            margin-right: 12px;
            font-size: 20px;
        }

        .custom-alert .close-btn {
            margin-left: auto;
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 16px;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }

        .btn-excluir {
            position: absolute; /* Mantém o posicionamento absoluto */
            top: 1rem;
            right: 1rem;
            color: #9CA3AF;
            transition: all 0.2s ease;
            z-index: 10; /* Ajuste o índice Z para evitar conflitos */
            background-color: white;
            padding: 0.5rem;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: none; /* Esconde o botão até a interação */
        }

        .btn-excluir:hover {
            color: #EF4444;
            transform: scale(1.1);
        }

        .abastecimento-item:hover .btn-excluir {
            display: block; /* Mostra o botão apenas ao passar o mouse no contêiner */
        }

        .info-container {
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .motorista-photo-container {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        .motorista-photo {
            width: 300px;
            height: 300px;
            border-radius: 8px;
            object-fit: cover;
            border: 3px solid #e5e7eb;
        }

        .motorista-photo-label {
            text-align: center;
            margin-bottom: 1rem;
            font-size: 1.25rem;
            font-weight: 600;
            color: #4b5563;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .abastecimento-item {
            transition: all 0.2s ease;
        }

        .abastecimento-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>

    <script>
        // Variáveis globais para preços e saldos
        let precosCombustiveis = <?php echo $precos_json; ?>;
        let saldosSecretarias = <?php echo $saldos_json; ?>;
        let savedInputs = {};

        // Função para reativar eventos após atualização via AJAX
        function reativarEventos() {
            // Reativar eventos para os campos de litros
            document.querySelectorAll('.litros-input').forEach(input => {
                input.addEventListener('input', function() {
                    formatarLitros(this);
                });
            });

            // Reativar botão de atualização
            const atualizarManualmente = document.getElementById('atualizar-manualmente');
            if (atualizarManualmente) {
                // Remover todos os event listeners antigos primeiro
                const novoBtn = atualizarManualmente.cloneNode(true);
                atualizarManualmente.parentNode.replaceChild(novoBtn, atualizarManualmente);

                // Adicionar novo event listener
                novoBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Botão de atualização clicado');

                    // Mostrar indicador de carregamento
                    const atualizarIndicador = document.querySelector('.atualizar-indicador') ||
                                               document.createElement('div');
                    atualizarIndicador.className = 'fixed bottom-4 right-4 bg-primary text-white px-3 py-2 rounded-full shadow-lg z-50 atualizar-indicador';
                    atualizarIndicador.innerHTML = '<i class="fas fa-sync-alt fa-spin mr-2"></i> Atualizando...';

                    if (!document.querySelector('.atualizar-indicador')) {
                        document.body.appendChild(atualizarIndicador);
                    } else {
                        atualizarIndicador.classList.remove('hidden');
                    }

                    // Executar a função de atualização
                    atualizarSecoes();

                    // Esconder o indicador após um tempo
                    setTimeout(() => {
                        atualizarIndicador.classList.add('hidden');
                    }, 1000);
                });
            }

            // Reativar saldos das secretarias
            document.querySelectorAll('.abastecimento-item').forEach(item => {
                const secretariaElement = item.querySelector('.secretaria-value');
                if (secretariaElement) {
                    const secretaria = secretariaElement.value;
                    const container = item.querySelector('.saldos-secretaria-container');
                    if (secretaria && container) {
                        inicializarSaldosSecretaria(secretaria, container);
                    }
                }
            });
        }

        // Função para atualizar as seções via AJAX
        function atualizarSecoes() {
            // Atualizar abastecimentos pendentes
            fetch('atualizar_abastecimentos.php?tipo=pendentes')
                .then(response => response.text())
                .then(html => {
                    document.querySelector('.abastecimentos-pendentes-container').innerHTML = html;
                    // Reativar todos os eventos após atualização
                    reativarEventos();
                })
                .catch(error => {
                    console.error('Erro ao atualizar abastecimentos pendentes:', error);
                });

            // Atualizar abastecimentos aguardando assinatura
            fetch('atualizar_abastecimentos.php?tipo=aguardando_assinatura')
                .then(response => response.text())
                .then(html => {
                    document.querySelector('.abastecimentos-preenchidos-container').innerHTML = html;
                    // Reativar todos os eventos após atualização
                    reativarEventos();
                })
                .catch(error => {
                    console.error('Erro ao atualizar abastecimentos aguardando assinatura:', error);
                });

            // Atualizar abastecimentos concluídos (se estiver visível)
            const filtroTipo = document.querySelector('select[name="filtro_tipo"]')?.value;
            if (filtroTipo) {
                let url = 'filtrar_abastecimentos.php?tipo=' + filtroTipo;

                if (filtroTipo === 'personalizado') {
                    const dataInicial = document.querySelector('input[name="data_inicial"]')?.value;
                    const dataFinal = document.querySelector('input[name="data_final"]')?.value;

                    if (dataInicial && dataFinal) {
                        url += '&data_inicial=' + dataInicial + '&data_final=' + dataFinal;

                        fetch(url)
                            .then(response => response.text())
                            .then(html => {
                                document.getElementById('abastecimentos-concluidos-container').innerHTML = html;
                                // Reativar todos os eventos após atualização
                                reativarEventos();
                            })
                            .catch(error => {
                                console.error('Erro ao atualizar abastecimentos concluídos:', error);
                            });
                    }
                } else {
                    fetch(url)
                        .then(response => response.text())
                        .then(html => {
                            document.getElementById('abastecimentos-concluidos-container').innerHTML = html;
                            // Reativar todos os eventos após atualização
                            reativarEventos();
                        })
                        .catch(error => {
                            console.error('Erro ao atualizar abastecimentos concluídos:', error);
                        });
                }
            }
        }

        function verificarSaldoSecretaria(secretaria, combustivel, valor) {
            const secretariaData = saldosSecretarias.find(s => s.secretaria === secretaria);
            if (!secretariaData) return false;

            switch (combustivel) {
                case 'Etanol': return parseFloat(secretariaData.valor_etanol) >= valor;
                case 'Gasolina': return parseFloat(secretariaData.valor_gasolina) >= valor;
                case 'Diesel': return parseFloat(secretariaData.valor_diesel) >= valor;
                case 'Diesel S10': return parseFloat(secretariaData.valor_diesel_s10) >= valor;
                default: return false;
            }
        }

        function getSaldoSecretaria(secretaria, combustivel) {
            const secretariaData = saldosSecretarias.find(s => s.secretaria === secretaria);
            if (!secretariaData) return 0;

            switch (combustivel) {
                case 'Etanol': return parseFloat(secretariaData.valor_etanol);
                case 'Gasolina': return parseFloat(secretariaData.valor_gasolina);
                case 'Diesel': return parseFloat(secretariaData.valor_diesel);
                case 'Diesel S10': return parseFloat(secretariaData.valor_diesel_s10);
                default: return 0;
            }
        }

        function calcularValorAbastecimento(input) {
            const row = input.closest('.abastecimento-item');
            const litrosInput = row.querySelector('.litros-input');
            const valorInput = row.querySelector('.valor-input');
            const secretariaElement = row.querySelector('.secretaria-value');
            const secretaria = secretariaElement ? secretariaElement.value : '';
            let combustivel = row.querySelector('input[name="combustivel"]').value;

            // Corrigir o nome do combustível para coincidir com o banco de dados
            if (combustivel === 'Diesel-S10') {
                combustivel = 'Diesel S10';
            }

            const container = row.querySelector('.saldos-secretaria-container');

            const litros = parseFloat(litrosInput.value.replace(',', '.')) || 0;
            const posto = "<?= $_SESSION['user_name'] ?>"; // Nome do posto logado

            // Encontrar o preço correspondente
            const precoCombustivel = precosCombustiveis.find(preco =>
                preco.posto_nome === posto &&
                preco.tipo_combustivel === combustivel
            );

            const preco = precoCombustivel ? parseFloat(precoCombustivel.preco) : 0;
            const valorAbastecimento = litros * preco;

            if (valorInput) {
                valorInput.value = valorAbastecimento.toFixed(2).replace('.', ',');

                // Atualizar a exibição dos saldos
                atualizarSaldosSecretaria(secretaria, combustivel, valorAbastecimento, container);
            }
        }

        function atualizarSaldosSecretaria(secretaria, combustivel, valor, container) {
            if (!container) return;

            // Encontrar os dados da secretaria específica
            const secretariaData = saldosSecretarias.find(s => s.secretaria === secretaria);
            if (!secretariaData) return;

            // Verificar se o empenho está ativo
            if (secretariaData.status !== 'ativo') {
                container.innerHTML = `
                    <div class="col-span-4 bg-red-50 rounded-lg p-4 text-center border border-red-200">
                        <i class="fas fa-exclamation-triangle text-red-500 text-xl mb-2"></i>
                        <p class="font-medium text-red-600">Empenho Inativo</p>
                        <p class="text-sm text-gray-600 mt-1">Não é possível realizar abastecimentos</p>
                    </div>
                `;
                return;
            }

            // Atualizar todos os saldos, não apenas o do combustível selecionado
            const saldos = {
                'gasolina': parseFloat(secretariaData.valor_gasolina),
                'etanol': parseFloat(secretariaData.valor_etanol),
                'diesel': parseFloat(secretariaData.valor_diesel),
                'diesel-s10': parseFloat(secretariaData.valor_diesel_s10)
            };

            // Se for o combustível sendo abastecido, subtrair o valor
            const combustivelKey = combustivel.toLowerCase().replace(' ', '-');
            if (saldos[combustivelKey] !== undefined) {
                saldos[combustivelKey] -= valor;
            }

            // Atualizar todos os elementos de saldo
            for (const [key, value] of Object.entries(saldos)) {
                const saldoElement = container.querySelector(`.saldo-${key}`);
                if (saldoElement) {
                    saldoElement.textContent = `R$ ${value.toFixed(2).replace('.', ',')}`;
                    saldoElement.classList.toggle('text-red-500', value < 0);
                }
            }
        }

        function inicializarSaldosSecretaria(secretaria, container) {
            if (!container) return;

            const secretariaData = saldosSecretarias.find(s => s.secretaria === secretaria);
            if (!secretariaData) return;

            // Verificar se o empenho está ativo
            if (secretariaData.status !== 'ativo') {
                container.innerHTML = `
                    <div class="col-span-4 bg-red-50 rounded-lg p-4 text-center border border-red-200">
                        <i class="fas fa-exclamation-triangle text-red-500 text-xl mb-2"></i>
                        <p class="font-medium text-red-600">Empenho Inativo</p>
                        <p class="text-sm text-gray-600 mt-1">Não é possível realizar abastecimentos</p>
                    </div>
                `;
                return;
            }

            const saldos = {
                'gasolina': parseFloat(secretariaData.valor_gasolina),
                'etanol': parseFloat(secretariaData.valor_etanol),
                'diesel': parseFloat(secretariaData.valor_diesel),
                'diesel-s10': parseFloat(secretariaData.valor_diesel_s10)
            };

            for (const [key, value] of Object.entries(saldos)) {
                const saldoElement = container.querySelector(`.saldo-${key}`);
                if (saldoElement) {
                    saldoElement.textContent = `R$ ${value.toFixed(2).replace('.', ',')}`;
                    saldoElement.classList.toggle('text-red-500', value < 0);
                }
            }
        }

        function formatarLitros(input) {
            let value = input.value;

            // Substituir replace virgula por ponto
            value = value.replace(',', '.');

            // Remover caracteres não numéricos exceto ponto
            value = value.replace(/[^0-9.]/g, '');

            // Garantir apenas um ponto decimal
            const parts = value.split('.');
            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }

            // Não permitir que comece com ponto
            if (value.startsWith('.')) {
                value = '0' + value;
            }

            // Limitar a 3 casas decimais para length
            if (parts.length === 2 && parts[1].length > 3) {
                value = parts[0] + '.' + parts[1].substring(0, 3);
            }

            input.value = value;

            // Calcular o valor do abastecimento pelo posto/abstecimento
            if (value && !isNaN(parseFloat(value))) {
                calcularValorAbastecimento(input);
            }
        }

        function salvarInputs() {
            savedInputs = {}; // Limpar inputs salvos anteriormente

            document.querySelectorAll('.abastecimento-item').forEach(item => {
                const abastecimentoId = item.dataset.id;
                const form = item.querySelector('form');

                if (form) {
                    const inputs = form.querySelectorAll('input, select, textarea');
                    inputs.forEach(input => {
                        if (input.name) {
                            savedInputs[`${abastecimentoId}_${input.name}`] = input.value;
                        }
                    });
                }
            });
        }

        function restaurarInputs() {
            document.querySelectorAll('.abastecimento-item').forEach(item => {
                const abastecimentoId = item.dataset.id;
                const form = item.querySelector('form');

                if (form) {
                    const inputs = form.querySelectorAll('input, select, textarea');
                    inputs.forEach(input => {
                        const savedValue = savedInputs[`${abastecimentoId}_${input.name}`];
                        if (input.name && savedValue !== undefined) {
                            input.value = savedValue;

                            // Disparar eventos para atualizar cálculos
                            if (input.name === 'litros' || input.name === 'combustivel') {
                                const event = new Event(input.name === 'litros' ? 'input' : 'change');
                                input.dispatchEvent(event);
                            }
                        }
                    });
                }

                // Reaplicar saldos das secretarias
                const secretariaElement = item.querySelector('.secretaria-value');
                if (secretariaElement) {
                    const secretaria = secretariaElement.value;
                    const container = item.querySelector('.saldos-secretaria-container');
                    if (secretaria && container) {
                        inicializarSaldosSecretaria(secretaria, container);
                    }
                }
            });
        }

        function mostrarDetalhesMotorista(dados) {
            const modal = document.getElementById('motoristaModal');
            document.getElementById('motoristaNome').textContent = dados.nome;
            document.getElementById('motoristaCpf').textContent = formatarCPF(dados.cpf);
            document.getElementById('motoristaSecretaria').textContent = dados.secretaria || 'Não informado';
            document.getElementById('motoristaVeiculo').textContent = dados.veiculo || 'Não informado';

            const fotoElement = document.getElementById('motoristaFoto');
            if (dados.foto) {
                fotoElement.style.backgroundImage = `url('../uploads/${dados.foto}')`;
            } else {
                fotoElement.style.backgroundImage = 'url("data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%22200%22%20height%3D%22200%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20200%20200%22%20preserveAspectRatio%3D%22none%22%3E%3Cdefs%3E%3Cstyle%20type%3D%22text%2Fcss%22%3E%23holder_18a7b1a6a3f%20text%20%7B%20fill%3A%23AAAAAA%3Bfont-weight%3Abold%3Bfont-family%3AArial%2C%20Helvetica%2C%20Open%20Sans%2C%20sans-serif%2C%20monospace%3Bfont-size%3A10pt%20%7D%20%3C%2Fstyle%3E%3C%2Fdefs%3E%3Cg%20id%3D%22holder_18a7b1a6a3f%22%3E%3Crect%20width%3D%22200%22%20height%3D%22200%22%20fill%3D%22%23EEEEEE%22%3E%3C%2Frect%3E%3Cg%3E%3Ctext%20x%3D%2274.421875%22%20y%3D%22104.5%22%3E200x200%3C%2Ftext%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E")';
            }

            modal.classList.remove('hidden');
        }

        function formatarCPF(cpf) {
            if (!cpf) return 'Não informado';
            return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        }

        function ampliarFoto(urlFoto) {
            const modal = document.getElementById('fotoAmpliadaModal');
            const img = document.getElementById('fotoAmpliada');

            // Garantir que o caminho está correto
            img.src = urlFoto;
            modal.classList.remove('hidden');
        }

        function showAlert(message, type = 'error') {
            // Remover alertas anteriores
            const existingAlert = document.querySelector('.custom-alert');
            if (existingAlert) {
                existingAlert.remove();
            }

            // Criar novo alerta
            const alertDiv = document.createElement('div');
            alertDiv.className = `custom-alert ${type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-circle"></i>
                <span>${message}</span>
                <button class="close-btn" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;

            document.body.appendChild(alertDiv);

            // Remover automaticamente após 5 segundos
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        function confirmarExclusao(abastecimentoId) {
            Swal.fire({
                title: 'Confirmar Exclusão',
                text: "Tem certeza que deseja excluir este registro de abastecimento? Esta ação não pode ser desfeita.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#10B981',
                cancelButtonColor: '#EF4444',
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    excluirAbastecimento(abastecimentoId);
                }
            });
        }

        function excluirAbastecimento(abastecimentoId) {
            // Mostrar loading
            const loading = Swal.fire({
                title: 'Excluindo...',
                allowOutsideClick: false,
                showConfirmButton: false,
                willOpen: () => {
                    Swal.showLoading();
                }
            });

            // Enviar requisição AJAX para excluir
            fetch('excluir_abastecimento.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${abastecimentoId}`
            })
            .then(response => response.json())
            .then(data => {
                loading.close();

                if (data.success) {
                    Swal.fire({
                        title: 'Excluído!',
                        text: 'O registro foi excluído com sucesso.',
                        icon: 'success',
                        confirmButtonColor: '#10B981'
                    }).then(() => {
                        // Recarregar a página para atualizar a lista
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Erro!',
                        text: data.message || 'Ocorreu um erro ao tentar excluir o registro.',
                        icon: 'error',
                        confirmButtonColor: '#EF4444'
                    });
                }
            })
            .catch(error => {
                loading.close();
                Swal.fire({
                    title: 'Erro!',
                    text: 'Ocorreu um erro ao tentar excluir o registro.',
                    icon: 'error',
                    confirmButtonColor: '#EF4444'
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Adicionar eventos a todos os campos de litros
            document.querySelectorAll('.litros-input').forEach(input => {
                input.addEventListener('input', function() {
                    formatarLitros(this);
                });
            });

            // Mostrar/ocultar campos de data conforme seleção
            const filtroTipo = document.querySelector('select[name="filtro_tipo"]');
            const camposData = document.querySelectorAll('.filtro-data');

            filtroTipo.addEventListener('change', function() {
                if (this.value === 'personalizado') {
                    camposData.forEach(campo => campo.classList.remove('hidden'));
                } else {
                    camposData.forEach(campo => campo.classList.add('hidden'));
                    // Atualizar lista para mostrar apenas hoje
                    filtrarAbastecimentos('hoje');
                }
            });

            // Adicionar eventos aos campos de data
            document.querySelectorAll('input[name="data_inicial"], input[name="data_final"]').forEach(input => {
                input.addEventListener('change', function() {
                    if (filtroTipo.value === 'personalizado') {
                        filtrarAbastecimentos('personalizado');
                    }
                });
            });

            // Carregar abastecimentos concluídos ao iniciar (filtro "hoje" pré-selecionado)
            filtrarAbastecimentos('hoje');

            // Inicializar saldos quando a página carrega
            document.querySelectorAll('.abastecimento-item').forEach(item => {
                const secretariaElement = item.querySelector('.secretaria-value');
                if (secretariaElement) {
                    const secretaria = secretariaElement.value;
                    const container = item.querySelector('.saldos-secretaria-container');
                    inicializarSaldosSecretaria(secretaria, container);
                }
            });

            // Iniciar a atualização automática quando o documento estiver pronto
            setInterval(atualizarSecoes, 30000);

            // Adicionar um indicador visual para as atualizações
            const atualizarIndicador = document.createElement('div');
            atualizarIndicador.className = 'fixed bottom-4 right-4 bg-primary text-white px-3 py-2 rounded-full shadow-lg hidden z-50 atualizar-indicador';
            atualizarIndicador.innerHTML = '<i class="fas fa-sync-alt fa-spin mr-2"></i> Atualizando...';
            document.body.appendChild(atualizarIndicador);

            // Configurar o evento para o botão de atualização manual
            reativarEventos();

            // Quando uma atualização automática ocorrer
            setInterval(() => {
                atualizarIndicador.classList.remove('hidden');
                setTimeout(() => {
                    atualizarIndicador.classList.add('hidden');
                }, 1000);
            }, 30000);
        });

        function filtrarAbastecimentos(tipo) {
            const container = document.getElementById('abastecimentos-concluidos-container');
            let url = 'filtrar_abastecimentos.php?tipo=' + tipo;

            if (tipo === 'personalizado') {
                const dataInicial = document.querySelector('input[name="data_inicial"]').value;
                const dataFinal = document.querySelector('input[name="data_final"]').value;

                if (!dataInicial || !dataFinal) {
                    showAlert('Por favor, selecione ambas as datas');
                    return;
                }

                url += '&data_inicial=' + dataInicial + '&data_final=' + dataFinal;
            }

            // Mostrar loading
            container.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-primary text-4xl mb-3"></i>
                    <p class="text-gray-600">Carregando abastecimentos...</p>
                </div>
            `;

            // Fazer requisição AJAX
            fetch(url)
                .then(response => response.text())
                .then(html => {
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Erro ao filtrar abastecimentos:', error);
                    showAlert('Ocorreu um erro ao carregar os abastecimentos');
                    container.innerHTML = `
                        <div class="text-center py-8">
                            <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-3"></i>
                            <p class="text-gray-600">Ocorreu um erro ao carregar os abastecimentos.</p>
                        </div>
                    `;
                });
        }
    </script>
</head>
<body>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
    <div class="logo-container shadow-hard">
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col items-center text-center space-y-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center space-x-4">
                    <a href="../menu_posto.php" class="bg-white/20 p-3 rounded-full hover:bg-white/30 transition">
                        <i class="fas fa-arrow-left text-white text-2xl"></i>
                    </a>
                    <div class="bg-white/20 p-3 rounded-full">
                        <i class="fas fa-gas-pump text-white text-2xl"></i>
                    </div>
                </div>
                <div class="text-white text-center">
             <span class="font-bold text-2xl md:text-2xl leading-tight break-words text-center block">
                <?= $_SESSION['user_name'] ?>
            </span>
                </div>
            </div>
        </div>
    </div>

        <!-- Main Content -->
        <div class="container mx-auto px-4 py-6 max-w-6xl">
            <?php if (isset($success)): ?>
                <div class="message-container success">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= $success ?>
                </div>
                <script>
                    setTimeout(() => {
                        document.querySelector('.message-container').style.opacity = '0';
                    }, 5000);
                </script>
            <?php elseif (isset($error)): ?>
                <div class="message-container error">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= $error ?>
                </div>
                <script>
                    setTimeout(() => {
                        document.querySelector('.message-container').style.opacity = '0';
                    }, 5000);
                </script>
            <?php endif; ?>

            <!-- Botão de atualização manual -->
            <div class="bg-white rounded-2xl shadow-hard mb-4 overflow-hidden w-full">
                <button id="atualizar-manualmente" class="w-full py-3 bg-primary text-white px-4 font-medium hover:bg-primary-dark transition flex items-center justify-center gap-2">
                    <i class="fas fa-sync-alt"></i>
                    <span>Atualizar dados em tempo real</span>
                </button>
            </div>

            <!-- Abastecimentos Pendentes -->
            <div class="bg-white rounded-2xl shadow-hard overflow-hidden mb-8">
                <div class="bg-primary-dark px-6 py-4">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-clock mr-3"></i>
                        Abastecimentos Pendentes
                    </h2>
                </div>
                <div class="p-6 abastecimentos-pendentes-container">
                    <?php if (empty($abastecimentos)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-check-circle text-gray-300 text-4xl mb-3"></i>
                            <p class="text-gray-600">Nenhum abastecimento pendente para este posto.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach ($abastecimentos as $abastecimento): ?>
                                    <div class="relative border border-gray-200 rounded-xl p-5 shadow-soft abastecimento-item" data-id="<?= $abastecimento['id'] ?>">
                                        <!-- Botão de excluir -->
                                        <button onclick="confirmarExclusao(<?= $abastecimento['id'] ?>)" class="btn-excluir">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                 

                                    <!-- Foto do motorista em container quadrado grande acima das informações -->
                                    <div class="motorista-photo-container mb-6">
                                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 w-full max-w-md mx-auto">
                                            <label class="motorista-photo-label">Motorista</label>
                                            <div class="flex flex-col items-center">
                                                <?php if (!empty($abastecimento['motorista_foto'])): ?>
                                                    <img src="../uploads/<?= basename($abastecimento['motorista_foto']) ?>"
                                                         class="motorista-photo cursor-pointer"
                                                         onclick="ampliarFoto('../uploads/<?= basename($abastecimento['motorista_foto']) ?>')">
                                                <?php else: ?>
                                                    <div class="motorista-photo bg-gray-200 flex items-center justify-center">
                                                        <i class="fas fa-user text-gray-400 text-6xl"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="mt-4 text-center">
                                                    <p class="text-sm font-medium text-gray-900">
                                                        <?= $abastecimento['motorista_name'] ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500">
                                                        CPF: <?= formatarCPF($abastecimento['motorista_cpf']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" class="space-y-4">
                                        <input type="hidden" name="abastecimento_id" value="<?= $abastecimento['id'] ?>">
                                        <input type="hidden" class="secretaria-value" value="<?= $abastecimento['motorista_secretaria'] ?>">

                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                            <!-- Coluna 1: Informações adicionais -->
                                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Secretaria</label>
                                                <div class="flex items-center">
                                                    <i class="fas fa-building text-gray-400 mr-2"></i>
                                                    <input type="text" class="w-full bg-transparent focus:outline-none"
                                                           value="<?= $abastecimento['motorista_secretaria'] ?: 'Não informado' ?>" readonly>
                                                </div>

                                                <div class="mt-3">
                                                    <label class="block text-xs font-medium text-gray-500 mb-1">Veículo</label>
                                                    <div class="flex items-center">
                                                        <i class="fas fa-car text-gray-400 mr-2"></i>
                                                        <input type="text" class="w-full bg-transparent focus:outline-none"
                                                               value="<?= $abastecimento['nome_veiculo'] ?: 'Não informado' ?>" readonly>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Coluna 2: Veículo -->
                                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Veículo</label>
                                                <div class="flex items-center">
                                                    <i class="fas fa-car text-gray-400 mr-2"></i>
                                                    <input type="text" class="w-full bg-transparent focus:outline-none"
                                                           value="<?= $abastecimento['veiculo_nome'] ?> - <?= $abastecimento['placa'] ?>" readonly>
                                                </div>
                                            </div>

                                            <!-- Coluna 3: KM -->
                                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                                                <label class="block text-xs font-medium text-gray-500 mb-1">KM</label>
                                                <div class="flex items-center">
                                                    <i class="fas fa-tachometer-alt text-gray-400 mr-2"></i>
                                                    <input type="text" class="w-full bg-transparent focus:outline-none"
                                                           value="<?= $abastecimento['km_abastecido'] ?>" readonly>
                                                </div>
                                            </div>

                                            <!-- Coluna 4: Data/Hora -->
                                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Data/Hora</label>
                                                <div class="flex items-center">
                                                    <i class="far fa-clock text-gray-400 mr-2"></i>
                                                    <input type="text" class="w-full bg-transparent focus:outline-none"
                                                           value="<?= date('d/m/Y H:i', strtotime($abastecimento['data_criacao'])) ?>" readonly>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div class="input-field rounded-xl p-3 border border-gray-200">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Litros*</label>
                                                <input type="text" name="litros" class="w-full bg-transparent focus:outline-none litros-input"
                                                           placeholder="Ex: 30.50" required>
                                                <p class="text-xs text-gray-500 mt-2">Capacidade: <?= $abastecimento['tanque_veiculo'] ?> litros</p>
                                            </div>

                                            <div class="input-field rounded-xl p-3 border border-gray-200">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Combustível</label>
                                                <input type="text" class="w-full bg-transparent focus:outline-none"
                                                       value="<?= ($abastecimento['combustivel_veiculo'] == 'Diesel-S500') ? 'Diesel' : $abastecimento['combustivel_veiculo'] ?>" readonly>
                                                <input type="hidden" name="combustivel"
                                                       value="<?= ($abastecimento['combustivel_veiculo'] == 'Diesel-S500') ? 'Diesel' : $abastecimento['combustivel_veiculo'] ?>">
                                            </div>

                                            <div class="input-field rounded-xl p-3 border border-gray-200">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Valor (R$)*</label>
                                                <input type="text" name="valor" class="w-full bg-transparent focus:outline-none valor-input"
                                                           placeholder="Será calculado automaticamente" readonly>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-2 saldos-secretaria-container">
                                            <div class="bg-blue-50 rounded-lg p-2 text-center border border-blue-100">
                                                <i class="fas fa-gas-pump text-blue-500 mb-1"></i>
                                                <p class="text-xs text-gray-600">Saldo Gasolina</p>
                                                <p class="font-medium text-blue-600 saldo-gasolina">R$ 0,00</p>
                                            </div>
                                            <div class="bg-green-50 rounded-lg p-2 text-center border border-green-100">
                                                <i class="fas fa-leaf text-green-500 mb-1"></i>
                                                <p class="text-xs text-gray-600">Saldo Etanol</p>
                                                <p class="font-medium text-green-600 saldo-etanol">R$ 0,00</p>
                                            </div>
                                            <div class="bg-yellow-50 rounded-lg p-2 text-center border border-yellow-100">
                                                <i class="fas fa-truck text-yellow-500 mb-1"></i>
                                                <p class="text-xs text-gray-600">Saldo Diesel</p>
                                                <p class="font-medium text-yellow-600 saldo-diesel">R$ 0,00</p>
                                            </div>
                                            <div class="bg-purple-50 rounded-lg p-2 text-center border border-purple-100">
                                                <i class="fas fa-truck text-purple-500 mb-1"></i>
                                                <p class="text-xs text-gray-600">Saldo Diesel S10</p>
                                                <p class="font-medium text-purple-600 saldo-diesel-s10">R$ 0,00</p>
                                            </div>
                                        </div>

                                        <div class="pt-2">
                                            <button type="submit" name="preencher_abastecimento"
                                                    class="w-full py-3 px-4 bg-success text-white font-medium rounded-xl hover:bg-green-700 transition duration-200 flex items-center justify-center gap-2">
                                                <i class="fas fa-check-circle"></i>
                                                <span>Registrar Abastecimento</span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Abastecimentos Aguardando Assinatura -->
            <div class="bg-white rounded-2xl shadow-hard overflow-hidden mb-8">
                <div class="bg-yellow-500 px-6 py-4">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-signature mr-3"></i>
                        Aguardando assinatura do motorista
                    </h2>
                </div>
                <div class="p-6 abastecimentos-preenchidos-container">
                    <?php
                    $aguardando_assinatura = array_filter($abastecimentos_preenchidos, fn($a) => $a['status'] === 'aguardando_assinatura');
                    ?>
                    <?php if (empty($aguardando_assinatura)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-check-circle text-gray-300 text-4xl mb-3"></i>
                            <p class="text-gray-600">Nenhum abastecimento aguardando assinatura.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach ($aguardando_assinatura as $abastecimento): ?>
                                <div class="border border-gray-200 rounded-xl p-5 shadow-soft abastecimento-item relative border-l-4 border-yellow-500" data-id="<?= $abastecimento['id'] ?>">
                                    <!-- Botão de excluir -->
                                    <button onclick="confirmarExclusao(<?= $abastecimento['id'] ?>)"
                                            class="btn-excluir">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>

                                    <div class="space-y-4">
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                            <!-- Coluna 1: Foto e informações do motorista -->
                                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200 col-span-1">
                                                <label class="block text-xs font-medium text-gray-500 mb-2">Motorista</label>
                                                <div class="flex items-center space-x-3">
                                                    <!-- Foto do motorista (clique para ampliar) -->
                                                    <div class="flex-shrink-0">
                                                        <?php if (!empty($abastecimento['motorista_foto'])): ?>
                                                            <img src="../uploads/<?= basename($abastecimento['motorista_foto']) ?>"
                                                                 class="w-12 h-12 rounded-full object-cover cursor-pointer"
                                                                 onclick="ampliarFoto('../uploads/<?= basename($abastecimento['motorista_foto']) ?>')">
                                                        <?php else: ?>
                                                            <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center">
                                                                <i class="fas fa-user text-gray-400"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-sm font-medium text-gray-900 truncate">
                                                            <?= $abastecimento['motorista_name'] ?>
                                                        </p>
                                                        <p class="text-xs text-gray-500 truncate">
                                                            CPF: <?= formatarCPF($abastecimento['motorista_cpf']) ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <!-- Informações adicionais -->
                                                <div class="mt-2 space-y-1">
                                                    <p class="text-xs text-gray-600">
                                                        <span class="font-medium">Secretaria:</span>
                                                        <?= $abastecimento['motorista_secretaria'] ?: 'Não informado' ?>
                                                    </p>
                                                    <p class="text-xs text-gray-600">
                                                        <span class="font-medium">Veículo:</span>
                                                        <?= $abastecimento['nome_veiculo'] ?: 'Não informado' ?>
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Coluna 2: Veículo -->
                                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Veículo</label>
                                                <div class="flex items-center">
                                                    <i class="fas fa-car text-gray-400 mr-2"></i>
                                                    <input type="text" class="w-full bg-transparent focus:outline-none"
                                                           value="<?= $abastecimento['veiculo_nome'] ?> - <?= $abastecimento['placa'] ?>" readonly>
                                                </div>
                                            </div>

                                            <!-- Coluna 3: KM -->
                                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                                                <label class="block text-xs font-medium text-gray-500 mb-1">KM</label>
                                                <div class="flex items-center">
                                                    <i class="fas fa-tachometer-alt text-gray-400 mr-2"></i>
                                                    <input type="text" class="w-full bg-transparent focus:outline-none"
                                                           value="<?= $abastecimento['km_abastecido'] ?>" readonly>
                                                </div>
                                            </div>

                                            <!-- Coluna 4: Data/Hora -->
                                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                                                <label class="block text-xs font-medium text-gray-500 mb-1">Data/Hora</label>
                                                <div class="flex items-center">
                                                    <i class="far fa-clock text-gray-400 mr-2"></i>
                                                    <input type="text" class="w-full bg-transparent focus:outline-none"
                                                           value="<?= date('d/m/Y H:i', strtotime($abastecimento['data_criacao'])) ?>" readonly>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Litros</label>
                                                <input type="text" class="w-full bg-transparent focus:outline-none"
                                                       value="<?= $abastecimento['litros'] ?>" readonly>
                                            </div>

                                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Combustível</label>
                                                <input type="text" class="w-full bg-transparent focus:outline-none"
                                                       value="<?= $abastecimento['combustivel'] ?>" readonly>
                                            </div>

                                            <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Valor (R$)</label>
                                                <input type="text" class="w-full bg-transparent focus:outline-none"
                                                       value="<?= number_format($abastecimento['valor'], 2, ',', '.') ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Abastecimentos Concluídos -->
            <div class="bg-white rounded-2xl shadow-hard overflow-hidden">
                <div class="bg-primary-dark px-6 py-4">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <i class="fas fa-history mr-3"></i>
                        Abastecimentos Concluídos
                    </h2>
                </div>
                <div class="p-6">
                    <!-- Filtro de Data -->
                    <div class="mb-6 bg-gray-50 p-4 rounded-xl">
                        <form id="filtro-data" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Mostrar</label>
                                <select name="filtro_tipo" class="w-full rounded-lg border-gray-300">
                                    <option value="hoje">Hoje</option>
                                    <option value="personalizado">Período personalizado</option>
                                </select>
                            </div>
                            <div class="filtro-data hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data Inicial</label>
                                <input type="date" name="data_inicial" class="w-full rounded-lg border-gray-300">
                            </div>
                            <div class="filtro-data hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data Final</label>
                                <input type="date" name="data_final" class="w-full rounded-lg border-gray-300">
                            </div>
                        </form>
                    </div>

                    <div id="abastecimentos-concluidos-container"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Botão flutuante para atualização -->
    <div class="fixed bottom-4 left-4 z-50">
        <button id="botao-flutuante" class="bg-primary text-white w-14 h-14 rounded-full shadow-lg flex items-center justify-center hover:bg-primary-dark transition">
            <i class="fas fa-sync-alt text-lg"></i>
        </button>
    </div>

    <!-- Modal de detalhes do motorista -->
    <div id="motoristaModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Informações do Motorista</h3>
                <button onclick="document.getElementById('motoristaModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div class="flex items-center justify-center">
                    <div id="motoristaFoto" class="w-24 h-24 bg-gray-200 rounded-full bg-cover bg-center"></div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Nome</label>
                        <p id="motoristaNome" class="font-medium"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">CPF</label>
                        <p id="motoristaCpf" class="font-medium"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Secretaria</label>
                        <p id="motoristaSecretaria" class="font-medium"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Veículo</label>
                        <p id="motoristaVeiculo" class="font-medium"></p>
                    </div>
                </div>

                <div class="pt-4 flex justify-end">
                    <button onclick="document.getElementById('motoristaModal').classList.add('hidden')"
                            class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para foto ampliada -->
    <div id="fotoAmpliadaModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl p-6 max-w-2xl w-full">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Foto do Motorista</h3>
                <button onclick="document.getElementById('fotoAmpliadaModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="flex justify-center">
                <img id="fotoAmpliada" class="max-w-full max-h-96" alt="Foto ampliada">
            </div>

            <div class="pt-4 flex justify-end">
                <button onclick="document.getElementById('fotoAmpliadaModal').classList.add('hidden')"
                        class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">
                    Fechar
                </button>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('botao-flutuante').addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Botão flutuante clicado');

        // Mostrar indicador de carregamento
        const atualizarIndicador = document.querySelector('.atualizar-indicador') ||
                                document.createElement('div');
        atualizarIndicador.className = 'fixed bottom-4 right-4 bg-primary text-white px-3 py-2 rounded-full shadow-lg z-50 atualizar-indicador';
        atualizarIndicador.innerHTML = '<i class="fas fa-sync-alt fa-spin mr-2"></i> Atualizando...';

        if (!document.querySelector('.atualizar-indicador')) {
            document.body.appendChild(atualizarIndicador);
        } else {
            atualizarIndicador.classList.remove('hidden');
        }

        // Executar a função de atualização
        atualizarSecoes();

        // Esconder o indicador após um tempo
        setTimeout(() => {
            atualizarIndicador.classList.add('hidden');
        }, 1000);
    });
    </script>
</body>
</html>

<?php
function formatarCPF($cpf) {
    if (empty($cpf)) return 'Não informado';
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}
?>
