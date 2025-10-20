<?php
/**
 * Модуль: Обработка заказов
 * Описание: Создание и обработка заказов с кастомными данными
 * Зависимости: cart-functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Добавление кастомных колонок в список заказов
 */
add_filter('manage_edit-shop_order_columns', 'add_order_custom_columns');
function add_order_custom_columns($columns) {
    $new_columns = [];
    
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        if ($key === 'order_total') {
            $new_columns['custom_area'] = 'Общая площадь';
        }
    }
    
    return $new_columns;
}

/**
 * Вывод данных в кастомных колонках заказов
 */
add_action('manage_shop_order_posts_custom_column', 'show_order_custom_columns', 10, 2);
function show_order_custom_columns($column, $post_id) {
    if ($column === 'custom_area') {
        $order = wc_get_order($post_id);
        
        if (!$order) {
            echo '—';
            return;
        }
        
        $total_area = 0;
        
        foreach ($order->get_items() as $item) {
            $area_data = $item->get_meta('custom_area_calc');
            
            if ($area_data && isset($area_data['area_m2'])) {
                $total_area += floatval($area_data['area_m2']) * $item->get_quantity();
            }
        }
        
        if ($total_area > 0) {
            echo '<strong>' . number_format($total_area, 2, ',', ' ') . ' м²</strong>';
        } else {
            echo '—';
        }
    }
}

/**
 * Добавление метабокса с дополнительной информацией о заказе
 */
add_action('add_meta_boxes', 'add_order_info_metabox');
function add_order_info_metabox() {
    add_meta_box(
        'order_custom_info',
        'Дополнительная информация',
        'render_order_info_metabox',
        'shop_order',
        'side',
        'default'
    );
    
    add_meta_box(
        'order_custom_info',
        'Дополнительная информация',
        'render_order_info_metabox',
        'woocommerce_page_wc-orders',
        'side',
        'default'
    );
}

/**
 * Отрисовка метабокса с информацией о заказе
 */
function render_order_info_metabox($post_or_order) {
    $order = $post_or_order instanceof WP_Post ? wc_get_order($post_or_order->ID) : $post_or_order;
    
    if (!$order) {
        echo '<p>Информация недоступна</p>';
        return;
    }
    
    $total_area = 0;
    $total_length = 0;
    $has_custom_calc = false;
    
    foreach ($order->get_items() as $item) {
        $area_data = $item->get_meta('custom_area_calc');
        if ($area_data && isset($area_data['area_m2'])) {
            $total_area += floatval($area_data['area_m2']) * $item->get_quantity();
            $has_custom_calc = true;
        }
        
        $length_data = $item->get_meta('custom_running_meter_calc');
        if ($length_data && isset($length_data['length'])) {
            $total_length += floatval($length_data['length']) * $item->get_quantity();
            $has_custom_calc = true;
        }
    }
    
    if (!$has_custom_calc) {
        echo '<p>Нет дополнительных данных</p>';
        return;
    }
    
    echo '<div style="padding: 10px 0;">';
    
    if ($total_area > 0) {
        echo '<p><strong>Общая площадь:</strong><br>';
        echo '<span style="font-size: 16px; color: #2271b1;">' . number_format($total_area, 2, ',', ' ') . ' м²</span></p>';
    }
    
    if ($total_length > 0) {
        echo '<p><strong>Общая длина:</strong><br>';
        echo '<span style="font-size: 16px; color: #2271b1;">' . number_format($total_length, 2, ',', ' ') . ' м.п.</span></p>';
    }
    
    echo '</div>';
}

/**
 * Добавление информации о расчетах в email заказа
 */
add_action('woocommerce_email_order_details', 'add_custom_data_to_email', 20, 4);
function add_custom_data_to_email($order, $sent_to_admin, $plain_text, $email) {
    
    $total_area = 0;
    $total_length = 0;
    $has_data = false;
    
    foreach ($order->get_items() as $item) {
        $area_data = $item->get_meta('custom_area_calc');
        if ($area_data && isset($area_data['area_m2'])) {
            $total_area += floatval($area_data['area_m2']) * $item->get_quantity();
            $has_data = true;
        }
        
        $length_data = $item->get_meta('custom_running_meter_calc');
        if ($length_data && isset($length_data['length'])) {
            $total_length += floatval($length_data['length']) * $item->get_quantity();
            $has_data = true;
        }
    }
    
    if (!$has_data) {
        return;
    }
    
    if ($plain_text) {
        echo "\n\n";
        echo "ДОПОЛНИТЕЛЬНАЯ ИНФОРМАЦИЯ\n";
        echo "==========================\n";
        
        if ($total_area > 0) {
            echo "Общая площадь: " . number_format($total_area, 2, ',', ' ') . " м²\n";
        }
        
        if ($total_length > 0) {
            echo "Общая длина: " . number_format($total_length, 2, ',', ' ') . " м.п.\n";
        }
    } else {
        echo '<h2>Дополнительная информация</h2>';
        echo '<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee;">';
        
        if ($total_area > 0) {
            echo '<tr>';
            echo '<th style="text-align: left; border: 1px solid #eee;">Общая площадь:</th>';
            echo '<td style="text-align: left; border: 1px solid #eee;">' . number_format($total_area, 2, ',', ' ') . ' м²</td>';
            echo '</tr>';
        }
        
        if ($total_length > 0) {
            echo '<tr>';
            echo '<th style="text-align: left; border: 1px solid #eee;">Общая длина:</th>';
            echo '<td style="text-align: left; border: 1px solid #eee;">' . number_format($total_length, 2, ',', ' ') . ' м.п.</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }
}

/**
 * Валидация минимальной суммы заказа при оформлении
 */
add_action('woocommerce_after_checkout_validation', 'validate_minimum_order_total', 10, 2);
function validate_minimum_order_total($data, $errors) {
    
    if (function_exists('get_minimum_order_amount')) {
        $minimum = get_minimum_order_amount();
        
        if ($minimum > 0) {
            $cart_total = WC()->cart->get_subtotal();
            
            if ($cart_total < $minimum) {
                $errors->add('minimum_order', sprintf(
                    'Минимальная сумма заказа %s. Ваша текущая сумма: %s',
                    wc_price($minimum),
                    wc_price($cart_total)
                ));
            }
        }
    }
}

/**
 * Сохранение дополнительных данных заказа
 */
add_action('woocommerce_checkout_order_processed', 'save_order_custom_data', 10, 3);
function save_order_custom_data($order_id, $posted_data, $order) {
    
    $total_area = 0;
    $total_length = 0;
    
    foreach ($order->get_items() as $item) {
        $area_data = $item->get_meta('custom_area_calc');
        if ($area_data && isset($area_data['area_m2'])) {
            $total_area += floatval($area_data['area_m2']) * $item->get_quantity();
        }
        
        $length_data = $item->get_meta('custom_running_meter_calc');
        if ($length_data && isset($length_data['length'])) {
            $total_length += floatval($length_data['length']) * $item->get_quantity();
        }
    }
    
    if ($total_area > 0) {
        $order->update_meta_data('_total_area', $total_area);
    }
    
    if ($total_length > 0) {
        $order->update_meta_data('_total_length', $total_length);
    }
    
    $order->save();
}

/**
 * Добавление заметки к заказу при создании
 */
add_action('woocommerce_new_order', 'add_order_note_with_calculations');
function add_order_note_with_calculations($order_id) {
    
    $order = wc_get_order($order_id);
    
    if (!$order) {
        return;
    }
    
    $notes = [];
    
    foreach ($order->get_items() as $item) {
        $product_name = $item->get_name();
        
        $area_data = $item->get_meta('custom_area_calc');
        if ($area_data && isset($area_data['area_m2'])) {
            $notes[] = sprintf(
                '%s: площадь %.2f м²',
                $product_name,
                $area_data['area_m2']
            );
            
            if (isset($area_data['width']) && $area_data['width'] > 0) {
                $notes[count($notes) - 1] .= sprintf(' (%.2f × %.2f м)', $area_data['width'], $area_data['length']);
            }
        }
        
        $length_data = $item->get_meta('custom_running_meter_calc');
        if ($length_data && isset($length_data['length'])) {
            $notes[] = sprintf(
                '%s: длина %.2f м.п.',
                $product_name,
                $length_data['length']
            );
        }
    }
    
    if (!empty($notes)) {
        $order->add_order_note(
            'Дополнительная информация о заказе:' . "\n" . implode("\n", $notes)
        );
    }
}

/**
 * Форматирование мета-данных в заказе
 */
add_filter('woocommerce_order_item_display_meta_key', 'format_order_meta_key', 10, 3);
function format_order_meta_key($display_key, $meta, $item) {
    
    $custom_keys = [
        'Схема покраски' => 'Схема покраски',
        'Цвет' => 'Цвет',
        'Размеры' => 'Размеры',
        'Площадь' => 'Площадь',
        'Длина' => 'Длина',
        'Цена за м²' => 'Цена за м²',
        'Цена за м.п.' => 'Цена за м.п.'
    ];
    
    if (isset($custom_keys[$display_key])) {
        return $custom_keys[$display_key];
    }
    
    return $display_key;
}

/**
 * Форматирование значений мета-данных в заказе
 */
add_filter('woocommerce_order_item_display_meta_value', 'format_order_meta_value', 10, 3);
function format_order_meta_value($display_value, $meta, $item) {
    return $display_value;
}