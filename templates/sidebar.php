<div class="sidebar d-flex flex-column justify-content-between">
    <div class="text-center mb-3">
        <img src="/forecast/public/assets/img/logo_color2.png" alt="Logo da Empresa" class="img-fluid" style="max-width: 150px;">
    </div>

    <ul class="nav flex-column" id="sidebar-menu">
        <li class="nav-item">
            <a href="index.php?page=dashboard" class="nav-link">🏠 Dashboard</a>
        </li>

        <li class="nav-item">
            <a href="#forecastSubmenu" class="nav-link dropdown-toggle" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="forecastSubmenu">
                📊 Forecast
            </a>
            <div class="collapse ms-3" id="forecastSubmenu">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="index.php?page=apontar_forecast" class="nav-link">🛒 Apontar Forecast</a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=historico_forecast" class="nav-link">🕒 Apontamentos</a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=consulta_lancamentos" class="nav-link">📋 Relatório PCP</a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=forecast_geral" class="nav-link">📏 Geral</a>
                    </li>
                </ul>
            </div>
        </li>

        

        <li class="nav-item">
            <a href="#forecastSubmenu2" class="nav-link dropdown-toggle" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="forecastSubmenu2">
                🎯 OKR
            </a>
            <div class="collapse ms-3" id="forecastSubmenu2">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="index.php?page=OKR_consulta" class="nav-link">📋 Consulta</a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=OKR_novo_objetivo" class="nav-link">🚀 Novo Objetivo</a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=OKR_novo_kr" class="nav-link">📈 Novo KR</a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=OKR_novo_iniciativa" class="nav-link">🛠️ Nova Iniciativa</a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=OKR_aprovacao" class="nav-link">✅ Aprovações</a>
                    </li>
                </ul>
            </div>
        </li>

        <!--
        
        <li class="nav-item">
            <a href="#clientesSubmenu" class="nav-link dropdown-toggle" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="clientesSubmenu">
                🏢🏢 Clientes
            </a>
            <div class="collapse ms-3" id="clientesSubmenu">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="index.php?page=pdv_clientes" class="nav-link">🏪 PDVs</a>
                    </li>
                </ul>
            </div>
        </li>

        -->

        <li class="nav-item">
            <a class="nav-link" href="index.php?page=enviar_sellout">
                📤 Enviar Sell-Out
            </a>
        </li>

        <li class="nav-item">
            <a href="#configSubmenu" class="nav-link dropdown-toggle" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="configSubmenu">
                ⚙️ Configurações
            </a>
            <div class="collapse ms-3" id="configSubmenu">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="index.php?page=configuracoes" class="nav-link">🛠️ Geral</a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=users" class="nav-link">👥 Gerenciar Usuários</a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=depara_comercial" class="nav-link">📊 Gestores Comerciais</a>
                    </li>
                </ul>
            </div>
        </li>
    </ul>

    <div class="mt-auto text-center mb-3 small">
        <?php if(isset($_SESSION) && isset($_SESSION['user_name'])): ?>
            <p class="mb-1">Logado como:</p>
            <strong><?= htmlspecialchars($_SESSION['user_name']); ?></strong>
        <?php else: ?>
            <p class="mb-1">Sessão não iniciada</p>
        <?php endif; ?>
    </div>

    <a href="index.php?page=logout" class="btn btn-danger logout-btn btn-sm w-100">
        <i class="bi bi-box-arrow-right me-2"></i> Sair
    </a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Desative o comportamento padrão dos dropdowns no sidebar
    var dropdownToggles = document.querySelectorAll('.sidebar .dropdown-toggle');
    
    dropdownToggles.forEach(function(toggle) {
        // Armazena o atributo original (caso precise restaurar)
        var originalToggle = toggle.getAttribute('data-bs-toggle');
        toggle.setAttribute('data-bs-toggle-disabled', originalToggle);
        toggle.removeAttribute('data-bs-toggle');
        
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            var targetId = this.getAttribute('href');
            var targetElement = document.querySelector(targetId);
            var isExpanded = this.getAttribute('aria-expanded') === 'true';
            
            if (isExpanded) {
                targetElement.classList.remove('show');
                this.setAttribute('aria-expanded', 'false');
            } else {
                // Fecha outros dropdowns
                dropdownToggles.forEach(function(otherToggle) {
                    if (otherToggle !== toggle) {
                        var otherId = otherToggle.getAttribute('href');
                        var otherTarget = document.querySelector(otherId);
                        if (otherTarget) {
                            otherTarget.classList.remove('show');
                            otherToggle.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
                targetElement.classList.add('show');
                this.setAttribute('aria-expanded', 'true');
            }
        });
    });
});
</script>
