<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Forecast System'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">

    <style>
        /* Estilos existentes para sidebar, conteúdo, etc. */
        .sidebar {
            height: 100vh;
            background-color: #133fa2;
            color: white;
            position: fixed;
            width: 200px;
            padding-top: 15px;
            display: flex;
            flex-direction: column;
        }
        .sidebar a {
            padding: 8px 12px;
            text-decoration: none;
            font-size: 0.9rem;
            color: white;
            display: block;
        }
        .sidebar a:hover {
            background-color: #ee5b2f;
            color: #ffffff;
        }
        .sidebar img {
            max-width: 100px;
        }
        .content {
            margin-left: 210px;
            padding: 15px;
            font-size: 0.8rem;
        }

        /* Estilos do overlay de carregamento */
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 10000;
            display: none; /* inicialmente oculto */
            justify-content: center;
            align-items: center;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        #loadingContainer {
            width: 300px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        #loadingContainer img {
            max-width: 100%;
            height: auto;
        }
        .loading-text {
            font-size: 24px;
            margin-top: 20px;
        }
        .loading-phrase {
            font-size: 18px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <!-- Overlay de carregamento – permanece no DOM -->
    <div id="loadingOverlay">
        <div id="loadingContainer">
            <img src="/forecast/public/assets/img/logo_color1.png" alt="Logo da Empresa">
            <div class="loading-text">CARREGANDO</div>
            <div class="loading-phrase"></div>
        </div>
    </div>

    <script>
        // Atualiza as frases de forma aleatória a cada 2 segundos
        document.addEventListener('DOMContentLoaded', function(){
            const frases = [
                "TI recarregando, segura essa! ⚡️",
                "Café na veia, página em produção! ☕️🚀",
                "Carregando mais devagar que relatório! 🐢📊",
                "Já pediu café? Tá demorando! ☕️⏳",
                "Humor corporativo em atualização! 😂💼",
                "Paciência é KPI, obrigado! ⏳📈",
                "Relatório? Só piada de TI! 📑🤣",
                "Torça pra internet não cair 🤞📶",
                "Processando: pausa pro cafezinho! ⏸☕️",
                "Alô, chefe! Página quase pronta! 👨‍💼🚀",
                "Aguarde: humor e dados chegando! 📊😄",
                "Reiniciando: TI não dorme, só carrega! 🔄💻",
                "Se a impressora esperasse, já era! 🖨️😂",
                "Entre reuniões, página se ajeita! 🕒💼",
                "Conteúdo a caminho, com café extra! 🚚☕️"
            ];
            const fraseElemento = document.querySelector(".loading-phrase");
            // Define uma frase inicial aleatória
            fraseElemento.textContent = frases[Math.floor(Math.random() * frases.length)];
            // Atualiza a cada 2 segundos com uma frase aleatória
            setInterval(function(){
                const randomIndex = Math.floor(Math.random() * frases.length);
                fraseElemento.textContent = frases[randomIndex];
            }, 2000);
        });

        // Garante que, no primeiro carregamento, o overlay fique oculto
        window.addEventListener('load', function(){
            const overlay = document.getElementById('loadingOverlay');
            overlay.style.display = 'none';
        });

        // Global listener para capturar cliques em qualquer link (exceto âncoras ou elementos de collapse)
        document.addEventListener('click', function(e){
            let target = e.target;
            while (target && target !== document) {
                if (target.tagName === 'A') {
                    const href = target.getAttribute('href');
                    // Se o link tiver o atributo data-no-loading ou se o href conter "export_forecast.php", não exibe o overlay
                    if (target.hasAttribute('data-no-loading') || (href && href.indexOf("export_forecast.php") !== -1)) {
                        break;
                    }
                    if (href && href.trim() !== '' && !href.trim().startsWith('#') && !target.hasAttribute('data-bs-toggle')) {
                        document.getElementById('loadingOverlay').style.display = 'flex';
                        break;
                    }
                }
                target = target.parentNode;
            }
        });

        function iniciarDownloadEReload(url) {
            document.getElementById('loadingOverlay').style.display = 'flex';

            // Inicia o download via window.open em vez de iframe
            window.open(url, '_blank');
            console.log("Download iniciado via window.open para:", url);

            // Opcional: se necessário atualizar a página, faça após um delay maior
            setTimeout(function(){
                document.getElementById('loadingOverlay').style.display = 'none';
                // location.reload(); // Comente ou ajuste o tempo se o reload for imprescindível
            }, 1000);
        }


        // Listener para navegações que não são acionadas por cliques (ex: redirecionamentos, formulários, etc.)
        window.addEventListener('beforeunload', function(){
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'flex';
            }
        });
    </script>
    <!-- O restante do conteúdo da página segue abaixo -->
