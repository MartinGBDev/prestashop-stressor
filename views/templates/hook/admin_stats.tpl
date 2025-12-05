<div class="stressor-stats-container">
    <div class="stressor-stats-header">
        <h3>
            <i class="icon-bar-chart"></i>
            Stressor - Dashboard de Rendimiento
            <button id="stressorRefreshStats" class="stressor-refresh-btn pull-right">
                <i class="icon-refresh"></i> Actualizar
            </button>
        </h3>
        <p class="m-0">Monitoreo de tests de rendimiento y auditoría</p>
    </div>
    
    {if $stressor_stats.tests_count == 0}
        <div class="stressor-empty-state">
            <i class="icon-bar-chart"></i>
            <h4>No hay tests ejecutados</h4>
            <p>Ejecuta tu primer test de rendimiento para ver estadísticas aquí.</p>
            <a href="{$stressor_configure_url|escape:'html':'UTF-8'}" class="btn btn-primary">
                <i class="icon-cogs"></i> Configurar Stressor
            </a>
        </div>
    {else}
        <!-- Tarjetas de resumen -->
        <div class="stressor-stats-cards">
            <div class="stressor-stat-card">
                <div class="stressor-card-header">
                    <span class="stressor-card-title">Tests Ejecutados</span>
                    <span class="stressor-card-value">{$stressor_stats.tests_count|intval}</span>
                </div>
                <div class="stressor-card-trend">
                    <i class="icon-arrow-up trend-up"></i>
                    <span>Últimos 30 días: {$stressor_stats.tests_30_days|intval}</span>
                </div>
            </div>
            
            <div class="stressor-stat-card">
                <div class="stressor-card-header">
                    <span class="stressor-card-title">Performance Promedio</span>
                    <span class="stressor-card-value">
                        {if $stressor_stats.avg_performance}
                            {($stressor_stats.avg_performance * 100)|string_format:"%.1f"}%
                        {else}
                            N/A
                        {/if}
                    </span>
                </div>
                <div class="stressor-card-trend">
                    {if $stressor_stats.performance_trend == 'up'}
                        <i class="icon-arrow-up trend-up"></i>
                        <span>Mejorando</span>
                    {elseif $stressor_stats.performance_trend == 'down'}
                        <i class="icon-arrow-down trend-down"></i>
                        <span>Bajando</span>
                    {else}
                        <i class="icon-minus trend-stable"></i>
                        <span>Estable</span>
                    {/if}
                </div>
            </div>
            
            <div class="stressor-stat-card">
                <div class="stressor-card-header">
                    <span class="stressor-card-title">Tiempo Respuesta</span>
                    <span class="stressor-card-value">
                        {if $stressor_stats.avg_response_time}
                            {$stressor_stats.avg_response_time|string_format:"%.0f"}ms
                        {else}
                            N/A
                        {/if}
                    </span>
                </div>
                <div class="stressor-card-trend">
                    <i class="icon-clock-o"></i>
                    <span>Último test</span>
                </div>
            </div>
            
            <div class="stressor-stat-card">
                <div class="stressor-card-header">
                    <span class="stressor-card-title">Estado del Sistema</span>
                    <span class="stressor-card-value">
                        {if $stressor_stats.health_score >= 0.9}
                            <span class="stressor-score-badge score-excellent">Óptimo</span>
                        {elseif $stressor_stats.health_score >= 0.7}
                            <span class="stressor-score-badge score-good">Bueno</span>
                        {elseif $stressor_stats.health_score >= 0.5}
                            <span class="stressor-score-badge score-fair">Regular</span>
                        {else}
                            <span class="stressor-score-badge score-poor">Crítico</span>
                        {/if}
                    </span>
                </div>
                <div class="stressor-progress-bar">
                    <div class="stressor-progress-fill" data-score="{$stressor_stats.health_score|floatval}"></div>
                </div>
            </div>
        </div>
        
        <!-- Navegación por tabs -->
        <div class="panel">
            <div class="panel-heading">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#" class="stressor-tab-btn active" data-tab="audit">
                            <i class="icon-search"></i> Auditoría
                        </a>
                    </li>
                    <li>
                        <a href="#" class="stressor-tab-btn" data-tab="load">
                            <i class="icon-bar-chart"></i> Carga
                        </a>
                    </li>
                    <li>
                        <a href="#" class="stressor-tab-btn" data-tab="trend">
                            <i class="icon-line-chart"></i> Evolución
                        </a>
                    </li>
                </ul>
            </div>
            <div class="panel-body">
                
                <!-- Tab de Auditoría -->
                <div id="audit-content" class="stressor-tab-content active">
                    <div class="stressor-charts-grid">
                        <div class="stressor-chart-container">
                            <div class="stressor-chart-header">
                                <h4 class="stressor-chart-title">Puntajes de Auditoría</h4>
                                <p class="stressor-chart-subtitle">Comparativa de métricas Lighthouse</p>
                            </div>
                            <div class="stressor-chart-canvas">
                                <canvas id="auditRadarChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="stressor-chart-container">
                            <div class="stressor-chart-header">
                                <h4 class="stressor-chart-title">Métricas Detalladas</h4>
                                <p class="stressor-chart-subtitle">Última auditoría: {$stressor_stats.last_audit_date|escape:'html':'UTF-8'}</p>
                            </div>
                            <table class="stressor-metrics-table">
                                <thead>
                                    <tr>
                                        <th>Métrica</th>
                                        <th>Puntaje</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {foreach from=$stressor_stats.audit_scores key=metric item=score}
                                    <tr>
                                        <td>{$metric|escape:'html':'UTF-8'}</td>
                                        <td>{($score * 100)|string_format:"%.1f"}%</td>
                                        <td>
                                            {if $score >= 0.9}
                                                <span class="stressor-score-badge score-excellent">Excelente</span>
                                            {elseif $score >= 0.8}
                                                <span class="stressor-score-badge score-good">Bueno</span>
                                            {elseif $score >= 0.7}
                                                <span class="stressor-score-badge score-fair">Regular</span>
                                            {else}
                                                <span class="stressor-score-badge score-poor">Mejorar</span>
                                            {/if}
                                        </td>
                                    </tr>
                                    {/foreach}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Tab de Carga -->
                <div id="load-content" class="stressor-tab-content">
                    <div class="stressor-charts-grid">
                        <div class="stressor-chart-container">
                            <div class="stressor-chart-header">
                                <h4 class="stressor-chart-title">Métricas de Carga</h4>
                                <p class="stressor-chart-subtitle">Resultados del último test k6</p>
                            </div>
                            <div class="stressor-chart-canvas">
                                <canvas id="loadMetricsChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="stressor-chart-container">
                            <div class="stressor-chart-header">
                                <h4 class="stressor-chart-title">Resumen de Métricas</h4>
                                <p class="stressor-chart-subtitle">Estadísticas del test de carga</p>
                            </div>
                            <table class="stressor-metrics-table">
                                <thead>
                                    <tr>
                                        <th>Métrica</th>
                                        <th>Promedio</th>
                                        <th>Mínimo</th>
                                        <th>Máximo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {foreach from=$stressor_stats.load_metrics key=metric item=stats}
                                    <tr>
                                        <td>{$metric|escape:'html':'UTF-8'}</td>
                                        <td>
                                            {if $metric == 'http_req_failed'}
                                                {$stats.avg|string_format:"%.1f"}%
                                            {elseif $metric == 'http_req_duration'}
                                                {$stats.avg|string_format:"%.0f"}ms
                                            {else}
                                                {$stats.avg|string_format:"%.1f"}
                                            {/if}
                                        </td>
                                        <td>
                                            {if $metric == 'http_req_failed'}
                                                {$stats.min|string_format:"%.1f"}%
                                            {elseif $metric == 'http_req_duration'}
                                                {$stats.min|string_format:"%.0f"}ms
                                            {else}
                                                {$stats.min|string_format:"%.1f"}
                                            {/if}
                                        </td>
                                        <td>
                                            {if $metric == 'http_req_failed'}
                                                {$stats.max|string_format:"%.1f"}%
                                            {elseif $metric == 'http_req_duration'}
                                                {$stats.max|string_format:"%.0f"}ms
                                            {else}
                                                {$stats.max|string_format:"%.1f"}
                                            {/if}
                                        </td>
                                    </tr>
                                    {/foreach}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Tab de Evolución -->
                <div id="trend-content" class="stressor-tab-content">
                    <div class="stressor-chart-container">
                        <div class="stressor-chart-header">
                            <h4 class="stressor-chart-title">Evolución del Performance</h4>
                            <p class="stressor-chart-subtitle">Histórico de los últimos tests</p>
                        </div>
                        <div class="stressor-chart-canvas">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="stressor-chart-container">
                        <div class="stressor-chart-header">
                            <h4 class="stressor-chart-title">Tests Recientes</h4>
                            <p class="stressor-chart-subtitle">Últimas ejecuciones</p>
                        </div>
                        <ul class="stressor-test-list">
                            {foreach from=$stressor_stats.recent_tests item=test}
                            <li class="stressor-test-item">
                                <span class="stressor-test-name">{$test.name|escape:'html':'UTF-8'}</span>
                                <span class="stressor-test-score">
                                    {if $test.type == 'audit'}
                                        <span class="label {if $test.score >= 0.9}label-success{elseif $test.score >= 0.8}label-warning{else}label-danger{/if}">
                                            {($test.score * 100)|string_format:"%.0f"}%
                                        </span>
                                    {else}
                                        <span class="label label-info">
                                            {$test.response_time|string_format:"%.0f"}ms
                                        </span>
                                    {/if}
                                </span>
                            </li>
                            {/foreach}
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Script para pasar datos a JavaScript -->
        <script>
        window.stressorAuditData = {$stressor_stats.audit_chart_data|json_encode};
        window.stressorLoadData = {$stressor_stats.load_chart_data|json_encode};
        window.stressorTrendData = {$stressor_stats.trend_chart_data|json_encode};
        </script>
    {/if}
</div>