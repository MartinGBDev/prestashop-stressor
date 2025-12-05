document.addEventListener('DOMContentLoaded', function() {
    
    // ====================
    // VARIABLES GLOBALES
    // ====================
    
    // Traducciones (se definen desde PHP)
    window.stressorTranslate = window.stressorTranslate || {
        scenario_name: 'Nombre del escenario',
        header_key: 'Clave (ej: Authorization)',
        header_value: 'Valor',
        remove: 'Eliminar',
        at_least_one_test: 'Debe habilitar al menos un tipo de test (Load Test o Audit Test)',
        scenario_name_required: 'Todos los escenarios deben tener un nombre',
        header_value_required: 'Si especifica una clave de header, debe proporcionar un valor'
    };
    
    // ====================
    // TOGGLE FORM FIELDS - FUNCIÓN CORREGIDA
    // ====================
    
    // Función para habilitar/deshabilitar campos de Load Test
    function toggleLoadFields(enabled) {
        const loadFields = document.querySelectorAll('.load-options input, .load-options select');
        
        loadFields.forEach(function(field) {
            // Usar prop('disabled') para jQuery o propiedad disabled directa
            if (field.tagName === 'SELECT') {
                field.disabled = !enabled;
            } else {
                field.disabled = !enabled;
            }
            
            // Cambiar estilo visual
            const parent = field.closest('.form-group');
            if (parent) {
                if (enabled) {
                    parent.classList.remove('disabled');
                    parent.style.opacity = '1';
                } else {
                    parent.classList.add('disabled');
                    parent.style.opacity = '0.6';
                }
            }
        });
        
        // Mostrar/ocultar contenedor de escenarios
        const scenariosContainer = document.getElementById('scenarios-container');
        if (scenariosContainer) {
            scenariosContainer.style.display = enabled ? 'block' : 'none';
            
            // Si se habilita, agregar un escenario por defecto si no hay
            if (enabled && document.getElementById('scenarios-list').children.length === 0) {
                addScenarioField();
            }
        }
    }
    
    // Función para habilitar/deshabilitar campos de Audit Test
    function toggleAuditFields(enabled) {
        const auditFields = document.querySelectorAll('.audit-options input, .audit-options select, .audit-options textarea');
        
        auditFields.forEach(function(field) {
            field.disabled = !enabled;
            
            // Cambiar estilo visual
            const parent = field.closest('.form-group');
            if (parent) {
                if (enabled) {
                    parent.classList.remove('disabled');
                    parent.style.opacity = '1';
                } else {
                    parent.classList.add('disabled');
                    parent.style.opacity = '0.6';
                }
            }
        });
        
        // Mostrar/ocultar contenedor de headers
        const headersContainer = document.getElementById('headers-container');
        if (headersContainer) {
            headersContainer.style.display = enabled ? 'block' : 'none';
            
            // Si se habilita, agregar un header por defecto si no hay
            if (enabled && document.getElementById('headers-list').children.length === 0) {
                addHeaderField();
            }
        }
    }
    
    // ====================
    // INICIALIZACIÓN DE TOGGLES
    // ====================
    
    // Inicializar Load Test
    const loadEnabledCheckbox = document.querySelector('input[name="load_enabled"]');
    if (loadEnabledCheckbox) {
        // Verificar el estado inicial
        const loadEnabled = loadEnabledCheckbox.checked || loadEnabledCheckbox.value === '1';
        toggleLoadFields(loadEnabled);
        
        // Agregar event listener a todos los radio buttons de load_enabled
        document.querySelectorAll('input[name="load_enabled"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                const isEnabled = this.checked || this.value === '1';
                toggleLoadFields(isEnabled);
            });
        });
    }
    
    // Inicializar Audit Test
    const auditEnabledCheckbox = document.querySelector('input[name="audit_enabled"]');
    if (auditEnabledCheckbox) {
        // Verificar el estado inicial
        const auditEnabled = auditEnabledCheckbox.checked || auditEnabledCheckbox.value === '1';
        toggleAuditFields(auditEnabled);
        
        // Agregar event listener a todos los radio buttons de audit_enabled
        document.querySelectorAll('input[name="audit_enabled"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                const isEnabled = this.checked || this.value === '1';
                toggleAuditFields(isEnabled);
            });
        });
    }
    
    // ====================
    // GESTIÓN DE ESCENARIOS (LOAD TEST)
    // ====================
    
    const scenariosContainer = document.getElementById('scenarios-list');
    const addScenarioBtn = document.getElementById('add-scenario');
    
    if (addScenarioBtn) {
        addScenarioBtn.addEventListener('click', function() {
            addScenarioField();
        });
        
        // Agregar un escenario inicial si Load Test está habilitado y no hay escenarios
        if (loadEnabledCheckbox && (loadEnabledCheckbox.checked || loadEnabledCheckbox.value === '1')) {
            if (!scenariosContainer || scenariosContainer.children.length === 0) {
                addScenarioField();
            }
        }
    }
    
    // ====================
    // GESTIÓN DE HEADERS (AUDIT TEST)
    // ====================
    
    const headersContainer = document.getElementById('headers-list');
    const addHeaderBtn = document.getElementById('add-header');
    
    if (addHeaderBtn) {
        addHeaderBtn.addEventListener('click', function() {
            addHeaderField();
        });
        
        // Agregar un header inicial si Audit Test está habilitado y no hay headers
        if (auditEnabledCheckbox && (auditEnabledCheckbox.checked || auditEnabledCheckbox.value === '1')) {
            if (!headersContainer || headersContainer.children.length === 0) {
                addHeaderField();
            }
        }
    }
    
    // ====================
    // FUNCIONES AUXILIARES
    // ====================
    
    function addScenarioField() {
        if (!scenariosContainer) return;
        
        const index = scenariosContainer.children.length;
        const scenarioDiv = document.createElement('div');
        scenarioDiv.className = 'scenario-field form-group';
        scenarioDiv.innerHTML = `
            <div class="row">
                <div class="col-lg-5">
                    <input type="text" 
                           name="load_scenario_name[]" 
                           class="form-control" 
                           placeholder="${window.stressorTranslate.scenario_name || 'Nombre del escenario'}" 
                           value="">
                </div>
                <div class="col-lg-5">
                    <select name="load_scenario_executor[]" class="form-control">
                        <option value="shared-iterations">shared-iterations</option>
                        <option value="per-vu-iterations">per-vu-iterations</option>
                        <option value="constant-vus">constant-vus</option>
                        <option value="ramping-vus">ramping-vus</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <button type="button" class="btn btn-danger remove-scenario" title="${window.stressorTranslate.remove || 'Eliminar'}">
                        <i class="icon-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        scenariosContainer.appendChild(scenarioDiv);
        
        // Agregar evento al botón de eliminar
        const removeBtn = scenarioDiv.querySelector('.remove-scenario');
        removeBtn.addEventListener('click', function() {
            scenarioDiv.remove();
        });
    }
    
    function addHeaderField() {
        if (!headersContainer) return;
        
        const index = headersContainer.children.length;
        const headerDiv = document.createElement('div');
        headerDiv.className = 'header-field form-group';
        headerDiv.innerHTML = `
            <div class="row">
                <div class="col-lg-5">
                    <input type="text" 
                           name="audit_header_key[]" 
                           class="form-control" 
                           placeholder="${window.stressorTranslate.header_key || 'Clave (ej: Authorization)'}" 
                           value="">
                </div>
                <div class="col-lg-5">
                    <input type="text" 
                           name="audit_header_value[]" 
                           class="form-control" 
                           placeholder="${window.stressorTranslate.header_value || 'Valor'}" 
                           value="">
                </div>
                <div class="col-lg-2">
                    <button type="button" class="btn btn-danger remove-header" title="${window.stressorTranslate.remove || 'Eliminar'}">
                        <i class="icon-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        headersContainer.appendChild(headerDiv);
        
        // Agregar evento al botón de eliminar
        const removeBtn = headerDiv.querySelector('.remove-header');
        removeBtn.addEventListener('click', function() {
            headerDiv.remove();
        });
    }
    
    // ====================
    // VALIDACIÓN DEL FORMULARIO
    // ====================
    
    const form = document.querySelector('form[name="stressor_config"]') || 
                 document.querySelector('form[action*="configure=' + moduleName + '"]');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            // Verificar que al menos un test esté habilitado
            const loadEnabledElements = document.querySelectorAll('input[name="load_enabled"]:checked');
            const auditEnabledElements = document.querySelectorAll('input[name="audit_enabled"]:checked');
            
            let loadEnabled = false;
            let auditEnabled = false;
            
            // Verificar radio buttons
            loadEnabledElements.forEach(function(el) {
                if (el.value === '1') loadEnabled = true;
            });
            
            auditEnabledElements.forEach(function(el) {
                if (el.value === '1') auditEnabled = true;
            });
            
            // Si es un switch de Bootstrap, verificar el input hidden
            if (!loadEnabled) {
                const loadHidden = document.querySelector('input[name="load_enabled"][type="hidden"]');
                if (loadHidden && loadHidden.value === '1') loadEnabled = true;
            }
            
            if (!auditEnabled) {
                const auditHidden = document.querySelector('input[name="audit_enabled"][type="hidden"]');
                if (auditHidden && auditHidden.value === '1') auditEnabled = true;
            }
            
            if (!loadEnabled && !auditEnabled) {
                alert(window.stressorTranslate.at_least_one_test || 'Debe habilitar al menos un tipo de test (Load Test o Audit Test)');
                e.preventDefault();
                return false;
            }
            
            // Validar escenarios si Load Test está habilitado
            if (loadEnabled) {
                const scenarioNames = document.querySelectorAll('[name="load_scenario_name[]"]');
                let hasEmptyNames = false;
                
                scenarioNames.forEach(function(input) {
                    if (input.value.trim() === '') {
                        hasEmptyNames = true;
                        input.style.borderColor = '#f00';
                    } else {
                        input.style.borderColor = '';
                    }
                });
                
                if (hasEmptyNames) {
                    alert(window.stressorTranslate.scenario_name_required || 'Todos los escenarios deben tener un nombre');
                    e.preventDefault();
                    return false;
                }
            }
            
            // Validar headers si Audit Test está habilitado
            if (auditEnabled) {
                const headerKeys = document.querySelectorAll('[name="audit_header_key[]"]');
                const headerValues = document.querySelectorAll('[name="audit_header_value[]"]');
                
                for (let i = 0; i < headerKeys.length; i++) {
                    if (headerKeys[i].value.trim() !== '' && headerValues[i].value.trim() === '') {
                        alert(window.stressorTranslate.header_value_required || 'Si especifica una clave de header, debe proporcionar un valor');
                        e.preventDefault();
                        return false;
                    }
                }
            }
            
            return true;
        });
    }
    
    // ====================
    // MANEJO DE SWITCHES DE BOOTSTRAP
    // ====================
    
    // Los switches de Bootstrap PrestaShop crean inputs ocultos
    // Necesitamos sincronizarlos con los checkboxes visuales
    function initBootstrapSwitches() {
        // Para switches de Load Test
        document.querySelectorAll('.switch-load_enabled input[type="checkbox"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const hiddenInput = document.querySelector('input[name="load_enabled"][type="hidden"]');
                if (hiddenInput) {
                    hiddenInput.value = this.checked ? '1' : '0';
                }
                
                // Actualizar campos
                toggleLoadFields(this.checked);
            });
        });
        
        // Para switches de Audit Test
        document.querySelectorAll('.switch-audit_enabled input[type="checkbox"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const hiddenInput = document.querySelector('input[name="audit_enabled"][type="hidden"]');
                if (hiddenInput) {
                    hiddenInput.value = this.checked ? '1' : '0';
                }
                
                // Actualizar campos
                toggleAuditFields(this.checked);
            });
        });
    }
    
    // Inicializar switches después de que se cargue el DOM
    setTimeout(initBootstrapSwitches, 100);
    
    // ====================
    // ESTILOS ADICIONALES
    // ====================
    
    // Agregar estilos CSS dinámicamente
    const style = document.createElement('style');
    style.textContent = `
        .form-group.disabled {
            opacity: 0.6;
        }
        
        .form-group.disabled input,
        .form-group.disabled select,
        .form-group.disabled textarea {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        
        .scenario-field,
        .header-field {
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #25b9d7;
        }
        
        .scenario-field .row,
        .header-field .row {
            align-items: center;
        }
        
        .remove-scenario,
        .remove-header {
            padding: 5px 10px;
            font-size: 12px;
        }
    `;
    document.head.appendChild(style);
});