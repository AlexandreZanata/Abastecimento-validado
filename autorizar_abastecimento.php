<?php
session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$secretaria = $_SESSION['secretaria'];

try {
    $stmt = $conn->prepare("SELECT v.id, v.veiculo, v.tipo, v.placa, v.tanque, v.combustivel
                           FROM veiculos v
                           JOIN usuarios u ON v.id = u.codigo_veiculo
                           WHERE u.id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $veiculo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$veiculo) {
        ?>
        <!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Erro - Veículo não encontrado</title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                                'warning-vivo': '#FFD700',
                                'warning-custom': '#FBBF24',
                                'danger': '#EF4444',
                                'orange-vibrant': '#FF6B00',
                                'orange-vibrant-dark': '#E05D00',
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
                    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
                }
                .error-card {
                    background: white;
                    border-radius: 0.75rem;
                    padding: 1rem;
                    margin: 1rem 0;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                    border-left: 4px solid #EF4444;
                }
                .button-container {
                    display: flex;
                    flex-direction: column;
                    gap: 0.75rem;
                    margin-top: 1.5rem;
                }
                .btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0.75rem 1.5rem;
                    border-radius: 0.75rem;
                    font-weight: 600;
                    transition: all 0.2s ease;
                    text-decoration: none;
                }
                .btn i {
                    margin-right: 0.5rem;
                }
                .btn-back {
                    background: linear-gradient(135deg, #4F46E5, #4338CA);
                    color: white;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem; /* Espaçamento entre ícone e texto */
                    padding: 0.75rem 1.5rem;
                    border-radius: 0.75rem;
                    font-weight: 600;
                    font-size: 1rem; /* Tamanho do texto */
                    transition: all 0.2s ease;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }

                .btn-back i {
                    font-size: 1.25rem; /* Ajusta o tamanho do ícone */
                }

                .btn-back:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
                }
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
            </style>
        </head>
        <body>
            <div class="app-container relative">
                <!-- Logo Header -->
                <div class="logo-container h-48 rounded-b-3xl flex flex-col items-center justify-center shadow-hard relative">
                <a href="<?php
                    if (!isset($_SESSION['role'])) {
                        echo '../login.php';
                    } elseif ($_SESSION['role'] === 'user') {
                        echo '../menu.php';
                    } elseif ($_SESSION['role'] === 'admin') {
                        echo '../menuadm.php';
                    } elseif ($_SESSION['role'] === 'geraladm') {
                        echo '../menugeraladm.php';
                    } else {
                        echo '../login.php';
                    }
                ?>"  class="nav-button bg-white absolute top-6 left-6 cursor-pointer flex items-center justify-center w-10 h-10 rounded-full shadow-soft hover:shadow-hard transition-shadow">
                    <i class="fas fa-chevron-left text-primary text-lg"></i>
                </a>
                    <div class="bg-white/20 p-4 rounded-full mb-4">
                        <i class="fas fa-exclamation-triangle text-white text-4xl"></i>
                    </div>
                    <h1 class="text-white text-2xl font-bold">Erro no Veículo</h1>
                </div>

                <!-- Forms Container -->
                <div class="px-5 pb-6 -mt-8 relative">
                    <div class="bg-white rounded-2xl p-6 shadow-hard">
                        <div class="error-card">
                            <p class="text-danger font-bold text-center mb-4">Nenhum veículo vinculado ao usuário.</p>
                            <p class="text-gray-600 text-sm text-center">
                                Você não tem um veículo vinculado à sua conta. Entre em contato com o administrador.
                            </p>
                        </div>

                        <div class="button-container">
                            <a href="<?php
                                if (!isset($_SESSION['role'])) {
                                    echo '../login.php';
                                } elseif ($_SESSION['role'] === 'user') {
                                    echo '../menu.php';
                                } elseif ($_SESSION['role'] === 'admin') {
                                    echo '../menuadm.php';
                                } elseif ($_SESSION['role'] === 'geraladm') {
                                    echo '../menugeraladm.php';
                                } else {
                                    echo '../login.php';
                                }
                            ?>" class="btn btn-back w-full">
                                <i class="fas fa-arrow-left"></i> Voltar ao Menu
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }

    // Mapeamento para combustíveis específicos
    $combustiveis_map = [
        'Diesel-S500' => 'diesel',
        'Diesel S10' => 'diesel_s10'
    ];

    // Verifica e aplica mapeamento, se necessário
    $combustivel_original = $veiculo['combustivel'];
    $combustivel_veiculo = isset($combustiveis_map[$combustivel_original])
        ? $combustiveis_map[$combustivel_original]
        : strtolower(str_replace('-', '_', $combustivel_original)); // Substitui traços por sublinhados e converte para minúsculas

    // Exibir valores para depuração (remova em produção)
    error_log("Combustível original: $combustivel_original");
    error_log("Combustível mapeado: $combustivel_veiculo");

    // Verifica se o combustível mapeado é válido
    $combustiveis_validos = ['etanol', 'gasolina', 'diesel', 'diesel_s10'];
    if (!in_array($combustivel_veiculo, $combustiveis_validos)) {
        ?>
        <!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Erro - Combustível inválido</title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                                'warning-vivo': '#FFD700',
                                'warning-custom': '#FBBF24',
                                'danger': '#EF4444',
                                'orange-vibrant': '#FF6B00',
                                'orange-vibrant-dark': '#E05D00',
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
                    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
                }
                .error-card {
                    background: white;
                    border-radius: 0.75rem;
                    padding: 1rem;
                    margin: 1rem 0;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                    border-left: 4px solid #EF4444;
                }
                .button-container {
                    display: flex;
                    flex-direction: column;
                    gap: 0.75rem;
                    margin-top: 1.5rem;
                }
                .btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0.75rem 1.5rem;
                    border-radius: 0.75rem;
                    font-weight: 600;
                    transition: all 0.2s ease;
                    text-decoration: none;
                }
                .btn i {
                    margin-right: 0.5rem;
                }
                .btn-back {
                    background: linear-gradient(135deg, #4F46E5, #4338CA);
                    color: white;
                }
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
            </style>
        </head>
        <body>
            <div class="app-container relative">
                <!-- Logo Header -->
                <div class="logo-container h-48 rounded-b-3xl flex flex-col items-center justify-center shadow-hard relative">
                <a href="<?php
                    if (!isset($_SESSION['role'])) {
                        echo '../login.php';
                    } elseif ($_SESSION['role'] === 'user') {
                        echo '../menu.php';
                    } elseif ($_SESSION['role'] === 'admin') {
                        echo '../menuadm.php';
                    } elseif ($_SESSION['role'] === 'geraladm') {
                        echo '../menugeraladm.php';
                    } else {
                        echo '../login.php';
                    }
                ?>"  class="nav-button bg-white absolute top-6 left-6 cursor-pointer flex items-center justify-center w-10 h-10 rounded-full shadow-soft hover:shadow-hard transition-shadow">
                    <i class="fas fa-chevron-left text-primary text-lg"></i>
                </a>
                    <div class="bg-white/20 p-4 rounded-full mb-4">
                        <i class="fas fa-exclamation-triangle text-white text-4xl"></i>
                    </div>
                    <h1 class="text-white text-2xl font-bold">Erro no Combustível</h1>
                </div>

                <!-- Forms Container -->
                <div class="px-5 pb-6 -mt-8 relative">
                    <div class="bg-white rounded-2xl p-6 shadow-hard">
                        <div class="error-card">
                            <p class="text-danger font-bold text-center mb-4">Combustível inválido associado ao veículo.</p>
                            <p class="text-gray-600 text-sm text-center">
                                O combustível "<?= htmlspecialchars($combustivel_veiculo) ?>" não é válido. Entre em contato com o administrador.
                            </p>
                        </div>

                        <div class="button-container">
                            <a href="<?php
                                if (!isset($_SESSION['role'])) {
                                    echo '../login.php';
                                } elseif ($_SESSION['role'] === 'user') {
                                    echo '../menu.php';
                                } elseif ($_SESSION['role'] === 'admin') {
                                    echo '../menuadm.php';
                                } elseif ($_SESSION['role'] === 'geraladm') {
                                    echo '../menugeraladm.php';
                                } else {
                                    echo '../login.php';
                                }
                            ?>" class="btn btn-back w-full">
                                <i class="fas fa-arrow-left"></i> Voltar ao Menu
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }

    $posto_ativo = $conn->prepare("SELECT ps.posto_nome
                                FROM posto_semanal_agendamentos ps
                                WHERE ps.status = 'ativo'
                                AND (
                                    JSON_CONTAINS(ps.secretarias, JSON_QUOTE(:secretaria)) OR
                                    ps.secretarias = 'todas'
                                )
                                AND JSON_CONTAINS(ps.combustiveis, JSON_QUOTE(:combustivel))
                                ORDER BY
                                    JSON_CONTAINS(ps.secretarias, JSON_QUOTE(:secretaria)) DESC,
                                    ps.secretarias = 'todas' DESC
                                LIMIT 1");
    $posto_ativo->bindParam(':secretaria', $secretaria);
    $posto_ativo->bindParam(':combustivel', $combustivel_veiculo);
    $posto_ativo->execute();
    $posto_ativo = $posto_ativo->fetch(PDO::FETCH_ASSOC);

    if (!$posto_ativo) {
        ?>
        <!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Erro - Posto não encontrado</title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                                'warning-vivo': '#FFD700',
                                'warning-custom': '#FBBF24',
                                'danger': '#EF4444',
                                'orange-vibrant': '#FF6B00',
                                'orange-vibrant-dark': '#E05D00',
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
                    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
                }
                .error-card {
                    background: white;
                    border-radius: 0.75rem;
                    padding: 1rem;
                    margin: 1rem 0;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                    border-left: 4px solid #EF4444;
                }
                .button-container {
                    display: flex;
                    flex-direction: column;
                    gap: 0.75rem;
                    margin-top: 1.5rem;
                }
                .btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0.75rem 1.5rem;
                    border-radius: 0.75rem;
                    font-weight: 600;
                    transition: all 0.2s ease;
                    text-decoration: none;
                }
                .btn i {
                    margin-right: 0.5rem;
                }
                .btn-back {
                    background: linear-gradient(135deg, #4F46E5, #4338CA);
                    color: white;
                }
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
            </style>
        </head>
        <body>
            <div class="app-container relative">
                <!-- Logo Header -->
                <div class="logo-container h-48 rounded-b-3xl flex flex-col items-center justify-center shadow-hard relative">
                <a href="<?php
                    if (!isset($_SESSION['role'])) {
                        echo '../login.php';
                    } elseif ($_SESSION['role'] === 'user') {
                        echo '../menu.php';
                    } elseif ($_SESSION['role'] === 'admin') {
                        echo '../menuadm.php';
                    } elseif ($_SESSION['role'] === 'geraladm') {
                        echo '../menugeraladm.php';
                    } else {
                        echo '../login.php';
                    }
                ?>"  class="nav-button bg-white absolute top-6 left-6 cursor-pointer flex items-center justify-center w-10 h-10 rounded-full shadow-soft hover:shadow-hard transition-shadow">
                    <i class="fas fa-chevron-left text-primary text-lg"></i>
                </a>
                    <div class="bg-white/20 p-4 rounded-full mb-4">
                        <i class="fas fa-exclamation-triangle text-white text-4xl"></i>
                    </div>
                    <h1 class="text-white text-2xl font-bold">Erro no Abastecimento</h1>
                </div>

                <!-- Forms Container -->
                <div class="px-5 pb-6 -mt-8 relative">
                    <div class="bg-white rounded-2xl p-6 shadow-hard">
                        <div class="error-card">
                            <p class="text-danger font-bold text-center mb-4">Nenhum posto ativo encontrado no agendamento semanal.</p>
                            <p class="text-gray-600 text-sm text-center">
                                Não foi encontrado nenhum posto de combustível ativo para sua secretaria e tipo de combustível.
                            </p>
                        </div>

                        <div class="button-container">
                            <a href="<?php
                                if (!isset($_SESSION['role'])) {
                                    echo '../login.php';
                                } elseif ($_SESSION['role'] === 'user') {
                                    echo '../menu.php';
                                } elseif ($_SESSION['role'] === 'admin') {
                                    echo '../menuadm.php';
                                } elseif ($_SESSION['role'] === 'geraladm') {
                                    echo '../menugeraladm.php';
                                } else {
                                    echo '../login.php';
                                }
                            ?>" class="btn btn-back w-full">
                                <i class="fas fa-arrow-left"></i> Voltar ao Menu
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }

    $posto = $conn->prepare("SELECT id, name FROM usuarios WHERE role = 'posto' AND name = :posto_nome");
    $posto->bindParam(':posto_nome', $posto_ativo['posto_nome']);
    $posto->execute();
    $posto = $posto->fetch(PDO::FETCH_ASSOC);

    if (!$posto) {
        ?>
        <!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Erro - Posto não encontrado</title>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = {
                    theme: {
                        extend: {
                            colors: {
                                'primary': '#6E6E75',
                                'primary-dark': '#4338CA',
                                'secondary': '#F59E0B',
                                'accent': '#10B981',
                                'success': '#10B981',
                                'warning': '#F59E0B',
                                'warning-vivo': '#FFD700',
                                'warning-custom': '#FBBF24',
                                'danger': '#EF4444',
                                'orange-vibrant': '#FF6B00',
                                'orange-vibrant-dark': '#E05D00',
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
                    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
                }
                .error-card {
                    background: white;
                    border-radius: 0.75rem;
                    padding: 1rem;
                    margin: 1rem 0;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                    border-left: 4px solid #EF4444;
                }
                .button-container {
                    display: flex;
                    flex-direction: column;
                    gap: 0.75rem;
                    margin-top: 1.5rem;
                }
                .btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0.75rem 1.5rem;
                    border-radius: 0.75rem;
                    font-weight: 600;
                    transition: all 0.2s ease;
                    text-decoration: none;
                }
                .btn i {
                    margin-right: 0.5rem;
                }
                .btn-back {
                    background: linear-gradient(135deg, #4F46E5, #4338CA);
                    color: white;
                }
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                }
            </style>
        </head>
        <body>
            <div class="app-container relative">
                <!-- Logo Header -->
                <div class="logo-container h-48 rounded-b-3xl flex flex-col items-center justify-center shadow-hard relative">
                <a href="<?php
                    if (!isset($_SESSION['role'])) {
                        echo '../login.php';
                    } elseif ($_SESSION['role'] === 'user') {
                        echo '../menu.php';
                    } elseif ($_SESSION['role'] === 'admin') {
                        echo '../menuadm.php';
                    } elseif ($_SESSION['role'] === 'geraladm') {
                        echo '../menugeraladm.php';
                    } else {
                        echo '../login.php';
                    }
                ?>"  class="nav-button bg-white absolute top-6 left-6 cursor-pointer flex items-center justify-center w-10 h-10 rounded-full shadow-soft hover:shadow-hard transition-shadow">
                    <i class="fas fa-chevron-left text-primary text-lg"></i>
                </a>
                    <div class="bg-white/20 p-4 rounded-full mb-4">
                        <i class="fas fa-exclamation-triangle text-white text-4xl"></i>
                    </div>
                    <h1 class="text-white text-2xl font-bold">Erro no Posto</h1>
                </div>

                <!-- Forms Container -->
                <div class="px-5 pb-6 -mt-8 relative">
                    <div class="bg-white rounded-2xl p-6 shadow-hard">
                        <div class="error-card">
                            <p class="text-danger font-bold text-center mb-4">Posto ativo não encontrado na base de usuários.</p>
                            <p class="text-gray-600 text-sm text-center">
                                O posto configurado no agendamento semanal não foi encontrado no sistema.
                            </p>
                        </div>

                        <div class="button-container">
                            <a href="<?php
                                if (!isset($_SESSION['role'])) {
                                    echo '../login.php';
                                } elseif ($_SESSION['role'] === 'user') {
                                    echo '../menu.php';
                                } elseif ($_SESSION['role'] === 'admin') {
                                    echo '../menuadm.php';
                                } elseif ($_SESSION['role'] === 'geraladm') {
                                    echo '../menugeraladm.php';
                                } else {
                                    echo '../login.php';
                                }
                            ?>" class="btn btn-back w-full">
                                <i class="fas fa-arrow-left"></i> Voltar ao Menu
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['autorizar_abastecimento'])) {
        $posto_id = $_POST['posto_id'];
        $km_abastecido = $_POST['km_abastecido'];

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
                        'primary': '#000000',
                        'primary-dark': '#4338CA',
                        'secondary': '#F59E0B',
                        'accent': '#10B981',
                        'success': '#10B981',
                        'warning': '#F59E0B',
                        'warning-vivo': '#FFD700',
                        'warning-custom': '#FBBF24',
                        'danger': '#EF4444',
                        'orange-vibrant': '#FF6B00',
                        'orange-vibrant-dark': '#E05D00',
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
        <!-- Logo Header -->
<div class="logo-container h-48 rounded-b-3xl flex flex-col items-center justify-center shadow-hard relative">
    <a href="<?php
        if (!isset($_SESSION['role'])) {
            echo '../login.php';
        } elseif ($_SESSION['role'] === 'user') {
            echo '../menu.php';
        } elseif ($_SESSION['role'] === 'admin') {
            echo '../menuadm.php';
        } elseif ($_SESSION['role'] === 'geraladm') {
            echo '../menugeraladm.php';
        } else {
            echo '../login.php';
        }
    ?>"  class="nav-button bg-white absolute top-6 left-6 cursor-pointer flex items-center justify-center w-10 h-10 rounded-full shadow-soft hover:shadow-hard transition-shadow">
        <i class="fas fa-chevron-left text-primary text-lg"></i>
    </a>
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
            <!-- Posto de Combustível destacado -->
            <div class="bg-primary/10 p-3 rounded-xl border border-primary/20 mb-4">
                <label class="block text-sm font-medium text-primary mb-2">Posto da Semana</label>
                <div class="input-field bg-white rounded-lg p-3 border border-primary/30">
                    <input type="text" class="w-full bg-transparent focus:outline-none text-primary font-medium"
                           value="<?= $posto['name'] ?>" readonly>
                    <input type="hidden" name="posto_id" value="<?= $posto['id'] ?>">
                </div>
            </div>

            <!-- Restante dos campos do formulário -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Veículo</label>
                <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200 flex gap-4">
                    <span class="bg-transparent focus:outline-none"><?= $veiculo['veiculo'] ?></span>
                    <span class="bg-transparent focus:outline-none"><?= $veiculo['tipo'] ?></span>
                </div>
            </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Placa</label>
                        <div class="input-field bg-gray-50 rounded-xl p-3 border border-gray-200">
                            <input type="text" class="w-full bg-transparent focus:outline-none"
                                   value="<?= $veiculo['placa'] ?>" readonly>
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
                            <input type="text" class="w-full bg-transparent focus:outline-none"
                                   value="<?= $posto['name'] ?>" readonly>
                            <input type="hidden" name="posto_id" value="<?= $posto['id'] ?>">
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
                                    </div>

                                    <?php if ($abastecimento['status'] == 'aguardando_assinatura'): ?>
                                        <div class="mt-2 pt-2 border-t border-gray-200">
                                            <p class="text-sm"><span class="font-medium">Litros:</span> <?= $abastecimento['litros'] ?? '--' ?></p>
                                            <p class="text-sm"><span class="font-medium">Combustível:</span> <?= $abastecimento['combustivel'] ?? '--' ?></p>
                                            <p class="text-sm"><span class="font-medium">Valor:</span> R$ <?= number_format($abastecimento['valor'] ?? 0, 2, ',', '.') ?></p>
                                        </div>

                                        <div class="mt-4 flex justify-end">
                                            <a href="assinar_abastecimento.php?id=<?= $abastecimento['id'] ?>"
                                               class="w-full py-3 px-4 bg-orange-vibrant text-white font-bold rounded-xl hover:bg-orange-vibrant-dark transition duration-200 flex items-center justify-center gap-2 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                                <i class="fas fa-signature"></i>
                                                <span>Ir para assinatura</span>
                                            </a>
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
    // Função para atualizar apenas os abastecimentos pendentes
    function atualizarAbastecimentosPendentes() {
        fetch('buscar_abastecimentos.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro ao buscar os abastecimentos pendentes.');
                }
                return response.json();
            })
            .then(data => {
                const container = document.querySelector('.space-y-3');
                if (!container) return;

                container.innerHTML = '';

                if (data.length === 0) {
                    container.innerHTML = '<p class="text-gray-500 text-sm">Nenhum abastecimento pendente.</p>';
                } else {
                    data.forEach(abastecimento => {
                        const item = document.createElement('div');
                        item.className = 'abastecimento-item';
                        item.dataset.id = abastecimento.id;

                        item.innerHTML = `
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium text-gray-800">Posto: ${abastecimento.posto_name}</p>
                                    <p class="text-sm text-gray-600">KM: ${abastecimento.km_abastecido}</p>
                                    <p class="text-sm">
                                        Status:
                                        <span class="${
                                            abastecimento.status === 'aguardando_posto' ? 'text-yellow-600' :
                                            (abastecimento.status === 'aguardando_assinatura' ? 'text-blue-600' : 'text-gray-600')
                                        }">
                                            ${capitalizeStatus(abastecimento.status)}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            ${
                                abastecimento.status === 'aguardando_assinatura'
                                ? `<div class="mt-2 pt-2 border-t border-gray-200">
                                        <p class="text-sm"><span class="font-medium">Litros:</span> ${abastecimento.litros || '--'}</p>
                                        <p class="text-sm"><span class="font-medium">Combustível:</span> ${abastecimento.combustivel || '--'}</p>
                                        <p class="text-sm"><span class="font-medium">Valor:</span> R$ ${formatarValor(abastecimento.valor)}</p>
                                    </div>
                                    <div class="mt-4 flex justify-end">
                                        <a href="assinar_abastecimento.php?id=${abastecimento.id}"
                                           class="w-full py-3 px-4 bg-orange-vibrant text-white font-bold rounded-xl hover:bg-orange-vibrant-dark transition duration-200 flex items-center justify-center gap-2 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                            <i class="fas fa-signature"></i>
                                            <span>Ir para assinatura</span>
                                        </a>
                                    </div>`
                                : ''
                            }
                        `;
                        container.appendChild(item);
                    });
                }
            })
            .catch(error => {
                console.error('Erro ao atualizar abastecimentos pendentes:', error);
            });
    }

    function capitalizeStatus(status) {
        return status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    }

    function formatarValor(valor) {
        return valor ? parseFloat(valor).toFixed(2).replace('.', ',') : '0,00';
    }

    setInterval(atualizarAbastecimentosPendentes, 3000);

    document.addEventListener('DOMContentLoaded', atualizarAbastecimentosPendentes);
</script>
</body>
</html>
