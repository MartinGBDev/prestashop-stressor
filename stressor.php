<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

if (!class_exists('Module', false)) {
    require_once _PS_ROOT_DIR_ . '/classes/Module.php';
}

class Stressor extends Module
{
    const DB_TABLE = 'stressor_tests';
    
    public function __construct()
    {
        $this->name = 'stressor';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Martin Alejandro Garcia Babastro';
        $this->need_instance = 1;
        $this->bootstrap = true;
        $this->module_key = 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6';

        $this->displayName = 'Stressor';
        $this->description = 'Módulo de stress testing y auditoría de rendimiento';
        $this->ps_versions_compliancy = array('min' => '8.0.4', 'max' => '9.0.0');
        
        parent::__construct();
    }

    public function install()
    {
        $success = parent::install() && 
               $this->registerHook('displayAdminStatsModules') &&
               $this->registerHook('actionAdminControllerSetMedia') &&
               $this->createDatabaseTable();
        
        // Agregar un test de ejemplo con resultados
        if ($success) {
            $this->addSampleTestWithResults();
        }
        
        return $success;
    }
    
    public function uninstall()
    {
        return parent::uninstall() &&
               $this->dropDatabaseTable();
    }
    
    /**
     * Crear tabla de base de datos con campos JSON
     */
    private function createDatabaseTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . self::DB_TABLE . "` (
            `id_test` INT(11) NOT NULL AUTO_INCREMENT,
            `request` LONGTEXT NOT NULL COMMENT 'Configuración completa en formato JSON stringified',
            `results` LONGTEXT NULL COMMENT 'Resultados del test en formato JSON stringified',
            `status` VARCHAR(50) DEFAULT 'draft',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            `executed_at` DATETIME NULL,
            PRIMARY KEY (`id_test`),
            INDEX `idx_status` (`status`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        return Db::getInstance()->execute($sql);
    }
    
    private function dropDatabaseTable()
    {
        $sql = "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . self::DB_TABLE . "`";
        return Db::getInstance()->execute($sql);
    }

    public function hookDisplayAdminStatsModules()
    {
        if (!$this->active) {
            return '';
        }
        
        // Obtener los últimos tests ejecutados
        $sql = "SELECT * FROM `" . _DB_PREFIX_ . self::DB_TABLE . "` 
                WHERE status = 'completed' 
                AND results IS NOT NULL 
                AND results != ''
                ORDER BY executed_at DESC 
                LIMIT 5";
        
        $tests = Db::getInstance()->executeS($sql);
        
        $this->context->controller->addCSS($this->_path . 'views/css/admin/stats.css');
        $this->context->controller->addJS($this->_path . 'views/js/admin/stats.js');
        
        // Cargar Chart.js
        $chartJsPath = _PS_JS_DIR_ . 'chartjs/Chart.min.js';
        if (file_exists(_PS_ROOT_DIR_ . '/' . $chartJsPath)) {
            $this->context->controller->addJS($chartJsPath);
        } else {
            // Usar CDN como fallback
            $this->context->controller->addJS('https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js');
        }
        
        // Preparar datos para los gráficos
        $statsData = $this->prepareStatsData($tests);
        
        // Pasar datos al template
        $this->context->smarty->assign([
            'stressor_stats' => $statsData,
            'stressor_configure_url' => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name
        ]);
        
        // Renderizar el template
        return $this->display(__FILE__, 'views/templates/hook/admin_stats.tpl');
    }
    
    public function hookActionAdminControllerSetMedia()
{
    if (Tools::getValue('configure') == $this->name || Tools::getValue('controller') == 'AdminStats') {
        // Cargar CSS
        $this->context->controller->addCSS($this->_path . 'views/css/admin/config.css');
        $this->context->controller->addCSS($this->_path . 'views/css/admin/stats.css');
        
        // Cargar JS
        $this->context->controller->addJS($this->_path . 'views/js/admin/config.js');
        $this->context->controller->addJS($this->_path . 'views/js/admin/stats.js');
        
        // Cargar Chart.js para estadísticas
        $chartJsPath = _PS_JS_DIR_ . 'chartjs/Chart.min.js';
        if (file_exists(_PS_ROOT_DIR_ . '/' . $chartJsPath)) {
            $this->context->controller->addJS($chartJsPath);
        } else {
            $this->context->controller->addJS('https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js');
        }
        
        // Pasar traducciones a JavaScript
        Media::addJsDef([
            'stressorTranslate' => json_decode($this->getJsTranslations(), true),
            'moduleName' => $this->name // Agregar nombre del módulo para referencia
        ]);
    }
}
    
    public function getContent()
    {
        $output = '';
        
        // Manejar solicitudes AJAX
        if (Tools::isSubmit('ajax') && Tools::isSubmit('action')) {
            $this->processAjaxRequest();
            return '';
        }
        
        // Manejar exportación de resultados
        if (Tools::isSubmit('export_test')) {
            $this->exportTestResults((int)Tools::getValue('export_test'));
            return '';
        }
        
        // Procesar el formulario cuando se envía
        if (Tools::isSubmit('submitStressorConfig')) {
            if ($this->processConfiguration()) {
                $output .= $this->displayConfirmation($this->l('Configuración guardada correctamente'));
            } else {
                $output .= $this->displayError($this->l('Error al guardar la configuración'));
            }
        }
        
        // Procesar ejecución de test
        if (Tools::isSubmit('executeTest')) {
            $idTest = (int)Tools::getValue('id_test');
            if ($this->executeTest($idTest)) {
                $output .= $this->displayConfirmation($this->l('Test ejecutado correctamente'));
            } else {
                $output .= $this->displayError($this->l('Error al ejecutar el test'));
            }
        }
        
        // Mostrar formulario
        $output .= $this->renderConfigurationForm();
        
        // Mostrar lista de tests guardados
        $output .= $this->renderTestsList();
        
        return $output;
    }
    
    private function processAjaxRequest()
    {
        $action = Tools::getValue('action');
        
        switch ($action) {
            case 'getTestResults':
                $this->ajaxProcessGetTestResults();
                break;
            default:
                die(json_encode(['success' => false, 'message' => 'Acción no válida']));
        }
    }
    
    /**
     * Manejar solicitudes AJAX para obtener resultados
     */
    private function ajaxProcessGetTestResults()
    {
        $idTest = (int)Tools::getValue('id_test');
        
        $sql = "SELECT results FROM `" . _DB_PREFIX_ . self::DB_TABLE . "` 
                WHERE id_test = " . $idTest;
        
        $test = Db::getInstance()->getRow($sql);
        
        if ($test && !empty($test['results'])) {
            $results = json_decode($test['results'], true);
            
            die(json_encode([
                'success' => true,
                'results' => $results,
                'test_id' => $idTest
            ]));
        } else {
            die(json_encode([
                'success' => false,
                'message' => $this->l('No hay resultados disponibles para este test')
            ]));
        }
    }
    
    private function processConfiguration()
    {
        try {
            // Construir el objeto de configuración completo
            $config = $this->buildConfigFromPost();
            
            // Convertir a JSON string
            $jsonConfig = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error al generar JSON: ' . json_last_error_msg());
            }
            
            // Guardar en base de datos
            $data = [
                'request' => pSQL($jsonConfig),
                'results' => null,
                'status' => 'draft',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            return Db::getInstance()->insert(self::DB_TABLE, $data);
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Error procesando configuración Stressor: ' . $e->getMessage(), 3);
            return false;
        }
    }
    
    private function buildConfigFromPost()
    {
        // Construir estructura completa del JSON
        $config = [
            'name' => Tools::getValue('config_name', 'Test Sin Nombre'),
            'owner' => Tools::getValue('config_owner', 'admin'),
            'timestamp' => time(),
            'options' => [
                'runInParallel' => (bool)Tools::getValue('options_runInParallel'),
                'timeout' => (int)Tools::getValue('options_timeout'),
                'saveRaw' => (bool)Tools::getValue('options_saveRaw')
            ],
            'jobs' => []
        ];
        
        // Job de carga (Load Test)
        if (Tools::getValue('load_enabled')) {
            $loadJob = [
                'type' => 'load',
                'id' => 'load-test-' . Tools::getValue('config_name', 'default') . '-' . time(),
                'options' => [
                    'vus' => (int)Tools::getValue('load_vus', 10),
                    'iterations' => (int)Tools::getValue('load_iterations', 100),
                    'duration' => Tools::getValue('load_duration', '30s')
                ]
            ];
            
            // Agregar escenarios si existen
            $scenarios = [];
            $scenarioNames = Tools::getValue('load_scenario_name', []);
            $scenarioExecutors = Tools::getValue('load_scenario_executor', []);
            
            foreach ($scenarioNames as $index => $name) {
                if (!empty($name)) {
                    $scenarios[] = [
                        'name' => $name,
                        'executor' => $scenarioExecutors[$index] ?? 'shared-iterations'
                    ];
                }
            }
            
            if (!empty($scenarios)) {
                $loadJob['scenario'] = $scenarios;
            }
            
            $config['jobs'][] = $loadJob;
        }
        
        // Job de auditoría (Lighthouse)
        if (Tools::getValue('audit_enabled')) {
            $auditJob = [
                'type' => 'audit',
                'id' => 'audit-test-' . Tools::getValue('config_name', 'default') . '-' . time(),
                'url' => Tools::getValue('audit_url', Tools::getHttpHost(true) . __PS_BASE_URI__),
                'options' => [
                    'output' => Tools::getValue('audit_output', ['json']),
                    'emulatedFormFactor' => Tools::getValue('audit_formFactor', 'mobile'),
                    'onlyCategories' => Tools::getValue('audit_categories', ['performance'])
                ],
                'filters' => [
                    'minScore' => (float)Tools::getValue('audit_minScore', 80)
                ],
                'run' => [
                    'maxWaitForLoad' => (int)Tools::getValue('audit_maxWait', 30000),
                    'numberOfRuns' => (int)Tools::getValue('audit_numberOfRuns', 3)
                ]
            ];
            
            // Throttling
            if (Tools::getValue('audit_throttling_enabled')) {
                $auditJob['options']['throttling'] = [
                    'rttMs' => (int)Tools::getValue('audit_throttling_rtt', 150),
                    'throughputKbps' => (int)Tools::getValue('audit_throttling_throughput', 1638)
                ];
            }
            
            // Headers extra
            $extraHeaders = [];
            $headerKeys = Tools::getValue('audit_header_key', []);
            $headerValues = Tools::getValue('audit_header_value', []);
            
            foreach ($headerKeys as $index => $key) {
                if (!empty($key) && isset($headerValues[$index])) {
                    $extraHeaders[$key] = $headerValues[$index];
                }
            }
            
            if (!empty($extraHeaders)) {
                $auditJob['options']['extraHeaders'] = $extraHeaders;
            }
            
            $config['jobs'][] = $auditJob;
        }
        
        return $config;
    }
    
    private function renderConfigurationForm()
    {
        // Inicializar HelperForm
        $helper = new HelperForm();
        
        // Configuración básica del helper
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submitStressorConfig';
        
        // Idioma por defecto
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        
        // Cargar valores por defecto
        $helper->fields_value = [
            'config_name' => 'Test de Rendimiento ' . date('Y-m-d'),
            'config_owner' => $this->context->employee->firstname . ' ' . $this->context->employee->lastname,
            'options_runInParallel' => 1,
            'options_timeout' => 300000,
            'options_saveRaw' => 0,
            'load_enabled' => 1,
            'load_vus' => 10,
            'load_iterations' => 100,
            'load_duration' => '30s',
            'audit_enabled' => 1,
            'audit_url' => Tools::getHttpHost(true) . __PS_BASE_URI__,
            'audit_formFactor' => 'mobile',
            'audit_minScore' => 80,
            'audit_maxWait' => 30000,
            'audit_numberOfRuns' => 3,
        ];
        
        // Generar los formularios
        return $helper->generateForm($this->getFormFields());
    }
    
    private function getFormFields()
    {
        return [
            // FORMULARIO 1: CONFIGURACIÓN BÁSICA
            [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Configuración Básica'),
                        'icon' => 'icon-cogs'
                    ],
                    'input' => [
                        [
                            'type' => 'text',
                            'label' => $this->l('Nombre del Test'),
                            'name' => 'config_name',
                            'required' => true,
                            'desc' => $this->l('Nombre descriptivo para identificar este test'),
                            'size' => 50
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Propietario'),
                            'name' => 'config_owner',
                            'desc' => $this->l('Persona o equipo responsable'),
                            'size' => 30
                        ]
                    ]
                ]
            ],
            
            // FORMULARIO 2: OPCIONES DEL PIPELINE
            [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Opciones de Ejecución'),
                        'icon' => 'icon-sliders'
                    ],
                    'input' => [
                        [
                            'type' => 'switch',
                            'label' => $this->l('Ejecutar en Paralelo'),
                            'name' => 'options_runInParallel',
                            'is_bool' => true,
                            'values' => [
                                ['id' => 'runInParallel_on', 'value' => 1, 'label' => $this->l('Sí')],
                                ['id' => 'runInParallel_off', 'value' => 0, 'label' => $this->l('No')]
                            ]
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Timeout (ms)'),
                            'name' => 'options_timeout',
                            'class' => 'fixed-width-md',
                            'desc' => $this->l('Tiempo máximo de ejecución')
                        ],
                        [
                            'type' => 'switch',
                            'label' => $this->l('Guardar Datos Crudos'),
                            'name' => 'options_saveRaw',
                            'is_bool' => true,
                            'values' => [
                                ['id' => 'saveRaw_on', 'value' => 1, 'label' => $this->l('Sí')],
                                ['id' => 'saveRaw_off', 'value' => 0, 'label' => $this->l('No')]
                            ]
                        ]
                    ]
                ]
            ],
            
            // FORMULARIO 3: LOAD TEST
            [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Load Test (k6)'),
                        'icon' => 'icon-bar-chart'
                    ],
                    'input' => [
                        [
                            'type' => 'switch',
                            'label' => $this->l('Habilitar Load Test'),
                            'name' => 'load_enabled',
                            'is_bool' => true,
                            'values' => [
                                ['id' => 'load_enabled_on', 'value' => 1, 'label' => $this->l('Sí')],
                                ['id' => 'load_enabled_off', 'value' => 0, 'label' => $this->l('No')]
                            ]
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Usuarios Virtuales (VUs)'),
                            'name' => 'load_vus',
                            'class' => 'fixed-width-sm',
                            'disabled' => true,
                            'form_group_class' => 'load-options'
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Iteraciones'),
                            'name' => 'load_iterations',
                            'class' => 'fixed-width-sm',
                            'disabled' => true,
                            'form_group_class' => 'load-options'
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Duración'),
                            'name' => 'load_duration',
                            'class' => 'fixed-width-md',
                            'disabled' => true,
                            'form_group_class' => 'load-options'
                        ],
                        [
                            'type' => 'html',
                            'name' => 'scenarios_html',
                            'html_content' => '
                                <div class="form-group" id="scenarios-container">
                                    <label class="control-label col-lg-3">' . $this->l('Escenarios') . '</label>
                                    <div class="col-lg-9">
                                        <div id="scenarios-list"></div>
                                        <button type="button" id="add-scenario" class="btn btn-default">
                                            <i class="icon-plus"></i> ' . $this->l('Añadir Escenario') . '
                                        </button>
                                    </div>
                                </div>
                            '
                        ]
                    ]
                ]
            ],
            
            // FORMULARIO 4: AUDIT TEST
            [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Audit Test (Lighthouse)'),
                        'icon' => 'icon-search'
                    ],
                    'input' => [
                        [
                            'type' => 'switch',
                            'label' => $this->l('Habilitar Auditoría'),
                            'name' => 'audit_enabled',
                            'is_bool' => true,
                            'values' => [
                                ['id' => 'audit_enabled_on', 'value' => 1, 'label' => $this->l('Sí')],
                                ['id' => 'audit_enabled_off', 'value' => 0, 'label' => $this->l('No')]
                            ]
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('URL a auditar'),
                            'name' => 'audit_url',
                            'disabled' => true,
                            'form_group_class' => 'audit-options'
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Form Factor'),
                            'name' => 'audit_formFactor',
                            'options' => [
                                'query' => [
                                    ['id' => 'mobile', 'name' => $this->l('Móvil')],
                                    ['id' => 'desktop', 'name' => $this->l('Escritorio')]
                                ],
                                'id' => 'id',
                                'name' => 'name'
                            ],
                            'disabled' => true,
                            'form_group_class' => 'audit-options'
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Puntuación Mínima'),
                            'name' => 'audit_minScore',
                            'class' => 'fixed-width-sm',
                            'disabled' => true,
                            'form_group_class' => 'audit-options'
                        ],
                        [
                            'type' => 'html',
                            'name' => 'headers_html',
                            'html_content' => '
                                <div class="form-group" id="headers-container">
                                    <label class="control-label col-lg-3">' . $this->l('Headers Personalizados') . '</label>
                                    <div class="col-lg-9">
                                        <div id="headers-list"></div>
                                        <button type="button" id="add-header" class="btn btn-default">
                                            <i class="icon-plus"></i> ' . $this->l('Añadir Header') . '
                                        </button>
                                    </div>
                                </div>
                            '
                        ]
                    ],
                    'submit' => [
                        'title' => $this->l('Guardar Configuración'),
                        'class' => 'btn btn-primary'
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Mostrar lista de tests guardados con enlace a resultados
     */
    private function renderTestsList()
    {
        // Obtener tests de la base de datos
        $sql = "SELECT * FROM `" . _DB_PREFIX_ . self::DB_TABLE . "` 
                ORDER BY created_at DESC 
                LIMIT 20";
        
        $tests = Db::getInstance()->executeS($sql);
        
        if (empty($tests)) {
            return '';
        }
        
        $html = '
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-list"></i> ' . $this->l('Tests Guardados') . '
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>' . $this->l('Nombre') . '</th>
                            <th>' . $this->l('Estado') . '</th>
                            <th>' . $this->l('Resultados') . '</th>
                            <th>' . $this->l('Creado') . '</th>
                            <th>' . $this->l('Ejecutado') . '</th>
                            <th>' . $this->l('Acciones') . '</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($tests as $test) {
            $config = json_decode($test['request'], true);
            $name = isset($config['name']) ? $config['name'] : 'Sin nombre';
            
            // Verificar si hay resultados
            $hasResults = !empty($test['results']);
            $resultsInfo = '';
            
            if ($hasResults) {
                $resultsData = json_decode($test['results'], true);
                $resultTypes = [];
                
                foreach ($resultsData as $key => $result) {
                    if (isset($result['url'])) {
                        $resultTypes[] = 'Auditoría';
                    } elseif (is_array($result) && isset($result[0]['name'])) {
                        $resultTypes[] = 'Carga';
                    }
                }
                
                $resultsInfo = '<span class="badge badge-success">' . 
                              implode(' + ', array_unique($resultTypes)) . 
                              '</span>';
            }
            
            $html .= '
                    <tr>
                        <td>' . $test['id_test'] . '</td>
                        <td>' . htmlspecialchars($name) . '</td>
                        <td>
                            <span class="label label-' . $this->getStatusColor($test['status']) . '">
                                ' . $test['status'] . '
                            </span>
                        </td>
                        <td>' . $resultsInfo . '</td>
                        <td>' . $test['created_at'] . '</td>
                        <td>' . ($test['executed_at'] ?: '-') . '</td>
                        <td>
                            <div class="btn-group">
                                <a href="' . $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&view_test=' . $test['id_test'] . '" 
                                   class="btn btn-default btn-sm" title="' . $this->l('Ver detalles') . '">
                                    <i class="icon-eye"></i>
                                </a>';
            
            if ($hasResults) {
                $html .= '
                                <button type="button" 
                                        onclick="showResults(' . $test['id_test'] . ')" 
                                        class="btn btn-info btn-sm" title="' . $this->l('Ver resultados') . '">
                                    <i class="icon-bar-chart"></i>
                                </button>';
            }
            
            $html .= '
                                <button type="button" 
                                        onclick="executeTest(' . $test['id_test'] . ')" 
                                        class="btn btn-success btn-sm" title="' . $this->l('Ejecutar test') . '">
                                    <i class="icon-play"></i>
                                </button>
                                <button type="button" 
                                        onclick="exportResults(' . $test['id_test'] . ')" 
                                        class="btn btn-warning btn-sm" title="' . $this->l('Exportar resultados') . '">
                                    <i class="icon-download"></i>
                                </button>
                                <a href="' . $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&delete_test=' . $test['id_test'] . '" 
                                   class="btn btn-danger btn-sm" 
                                   onclick="return confirm(\'' . $this->l('¿Eliminar este test?') . '\')"
                                   title="' . $this->l('Eliminar') . '">
                                    <i class="icon-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>';
        }
        
        $html .= '
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Modal para mostrar resultados -->
        <div class="modal fade" id="resultsModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">' . $this->l('Resultados del Test') . '</h4>
                    </div>
                    <div class="modal-body">
                        <pre id="resultsContent" style="max-height: 500px; overflow: auto;"></pre>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">' . $this->l('Cerrar') . '</button>
                        <button type="button" class="btn btn-primary" onclick="downloadResults()">' . $this->l('Descargar JSON') . '</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Formularios ocultos -->
        <form id="executeTestForm" method="post" style="display: none;">
            <input type="hidden" name="executeTest" value="1">
            <input type="hidden" name="id_test" id="testIdToExecute">
            <input type="hidden" name="token" value="' . Tools::getAdminTokenLite('AdminModules') . '">
        </form>
        
        <script>
        var currentResults = null;
        var currentTestId = null;
        
        function executeTest(idTest) {
            if (confirm("' . $this->l('¿Ejecutar este test ahora?') . '")) {
                document.getElementById("testIdToExecute").value = idTest;
                document.getElementById("executeTestForm").submit();
            }
        }
        
        function showResults(idTest) {
            currentTestId = idTest;
            $.ajax({
                url: "' . $this->context->link->getAdminLink('AdminModules') . '",
                type: "POST",
                data: {
                    ajax: 1,
                    action: "getTestResults",
                    id_test: idTest,
                    token: "' . Tools::getAdminTokenLite('AdminModules') . '"
                },
                success: function(response) {
                    try {
                        var data = JSON.parse(response);
                        if (data.success) {
                            currentResults = data.results;
                            var formatted = JSON.stringify(data.results, null, 2);
                            document.getElementById("resultsContent").textContent = formatted;
                            $("#resultsModal").modal("show");
                        } else {
                            alert(data.message || "' . $this->l('Error al cargar resultados') . '");
                        }
                    } catch (e) {
                        console.error(e);
                        alert("' . $this->l('Error al procesar resultados') . '");
                    }
                }
            });
        }
        
        function downloadResults() {
            if (!currentResults) return;
            
            var dataStr = JSON.stringify(currentResults, null, 2);
            var dataUri = "data:application/json;charset=utf-8," + encodeURIComponent(dataStr);
            var exportFileDefaultName = "stressor_results_" + currentTestId + ".json";
            
            var linkElement = document.createElement("a");
            linkElement.setAttribute("href", dataUri);
            linkElement.setAttribute("download", exportFileDefaultName);
            linkElement.click();
        }
        
        function exportResults(idTest) {
            window.location.href = "' . $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&export_test=" + idTest;
        }
        </script>';
        
        return $html;
    }
    
    private function getStatusColor($status)
    {
        $colors = [
            'draft' => 'default',
            'scheduled' => 'info',
            'running' => 'warning',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'default'
        ];
        
        return isset($colors[$status]) ? $colors[$status] : 'default';
    }
    
    /**
     * Ejecutar un test y guardar resultados
     */
    private function executeTest($idTest)
    {
        try {
            // Obtener configuración de la base de datos
            $sql = "SELECT request FROM `" . _DB_PREFIX_ . self::DB_TABLE . "` 
                    WHERE id_test = " . (int)$idTest;
            
            $test = Db::getInstance()->getRow($sql);
            
            if (!$test) {
                throw new Exception('Test no encontrado');
            }
            
            // Decodificar configuración para determinar el tipo de test
            $config = json_decode($test['request'], true);
            
            // Actualizar estado a "running"
            Db::getInstance()->update(
                self::DB_TABLE,
                [
                    'status' => 'running',
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id_test = ' . (int)$idTest
            );
            
            // Ejecutar el test según su tipo y obtener resultados
            $results = $this->runStressorTest($config);
            
            // Actualizar estado a "completed" con resultados
            Db::getInstance()->update(
                self::DB_TABLE,
                [
                    'status' => 'completed',
                    'results' => pSQL(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
                    'executed_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id_test = ' . (int)$idTest
            );
            
            return true;
            
        } catch (Exception $e) {
            // Actualizar estado a "failed"
            Db::getInstance()->update(
                self::DB_TABLE,
                [
                    'status' => 'failed',
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id_test = ' . (int)$idTest
            );
            
            PrestaShopLogger::addLog('Error ejecutando test Stressor: ' . $e->getMessage(), 3);
            return false;
        }
    }
    
    /**
     * Simular ejecución de test y generar resultados de prueba
     */
    private function runStressorTest($config)
    {
        // Determinar qué tipos de jobs están configurados
        $hasAudit = false;
        $hasLoad = false;
        
        if (isset($config['jobs']) && is_array($config['jobs'])) {
            foreach ($config['jobs'] as $job) {
                if ($job['type'] === 'audit') {
                    $hasAudit = true;
                } elseif ($job['type'] === 'load') {
                    $hasLoad = true;
                }
            }
        }
        
        $results = [];
        
        // Generar resultados de auditoría si está configurado
        if ($hasAudit) {
            $auditId = isset($config['jobs'][0]['id']) ? $config['jobs'][0]['id'] : 'audit_homepage';
            
            $results[$auditId] = [
                'url' => $config['jobs'][0]['url'] ?? 'https://ejemplo.com',
                'performance' => rand(70, 98) / 100,
                'accessibility' => rand(65, 95) / 100,
                'seo' => rand(75, 99) / 100,
                'bestPractices' => rand(70, 95) / 100,
                'pwa' => rand(30, 80) / 100,
                'categories' => [
                    ['id' => 'performance', 'score' => rand(70, 98) / 100],
                    ['id' => 'accessibility', 'score' => rand(65, 95) / 100],
                    ['id' => 'seo', 'score' => rand(75, 99) / 100],
                    ['id' => 'best-practices', 'score' => rand(70, 95) / 100],
                    ['id' => 'pwa', 'score' => rand(30, 80) / 100]
                ]
            ];
        }
        
        // Generar resultados de carga si está configurado
        if ($hasLoad) {
            $loadId = isset($config['jobs'][0]['id']) ? $config['jobs'][0]['id'] : 'load_test_1';
            
            $results[$loadId] = [
                [
                    'name' => 'vus',
                    'type' => 'gauge',
                    'description' => 'Virtual Users - Número de usuarios virtuales activos',
                    'unit' => null,
                    'points' => $this->generateSamplePoints(150, 0, 10),
                    'summary' => [
                        'count' => 150,
                        'avg' => 4.8,
                        'min' => 0,
                        'max' => 10
                    ]
                ],
                [
                    'name' => 'http_reqs',
                    'type' => 'counter',
                    'description' => 'HTTP Requests - Total de peticiones HTTP',
                    'unit' => null,
                    'points' => $this->generateSamplePoints(100, 1, 1000, true),
                    'summary' => [
                        'count' => 100,
                        'avg' => 500,
                        'min' => 1,
                        'max' => 1000
                    ]
                ],
                [
                    'name' => 'http_req_duration',
                    'type' => 'trend',
                    'description' => 'HTTP Request Duration - Duración de las peticiones HTTP',
                    'unit' => 'ms',
                    'points' => $this->generateSamplePoints(1000, 120, 890),
                    'summary' => [
                        'count' => 1000,
                        'avg' => 245.3,
                        'min' => 120,
                        'max' => 890
                    ]
                ],
                [
                    'name' => 'http_req_failed',
                    'type' => 'rate',
                    'description' => 'HTTP Failed Requests - Porcentaje de peticiones fallidas',
                    'unit' => '%',
                    'points' => $this->generateSamplePoints(150, 0, 5),
                    'summary' => [
                        'count' => 150,
                        'avg' => 0.8,
                        'min' => 0,
                        'max' => 3.2
                    ]
                ],
                [
                    'name' => 'iterations',
                    'type' => 'counter',
                    'description' => 'Iterations - Iteraciones completadas',
                    'unit' => null,
                    'points' => $this->generateSamplePoints(50, 1, 100, true),
                    'summary' => [
                        'count' => 50,
                        'avg' => 50,
                        'min' => 1,
                        'max' => 100
                    ]
                ]
            ];
        }
        
        // Si no hay jobs configurados, generar un resultado de prueba completo
        if (empty($results)) {
            $results = $this->getSampleFullResults();
        }
        
        return $results;
    }
    
    /**
     * Generar puntos de datos de muestra
     */
    private function generateSamplePoints($count, $minValue, $maxValue, $incremental = false)
    {
        $points = [];
        $baseTime = time() - ($count * 10); // 10 segundos entre puntos
        
        for ($i = 0; $i < $count; $i++) {
            if ($incremental) {
                $value = $minValue + (($maxValue - $minValue) * $i / $count);
            } else {
                $value = rand($minValue * 100, $maxValue * 100) / 100;
            }
            
            $time = date('c', $baseTime + ($i * 10));
            
            $points[] = [
                'time' => $time,
                'timestamp' => $time,
                'value' => $value,
                'tags' => []
            ];
        }
        
        return $points;
    }
    
    /**
     * Obtener resultados de prueba completos (para inicialización)
     */
    public function getSampleFullResults()
    {
        return [
            'smoke_test_load' => [
                [
                    'name' => 'vus',
                    'type' => 'gauge',
                    'description' => 'Virtual Users - Número de usuarios virtuales activos',
                    'unit' => null,
                    'points' => [
                        [
                            'time' => '2024-01-15T10:00:00.000Z',
                            'timestamp' => '2024-01-15T10:00:00.000Z',
                            'value' => 5,
                            'tags' => []
                        ]
                    ],
                    'summary' => [
                        'count' => 150,
                        'avg' => 4.8,
                        'min' => 0,
                        'max' => 10
                    ]
                ],
                [
                    'name' => 'http_req_duration',
                    'type' => 'trend',
                    'description' => 'HTTP Request Duration - Duración de las peticiones HTTP',
                    'unit' => 'ms',
                    'points' => [
                        [
                            'time' => '2024-01-15T10:00:00.500Z',
                            'timestamp' => '2024-01-15T10:00:00.500Z',
                            'value' => 245.3,
                            'tags' => []
                        ]
                    ],
                    'summary' => [
                        'count' => 1000,
                        'avg' => 245.3,
                        'min' => 120,
                        'max' => 890
                    ]
                ],
                [
                    'name' => 'http_req_failed',
                    'type' => 'rate',
                    'description' => 'HTTP Failed Requests - Porcentaje de peticiones fallidas',
                    'unit' => '%',
                    'points' => [
                        [
                            'time' => '2024-01-15T10:00:01.000Z',
                            'timestamp' => '2024-01-15T10:00:01.000Z',
                            'value' => 0.8,
                            'tags' => []
                        ]
                    ],
                    'summary' => [
                        'count' => 150,
                        'avg' => 0.8,
                        'min' => 0,
                        'max' => 3.2
                    ]
                ]
            ],
            'homepage_audit' => [
                'url' => Tools::getHttpHost(true) . __PS_BASE_URI__,
                'performance' => 0.94,
                'accessibility' => 0.85,
                'seo' => 0.91,
                'bestPractices' => 0.88,
                'pwa' => 0.40,
                'categories' => [
                    ['id' => 'performance', 'score' => 0.94],
                    ['id' => 'accessibility', 'score' => 0.85],
                    ['id' => 'seo', 'score' => 0.91],
                    ['id' => 'best-practices', 'score' => 0.88],
                    ['id' => 'pwa', 'score' => 0.40]
                ]
            ]
        ];
    }
    
    /**
     * Exportar resultados como archivo JSON
     */
    private function exportTestResults($idTest)
    {
        $sql = "SELECT * FROM `" . _DB_PREFIX_ . self::DB_TABLE . "` 
                WHERE id_test = " . (int)$idTest;
        
        $test = Db::getInstance()->getRow($sql);
        
        if (!$test) {
            return false;
        }
        
        $config = json_decode($test['request'], true);
        $results = json_decode($test['results'], true);
        
        $exportData = [
            'test_info' => [
                'id' => $test['id_test'],
                'name' => $config['name'] ?? 'Sin nombre',
                'status' => $test['status'],
                'created_at' => $test['created_at'],
                'executed_at' => $test['executed_at']
            ],
            'configuration' => $config,
            'results' => $results
        ];
        
        $jsonOutput = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // Enviar como archivo descargable
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="stressor_test_' . $idTest . '_' . date('Y-m-d') . '.json"');
        header('Content-Length: ' . strlen($jsonOutput));
        
        echo $jsonOutput;
        exit;
    }
    
    /**
     * Preparar datos para las estadísticas
     */
    private function prepareStatsData($tests)
    {
        $stats = [
            'tests_count' => count($tests),
            'tests_30_days' => 0,
            'avg_performance' => 0,
            'avg_response_time' => 0,
            'health_score' => 0,
            'performance_trend' => 'stable',
            'last_audit_date' => '',
            'audit_scores' => [],
            'load_metrics' => [],
            'recent_tests' => [],
            'audit_chart_data' => [
                'labels' => ['Performance', 'Accessibility', 'SEO', 'Best Practices', 'PWA'],
                'datasets' => []
            ],
            'load_chart_data' => [
                'labels' => ['Tiempo Respuesta', 'Peticiones Fallidas', 'Usuarios Virtuales'],
                'datasets' => []
            ],
            'trend_chart_data' => [
                'labels' => [],
                'datasets' => []
            ]
        ];
        
        if (empty($tests)) {
            return $stats;
        }
        
        // Procesar cada test
        $performanceScores = [];
        $responseTimes = [];
        $auditData = [];
        $loadData = [];
        $trendData = [];
        
        foreach ($tests as $test) {
            $results = json_decode($test['results'], true);
            if (!$results) continue;
            
            // Procesar resultados de auditoría
            foreach ($results as $jobId => $jobResults) {
                if (isset($jobResults['url'])) {
                    // Es un resultado de auditoría
                    $auditData[] = $jobResults;
                    
                    if (empty($stats['last_audit_date'])) {
                        $stats['last_audit_date'] = $test['executed_at'];
                    }
                    
                    // Acumular scores
                    if (isset($jobResults['performance'])) {
                        $performanceScores[] = $jobResults['performance'];
                    }
                    
                    // Preparar datos para gráfico de auditoría
                    if (empty($stats['audit_scores'])) {
                        $stats['audit_scores'] = [
                            'Performance' => $jobResults['performance'] ?? 0,
                            'Accessibility' => $jobResults['accessibility'] ?? 0,
                            'SEO' => $jobResults['seo'] ?? 0,
                            'Best Practices' => $jobResults['bestPractices'] ?? 0,
                            'PWA' => $jobResults['pwa'] ?? 0
                        ];
                    }
                } elseif (is_array($jobResults) && isset($jobResults[0]['name'])) {
                    // Es un resultado de carga
                    $loadData[] = $jobResults;
                    
                    // Extraer métricas de carga
                    foreach ($jobResults as $metric) {
                        if ($metric['name'] === 'http_req_duration') {
                            $avgResponse = $metric['summary']['avg'] ?? 0;
                            $responseTimes[] = $avgResponse;
                        }
                        
                        // Preparar datos para tabla
                        if (in_array($metric['name'], ['vus', 'http_req_duration', 'http_req_failed', 'iterations'])) {
                            $metricName = $this->getMetricDisplayName($metric['name']);
                            $stats['load_metrics'][$metricName] = [
                                'avg' => $metric['summary']['avg'] ?? 0,
                                'min' => $metric['summary']['min'] ?? 0,
                                'max' => $metric['summary']['max'] ?? 0
                            ];
                        }
                    }
                }
            }
            
            // Preparar datos para tests recientes
            $config = json_decode($test['request'], true);
            $testName = $config['name'] ?? 'Test #' . $test['id_test'];
            
            // Determinar tipo y score del test
            $testType = 'audit';
            $testScore = 0;
            $responseTime = 0;
            
            if (!empty($loadData) && empty($auditData)) {
                $testType = 'load';
                $testScore = 0;
                $responseTime = end($responseTimes);
            } elseif (!empty($auditData)) {
                $testType = 'audit';
                $testScore = end($performanceScores);
            }
            
            $stats['recent_tests'][] = [
                'name' => $testName,
                'date' => $test['executed_at'],
                'type' => $testType,
                'score' => $testScore,
                'response_time' => $responseTime
            ];
            
            // Datos para gráfico de evolución
            if (!empty($performanceScores)) {
                $lastScore = end($performanceScores);
                $stats['trend_chart_data']['labels'][] = date('d/m', strtotime($test['executed_at']));
                $trendData[] = $lastScore;
            }
        }
        
        // Calcular promedios
        if (!empty($performanceScores)) {
            $stats['avg_performance'] = array_sum($performanceScores) / count($performanceScores);
            $stats['health_score'] = $stats['avg_performance'];
        }
        
        if (!empty($responseTimes)) {
            $stats['avg_response_time'] = array_sum($responseTimes) / count($responseTimes);
        }
        
        // Determinar tendencia
        if (count($performanceScores) >= 2) {
            $firstScore = $performanceScores[0];
            $lastScore = end($performanceScores);
            
            if ($lastScore > $firstScore + 0.05) {
                $stats['performance_trend'] = 'up';
            } elseif ($lastScore < $firstScore - 0.05) {
                $stats['performance_trend'] = 'down';
            }
        }
        
        // Preparar datos para gráficos
        $this->prepareChartData($stats, $auditData, $loadData, $trendData);
        
        // Contar tests de los últimos 30 días
        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
        foreach ($tests as $test) {
            if ($test['executed_at'] >= $thirtyDaysAgo) {
                $stats['tests_30_days']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Preparar datos para gráficos
     */
    private function prepareChartData(&$stats, $auditData, $loadData, $trendData)
    {
        // Gráfico de auditoría (radar)
        if (!empty($auditData)) {
            $latestAudit = end($auditData);
            $stats['audit_chart_data']['datasets'][] = [
                'label' => 'Puntaje Actual',
                'data' => [
                    $latestAudit['performance'] ?? 0,
                    $latestAudit['accessibility'] ?? 0,
                    $latestAudit['seo'] ?? 0,
                    $latestAudit['bestPractices'] ?? 0,
                    $latestAudit['pwa'] ?? 0
                ],
                'backgroundColor' => 'rgba(102, 126, 234, 0.2)',
                'borderColor' => 'rgba(102, 126, 234, 1)',
                'borderWidth' => 2
            ];
        }
        
        // Gráfico de métricas de carga
        if (!empty($loadData)) {
            $latestLoad = end($loadData);
            
            $responseTime = 0;
            $failedRequests = 0;
            $vus = 0;
            
            foreach ($latestLoad as $metric) {
                switch ($metric['name']) {
                    case 'http_req_duration':
                        $responseTime = $metric['summary']['avg'] ?? 0;
                        break;
                    case 'http_req_failed':
                        $failedRequests = $metric['summary']['avg'] ?? 0;
                        break;
                    case 'vus':
                        $vus = $metric['summary']['avg'] ?? 0;
                        break;
                }
            }
            
            $stats['load_chart_data']['datasets'][] = [
                'label' => 'Valor Actual',
                'data' => [$responseTime, $failedRequests, $vus],
                'backgroundColor' => [
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(75, 192, 192, 0.7)'
                ]
            ];
        }
        
        // Gráfico de evolución
        if (!empty($trendData)) {
            $stats['trend_chart_data']['datasets'][] = [
                'label' => 'Performance Score',
                'data' => $trendData,
                'borderColor' => 'rgba(102, 126, 234, 1)',
                'backgroundColor' => 'rgba(102, 126, 234, 0.1)',
                'fill' => true,
                'tension' => 0.4
            ];
        }
    }
    
    /**
     * Obtener nombre legible para métricas
     */
    private function getMetricDisplayName($metricName)
    {
        $names = [
            'vus' => 'Usuarios Virtuales',
            'http_req_duration' => 'Tiempo de Respuesta',
            'http_req_failed' => 'Peticiones Fallidas',
            'iterations' => 'Iteraciones',
            'http_reqs' => 'Total Peticiones',
            'data_sent' => 'Datos Enviados',
            'data_received' => 'Datos Recibidos'
        ];
        
        return $names[$metricName] ?? $metricName;
    }
    
    /**
     * Agregar un test de ejemplo con resultados iniciales
     */
    private function addSampleTestWithResults()
    {
        $sampleConfig = [
            'name' => 'Test de Ejemplo - Suite Completa',
            'owner' => 'Sistema',
            'timestamp' => time(),
            'options' => [
                'runInParallel' => true,
                'timeout' => 300000,
                'saveRaw' => false
            ],
            'jobs' => [
                [
                    'type' => 'load',
                    'id' => 'smoke_test_load',
                    'options' => [
                        'vus' => 10,
                        'iterations' => 100,
                        'duration' => '30s'
                    ]
                ],
                [
                    'type' => 'audit',
                    'id' => 'homepage_audit',
                    'url' => Tools::getHttpHost(true) . __PS_BASE_URI__,
                    'options' => [
                        'output' => ['json'],
                        'emulatedFormFactor' => 'mobile',
                        'onlyCategories' => ['performance', 'accessibility', 'seo']
                    ]
                ]
            ]
        ];
        
        $sampleResults = $this->getSampleFullResults();
        
        $data = [
            'request' => pSQL(json_encode($sampleConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            'results' => pSQL(json_encode($sampleResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'executed_at' => date('Y-m-d H:i:s')
        ];
        
        return Db::getInstance()->insert(self::DB_TABLE, $data);
    }
    
    /**
     * Cargar traducciones para JavaScript
     */
    public function getJsTranslations()
    {
        return json_encode([
            'scenario_name' => $this->l('Nombre del escenario'),
            'header_key' => $this->l('Clave (ej: Authorization)'),
            'header_value' => $this->l('Valor'),
            'remove' => $this->l('Eliminar'),
            'at_least_one_test' => $this->l('Debe habilitar al menos un tipo de test (Load Test o Audit Test)'),
            'scenario_name_required' => $this->l('Todos los escenarios deben tener un nombre'),
            'header_value_required' => $this->l('Si especifica una clave de header, debe proporcionar un valor'),
        ]);
    }
}