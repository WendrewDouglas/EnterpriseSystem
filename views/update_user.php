<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $role = $_POST['role'] ?? '';
    $permissions = $_POST['permissions'] ?? [];

    if (empty($name) || empty($email) || empty($role)) {
        $_SESSION['error_message'] = "Todos os campos são obrigatórios.";
        header("Location: index.php?page=edit_user&id=" . $user_id);
        exit();
    }

    $db = new Database();
    $conn = $db->getConnection(); // recurso sqlsrv_connect

    // Iniciar transação
    if (sqlsrv_begin_transaction($conn) === false) {
        $errors = sqlsrv_errors();
        error_log("Falha ao iniciar transação: " . print_r($errors, true));
        $_SESSION['error_message'] = "Erro interno.";
        header("Location: index.php?page=edit_user&id=" . $user_id);
        exit();
    }

    try {
        // Processar a imagem se enviada
        $imagem_base64 = null;
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            $arquivo_temporario = $_FILES['imagem']['tmp_name'];
            $dados_imagem = file_get_contents($arquivo_temporario);
            $imagem_base64 = base64_encode($dados_imagem);
        } elseif (isset($_POST['imagem_base64']) && !empty($_POST['imagem_base64'])) {
            $imagem_base64 = $_POST['imagem_base64'];
        }

        // 1. Atualizar informações básicas do usuário
        $tsql = "UPDATE users SET name = ?, email = ?, role = ?";
        $params = [$name, $email, $role];

        if ($imagem_base64 !== null) {
            $tsql .= ", imagem = ?";
            $params[] = $imagem_base64;
        }
        $tsql .= " WHERE id = ?";
        $params[] = $user_id;

        $stmt = sqlsrv_prepare($conn, $tsql, $params);
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Erro ao preparar atualização de usuário: " . print_r($errors, true));
        }
        if (sqlsrv_execute($stmt) === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Erro ao atualizar usuário: " . print_r($errors, true));
        }
        sqlsrv_free_stmt($stmt);

        // 2. Remover permissões anteriores
        $tsql_delete = "DELETE FROM user_permissions WHERE user_id = ?";
        $stmt_delete = sqlsrv_prepare($conn, $tsql_delete, [$user_id]);
        if ($stmt_delete === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Erro ao preparar remoção de permissões: " . print_r($errors, true));
        }
        if (sqlsrv_execute($stmt_delete) === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Erro ao remover permissões anteriores: " . print_r($errors, true));
        }
        sqlsrv_free_stmt($stmt_delete);

        // 3. Inserir novas permissões
        if (!empty($permissions) && is_array($permissions)) {
            $available_pages = [
                'dashboard','users','add_user','edit_user','configuracoes',
                'apontar_forecast','consulta_lancamentos','historico_forecast',
                'depara_comercial','enviar_sellout','export_sellout','financeiro',
                'cursos','forecast_geral','novo_objetivo','aprovacao_OKR',
                'novo_kr','cadastrar_cursos'
            ];
            $valuesClauses = [];
            $paramsInsert = [];
            foreach ($available_pages as $page) {
                $has_access = isset($permissions[$page]) ? 1 : 0;
                // Para cada página, inserimos um registro
                $valuesClauses[] = "(?, ?, ?)";
                $paramsInsert[] = $user_id;
                $paramsInsert[] = $page;
                $paramsInsert[] = $has_access;
            }
            if (!empty($valuesClauses)) {
                $tsql_insert = "INSERT INTO user_permissions (user_id, page_name, has_access) VALUES "
                              . implode(", ", $valuesClauses);
                $stmt_insert = sqlsrv_prepare($conn, $tsql_insert, $paramsInsert);
                if ($stmt_insert === false) {
                    $errors = sqlsrv_errors();
                    throw new Exception("Erro ao preparar inserção de permissões: " . print_r($errors, true));
                }
                if (sqlsrv_execute($stmt_insert) === false) {
                    $errors = sqlsrv_errors();
                    throw new Exception("Erro ao inserir novas permissões: " . print_r($errors, true));
                }
                sqlsrv_free_stmt($stmt_insert);
            }
        }

        // Commit
        if (sqlsrv_commit($conn) === false) {
            $errors = sqlsrv_errors();
            throw new Exception("Erro ao confirmar transação: " . print_r($errors, true));
        }
        $_SESSION['success_message'] = "Usuário atualizado com sucesso!";
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        error_log($e->getMessage());
        $_SESSION['error_message'] = "Erro ao atualizar usuário: " . $e->getMessage();
    } finally {
        if ($conn) {
            sqlsrv_close($conn);
        }
        header("Location: index.php?page=users");
        exit();
    }
}
?>
