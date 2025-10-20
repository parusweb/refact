<?php
/**
 * Модуль: Функционал корзины
 * Описание: Логика работы корзины, добавление товаров с расчетами
 * Зависимости: product-calculations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Валидация перед добавлением в корзину
 */
add_filter('woocommerce_add_to_cart_validation', 'validate_cart_item_data', 10, 3);
function validate_cart_item_data($passed, $product_id, $quantity) {
    
    // Проверяем нужен ли калькулятор для этого товара
    if (!function_exists('is_in_target_categories')) {
        return $passed;
    }
    
    if (!is_in_target_categories($product_id)) {
        return $passed;
    }
    
    // Проверяем что данные калькулятора заполнены
    $has_calc_data = false;
    
    // Калькулятор площади
    if (isset($_POST['custom_area_packs']) && !empty($_POST['custom_area_area_value'])) {
        $has_calc_data = true;
    }
    
    // Калькулятор размеров
    if (isset($_POST['custom_width_val']) && !empty($_POST['custom_length_val'])) {
        $has_calc_data = true;
    }
    
    // Калькулятор множителя
    if (isset($_POST['custom_mult_width']) && !empty($_POST['custom_mult_length'])) {
        $has_calc_data = true;
    }
    
    // Калькулятор погонных метров
    if (isset($_POST['custom_rm_length']) && !empty($_POST['custom_rm_length'])) {
        $has_calc_data = true;
    }
    
    // Калькулятор квадратных метров
    if (isset($_POST['custom_sq_width']) && !empty($_POST['custom_sq_length'])) {
        $has_calc_data = true;
    }
    
    // Покупка из карточки или стандартная покупка
    if (isset($_POST['card_purchase']) || isset($_POST['standard_pack_purchase'])) {
        $has_calc_data = true;
    }
    
    // Если калькулятор нужен, но данных нет - ошибка
    if (!$has_calc_data) {
        // Проверяем есть ли площадь в названии - значит калькулятор обязателен
        $title = get_the_title($product_id);
        if (function_exists('extract_area_with_qty')) {
            $area = extract_area_with_qty($title, $product_id);
            if ($area) {
                wc_add_notice('Пожалуйста, используйте калькулятор для расчета количества', 'error');
                return false;
            }
        }
    }
    
    return $passed;
}

/**
 * Сохранение метаданных товара при добавлении в корзину
 */
add_action('woocommerce_add_to_cart', 'save_cart_item_metadata', 10, 6);
function save_cart_item_metadata($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    
    // Логируем добавление для отладки
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('ParusWeb: Adding to cart - Product ID: ' . $product_id . ', Quantity: ' . $quantity);
        if (!empty($cart_item_data)) {
            error_log('ParusWeb: Cart item data: ' . print_r($cart_item_data, true));
        }
    }
}

/**
 * Отображение метаданных в корзине
 * Примечание: Форматирование вынесено в price-display.php
 */
// Функции format_cart_item_price() и format_cart_item_subtotal() 
// находятся в модуле price-display.php

/**
 * Пересчет цен в корзине на основе кастомных данных
 * Примечание: Основная логика в legacy-javascript.php
 */
// Функция woocommerce_before_calculate_totals находится в legacy-javascript.php

/**
 * Сохранение дополнительных данных в мета заказа
 */
add_action('woocommerce_checkout_create_order_line_item', 'save_order_item_metadata', 10, 4);
function save_order_item_metadata($item, $cart_item_key, $values, $order) {
    
    // Сохраняем данные калькулятора площади
    if (isset($values['custom_area_calc'])) {
        $data = $values['custom_area_calc'];
        $item->add_meta_data('_calc_type', 'area');
        $item->add_meta_data('_calc_area', $data['area']);
        $item->add_meta_data('_calc_packs', $data['packs']);
        
        if (isset($data['painting_service'])) {
            $painting = $data['painting_service'];
            $item->add_meta_data('_painting_service', $painting['name']);
            if (isset($painting['color_filename'])) {
                $item->add_meta_data('_painting_color', $painting['color_filename']);
            }
        }
    }
    
    // Сохраняем данные калькулятора размеров
    if (isset($values['custom_dimensions'])) {
        $data = $values['custom_dimensions'];
        $item->add_meta_data('_calc_type', 'dimensions');
        $item->add_meta_data('_calc_width', $data['width']);
        $item->add_meta_data('_calc_length', $data['length']);
        
        if (isset($data['painting_service'])) {
            $painting = $data['painting_service'];
            $item->add_meta_data('_painting_service', $painting['name']);
            if (isset($painting['color_filename'])) {
                $item->add_meta_data('_painting_color', $painting['color_filename']);
            }
        }
    }
    
    // Калькулятор множителя
    if (isset($values['custom_multiplier_calc'])) {
        $data = $values['custom_multiplier_calc'];
        $item->add_meta_data('_calc_type', 'multiplier');
        $item->add_meta_data('_calc_width', $data['width']);
        $item->add_meta_data('_calc_length', $data['length']);
        $item->add_meta_data('_calc_area', $data['total_area']);
        
        // Сохраняем фаску если выбрана
        if (isset($values['selected_faska_type'])) {
            $item->add_meta_data('_faska_type', $values['selected_faska_type']);
        }
        
        if (isset($data['painting_service'])) {
            $painting = $data['painting_service'];
            $item->add_meta_data('_painting_service', $painting['name']);
            if (isset($painting['color_filename'])) {
                $item->add_meta_data('_painting_color', $painting['color_filename']);
            }
        }
    }
    
    // Калькулятор погонных метров
    if (isset($values['custom_running_meter_calc'])) {
        $data = $values['custom_running_meter_calc'];
        $item->add_meta_data('_calc_type', 'running_meter');
        
        if (isset($data['shape_label'])) {
            $item->add_meta_data('_falsebalk_shape', $data['shape_label']);
        }
        if (isset($data['width']) && $data['width'] > 0) {
            $item->add_meta_data('_calc_width', $data['width']);
        }
        if (isset($data['height']) && $data['height'] > 0) {
            $item->add_meta_data('_calc_height', $data['height']);
        }
        $item->add_meta_data('_calc_length', $data['length']);
        $item->add_meta_data('_calc_total_length', $data['total_length']);
        
        if (isset($data['painting_service'])) {
            $painting = $data['painting_service'];
            $item->add_meta_data('_painting_service', $painting['name']);
            if (isset($painting['color_filename'])) {
                $item->add_meta_data('_painting_color', $painting['color_filename']);
            }
        }
    }
    
    // Калькулятор квадратных метров
    if (isset($values['custom_square_meter_calc'])) {
        $data = $values['custom_square_meter_calc'];
        $item->add_meta_data('_calc_type', 'square_meter');
        $item->add_meta_data('_calc_width', $data['width']);
        $item->add_meta_data('_calc_length', $data['length']);
        $item->add_meta_data('_calc_area', $data['total_area']);
        
        if (isset($data['painting_service'])) {
            $painting = $data['painting_service'];
            $item->add_meta_data('_painting_service', $painting['name']);
            if (isset($painting['color_filename'])) {
                $item->add_meta_data('_painting_color', $painting['color_filename']);
            }
        }
    }
    
    // Схемы покраски
    if (isset($values['pm_selected_scheme_name'])) {
        $item->add_meta_data('_paint_scheme', $values['pm_selected_scheme_name']);
    }
    
    if (isset($values['pm_selected_color'])) {
        $item->add_meta_data('_paint_color', $values['pm_selected_color']);
    }
    
    if (isset($values['pm_selected_color_image'])) {
        $item->add_meta_data('_paint_color_image', $values['pm_selected_color_image'], true);
    }
}

/**
 * Отображение метаданных в админке заказов
 */
add_filter('woocommerce_order_item_display_meta_key', 'translate_order_item_meta_keys');
function translate_order_item_meta_keys($key) {
    $translations = array(
        '_calc_type' => 'Тип расчета',
        '_calc_area' => 'Площадь',
        '_calc_packs' => 'Количество упаковок',
        '_calc_width' => 'Ширина',
        '_calc_height' => 'Высота',
        '_calc_length' => 'Длина',
        '_calc_total_length' => 'Общая длина',
        '_falsebalk_shape' => 'Форма сечения',
        '_faska_type' => 'Тип фаски',
        '_painting_service' => 'Услуга покраски',
        '_painting_color' => 'Цвет покраски',
        '_paint_scheme' => 'Схема покраски',
        '_paint_color' => 'Цвет'
    );
    
    return isset($translations[$key]) ? $translations[$key] : $key;
}

/**
 * Форматирование значений метаданных в заказах
 */
add_filter('woocommerce_order_item_display_meta_value', 'format_order_item_meta_values', 10, 3);
function format_order_item_meta_values($value, $meta, $item) {
    
    switch ($meta->key) {
        case '_calc_type':
            $types = array(
                'area' => 'По площади',
                'dimensions' => 'По размерам',
                'multiplier' => 'С множителем',
                'running_meter' => 'Погонные метры',
                'square_meter' => 'Квадратные метры'
            );
            return isset($types[$value]) ? $types[$value] : $value;
            
        case '_calc_area':
            return number_format($value, 2, ',', ' ') . ' м²';
            
        case '_calc_width':
        case '_calc_height':
            return $value . ' мм';
            
        case '_calc_length':
        case '_calc_total_length':
            return number_format($value, 2, ',', ' ') . ' м';
            
        case '_paint_color_image':
            // Скрываем URL изображения
            return '';
    }
    
    return $value;
}

/**
 * Очистка корзины от устаревших данных при изменении количества
 */
add_action('woocommerce_after_cart_item_quantity_update', 'clear_custom_data_on_quantity_change', 10, 4);
function clear_custom_data_on_quantity_change($cart_item_key, $quantity, $old_quantity, $cart) {
    
    $cart_item = $cart->cart_contents[$cart_item_key];
    
    // Если есть кастомные расчеты - не даем менять количество
    if (isset($cart_item['custom_area_calc']) || 
        isset($cart_item['custom_dimensions']) ||
        isset($cart_item['custom_multiplier_calc']) ||
        isset($cart_item['custom_running_meter_calc']) ||
        isset($cart_item['custom_square_meter_calc'])) {
        
        // Возвращаем старое количество
        $cart->cart_contents[$cart_item_key]['quantity'] = $old_quantity;
        
        wc_add_notice(
            'Для изменения количества этого товара удалите его из корзины и добавьте снова с нужными параметрами', 
            'notice'
        );
    }
}