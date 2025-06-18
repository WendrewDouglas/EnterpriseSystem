<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST['id'];
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $role = $_POST['role'];
    $permissions = $_POST['permissions'] ?? [];

    if (empty($name) || empty($email) || empty($role)) {
        $_SESSION['error_message'] = "Todos os campos são obrigatórios.";
        header("Location: index.php?page=edit_user&id=" . $user_id);
        exit();
    }

    $db = new Database();
    $conn = $db->getConnection();
    
    // Iniciar transação para garantir consistência dos dados
    sqlsrv_begin_transaction($conn);
    
    try {
        // 1. Atualizar informações básicas do usuário
        $sql = "UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?";
        $params = array($name, $email, $role, $user_id);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            throw new Exception("Erro ao atualizar usuário: " . print_r(sqlsrv_errors(), true));
        }
        
        // 2. Remover permissões anteriores
        $sql_delete = "DELETE FROM user_permissions WHERE user_id = ?";
        $stmt_delete = sqlsrv_query($conn, $sql_delete, [$user_id]);
        
        if ($stmt_delete === false) {
            throw new Exception("Erro ao remover permissões anteriores: " . print_r(sqlsrv_errors(), true));
        }
        
        // 3. Inserir novas permissões
        if (!empty($permissions)) {
            // Preparar declaração para inserção em massa
            $values = [];
            $params = [];
            
            // Obter lista de páginas disponíveis no sistema
            $available_pages = [
                'dashboard', 'users', 'add_user', 'edit_user', 'configuracoes',
                'apontar_forecast', 'consulta_lancamentos', 'historico_forecast',
                'depara_comercial', 'enviar_sellout', 'export_sellout', 'financeiro',
                'cursos', 'forecast_geral', 'novo_objetivo', 'aprovacao_OKR', 'novo_kr'
            ];
            
            // Para cada página disponível, verificar se o usuário tem permissão
            foreach ($available_pages as $page) {
                $has_access = isset($permissions[$page]) ? 1 : 0;
                $values[] = "(?, ?, ?)";
                array_push($params, $user_id, $page, $has_access);
            }
            
            // Inserir todas as permissões de uma vez
            $sql_insert = "INSERT INTO user_permissions (user_id, page_name, has_access) VALUES " . implode(", ", $values);
            $stmt_insert = sqlsrv_query($conn, $sql_insert, $params);
            
            if ($stmt_insert === false) {
                throw new Exception("Erro ao inserir novas permissões: " . print_r(sqlsrv_errors(), true));
            }
        }
        
        // Confirmar transação
        sqlsrv_commit($conn);
        $_SESSION['success_message'] = "Usuário atualizado com sucesso!";
        
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        sqlsrv_rollback($conn);
        error_log($e->getMessage());
        $_SESSION['error_message'] = "Erro ao atualizar usuário: " . $e->getMessage();
    }

    header("Location: index.php?page=users");
    exit();
}
?>
