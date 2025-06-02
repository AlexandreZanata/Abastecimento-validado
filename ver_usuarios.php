<?php
// Configurações do Banco de Dados (SUBSTITUA COM SUAS CREDENCIAIS)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "workflow_system"; // Ex: "nome_do_banco"
$table_name = "usuarios";

// Mapa de Secretarias (Conforme fornecido)
$secretarias_map = [
    "Gabinete do Prefeito" => "Gabinete do Prefeito",
    "Gabinete do Vice-Prefeito" => "Gabinete do Vice-Prefeito",
    "Secretaria Municipal da Mulher de Família" => "Secretaria Municipal da Mulher de Família",
    "Secretaria Municipal de Fazenda" => "Secretaria Municipal de Fazenda",
    "Secretaria Municipal de Educação" => "Secretaria Municipal de Educação",
    "Secretaria Municipal de Agricultura e Meio Ambiente" => "Secretaria Municipal de Agricultura e Meio Ambiente",
    "Secretaria Municipal de Agricultura Familiar e Segurança Alimentar" => "Secretaria Municipal de Agricultura Familiar e Segurança Alimentar",
    "Secretaria Municipal de Assistência Social" => "Secretaria Municipal de Assistência Social",
    "Secretaria Municipal de Desenvolvimento Econômico e Turismo" => "Secretaria Municipal de Desenvolvimento Econômico e Turismo",
    "Secretaria Municipal de Administração" => "Secretaria Municipal de Administração",
    "Secretaria Municipal de Governo" => "Secretaria Municipal de Governo",
    "Secretaria Municipal de Infraestrutura, Transportes e Saneamento" => "Secretaria Municipal de Infraestrutura, Transportes e Saneamento",
    "Secretaria Municipal de Esporte e Lazer e Juventude" => "Secretaria Municipal de Esporte e Lazer e Juventude",
    "Secretaria Municipal da Cidade" => "Secretaria Municipal da Cidade",
    "Secretaria Municipal de Saúde" => "Secretaria Municipal de Saúde",
    "Secretaria Municipal de Segurança Pública, Trânsito e Defesa Civil" => "Secretaria Municipal de Segurança Pública, Trânsito e Defesa Civil",
    "Controladoria Geral do Município" => "Controladoria Geral do Município",
    "Procuradoria Geral do Município" => "Procuradoria Geral do Município",
    "Secretaria Municipal de Cultura" => "Secretaria Municipal de Cultura",
    "Secretaria Municipal de Planejamento, Ciência, Tecnologia e Inovação" => "Secretaria Municipal de Planejamento, Ciência, Tecnologia e Inovação",
    "Secretaria Municipal de Obras e Serviços Públicos" => "Secretaria Municipal de Obras e Serviços Públicos",
];

// Inicialização de variáveis
$conn = null; // Definido mais tarde
$display_mode = 'search_form_only'; // 'search_form_only', 'individual_step1_results', 'full_details_list_view', 'single_full_detail_view'
$partial_search_results = []; // Para etapa 1 da busca individual: [ {id, name, cpf, secretaria}, ... ]
$full_user_details_list = []; // Para busca por secretaria, todos, ou usuário único selecionado: [ {user_details}, ... ]
$message = '';
$query_individual_term = '';
$selected_secretaria = '';
$selected_user_id = null;


try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Falha na conexão com o banco de dados: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search_type'])) {
        $searchType = $_GET['search_type'];

        // Etapa 1 da Busca Individual (Nome ou CPF)
        if ($searchType == 'individual_step1' && !empty($_GET['query_individual'])) {
            $query_individual_term = trim($_GET['query_individual']);
            $sql = "SELECT id, name, cpf, secretaria FROM $table_name WHERE name LIKE ? OR cpf LIKE ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Erro ao preparar consulta (individual_step1): " . $conn->error);
            
            $likeTerm = "%" . $query_individual_term . "%";
            $stmt->bind_param("ss", $likeTerm, $likeTerm);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $partial_search_results[] = $row;
            }
            $stmt->close();
            $display_mode = 'individual_step1_results';
            if (empty($partial_search_results)) {
                $message = "Nenhum usuário encontrado com o termo: '" . htmlspecialchars($query_individual_term) . "'.";
            }
        }
        // Etapa 2 da Busca Individual (Ver Detalhes Completos)
        elseif ($searchType == 'individual_step2' && !empty($_GET['user_id'])) {
            $selected_user_id = intval($_GET['user_id']);
            $sql = "SELECT id, name, email, cpf, secretaria, role, profile_photo, number FROM $table_name WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Erro ao preparar consulta (individual_step2): " . $conn->error);

            $stmt->bind_param("i", $selected_user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($user = $result->fetch_assoc()) {
                $full_user_details_list[] = $user; // Armazena como lista para reusar o template de card
            }
            $stmt->close();
            $display_mode = 'single_full_detail_view';
             if (empty($full_user_details_list)) {
                $message = "Usuário não encontrado ou ID inválido.";
            }
        }
        // Busca por Secretaria
        elseif ($searchType == 'secretaria' && !empty($_GET['search_secretaria_select'])) {
            $selected_secretaria = trim($_GET['search_secretaria_select']);
            if (array_key_exists($selected_secretaria, $secretarias_map)) { // Verifica se é uma secretaria válida
                $sql = "SELECT id, name, email, cpf, secretaria, role, profile_photo, number FROM $table_name WHERE secretaria = ?";
                $stmt = $conn->prepare($sql);
                if (!$stmt) throw new Exception("Erro ao preparar consulta (secretaria): " . $conn->error);

                $stmt->bind_param("s", $selected_secretaria);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $full_user_details_list[] = $row;
                }
                $stmt->close();
                $display_mode = 'full_details_list_view';
                if (empty($full_user_details_list)) {
                    $message = "Nenhum usuário encontrado para a secretaria: '" . htmlspecialchars($selected_secretaria) . "'.";
                }
            } else {
                $message = "Secretaria inválida selecionada.";
            }
        }
        // Ver Todos os Usuários
        elseif ($searchType == 'todos') {
            $sql = "SELECT id, name, email, cpf, secretaria, role, profile_photo, number FROM $table_name ORDER BY name ASC";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Erro ao preparar consulta (todos): " . $conn->error);
            
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $full_user_details_list[] = $row;
            }
            $stmt->close();
            $display_mode = 'full_details_list_view';
            if (empty($full_user_details_list)) {
                $message = "Nenhum usuário cadastrado no sistema.";
            }
        }
    }
} catch (Exception $e) {
    // Em produção, logar o erro em vez de exibir diretamente
    $message = "Erro no sistema: " . $e->getMessage();
    // Considerar não mostrar $e->getMessage() para o usuário final em produção por segurança.
} finally {
    if ($conn) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Avançado de Usuários</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --primary-color: #3498db; /* Azul mais vibrante */
            --primary-hover-color: #2980b9;
            --secondary-color: #2ecc71; /* Verde para ações secundárias/positivas */
            --secondary-hover-color: #27ae60;
            --accent-color: #e74c3c; /* Vermelho para alertas/ações de destaque negativo */
            --light-gray: #f4f6f8;
            --medium-gray: #bdc3c7;
            --dark-gray: #7f8c8d;
            --text-color: #34495e;
            --card-bg: #ffffff;
            --border-radius: 8px;
            --box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            --font-family: 'Poppins', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--light-gray);
            color: var(--text-color);
            line-height: 1.7;
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 25px;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
            color: var(--primary-color);
            font-size: 2.5em;
            font-weight: 600;
        }
        .page-header i {
            margin-right: 10px;
        }

        .search-section {
            margin-bottom: 30px;
            padding: 25px;
            background-color: #fff;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .search-section h2 {
            font-size: 1.5em;
            color: var(--primary-color);
            margin-bottom: 20px;
            border-bottom: 2px solid var(--light-gray);
            padding-bottom: 10px;
        }
        .search-section .form-group {
            margin-bottom: 20px;
        }
        .search-section label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: #555;
        }
        .search-section input[type="text"],
        .search-section select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--medium-gray);
            border-radius: var(--border-radius);
            font-size: 1em;
            transition: all 0.3s ease;
        }
        .search-section input[type="text"]:focus,
        .search-section select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            outline: none;
        }
        .search-section .button {
            padding: 12px 25px;
            font-size: 1em;
            font-weight: 600;
            color: #fff;
            background-color: var(--primary-color);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: background-color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .search-section .button:hover {
            background-color: var(--primary-hover-color);
        }
        .search-section .button.secondary {
            background-color: var(--secondary-color);
        }
        .search-section .button.secondary:hover {
            background-color: var(--secondary-hover-color);
        }

        .message-area {
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: var(--border-radius);
            font-weight: 500;
            text-align: center;
        }
        .message-area.info {
            background-color: #eaf5fd;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }
        .message-area.error {
            background-color: #fdeded;
            color: var(--accent-color);
            border: 1px solid var(--accent-color);
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .user-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px rgba(0,0,0,0.07);
            padding: 20px;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.1);
        }
        .user-card .profile-pic-container {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 20px auto;
            border: 4px solid var(--primary-color);
            box-shadow: 0 0 10px rgba(52, 152, 219, 0.3);
            display: flex; /* For centering content inside */
            align-items: center;
            justify-content: center;
        }
        .user-card .profile-pic {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-card .no-pic {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--light-gray);
            color: var(--dark-gray);
            font-weight: 500;
        }
        .user-card h3 {
            font-size: 1.4em;
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .user-card p {
            font-size: 0.95em;
            margin-bottom: 8px;
            color: var(--text-color);
            word-break: break-word;
        }
        .user-card p strong {
            color: #333;
            margin-right: 5px;
        }
        .user-card .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        .user-card .detail-item i {
            color: var(--primary-color);
            font-size: 1.1em;
            width: 20px; /* Ensure icons align */
            text-align: center;
        }
        .user-card .actions {
            margin-top: auto; /* Push actions to the bottom */
            padding-top: 15px;
            border-top: 1px solid var(--light-gray);
            text-align: center;
        }
        .user-card .actions .button {
             padding: 8px 15px; font-size: 0.9em;
        }

        /* Tabela para resultados parciais da busca individual (Etapa 1) */
        .partial-results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-radius: var(--border-radius);
            overflow: hidden; /* Ensures rounded corners apply to content */
        }
        .partial-results-table th, .partial-results-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }
        .partial-results-table thead th {
            background-color: var(--primary-color);
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }
        .partial-results-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .partial-results-table tbody tr:hover {
            background-color: #eaf5fd;
        }
        .partial-results-table .select-user-link {
            color: var(--secondary-color);
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s ease;
        }
        .partial-results-table .select-user-link:hover {
            text-decoration: underline;
            color: var(--secondary-hover-color);
        }

        /* Responsividade */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .container { padding: 15px; }
            .page-header { font-size: 2em; }
            .search-section h2 { font-size: 1.3em; }
            .results-grid {
                grid-template-columns: 1fr; /* Stack cards on mobile */
            }
            .user-card .profile-pic-container { width: 80px; height: 80px; }
            .user-card h3 { font-size: 1.2em; }
            .partial-results-table thead { display: none; } /* Hide table header on small screens */
            .partial-results-table, .partial-results-table tbody, .partial-results-table tr, .partial-results-table td {
                display: block;
                width: 100%;
            }
            .partial-results-table tr {
                margin-bottom: 15px;
                border: 1px solid var(--medium-gray);
                border-radius: var(--border-radius);
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            .partial-results-table td {
                text-align: right;
                padding-left: 50%;
                position: relative;
            }
            .partial-results-table td::before {
                content: attr(data-label);
                position: absolute;
                left: 10px;
                width: calc(50% - 20px);
                padding-right: 10px;
                white-space: nowrap;
                text-align: left;
                font-weight: 600;
                color: var(--primary-color);
            }
        }
         @media (max-width: 480px) {
            .search-section .button { width: 100%; margin-bottom: 10px; justify-content: center;}
            .search-section .button:last-child { margin-bottom: 0; }
        }

    </style>
</head>
<body>
    <div class="container">
        <h1 class="page-header"><i class="fas fa-users-cog"></i>Painel de Usuários</h1>

        <?php if (!empty($message)): ?>
            <div class="message-area <?php echo (strpos(strtolower($message), 'erro') !== false || strpos(strtolower($message), 'inválid') !== false) ? 'error' : 'info'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="search-section">
            <h2><i class="fas fa-search"></i>Opções de Busca</h2>
            <form method="GET" action="ver_usuarios.php">
                <div class="form-group">
                    <label for="query_individual_input">Busca Individual por Nome ou CPF:</label>
                    <input type="hidden" name="search_type" value="individual_step1">
                    <input type="text" id="query_individual_input" name="query_individual" placeholder="Digite nome ou CPF do usuário" value="<?php echo htmlspecialchars($query_individual_term); ?>">
                </div>
                <button type="submit" class="button"><i class="fas fa-user-magnifying-glass"></i>Buscar Usuário</button>
            </form>

            <hr style="margin: 30px 0; border: 0; border-top: 1px solid var(--light-gray);">

            <form method="GET" action="ver_usuarios.php">
                <div class="form-group">
                    <label for="search_secretaria_select_input">Busca por Secretaria:</label>
                    <input type="hidden" name="search_type" value="secretaria">
                    <select name="search_secretaria_select" id="search_secretaria_select_input">
                        <option value="">-- Selecione uma Secretaria --</option>
                        <?php
                        foreach ($secretarias_map as $key => $value) {
                            $is_selected = ($selected_secretaria == $key) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($key) . '" ' . $is_selected . '>' . htmlspecialchars($value) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="button"><i class="fas fa-building-user"></i>Filtrar por Secretaria</button>
            </form>

            <hr style="margin: 30px 0; border: 0; border-top: 1px solid var(--light-gray);">
            
            <form method="GET" action="ver_usuarios.php" style="text-align:center;">
                <input type="hidden" name="search_type" value="todos">
                <button type="submit" class="button secondary"><i class="fas fa-list-ul"></i>Ver Todos os Usuários</button>
            </form>
        </div>


        <?php if ($display_mode == 'individual_step1_results' && !empty($partial_search_results)): ?>
            <div class="results-section">
                <h2><i class="fas fa-clipboard-list"></i>Selecione o Usuário</h2>
                <table class="partial-results-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>Secretaria</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partial_search_results as $user): ?>
                            <tr>
                                <td data-label="Nome:"><?php echo htmlspecialchars($user['name']); ?></td>
                                <td data-label="CPF:"><?php echo htmlspecialchars($user['cpf']); ?></td>
                                <td data-label="Secretaria:"><?php echo htmlspecialchars($user['secretaria']); ?></td>
                                <td data-label="Ação:">
                                    <a href="ver_usuarios.php?search_type=individual_step2&user_id=<?php echo $user['id']; ?>" class="select-user-link">
                                        <i class="fas fa-eye"></i> Ver Detalhes
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>


        <?php
        // Display para lista de detalhes completos ou detalhe único
        if (($display_mode == 'full_details_list_view' || $display_mode == 'single_full_detail_view') && !empty($full_user_details_list)):
        ?>
            <div class="results-section">
                 <h2>
                    <?php
                    if ($display_mode == 'single_full_detail_view') {
                        echo '<i class="fas fa-user-check"></i> Detalhes do Usuário Selecionado';
                    } elseif ($selected_secretaria) {
                        echo '<i class="fas fa-users"></i> Usuários da Secretaria: ' . htmlspecialchars($selected_secretaria);
                    } else {
                        echo '<i class="fas fa-users"></i> Lista de Usuários';
                    }
                    ?>
                </h2>
                <div class="results-grid">
                    <?php foreach ($full_user_details_list as $user): ?>
                        <div class="user-card">
                            <div class="profile-pic-container">
                                <?php if (!empty($user['profile_photo'])): ?>
                                    <img src="../uploads/<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Foto de <?php echo htmlspecialchars($user['name']); ?>" class="profile-pic">
                                <?php else: ?>
                                    <div class="no-pic"><i class="fas fa-user-circle fa-3x"></i></div>
                                <?php endif; ?>
                            </div>
                            <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                            
                            <div class="detail-item"><i class="fas fa-id-card"></i> <strong>ID:</strong> <?php echo htmlspecialchars($user['id']); ?></div>
                            <div class="detail-item"><i class="fas fa-envelope"></i> <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></div>
                            <div class="detail-item"><i class="fas fa-id-badge"></i> <strong>CPF:</strong> <?php echo htmlspecialchars($user['cpf']); ?></div>
                            <div class="detail-item"><i class="fas fa-building"></i> <strong>Secretaria:</strong> <?php echo htmlspecialchars($user['secretaria']); ?></div>
                            <div class="detail-item"><i class="fas fa-user-tag"></i> <strong>Função:</strong> <?php echo htmlspecialchars($user['role']); ?></div>
                            <div class="detail-item"><i class="fas fa-phone"></i> <strong>Telefone:</strong> <?php echo htmlspecialchars($user['number'] ?: 'N/A'); ?></div>
                            
                            <?php if ($display_mode == 'full_details_list_view' && $searchType !== 'todos' && $searchType !== 'secretaria' ): // Ação apenas se não for lista completa ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>