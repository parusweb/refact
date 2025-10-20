/* ParusWeb Functions - Скрипты фронтенда */

(function($) {
    'use strict';
    
    const ParusWeb = {
        
        init: function() {
            this.initCalculators();
            this.initValidation();
            this.initDynamicPricing();
        },
        
        /**
         * Инициализация калькуляторов
         */
        initCalculators: function() {
            // Калькулятор площади
            $('.area-calculator').each(function() {
                const $calc = $(this);
                const productId = $calc.data('product-id');
                const pricePerM2 = parseFloat($('#calc_area_price').val()) || 0;
                
                $calc.find('#calc_area_width, #calc_area_length').on('input', function() {
                    const width = parseFloat($('#calc_area_width').val()) || 0;
                    const length = parseFloat($('#calc_area_length').val()) || 0;
                    const area = width * length;
                    const totalPrice = area * pricePerM2;
                    
                    $calc.find('.area-value').text(ParusWeb.formatNumber(area, 2));
                    $calc.find('.price-value').html(ParusWeb.formatPrice(totalPrice));
                    $('#calc_area_m2').val(area);
                    
                    // Обновляем поле количества
                    $('input.qty').val(area.toFixed(2)).prop('readonly', true);
                });
            });
            
            // Калькулятор погонных метров
            $('.running-meter-calculator').each(function() {
                const $calc = $(this);
                const pricePerMeter = parseFloat($('#running_meter_price').val()) || 0;
                
                $calc.find('#running_meter_length').on('input', function() {
                    const length = parseFloat($(this).val()) || 0;
                    const totalPrice = length * pricePerMeter;
                    
                    $calc.find('.total-value').html(ParusWeb.formatPrice(totalPrice));
                    
                    // Обновляем поле количества
                    $('input.qty').val(length.toFixed(2));
                });
            });
        },
        
        /**
         * Валидация форм
         */
        initValidation: function() {
            // Валидация при добавлении в корзину
            $('form.cart').on('submit', function(e) {
                const $form = $(this);
                
                // Проверка калькулятора площади
                if ($('.area-calculator').length) {
                    const width = parseFloat($('#calc_area_width').val()) || 0;
                    const length = parseFloat($('#calc_area_length').val()) || 0;
                    
                    if (width <= 0 || length <= 0) {
                        e.preventDefault();
                        alert('Пожалуйста, укажите размеры');
                        return false;
                    }
                    
                    if (width > 100 || length > 100) {
                        e.preventDefault();
                        alert('Размеры слишком большие (максимум 100 м)');
                        return false;
                    }
                }
                
                // Проверка калькулятора погонных метров
                if ($('.running-meter-calculator').length) {
                    const length = parseFloat($('#running_meter_length').val()) || 0;
                    
                    if (length <= 0) {
                        e.preventDefault();
                        alert('Пожалуйста, укажите длину');
                        return false;
                    }
                    
                    if (length > 1000) {
                        e.preventDefault();
                        alert('Длина слишком большая (максимум 1000 м.п.)');
                        return false;
                    }
                }
            });
        },
        
        /**
         * Динамическое обновление цен
         */
        initDynamicPricing: function() {
            // Обновление цены при изменении вариации
            $('form.variations_form').on('found_variation', function(event, variation) {
                const $form = $(this);
                const price = variation.display_price;
                
                // Обновляем цену в калькуляторах
                if ($('.area-calculator').length) {
                    $('#calc_area_price').val(price);
                    $('.area-calculator #calc_area_width').trigger('input');
                }
                
                if ($('.running-meter-calculator').length) {
                    $('#running_meter_price').val(price);
                    $('.running-meter-calculator #running_meter_length').trigger('input');
                }
            });
        },
        
        /**
         * Форматирование числа
         */
        formatNumber: function(number, decimals) {
            decimals = decimals || 2;
            return number.toFixed(decimals).replace('.', paruswebData.decimal_separator);
        },
        
        /**
         * Форматирование цены
         */
        formatPrice: function(price) {
            const formatted = this.formatNumber(price, paruswebData.decimals);
            return paruswebData.currency_symbol + formatted;
        },
        
        /**
         * AJAX запрос расчета цены по площади
         */
        calculateAreaPrice: function(productId, width, length, callback) {
            $.ajax({
                url: paruswebData.ajax_url,
                type: 'POST',
                data: {
                    action: 'calculate_area_price',
                    nonce: paruswebAjax.nonce,
                    product_id: productId,
                    width: width,
                    length: length
                },
                success: function(response) {
                    if (response.success) {
                        callback(response.data);
                    }
                },
                error: function() {
                    console.error('Ошибка при расчете цены');
                }
            });
        },
        
        /**
         * AJAX запрос расчета цены по длине
         */
        calculateLengthPrice: function(productId, length, callback) {
            $.ajax({
                url: paruswebData.ajax_url,
                type: 'POST',
                data: {
                    action: 'calculate_length_price',
                    nonce: paruswebAjax.nonce,
                    product_id: productId,
                    length: length
                },
                success: function(response) {
                    if (response.success) {
                        callback(response.data);
                    }
                },
                error: function() {
                    console.error('Ошибка при расчете цены');
                }
            });
        },
        
        /**
         * Получение информации о товаре
         */
        getProductInfo: function(productId, callback) {
            $.ajax({
                url: paruswebData.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_product_info',
                    nonce: paruswebAjax.nonce,
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        callback(response.data);
                    }
                },
                error: function() {
                    console.error('Ошибка при получении информации о товаре');
                }
            });
        },
        
        /**
         * Проверка наличия товара
         */
        checkProductStock: function(productId, quantity, callback) {
            $.ajax({
                url: paruswebData.ajax_url,
                type: 'POST',
                data: {
                    action: 'check_product_stock',
                    nonce: paruswebAjax.nonce,
                    product_id: productId,
                    quantity: quantity
                },
                success: function(response) {
                    if (response.success) {
                        callback(response.data);
                    }
                },
                error: function() {
                    console.error('Ошибка при проверке наличия');
                }
            });
        }
    };
    
    // Инициализация при загрузке DOM
    $(document).ready(function() {
        ParusWeb.init();
    });
    
    // Экспорт в глобальную область
    window.ParusWeb = ParusWeb;
    
})(jQuery);