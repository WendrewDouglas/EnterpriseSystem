<?php
// views/add_user.php

require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';

$pageTitle = 'Adicionar Novo Usuário - Forecast System';

$roles = ['admin' => 'Administrador', 'gestor' => 'Gestor', 'consulta' => 'Consulta', 'cursante' => 'Cursante'];

// Inclua o CSS do Cropper.js no cabeçalho
echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">';

// Conexão com o banco
$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recebe e sanitiza inputs
    $name = trim($_POST['name']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $imagem_base64 = $_POST['imagem_base64'] ?? '';

    // Log dos dados do POST (para debug; remova em produção)
    error_log("Dados do POST: " . print_r($_POST, true));

    // Validação de campos obrigatórios
    if (empty($name) || !$email || empty($password) || empty($role)) {
        $error_message = "Todos os campos são obrigatórios e devem ser preenchidos corretamente.";
    } else {
        // Hash da senha
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // INSERT com OUTPUT para obter o ID inserido
        $tsql = "
            INSERT INTO users (name, email, password, role, imagem)
            OUTPUT INSERTED.id AS new_id
            VALUES (?, ?, ?, ?, ?)
        ";
        $params = [$name, $email, $hashedPassword, $role, $imagem_base64];

        // Preparar
        $stmt = sqlsrv_prepare($conn, $tsql, $params);
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $error_message = "Erro ao preparar a consulta: " . print_r($errors, true);
            error_log($error_message);
        } else {
            // Executar
            if (sqlsrv_execute($stmt) === false) {
                $errors = sqlsrv_errors();
                $error_message = "Erro ao cadastrar o usuário: " . print_r($errors, true);
                error_log($error_message);
            } else {
                // Recuperar o ID inserido
                $insertedRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                if ($insertedRow && isset($insertedRow['new_id'])) {
                    $id_novo_usuario = $insertedRow['new_id'];
                    // Log da imagem e do ID
                    error_log("Imagem Base64 enviada para o banco de dados: " . $imagem_base64);
                    error_log("ID do novo usuário: " . $id_novo_usuario);
                    $_SESSION['success_message'] = "Usuário cadastrado com sucesso!";
                    header("Location: index.php?page=users");
                    sqlsrv_free_stmt($stmt);
                    exit();
                } else {
                    // Mesmo que a execução tenha retornado sem erro, não recuperou new_id
                    $error_message = "Usuário cadastrado, mas não foi possível obter o ID inserido.";
                    error_log($error_message);
                }
            }
            sqlsrv_free_stmt($stmt);
        }
    }
}

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<style>
/* Fonte moderna */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
body, .main-content { font-family: 'Inter', sans-serif; }

/* Defina a largura da sidebar aqui: ajuste conforme seu layout */
:root {
    --sidebar-width: 200px; /* Antes era 260px; ajuste conforme a largura real da sidebar para aproximar o conteúdo */
}

/* Área principal (conteúdo) */
.main-content {
    margin-left: var(--sidebar-width);
    padding: 1rem; /* Reduzido de 2rem para 1rem */
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

/* Card para o formulário */
.card-custom {
    background: #fff;
    border: none;
    border-radius: .75rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

/* Ícones dentro dos inputs */
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

/* Preview da imagem */
#current_image {
    max-width: 120px;
    border-radius: 50%;
    display: none;
}
/* Seção de recorte permanece oculta até seleção */
#crop_section {
    display: none;
    margin-top: 1.5rem;
}
/* Container do recorte: mantém overflow caso imagem seja maior */
#image_to_crop {
    max-height: 350px;
    overflow: auto;
    border: 1px solid #dee2e6;
    border-radius: .5rem;
    padding: .5rem;
}
/* Preview: fixa tamanho e usa object-fit para proporção correta */
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
    object-fit: cover; /* garante que preview preencha o círculo proporcionalmente ao recorte */
}

/* Spinner overlay, caso futuro AJAX */
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

/* Botões */
.btn-primary {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
.btn-primary:hover {
    background-color: #0b5ed7;
    border-color: #0a58ca;
}
</style>

<div class="main-content position-relative">
  <!-- Overlay para loading (se necessário em AJAX) -->
  <div id="loadingOverlay" class="spinner-overlay">
    <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
  </div>

  <!-- Removi padding horizontal extra para aproximar o conteúdo -->
  <div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="h4 text-primary"><i class="bi bi-person-plus me-2"></i>Adicionar Novo Usuário</h2>
      <a href="index.php?page=users" class="btn btn-secondary btn-sm"><i class="bi bi-arrow-left-circle me-1"></i>Voltar</a>
    </div>

    <?php if (isset($error_message)) : ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="card card-custom p-4 mb-4">
      <form method="POST" action="" enctype="multipart/form-data" novalidate>
        <div class="row g-3">
          <div class="col-md-6">
            <label for="name" class="form-label">Nome</label>
            <div class="input-with-icon">
              <i class="bi bi-person-fill"></i>
              <input type="text" class="form-control" id="name" name="name"
                     value="<?= isset($name) ? htmlspecialchars($name, ENT_QUOTES, 'UTF-8') : '' ?>"
                     required>
            </div>
          </div>
          <div class="col-md-6">
            <label for="email" class="form-label">E-mail</label>
            <div class="input-with-icon">
              <i class="bi bi-envelope-fill"></i>
              <input type="email" class="form-control" id="email" name="email"
                     value="<?= isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : '' ?>"
                     required>
            </div>
          </div>
          <div class="col-md-6">
            <label for="password" class="form-label">Senha</label>
            <div class="input-with-icon">
              <i class="bi bi-lock-fill"></i>
              <input type="password" class="form-control" id="password" name="password" required>
            </div>
          </div>
          <div class="col-md-6">
            <label for="role" class="form-label">Perfil</label>
            <div class="input-with-icon">
              <i class="bi bi-shield-lock-fill"></i>
              <select class="form-select" id="role" name="role" required>
                <option value="" disabled <?= empty($role) ? 'selected' : '' ?>>Selecione o Perfil</option>
                <?php foreach ($roles as $key => $value) : ?>
                  <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                      <?= (isset($role) && $role === $key) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="col-12">
            <label for="imagem" class="form-label">Foto de Perfil</label>
            <input type="file" class="form-control" id="imagem" name="imagem" accept="image/*"
                   onchange="handleImageChange(this)" required>
            <small class="text-muted">Selecione uma imagem para o perfil.</small>
            <div class="mt-3">
              <img id="current_image" src="" alt="Preview">
            </div>
            <input type="hidden" name="imagem_base64" id="imagem_base64"
                   value="<?= isset($imagem_base64) ? htmlspecialchars($imagem_base64, ENT_QUOTES, 'UTF-8') : '' ?>">
          </div>
        </div>

        <!-- Seção de recorte com botões de confirmar/cancelar recorte -->
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
            <button type="button" class="btn btn-secondary" id="cancel_crop_button"><i class="bi bi-x-circle me-1"></i>Cancelar Recorte</button>
          </div>
        </div>

        <div class="mt-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary" id="submit_button"><i class="bi bi-check-circle me-1"></i>Cadastrar</button>
          <a href="index.php?page=users" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Parte de recorte e pré-visualização permanece inalterada, exceto adicionamos handlers para os botões:
// Variáveis globais
let cropper;
const imageToCrop = document.getElementById('cropable_image');
const cropSection = document.getElementById('crop_section');
const imagePreview = document.getElementById('cropped_preview');
const imageInput = document.getElementById('imagem');
const currentImage = document.getElementById('current_image');
const submitButton = document.getElementById('submit_button');
const cropButton = document.getElementById('crop_button');
const cancelCropButton = document.getElementById('cancel_crop_button');

// Quando usuário escolhe imagem, exibe crop_section e inicializa Cropper
function handleImageChange(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            imageToCrop.src = e.target.result;
            cropSection.style.display = 'block';
            currentImage.style.display = 'none';
            initializeCropper();
        };
        reader.readAsDataURL(file);
    }
}

function initializeCropper() {
    if (!imageToCrop) return;
    if (cropper) {
        cropper.destroy();
    }
    try {
        cropper = new Cropper(imageToCrop, {
            aspectRatio: 1,
            viewMode: 1,
            cropBoxMovable: true,
            cropBoxResizable: true,
            ready: function () {
                const initialWidth = 200;
                const initialHeight = 200;
                const startX = (imageToCrop.offsetWidth - initialWidth) / 2;
                const startY = (imageToCrop.offsetHeight - initialHeight) / 2;
                cropper.setCropBoxData({
                    left: startX,
                    top: startY,
                    width: initialWidth,
                    height: initialHeight,
                });
            },
            crop: function(event) {
                const canvas = cropper.getCroppedCanvas();
                imagePreview.src = canvas.toDataURL();
            },
        });
    } catch (error) {
        console.error('Erro ao inicializar Cropper:', error);
    }
}

// Ao clicar em “Recortar e Salvar”, confirmamos o recorte e atualizamos o hidden + preview fixo
cropButton.addEventListener('click', function() {
    if (!cropper) return;
    const canvas = cropper.getCroppedCanvas();
    const croppedDataURL = canvas.toDataURL();
    // Atualiza hidden e preview fixo
    document.getElementById('imagem_base64').value = croppedDataURL;
    currentImage.src = croppedDataURL;
    currentImage.style.display = 'block';
    // Limpa campo file para não reconfundir
    imageInput.value = '';
    // Esconde seção de recorte e destrói cropper
    cropSection.style.display = 'none';
    cropper.destroy();
    cropper = null;
});

// Ao clicar em “Cancelar Recorte”, desfazemos o recorte em curso
cancelCropButton.addEventListener('click', function() {
    cropSection.style.display = 'none';
    imageInput.value = '';
    // Restaura preview antigo (se existia), ou oculta se vazio
    if (currentImage.src) {
        currentImage.style.display = 'block';
    } else {
        currentImage.style.display = 'none';
    }
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }
});

// O botão de submit só envia o form normalmente, mas impede envio se recorte ainda aberto
submitButton.addEventListener('click', function(event) {
    if (cropSection.style.display === 'block') {
        event.preventDefault();
        alert('Por favor, clique em "Recortar e Salvar" ou "Cancelar Recorte" antes de enviar o formulário.');
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
