<?php
/**
 * Модуль: Legacy JavaScript
 * Описание: JavaScript код (калькуляторы, покраска, фаски, фильтры)
 * Зависимости: product-calculations, category-helpers
 * 
 * ВАЖНО: Этот файл НЕ должен содержать PHP функций!
 * Все PHP функции находятся в других модулях:
 * - category-helpers.php - функции категорий
 * - product-calculations.php - функции расчетов
 * - pm-paint-schemes.php - функции покраски
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ЭТОТ ФАЙЛ СОДЕРЖИТ ТОЛЬКО JAVASCRIPT!
 * 
 * Не добавляйте сюда PHP функции типа:
 * - is_in_painting_categories()
 * - get_calculator_type()
 * - и т.д.
 * 
 * Они уже определены в других модулях!
 */

// Подключение скриптов и стилей
add_action('wp_enqueue_scripts', 'parusweb_enqueue_legacy_scripts');
function parusweb_enqueue_legacy_scripts() {
    if (!is_product()) {
        return;
    }
    
    // Основной скрипт (если есть отдельный файл)
    if (file_exists(plugin_dir_path(dirname(__FILE__)) . 'assets/js/legacy.js')) {
        wp_enqueue_script(
            'parusweb-legacy',
            plugins_url('assets/js/legacy.js', dirname(__FILE__)),
            array('jquery'),
            '1.0.0',
            true
        );
    }
    
    // Передаем данные в JavaScript
    wp_localize_script('jquery', 'paruswebData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('parusweb_nonce'),
    ));
}

// Встроенный JavaScript код
add_action('wp_footer', 'parusweb_legacy_javascript', 100);
function parusweb_legacy_javascript() {
    if (!is_product()) {
        return;
    }
    
    global $product;
    if (!$product) {
        return;
    }
    
    $product_id = $product->get_id();
    
    // Получаем данные используя функции из других модулей
    $calculator_type = function_exists('get_calculator_type') ? get_calculator_type($product_id) : 'none';
    $is_painting = function_exists('is_in_painting_categories') ? is_in_painting_categories($product_id) : false;
    
    ?>
    <script>
    jQuery(document).ready(function($) {
        'use strict';
        
        // Данные товара
        const productData = {
            id: <?php echo $product_id; ?>,
            calculatorType: '<?php echo esc_js($calculator_type); ?>',
            hasPainting: <?php echo $is_painting ? 'true' : 'false'; ?>
        };
        
        console.log('ParusWeb Legacy JS loaded', productData);
        
        // ===============================================
        // КАЛЬКУЛЯТОРЫ
        // ===============================================
        
        // Калькулятор площади (м²)
        if (productData.calculatorType === 'square_meter') {
            $(document).on('input', '.area-calculator input', function() {
                calculateArea();
            });
        }
        
        function calculateArea() {
            const width = parseFloat($('#area_width').val()) || 0;
            const length = parseFloat($('#area_length').val()) || 0;
            const area = width * length;
            
            if (area > 0) {
                $('#calculated_area').text(area.toFixed(2) + ' м²');
                updatePriceByArea(area);
            }
        }
        
        // Калькулятор длины (м.п.)
        if (productData.calculatorType === 'running_meter') {
            $(document).on('input', '.length-calculator input', function() {
                calculateLength();
            });
        }
        
        function calculateLength() {
            const length = parseFloat($('#running_length').val()) || 0;
            
            if (length > 0) {
                $('#calculated_length').text(length + ' м.п.');
                updatePriceByLength(length);
            }
        }
        
        // Калькулятор фальшбалок
        if (productData.calculatorType === 'falsebalk') {
            $(document).on('change', '.falsebalk-calculator select, .falsebalk-calculator input', function() {
                calculateFalsebalk();
            });
        }
        
        function calculateFalsebalk() {
            const shape = $('#falsebalk_shape').val();
            const length = parseFloat($('#falsebalk_length').val()) || 0;
            const width = parseFloat($('#falsebalk_width').val()) || 0;
            const height = parseFloat($('#falsebalk_height').val()) || 0;
            
            if (length > 0) {
                updatePriceByFalsebalk(shape, length, width, height);
            }
        }
        
        // ===============================================
        // ОБНОВЛЕНИЕ ЦЕН
        // ===============================================
        
        function updatePriceByArea(area) {
            $.ajax({
                url: paruswebData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'calculate_area_price',
                    nonce: paruswebData.nonce,
                    product_id: productData.id,
                    area: area
                },
                success: function(response) {
                    if (response.success) {
                        $('#calculated_price').html(response.data.price_html);
                        updateAddToCartButton(response.data);
                    }
                }
            });
        }
        
        function updatePriceByLength(length) {
            $.ajax({
                url: paruswebData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'calculate_length_price',
                    nonce: paruswebData.nonce,
                    product_id: productData.id,
                    length: length
                },
                success: function(response) {
                    if (response.success) {
                        $('#calculated_price').html(response.data.price_html);
                        updateAddToCartButton(response.data);
                    }
                }
            });
        }
        
        function updatePriceByFalsebalk(shape, length, width, height) {
            $.ajax({
                url: paruswebData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'calculate_falsebalk_price',
                    nonce: paruswebData.nonce,
                    product_id: productData.id,
                    shape: shape,
                    length: length,
                    width: width,
                    height: height
                },
                success: function(response) {
                    if (response.success) {
                        $('#calculated_price').html(response.data.price_html);
                        updateAddToCartButton(response.data);
                    }
                }
            });
        }
        
        function updateAddToCartButton(data) {
            // Обновляем скрытые поля для корзины
            $('input[name="custom_calculator_data"]').val(JSON.stringify(data));
        }
        
        // ===============================================
        // УСЛУГИ ПОКРАСКИ
        // ===============================================
        
        if (productData.hasPainting) {
            // Обработчик выбора услуги покраски
            $(document).on('change', 'select[name="painting_service"]', function() {
                const serviceName = $(this).find(':selected').text();
                const servicePrice = parseFloat($(this).val()) || 0;
                
                if (servicePrice > 0) {
                    updatePaintingPrice(servicePrice);
                }
            });
            
            // Обработчик выбора цвета
            $(document).on('change', 'input[name="paint_color"]', function() {
                const colorCode = $(this).data('color-code');
                const colorImage = $(this).data('color-image');
                
                // Показываем выбранный цвет
                $('.selected-color-preview').html(
                    '<img src="' + colorImage + '" alt="' + colorCode + '">' +
                    '<span>' + colorCode + '</span>'
                );
            });
        }
        
        function updatePaintingPrice(additionalPrice) {
            const currentPrice = parseFloat($('#calculated_price').data('base-price')) || 0;
            const totalPrice = currentPrice + additionalPrice;
            
            $('#painting_price').text('+' + additionalPrice.toFixed(2) + ' ₽');
            $('#total_with_painting').text(totalPrice.toFixed(2) + ' ₽');
        }
        
        // ===============================================
        // ФАСКИ И ОБРАБОТКА КРОМОК
        // ===============================================
        
        $(document).on('change', 'input[name="edge_processing[]"]', function() {
            updateEdgeProcessing();
        });
        
        function updateEdgeProcessing() {
            const selected = $('input[name="edge_processing[]"]:checked');
            let totalEdgePrice = 0;
            
            selected.each(function() {
                const price = parseFloat($(this).data('price')) || 0;
                totalEdgePrice += price;
            });
            
            $('#edge_processing_price').text('+' + totalEdgePrice.toFixed(2) + ' ₽');
        }
        
        // ===============================================
        // ФИЛЬТРЫ ТОВАРОВ
        // ===============================================
        
        // Фильтр по размерам
        $('.size-filter input').on('change', function() {
            filterProducts();
        });
        
        function filterProducts() {
            const minWidth = parseFloat($('#min_width').val()) || 0;
            const maxWidth = parseFloat($('#max_width').val()) || 999999;
            const minLength = parseFloat($('#min_length').val()) || 0;
            const maxLength = parseFloat($('#max_length').val()) || 999999;
            
            $('.product-item').each(function() {
                const width = parseFloat($(this).data('width')) || 0;
                const length = parseFloat($(this).data('length')) || 0;
                
                const visible = (width >= minWidth && width <= maxWidth && 
                               length >= minLength && length <= maxLength);
                
                $(this).toggle(visible);
            });
        }
        
        // ===============================================
        // ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
        // ===============================================
        
        // Форматирование чисел
        function formatNumber(num, decimals = 2) {
            return parseFloat(num).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        }
        
        // Валидация числовых полей
        $('input[type="number"]').on('input', function() {
            const min = parseFloat($(this).attr('min')) || 0;
            const max = parseFloat($(this).attr('max')) || 999999;
            let val = parseFloat($(this).val()) || 0;
            
            if (val < min) val = min;
            if (val > max) val = max;
            
            $(this).val(val);
        });
        
        // ===============================================
        // ИНИЦИАЛИЗАЦИЯ
        // ===============================================
        
        // Автоматический расчет при загрузке
        if ($('.area-calculator input').length) {
            calculateArea();
        }
        
        if ($('.length-calculator input').length) {
            calculateLength();
        }
        
        if ($('.falsebalk-calculator select').length) {
            calculateFalsebalk();
        }
        
        console.log('ParusWeb Legacy JS initialized');
    });
    </script>
    <?php
}