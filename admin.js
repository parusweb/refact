/* ParusWeb Functions - Скрипты админки */

jQuery(document).ready(function($) {
    
    const dependencies = paruswebModules.dependencies;
    const dependents = paruswebModules.dependents;
    
    // Обработчик изменения чекбоксов
    $('.module-checkbox').on('change', function() {
        const $checkbox = $(this);
        const $row = $checkbox.closest('tr');
        const moduleId = $row.data('module');
        const isChecked = $checkbox.is(':checked');
        
        if (isChecked) {
            // Включение модуля - включаем зависимости
            enableDependencies(moduleId);
        } else {
            // Отключение модуля - отключаем зависимые
            disableDependents(moduleId);
        }
        
        updateModuleStates();
    });
    
    // Включение всех зависимостей модуля
    function enableDependencies(moduleId) {
        const deps = dependencies[moduleId] || [];
        
        deps.forEach(function(depId) {
            const $depCheckbox = $('tr[data-module="' + depId + '"]').find('.module-checkbox');
            
            if (!$depCheckbox.is(':checked')) {
                $depCheckbox.prop('checked', true);
                // Рекурсивно включаем зависимости зависимостей
                enableDependencies(depId);
            }
        });
    }
    
    // Отключение всех зависимых модулей
    function disableDependents(moduleId) {
        const deps = dependents[moduleId] || [];
        
        deps.forEach(function(depId) {
            const $depCheckbox = $('tr[data-module="' + depId + '"]').find('.module-checkbox');
            
            if ($depCheckbox.is(':checked')) {
                $depCheckbox.prop('checked', false);
                // Рекурсивно отключаем зависимые от зависимых
                disableDependents(depId);
            }
        });
    }
    
    // Обновление визуального состояния модулей
    function updateModuleStates() {
        $('tr[data-module]').each(function() {
            const $row = $(this);
            const $checkbox = $row.find('.module-checkbox');
            const moduleId = $row.data('module');
            const isChecked = $checkbox.is(':checked');
            
            // Удаляем все классы состояний
            $row.removeClass('disabled has-missing-deps will-disable-dependents');
            
            if (!isChecked) {
                $row.addClass('disabled');
            } else {
                // Проверяем зависимости
                const deps = dependencies[moduleId] || [];
                let hasMissingDeps = false;
                
                deps.forEach(function(depId) {
                    const $depCheckbox = $('tr[data-module="' + depId + '"]').find('.module-checkbox');
                    if (!$depCheckbox.is(':checked')) {
                        hasMissingDeps = true;
                    }
                });
                
                if (hasMissingDeps) {
                    $row.addClass('has-missing-deps');
                }
            }
        });
    }
    
    // Предупреждение при отключении модуля с зависимыми
    $('.module-checkbox').on('click', function(e) {
        const $checkbox = $(this);
        const $row = $checkbox.closest('tr');
        const moduleId = $row.data('module');
        const wasChecked = !$checkbox.is(':checked'); // инвертируем т.к. уже изменилось
        
        // Если пытаемся отключить модуль (был включен)
        if (wasChecked && dependents[moduleId] && dependents[moduleId].length > 0) {
            const depNames = dependents[moduleId].map(function(depId) {
                return $('tr[data-module="' + depId + '"]').find('strong').text();
            }).join(', ');
            
            const confirmMsg = 'Внимание! От этого модуля зависят другие модули: ' + depNames + 
                             '\n\nОни также будут отключены. Продолжить?';
            
            if (!confirm(confirmMsg)) {
                e.preventDefault();
                $checkbox.prop('checked', true);
                return false;
            }
        }
    });
    
    // Начальное обновление состояний
    updateModuleStates();
    
    // Подсветка при наведении на модуль с зависимостями
    $('tr[data-module]').hover(
        function() {
            const $row = $(this);
            const moduleId = $row.data('module');
            
            // Подсвечиваем зависимости
            const deps = dependencies[moduleId] || [];
            deps.forEach(function(depId) {
                $('tr[data-module="' + depId + '"]').css('background-color', '#e8f4f8');
            });
            
            // Подсвечиваем зависимые
            const dependentsList = dependents[moduleId] || [];
            dependentsList.forEach(function(depId) {
                $('tr[data-module="' + depId + '"]').css('background-color', '#fff8e1');
            });
        },
        function() {
            // Убираем подсветку
            $('tr[data-module]').css('background-color', '');
        }
    );
});