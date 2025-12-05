# Stressor Module for PrestaShop



## 🎯 ¿Qué es Stressor?

Stressor es un módulo avanzado de **stress testing** y **auditoría de rendimiento** para PrestaShop. Permite a los administradores de tiendas online realizar pruebas de carga y auditorías de rendimiento directamente desde el panel de administración de PrestaShop, sin necesidad de herramientas externas.

### Características Principales

- ✅ **Pruebas de Carga (Load Testing)**: Simula múltiples usuarios concurrentes
- ✅ **Auditorías Lighthouse**: Análisis de performance, SEO, accesibilidad y buenas prácticas
- ✅ **Dashboard Integrado**: Gráficos interactivos con Chart.js
- ✅ **Gestión de Resultados**: Almacenamiento y exportación de resultados
- ✅ **Configuración Flexible**: Múltiples escenarios de prueba
- ✅ **Ejecución Paralela**: Tests simultáneos para mayor eficiencia

## 📋 Requisitos del Sistema

### Requisitos Mínimos
- **PrestaShop**: Versión 1.7.0 o superior
- **PHP**: Versión 7.2 o superior
- **Memoria PHP**: Mínimo 128MB (recomendado 256MB+)
- **Base de Datos**: MySQL 5.7+ / MariaDB 10.2+
- **JavaScript**: Habilitado en el navegador

### Extensiones PHP Requeridas
- cURL
- JSON
- DOM
- SimpleXML
- mbstring

### Recomendaciones
- **Timeout PHP**: Aumentar a 300 segundos para pruebas largas
- **Memoria**: 512MB o más para tests complejos
- **Node.js**: Opcional para ejecutar tests de carga reales

## 🚀 Instalación
 

1. **Descargar el módulo**
   - Descarga el archivo ZIP desde la sección de releases
   - **No descargues desde "Code > Download ZIP"** ya que no incluye dependencias

2. **Instalar en PrestaShop**
   - Ve a **Módulos > Gestor de módulos** en tu panel de administración
   - Haz clic en **"Subir un módulo"**
   - Selecciona el archivo ZIP descargado
   - Haz clic en **"Instalar"**

3. **Configuración inicial**
   - El módulo creará automáticamente una tabla en la base de datos
   - Se agregará un test de ejemplo con datos demostrativos
   - Aparecerá en **Estadísticas > Stressor Dashboard**



### Verificación de Instalación

1. Verifica que el módulo aparece en **Módulos > Módulos y servicios**
2. Busca "Stressor" en la lista
3. Asegúrate de que el estado sea **"Instalado" y "Habilitado"**
4. Verifica que aparece una nueva sección en **Estadísticas**

## 🔧 Configuración

### Panel de Configuración Principal

Accede a la configuración desde:
**Módulos > Módulos y servicios > Stressor > Configurar**

### Crear un Nuevo Test

1. **Configuración General**
   - Nombre del test
   - Propietario
   - Opciones de ejecución

2. **Agregar Jobs de Prueba**
   - **Load Test**: Simula carga de usuarios
     - Configurar VUs (usuarios virtuales)
     - Especificar duración/iteraciones
     - Definir endpoints a probar
   
   - **Audit Test**: Auditoría Lighthouse
     - URL a auditar
     - Métricas a incluir
     - Dispositivo a emular

3. **Configurar Headers**
   - Headers personalizados
   - Autenticación
   - Cookies específicas

4. **Guardar y Ejecutar**
   - Guardar configuración
   - Ejecutar test manualmente
   - Programar ejecución automática

## 📊 Dashboard de Resultados

### Estadísticas Principales
- **Tests Ejecutados**: Conteo total y últimos 30 días
- **Performance Promedio**: Score Lighthouse promedio
- **Tiempo de Respuesta**: Latencia promedio en ms
- **Estado del Sistema**: Indicador de salud general

### Gráficos Interactivos

1. **Gráfico de Auditoría (Radar)**
   - Comparativa de métricas Lighthouse
   - Performance, SEO, Accesibilidad, etc.
   - Indicadores de puntuación

2. **Gráfico de Métricas de Carga (Barras)**
   - Tiempo de respuesta
   - Peticiones fallidas
   - Usuarios virtuales activos

3. **Gráfico de Evolución (Línea)**
   - Tendencia histórica
   - Comparativa entre tests
   - Detección de degradación

### Gestión de Tests

#### Lista de Tests
- Vista tabular con todos los tests guardados
- Indicadores de estado (draft, running, completed, failed)
- Información de resultados disponibles
- Fechas de creación y ejecución

#### Acciones Disponibles
- 👁️ **Ver detalles**: Configuración completa
- 📊 **Ver resultados**: JSON con métricas detalladas
- ▶️ **Ejecutar test**: Ejecutar inmediatamente
- 📥 **Exportar resultados**: Descargar JSON completo
- 🗑️ **Eliminar test**: Eliminar permanentemente

## 💡 Casos de Uso

### 1. Optimización de Performance
- Identificar cuellos de botella antes de campañas
- Medir impacto de nuevas funcionalidades
- Validar optimizaciones de caché

### 2. Control de Calidad
- Auditorías periódicas de SEO
- Verificación de accesibilidad
- Validación de buenas prácticas

### 3. Preparación para Eventos
- Stress testing para Black Friday
- Validación de capacidad de servidor
- Pruebas de escalabilidad

### 4. Desarrollo Continuo
- Tests automatizados en staging
- Comparativa entre versiones
- Monitoreo de degradación

## ⚡ Ventajas Competitivas

### ✅ Integración Nativa
- 100% integrado con PrestaShop
- Interface familiar para administradores

### ✅ Facilidad de Uso
- Configuración mediante formularios
- Resultados visuales intuitivos
- Sin conocimiento técnico avanzado

### ✅ Potencia
- Tests complejos de carga
- Auditorías profesionales
- Métricas detalladas

### ✅ Flexibilidad
- Múltiples escenarios
- Configuración personalizada
- Resultados exportables

### ✅ Costo-Efectivo
- Gratuito y open source
- Sin costos de servicios externos
- Sin límites de uso

## 🛠️ Solución de Problemas

### Problemas Comunes

#### 1. "Error de tiempo de ejecución"
**Solución:**
```php
# Editar php.ini
max_execution_time = 300
memory_limit = 512M
```

#### 2. "No se pueden cargar los gráficos"
**Solución:**
- Verificar que Chart.js está disponible
- Revisar la consola JavaScript del navegador
- Probar con un CDN alternativo

#### 3. "Test no se ejecuta"
**Solución:**
- Verificar configuración de cURL
- Revisar logs de PrestaShop
- Probar con un test simple

#### 4. "Resultados no se guardan"
**Solución:**
- Verificar permisos de base de datos
- Revisar límite de tamaño de columna LONGTEXT
- Comprobar encoding JSON

### Logs y Diagnóstico

1. **Logs de PrestaShop**
   ```bash
   var/logs/prod.log
   var/logs/dev.log
   ```

2. **Logs del Módulo**
   - Registros en tabla `ps_stressor_tests`
   - Timestamps de ejecución
   - Estados de cada test

3. **Diagnóstico del Sistema**
   - PHP Info desde configuración
   - Estado de extensiones
   - Configuración del servidor

## 🔄 Actualización

### Proceso de Actualización
1. **Backup** de configuración existente
2. **Desinstalar** versión anterior
3. **Instalar** nueva versión
4. **Restaurar** tests importantes
5. **Verificar** compatibilidad

### Migración de Datos
- Los tests guardados se mantienen entre versiones
- Resultados históricos conservados
- Configuración migrada automáticamente

## 📈 Roadmap

### Próximas Funcionalidades
- [ ] Ejecución programada de tests
- [ ] Alertas por email
- [ ] Comparativa entre tests
- [ ] API REST para integraciones
- [ ] Tests multi-página
- [ ] Métricas de negocio

### Mejoras Planificadas
- [ ] Gráficos más avanzados
- [ ] Plantillas de tests
- [ ] Exportación a PDF
- [ ] Dashboard público
- [ ] Integración con CI/CD

## 🤝 Contribuir

### Reportar Issues
1. Verificar que no sea un duplicado
2. Proporcionar versión de PrestaShop
3. Incluir logs relevantes
4. Describir pasos para reproducir

### Desarrollo
1. Fork del repositorio
2. Crear rama de feature
3. Commit de cambios
4. Pull request

### Guías de Estilo
- Seguir estándares PrestaShop
- Documentación en español
- Tests unitarios cuando sea posible

## 📄 Licencia

Este módulo está licenciado bajo **Academic Free License 3.0 (AFL-3.0)**.



## 🙏 Agradecimientos

- **PrestaShop** por la excelente plataforma
- **Chart.js** por las librerías de gráficos
- **k6.io** por la inspiración en load testing
- **Google Lighthouse** por las métricas de auditoría

## 📞 Soporte

### Canales de Ayuda
- **Issues de GitHub**: Para bugs y mejoras
- **Foro PrestaShop**: Comunidad de usuarios
- **Documentación**: Guías detalladas

### Soporte Comercial
- Consultoría personalizada
- Implementación empresarial
- Desarrollo de funcionalidades

---

**⭐ Si este módulo te es útil, por favor dale una estrella en GitHub!**

---
*Desarrollado con ❤️ para la comunidad PrestaShop*
