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
                           value="<?= $abastecimento['veiculo_nome'] ?? '' ?> - <?= $abastecimento['placa'] ?? '' ?>" readonly>
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