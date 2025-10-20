<?php
/**
 * Модуль: PM Paint Schemes
 * Описание: Вынесена логика получения схем покраски и подключения frontend-скрипта/обработки данных в корзине.
 * Файл: modules/pm-paint-schemes.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Очистка имени файла цвета
 * Оставляем префикс pm_ чтобы не ломать зависимости в других модулях
 */
if (!function_exists('pm_clean_color_filename')) {
    function pm_clean_color_filename($filename) {
        $filename = preg_replace('/\.(jpg|jpeg|png|webp|gif)$/i', '', $filename);
        $filename = preg_replace('/[-_](180|kopiya|copy|1)$/i', '', $filename);

        $patterns = [
            '/^img[_-]?(\d+)[-_].*$/' => '$1',
            '/^(\d+)[-_]\d+$/' => '$1',
            '/^[a-z]+[_-]?[a-z]*[_-]?(\d+)[-_]\d*$/' => '$1',
            '/^([a-z]+)_dlya_pokraski[_-](\d+)$/i' => '$1_$2',
            '/^([a-z]+[_-]\d+[a-z0-9]+)[-_]\d+$/' => '$1'
        ];

        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $filename)) {
                $filename = preg_replace($pattern, $replacement, $filename);
                break;
            }
        }

        $filename = preg_replace('/[-_]+/', '_', $filename);
        $filename = trim($filename, '-_');

        return $filename;
    }
}

/**
 * Получение схем покраски для товара (проверяет product-level, затем категории (child-first), затем parents)
 * Возвращает массив схем или пустой массив.
 */
if (!function_exists('pm_get_product_paint_schemes')) {
    function pm_get_product_paint_schemes($product_id) {
        // 1) product-level custom_schemes (ACF)
        if (function_exists('get_field')) {
            $schemes = get_field('custom_schemes', $product_id);
            if (!empty($schemes) && is_array($schemes)) {
                return $schemes;
            }
        }

        // 2) categories
        $terms = get_the_terms($product_id, 'product_cat');
        if (!$terms || is_wp_error($terms)) {
            return [];
        }

        // sort: children first (heuristic)
        usort($terms, function($a, $b) {
            if ($a->parent > 0 && $b->parent == 0) return -1;
            if ($b->parent > 0 && $a->parent == 0) return 1;
            return $b->term_id - $a->term_id;
        });

        // iterate categories: first direct category fields
        foreach ($terms as $term) {
            $term_key = 'product_cat_' . intval($term->term_id);

            if (function_exists('get_field')) {
                $schemes = get_field('schemes', $term_key);
                if (!empty($schemes) && is_array($schemes)) {
                    return $schemes;
                }

                $schemes = get_field('custom_schemes', $term_key);
                if (!empty($schemes) && is_array($schemes)) {
                    return $schemes;
                }
            }
        }

        // 3) check parent categories
        foreach ($terms as $term) {
            $parent_id = $term->parent;
            while ($parent_id) {
                $parent_term = get_term($parent_id, 'product_cat');
                if (!$parent_term || is_wp_error($parent_term)) break;

                if (function_exists('get_field')) {
                    $schemes = get_field('schemes', 'product_cat_' . intval($parent_id));
                    if (!empty($schemes) && is_array($schemes)) {
                        return $schemes;
                    }

                    $schemes = get_field('custom_schemes', 'product_cat_' . intval($parent_id));
                    if (!empty($schemes) && is_array($schemes)) {
                        return $schemes;
                    }
                }

                $parent_id = $parent_term->parent;
            }
        }

        return [];
    }
}

/**
 * Проверяет, нужно ли показывать блок покраски для товара.
 */
if (!function_exists('pm_can_show_paint_schemes')) {
    function pm_can_show_paint_schemes($product_id) {
        if (function_exists('is_in_painting_categories')) {
            return is_in_painting_categories($product_id);
        } elseif (function_exists('is_in_target_categories')) {
            return is_in_target_categories($product_id);
        } else {
            $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
            if (is_wp_error($product_categories) || empty($product_categories)) return false;

            $target_categories = array_merge(range(87, 93), [190, 191, 127, 94], range(265, 271));
            foreach ($product_categories as $cat_id) {
                if (in_array(intval($cat_id), $target_categories, true)) {
                    return true;
                }
                foreach ($target_categories as $target_cat_id) {
                    if (function_exists('cat_is_ancestor_of') && cat_is_ancestor_of($target_cat_id, $cat_id)) {
                        return true;
                    }
                }
            }
            return false;
        }
    }
}

/**
 * Регистрация скрипта/стиля и передача данных схем в JS
 */
add_action('wp_enqueue_scripts', function() {
    if (!is_product()) return;

    global $product;
    if (empty($product) || !is_object($product)) return;

    $product_id = $product->get_id();
    if (!pm_can_show_paint_schemes($product_id)) return;

    $schemes = pm_get_product_paint_schemes($product_id);
    if (!is_array($schemes) || empty($schemes)) return;

    wp_register_script(
        'parusweb-pm-paint-schemes',
        PARUSWEB_PLUGIN_URL . 'assets/js/pm-paint-schemes.js',
        ['jquery'],
        PARUSWEB_VERSION,
        true
    );

    $safe_schemes = [];
    foreach ($schemes as $s) {
        if (!is_array($s)) continue;
        $safe = [
            'scheme_name' => isset($s['scheme_name']) ? wp_kses_post($s['scheme_name']) : '',
            'scheme_slug' => isset($s['scheme_slug']) ? sanitize_title($s['scheme_slug']) : '',
            'scheme_colors' => []
        ];
        if (!empty($s['scheme_colors']) && is_array($s['scheme_colors'])) {
            foreach ($s['scheme_colors'] as $c) {
                if (!is_array($c)) continue;
                $safe['scheme_colors'][] = [
                    'url' => isset($c['url']) ? esc_url_raw($c['url']) : '',
                    'label' => isset($c['label']) ? sanitize_text_field($c['label']) : ''
                ];
            }
        }
        $safe_schemes[] = $safe;
    }

    wp_localize_script('parusweb-pm-paint-schemes', 'paruswebPmPaintSchemes', [
        'schemes' => $safe_schemes,
        'ajax_url' => admin_url('admin-ajax.php'),
        'debug' => false
    ]);

    wp_enqueue_script('parusweb-pm-paint-schemes');
});

/**
 * Добавление данных в товар в корзине (валидация/санитизация)
 */
add_filter('woocommerce_add_cart_item_data', 'pm_add_paint_data_to_cart', 10, 3);
function pm_add_paint_data_to_cart($cart_item_data, $product_id, $variation_id) {
    if (!empty($_POST['pm_selected_color_filename'])) {
        $cleaned_filename = pm_clean_color_filename(sanitize_text_field(wp_unslash($_POST['pm_selected_color_filename'])));
        $cart_item_data['pm_selected_color'] = $cleaned_filename;
        $cart_item_data['pm_selected_color_filename'] = $cleaned_filename;
    } elseif (!empty($_POST['pm_selected_color'])) {
        $color_value = sanitize_text_field(wp_unslash($_POST['pm_selected_color']));
        if (strpos($color_value, ' — ') !== false) {
            $parts = explode(' — ', $color_value);
            $cleaned_filename = pm_clean_color_filename(end($parts));
        } else {
            $cleaned_filename = pm_clean_color_filename($color_value);
        }
        $cart_item_data['pm_selected_color'] = $cleaned_filename;
        $cart_item_data['pm_selected_color_filename'] = $cleaned_filename;
    }

    if (!empty($_POST['pm_selected_scheme_name'])) {
        $cart_item_data['pm_selected_scheme_name'] = sanitize_text_field(wp_unslash($_POST['pm_selected_scheme_name']));
    }

    if (!empty($_POST['pm_selected_scheme_slug'])) {
        $cart_item_data['pm_selected_scheme_slug'] = sanitize_title(wp_unslash($_POST['pm_selected_scheme_slug']));
    }

    if (!empty($_POST['pm_selected_color_image'])) {
        $image_url = esc_url_raw(wp_unslash($_POST['pm_selected_color_image']));
        $cart_item_data['pm_selected_color_image'] = $image_url;
    }

    return $cart_item_data;
}

/**
 * Сохранение в заказ (checkout)
 */
add_action('woocommerce_checkout_create_order_line_item', 'pm_add_paint_data_to_order', 10, 4);
function pm_add_paint_data_to_order($item, $cart_item_key, $values, $order) {
    if (!empty($values['pm_selected_scheme_name'])) {
        $scheme_display = sanitize_text_field($values['pm_selected_scheme_name']);
        if (!empty($values['pm_selected_color'])) {
            $scheme_display .= ' — ' . sanitize_text_field($values['pm_selected_color']);
        }
        $item->add_meta_data('Схема покраски', $scheme_display, true);
    }

    if (!empty($values['pm_selected_color_image'])) {
        $item->add_meta_data('_pm_color_image_url', esc_url_raw($values['pm_selected_color_image']), true);
    }

    if (!empty($values['pm_selected_color'])) {
        $item->add_meta_data('Код цвета', sanitize_text_field($values['pm_selected_color']), true);
    }
}

/**
 * Отображение мини-изображения в админке/емейлах
 */
add_filter('woocommerce_order_item_display_meta_key', 'pm_rename_color_meta_key', 10, 3);
function pm_rename_color_meta_key($display_key, $meta, $item) {
    if ($meta->key === '_pm_color_image_url') {
        return 'Образец цвета';
    }
    return $display_key;
}

add_filter('woocommerce_order_item_display_meta_value', 'pm_display_color_image_in_order', 10, 3);
function pm_display_color_image_in_order($display_value, $meta, $item) {
    if ($meta->key === '_pm_color_image_url') {
        $image_url = esc_url($meta->value);
        return '<img src="' . esc_url($image_url) . '" style="width:60px; height:60px; object-fit:cover; border:2px solid #ddd; border-radius:4px; display:block; margin-top:5px;">';
    }
    return $display_value;
}