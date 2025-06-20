<?php
// views/edit_user.php

require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';

$pageTitle = 'Editar Usuário - Forecast System';

// Inclui header e sidebar (index.php deve ter definido $allowed_pages antes de incluir esta view)
echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

// Verifica se $allowed_pages existe (definido em ../public/index.php)
if (!isset($allowed_pages) || !is_array($allowed_pages)) {
    echo "<div class='alert alert-danger'>A lista de páginas permitidas não está disponível.</div>";
    exit();
}

// Papéis fixos para select
$roles = [
    'admin' => 'Admin',
    'gestor' => 'Gestor',
    'consulta' => 'Consulta',
    'cursante' => 'Cursante',
    'custom' => 'Personalizado'
];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>ID de usuário inválido.</div>";
    exit();
}

$user_id = intval($_GET['id']);
$db = new Database();
$conn = $db->getConnection();

// 1. Buscar usuário
$tsql = "SELECT id, name, email, role, imagem FROM users WHERE id = ?";
$params = [$user_id];
$stmt = sqlsrv_prepare($conn, $tsql, $params);
if ($stmt === false) {
    $errors = sqlsrv_errors();
    echo "<div class='alert alert-danger'>Erro ao preparar consulta de usuário: " . htmlspecialchars(print_r($errors, true), ENT_QUOTES) . "</div>";
    exit();
}
if (sqlsrv_execute($stmt) === false) {
    $errors = sqlsrv_errors();
    echo "<div class='alert alert-danger'>Erro ao executar consulta de usuário: " . htmlspecialchars(print_r($errors, true), ENT_QUOTES) . "</div>";
    sqlsrv_free_stmt($stmt);
    exit();
}
$user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt);
if (!$user) {
    echo "<div class='alert alert-danger'>Usuário não encontrado.</div>";
    exit();
}
$name = $user['name'];
$email = $user['email'];
$role = $user['role'];
$imagem_base64 = $user['imagem'];
$current_image_src = !empty($imagem_base64) ? $imagem_base64 : '';

// 2. Montar available_pages a partir de $allowed_pages
$available_pages = [];
foreach ($allowed_pages as $pageKey) {
    // Converte slug em título legível: substitui "_" por espaço e ucfirst
    $title = str_replace('_', ' ', $pageKey);
    $title = ucfirst($title);
    $available_pages[$pageKey] = $title;
}

// 3. Buscar permissões atuais do usuário
$user_permissions = [];
$tsql_permissions = "SELECT page_name, has_access FROM user_permissions WHERE user_id = ?";
$params_perm = [$user_id];
$stmt_permissions = sqlsrv_prepare($conn, $tsql_permissions, $params_perm);
if ($stmt_permissions === false) {
    $errors = sqlsrv_errors();
    die("<div class='alert alert-danger'>Erro ao preparar consulta de permissões: " . htmlspecialchars(print_r($errors, true), ENT_QUOTES) . "</div>");
}
if (sqlsrv_execute($stmt_permissions) === false) {
    $errors = sqlsrv_errors();
    sqlsrv_free_stmt($stmt_permissions);
    die("<div class='alert alert-danger'>Erro ao executar consulta de permissões: " . htmlspecialchars(print_r($errors, true), ENT_QUOTES) . "</div>");
}
while ($row = sqlsrv_fetch_array($stmt_permissions, SQLSRV_FETCH_ASSOC)) {
    // Aqui assumimos que has_access controla tanto view quanto modify; ajuste se seu modelo for distinto
    $user_permissions[$row['page_name']] = [
        'view' => ($row['has_access'] == 1),
        'modify' => ($row['has_access'] == 1)
    ];
}
sqlsrv_free_stmt($stmt_permissions);

// 4. Se for admin, conceder todas permissões
if (isset($user['role']) && $user['role'] === 'admin') {
    foreach ($available_pages as $pageKey => $_title) {
        $user_permissions[$pageKey] = ['view' => true, 'modify' => true];
    }
}

// Permissões padrão para administradores (para uso em JS)
$admin_permissions = [];
foreach (array_keys($available_pages) as $pageKey) {
    $admin_permissions[$pageKey] = ['view' => true, 'modify' => true];
}
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
body, .main-content { font-family: 'Inter', sans-serif; }

/* Defina a largura da sidebar aqui: ajuste conforme seu layout */
:root {
    --sidebar-width: 200px; /* Antes 260px; ajuste conforme a largura real da sidebar para aproximar o conteúdo */
}

/* Área principal (conteúdo) */
.main-content {
    margin-left: var(--sidebar-width);
    padding: 1rem; /* reduzido de 2rem para 1rem */
    background: #f5f7fa;
    min-height: 100vh;
}

/* Ajuste responsivo se sidebar colapsa ou em telas menores */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 0.75rem;
    }
}

/* Estilos dos cards, inputs, botões, tabelas */
.card-custom {
    background: #fff;
    border: none;
    border-radius: .75rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.input-with-icon {
    position: relative;
}
.input-with-icon .bi {
    position: absolute;
    top: 50%;
    left: 0.75rem;
    transform: translateY(-50%);
    color: #6c757d;
}
.input-with-icon input,
.input-with-icon select {
    padding-left: 2.5rem;
}
#current_image {
    max-width: 100px;
    border-radius: 50%;
    display: block;
}
#crop_section {
    display: none;
    margin-top: 1.5rem;
}
#image_to_crop {
    max-height: 300px;
    overflow: auto;
    border: 1px solid #dee2e6;
    border-radius: .5rem;
    padding: .5rem;
}
#image_to_crop img {
    max-width: 100%;
}
#image_preview {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    overflow: hidden;
    margin-top: .5rem;
    border: 1px solid #ccc;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
}
#image_preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.spinner-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(255,255,255,0.7);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10;
    border-radius: .75rem;
}
.btn-primary {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
.btn-primary:hover {
    background-color: #0b5ed7;
    border-color: #0a58ca;
}
.table-custom th, .table-custom td {
    vertical-align: middle;
}
</style>

<div class="main-content position-relative">
  <div id="loadingOverlay" class="spinner-overlay">
    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
  </div>
  <!-- Removi padding horizontal extra para aproximar ainda mais -->
  <div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="h4 text-primary"><i class="bi bi-pencil-square me-2"></i>Editar Usuário</h2>
      <a href="index.php?page=users" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left-circle me-1"></i>Voltar</a>
    </div>

    <form action="index.php?page=update_user" method="POST" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="id" value="<?= htmlspecialchars($user['id'], ENT_QUOTES); ?>">
      <input type="hidden" name="imagem_base64_antiga" value="<?= htmlspecialchars($imagem_base64, ENT_QUOTES); ?>">

      <div class="card card-custom mb-4">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0">Informações Básicas</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label for="name" class="form-label">Nome</label>
              <div class="input-with-icon">
                <i class="bi bi-person-fill"></i>
                <input type="text" class="form-control" id="name" name="name"
                  value="<?= htmlspecialchars($user['name'], ENT_QUOTES); ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <label for="email" class="form-label">E-mail</label>
              <div class="input-with-icon">
                <i class="bi bi-envelope-fill"></i>
                <input type="email" class="form-control" id="email" name="email"
                  value="<?= htmlspecialchars($user['email'], ENT_QUOTES); ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <label for="role" class="form-label">Perfil</label>
              <div class="input-with-icon">
                <i class="bi bi-shield-lock-fill"></i>
                <select name="role" id="role" class="form-select" required onchange="updatePermissionsDisplay()">
                  <option value="" disabled>Selecione o Perfil</option>
                  <?php foreach ($roles as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>"
                      <?= ($user['role'] === $key) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($label, ENT_QUOTES) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <small class="text-muted">Administradores têm acesso completo a todas as funcionalidades.</small>
            </div>
            <div class="col-md-6">
              <label for="imagem" class="form-label">Foto de Perfil</label>
              <input type="file" class="form-control" id="imagem" name="imagem" accept="image/*"
                     onchange="handleImageChange(this)">
              <small class="text-muted">Selecione uma nova imagem para atualizar a foto de perfil.</small>
              <div class="mt-2">
                <img id="current_image"
                     src="<?= $current_image_src ?>"
                     alt="Foto de Perfil Atual">
              </div>
              <input type="hidden" name="imagem_base64" id="imagem_base64">
            </div>
          </div>

          <div id="crop_section">
            <hr class="my-4">
            <h5 class="mb-3 text-secondary">Recortar Imagem</h5>
            <div class="row">
              <div class="col-md-6">
                <div id="image_to_crop">
                  <img id="cropable_image" src="#" alt="A recortar">
                </div>
              </div>
              <div class="col-md-6 d-flex flex-column align-items-center">
                <div class="text-muted">Pré-visualização</div>
                <div id="image_preview">
                  <img id="cropped_preview" src="#" alt="Preview">
                </div>
              </div>
            </div>
            <div class="mt-3 d-flex gap-2">
              <button type="button" class="btn btn-primary" id="crop_button"><i class="bi bi-crop me-1"></i>Recortar e Salvar</button>
              <button type="button" class="btn btn-secondary" id="cancel_crop_button" onclick="cancelCrop()"><i class="bi bi-x-circle me-1"></i>Cancelar</button>
            </div>
          </div>
        </div>
      </div>

      <div id="permissionsCard" class="card card-custom mb-4" <?= ($user['role'] === 'admin') ? 'style="display:none;"' : ''; ?>>
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0">Permissões de Acesso</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered table-custom mb-0">
              <thead class="table-light">
                <tr>
                  <th>Página</th>
                  <th class="text-center">Visualizar</th>
                  <th class="text-center">Modificar</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($available_pages as $pageKey => $page_title): ?>
                  <tr>
                    <td><?= htmlspecialchars($page_title, ENT_QUOTES) ?></td>
                    <td class="text-center">
                      <div class="form-check d-flex justify-content-center">
                        <input class="form-check-input permission-view" type="checkbox"
                          name="permissions[<?= htmlspecialchars($pageKey, ENT_QUOTES) ?>][view]"
                          id="perm_view_<?= htmlspecialchars($pageKey, ENT_QUOTES) ?>"
                          value="1"
                          <?= (isset($user_permissions[$pageKey]['view']) && $user_permissions[$pageKey]['view']) ? 'checked' : ''; ?>>
                      </div>
                    </td>
                    <td class="text-center">
                      <div class="form-check d-flex justify-content-center">
                        <input class="form-check-input permission-modify" type="checkbox"
                          name="permissions[<?= htmlspecialchars($pageKey, ENT_QUOTES) ?>][modify]"
                          id="perm_modify_<?= htmlspecialchars($pageKey, ENT_QUOTES) ?>"
                          value="1"
                          onchange="ensureViewPermission(this, '<?= htmlspecialchars($pageKey, ENT_QUOTES) ?>')"
                          <?= (isset($user_permissions[$pageKey]['modify']) && $user_permissions[$pageKey]['modify']) ? 'checked' : ''; ?>>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salvar Alterações</button>
        <a href="index.php?page=users" class="btn btn-secondary"><i class="bi bi-x-circle me-1"></i>Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
const adminPermissions = <?= json_encode($admin_permissions, JSON_UNESCAPED_UNICODE) ?>;
const availablePages = <?= json_encode(array_keys($available_pages), JSON_UNESCAPED_UNICODE) ?>;
const currentUserPermissions = <?= json_encode($user_permissions, JSON_UNESCAPED_UNICODE) ?>;

function updatePermissionsDisplay() {
    const role = document.getElementById('role').value;
    const permissionsCard = document.getElementById('permissionsCard');
    if (role === 'admin') {
        permissionsCard.style.display = 'none';
        availablePages.forEach(page => {
            if (!document.getElementById(`hidden_view_${page}`)) {
                const hiddenView = document.createElement('input');
                hiddenView.type = 'hidden';
                hiddenView.name = `permissions[${page}][view]`;
                hiddenView.id = `hidden_view_${page}`;
                hiddenView.value = '1';
                document.querySelector('form').appendChild(hiddenView);
                const hiddenModify = document.createElement('input');
                hiddenModify.type = 'hidden';
                hiddenModify.name = `permissions[${page}][modify]`;
                hiddenModify.id = `hidden_modify_${page}`;
                hiddenModify.value = '1';
                document.querySelector('form').appendChild(hiddenModify);
            }
        });
    } else {
        permissionsCard.style.display = 'block';
        availablePages.forEach(page => {
            const hiddenView = document.getElementById(`hidden_view_${page}`);
            const hiddenModify = document.getElementById(`hidden_modify_${page}`);
            if (hiddenView) hiddenView.remove();
            if (hiddenModify) hiddenModify.remove();
        });
        restoreUserPermissions();
    }
}

function restoreUserPermissions() {
    document.querySelectorAll('.permission-view, .permission-modify').forEach(cb => cb.checked = false);
    availablePages.forEach(page => {
        const viewCb = document.getElementById(`perm_view_${page}`);
        const modifyCb = document.getElementById(`perm_modify_${page}`);
        if (viewCb && currentUserPermissions[page] && currentUserPermissions[page]['view']) {
            viewCb.checked = true;
        }
        if (modifyCb && currentUserPermissions[page] && currentUserPermissions[page]['modify']) {
            modifyCb.checked = true;
        }
    });
}

function ensureViewPermission(modifyCheckbox, page) {
    if (modifyCheckbox.checked) {
        document.getElementById(`perm_view_${page}`).checked = true;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updatePermissionsDisplay();
});

// Funções do Cropper.js
function handleImageChange(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const imageToCrop = document.getElementById('cropable_image');
            imageToCrop.src = e.target.result;
            document.getElementById('crop_section').style.display = 'block';
            document.getElementById('current_image').style.display = 'none';
            initializeCropper();
        };
        reader.readAsDataURL(file);
    }
}
let cropper;
function initializeCropper() {
    const imageToCrop = document.getElementById('cropable_image');
    if (!imageToCrop) return;
    if (cropper) cropper.destroy();
    try {
        cropper = new Cropper(imageToCrop, {
            aspectRatio: 1,
            viewMode: 1,
            cropBoxMovable: true,
            cropBoxResizable: true,
            ready() {
                const initialWidth = 200;
                const initialHeight = 200;
                const startX = (imageToCrop.offsetWidth - initialWidth) / 2;
                const startY = (imageToCrop.offsetHeight - initialHeight) / 2;
                cropper.setCropBoxData({ left: startX, top: startY, width: initialWidth, height: initialHeight });
            },
            crop() {
                const canvas = cropper.getCroppedCanvas();
                document.getElementById('cropped_preview').src = canvas.toDataURL();
            },
        });
    } catch (err) {
        console.error('Erro ao inicializar Cropper:', err);
    }
}

document.getElementById('crop_button')?.addEventListener('click', function() {
    if (!cropper) return;
    const canvas = cropper.getCroppedCanvas();
    const dataURL = canvas.toDataURL();
    // Se quiser validar base64, implemente isValidBase64 globalmente
    document.getElementById('imagem_base64').value = dataURL;
    document.getElementById('current_image').src = dataURL;
    document.getElementById('current_image').style.display = 'block';
    document.getElementById('imagem').value = '';
    document.getElementById('crop_section').style.display = 'none';
    cropper.destroy();
    cropper = null;
});

function cancelCrop() {
    document.getElementById('crop_section').style.display = 'none';
    document.getElementById('imagem').value = '';
    const currentImage = document.getElementById('current_image');
    if (currentImage && currentImage.src) {
        currentImage.style.display = 'block';
    }
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }
}
</script>

<?php
include __DIR__ . '/../templates/footer.php';
if (isset($conn)) {
    sqlsrv_close($conn);
}
?>
