<?php
session_start();
require '../vendor/autoload.php';
require_once '../includes/db_connection.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Variável para armazenar mensagens de feedback (para erros, por exemplo)
$error = "";

// Processa o formulário quando submetido
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Coleta e sanitiza os dados enviados
    $firstName  = trim($_POST['first_name'] ?? '');
    $lastName   = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $options    = $_POST['options'] ?? [];

    // Validação básica dos campos obrigatórios
    if (empty($firstName) || empty($lastName) || empty($email) || empty($department)) {
        $error = "Por favor, preencha todos os campos obrigatórios.";
    } else {
        // Configura e envia o e-mail utilizando PHPMailer
        $mail = new PHPMailer(true);
        try {
            // Configurações do servidor SMTP – ajuste conforme sua configuração
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';   // Substitua pelo seu servidor SMTP
            $mail->SMTPAuth   = true;
            $mail->Username   = 'wendrew.gomes@colormaq.com.br'; // Usuário do SMTP
            $mail->Password   = 'gdoc dfzb nnnt whzn';              // Senha do SMTP
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // ou ENCRYPTION_SMTPS, conforme seu servidor
            $mail->Port       = 587;                          // Porta do SMTP (ou 465 para SMTPS)

            // Ativa o envio de e-mail em HTML
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            

            // Adiciona a imagem da logomarca como embed (verifique se o caminho está correto)
            $logoPath = $_SERVER['DOCUMENT_ROOT'] . '/forecast/public/assets/img/logo_color1.png';
            $mail->addEmbeddedImage($logoPath, 'logo_cid');

            // Configura os cabeçalhos e o remetente
            $mail->setFrom($email, "{$firstName} {$lastName}");
            $mail->addAddress('suporteti@colormaq.com.br'); // Destinatário

            // Define o assunto
            $mail->Subject = 'Cadastro de usuário no dashboard';

            // Monta o conteúdo HTML do e-mail
            $htmlBody  = '<html><head><meta charset="UTF-8"><title>Cadastro de Usuário</title>';
            $htmlBody .= '<style>
                body { font-family: Arial, sans-serif; color: #333; background: #f2f2f2; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #fff; border-radius: 8px; }
                .header { text-align: center; margin-bottom: 20px; }
                .header img { max-width: 150px; }
                .content { padding: 20px; }
                .content h2 { color: #007BFF; }
                .content p { line-height: 1.5; }
                .content ul { list-style-type: disc; margin-left: 20px; }
                .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
            </style>';
            $htmlBody .= '</head><body>';
            $htmlBody .= '<div class="container">';
            $htmlBody .= '<div class="header">';
            $htmlBody .= '<img src="cid:logo_cid" alt="Logo da Empresa">';
            $htmlBody .= '</div>';
            $htmlBody .= '<div class="content">';
            $htmlBody .= '<h2>Cadastro de Usuário no Dashboard</h2>';
            $htmlBody .= '<p><strong>Nome:</strong> ' . htmlspecialchars($firstName . ' ' . $lastName) . '</p>';
            $htmlBody .= '<p><strong>Email Corporativo:</strong> ' . htmlspecialchars($email) . '</p>';
            $htmlBody .= '<p><strong>Departamento:</strong> ' . htmlspecialchars($department) . '</p>';
            $htmlBody .= '<p><strong>Opções selecionadas:</strong></p>';
            if (empty($options)) {
                $htmlBody .= '<p>Nenhuma opção selecionada.</p>';
            } else {
                $htmlBody .= '<ul>';
                foreach ($options as $option) {
                    $htmlBody .= '<li>' . htmlspecialchars($option) . '</li>';
                }
                $htmlBody .= '</ul>';
            }
            $htmlBody .= '</div>'; // Fecha .content
            $htmlBody .= '<div class="footer">';
            $htmlBody .= '<p>Esta mensagem foi enviada automaticamente. Por favor, não responda.</p>';
            $htmlBody .= '</div>';
            $htmlBody .= '</div>'; // Fecha .container
            $htmlBody .= '</body></html>';

            // Define o corpo do e-mail
            $mail->Body = $htmlBody;

            // Define uma versão alternativa em texto simples
            $mail->AltBody = "Cadastro de usuário no dashboard\n\n" .
                             "Nome: {$firstName} {$lastName}\n" .
                             "Email Corporativo: {$email}\n" .
                             "Departamento: {$department}\n" .
                             "Opções selecionadas: " . (empty($options) ? "Nenhuma opção selecionada." : implode(", ", $options));

            // Envia o e-mail
            $mail->send();

            // Define uma mensagem de sucesso na sessão (flash message)
            $_SESSION['success_message'] = "Sua solicitação gerou um chamado. Você receberá todas as informações por email.";

            // Redireciona para a página de login
            header("Location: index.php?page=login");
            exit();
        } catch (Exception $e) {
            $error = "Erro ao enviar o e-mail: " . $mail->ErrorInfo;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Cadastre-se - Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Cadastre-se</h1>
        
        <!-- Exibe mensagem de erro, se houver -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Formulário de cadastro -->
        <form method="POST" action="">
            <div class="mb-3">
                <label for="first_name" class="form-label">Primeiro Nome:</label>
                <input type="text" id="first_name" name="first_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="last_name" class="form-label">Último Nome:</label>
                <input type="text" id="last_name" name="last_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email Corporativo:</label>
                <input type="email" id="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="department" class="form-label">Departamento:</label>
                <input type="text" id="department" name="department" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Opções que pretende utilizar:</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="option1" name="options[]" value="Forecast Apontamento">
                    <label class="form-check-label" for="option1">Forecast Apontamento</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="option2" name="options[]" value="Forecast Relatórios PCP">
                    <label class="form-check-label" for="option2">Forecast Relatórios PCP</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="option2" name="options[]" value="Apontamento de Lojas de Clientes">
                    <label class="form-check-label" for="option2">Apontamento de Lojas de Clientes</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="option3" name="options[]" value="Envio de Sell-Out">
                    <label class="form-check-label" for="option3">Envio de Sell-Out</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Enviar Cadastro</button>
        </form>
    </div>
</body>
</html>
