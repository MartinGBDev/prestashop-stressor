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

        $this->displayName = 'Stressor';
        $this->description = 'Módulo de stress testing y auditoría de rendimiento';
        $this->ps_versions_compliancy = array('min' => '8.0.4', 'max' => '9.0.0');
        
        parent::__construct();
    }

    public function install()
    {
        return parent::install() && 
               $this->registerHook('displayAdminStatsModules') &&
               $this->registerHook('actionAdminControllerSetMedia') &&
               $this->createDatabaseTable();
    }
    
    public function uninstall()
    {
        return parent::uninstall() &&
               $this->dropDatabaseTable();
    }
    
    /**
     * Crear tabla de base de datos con campo JSON único
     */
    private function createDatabaseTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . self::DB_TABLE . "` (
            `id_test` INT(11) NOT NULL AUTO_INCREMENT,
            `request` LONGTEXT NOT NULL COMMENT 'Configuración completa en formato JSON stringified',
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
        // TODO: Implementar dashboard
        return '';
    }
    
    public function getContent()
    {
        $output = '';
        
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
            }
        }
        
        // Mostrar formulario
        $output .= $this->renderConfigurationForm();
        
        // Mostrar lista de tests guardados
        $output .= $this->renderTestsList();
        
        return $output;
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
     * Mostrar lista de tests guardados
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
                            <th>' . $this->l('Creado') . '</th>
                            <th>' . $this->l('Acciones') . '</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($tests as $test) {
            $config = json_decode($test['request'], true);
            $name = isset($config['name']) ? $config['name'] : 'Sin nombre';
            
            $html .= '
                    <tr>
                        <td>' . $test['id_test'] . '</td>
                        <td>' . htmlspecialchars($name) . '</td>
                        <td>
                            <span class="label label-' . $this->getStatusColor($test['status']) . '">
                                ' . $test['status'] . '
                            </span>
                        </td>
                        <td>' . $test['created_at'] . '</td>
                        <td>
                            <a href="' . $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&view_test=' . $test['id_test'] . '" 
                               class="btn btn-default btn-sm">
                                <i class="icon-eye"></i> Ver
                            </a>
                            <button type="button" 
                                    onclick="executeTest(' . $test['id_test'] . ')" 
                                    class="btn btn-success btn-sm">
                                <i class="icon-play"></i> Ejecutar
                            </button>
                            <a href="' . $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&delete_test=' . $test['id_test'] . '" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm(\'' . $this->l('¿Eliminar este test?') . '\')">
                                <i class="icon-trash"></i>
                            </a>
                        </td>
                    </tr>';
        }
        
        $html .= '
                    </tbody>
                </table>
            </div>
        </div>
        
        <form id="executeTestForm" method="post" style="display: none;">
            <input type="hidden" name="executeTest" value="1">
            <input type="hidden" name="id_test" id="testIdToExecute">
            <input type="hidden" name="token" value="' . Tools::getAdminTokenLite('AdminModules') . '">
        </form>
        
        <script>
        function executeTest(idTest) {
            if (confirm("' . $this->l('¿Ejecutar este test ahora?') . '")) {
                document.getElementById("testIdToExecute").value = idTest;
                document.getElementById("executeTestForm").submit();
            }
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
     * Ejecutar un test
     */
    private function executeTest($idTest)
    {
        // Obtener configuración de la base de datos
        $sql = "SELECT request FROM `" . _DB_PREFIX_ . self::DB_TABLE . "` 
                WHERE id_test = " . (int)$idTest;
        
        $test = Db::getInstance()->getRow($sql);
        
        if (!$test) {
            return false;
        }
        
        // Actualizar estado a "running"
        Db::getInstance()->update(
            self::DB_TABLE,
            [
                'status' => 'running',
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id_test = ' . (int)$idTest
        );
        
        // Aquí iría la lógica para ejecutar el stress test
        // Por ahora solo simulamos
        sleep(2);
        
        // Actualizar estado a "completed"
        Db::getInstance()->update(
            self::DB_TABLE,
            [
                'status' => 'completed',
                'executed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id_test = ' . (int)$idTest
        );
        
        return true;
    }
    
    /**
     * Hook para añadir JavaScript
     */
    public function hookActionAdminControllerSetMedia()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/admin/config.js');
        }
    }
}