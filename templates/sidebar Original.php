<div class="sidebar d-flex flex-column justify-content-between">
    <!-- Logo -->
    <div class="text-center mb-3">
        <img src="./public/assets/img/logo_color2.png" alt="Logo da Empresa" class="img-fluid" style="max-width: 150px;">
    </div>

    <!-- Menu Principal -->
    <ul class="nav flex-column" id="sidebar-menu">
        <!-- Dashboard -->
        <li class="nav-item">
            <a href="index.php?page=dashboard" class="nav-link">
                <i class="bi bi-house-door me-2"></i> Dashboard
            </a>
        </li>

        <!-- Menu Forecast com Dropdown -->
        <li class="nav-item">
            <a href="#forecastSubmenu" class="nav-link dropdown-toggle" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="forecastSubmenu">
                <i class="bi bi-graph-up me-2"></i> Forecast
            </a>
            <div class="collapse ms-3" id="forecastSubmenu">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="index.php?page=apontar_forecast" class="nav-link">
                            <i class="bi bi-cart me-2"></i> Apontar Forecast
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=historico_forecast" class="nav-link">
                            <i class="bi bi-clock-history me-2"></i> Apontamentos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=consulta_lancamentos" class="nav-link">
                            <i class="bi bi-clipboard-data me-2"></i> Relatório PCP
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=forecast_geral" class="nav-link">
                            <i class="bi bi-rulers me-2"></i> Geral
                        </a>
                    </li>
                </ul>
            </div>
        </li>

        <!-- Menu OKR com Dropdown -->
         
        <li class="nav-item">
            <a href="#okrSubmenu" class="nav-link dropdown-toggle" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="okrSubmenu">
                <i class="bi bi-bullseye me-2"></i> OKR
            </a>
            <div class="collapse ms-3" id="okrSubmenu">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="index.php?page=novo_objetivo" class="nav-link">
                            <i class="bi bi-plus-circle me-2"></i> Criar Objetivo
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=aprovacao_OKR" class="nav-link">
                            <i class="bi bi-check-circle me-2"></i> Aprovações
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=aprovacao_OKR" class="nav-link">
                            <i class="bi bi-check-circle me-2"></i> Consulta
                        </a>
                    </li>
                </ul>
            </div>
        </li>

        

        <!-- Enviar Sell-Out -->
        <li class="nav-item">
            <a class="nav-link" href="index.php?page=enviar_sellout">
                <i class="bi bi-upload me-2"></i> Enviar Sell-Out
            </a>
        </li>

        <!-- Universidade Color -->
        <li class="nav-item">
            <a href="#universidadeColorSubmenu" class="nav-link dropdown-toggle" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="universidadeColorSubmenu">
                <i class="bi bi-mortarboard me-2"></i> Universidade Color
            </a>
            <div class="collapse ms-3" id="universidadeColorSubmenu">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="index.php?page=areadoaluno" class="nav-link">
                            <i class="bi bi-person me-2"></i> Área do Aluno
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=cursos" class="nav-link">
                            <i class="bi bi-book me-2"></i> Cursos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=upload" class="nav-link">
                            <i class="bi bi-plus-circle me-2"></i> Novo Curso
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=certificados" class="nav-link">
                            <i class="bi bi-award me-2"></i> Certificados
                        </a>
                    </li>
                </ul>
            </div>
        </li>

        <!-- Menu Configurações com Dropdown -->
        <li class="nav-item">
            <a href="#configSubmenu" class="nav-link dropdown-toggle" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="configSubmenu">
                <i class="bi bi-gear me-2"></i> Configurações
            </a>
            <div class="collapse ms-3" id="configSubmenu">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a href="index.php?page=configuracoes" class="nav-link">
                            <i class="bi bi-tools me-2"></i> Geral
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=users" class="nav-link">
                            <i class="bi bi-people me-2"></i> Gerenciar Usuários
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=depara_comercial" class="nav-link">
                            <i class="bi bi-bar-chart me-2"></i> Gestores Comerciais
                        </a>
                    </li>
                </ul>
            </div>
        </li>
    </ul>

    <!-- Informações do Usuário e Botão Sair -->
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

<!-- Script para garantir que os dropdowns funcionem corretamente -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Adiciona evento de clique em todos os dropdowns da barra lateral
    document.querySelectorAll('.sidebar .dropdown-toggle').forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            const target = this.getAttribute('href');
            const targetElement = document.querySelector(target);
            
            // Fecha todos os outros menus abertos
            document.querySelectorAll('.sidebar .collapse.show').forEach(function(openMenu) {
                // Não fechar o menu atual
                if (openMenu.id !== targetElement.id) {
                    openMenu.classList.remove('show');
                }
            });
            
            // Alterna a visibilidade do menu atual
            targetElement.classList.toggle('show');
            
            // Atualiza o atributo aria-expanded
            this.setAttribute('aria-expanded', targetElement.classList.contains('show'));
        });
    });
});
</script>
