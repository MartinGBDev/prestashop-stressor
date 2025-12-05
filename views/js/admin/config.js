document.addEventListener('DOMContentLoaded', function() {
    
    // ====================
    // ACTIVAR/DESACTIVAR CAMPOS DEL FORMULARIO
    // ====================
    
    // Activar/desactivar campos de Load Test
    const loadEnabled = document.querySelector('[name="load_enabled"]:checked');
    if (loadEnabled) {
        toggleLoadFields(loadEnabled.value === '1');
    }
    
    document.querySelectorAll('[name="load_enabled"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            toggleLoadFields(this.value === '1');
        });
    });
    
    // Activar/desactivar campos de Audit Test
    const auditEnabled = document.querySelector('[name="audit_enabled"]:checked');
    if (auditEnabled) {
        toggleAuditFields(auditEnabled.value === '1');
    }
    
    document.querySelectorAll('[name="audit_enabled"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            toggleAuditFields(this.value === '1');
        });
    });
    
    // ====================
    // GESTIÓN DE ESCENARIOS (LOAD TEST)
    // ====================
    
    const scenariosContainer = document.getElementById('scenarios-list');
    const addScenarioBtn = document.getElementById('add-scenario');
    
    if (addScenarioBtn) {
        addScenarioBtn.addEventListener('click', function() {
            addScenarioField();
        });
        
        // Agregar un escenario inicial si no hay ninguno
        if (scenariosContainer.children.length === 0) {
            addScenarioField();
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
        
        // Agregar un header inicial si no hay ninguno
        if (headersContainer.children.length === 0) {
            addHeaderField();
        }
    }
    
    // ====================
    // FUNCIONES AUXILIARES
    // ====================
    
    function toggleLoadFields(enabled) {
        const loadFields = document.querySelectorAll('.load-options input, .load-options select');
        loadFields.forEach(function(field) {
            field.disabled = !enabled;
            const parent = field.closest('.form-group');
            if (parent) {
                if (enabled) {
                    parent.classList.remove('disabled');
                } else {
                    parent.classList.add('disabled');
                }
            }
        });
        
        // Mostrar/ocultar contenedor de escenarios
        const scenariosContainer = document.getElementById('scenarios-container');
        if (scenariosContainer) {
            scenariosContainer.style.display = enabled ? 'block' : 'none';
        }
    }
    
    function toggleAuditFields(enabled) {
        const auditFields = document.querySelectorAll('.audit-options input, .audit-options select, .audit-options textarea');
        auditFields.forEach(function(field) {
            field.disabled = !enabled;
            const parent = field.closest('.form-group');
            if (parent) {
                if (enabled) {
                    parent.classList.remove('disabled');
                } else {
                    parent.classList.add('disabled');
                }
            }
        });
        
        // Mostrar/ocultar contenedor de headers
        const headersContainer = document.getElementById('headers-container');
        if (headersContainer) {
            headersContainer.style.display = enabled ? 'block' : 'none';
        }
    }
    
    function addScenarioField() {
        const index = scenariosContainer.children.length;
        const scenarioDiv = document.createElement('div');
        scenarioDiv.className = 'scenario-field form-group';
        scenarioDiv.innerHTML = `
            <div class="row">
                <div class="col-lg-5">
                    <input type="text" 
                           name="load_scenario_name[]" 
                           class="form-control" 
                           placeholder="${stressorTranslate.scenario_name || 'Nombre del escenario'}" 
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
                    <button type="button" class="btn btn-danger remove-scenario" title="${stressorTranslate.remove || 'Eliminar'}">
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
        const index = headersContainer.children.length;
        const headerDiv = document.createElement('div');
        headerDiv.className = 'header-field form-group';
        headerDiv.innerHTML = `
            <div class="row">
                <div class="col-lg-5">
                    <input type="text" 
                           name="audit_header_key[]" 
                           class="form-control" 
                           placeholder="${stressorTranslate.header_key || 'Clave (ej: Authorization)'}" 
                           value="">
                </div>
                <div class="col-lg-5">
                    <input type="text" 
                           name="audit_header_value[]" 
                           class="form-control" 
                           placeholder="${stressorTranslate.header_value || 'Valor'}" 
                           value="">
                </div>
                <div class="col-lg-2">
                    <button type="button" class="btn btn-danger remove-header" title="${stressorTranslate.remove || 'Eliminar'}">
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
    
    const form = document.querySelector('form[name="stressor_config"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Validar que al menos un test esté habilitado
            const loadEnabled = document.querySelector('[name="load_enabled"]:checked');
            const auditEnabled = document.querySelector('[name="audit_enabled"]:checked');
            
            if ((!loadEnabled || loadEnabled.value !== '1') && (!auditEnabled || auditEnabled.value !== '1')) {
                alert(stressorTranslate.at_least_one_test || 'Debe habilitar al menos un tipo de test (Load Test o Audit Test)');
                e.preventDefault();
                return false;
            }
            
            // Validar escenarios si Load Test está habilitado
            if (loadEnabled && loadEnabled.value === '1') {
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
                    alert(stressorTranslate.scenario_name_required || 'Todos los escenarios deben tener un nombre');
                    e.preventDefault();
                    return false;
                }
            }
            
            // Validar headers si Audit Test está habilitado
            if (auditEnabled && auditEnabled.value === '1') {
                const headerKeys = document.querySelectorAll('[name="audit_header_key[]"]');
                const headerValues = document.querySelectorAll('[name="audit_header_value[]"]');
                
                for (let i = 0; i < headerKeys.length; i++) {
                    if (headerKeys[i].value.trim() !== '' && headerValues[i].value.trim() === '') {
                        alert(stressorTranslate.header_value_required || 'Si especifica una clave de header, debe proporcionar un valor');
                        e.preventDefault();
                        return false;
                    }
                }
            }
            
            return true;
        });
    }
    
   
});