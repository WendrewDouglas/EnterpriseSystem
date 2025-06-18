<!-- loading_overlay.php -->
<div id="loadingOverlay">
    <img src="/forecast/public/assets/img/logo_color1.png" alt="Logo da Empresa">
    <div class="loading-text">CARREGANDO</div>
    <div class="loading-phrase"></div>
</div>

<style>
    /* Estilo para o overlay de carregamento */
    #loadingOverlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.9);
        z-index: 10000;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
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

<script>
    document.addEventListener('DOMContentLoaded', function(){
        // Array com as 15 frases descontraídas
        const frases = [
            "Aguarde um instante, preparando as coisas...",
            "Carregando as novidades para você!",
            "Quase lá, só mais um pouquinho...",
            "Estamos organizando tudo com carinho.",
            "Preparando a experiência ideal.",
            "Só um momento, a magia está acontecendo...",
            "Sua paciência é nossa motivação!",
            "Transformando dados em experiências incríveis.",
            "Aguarde enquanto tudo se conecta.",
            "Ponha seu café, já vai começar!",
            "Estamos quase prontos para surpreender você.",
            "Ajustando os detalhes finais...",
            "Finalizando os preparativos.",
            "Seu conteúdo está a caminho!",
            "Obrigado pela paciência, estamos quase lá!"
        ];
        
        const fraseElemento = document.querySelector(".loading-phrase");
        let index = 0;
        
        // Atualiza a frase a cada 2 segundos
        setInterval(function(){
            fraseElemento.textContent = frases[index];
            index = (index + 1) % frases.length;
        }, 2000);
    });

    // Quando a página estiver totalmente carregada, remove o overlay
    window.addEventListener('load', function(){
        const overlay = document.getElementById('loadingOverlay');
        if(overlay) {
            overlay.style.display = 'none';
        }
    });
</script>
