-- Script para inicializar as permissões de usuários existentes
-- Executar este script uma vez após criar a tabela user_permissions

-- Recupera todos os usuários
DECLARE @users TABLE (id INT, role VARCHAR(20));
INSERT INTO @users (id, role)
SELECT id, role FROM users WHERE status = 'ativo';

-- Páginas para cada tipo de perfil
DECLARE @admin_pages TABLE (page_name VARCHAR(50));
INSERT INTO @admin_pages (page_name) VALUES 
('dashboard'), ('users'), ('add_user'), ('edit_user'), ('configuracoes'),
('apontar_forecast'), ('consulta_lancamentos'), ('historico_forecast'),
('depara_comercial'), ('enviar_sellout'), ('export_sellout'), ('financeiro'),
('cursos'), ('forecast_geral'), ('novo_objetivo'), ('novo_kr'), ('aprovacao_OKR');

DECLARE @gestor_pages TABLE (page_name VARCHAR(50));
INSERT INTO @gestor_pages (page_name) VALUES 
('dashboard'), ('apontar_forecast'), ('historico_forecast'), 
('configuracoes'), ('enviar_sellout');

DECLARE @consulta_pages TABLE (page_name VARCHAR(50));
INSERT INTO @consulta_pages (page_name) VALUES 
('dashboard'), ('configuracoes'), ('historico_forecast'), ('enviar_sellout');

-- Inserir permissões para usuários admin
INSERT INTO user_permissions (user_id, page_name, has_access)
SELECT u.id, a.page_name, 1
FROM @users u
CROSS JOIN @admin_pages a
WHERE u.role = 'admin';

-- Inserir permissões para usuários gestor
INSERT INTO user_permissions (user_id, page_name, has_access)
SELECT u.id, g.page_name, 1
FROM @users u
CROSS JOIN @gestor_pages g
WHERE u.role = 'gestor';

-- Inserir permissões para usuários consulta
INSERT INTO user_permissions (user_id, page_name, has_access)
SELECT u.id, c.page_name, 1
FROM @users u
CROSS JOIN @consulta_pages c
WHERE u.role = 'consulta';

-- Confirmar dados inseridos
SELECT u.id, u.name, u.role, COUNT(p.id) as total_permissions
FROM users u
LEFT JOIN user_permissions p ON u.id = p.user_id AND p.has_access = 1
GROUP BY u.id, u.name, u.role
ORDER BY u.role, u.name; 