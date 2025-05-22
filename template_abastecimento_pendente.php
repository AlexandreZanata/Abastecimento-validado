<div class="border border-gray-200 rounded-xl p-5 shadow-soft abastecimento-item" data-id="<?= $abastecimento['id'] ?>">
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