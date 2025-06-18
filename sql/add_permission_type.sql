-- Adiciona a coluna permission_type à tabela user_permissions
ALTER TABLE user_permissions
ADD permission_type VARCHAR(10) NOT NULL DEFAULT 'view';

-- Atualiza os registros existentes para terem permission_type = 'view'
UPDATE user_permissions
SET permission_type = 'view'
WHERE permission_type IS NULL;

-- Adiciona uma constraint para garantir que permission_type seja 'view' ou 'modify'
ALTER TABLE user_permissions
ADD CONSTRAINT chk_permission_type 
CHECK (permission_type IN ('view', 'modify')); 