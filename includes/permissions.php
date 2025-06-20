<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/index.php?error=unauthorized");
    exit();
}

// Função para verificar permissões
function verificarPermissao($requerido) {
    $userId = $_SESSION['user_id'] ?? 0;
    $usuarioRole = $_SESSION['user_role'] ?? 'consulta';
    
    // Verificar se há permissões específicas para este usuário na tabela user_permissions
    $db = new Database();
    $conn = $db->getConnection();
    
    // Buscar permissão específica para esta página
    $sql = "SELECT permission_type FROM user_permissions WHERE user_id = ? AND page_name = ? AND has_access = 1";
    $params = [$userId, $requerido];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    // Se encontrou um registro específico, usar essa permissão
    if ($stmt !== false && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        return true; // Tem permissão específica
    }
    
    // Se não encontrou permissão específica, usar as permissões padrão por perfil
    $permissoes = [
        'admin' => ['dashboard', 'OKR_consulta', 'pdv_clientes','cursos', 'users', 'consultar_okr', 'consulta_lancamentos', 'historico_forecast', 'apontar_forecast', 'configuracoes', 'depara_comercial', 'enviar_sellout', 'financeiro', 'run_forecast_update.php', 'forecast_geral', 'novo_objetivo', 'novo_kr', 'aprovacao_OKR', 'edit_user', 'add_user','export_forecast','nova_iniciativa', 'visualizar_objetivo', 'apontar_progresso'],
        'gestor' => ['dashboard', 'apontar_forecast', 'historico_forecast', 'configuracoes', 'sales_demand', 'enviar_sellout'],
        'consulta' => ['dashboard', 'configuracoes', 'historico_forecast', 'enviar_sellout']
    ];

    if (!in_array($requerido, $permissoes[$usuarioRole])) {
        header("Location: index.php?page=dashboard&error=permissao_negada");
        exit();
    }
    
    return true;
}

// Função para verificar se usuário tem permissão para uma página (sem redirecionamento)
function usuarioTemPermissao($userId, $pagina) {
    global $conn;
    
    if (!isset($conn)) {
        $db = new Database();
        $conn = $db->getConnection();
    }
    
    // Buscar permissão específica
    $sql = "SELECT permission_type FROM user_permissions WHERE user_id = ? AND page_name = ? AND has_access = 1";
    $params = [$userId, $pagina];
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt !== false && ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
        return true;
    }
    
    // Se não encontrou permissão específica, buscar o perfil do usuário
    $sql_role = "SELECT role FROM users WHERE id = ?";
    $stmt_role = sqlsrv_query($conn, $sql_role, [$userId]);
    
    if ($stmt_role !== false && ($row_role = sqlsrv_fetch_array($stmt_role, SQLSRV_FETCH_ASSOC))) {
        $role = $row_role['role'];
        
        $permissoes = [
            'admin' => ['dashboard',  'OKR_consulta', 'cursos', 'users', 'consultar_okr', 'consulta_lancamentos', 'historico_forecast', 'apontar_forecast', 'configuracoes', 'depara_comercial', 'enviar_sellout', 'financeiro', 'run_forecast_update.php', 'forecast_geral', 'novo_objetivo', 'novo_kr', 'aprovacao_OKR', 'edit_user', 'add_user', 'visualizar_objetivo','apontar_progresso'],
            'gestor' => ['dashboard', 'apontar_forecast', 'historico_forecast', 'configuracoes', 'sales_demand', 'enviar_sellout'],
            'consulta' => ['dashboard', 'configuracoes', 'historico_forecast', 'enviar_sellout']
        ];
        
        return in_array($pagina, $permissoes[$role] ?? []);
    }
    
    return false;
}
?>
