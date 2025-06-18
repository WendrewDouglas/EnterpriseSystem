<?php
ob_start();

require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: text/html; charset=UTF-8');

// Permitir apenas ADMIN e GESTOR acessar
verificarPermissao('consulta_lancamentos');

// Forçar o locale para exibir meses em português
setlocale(LC_TIME, 'ptb.UTF-8', 'ptb', 'portuguese', 'portuguese_brazil');

// Cria conexão com o banco
$db = new Database();
$conn = $db->getConnection();

// Se for chamada via AJAX para o gráfico, retorna o JSON com os dados agregados
if (isset($_GET['fetch']) && $_GET['fetch'] == 'chart') {
    ob_clean();
    header('Content-Type: application/json; charset=UTF-8');
    
    // Subconsulta para obter a data real e agrupar a soma de quantidade
    $sql = "SELECT 
                FORMAT(dt, 'MMMM/yyyy', 'pt-BR') AS mes, 
                total
            FROM (
                SELECT 
                    TRY_PARSE('01/' + mes_referencia AS date USING 'pt-BR') AS dt, 
                    SUM(quantidade) AS total
                FROM forecast_system
                GROUP BY TRY_PARSE('01/' + mes_referencia AS date USING 'pt-BR')
            ) AS sub
            ORDER BY dt ASC";
            
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        die(json_encode(["error" => "Erro ao consultar o forecast: " . print_r(sqlsrv_errors(), true)]));
    }
    $labels = [];
    $data = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $labels[] = $row['mes'];
        $data[] = (int)$row['total'];
    }
    if (empty($labels)) {
        die(json_encode(["error" => "Nenhum dado retornado pela consulta."]));
    }
    echo json_encode(["labels" => $labels, "data" => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// Se não for chamada via AJAX para o gráfico, prossegue com o layout normal
$pageTitle = 'Dashboard - Forecast System';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

ob_end_flush();
?>

<div class="content">
    <h2 class="mb-4"><i class="bi bi-graph-up"></i> Análise forecast</h2>
    
    <!-- Gráfico de Barras: Quantidade Forecast por Mês -->
    <div class="mb-4">
        <canvas id="forecastChart" style="max-height: 400px;"></canvas>
    </div>
    
    <?php include __DIR__ . '/../templates/footer.php'; ?>
</div>

<!-- Incluir Chart.js e o plugin Chart.js DataLabels via CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Registrar o plugin de datalabels
    Chart.register(ChartDataLabels);

    function renderChart(chartData) {
        const ctx = document.getElementById('forecastChart').getContext('2d');
        if (window.forecastChartInstance) {
            window.forecastChartInstance.destroy();
        }
        window.forecastChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Quantidade Forecast',
                    data: chartData.data,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Forecast por Mês',
                        align: 'start',  // Alinhado à esquerda
                        font: {
                            size: 24  // 50% maior que o padrão (16px * 1.5 = 24px)
                        }
                    },
                    subtitle: {
                        display: true,
                        text: 'Quantidade em mil peças.',
                        align: 'start',  // Alinhado à esquerda
                        font: {
                            size: 20,  // 25% maior que o padrão (16px * 1.25 = 20px)
                            style: 'italic'
                        },
                        color: 'gray'
                    },
                    legend: {
                        display: true,
                        position: 'bottom',
                        align: 'center'
                    },
                    datalabels: {
                        anchor: 'end',
                        align: 'end',
                        color: 'black',
                        formatter: function(value) {
                            // Exibe o valor dividido por 1000 sem texto adicional
                            return (value / 1000).toFixed(1);
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { 
                            callback: function(value) {
                                return (value / 1000).toFixed(1);
                            },
                            precision: 0
                        }
                    }
                }
            }
        });
        console.log("Gráfico renderizado com sucesso.");
    }

    // Função para buscar os dados do gráfico via AJAX
    function atualizarGrafico() {
        // Utilize a URL completa se necessário
        fetch("http://intranet.color.com.br/forecast/views/forecast_geral.php?fetch=chart")
            .then(response => response.json())
            .then(chartData => {
                console.log("Dados recebidos:", chartData);
                if(chartData.error) {
                    console.error("Erro:", chartData.error);
                } else {
                    renderChart(chartData);
                }
            })
            .catch(error => {
                console.error("Erro na requisição do gráfico:", error);
            });
    }

    atualizarGrafico();
});
</script>
