<?php
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv; 

// Carrega as variáveis de ambiente do arquivo .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitiza o e-mail informado
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    $db = new Database();
    $conn = $db->getConnection();

    // Verifica se o e-mail existe no banco de dados
    $sql = "SELECT id FROM users WHERE email = ?";
    $params = array($email);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false || sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC) === null) {
        $_SESSION['error_message'] = "E-mail não encontrado.";
        header("Location: index.php?page=password_reset_request");
        exit();
    }

    // Gere o token e a data de expiração (válido por 1 hora)
    $resetToken = bin2hex(random_bytes(32));
    $expirationTime = (new DateTime('+1 hour'))->format('d-m-Y H:i:s');

    // Exibe valores para depuração (remova em produção)
    var_dump($resetToken, $expirationTime, $email);

    // Salva o token e a expiração no banco de dados
    $sql = "UPDATE users SET reset_token = ?, reset_token_expiration = ? WHERE email = ?";
    $params = [$resetToken, $expirationTime, $email];
    $result = sqlsrv_query($conn, $sql, $params);

    if ($result === false) {
        die("Erro ao salvar o token no banco: " . print_r(sqlsrv_errors(), true));
    }

    echo "Token salvo com sucesso!";
}

$mail = new PHPMailer(true);
try {
    // Configurações do servidor SMTP
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'wendrew.gomes@colormaq.com.br';
    $mail->Password   = 'gdoc dfzb nnnt whzn'; // Substitua pela senha ou senha de aplicativo
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Define o remetente e o destinatário
    $mail->setFrom('wendrew.gomes@colormaq.com.br', 'T.I. Colormaq');
    $mail->addAddress($email);

    // Configura o charset e ativa o envio de HTML
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    $mail->Subject = 'Recuperação de Senha - T.I. Colormaq';

    // Adiciona a logomarca como imagem embutida (verifique o caminho)
    $logoPath = $_SERVER['DOCUMENT_ROOT'] . '/forecast/public/assets/img/logo_color2.png';
    $mail->addEmbeddedImage($logoPath, 'logo_cid');

    // Define o link para redefinição de senha
    $resetLink = "http://intranet.color.com.br/forecast/public/index.php?page=password_reset_form&token=$resetToken";

    // Monta o corpo HTML do e-mail
    $htmlBody  = '<html><head><meta charset="UTF-8"><title>Recuperação de Senha</title>';
    $htmlBody .= '<style>
        body { font-family: Arial, sans-serif; background-color: #f2f2f2; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .header { background-color: #007BFF; padding: 20px; text-align: center; }
        .header img { max-width: 150px; }
        .content { padding: 20px; }
        .content h2 { color:rgb(19, 104, 201); }
        .btn { display: inline-block; padding: 10px 20px; margin: 20px 0; background-color:rgb(206, 95, 21); color: #fff !important; text-decoration: none; border-radius: 5px; }
        .footer { background-color: #f2f2f2; text-align: center; padding: 10px; font-size: 12px; color: #777; }
    </style>';
    $htmlBody .= '</head><body>';
    $htmlBody .= '<div class="container">';
    $htmlBody .= '<div class="header">';
    $htmlBody .= '<img src="cid:logo_cid" alt="T.I. Colormaq Logo">';
    $htmlBody .= '</div>';
    $htmlBody .= '<div class="content">';
    $htmlBody .= '<h2>Recuperação de Senha</h2>';
    $htmlBody .= '<p>Olá,</p>';
    $htmlBody .= '<p>Recebemos uma solicitação para redefinir sua senha. Para prosseguir, clique no botão abaixo:</p>';
    $htmlBody .= '<p><a class="btn" href="' . $resetLink . '">Redefinir Senha</a></p>';
    $htmlBody .= '<p>Este link é válido por 1 hora.</p>';
    $htmlBody .= '</div>';
    $htmlBody .= '<div class="footer">';
    $htmlBody .= '<p>Se você não solicitou a redefinição de senha, ignore este e-mail.</p>';
    $htmlBody .= '<p>&copy; ' . date("Y") . ' T.I. Colormaq. Todos os direitos reservados.</p>';
    $htmlBody .= '</div>';
    $htmlBody .= '</div>';
    $htmlBody .= '</body></html>';

    $mail->Body = $htmlBody;
    $mail->AltBody = "Olá,\n\nClique no link a seguir para redefinir sua senha: $resetLink\n\nEste link é válido por 1 hora.\n\nSe você não solicitou a redefinição de senha, ignore este e-mail.";

    // Envia o e-mail
    $mail->send();
    $_SESSION['success_message'] = "E-mail de recuperação enviado!";
    header("Location: index.php?page=login");
    exit();
} catch (Exception $e) {
    $_SESSION['error_message'] = "Erro ao enviar e-mail: {$mail->ErrorInfo}";
    header("Location: index.php?page=password_reset_request");
    exit();
}
?>
