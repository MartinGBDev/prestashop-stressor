document.addEventListener('DOMContentLoaded', function() {
    
    // ====================
    // GRÁFICO DE AUDITORÍA (RADAR)
    // ====================
    const auditRadarCtx = document.getElementById('auditRadarChart');
    if (auditRadarCtx) {
        const auditRadarChart = new Chart(auditRadarCtx, {
            type: 'radar',
            data: window.stressorAuditData || {
                labels: ['Performance', 'Accessibility', 'SEO', 'Best Practices', 'PWA'],
                datasets: [{
                    label: 'Puntaje Actual',
                    data: [0.9, 0.8, 0.95, 0.85, 0.4],
                    backgroundColor: 'rgba(102, 126, 234, 0.2)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(102, 126, 234, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 1,
                        ticks: {
                            stepSize: 0.2,
                            callback: function(value) {
                                return (value * 100) + '%';
                            }
                        },
                        pointLabels: {
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.raw;
                                const percentage = (value * 100).toFixed(1);
                                const score = getScoreLabel(value);
                                return `${label}: ${percentage}% (${score})`;
                            }
                        }
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20
                        }
                    }
                }
            }
        });
    }
    
    // ====================
    // GRÁFICO DE MÉTRICAS DE CARGA (BARRAS)
    // ====================
    const loadMetricsCtx = document.getElementById('loadMetricsChart');
    if (loadMetricsCtx) {
        const loadMetricsChart = new Chart(loadMetricsCtx, {
            type: 'bar',
            data: window.stressorLoadData || {
                labels: ['Tiempo Respuesta', 'Peticiones Fallidas', 'Usuarios Virtuales'],
                datasets: [{
                    label: 'Valor Actual',
                    data: [245, 0.8, 4.8],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(75, 192, 192, 0.7)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(75, 192, 192, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value, index, values) {
                                if (index === 1) { // Para peticiones fallidas
                                    return value + '%';
                                }
                                if (index === 2) { // Para usuarios virtuales
                                    return value + ' VUs';
                                }
                                return value + 'ms';
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.raw;
                                let unit = '';
                                
                                switch (context.dataIndex) {
                                    case 0:
                                        unit = 'ms';
                                        break;
                                    case 1:
                                        unit = '%';
                                        break;
                                    case 2:
                                        unit = ' VUs';
                                        break;
                                }
                                
                                return `${label}: ${value}${unit}`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // ====================
    // GRÁFICO DE EVOLUCIÓN (LÍNEA)
    // ====================
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx && window.stressorTrendData) {
        const trendChart = new Chart(trendCtx, {
            type: 'line',
            data: window.stressorTrendData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Puntaje'
                        },
                        ticks: {
                            callback: function(value) {
                                return (value * 100) + '%';
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Fecha'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = context.raw;
                                return `${label}: ${(value * 100).toFixed(1)}%`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // ====================
    // FUNCIONES AUXILIARES
    // ====================
    
    function getScoreLabel(score) {
        if (score >= 0.9) return 'Excelente';
        if (score >= 0.8) return 'Bueno';
        if (score >= 0.7) return 'Regular';
        return 'Pobre';
    }
    
    function getScoreClass(score) {
        if (score >= 0.9) return 'score-excellent';
        if (score >= 0.8) return 'score-good';
        if (score >= 0.7) return 'score-fair';
        return 'score-poor';
    }
    
    // Actualizar progres bars
    document.querySelectorAll('.stressor-progress-fill').forEach(function(bar) {
        const score = parseFloat(bar.getAttribute('data-score'));
        const width = (score * 100) + '%';
        const color = getScoreColor(score);
        
        bar.style.width = width;
        bar.style.backgroundColor = color;
        
        // Actualizar etiqueta de progreso
        const progressLabel = bar.nextElementSibling;
        if (progressLabel && progressLabel.classList.contains('progress-label')) {
            progressLabel.textContent = (score * 100).toFixed(1) + '%';
        }
    });
    
    function getScoreColor(score) {
        if (score >= 0.9) return '#4CAF50';
        if (score >= 0.8) return '#8BC34A';
        if (score >= 0.7) return '#FFC107';
        return '#F44336';
    }
    
    // Botón de refrescar
    const refreshBtn = document.getElementById('stressorRefreshStats');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            this.innerHTML = '<i class="icon-spin icon-spinner"></i> Actualizando...';
            this.disabled = true;
            
            setTimeout(function() {
                location.reload();
            }, 1000);
        });
    }
    
    // Tabs de navegación
    const tabButtons = document.querySelectorAll('.stressor-tab-btn');
    const tabContents = document.querySelectorAll('.stressor-tab-content');
    
    tabButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Actualizar botones activos
            tabButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Mostrar contenido activo
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === tabId + '-content') {
                    content.classList.add('active');
                }
            });
            
            // Redibujar gráficos cuando se cambia de tab
            setTimeout(function() {
                if (auditRadarChart) auditRadarChart.resize();
                if (loadMetricsChart) loadMetricsChart.resize();
                if (trendChart) trendChart.resize();
            }, 100);
        });
    });
});