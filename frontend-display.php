<?php
/**
 * Модуль: Отображение на фронтенде
 * Описание: Вывод информации о товарах, калькуляторов, форматирование цен
 * Зависимости: product-calculations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Подключение стилей и скриптов фронтенда
 */
add_action('wp_enqueue_scripts', 'enqueue_frontend_scripts');
function enqueue_frontend_scripts() {
    
    if (is_product() || is_shop() || is_product_category()) {
        wp_enqueue_style(
            'parusweb-frontend',
            PARUSWEB_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            PARUSWEB_VERSION
        );
        
        wp_enqueue_script(
            'parusweb-frontend',
            PARUSWEB_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            PARUSWEB_VERSION,
            true
        );
        
        wp_localize_script('parusweb-frontend', 'paruswebData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'decimal_separator' => wc_get_price_decimal_separator(),
            'thousand_separator' => wc_get_price_thousand_separator(),
            'decimals' => wc_get_price_decimals()
        ]);
    }
}

/**
 * Добавление калькулятора площади на страницу товара
 */
add_action('woocommerce_before_add_to_cart_button', 'display_area_calculator');
function display_area_calculator() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $sold_by_area = get_post_meta($product->get_id(), '_sold_by_area', true);
    
    if ($sold_by_area !== 'yes') {
        return;
    }
    
    $price = $product->get_price();
    $title = $product->get_title();
    $area_from_title = extract_area_with_qty($title, $product->get_id());
    
    ?>
    <div class="parusweb-calculator area-calculator" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
        <h4>Калькулятор площади</h4>
        
        <div class="calculator-row">
            <label for="calc_area_width">Ширина (м):</label>
            <input type="number" id="calc_area_width" name="calc_area_width" step="0.01" min="0" value="" placeholder="Ширина">
        </div>
        
        <div class="calculator-row">
            <label for="calc_area_length">Длина (м):</label>
            <input type="number" id="calc_area_length" name="calc_area_length" step="0.01" min="0" value="" placeholder="Длина">
        </div>
        
        <div class="calculator-result">
            <div class="result-area">
                Площадь: <strong class="area-value">0</strong> м²
            </div>
            <div class="result-price">
                Цена: <strong class="price-value"><?php echo wc_price(0); ?></strong>
            </div>
        </div>
        
        <input type="hidden" name="calc_area_m2" id="calc_area_m2" value="0">
        <input type="hidden" name="calc_area_price" id="calc_area_price" value="<?php echo esc_attr($price); ?>">
        
        <script>
        jQuery(document).ready(function($) {
            const pricePerM2 = <?php echo $price; ?>;
            
            function updateAreaCalc() {
                const width = parseFloat($('#calc_area_width').val()) || 0;
                const length = parseFloat($('#calc_area_length').val()) || 0;
                const area = width * length;
                const totalPrice = area * pricePerM2;
                
                $('.area-value').text(area.toFixed(2));
                $('.price-value').html(formatPrice(totalPrice));
                $('#calc_area_m2').val(area);
                
                // Отключаем стандартное поле количества
                $('input.qty').val(area).prop('readonly', true);
            }
            
            function formatPrice(price) {
                return paruswebData.currency_symbol + price.toFixed(paruswebData.decimals);
            }
            
            $('#calc_area_width, #calc_area_length').on('input', updateAreaCalc);
        });
        </script>
    </div>
    <?php
}

/**
 * Добавление калькулятора погонных метров на страницу товара
 */
add_action('woocommerce_before_add_to_cart_button', 'display_running_meter_calculator');
function display_running_meter_calculator() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $sold_by_length = get_post_meta($product->get_id(), '_sold_by_length', true);
    
    if ($sold_by_length !== 'yes') {
        return;
    }
    
    $price = $product->get_price();
    
    ?>
    <div class="parusweb-calculator running-meter-calculator" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
        <h4>Калькулятор погонных метров</h4>
        
        <div class="calculator-row">
            <label for="running_meter_length">Длина (м.п.):</label>
            <input type="number" id="running_meter_length" name="running_meter_length" step="0.01" min="0" value="1" placeholder="Длина">
        </div>
        
        <div class="calculator-result">
            <div class="result-price">
                Цена за м.п.: <strong><?php echo wc_price($price); ?></strong>
            </div>
            <div class="result-total">
                Итого: <strong class="total-value"><?php echo wc_price($price); ?></strong>
            </div>
        </div>
        
        <input type="hidden" name="running_meter_price" id="running_meter_price" value="<?php echo esc_attr($price); ?>">
        
        <script>
        jQuery(document).ready(function($) {
            const pricePerMeter = <?php echo $price; ?>;
            
            function updateRunningMeterCalc() {
                const length = parseFloat($('#running_meter_length').val()) || 0;
                const totalPrice = length * pricePerMeter;
                
                $('.total-value').html(formatPrice(totalPrice));
                
                // Обновляем количество
                $('input.qty').val(length);
            }
            
            function formatPrice(price) {
                return paruswebData.currency_symbol + price.toFixed(paruswebData.decimals);
            }
            
            $('#running_meter_length').on('input', updateRunningMeterCalc);
        });
        </script>
    </div>
    <?php
}

/**
 * Отображение единицы измерения рядом с ценой
 */
add_filter('woocommerce_get_price_html', 'add_unit_to_price', 10, 2);
function add_unit_to_price($price, $product) {
    
    if (!is_product()) {
        return $price;
    }
    
    $unit = get_post_meta($product->get_id(), '_custom_unit', true);
    
    if (empty($unit)) {
        return $price;
    }
    
    // Добавляем единицу измерения к цене
    $price .= ' <small class="unit-label">/' . esc_html($unit) . '</small>';
    
    return $price;
}

/**
 * Отображение площади из названия товара
 */
add_action('woocommerce_single_product_summary', 'display_product_area_info', 15);
function display_product_area_info() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $title = $product->get_title();
    $area = extract_area_with_qty($title, $product->get_id());
    
    if ($area) {
        echo '<div class="product-area-info">';
        echo '<span class="area-label">Площадь в упаковке:</span> ';
        echo '<strong class="area-value">' . number_format($area, 2, ',', ' ') . ' м²</strong>';
        echo '</div>';
    }
}

/**
 * Форматирование названия товара в корзине и заказах
 */
add_filter('woocommerce_cart_item_name', 'format_cart_item_name', 10, 3);
function format_cart_item_name($name, $cart_item, $cart_item_key) {
    
    $product_id = $cart_item['product_id'];
    $multiplier = get_final_multiplier($product_id);
    
    if ($multiplier != 1.0 && $multiplier > 0) {
        $name .= '<br><small style="color: #666;">Множитель: ×' . number_format($multiplier, 2) . '</small>';
    }
    
    return $name;
}

/**
 * Отображение бейджа для товаров с множителем
 */
add_action('woocommerce_before_shop_loop_item_title', 'display_multiplier_badge');
function display_multiplier_badge() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $multiplier = get_final_multiplier($product->get_id());
    
    if ($multiplier > 1.0) {
        $percent = round(($multiplier - 1) * 100);
        echo '<span class="multiplier-badge">+' . $percent . '%</span>';
    }
}

/**
 * Добавление информации о множителе на страницу товара
 */
add_action('woocommerce_single_product_summary', 'display_multiplier_info', 12);
function display_multiplier_info() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $multiplier = get_final_multiplier($product->get_id());
    
    if ($multiplier != 1.0) {
        $percent = round(($multiplier - 1) * 100);
        $sign = $multiplier > 1 ? '+' : '';
        
        echo '<div class="product-multiplier-info">';
        echo '<span class="info-icon">ⓘ</span> ';
        echo '<span>К цене применен множитель: <strong>' . $sign . $percent . '%</strong></span>';
        echo '</div>';
    }
}

/**
 * Русские окончания для единиц измерения в корзине
 */
add_filter('woocommerce_cart_item_quantity', 'format_cart_quantity_russian', 10, 3);
function format_cart_quantity_russian($product_quantity, $cart_item_key, $cart_item) {
    
    $quantity = $cart_item['quantity'];
    
    // Определяем правильное окончание
    $forms = [
        'шт' => ['штука', 'штуки', 'штук'],
        'м2' => ['м²', 'м²', 'м²'],
        'м.п.' => ['м.п.', 'м.п.', 'м.п.'],
        'л' => ['литр', 'литра', 'литров'],
        'кг' => ['кг', 'кг', 'кг']
    ];
    
    return $product_quantity;
}

/**
 * Функция для получения правильного окончания числительных
 */
function get_russian_plural($number, $forms) {
    $cases = [2, 0, 1, 1, 1, 2];
    return $forms[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
}