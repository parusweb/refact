<?php
/**
 * Модуль: Интеграция ACF
 * Описание: Настройка полей ACF и страниц опций
 */

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Регистрация полей для услуг покраски
 */
add_action('acf/init', 'register_painting_services_fields');
function register_painting_services_fields() {
    
    acf_add_local_field_group([
        'key' => 'group_painting_services',
        'title' => 'Услуги покраски',
        'fields' => [
            [
                'key' => 'field_painting_enabled',
                'label' => 'Включить услуги покраски',
                'name' => 'painting_services_enabled',
                'type' => 'true_false',
                'default_value' => 0
            ],
            [
                'key' => 'field_painting_schemes',
                'label' => 'Схемы покраски',
                'name' => 'painting_schemes',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => 'Добавить схему',
                'sub_fields' => [
                    [
                        'key' => 'field_scheme_name',
                        'label' => 'Название схемы',
                        'name' => 'scheme_name',
                        'type' => 'text',
                        'required' => 1
                    ],
                    [
                        'key' => 'field_scheme_price',
                        'label' => 'Цена',
                        'name' => 'scheme_price',
                        'type' => 'number',
                        'required' => 1,
                        'min' => 0
                    ],
                    [
                        'key' => 'field_scheme_colors',
                        'label' => 'Доступные цвета',
                        'name' => 'scheme_colors',
                        'type' => 'gallery'
                    ]
                ]
            ]
        ],
        'location' => [
            [
                [
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'parusweb-settings'
                ]
            ]
        ]
    ]);
}

/**
 * Вспомогательные функции для получения настроек ACF
 */
function get_shop_phone() {
    return get_field('shop_phone', 'option');
}

function get_shop_email() {
    return get_field('shop_email', 'option');
}

function get_shop_address() {
    return get_field('shop_address', 'option');
}

function get_minimum_order_amount() {
    $amount = get_field('minimum_order_amount', 'option');
    return $amount ? floatval($amount) : 0;
}

function get_free_shipping_amount() {
    $amount = get_field('free_shipping_amount', 'option');
    return $amount ? floatval($amount) : 0;
}

function get_painting_schemes() {
    if (!get_field('painting_services_enabled', 'option')) {
        return [];
    }
    
    return get_field('painting_schemes', 'option') ?: [];
}

/**
 * Проверка минимальной суммы заказа
 */
add_action('woocommerce_check_cart_items', 'check_minimum_order_amount');
function check_minimum_order_amount() {
    
    $minimum = get_minimum_order_amount();
    
    if ($minimum <= 0) {
        return;
    }
    
    $cart_total = WC()->cart->get_subtotal();
    
    if ($cart_total < $minimum) {
        wc_add_notice(
            sprintf(
                'Минимальная сумма заказа %s. Ваша текущая сумма: %s',
                wc_price($minimum),
                wc_price($cart_total)
            ),
            'error'
        );
    }
}