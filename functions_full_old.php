<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

include get_stylesheet_directory() . '/inc/briks-loader.php';
function add_webp_support($mimes) {
    $mimes['webp'] = 'image/webp';
    return $mimes;
}
add_filter('mime_types', 'add_webp_support');

//=============================== 
// parusweb
// ===============================

// Функция для извлечения площади из названия товара с учётом количества штук в упаковке
function extract_area_with_qty($title, $product_id = null) {
    $t = mb_strtolower($title, 'UTF-8');
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace("\xC2\xA0", ' ', $t);

    $patterns = [
        '/\(?\s*(\d+(?:[.,-]\d+)?)\s*[мm](?:2|²)\b/u',
        '/\((\d+(?:[.,-]\d+)?)\s*[мm](?:2|²)\s*\/\s*\d+\s*(?:лист|упак|шт)\)/u',
        '/(\d+(?:[.,-]\d+)?)\s*[мm](?:2|²)\s*\/\s*(?:упак|лист|шт)\b/u',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $t, $m)) {
            $num = str_replace([',','-'], '.', $m[1]);
            return (float) $num;
        }
    }

    // паттерн для размеров в формате ширина*длина*высота с указанием упаковки
    if (preg_match('/(\d+)\*(\d+)\*(\d+).*?(\d+)\s*штуп/u', $t, $m)) {
        $width_mm = intval($m[1]);
        $length_mm = intval($m[2]);
        $height_mm = intval($m[3]);
        $qty = intval($m[4]);
        
        // Находим два наибольших размера (ширина и длина, исключаем толщину)
        $sizes = [$width_mm, $length_mm, $height_mm];
        rsort($sizes);
        $width = $sizes[0];
        $length = $sizes[1];
        
        if ($width > 0 && $length > 0) {
            $area_m2 = ($width / 1000) * ($length / 1000) * $qty;
            return round($area_m2, 3);
        }
    }

    if (preg_match('/(\d+)\s*шт\s*\/\s*уп|(\d+)\s*штуп/u', $t, $m)) {
        $qty = !empty($m[1]) ? intval($m[1]) : intval($m[2] ?? 1);
        if (preg_match_all('/(\d{2,4})[xх\/](\d{2,4})[xх\/](\d{2,4})/u', $t, $rows)) {
            $nums = array_map('intval', [$rows[1][0], $rows[2][0], $rows[3][0]]);
            rsort($nums);
            $width_mm  = $nums[0];
            $length_mm = $nums[1];
            if ($width_mm > 0 && $length_mm > 0) {
                $area_m2 = ($width_mm / 1000) * ($length_mm / 1000) * $qty;
                return round($area_m2, 3);
            }
        }
    }

    if (preg_match_all('/(\d{2,4})[xх\/](\d{2,4})[xх\/](\d{2,4})/u', $t, $rows)) {
        $nums = array_map('intval', [$rows[1][0], $rows[2][0], $rows[3][0]]);
        rsort($nums);
        $width_mm  = $nums[0];
        $length_mm = $nums[1];
        if ($width_mm > 0 && $length_mm > 0) {
            $area_m2 = ($width_mm / 1000) * ($length_mm / 1000);
            return round($area_m2, 3);
        }
    }

    // Если площадь не найдена в названии, проверяем атрибуты товара
    if ($product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            // Проверяем атрибуты длины и ширины
            $width = $product->get_attribute('pa_shirina') ?: $product->get_attribute('shirina');
            $length = $product->get_attribute('pa_dlina') ?: $product->get_attribute('dlina');
            
            if ($width && $length) {
                // Извлекаем числовые значения из атрибутов
                preg_match('/(\d+)/', $width, $width_match);
                preg_match('/(\d+)/', $length, $length_match);
                
                if ($width_match[1] && $length_match[1]) {
                    $width_mm = intval($width_match[1]);
                    $length_mm = intval($length_match[1]);
                    $area_m2 = ($width_mm / 1000) * ($length_mm / 1000);
                    return round($area_m2, 3);
                }
            }
        }
    }

    return null;
}

// Функция получения множителя для товара или категории
function get_price_multiplier($product_id) {
    // Сначала проверяем множитель товара
    $product_multiplier = get_post_meta($product_id, '_price_multiplier', true);
    if (!empty($product_multiplier) && is_numeric($product_multiplier)) {
        return floatval($product_multiplier);
    }
    
    // Если нет множителя у товара, проверяем категории
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    if (!is_wp_error($product_categories) && !empty($product_categories)) {
        foreach ($product_categories as $cat_id) {
            $cat_multiplier = get_term_meta($cat_id, 'category_price_multiplier', true);
            if (!empty($cat_multiplier) && is_numeric($cat_multiplier)) {
                return floatval($cat_multiplier);
            }
        }
    }
    
    return 1.0; // По умолчанию множитель = 1
}

// Добавляем поля множителя и размеров в товар
add_action('woocommerce_product_options_pricing', function() {
    echo '<div class="options_group">';
    
    woocommerce_wp_text_input([
        'id' => '_price_multiplier',
        'label' => 'Множитель цены',
        'desc_tip' => true,
        'description' => 'Множитель для расчета итоговой цены (например, 1.5). Если не задан, используется множитель категории.',
        'type' => 'number',
        'custom_attributes' => [
            'step' => '0.01',
            'min' => '0'
        ]
    ]);
    
    echo '</div>';
    echo '<div class="options_group show_if_simple show_if_variable">';
    echo '<h4 style="padding-left: 12px;">Настройки калькулятора размеров (для столярки)</h4>';
    
    woocommerce_wp_text_input([
        'id' => '_calc_width_min',
        'label' => 'Ширина мин. (мм)',
        'type' => 'number',
        'custom_attributes' => ['step' => '1', 'min' => '0']
    ]);
    
    woocommerce_wp_text_input([
        'id' => '_calc_width_max',
        'label' => 'Ширина макс. (мм)',
        'type' => 'number',
        'custom_attributes' => ['step' => '1', 'min' => '0']
    ]);
    
    woocommerce_wp_text_input([
        'id' => '_calc_width_step',
        'label' => 'Шаг ширины (мм)',
        'placeholder' => '100',
        'type' => 'number',
        'custom_attributes' => ['step' => '1', 'min' => '1']
    ]);
    
    woocommerce_wp_text_input([
        'id' => '_calc_length_min',
        'label' => 'Длина мин. (м)',
        'type' => 'number',
        'custom_attributes' => ['step' => '0.01', 'min' => '0.01']
    ]);
    
    woocommerce_wp_text_input([
        'id' => '_calc_length_max',
        'label' => 'Длина макс. (м)',
        'type' => 'number',
        'custom_attributes' => ['step' => '0.01', 'min' => '0.01']
    ]);
    
    woocommerce_wp_text_input([
        'id' => '_calc_length_step',
        'label' => 'Шаг длины (м)',
        'placeholder' => '0.01',
        'type' => 'number',
        'custom_attributes' => ['step' => '0.01', 'min' => '0.01']
    ]);
    
    echo '</div>';
});

add_action('woocommerce_process_product_meta', function($post_id) {
    $multiplier = isset($_POST['_price_multiplier']) ? sanitize_text_field($_POST['_price_multiplier']) : '';
    update_post_meta($post_id, '_price_multiplier', $multiplier);
    
    // Сохраняем настройки калькулятора
    $calc_fields = [
        '_calc_width_min', '_calc_width_max', '_calc_width_step',
        '_calc_length_min', '_calc_length_max', '_calc_length_step'
    ];
    
    foreach ($calc_fields as $field) {
        $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
        update_post_meta($post_id, $field, $value);
    }
});

// Добавляем поле множителя в категорию
add_action('product_cat_add_form_fields', function() {
    ?>
    <div class="form-field">
        <label>Множитель цены для категории</label>
        <input type="number" name="category_price_multiplier" step="0.01" min="0" value="">
        <p class="description">Множитель для расчета итоговой цены товаров этой категории (например, 1.5)</p>
    </div>
    <?php
});

add_action('product_cat_edit_form_fields', function($term) {
    $multiplier = get_term_meta($term->term_id, 'category_price_multiplier', true);
    ?>
    <tr class="form-field">
        <th scope="row"><label>Множитель цены для категории</label></th>
        <td>
            <input type="number" name="category_price_multiplier" step="0.01" min="0" value="<?php echo esc_attr($multiplier); ?>">
            <p class="description">Множитель для расчета итоговой цены товаров этой категории</p>
        </td>
    </tr>
    <?php
});

add_action('created_product_cat', function($term_id) {
    if (isset($_POST['category_price_multiplier'])) {
        update_term_meta($term_id, 'category_price_multiplier', sanitize_text_field($_POST['category_price_multiplier']));
    }
});

add_action('edited_product_cat', function($term_id) {
    if (isset($_POST['category_price_multiplier'])) {
        update_term_meta($term_id, 'category_price_multiplier', sanitize_text_field($_POST['category_price_multiplier']));
    }
});

// --- ОБНОВЛЕННЫЙ фильтр для цены ---
add_filter('woocommerce_get_price_html', function($price, $product) {
    $product_id = $product->get_id();
    
    // Категории для скрытия базовой цены (265-271)
    $hide_base_price_categories = range(265, 271);
    $should_hide_base_price = has_term($hide_base_price_categories, 'product_cat', $product_id);
    
    // Категории для "лист"
    $leaf_parent_id = 190;
    $leaf_children = [191, 127, 94];
    $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);

    // Категории для пиломатериалов
    $lumber_categories = range(87, 93);

    $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
    $is_lumber_category = has_term($lumber_categories, 'product_cat', $product_id);
    $is_square_meter = is_square_meter_category($product_id);
    $is_running_meter = is_running_meter_category($product_id);

    if ($is_leaf_category) {
        $price = str_replace('упак.', 'лист', $price);
    }
    
    
    $price_multiplier = get_price_multiplier($product->get_id());
    // Для столярных изделий за пог.м
    if ($is_running_meter) {
        $base_price_per_m = floatval($product->get_regular_price() ?: $product->get_price());
        if ($base_price_per_m) {
            // Получаем минимальные размеры
            $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true)) ?: 1;
            $min_length = round($min_length, 2);
            $min_price = $base_price_per_m * $min_length * $price_multiplier;
            
            if (is_product()) {
                // Если нужно скрыть базовую цену - показываем только цену за шт.
                if ($should_hide_base_price) {
                    return '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (' . $min_length . ' м)</span>';
                }
                
                return wc_price($base_price_per_m) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за пог. м</span><br>' .
                       '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (' . $min_length . ' м)</span>';
            } else {
                // Если нужно скрыть базовую цену - показываем только цену за шт.
                if ($should_hide_base_price) {
                    return '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
                }
                
                return wc_price($base_price_per_m) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за пог. м</span><br>' .
                       '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
            }
        }
    }

// Для столярных изделий за кв.м
    if ($is_square_meter) {
        $base_price_per_m2 = floatval($product->get_regular_price() ?: $product->get_price());
        if ($base_price_per_m2) {
            // Получаем минимальные размеры
            $is_falshbalka = has_term(266, 'product_cat', $product_id);
            if ($is_falshbalka) {
                // Для фальшбалок (Г-образная форма): 70x70мм по умолчанию, 2 плоскости
                $min_width = floatval(get_post_meta($product_id, '_calc_width_min', true)) ?: 70;
                $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true)) ?: 1;
                $min_length = round($min_length, 2);
                // Площадь Г-образной формы: 2 плоскости по 70мм каждая
                $min_area = 2 * ($min_width / 1000) * $min_length;
            } else {
                $min_width = floatval(get_post_meta($product_id, '_calc_width_min', true)) ?: 100;
                $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true)) ?: 0.01;
                $min_length = round($min_length, 2);
                $min_area = ($min_width / 1000) * $min_length;
            }
            $min_price = $base_price_per_m2 * $min_area * $price_multiplier;
            
            if (is_product()) {
                // Если нужно скрыть базовую цену - показываем только цену за шт.
                if ($should_hide_base_price) {
                    return '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (' . number_format($min_area, 2) . ' м²)</span>';
                }
                
                return wc_price($base_price_per_m2) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                       '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (' . number_format($min_area, 2) . ' м²)</span>';
            } else {
                // Если нужно скрыть базовую цену - показываем только цену за шт.
                if ($should_hide_base_price) {
                    return '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
                }
                
                return wc_price($base_price_per_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                       '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
            }
        }
    }

    // Для пиломатериалов и листовых (категории 87-93 + листовые)
    if (($is_lumber_category || $is_leaf_category) && is_in_target_categories($product_id)) {
        $base_price_per_m2 = floatval($product->get_regular_price() ?: $product->get_price());
        $pack_area = extract_area_with_qty($product->get_name(), $product_id);
        
        if ($base_price_per_m2) {
            if (is_product() && $pack_area) {
                $price_per_pack = $base_price_per_m2 * $pack_area;
                $unit_text = $is_leaf_category ? 'лист' : 'упаковку';
                
                return wc_price($base_price_per_m2) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                       '<span style="font-size:1.3em;"><strong>' . wc_price($price_per_pack) . '</strong> за 1 ' . $unit_text . '</span>';
            } elseif ($pack_area) {
                $price_per_pack = $base_price_per_m2 * $pack_area;
                $unit_text = $is_leaf_category ? 'лист' : 'упаковку';
                
                return wc_price($base_price_per_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                       '<span style="font-size:1.1em;"><strong>' . wc_price($price_per_pack) . '</strong> за ' . $unit_text . '</span>';
            } else {
                $price .= '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span>';
            }
        }
    }

    return $price;
}, 20, 2);


// --- Проверка категорий с расчетом за кв.м (столярные изделия) ---
function is_square_meter_category($product_id) {
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    if (is_wp_error($product_categories) || empty($product_categories)) return false;
    
    $target_categories = [266, 270, 268];
    
    foreach ($product_categories as $cat_id) {
        if (in_array($cat_id, $target_categories)) return true;
        foreach ($target_categories as $target_cat_id) {
            if (cat_is_ancestor_of($target_cat_id, $cat_id)) return true;
        }
    }
    return false;
}

// --- Проверка категорий с расчетом за пог.м (столярные изделия) ---
function is_running_meter_category($product_id) {
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    if (is_wp_error($product_categories) || empty($product_categories)) return false;
    
    $target_categories = [267, 271];
    
    foreach ($product_categories as $cat_id) {
        if (in_array($cat_id, $target_categories)) return true;
        foreach ($target_categories as $target_cat_id) {
            if (cat_is_ancestor_of($target_cat_id, $cat_id)) return true;
        }
    }
    return false;
}

// --- Проверка категорий для покраски ---
function is_in_painting_categories($product_id) {
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    if (is_wp_error($product_categories) || empty($product_categories)) return false;
    
    $target_categories = array_merge(
        range(87, 93),
        [190, 191, 127, 94],
        range(265, 271)
    );
    
    foreach ($product_categories as $cat_id) {
        if (in_array($cat_id, $target_categories)) return true;
        foreach ($target_categories as $target_cat_id) {
            if (cat_is_ancestor_of($target_cat_id, $cat_id)) return true;
        }
    }
    return false;
}


// --- Проверка категорий ---
function is_in_target_categories($product_id) {
    return is_in_painting_categories($product_id);
}

// --- Проверка категорий 265-268 ---
function is_in_multiplier_categories($product_id) {
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
    if (is_wp_error($product_categories) || empty($product_categories)) return false;
    $target_categories = [265, 266, 267, 268, 270, 271];
    foreach ($product_categories as $cat_id) {
        if (in_array($cat_id, $target_categories)) return true;
        foreach ($target_categories as $target_cat_id) {
            if (cat_is_ancestor_of($target_cat_id, $cat_id)) return true;
        }
    }
    return false;
}

// --- Извлечение размеров ---
function extract_dimensions_from_title($title) {
    if (preg_match('/\d+\/(\d+)(?:\((\d+)\))?\/(\d+)-(\d+)/u', $title, $m)) {
        $widths = [$m[1]];
        if (!empty($m[2])) $widths[] = $m[2];
        $length_min = (int)$m[3];
        $length_max = (int)$m[4];
        return ['widths'=>$widths, 'length_min'=>$length_min, 'length_max'=>$length_max];
    }
    return null;
}

// --- Калькулятор площади и размеров ---
add_action('wp_footer', function () {
    if (!is_product()) return;
    
    global $product;
    $product_id = $product->get_id();


    // ОТЛАДКА: Базовая информация
    error_log('=== FALSEBALK DEBUG START ===');
    error_log('Product ID: ' . $product_id);
    error_log('Product Name: ' . $product->get_name());
    
    // Получаем все категории товара
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'all']);
    error_log('Product categories: ' . print_r(wp_list_pluck($product_categories, 'name', 'term_id'), true));
    
    $is_target = is_in_target_categories($product->get_id());
    $is_multiplier = is_in_multiplier_categories($product->get_id());
    $is_square_meter = is_square_meter_category($product->get_id());
    $is_running_meter = is_running_meter_category($product->get_id());
    
    error_log('Is target: ' . ($is_target ? 'YES' : 'NO'));
    error_log('Is multiplier: ' . ($is_multiplier ? 'YES' : 'NO'));
    error_log('Is square meter: ' . ($is_square_meter ? 'YES' : 'NO'));
    error_log('Is running meter: ' . ($is_running_meter ? 'YES' : 'NO'));
    
    // ВАЖНО: Проверяем фальшбалки ДО использования переменной в условиях
    $show_falsebalk_calc = false;
    $is_falsebalk = false;
    $shapes_data = array();
    
    if ($is_square_meter) {
        error_log('Checking for falsebalk category (266)...');
        
        // Функция для проверки категории с учетом иерархии
        if (!function_exists('product_in_category')) {
            function product_in_category($product_id, $category_id) {
                $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
                if (is_wp_error($terms) || empty($terms)) {
                    return false;
                }
                if (in_array($category_id, $terms)) {
                    return true;
                }
                foreach ($terms as $term_id) {
                    if (term_is_ancestor_of($category_id, $term_id, 'product_cat')) {
                        return true;
                    }
                }
                return false;
            }
        }
        
        $is_falsebalk = product_in_category($product->get_id(), 266);
        error_log('Is falsebalk (category 266): ' . ($is_falsebalk ? 'YES' : 'NO'));
        
        if ($is_falsebalk) {
            $shapes_data = get_post_meta($product->get_id(), '_falsebalk_shapes_data', true);
            error_log('Shapes data retrieved: ' . ($shapes_data ? 'YES' : 'NO'));
            error_log('Shapes data content: ' . print_r($shapes_data, true));
            
            if (is_array($shapes_data)) {
                foreach ($shapes_data as $shape_key => $shape_info) {
                    error_log("Checking shape: {$shape_key}");
                    error_log("Shape info: " . print_r($shape_info, true));
                    
                    if (is_array($shape_info)) {
                        $enabled = !empty($shape_info['enabled']);
                        error_log("Shape {$shape_key} enabled: " . ($enabled ? 'YES' : 'NO'));
                        
                        if ($enabled) {
                            // Проверяем новый формат (min/max/step)
                            $has_width = !empty($shape_info['width_min']) || !empty($shape_info['width_max']);
                            error_log("Has width: " . ($has_width ? 'YES' : 'NO'));
                            
                            // Проверяем высоту в зависимости от формы
                            $has_height = false;
                            if ($shape_key === 'p') {
                                $has_height = !empty($shape_info['height1_min']) || !empty($shape_info['height1_max']) ||
                                             !empty($shape_info['height2_min']) || !empty($shape_info['height2_max']);
                                error_log("Has height (P-shape, two heights): " . ($has_height ? 'YES' : 'NO'));
                            } else {
                                $has_height = !empty($shape_info['height_min']) || !empty($shape_info['height_max']);
                                error_log("Has height (G/O-shape, one height): " . ($has_height ? 'YES' : 'NO'));
                            }
                            
                            $has_length = !empty($shape_info['length_min']) || !empty($shape_info['length_max']);
                            error_log("Has length: " . ($has_length ? 'YES' : 'NO'));
                            
                            // Также поддерживаем старый формат
                            $has_old_format = !empty($shape_info['widths']) || 
                                             !empty($shape_info['heights']) || 
                                             !empty($shape_info['lengths']);
                            error_log("Has old format: " . ($has_old_format ? 'YES' : 'NO'));
                            
                            if ($has_width || $has_height || $has_length || $has_old_format) {
                                $show_falsebalk_calc = true;
                                error_log("✓ Falsebalk calculator ENABLED for shape: {$shape_key}");
                                break;
                            } else {
                                error_log("✗ Shape {$shape_key} has no valid dimensions");
                            }
                        }
                    }
                }
            } else {
                error_log('Shapes data is NOT an array or is empty');
            }
        }
    }
    
    error_log('Final show_falsebalk_calc: ' . ($show_falsebalk_calc ? 'YES' : 'NO'));
    error_log('=== FALSEBALK DEBUG END ===');
    
    if (!$is_target && !$is_multiplier) {
        error_log('Product not in target or multiplier categories, exiting');
        return;
    }
    $title = $product->get_name();
    $pack_area = extract_area_with_qty($title, $product->get_id());
    $dims = extract_dimensions_from_title($title);
    
    // Получаем доступные услуги покраски
    $painting_services = get_available_painting_services_by_material($product->get_id());
    
    // Получаем множитель цены
    $price_multiplier = get_price_multiplier($product->get_id());
    
    // Получаем настройки калькулятора для категорий 265-268
    $calc_settings = null;
    if ($is_multiplier) {
        $calc_settings = [
            'width_min' => floatval(get_post_meta($product->get_id(), '_calc_width_min', true)),
            'width_max' => floatval(get_post_meta($product->get_id(), '_calc_width_max', true)),
            'width_step' => floatval(get_post_meta($product->get_id(), '_calc_width_step', true)) ?: 100,
            'length_min' => floatval(get_post_meta($product->get_id(), '_calc_length_min', true)),
            'length_max' => floatval(get_post_meta($product->get_id(), '_calc_length_max', true)),
            'length_step' => floatval(get_post_meta($product->get_id(), '_calc_length_step', true)) ?: 0.01,
        ];
    }
    
    // Определяем единицу измерения для калькулятора
    $product_id = $product->get_id();
    $leaf_parent_id = 190;
    $leaf_children = [191, 127, 94];
    $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
    $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
    $unit_text = $is_leaf_category ? 'лист' : 'упаковку';
    $unit_forms = $is_leaf_category ? ['лист', 'листа', 'листов'] : ['упаковка', 'упаковки', 'упаковок'];
    $is_square_meter = has_term([270, 267, 268], 'product_cat', $product->get_id());
    $is_running_meter = has_term([266, 271], 'product_cat', $product->get_id());
    ?>
    
    <script>
    const isSquareMeter = <?php echo $is_square_meter ? 'true' : 'false'; ?>;
const isRunningMeter = 'false';
const paintingServices = <?php echo json_encode($painting_services); ?>;
const priceMultiplier = <?php echo $price_multiplier; ?>;
const isMultiplierCategory = <?php echo $is_multiplier ? 'true' : 'false'; ?>;
const calcSettings = <?php echo $calc_settings ? json_encode($calc_settings) : 'null'; ?>;

// Теперь DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    let form = document.querySelector('form.cart') || 
              document.querySelector('form[action*="add-to-cart"]') ||
              document.querySelector('.single_add_to_cart_button').closest('form');
    let quantityInput = document.querySelector('input[name="quantity"]') ||
                       document.querySelector('.qty') ||
                       document.querySelector('.input-text.qty');
    if (!form) return;

const resultBlock = document.createElement('div');
resultBlock.id = 'custom-calc-block';
resultBlock.className = 'calc-result-container'; // ВАЖНО: Добавляем класс для поиска позже
resultBlock.style.marginTop = '20px';
resultBlock.style.marginBottom = '20px';
form.insertAdjacentElement('afterend', resultBlock);

    // Локальные переменные
    let isAutoUpdate = false;
    
        const paintingServices = <?php echo json_encode($painting_services); ?>;
        const priceMultiplier = <?php echo $price_multiplier; ?>;
        const isMultiplierCategory = <?php echo $is_multiplier ? 'true' : 'false'; ?>;
        const isSquareMeter = <?php echo $is_square_meter ? 'true' : 'false'; ?>;
        const isRunningMeter = <?php echo $is_running_meter ? 'true' : 'false'; ?>;
        const calcSettings = <?php echo $calc_settings ? json_encode($calc_settings) : 'null'; ?>;

        function getRussianPlural(n, forms) {
            n = Math.abs(n);
            n %= 100;
            if (n > 10 && n < 20) return forms[2];
            n %= 10;
            if (n === 1) return forms[0];
            if (n >= 2 && n <= 4) return forms[1];
            return forms[2];
        }

        function removeHiddenFields(prefix) {
            const fields = form.querySelectorAll(`input[name^="${prefix}"]`);
            fields.forEach(field => field.remove());
        }

        function createHiddenField(name, value) {
            let field = form.querySelector(`input[name="${name}"]`);
            if (!field) {
                field = document.createElement('input');
                field.type = 'hidden';
                field.name = name;
                form.appendChild(field);
            }
            field.value = value;
            return field;
        }

        // Создаем блок для услуг покраски с select вместо radiobutton
function createPaintingServicesBlock(currentCategoryId) {
    if (Object.keys(paintingServices).length === 0) return null;

    const paintingBlock = document.createElement('div');
    paintingBlock.id = 'painting-services-block';

    // options
    let optionsHTML = '<option value="" selected>Без покраски</option>';
    Object.entries(paintingServices).forEach(([key, service]) => {
        let optionText = service.name;
        // Добавляем цену только если категория вне диапазона 265-271
        if (currentCategoryId < 265 || currentCategoryId > 271) {
            optionText += ` (+${service.price} ₽/м²)`;
        }
        optionsHTML += `<option value="${key}" data-price="${service.price}">${optionText}</option>`;
    });


    paintingBlock.innerHTML = `
        <br><h4>Услуги покраски</h4>
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 10px;">
                Выберите услугу покраски:
                <select id="painting_service_select" style="margin-left: 10px; padding: 5px; width: 100%; background: #fff">
                    ${optionsHTML}
                </select>
            </label>
            <div id="painting-service-result" style="display:none;"></div>
        </div>

        <!-- для вставки блоков выбора схем/цветов из pm-paint-schemes.php -->
        <div id="paint-schemes-root"></div>
    `;
    return paintingBlock;
}


        const paintingBlock = createPaintingServicesBlock();

        <?php if($pack_area && $is_target): ?>
        const areaCalc = document.createElement('div');
        areaCalc.id = 'calc-area';
        areaCalc.innerHTML = `
            <br><h4>Расчет количества по площади</h4>
            <div style="margin-bottom: 10px;">
                Площадь ${<?php echo json_encode($unit_text); ?>.replace('упаковку', 'упаковки').replace('лист', 'листа')}: <strong>${<?php echo $pack_area; ?>.toFixed(3)} м²</strong><br>
                Цена за ${<?php echo json_encode($unit_text); ?>}: <strong>${(<?php echo floatval($product->get_price()); ?> * <?php echo $pack_area; ?>).toFixed(2)} ₽</strong>
            </div>
            <label>Введите нужную площадь, м²:
                <input type="number" min="<?php echo $pack_area; ?>" step="0.1" id="calc_area_input" placeholder="1" style="width:100px; margin-left:10px;">
            </label>
            <div id="calc_area_result" style="margin-top:10px;"></div>
        `;
        resultBlock.appendChild(areaCalc);

        // Добавляем блок услуг покраски после блока расчета площади
        if (paintingBlock) {
            areaCalc.appendChild(paintingBlock);
        }

        const areaInput = document.getElementById('calc_area_input');
        const areaResult = document.getElementById('calc_area_result');
        const basePriceM2 = <?php echo floatval($product->get_price()); ?>;
        const packArea = <?php echo $pack_area; ?>;
        const unitForms = <?php echo json_encode($unit_forms); ?>;

        function updateAreaCalc() {
            const area = parseFloat(areaInput.value);
            
            if (!area || area <= 0) {
                areaResult.innerHTML = '';
                removeHiddenFields('custom_area_');
                updatePaintingServiceCost(0);
                return;
            }

            const packs = Math.ceil(area / packArea);
            const totalPrice = packs * basePriceM2 * packArea;
            const totalArea = packs * packArea;
            const plural = getRussianPlural(packs, unitForms);
            
            const paintingCost = updatePaintingServiceCost(totalArea);
            const grandTotal = totalPrice + paintingCost;

            let html = `Нужная площадь: <b>${area.toFixed(2)} м²</b><br>`;
            html += `Необходимо: <b>${packs} ${plural}</b><br>`;
            html += `Стоимость материала: <b>${totalPrice.toFixed(2)} ₽</b><br>`;
            if (paintingCost > 0) {
                html += `Стоимость покраски: <b>${paintingCost.toFixed(2)} ₽</b><br>`;
                html += `<strong>Итого с покраской: <b>${grandTotal.toFixed(2)} ₽</b></strong>`;
            } else {
                html += `<strong>Итого: <b>${totalPrice.toFixed(2)} ₽</b></strong>`;
            }
            
            areaResult.innerHTML = html;

            createHiddenField('custom_area_packs', packs);
            createHiddenField('custom_area_area_value', area.toFixed(2));
            createHiddenField('custom_area_total_price', totalPrice.toFixed(2));
            createHiddenField('custom_area_grand_total', grandTotal.toFixed(2));

            if (quantityInput) {
                isAutoUpdate = true;
                quantityInput.value = packs;
                quantityInput.dispatchEvent(new Event('change', { bubbles: true }));
                setTimeout(() => { isAutoUpdate = false; }, 100);
            }
        }
        
        areaInput.addEventListener('input', updateAreaCalc);
        
        if (quantityInput) {
            quantityInput.addEventListener('input', function() {
                if (!isAutoUpdate && areaInput.value) {
                    areaInput.value = '';
                    updateAreaCalc();
                }
            });
        }
        
        if (quantityInput) {
            quantityInput.addEventListener('change', function() {
                if (!isAutoUpdate) {
                    const packs = parseInt(this.value);
                    if (packs > 0) {
                        const area = packs * packArea;
                        areaInput.value = area.toFixed(2);
                        updateAreaCalc();
                    }
                }
            });
        }
        <?php endif; ?>

        <?php if($dims && $is_target): ?>
        const dimCalc = document.createElement('div');
        dimCalc.id = 'calc-dim';
        let dimHTML = '<br><h4>Расчет по размерам</h4><div style="display:flex;gap:20px;flex-wrap:wrap;align-items: center;white-space:nowrap">';
        dimHTML += '<label>Ширина (мм): <select id="custom_width">';
        <?php foreach($dims['widths'] as $w): ?>
            dimHTML += '<option value="<?php echo $w; ?>"><?php echo $w; ?></option>';
        <?php endforeach; ?>
        dimHTML += '</select></label>';
        dimHTML += '<label>Длина (мм): <select id="custom_length">';
        <?php for($l=$dims['length_min']; $l<=$dims['length_max']; $l+=100): ?>
            dimHTML += '<option value="<?php echo $l; ?>"><?php echo $l; ?></option>';
        <?php endfor; ?>
        dimHTML += '</select></label></div><div id="calc_dim_result" style="margin-top:10px; font-size:1.3em"></div>';
        dimCalc.innerHTML = dimHTML;
        resultBlock.appendChild(dimCalc);

        if (paintingBlock && !document.getElementById('calc-area')) {
            dimCalc.appendChild(paintingBlock);
        }

        const widthEl = document.getElementById('custom_width');
        const lengthEl = document.getElementById('custom_length');
        const dimResult = document.getElementById('calc_dim_result');
        const basePriceDim = <?php echo floatval($product->get_price()); ?>;
        let dimInitialized = false;

        function updateDimCalc(userInteraction = false) {
            const width = parseFloat(widthEl.value);
            const length = parseFloat(lengthEl.value);
            const area = (width/1000) * (length/1000);
            const total = area * basePriceDim;
            
            const paintingCost = updatePaintingServiceCost(area);
            const grandTotal = total + paintingCost;

            let html = `Площадь: <b>${area.toFixed(3)} м²</b><br>`;
            html += `Стоимость материала: <b>${total.toFixed(2)} ₽</b><br>`;
            if (paintingCost > 0) {
                html += `Стоимость покраски: <b>${paintingCost.toFixed(2)} ₽</b><br>`;
                html += `<strong>Итого с покраской: <b>${grandTotal.toFixed(2)} ₽</b></strong>`;
            } else {
                html += `<strong>Цена: <b>${total.toFixed(2)} ₽</b></strong>`;
            }

            dimResult.innerHTML = html;

            if (userInteraction) {
                createHiddenField('custom_width_val', width);
                createHiddenField('custom_length_val', length);
                createHiddenField('custom_dim_price', total.toFixed(2));
                createHiddenField('custom_dim_grand_total', grandTotal.toFixed(2));

                if (quantityInput) {
                    isAutoUpdate = true;
                    quantityInput.value = 1;
                    quantityInput.dispatchEvent(new Event('change', { bubbles: true }));
                    setTimeout(() => { isAutoUpdate = false; }, 100);
                }
            } else if (!dimInitialized) {
                dimInitialized = true;
            }
        }

        widthEl.addEventListener('change', () => updateDimCalc(true));
        lengthEl.addEventListener('change', () => updateDimCalc(true));
        
        if (quantityInput) {
            quantityInput.addEventListener('input', function() {
                if (!isAutoUpdate && form.querySelector('input[name="custom_width_val"]')) {
                    removeHiddenFields('custom_');
                    removeHiddenFields('painting_service_');
                    widthEl.selectedIndex = 0;
                    lengthEl.selectedIndex = 0;
                    const paintingSelect = document.getElementById('painting_service_select');
                    if (paintingSelect) paintingSelect.selectedIndex = 0;
                    updateDimCalc(false);
                }
            });
        }
        
        updateDimCalc(false);
        <?php endif; ?>

<?php 
// Проверяем, нужно ли показывать выбор фаски
$product_cats = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
$show_faska = false;
$faska_types = array();

if ($product_cats && !is_wp_error($product_cats)) {
    foreach ($product_cats as $cat_id) {
        if (in_array($cat_id, array(268, 270))) {
            $show_faska = true;
            // Получаем типы фасок из категории
            $faska_types = get_term_meta($cat_id, 'faska_types', true);
            if ($faska_types) break;
        }
    }
}
?>

<?php if($is_multiplier && !$show_falsebalk_calc): ?>
// Калькулятор для столярки (кроме фальшбалок)
const multiplierCalc = document.createElement('div');
multiplierCalc.id = 'calc-multiplier';

let calcHTML = '<br><h4>Калькулятор стоимости</h4>';
if (priceMultiplier !== 1) {
    calcHTML += ``;
}
calcHTML += '<div style="display:flex;gap:20px;flex-wrap:wrap;align-items: center;">';

// Поле ширины
if (calcSettings && calcSettings.width_min > 0 && calcSettings.width_max > 0) {
    calcHTML += `<label>Ширина (мм): 
        <select id="mult_width" style="background:#fff;margin-left:10px;">
            <option value="">Выберите...</option>`;
    for (let w = calcSettings.width_min; w <= calcSettings.width_max; w += calcSettings.width_step) {
        calcHTML += `<option value="${w}">${w}</option>`;
    }
    calcHTML += `</select></label>`;
} else {
    calcHTML += `<label>Ширина (мм): 
        <input type="number" id="mult_width" min="1" step="100" placeholder="1000" style="width:100px; margin-left:10px;background:#fff;">
    </label>`;
}

// Поле длины
if (calcSettings && calcSettings.length_min > 0 && calcSettings.length_max > 0) {
    calcHTML += `<label>Длина (м): 
        <select id="mult_length" min="0.01" step="0.01" style="margin-left:10px;background:#fff;">
            <option value="">Выберите...</option>`;
    for (let l = calcSettings.length_min; l <= calcSettings.length_max; l += calcSettings.length_step) {
        calcHTML += `<option value="${l.toFixed(2)}">${l.toFixed(2)}</option>`;
    }
    calcHTML += `</select></label>`;
} else {
    calcHTML += `<label>Длина (м): 
        <input type="number" id="mult_length" min="0.01" step="0.01" placeholder="0.01" style="width:100px; margin-left:10px;">
    </label>`;
}

// Количество — НЕ вводимое поле. Используем главное поле WC. Показываем текущее значение.
calcHTML += `<label style="display:none">Количество (шт): <span id="mult_quantity_display" style="display:none">1</span></label>`;

calcHTML += '</div>';

<?php if ($show_faska && !empty($faska_types)): ?>
// Добавляем выбор фаски
calcHTML += `<div id="faska_selection" style="margin-top: 10px; display: none;">
    <h5>Выберите тип фаски:</h4>
    <div id="faska_grid" style="display: grid; grid-template-columns: repeat(4, 1fr); grid-template-rows: repeat(2, 1fr); gap: 10px; margin-top: 10px;">
        <?php foreach ($faska_types as $index => $faska): 
            if (!empty($faska['name'])): ?>
        <label class="faska-option" style="cursor: pointer; text-align: center; padding: 8px; border: 2px solid #ddd; border-radius: 8px; transition: all 0.3s; aspect-ratio: 1;">
            <input type="radio" name="faska_type" value="<?php echo esc_attr($faska['name']); ?>" data-index="<?php echo $index; ?>" data-image="<?php echo esc_url($faska['image']); ?>" style="display: none;">
            <?php if (!empty($faska['image'])): ?>
            <img src="<?php echo esc_url($faska['image']); ?>" alt="<?php echo esc_attr($faska['name']); ?>" style="width: 100%; height: 60px; object-fit: contain; margin-bottom: 3px;">
            <?php endif; ?>
            <div style="font-size: 11px; line-height: 1.2;"><?php echo esc_html($faska['name']); ?></div>
        </label>
        <?php endif; 
        endforeach; ?>
    </div>
    <div id="faska_selected" style="display: none; margin-top: 20px; text-align: center; padding: 10px; border: 2px solid rgb(76, 175, 80); border-radius: 8px; background: #f9f9f9;">
        <p style="margin-bottom: 10px;">Выбранная фаска: <span id="faska_selected_name"></span></p>
        <img id="faska_selected_image" src="" alt="" style="height: auto; max-height: 250px; object-fit: contain;">
        <div style="margin-top: 10px;">
            <button type="button" id="change_faska_btn" style="padding: 8px 20px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer;">Изменить выбор</button>
        </div>
    </div>
</div>`;

// Добавляем CSS для выбранной фаски
document.head.insertAdjacentHTML('beforeend', `
<style>
#faska_selection .faska-option:has(input:checked) {
    border-color: #0073aa !important;
    background-color: #f0f8ff;
    box-shadow: 0 0 8px rgba(0,115,170,0.4);
}
#faska_selection .faska-option:hover {
    border-color: #0073aa;
    transform: scale(1.05);
}
#change_faska_btn:hover {
    background: #005a87 !important;
}
@media (max-width: 768px) {
    #faska_grid {
        grid-template-columns: repeat(3, 1fr) !important;
        grid-template-rows: repeat(3, 1fr) !important;
    }
}
@media (max-width: 480px) {
    #faska_grid {
        grid-template-columns: repeat(2, 1fr) !important;
        grid-template-rows: repeat(4, 1fr) !important;
    }
    #faska_selected_image {
        max-width: 200px !important;
    }
}
</style>
`);
<?php endif; ?>

calcHTML += '<div id="calc_mult_result" style="margin-top:10px; font-size:1.3em"></div>';
multiplierCalc.innerHTML = calcHTML;
resultBlock.appendChild(multiplierCalc);

// Добавляем блок услуг покраски после калькулятора с множителем
if (paintingBlock) {
    multiplierCalc.appendChild(paintingBlock);
}

const multWidthEl = document.getElementById('mult_width');
const multLengthEl = document.getElementById('mult_length');
const multQuantityDisplay = document.getElementById('mult_quantity_display');
const multResult = document.getElementById('calc_mult_result');
const basePriceMult = <?php echo floatval($product->get_price()); ?>;

function updateMultiplierCalc() {
    const widthValue = parseFloat(multWidthEl && multWidthEl.value);
    const lengthValue = parseFloat(multLengthEl && multLengthEl.value);

    // quantity берём из основного поля WC, fallback = 1
    const quantity = (quantityInput && !isNaN(parseInt(quantityInput.value))) ? parseInt(quantityInput.value) : 1;
    multQuantityDisplay.textContent = quantity;

    <?php if ($show_faska): ?>
    // Показываем выбор фаски только если введены размеры
    const faskaSelection = document.getElementById('faska_selection');
    if (faskaSelection) {
        if (widthValue > 0 && lengthValue > 0) {
            faskaSelection.style.display = 'block';
        } else {
            faskaSelection.style.display = 'none';
            // Сброс выбора фаски при изменении размеров
            const faskaInputs = document.querySelectorAll('input[name="faska_type"]');
            faskaInputs.forEach(input => input.checked = false);
            document.getElementById('faska_grid').style.display = 'grid';
            document.getElementById('faska_selected').style.display = 'none';
        }
    }
    <?php endif; ?>

    if (!widthValue || widthValue <= 0 || !lengthValue || lengthValue <= 0) {
        multResult.innerHTML = '';
        removeHiddenFields('custom_mult_');
        updatePaintingServiceCost(0);
        return;
    }

    const width_m = widthValue / 1000;
    const length_m = lengthValue;
    
    const areaPerItem = width_m * length_m;
    const totalArea = areaPerItem * quantity;
    const pricePerItem = areaPerItem * basePriceMult * priceMultiplier;
    const materialPrice = pricePerItem * quantity;
    
    const paintingCost = updatePaintingServiceCost(totalArea);
    const grandTotal = materialPrice + paintingCost;

    let html = `Площадь 1 шт: <b>${areaPerItem.toFixed(3)} м²</b><br>`;
    html += `Общая площадь: <b>${totalArea.toFixed(3)} м²</b> (${quantity} шт)<br>`;
    html += `Толщина: <b>40мм</b></br>`;
    html += `Цена за 1 шт: <b>${pricePerItem.toFixed(2)} ₽</b>`;

    html += '<br>';
    html += `Стоимость материала: <b>${materialPrice.toFixed(2)} ₽</b><br>`;
    
    if (paintingCost > 0) {
        html += `Стоимость покраски: <b>${paintingCost.toFixed(2)} ₽</b><br>`;
        html += `<strong>Итого с покраской: <b>${grandTotal.toFixed(2)} ₽</b></strong>`;
    } else {
        html += `<strong>Итого: <b>${materialPrice.toFixed(2)} ₽</b></strong>`;
    }

    multResult.innerHTML = html;

    createHiddenField('custom_mult_width', widthValue);
    createHiddenField('custom_mult_length', lengthValue);
    createHiddenField('custom_mult_quantity', quantity);
    createHiddenField('custom_mult_area_per_item', areaPerItem.toFixed(3));
    createHiddenField('custom_mult_total_area', totalArea.toFixed(3));
    createHiddenField('custom_mult_multiplier', priceMultiplier);
    createHiddenField('custom_mult_price', materialPrice.toFixed(2));
    createHiddenField('custom_mult_grand_total', grandTotal.toFixed(2));

    <?php if ($show_faska): ?>
    // Сохраняем выбранную фаску
    const selectedFaska = document.querySelector('input[name="faska_type"]:checked');
    if (selectedFaska) {
        createHiddenField('selected_faska_type', selectedFaska.value);
    } else {
        removeHiddenFields('selected_faska_');
    }
    <?php endif; ?>

    // Не меняем quantityInput здесь — это поле главный источник.
}

multWidthEl.addEventListener('change', updateMultiplierCalc);
multLengthEl.addEventListener('change', updateMultiplierCalc);

<?php if ($show_faska): ?>
// Обработчик выбора фаски
setTimeout(function() {
    const faskaInputs = document.querySelectorAll('input[name="faska_type"]');
    const faskaGrid = document.getElementById('faska_grid');
    const faskaSelected = document.getElementById('faska_selected');
    const faskaSelectedName = document.getElementById('faska_selected_name');
    const faskaSelectedImage = document.getElementById('faska_selected_image');
    const changeFaskaBtn = document.getElementById('change_faska_btn');
    
    faskaInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.checked) {
                // Скрываем сетку, показываем выбранное
                faskaGrid.style.display = 'none';
                faskaSelected.style.display = 'block';
                
                // Обновляем информацию о выбранной фаске
                faskaSelectedName.textContent = this.value;
                faskaSelectedImage.src = this.dataset.image;
                faskaSelectedImage.alt = this.value;
            }
            updateMultiplierCalc();
        });
    });
    
    // Кнопка изменения выбора
    if (changeFaskaBtn) {
        changeFaskaBtn.addEventListener('click', function() {
            faskaGrid.style.display = 'grid';
            faskaSelected.style.display = 'none';
        });
    }
}, 100);
<?php endif; ?>

if (quantityInput) {
    quantityInput.addEventListener('change', function() {
        if (!isAutoUpdate && multWidthEl.value && multLengthEl.value) {
            updateMultiplierCalc();
        }
    });
}

// Синхронизация количества из основного поля в калькулятор
if (quantityInput) {
    quantityInput.addEventListener('input', function() {
        if (!isAutoUpdate) {
            const mainQty = parseInt(this.value);
            if (mainQty > 0 && multWidthEl.value && multLengthEl.value) {
                multQuantityEl.value = mainQty;
                updateMultiplierCalc();
            }
        }
    });
    
    // Полный сброс при изменении количества вручную без активного калькулятора
    quantityInput.addEventListener('change', function() {
        if (!isAutoUpdate && !form.querySelector('input[name="custom_mult_width"]')) {
            // Калькулятор не активен, ничего не делаем
            return;
        }
    });
}
<?php endif; ?>

// Функция обновления стоимости покраски
function updatePaintingServiceCost(totalArea = null) {
    if (!paintingBlock) return 0;
    
    const serviceSelect = document.getElementById('painting_service_select');
    const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
    const paintingResult = document.getElementById('painting-service-result');
    
    if (!selectedOption || !selectedOption.value) {
        paintingResult.innerHTML = '';
        removeHiddenFields('painting_service_');
        return 0;
    }
    
    const serviceKey = selectedOption.value;
    const servicePrice = parseFloat(selectedOption.dataset.price);
    
    if (!totalArea) {
        paintingResult.innerHTML = `Выбрана услуга: ${paintingServices[serviceKey].name}`;
        return 0;
    }
    
    const totalPaintingCost = totalArea * servicePrice;
    paintingResult.innerHTML = `${paintingServices[serviceKey].name}: ${totalPaintingCost.toFixed(2)} ₽ (${totalArea.toFixed(3)} м² × ${servicePrice} ₽/м²)`;
    
    createHiddenField('painting_service_key', serviceKey);
    createHiddenField('painting_service_name', paintingServices[serviceKey].name);
    createHiddenField('painting_service_price_per_m2', servicePrice);
    createHiddenField('painting_service_area', totalArea.toFixed(3));
    createHiddenField('painting_service_total_cost', totalPaintingCost.toFixed(2));
    
    return totalPaintingCost;
}

// Обработчик для услуг покраски (select)
if (paintingBlock) {
    const serviceSelect = document.getElementById('painting_service_select');
    if (serviceSelect) {
        serviceSelect.addEventListener('change', function() {
            const areaInput = document.getElementById('calc_area_input');
            const widthEl = document.getElementById('custom_width');
            const lengthEl = document.getElementById('custom_length');
            const multWidthEl = document.getElementById('mult_width');
            const multLengthEl = document.getElementById('mult_length');

            // Сценарий 1: калькулятор площади
            if (areaInput && areaInput.value) {
                updateAreaCalc();
                return;
            }

            // Сценарий 2: калькулятор размеров для стандартных категорий
            if (widthEl && lengthEl) {
                const width = parseFloat(widthEl.value);
                const length = parseFloat(lengthEl.value);
                if (width > 0 && length > 0) {
                    updateDimCalc(true);
                    return;
                }
            }

            // Сценарий 3: калькулятор с множителем для категорий 265-268
            if (multWidthEl && multLengthEl) {
                const width = parseFloat(multWidthEl.value);
                const length = parseFloat(multLengthEl.value);
                if (width > 0 && length > 0) {
                    updateMultiplierCalc();
                    return;
                }
            }

            // Сценарий 3.5: калькулятор погонных метров (running meter)
            const rmWidthEl = document.getElementById('rm_width');
            const rmLengthEl = document.getElementById('rm_length');
            if (rmLengthEl && rmLengthEl.value) {
                updateRunningMeterCalc();
                return; // ВАЖНО: возвращаем return, чтобы не сбросить покраску
            }

            // Сценарий 4: ничего не введено, но есть pack_area
            if (typeof packArea !== 'undefined' && packArea > 0) {
                if (areaInput) {
                    areaInput.value = packArea.toFixed(2);
                    updateAreaCalc();
                } else if (widthEl && lengthEl) {
                    updateDimCalc(true);
                }
            }

            updatePaintingServiceCost(0);
        });
    }
}
        
// Обработчик для выбора цвета покраски через делегирование событий
document.addEventListener('change', function(e) {
    // Проверяем, что это радио-кнопка выбора цвета
    if (e.target.name === 'pm_selected_color') {
        console.log('Paint color changed, recalculating...');
        
        // Определяем, какой калькулятор активен и пересчитываем его
        const areaInput = document.getElementById('calc_area_input');
        const widthEl = document.getElementById('custom_width');
        const lengthEl = document.getElementById('custom_length');
        const multWidthEl = document.getElementById('mult_width');
        const multLengthEl = document.getElementById('mult_length');
        const rmLengthEl = document.getElementById('rm_length');
        const sqWidthEl = document.getElementById('sq_width');
        const sqLengthEl = document.getElementById('sq_length');
        
        // 1. Калькулятор площади
        if (areaInput && areaInput.value) {
            console.log('Updating area calculator');
            updateAreaCalc();
            return;
        }
        
        // 2. Калькулятор размеров (старый)
        if (widthEl && lengthEl && widthEl.value && lengthEl.value) {
            console.log('Updating dimensions calculator');
            updateDimCalc(true);
            return;
        }
        
        // 3. Калькулятор с множителем
        if (multWidthEl && multLengthEl && multWidthEl.value && multLengthEl.value) {
            console.log('Updating multiplier calculator');
            updateMultiplierCalc();
            return;
        }
        
        // 4. Калькулятор погонных метров (включая фальшбалки)
        if (rmLengthEl && rmLengthEl.value) {
            console.log('Updating running meter calculator (falsebalk)');
            updateRunningMeterCalc();
            return;
        }
        
        // 5. Калькулятор квадратных метров
        if (sqWidthEl && sqLengthEl && sqWidthEl.value && sqLengthEl.value) {
            console.log('Updating square meter calculator');
            updateSquareMeterCalc();
            return;
        }
    }
});



<?php if($is_running_meter): ?>
    <?php 
    // Получаем данные для фальшбалок (проверка уже была выполнена выше)
    $is_falsebalk = product_in_category($product->get_id(), 266);
    $shapes_data = array();
    $show_falsebalk_calculator = $show_falsebalk_calc; // Используем переменную из начала функции
    
    if ($show_falsebalk_calculator) {
        $shapes_data = get_post_meta($product->get_id(), '_falsebalk_shapes_data', true);
        if (!is_array($shapes_data)) {
            $shapes_data = array();
        }
    }
    ?>
    
    console.log('=== Running meter calculator initialization ===');
console.log('Show falsebalk calc:', <?php echo !empty($show_falsebalk_calc) ? 'true' : 'false'; ?>);

    console.log('Is falsebalk:', <?php echo $is_falsebalk ? 'true' : 'false'; ?>);
    
    // ТОЛЬКО для фальшбалок очищаем resultBlock
    <?php if ($show_falsebalk_calculator): ?>
        console.log('Clearing result block for FALSEBALK calculator');
        if (resultBlock) {
            resultBlock.innerHTML = '';
        }
    <?php endif; ?>
    
    const runningMeterCalc = document.createElement('div');
    runningMeterCalc.id = 'calc-running-meter';

    let rmCalcHTML = '<br><h4>Калькулятор стоимости</h4>';


<?php if ($show_falsebalk_calculator): ?>
// ============ ДЛЯ ФАЛЬШБАЛОК (КАТЕГОРИЯ 266) ============
console.log('=== Rendering FALSEBALK calculator ===');
const shapesData = <?php echo json_encode($shapes_data); ?>;
console.log('Shapes data:', shapesData);

<?php 
// --- ИКОНКИ ДЛЯ ФОРМ ---
$shape_icons = [
    'g' => '<svg width="60" height="60" viewBox="0 0 60 60">
                <rect x="5" y="5" width="10" height="50" fill="#000"/>
                <rect x="5" y="45" width="50" height="10" fill="#000"/>
            </svg>',
    'p' => '<svg width="60" height="60" viewBox="0 0 60 60">
                <rect x="5" y="5" width="10" height="50" fill="#000"/>
                <rect x="45" y="5" width="10" height="50" fill="#000"/>
                <rect x="5" y="5" width="50" height="10" fill="#000"/>
            </svg>',
    'o' => '<svg width="60" height="60" viewBox="0 0 60 60">
                <rect x="5" y="5" width="50" height="50" fill="none" stroke="#000" stroke-width="10"/>
            </svg>'
];

$shape_labels = [
    'g' => 'Г-образная',
    'p' => 'П-образная',
    'o' => 'О-образная'
];

// --- ГЕНЕРАЦИЯ HTML ДЛЯ ВЫБОРА ФОРМ ---
$shapes_buttons_html = '';

foreach ($shapes_data as $shape_key => $shape_info):
    if (is_array($shape_info) && !empty($shape_info['enabled'])):
        $shape_label = isset($shape_labels[$shape_key]) ? $shape_labels[$shape_key] : ucfirst($shape_key);
        $shapes_buttons_html .= '<label class="shape-tile" data-shape="' . esc_attr($shape_key) . '" style="cursor:pointer; border:2px solid #ccc; border-radius:10px; padding:10px; background:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; transition:all .2s; min-width:100px;">';
        $shapes_buttons_html .= '<input type="radio" name="falsebalk_shape" value="' . esc_attr($shape_key) . '" style="display:none;">';
        $shapes_buttons_html .= '<div>' . $shape_icons[$shape_key] . '</div>';
        $shapes_buttons_html .= '<span style="font-size:12px; color:#666; text-align:center;">' . esc_html($shape_label) . '</span>';
        $shapes_buttons_html .= '</label>';
    endif;
endforeach;
?>

// 1. ВЫБОР ФОРМЫ СЕЧЕНИЯ
rmCalcHTML += '<div style="margin-bottom:20px; border:2px solid #e0e0e0; padding:15px; border-radius:8px; background:#f9f9f9;">';
rmCalcHTML += '<label style="display:block; margin-bottom:15px; font-weight:600; font-size:1.1em;">Шаг 1: Выберите форму сечения фальшбалки</label>';
rmCalcHTML += '<div style="display:flex; gap:15px; flex-wrap:wrap;">';
rmCalcHTML += <?php echo json_encode($shapes_buttons_html); ?>;
rmCalcHTML += '</div></div>';

// 2. КОНТЕЙНЕР ДЛЯ ПАРАМЕТРОВ
rmCalcHTML += '<div id="falsebalk_params" style="display:none; margin-bottom:20px; border:2px solid #e0e0e0; padding:15px; border-radius:8px; background:#f9f9f9;">';
rmCalcHTML += '<label style="display:block; margin-bottom:15px; font-weight:600; font-size:1.1em;">Шаг 2: Выберите размеры</label>';
rmCalcHTML += '<div style="display:flex; gap:20px; flex-wrap:wrap; align-items:center;">';

rmCalcHTML += `<label style="display:flex; flex-direction:column; gap:5px;">
    <span style="font-weight:500;">Ширина (мм):</span>
    <select id="rm_width" style="background:#fff; padding:8px 12px; border:1px solid #ddd; border-radius:4px; min-width:150px;">
        <option value="">Сначала выберите форму</option>
    </select>
</label>`;

rmCalcHTML += `<div id="height_container" style="dislpay:contents"></div>`;

rmCalcHTML += `<label style="display:flex; flex-direction:column; gap:5px;">
    <span style="font-weight:500;">Длина (м):</span>
    <select id="rm_length" style="background:#fff; padding:8px 12px; border:1px solid #ddd; border-radius:4px; min-width:150px;">
        <option value="">Сначала выберите форму</option>
    </select>
</label>`;

rmCalcHTML += `<label style="display:none; flex-direction:column; gap:5px;">
    <span style="font-weight:500;">Количество (шт):</span>
    <span id="rm_quantity_display" style="font-weight:600; font-size:1.1em;">1</span>
</label>`;

rmCalcHTML += '</div></div>';

// 3. РЕЗУЛЬТАТ
rmCalcHTML += '<div id="calc_rm_result" style="margin-top:15px;"></div>';

<?php else: ?>
// ============ ДЛЯ ОБЫЧНЫХ СТОЛЯРНЫХ ИЗДЕЛИЙ ============
console.log('Rendering STANDARD running meter calculator');

rmCalcHTML += '<div style="display:flex;gap:20px;flex-wrap:wrap;align-items: center;">';

// Поле ширины
if (calcSettings && calcSettings.width_min > 0 && calcSettings.width_max > 0) {
    rmCalcHTML += `<label>Ширина (мм): 
        <select id="rm_width" style="background:#fff;margin-left:10px;">
            <option value="">Выберите...</option>`;
    for (let w = calcSettings.width_min; w <= calcSettings.width_max; w += calcSettings.width_step) {
        rmCalcHTML += `<option value="${w}">${w}</option>`;
    }
    rmCalcHTML += `</select></label>`;
} else {
    rmCalcHTML += `<label>Ширина (мм): 
        <input type="number" id="rm_width" min="1" step="100" placeholder="100" style="width:100px; margin-left:10px;background:#fff">
    </label>`;
}

// Поле длины
if (calcSettings && calcSettings.length_min > 0 && calcSettings.length_max > 0) {
    rmCalcHTML += `<label>Длина (м): 
        <select id="rm_length" style="background:#fff;margin-left:10px;">
            <option value="">Выберите...</option>`;
    for (let l = calcSettings.length_min; l <= calcSettings.length_max; l += calcSettings.length_step) {
        rmCalcHTML += `<option value="${l.toFixed(2)}">${l.toFixed(2)}</option>`;
    }
    rmCalcHTML += `</select></label>`;
} else {
    rmCalcHTML += `<label>Длина (пог. м): 
        <input type="number" id="rm_length" min="0.1" step="0.1" placeholder="2.0" style="width:100px; margin-left:10px;background:#fff">
    </label>`;
}

rmCalcHTML += `<label style="display:none">Количество (шт): <span id="rm_quantity_display" style="margin-left:10px; font-weight:600;">1</span></label>`;
rmCalcHTML += '</div>';
rmCalcHTML += '<div id="calc_rm_result" style="margin-top:10px;"></div>';
<?php endif; ?>

// ВАЖНО: Добавляем HTML в DOM
runningMeterCalc.innerHTML = rmCalcHTML;
resultBlock.appendChild(runningMeterCalc);
console.log('✓ Running meter calculator HTML added to DOM');

// Добавляем блок услуг покраски
if (paintingBlock) {
    runningMeterCalc.appendChild(paintingBlock);
    console.log('✓ Painting block added');
}

<?php if ($show_falsebalk_calculator): ?>
// ============ JAVASCRIPT ЛОГИКА ДЛЯ ФАЛЬШБАЛОК ============

// === ФУНКЦИИ ===
function generateOptions(min, max, step, unit = '') {
    const options = ['<option value="">Выберите...</option>'];
    if (!min || !max || !step || min > max) return options.join('');
    const stepsCount = Math.round((max - min) / step) + 1;
    for (let i = 0; i < stepsCount; i++) {
        const value = min + (i * step);
        const displayValue = unit === 'м' ? value.toFixed(2) : Math.round(value);
        const rawValue = unit === 'м' ? value.toFixed(2) : Math.round(value);
        options.push(`<option value="${rawValue}">${displayValue}${unit ? ' ' + unit : ''}</option>`);
    }
    return options.join('');
}

function parseOldFormat(data) {
    if (typeof data === 'string' && data.includes(',')) {
        const values = data.split(',').map(v => v.trim()).filter(v => v);
        return values.map(v => `<option value="${v}">${v}</option>`).join('');
    }
    return null;
}

const falsebalkaParams = document.getElementById('falsebalk_params');
const rmWidthEl = document.getElementById('rm_width');
const heightContainer = document.getElementById('height_container');
const rmLengthEl = document.getElementById('rm_length');

function updateDimensions(selectedShape) {
    const shapeData = shapesData[selectedShape];
    console.log('Updating dimensions for:', selectedShape, shapeData);
    
    if (!shapeData || !shapeData.enabled) {
        console.error('No data found for shape:', selectedShape);
        return;
    }
    
    falsebalkaParams.style.display = 'block';
    
    // ШИРИНЫ
    const oldWidthFormat = parseOldFormat(shapeData.widths);
    if (oldWidthFormat) {
        rmWidthEl.innerHTML = '<option value="">Выберите...</option>' + oldWidthFormat;
    } else {
        rmWidthEl.innerHTML = generateOptions(shapeData.width_min, shapeData.width_max, shapeData.width_step, 'мм');
    }
    
    // ВЫСОТЫ
    heightContainer.innerHTML = '';
    if (selectedShape === 'p') {
        // П-образная: две высоты
        let height1Options, height2Options;
        const oldHeight1Format = parseOldFormat(shapeData.heights);
        
        if (oldHeight1Format) {
            height1Options = '<option value="">Выберите...</option>' + oldHeight1Format;
            height2Options = '<option value="">Выберите...</option>' + oldHeight1Format;
        } else {
            height1Options = generateOptions(shapeData.height1_min, shapeData.height1_max, shapeData.height1_step, 'мм');
            height2Options = generateOptions(shapeData.height2_min, shapeData.height2_max, shapeData.height2_step, 'мм');
        }
        
        heightContainer.innerHTML = `
            <label style="display:flex; flex-direction:column; gap:5px;">
                <span style="font-weight:500;">Высота 1 (мм):</span>
                <select id="rm_height1" style="background:#fff; padding:8px 12px; border:1px solid #ddd; border-radius:4px; min-width:150px;">
                    ${height1Options}
                </select>
            </label>
            <label style="display:flex; flex-direction:column; gap:5px;">
                <span style="font-weight:500;">Высота 2 (мм):</span>
                <select id="rm_height2" style="background:#fff; padding:8px 12px; border:1px solid #ddd; border-radius:4px; min-width:150px;">
                    ${height2Options}
                </select>
            </label>
        `;
        
        document.getElementById('rm_height1').addEventListener('change', updateRunningMeterCalc);
        document.getElementById('rm_height2').addEventListener('change', updateRunningMeterCalc);
    } else {
        // Г и О: одна высота
        const oldHeightFormat = parseOldFormat(shapeData.heights);
        let heightOptions = oldHeightFormat ? '<option value="">Выберите...</option>' + oldHeightFormat : 
                           generateOptions(shapeData.height_min, shapeData.height_max, shapeData.height_step, 'мм');
        
        heightContainer.innerHTML = `
            <label style="display:flex; flex-direction:column; gap:5px;">
                <span style="font-weight:500;">Высота (мм):</span>
                <select id="rm_height" style="background:#fff; padding:8px 12px; border:1px solid #ddd; border-radius:4px; min-width:150px;">
                    ${heightOptions}
                </select>
            </label>
        `;
        
        document.getElementById('rm_height').addEventListener('change', updateRunningMeterCalc);
    }
    
    // ДЛИНЫ
    const oldLengthFormat = parseOldFormat(shapeData.lengths);
    if (oldLengthFormat) {
        rmLengthEl.innerHTML = '<option value="">Выберите...</option>' + oldLengthFormat;
    } else {
        rmLengthEl.innerHTML = generateOptions(shapeData.length_min, shapeData.length_max, shapeData.length_step, 'м');
    }
    
    document.getElementById('calc_rm_result').innerHTML = '';
    if (typeof removeHiddenFields === 'function') {
        removeHiddenFields('custom_rm_');
    }
}

// Обработчик клика по плиткам
document.addEventListener('click', function(e) {
    const tile = e.target.closest('.shape-tile');
    if (!tile) return;
    
    document.querySelectorAll('.shape-tile').forEach(t => {
        t.style.borderColor = '#ccc';
        t.style.boxShadow = 'none';
    });
    
    tile.style.borderColor = '#3aa655';
    tile.style.boxShadow = '0 0 0 3px rgba(58,166,85,0.3)';
    
    const radio = tile.querySelector('input[name="falsebalk_shape"]');
    if (radio) {
        radio.checked = true;
        updateDimensions(radio.value);
    }
});

// Эффекты наведения
document.querySelectorAll('.shape-tile').forEach(tile => {
    tile.addEventListener('mouseenter', function() {
        const radio = this.querySelector('input[name="falsebalk_shape"]');
        if (!radio || !radio.checked) {
            this.style.borderColor = '#0073aa';
            this.style.transform = 'scale(1.02)';
        }
    });
    
    tile.addEventListener('mouseleave', function() {
        const radio = this.querySelector('input[name="falsebalk_shape"]');
        if (!radio || !radio.checked) {
            this.style.borderColor = '#ccc';
            this.style.transform = 'scale(1)';
        }
    });
});

console.log('✓ Falsebalk event handlers attached');

<?php else: ?>
// ============ JAVASCRIPT ЛОГИКА ДЛЯ ОБЫЧНЫХ ИЗДЕЛИЙ ============
console.log('Initializing STANDARD running meter logic');
const rmWidthEl = document.getElementById('rm_width');
const rmLengthEl = document.getElementById('rm_length');
<?php endif; ?>

// === ОБЩАЯ ФУНКЦИЯ РАСЧЕТА (для обоих типов) ===
const rmQuantityDisplay = document.getElementById('rm_quantity_display');
const rmResult = document.getElementById('calc_rm_result');
const basePriceRM = <?php echo floatval($product->get_price()); ?>;

function updateRunningMeterCalc() {
    <?php if ($show_falsebalk_calculator): ?>
    const selectedShape = document.querySelector('input[name="falsebalk_shape"]:checked');
    if (!selectedShape) {
        rmResult.innerHTML = '<span style="color: #999;">⬆️ Выберите форму сечения фальшбалки</span>';
        return;
    }
    
    const widthValue = rmWidthEl ? parseFloat(rmWidthEl.value) : 0;
    const lengthValue = parseFloat(rmLengthEl.value);
    
    let heightValue = 0;
    let height2Value = 0;
    
    if (selectedShape.value === 'p') {
        const height1El = document.getElementById('rm_height1');
        const height2El = document.getElementById('rm_height2');
        heightValue = height1El ? parseFloat(height1El.value) : 0;
        height2Value = height2El ? parseFloat(height2El.value) : 0;
    } else {
        const heightEl = document.getElementById('rm_height');
        heightValue = heightEl ? parseFloat(heightEl.value) : 0;
    }
    <?php else: ?>
    const widthValue = rmWidthEl ? parseFloat(rmWidthEl.value) : 0;
    const lengthValue = parseFloat(rmLengthEl.value);
    <?php endif; ?>

    const quantity = (quantityInput && !isNaN(parseInt(quantityInput.value))) ? parseInt(quantityInput.value) : 1;
    rmQuantityDisplay.textContent = quantity;

    if (!lengthValue || lengthValue <= 0) {
        rmResult.innerHTML = '';
        removeHiddenFields('custom_rm_');
        updatePaintingServiceCost(0);
        return;
    }

// пересчёт длин и площади
const totalLength = lengthValue * quantity;

// --- вычисляем площадь покраски (m^2) как раньше ---
let paintingArea = 0;
if (widthValue > 0) {
    const width_m = widthValue / 1000;
    const height_m = (typeof heightValue !== 'undefined' ? heightValue : 0) / 1000;
    const height2_m = (typeof height2Value !== 'undefined' ? height2Value : 0) / 1000;

    if (selectedShape) {
        const shapeKey = selectedShape.value;
        if (shapeKey === 'g') {
            paintingArea = (width_m + height_m) * totalLength;
        } else if (shapeKey === 'p') {
            paintingArea = (width_m + height_m + height2_m) * totalLength;
        } else if (shapeKey === 'o') {
            // О-образная — две стороны и две высоты (эквивалент 4 плоскостей)
            paintingArea = 2 * (width_m + height_m) * totalLength;
        } else {
            // запасной вариант
            paintingArea = width_m * totalLength;
        }
    } else {
        paintingArea = width_m * totalLength;
    }
}

// --- материал берём пропорционально той же площади ---
// basePriceRM трактуем как цена за 1 м² материала/покрытия
const materialPrice = paintingArea * basePriceRM * priceMultiplier;

// цена за одну единицу (на случай вывода)
const pricePerItem = (quantity > 0) ? (materialPrice / quantity) : 0;

// рассчёт стоимости покраски 
const paintingCost = updatePaintingServiceCost(paintingArea);

// итог
const grandTotal = materialPrice + paintingCost;


    <?php if ($show_falsebalk_calculator): ?>
    const shapeLabel = selectedShape.closest('.shape-tile')?.querySelector('span')?.textContent.trim() || selectedShape.value;
    let html = `<div style="background: #f0f8ff; padding: 10px; font-size:1em; border-radius: 5px; margin-bottom: 10px; border-left: 4px solid #8bc34a;">`;
    html += `<div>Форма сечения: <b>${shapeLabel}</b></div>`;
    if (widthValue > 0) html += `<div>Ширина: <b>${widthValue} мм</b></div>`;
    if (heightValue > 0) {
        if (selectedShape.value === 'p') {
            html += `<div>Высота 1: <b>${heightValue} мм</b></div>`;
            if (height2Value > 0) html += `<div>Высота 2: <b>${height2Value} мм</b></div>`;
        } else {
            html += `<div>Высота: <b>${heightValue} мм</b></div>`;
        }
    }
    html += `<div>Длина 1 шт: <b>${lengthValue.toFixed(2)} пог. м</b></div></div>`;
    <?php else: ?>
    let html = `Длина 1 шт: <b>${lengthValue.toFixed(2)} пог. м</b><br>`;
    <?php endif; ?>
    
    html += `Общая длина: <b>${totalLength.toFixed(2)} пог. м</b> (${quantity} шт)<br>`;
    html += `Цена за 1 шт: <b>${pricePerItem.toFixed(2)} ₽</b><br>`;
    html += `Стоимость материала: <b>${materialPrice.toFixed(2)} ₽</b><br>`;
    
    if (paintingCost > 0) {
        html += `Площадь покраски: <b>${paintingArea.toFixed(3)} м²</b><br>`;
        html += `Стоимость покраски: <b>${paintingCost.toFixed(2)} ₽</b><br>`;
        html += `<strong style="font-size: 1.2em; color: #0073aa;">Итого с покраской: <b>${grandTotal.toFixed(2)} ₽</b></strong>`;
    } else {
        html += `<strong style="font-size: 1.2em; color: #0073aa;">Итого: <b>${materialPrice.toFixed(2)} ₽</b></strong>`;
    }

    rmResult.innerHTML = html;

    <?php if ($show_falsebalk_calculator): ?>
    createHiddenField('custom_rm_shape', selectedShape.value);
    createHiddenField('custom_rm_shape_label', shapeLabel);
    createHiddenField('custom_rm_width', widthValue || 0);
    createHiddenField('custom_rm_height', heightValue || 0);
    if (selectedShape.value === 'p' && height2Value > 0) {
        createHiddenField('custom_rm_height2', height2Value);
    }
    <?php else: ?>
    createHiddenField('custom_rm_width', widthValue || 0);
    <?php endif; ?>
    
    createHiddenField('custom_rm_length', lengthValue);
    createHiddenField('custom_rm_quantity', quantity);
    createHiddenField('custom_rm_total_length', totalLength.toFixed(2));
    createHiddenField('custom_rm_painting_area', paintingArea.toFixed(3));
    createHiddenField('custom_rm_multiplier', priceMultiplier);
    createHiddenField('custom_rm_price', materialPrice.toFixed(2));
    createHiddenField('custom_rm_grand_total', grandTotal.toFixed(2));
}

if (rmWidthEl) rmWidthEl.addEventListener('change', updateRunningMeterCalc);
if (rmLengthEl) rmLengthEl.addEventListener('change', updateRunningMeterCalc);

if (quantityInput) {
    quantityInput.addEventListener('input', function() {
        if (!isAutoUpdate && rmLengthEl && rmLengthEl.value) {
            updateRunningMeterCalc();
        }
    });
    
    quantityInput.addEventListener('change', function() {
        if (!isAutoUpdate && rmLengthEl && rmLengthEl.value) {
            updateRunningMeterCalc();
        }
    });
}

console.log('✓ Running meter calculator fully initialized');
<?php endif; ?>

    });


        <?php if($is_square_meter && !$is_running_meter): ?>
        // Калькулятор для категорий за квадратные метры - столярные изделия
        const sqMeterCalc = document.createElement('div');
        sqMeterCalc.id = 'calc-square-meter';

        let sqCalcHTML = '<br><h4>Калькулятор стоимости</h4>';

        sqCalcHTML += '<div style="display:flex;gap:20px;flex-wrap:wrap;align-items: center;">';

        // Поле ширины
        if (calcSettings && calcSettings.width_min > 0 && calcSettings.width_max > 0) {
            sqCalcHTML += `<label>Ширина (мм): 
                <select id="sq_width" style="background:#fff;margin-left:10px;">
                    <option value="">Выберите...</option>`;
            for (let w = calcSettings.width_min; w <= calcSettings.width_max; w += calcSettings.width_step) {
                sqCalcHTML += `<option value="${w}">${w}</option>`;
            }
            sqCalcHTML += `</select></label>`;
        } else {
            sqCalcHTML += `<label>Ширина (мм): 
                <input type="number" id="sq_width" min="1" step="100" placeholder="1000" style="width:100px; margin-left:10px;background:#fff">
            </label>`;
        }


// Поле длины
if (calcSettings && calcSettings.length_min > 0 && calcSettings.length_max > 0) {
    calcHTML += `<label>Длина (м) 001: 
        <select id="mult_length" min="0.01" step="0.01"  style="margin-left:10px;background:#fff;">
            <option value="">Выберите...</option>`;
    
    // ВАЖНО: Используем целочисленный счётчик для избежания ошибок округления float
    const lengthMin = calcSettings.length_min;
    const lengthMax = calcSettings.length_max;
    const lengthStep = calcSettings.length_step;
    
    // Вычисляем количество шагов
    const stepsCount = Math.round((lengthMax - lengthMin) / lengthStep) + 1;
    
    for (let i = 0; i < stepsCount; i++) {
        const value = lengthMin + (i * lengthStep);
        // Округляем до 2 знаков после запятой для отображения
        const displayValue = value.toFixed(2);
        calcHTML += `<option value="${displayValue}">${displayValue}</option>`;
    }
    
    calcHTML += `</select></label>`;
} else {
    calcHTML += `<label>Длина (м): 
        <input type="number" id="mult_length" min="0.01" step="0.01" placeholder="0.01" style="width:100px; margin-left:10px;background:#fff">
    </label>`;
}

        // Количество
        sqCalcHTML += '</div><div id="calc_sq_result" style="margin-top:10px; font-size:1.3em"></div>';
        sqMeterCalc.innerHTML = sqCalcHTML;
        resultBlock.appendChild(sqMeterCalc);

        // Добавляем блок услуг покраски
        if (paintingBlock) {
            sqMeterCalc.appendChild(paintingBlock);
        }

        const sqWidthEl = document.getElementById('sq_width');
        const sqLengthEl = document.getElementById('sq_length');
        const sqQuantityDisplay = document.getElementById('sq_quantity_display');
        const sqResult = document.getElementById('calc_sq_result');
        const basePriceSQ = <?php echo floatval($product->get_price()); ?>;

        function updateSquareMeterCalc() {
            const widthValue = parseFloat(sqWidthEl.value);
            const lengthValue = parseFloat(sqLengthEl.value);

            const quantity = (quantityInput && !isNaN(parseInt(quantityInput.value))) ? parseInt(quantityInput.value) : 1;
            sqQuantityDisplay.textContent = quantity;

            if (!widthValue || widthValue <= 0 || !lengthValue || lengthValue <= 0) {
                sqResult.innerHTML = '';
                removeHiddenFields('custom_sq_');
                updatePaintingServiceCost(0);
                return;
            }

            const width_m = widthValue / 1000;
            const length_m = lengthValue;
            
            const areaPerItem = width_m * length_m;
            const totalArea = areaPerItem * quantity;
            const pricePerItem = areaPerItem * basePriceSQ;
            const materialPrice = pricePerItem * quantity;
            
            const paintingCost = updatePaintingServiceCost(totalArea);
            const grandTotal = materialPrice + paintingCost;

            let html = `Площадь 1 шт: <b>${areaPerItem.toFixed(3)} м²</b><br>`;
            html += `Общая площадь: <b>${totalArea.toFixed(3)} м²</b> (${quantity} шт)<br>`;
            html += `Цена за 1 шт: <b>${pricePerItem.toFixed(2)} ₽</b>`;
            html += '<br>';
            html += `Стоимость материала: <b>${materialPrice.toFixed(2)} ₽</b><br>`;
            
            if (paintingCost > 0) {
                html += `Стоимость покраски: <b>${paintingCost.toFixed(2)} ₽</b><br>`;
                html += `<strong>Итого с покраской: <b>${grandTotal.toFixed(2)} ₽</b></strong>`;
            } else {
                html += `<strong>Итого: <b>${materialPrice.toFixed(2)} ₽</b></strong>`;
            }

            sqResult.innerHTML = html;

            createHiddenField('custom_sq_width', widthValue);
            createHiddenField('custom_sq_length', lengthValue);
            createHiddenField('custom_sq_quantity', quantity);
            createHiddenField('custom_sq_area_per_item', areaPerItem.toFixed(3));
            createHiddenField('custom_sq_total_area', totalArea.toFixed(3));
            createHiddenField('custom_sq_multiplier', priceMultiplier);
            createHiddenField('custom_sq_price', materialPrice.toFixed(2));
            createHiddenField('custom_sq_grand_total', grandTotal.toFixed(2));
        }

        sqWidthEl.addEventListener('change', updateSquareMeterCalc);
        sqLengthEl.addEventListener('change', updateSquareMeterCalc);

        // Синхронизация количества
        if (quantityInput) {
            quantityInput.addEventListener('input', function() {
                if (!isAutoUpdate && sqWidthEl.value && sqLengthEl.value) {
                    updateSquareMeterCalc();
                }
            });
        }
        <?php endif; ?>
    
    </script>
    <?php
}, 20);

















// --- Добавляем выбранные данные в корзину ---
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id){

    // Проверяем, что товар из целевых категорий
    if (!is_in_target_categories($product_id)) {
        return $cart_item_data;
    }



    $product = wc_get_product($product_id);
    if (!$product) return $cart_item_data;

    $title = $product->get_name();
    $pack_area = extract_area_with_qty($title, $product_id);
    $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());

    // Тип товара
    $leaf_parent_id = 190;
    $leaf_children = [191, 127, 94];
    $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
    $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);

    // Услуга покраски
    $painting_service = null;
    if (!empty($_POST['painting_service_key'])) {
        $painting_service = [
            'key' => sanitize_text_field($_POST['painting_service_key']),
            'name' => sanitize_text_field($_POST['painting_service_name']),
            'price_per_m2' => floatval($_POST['painting_service_price_per_m2']),
            'area' => floatval($_POST['painting_service_area']),
            'total_cost' => floatval($_POST['painting_service_total_cost'])
        ];

        // Добавляем цвет
        if (!empty($_POST['pm_selected_color_filename'])) {
            $color_filename = sanitize_text_field($_POST['pm_selected_color_filename']);
            $painting_service['color_filename'] = $color_filename;
            $painting_service['name_with_color'] = $painting_service['name'] . ' (' . $color_filename . ')';
        }
    }
    
    // Данные схем покраски
    if (!empty($_POST['pm_selected_scheme_name'])) {
        $cart_item_data['pm_selected_scheme_name'] = sanitize_text_field($_POST['pm_selected_scheme_name']);
    }
    if (!empty($_POST['pm_selected_scheme_slug'])) {
        $cart_item_data['pm_selected_scheme_slug'] = sanitize_text_field($_POST['pm_selected_scheme_slug']);
    }
    if (!empty($_POST['pm_selected_color_image'])) {
        $cart_item_data['pm_selected_color_image'] = esc_url_raw($_POST['pm_selected_color_image']);
    }
    if (!empty($_POST['pm_selected_color_filename'])) {
        $cart_item_data['pm_selected_color'] = sanitize_text_field($_POST['pm_selected_color_filename']);
    }

    // ПРИОРИТЕТЫ (в порядке важности):

    // 1. Калькулятор площади
    if (!empty($_POST['custom_area_packs']) && !empty($_POST['custom_area_area_value'])) {
        $cart_item_data['custom_area_calc'] = [
            'packs' => intval($_POST['custom_area_packs']),
            'area' => floatval($_POST['custom_area_area_value']),
            'total_price' => floatval($_POST['custom_area_total_price']),
            'grand_total' => floatval($_POST['custom_area_grand_total'] ?? $_POST['custom_area_total_price']),
            'is_leaf' => $is_leaf_category,
            'painting_service' => $painting_service
        ];
        return $cart_item_data;
    }

    // 2. Калькулятор размеров (старый)
    if (!empty($_POST['custom_width_val']) && !empty($_POST['custom_length_val'])) {
        $cart_item_data['custom_dimensions'] = [
            'width' => intval($_POST['custom_width_val']),
            'length'=> intval($_POST['custom_length_val']),
            'price'=> floatval($_POST['custom_dim_price']),
            'grand_total' => floatval($_POST['custom_dim_grand_total'] ?? $_POST['custom_dim_price']),
            'is_leaf' => $is_leaf_category,
            'painting_service' => $painting_service
        ];
        return $cart_item_data;
    }

    // 3. НОВОЕ: Калькулятор с множителем (категории 265-271)
    if (!empty($_POST['custom_mult_width']) && !empty($_POST['custom_mult_length'])) {
        error_log('Adding multiplier calc to cart: ' . print_r($_POST, true));
        
        $cart_item_data['custom_multiplier_calc'] = [
            'width' => floatval($_POST['custom_mult_width']),
            'length' => floatval($_POST['custom_mult_length']),
            'quantity' => intval($_POST['custom_mult_quantity'] ?? 1),
            'area_per_item' => floatval($_POST['custom_mult_area_per_item']),
            'total_area' => floatval($_POST['custom_mult_total_area']),
            'multiplier' => floatval($_POST['custom_mult_multiplier']),
            'price' => floatval($_POST['custom_mult_price']),
            'grand_total' => floatval($_POST['custom_mult_grand_total'] ?? $_POST['custom_mult_price']),
            'painting_service' => $painting_service
        ];
        
        error_log('Multiplier calc data: ' . print_r($cart_item_data['custom_multiplier_calc'], true));
        return $cart_item_data;
    }

// 4. НОВОЕ: Калькулятор погонных метров (включая фальшбалки)
    if (!empty($_POST['custom_rm_length'])) {
        $rm_data = [
            'width' => floatval($_POST['custom_rm_width'] ?? 0),
            'length' => floatval($_POST['custom_rm_length']),
            'quantity' => intval($_POST['custom_rm_quantity'] ?? 1),
            'total_length' => floatval($_POST['custom_rm_total_length']),
            'painting_area' => floatval($_POST['custom_rm_painting_area'] ?? 0),
            'multiplier' => floatval($_POST['custom_rm_multiplier'] ?? 1),
            'price' => floatval($_POST['custom_rm_price']),
            'grand_total' => floatval($_POST['custom_rm_grand_total'] ?? $_POST['custom_rm_price']),
            'painting_service' => $painting_service
        ];
        
        // Дополнительные поля для фальшбалок
        if (!empty($_POST['custom_rm_shape'])) {
            $rm_data['shape'] = sanitize_text_field($_POST['custom_rm_shape']);
            $rm_data['shape_label'] = sanitize_text_field($_POST['custom_rm_shape_label']);
            $rm_data['height'] = floatval($_POST['custom_rm_height'] ?? 0);
        }
        
        $cart_item_data['custom_running_meter_calc'] = $rm_data;
        error_log('PM: Added running meter calc to cart - ' . print_r($rm_data, true));
        return $cart_item_data;
    }

    // 5. НОВОЕ: Калькулятор квадратных метров
    if (!empty($_POST['custom_sq_width']) && !empty($_POST['custom_sq_length'])) {
        $cart_item_data['custom_square_meter_calc'] = [
            'width' => floatval($_POST['custom_sq_width']),
            'length' => floatval($_POST['custom_sq_length']),
            'quantity' => intval($_POST['custom_sq_quantity'] ?? 1),
            'area_per_item' => floatval($_POST['custom_sq_area_per_item']),
            'total_area' => floatval($_POST['custom_sq_total_area']),
            'multiplier' => floatval($_POST['custom_sq_multiplier'] ?? 1),
            'price' => floatval($_POST['custom_sq_price']),
            'grand_total' => floatval($_POST['custom_sq_grand_total'] ?? $_POST['custom_sq_price']),
            'painting_service' => $painting_service
        ];
        return $cart_item_data;
    }

    // 6. Покупка из карточки товара
    if (!empty($_POST['card_purchase']) && $_POST['card_purchase'] === '1' && $pack_area > 0) {
        $cart_item_data['card_pack_purchase'] = [
            'area' => $pack_area,
            'price_per_m2' => $base_price_m2,
            'total_price' => $base_price_m2 * $pack_area,
            'is_leaf' => $is_leaf_category,
            'unit_type' => $is_leaf_category ? 'лист' : 'упаковка',
            'painting_service' => $painting_service
        ];
        return $cart_item_data;
    }

    // 7. Обычная покупка без калькулятора
    if ($pack_area > 0) {
        $cart_item_data['standard_pack_purchase'] = [
            'area' => $pack_area,
            'price_per_m2' => $base_price_m2,
            'total_price' => $base_price_m2 * $pack_area,
            'is_leaf' => $is_leaf_category,
            'unit_type' => $is_leaf_category ? 'лист' : 'упаковка',
            'painting_service' => $painting_service
        ];
    }

    return $cart_item_data;
}, 10, 3);



// --- Отображаем выбранные размеры/площадь в корзине и заказе ---
add_filter('woocommerce_get_item_data', function($item_data, $cart_item){
    // Данные схем покраски
    if (!empty($cart_item['pm_selected_scheme_name'])) {
        $item_data[] = [
            'name' => 'Схема покраски',
            'value' => $cart_item['pm_selected_scheme_name']
        ];
    }
    if (!empty($cart_item['pm_selected_color'])) {
        $color_display = $cart_item['pm_selected_color'];
        
        // Добавляем миниатюру изображения и код цвета
        if (!empty($cart_item['pm_selected_color_image'])) {
            $image_url = $cart_item['pm_selected_color_image'];
            $filename = !empty($cart_item['pm_selected_color_filename']) ? $cart_item['pm_selected_color_filename'] : '';
            
            $color_display = '<div style="display:flex; align-items:center; gap:10px;">';
            $color_display .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($filename) . '" style="width:40px; height:40px; object-fit:cover; border:2px solid #ddd; border-radius:4px;">';
            $color_display .= '<div>';
            $color_display .= '<div>' . esc_html($cart_item['pm_selected_color']) . '</div>';
            if ($filename) {
                $color_display .= '<div style="font-size:11px; color:#999;">Код: ' . esc_html($filename) . '</div>';
            }
            $color_display .= '</div>';
            $color_display .= '</div>';
        }
        
        $item_data[] = [
            'name' => 'Цвет',
            'value' => $color_display
        ];
    }
    
    if(isset($cart_item['custom_area_calc'])){
        $area_calc = $cart_item['custom_area_calc'];
        $is_leaf_category = $area_calc['is_leaf'];
        $unit_forms = $is_leaf_category ? ['лист', 'листа', 'листов'] : ['упаковка', 'упаковки', 'упаковок'];
        
        $plural = ($area_calc['packs'] % 10 === 1 && $area_calc['packs'] % 100 !== 11) ? $unit_forms[0] :
                  (($area_calc['packs'] % 10 >=2 && $area_calc['packs'] %10 <=4 && ($area_calc['packs'] %100 < 10 || $area_calc['packs'] %100 >= 20)) ? $unit_forms[1] : $unit_forms[2]);
        
        $display_text = $area_calc['area'] . ' м² (' . $area_calc['packs'] . ' ' . $plural . ') — ' . number_format($area_calc['total_price'], 2, '.', ' ') . ' ₽';
        
        if (isset($area_calc['painting_service']) && $area_calc['painting_service']) {
            $painting = $area_calc['painting_service'];
            // Используем имя с цветом, если есть
            $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
            $display_text .= '<br>+ ' . $painting_name . ' — ' . number_format($painting['total_cost'], 2, '.', ' ') . ' ₽';
        }
        
        $item_data[] = [
            'name'=>'Выбранная площадь',
            'value'=> $display_text
        ];
    }
    
    if(isset($cart_item['custom_dimensions'])){
        $dims = $cart_item['custom_dimensions'];
        $area = ($dims['width']/1000)*($dims['length']/1000);
        
        $display_text = $dims['width'].' мм × '.$dims['length'].' мм ('.round($area,3).' м²) — '.number_format($dims['price'], 2, '.', ' ').' ₽';
        
        if (isset($dims['painting_service']) && $dims['painting_service']) {
            $painting = $dims['painting_service'];
            // Используем имя с цветом, если есть
            $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
            $display_text .= '<br>+ ' . $painting_name . ' — ' . number_format($painting['total_cost'], 2, '.', ' ') . ' ₽';
        }
        
        $item_data[] = [
            'name'=>'Размеры',
            'value'=> $display_text
        ];
    }
    
    // Калькулятор погонных метров (включая фальшбалки)
    if(isset($cart_item['custom_running_meter_calc'])){
        $rm_calc = $cart_item['custom_running_meter_calc'];
        
        $display_text = '';
        
        // Если это фальшбалка - показываем форму сечения
        if (isset($rm_calc['shape_label'])) {
            $display_text .= 'Форма: ' . $rm_calc['shape_label'] . '<br>';
            if ($rm_calc['width'] > 0) $display_text .= 'Ширина: ' . $rm_calc['width'] . ' мм<br>';
            if (isset($rm_calc['height']) && $rm_calc['height'] > 0) $display_text .= 'Высота: ' . $rm_calc['height'] . ' мм<br>';
        } elseif ($rm_calc['width'] > 0) {
            $display_text .= 'Ширина: ' . $rm_calc['width'] . ' мм<br>';
        }
        
        $display_text .= 'Длина: ' . $rm_calc['length'] . ' м<br>';
        $display_text .= 'Общая длина: ' . $rm_calc['total_length'] . ' пог. м<br>';
        $display_text .= 'Стоимость: ' . number_format($rm_calc['price'], 2, '.', ' ') . ' ₽';
        
        if (isset($rm_calc['painting_service']) && $rm_calc['painting_service']) {
            $painting = $rm_calc['painting_service'];
            $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
            $display_text .= '<br>+ ' . $painting_name . ' — ' . number_format($painting['total_cost'], 2, '.', ' ') . ' ₽';
        }
        
        $item_data[] = [
            'name'=>'Параметры',
            'value'=> $display_text
        ];
    }
    

    // Покупки из карточек
    if(isset($cart_item['card_pack_purchase'])){
        $pack_data = $cart_item['card_pack_purchase'];
        $display_text = 'Площадь: ' . $pack_data['area'] . ' м² — ' . number_format($pack_data['total_price'], 2, '.', ' ') . ' ₽';
        
        if (isset($pack_data['painting_service']) && $pack_data['painting_service']) {
            $painting = $pack_data['painting_service'];
            // Используем имя с цветом, если есть
            $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
            $display_text .= '<br>+ ' . $painting_name . ' — ' . number_format($painting['total_cost'], 2, '.', ' ') . ' ₽';
        }
        
        $item_data[] = [
            'name' => 'В корзине ' . $pack_data['unit_type'],
            'value' => $display_text
        ];
    }
    
    // Обычные покупки упаковок/листов
    if(isset($cart_item['standard_pack_purchase'])){
        $pack_data = $cart_item['standard_pack_purchase'];
        $display_text = 'Площадь: ' . $pack_data['area'] . ' м² — ' . number_format($pack_data['total_price'], 2, '.', ' ') . ' ₽';
        
        if (isset($pack_data['painting_service']) && $pack_data['painting_service']) {
            $painting = $pack_data['painting_service'];
            // Используем имя с цветом, если есть
            $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
            $display_text .= '<br>+ ' . $painting_name . ' — ' . number_format($painting['total_cost'], 2, '.', ' ') . ' ₽';
        }
        
        $item_data[] = [
            'name' => 'В корзине ' . $pack_data['unit_type'],
            'value' => $display_text
        ];
    }
    
    return $item_data;
},10,2);

// --- Установка правильного количества при добавлении в корзину ---
add_filter('woocommerce_add_to_cart_quantity', function($quantity, $product_id) {
    if (!is_in_target_categories($product_id)) return $quantity;

    // ТОЛЬКО если калькулятор площади действительно использовался
    if (isset($_POST['custom_area_packs']) && !empty($_POST['custom_area_packs']) && 
        isset($_POST['custom_area_area_value']) && !empty($_POST['custom_area_area_value'])) {
        return intval($_POST['custom_area_packs']);
    }

    // ТОЛЬКО если калькулятор размеров использовался
    if (isset($_POST['custom_width_val']) && !empty($_POST['custom_width_val']) && 
        isset($_POST['custom_length_val']) && !empty($_POST['custom_length_val'])) {
        return 1;
    }
    
    // Для покупок из карточки товара или обычных покупок всегда возвращаем оригинальное количество
    return $quantity;
}, 10, 2);

// --- Дополнительно корректируем количество в корзине (только если используется калькулятор) ---
add_action('woocommerce_add_to_cart', function($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    if (!is_in_target_categories($product_id)) return;
    
    // Корректируем ТОЛЬКО если используется калькулятор площади
    if (isset($cart_item_data['custom_area_calc'])) {
        $packs = intval($cart_item_data['custom_area_calc']['packs']);
        if ($packs > 0 && $quantity !== $packs) {
            WC()->cart->set_quantity($cart_item_key, $packs);
        }
    }
}, 10, 6);

// --- Пересчёт цены в корзине ---
add_action('woocommerce_before_calculate_totals', function($cart){
    if(is_admin() && !defined('DOING_AJAX')) return;
    foreach($cart->get_cart() as $cart_item){
        $product = $cart_item['data'];
        
        // Калькулятор площади
        if(isset($cart_item['custom_area_calc'])){
            $area_calc = $cart_item['custom_area_calc'];
            $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());
            $pack_area = extract_area_with_qty($product->get_name(), $product->get_id());
            if($pack_area > 0) {
                $price_per_pack = $base_price_m2 * $pack_area;
                
                // Добавляем стоимость покраски к цене за упаковку, если есть
                if (isset($area_calc['painting_service']) && $area_calc['painting_service']) {
                    $painting = $area_calc['painting_service'];
                    $painting_cost_per_pack = $painting['total_cost'] / $area_calc['packs'];
                    $price_per_pack += $painting_cost_per_pack;
                }
                
                $product->set_price($price_per_pack);
            }
        } 
        // Калькулятор размеров
        elseif(isset($cart_item['custom_dimensions'])){
            $dims = $cart_item['custom_dimensions'];
            $total_price = $dims['price'];
            
            // Добавляем стоимость покраски, если есть
            if (isset($dims['painting_service']) && $dims['painting_service']) {
                $total_price += $dims['painting_service']['total_cost'];
            }
            
            $product->set_price($total_price);
        }
// Калькулятор множителя
        elseif(isset($cart_item['custom_multiplier_calc'])){
            $mult_calc = $cart_item['custom_multiplier_calc'];
            $total_price = $mult_calc['price'];
            
            if (isset($mult_calc['painting_service']) && $mult_calc['painting_service']) {
                $total_price += $mult_calc['painting_service']['total_cost'];
            }
            
            $product->set_price($total_price);
        }
        // Калькулятор погонных метров
        elseif(isset($cart_item['custom_running_meter_calc'])){
            $rm_calc = $cart_item['custom_running_meter_calc'];
            $total_price = $rm_calc['price'];
            
            if (isset($rm_calc['painting_service']) && $rm_calc['painting_service']) {
                $total_price += $rm_calc['painting_service']['total_cost'];
            }
            
            $product->set_price($total_price);
        }
        // Калькулятор квадратных метров
        elseif(isset($cart_item['custom_square_meter_calc'])){
            $sq_calc = $cart_item['custom_square_meter_calc'];
            $total_price = $sq_calc['price'];
            
            if (isset($sq_calc['painting_service']) && $sq_calc['painting_service']) {
                $total_price += $sq_calc['painting_service']['total_cost'];
            }
            
            $product->set_price($total_price);
        }
        // Покупки из карточек
        elseif(isset($cart_item['card_pack_purchase'])){
            $pack_data = $cart_item['card_pack_purchase'];
            $total_price = $pack_data['total_price'];
            
            // Добавляем стоимость покраски, если есть
            if (isset($pack_data['painting_service']) && $pack_data['painting_service']) {
                $total_price += $pack_data['painting_service']['total_cost'];
            }
            
            $product->set_price($total_price);
        }
        // Обычные покупки упаковок/листов БЕЗ калькулятора
        elseif(isset($cart_item['standard_pack_purchase'])){
            $pack_data = $cart_item['standard_pack_purchase'];
            $total_price = $pack_data['total_price'];
            
            // Добавляем стоимость покраски, если есть
            if (isset($pack_data['painting_service']) && $pack_data['painting_service']) {
                $total_price += $pack_data['painting_service']['total_cost'];
            }
            
            $product->set_price($total_price);
        }
    }
});

// --- Добавляем информацию о покраске в заказ ---
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    // Данные схем покраски из pm-paint-schemes.php
    if (!empty($values['pm_selected_scheme_name'])) {
        // Формируем название схемы с кодом цвета
        $scheme_with_color = $values['pm_selected_scheme_name'];
        
        if (!empty($values['pm_selected_color_filename'])) {
            $scheme_with_color .= ' "' . $values['pm_selected_color_filename'] . '"';
        }
        
        $item->add_meta_data('Схема покраски', $scheme_with_color, true);
    }
    
    if (!empty($values['pm_selected_color_image'])) {
        $item->add_meta_data('_pm_color_image_url', $values['pm_selected_color_image'], true);
    }
    
    if (!empty($values['pm_selected_color_filename'])) {
        $item->add_meta_data('Код цвета', $values['pm_selected_color_filename'], true);
    }
    
    // Калькулятор площади
    if (isset($values['custom_area_calc']) && isset($values['custom_area_calc']['painting_service']) && $values['custom_area_calc']['painting_service']) {
        $painting = $values['custom_area_calc']['painting_service'];
        $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
        $item->add_meta_data('Услуга покраски', $painting_name . ' (' . $painting['area'] . ' м² × ' . $painting['price_per_m2'] . ' ₽/м²)', true);
    }
    
    // Калькулятор размеров
    if (isset($values['custom_dimensions']) && isset($values['custom_dimensions']['painting_service']) && $values['custom_dimensions']['painting_service']) {
        $painting = $values['custom_dimensions']['painting_service'];
        $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
        $item->add_meta_data('Услуга покраски', $painting_name . ' (' . $painting['area'] . ' м² × ' . $painting['price_per_m2'] . ' ₽/м²)', true);
    }
    
    // Покупки из карточек
    if (isset($values['card_pack_purchase']) && isset($values['card_pack_purchase']['painting_service']) && $values['card_pack_purchase']['painting_service']) {
        $painting = $values['card_pack_purchase']['painting_service'];
        $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
        $item->add_meta_data('Услуга покраски', $painting_name . ' (' . $painting['area'] . ' м² × ' . $painting['price_per_m2'] . ' ₽/м²)', true);
    }
    
    // Обычные покупки
    if (isset($values['standard_pack_purchase']) && isset($values['standard_pack_purchase']['painting_service']) && $values['standard_pack_purchase']['painting_service']) {
        $painting = $values['standard_pack_purchase']['painting_service'];
        $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
        $item->add_meta_data('Услуга покраски', $painting_name . ' (' . $painting['area'] . ' м² × ' . $painting['price_per_m2'] . ' ₽/м²)', true);
    }
}, 10, 4);

// === Отображение изображения цвета в заказе (админка и email) ===
add_filter('woocommerce_order_item_display_meta_key', function($display_key, $meta, $item) {
    if ($meta->key === '_pm_color_image_url') {
        return 'Образец цвета';
    }
    return $display_key;
}, 10, 3);

add_filter('woocommerce_order_item_display_meta_value', function($display_value, $meta, $item) {
    if ($meta->key === '_pm_color_image_url') {
        $image_url = $meta->value;
        return '<img src="' . esc_url($image_url) . '" style="width:60px; height:60px; object-fit:cover; border:2px solid #ddd; border-radius:4px; display:block; margin-top:5px;">';
    }
    return $display_value;
}, 10, 3);



add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    $sources = ['custom_area_calc', 'custom_dimensions', 'card_pack_purchase', 'standard_pack_purchase'];

    foreach ($sources as $key) {
        if (!empty($values[$key]['painting_service'])) {
            $painting = $values[$key]['painting_service'];
            
            // Если уже есть name_with_color, используем его
            $painting_name = $painting['name_with_color'] ?? $painting['name'];

            // Если цвет отдельно, добавляем его к имени без дублирования
            if (!empty($values['pm_selected_color'])) {
                $color = $values['pm_selected_color'];
                if (strpos($painting_name, $color) === false) {
                    $painting_name .= ' "' . $color . '"';
                }
            }

            $item->add_meta_data('Услуга покраски', $painting_name, true);
            break; // берём только первую найденную услугу
        }
    }

    if (!empty($values['pm_selected_color_image'])) {
        $item->add_meta_data('_pm_color_image_url', $values['pm_selected_color_image'], true);
    }
}, 10, 4);




// --- Добавляем выбранный цвет к имени услуги покраски ---
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id){
    $sources = ['custom_area_calc', 'custom_dimensions', 'card_pack_purchase', 'standard_pack_purchase'];

    foreach ($sources as $key) {
        if (!empty($cart_item_data[$key]['painting_service'])) {
            // Берем выбранный цвет
            $color = '';
            if (!empty($_POST['pm_selected_color_filename'])) {
                $color = sanitize_text_field($_POST['pm_selected_color_filename']);
            } elseif (!empty($_POST['pm_selected_color'])) {
                $color = sanitize_text_field($_POST['pm_selected_color']);
                // Если прилетел URL, берем имя файла
                if (filter_var($color, FILTER_VALIDATE_URL)) {
                    $color = pathinfo($color, PATHINFO_FILENAME);
                    $color = preg_replace('/(_180|-1)$/', '', $color);
                }
            }

            if ($color) {
                // Добавляем цвет к имени услуги
                $cart_item_data[$key]['painting_service']['name_with_color'] = $cart_item_data[$key]['painting_service']['name'] . ' "' . $color . '"';
                // Сохраняем сам цвет отдельно для использования в заказе
                $cart_item_data['pm_selected_color'] = $color;
            }
        }
    }

    return $cart_item_data;
}, 10, 3);




// ===============================
// РАСШИРЕНИЕ ДЛЯ УСЛУГ ПОКРАСКИ
// ===============================

// Регистрация полей ACF для услуг покраски
function register_painting_services_acf_fields() {
    if (!function_exists('acf_add_local_field_group')) return;
    
    // Поля для категорий товаров
    acf_add_local_field_group(array(
        'key' => 'group_painting_services_category',
        'title' => 'Услуги покраски для категории',
        'fields' => array(
            array(
                'key' => 'field_dop_uslugi_category',
                'label' => 'Доступные услуги покраски',
                'name' => 'dop_uslugi',
                'type' => 'repeater',
                'instructions' => 'Настройте доступные виды покраски для этой категории товаров',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'collapsed' => 'field_name_usluga_category',
                'min' => 0,
                'max' => 0,
                'layout' => 'table',
                'button_label' => 'Добавить услугу покраски',
                'sub_fields' => array(
                    array(
                        'key' => 'field_name_usluga_category',
                        'label' => 'Название услуги',
                        'name' => 'name_usluga',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '70',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'placeholder' => 'Например: Покраска натуральным маслом',
                        'prepend' => '',
                        'append' => '',
                        'maxlength' => '',
                    ),
                    array(
                        'key' => 'field_price_usluga_category',
                        'label' => 'Цена (руб/м²)',
                        'name' => 'price_usluga',
                        'type' => 'number',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '30',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'placeholder' => '650',
                        'prepend' => '',
                        'append' => 'руб/м²',
                        'min' => 0,
                        'max' => '',
                        'step' => 50,
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'taxonomy',
                    'operator' => '==',
                    'value' => 'product_cat',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => '',
    ));

    // Поля для отдельных товаров (переопределение)
    acf_add_local_field_group(array(
        'key' => 'group_painting_services_product',
        'title' => 'Индивидуальные услуги покраски',
        'fields' => array(
            array(
                'key' => 'field_use_individual_services',
                'label' => 'Использовать индивидуальные услуги',
                'name' => 'use_individual_services',
                'type' => 'true_false',
                'instructions' => 'Включите, если хотите настроить услуги покраски индивидуально для этого товара, игнорируя настройки категории',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'message' => '',
                'default_value' => 0,
                'ui' => 1,
                'ui_on_text' => 'Да',
                'ui_off_text' => 'Нет',
            ),
            array(
                'key' => 'field_dop_uslugi_product',
                'label' => 'Услуги покраски для товара',
                'name' => 'dop_uslugi',
                'type' => 'repeater',
                'instructions' => 'Настройте индивидуальные услуги покраски для этого товара',
                'required' => 0,
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_use_individual_services',
                            'operator' => '==',
                            'value' => '1',
                        ),
                    ),
                ),
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'collapsed' => 'field_name_usluga_product',
                'min' => 0,
                'max' => 0,
                'layout' => 'table',
                'button_label' => 'Добавить услугу покраски',
                'sub_fields' => array(
                    array(
                        'key' => 'field_name_usluga_product',
                        'label' => 'Название услуги',
                        'name' => 'name_usluga',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '70',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'placeholder' => 'Например: Покраска натуральным маслом',
                        'prepend' => '',
                        'append' => '',
                        'maxlength' => '',
                    ),
                    array(
                        'key' => 'field_price_usluga_product',
                        'label' => 'Цена (руб/м²)',
                        'name' => 'price_usluga',
                        'type' => 'number',
                        'instructions' => '',
                        'required' => 1,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '30',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'placeholder' => '650',
                        'prepend' => '',
                        'append' => 'руб/м²',
                        'min' => 0,
                        'max' => '',
                        'step' => 50,
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'product',
                ),
            ),
        ),
        'menu_order' => 20,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => '',
    ));

    // Глобальные настройки (на случай, если нет настроек для категории)
    acf_add_local_field_group(array(
        'key' => 'group_global_painting_services',
        'title' => 'Глобальные услуги покраски',
        'fields' => array(
            array(
                'key' => 'field_dop_uslugi_global',
                'label' => 'Услуги покраски по умолчанию',
                'name' => 'global_dop_uslugi',
                'type' => 'repeater',
                'instructions' => 'Услуги покраски по умолчанию (используются, если не настроены для категории или товара)',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'collapsed' => 'field_name_usluga_global',
                'min' => 0,
                'max' => 0,
                'layout' => 'table',
                'button_label' => 'Добавить услугу покраски',
                'sub_fields' => array(
                    array(
                        'key' => 'field_name_usluga_global',
                        'label' => 'Название услуги',
                        'name' => 'name_usluga',
                        'type' => 'text',
                        'required' => 1,
                        'wrapper' => array('width' => '70'),
                        'placeholder' => 'Например: Покраска натуральным маслом',
                    ),
                    array(
                        'key' => 'field_price_usluga_global',
                        'label' => 'Цена (руб/м²)',
                        'name' => 'price_usluga',
                        'type' => 'number',
                        'required' => 1,
                        'wrapper' => array('width' => '30'),
                        'placeholder' => '650',
                        'append' => 'руб/м²',
                        'min' => 0,
                        'step' => 50,
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'theme-general-settings',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
    ));
}
add_action('acf/init', 'register_painting_services_acf_fields');

// Создание страницы настроек темы для глобальных услуг
function create_theme_options_page() {
    if (function_exists('acf_add_options_page')) {
        acf_add_options_page(array(
            'page_title' => 'Настройки услуг покраски',
            'menu_title' => 'Услуги покраски',
            'menu_slug' => 'theme-general-settings',
            'capability' => 'edit_posts',
            'icon_url' => 'dashicons-art',
            'position' => 30,
        ));
    }
}
add_action('acf/init', 'create_theme_options_page');

// Получение доступных услуг покраски для товара (совместимая версия)
function get_acf_painting_services($product_id) {
    // 1. Проверяем индивидуальные настройки товара
    $use_individual = get_field('use_individual_services', $product_id);
    if ($use_individual) {
        $services = get_field('dop_uslugi', $product_id);
        if (!empty($services)) {
            error_log("Using INDIVIDUAL painting services for product {$product_id}");
            return $services;
        }
    }
    
    // 2. Получаем услуги из категорий товара с приоритетом более конкретных категорий
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'all']);
    if (!is_wp_error($product_categories) && !empty($product_categories)) {
        
        // ВАЖНО: Сортируем категории по глубине вложенности (от более конкретных к общим)
        usort($product_categories, function($a, $b) {
            $depth_a = count(get_ancestors($a->term_id, 'product_cat'));
            $depth_b = count(get_ancestors($b->term_id, 'product_cat'));
            return $depth_b - $depth_a; // От более вложенных к менее вложенным
        });
        
        // Ищем услуги, начиная с самых конкретных категорий
        foreach ($product_categories as $category) {
            $services = get_field('dop_uslugi', 'product_cat_' . $category->term_id);
            if (!empty($services)) {
                error_log("Using painting services from category: {$category->name} (ID: {$category->term_id}, depth: " . count(get_ancestors($category->term_id, 'product_cat')) . ")");
                return $services;
            }
        }
    }
    
    // 3. Используем глобальные настройки
    $global_services = get_field('global_dop_uslugi', 'option');
    if (!empty($global_services)) {
        error_log("Using GLOBAL painting services for product {$product_id}");
        return $global_services;
    }
    
    // 4. Возвращаем пустой массив, если ничего не настроено
    error_log("No painting services found for product {$product_id}");
    return [];
}

// Функция для получения услуг покраски в формате, совместимом с существующим кодом
function get_available_painting_services_by_material($product_id) {
    $acf_services = get_acf_painting_services($product_id);
    $formatted_services = [];
    
    foreach ($acf_services as $index => $service) {
        $key = 'service_' . sanitize_title($service['name_usluga']);
        $formatted_services[$key] = [
            'name' => $service['name_usluga'],
            'price' => floatval($service['price_usluga'])
        ];
    }
    
    return $formatted_services;
}

// Функция для предзаполнения услуг покраски по умолчанию
function populate_default_painting_services() {
    $default_services = [
        ['name_usluga' => 'Покраска натуральным маслом', 'price_usluga' => 1700],
        ['name_usluga' => 'Покраска Воском', 'price_usluga' => 650],
        ['name_usluga' => 'Покраска Укрывная', 'price_usluga' => 650],
        ['name_usluga' => 'Покраска Гидромаслом', 'price_usluga' => 1050],
        ['name_usluga' => 'Покраска Лаком', 'price_usluga' => 650],
        ['name_usluga' => 'Покраска Лазурью', 'price_usluga' => 650],
        ['name_usluga' => 'Покраска Винтаж', 'price_usluga' => 1050],
        ['name_usluga' => 'Покраска Пропиткой', 'price_usluga' => 650],
    ];
    
    update_field('global_dop_uslugi', $default_services, 'option');
}

















// --- JavaScript для карточек товаров ---
add_action('wp_footer', function() {
    // Проверяем, что мы НЕ на странице отдельного товара
    if (is_product()) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Более широкий поиск кнопок "Купить" для всех типов карточек и слайдеров
        function processButtons() {
            const buyButtons = document.querySelectorAll('a.add_to_cart_button:not(.product_type_variable), .add_to_cart_button:not(.product_type_variable), a[data-product_id]:not(.product_type_variable)');
            
            buyButtons.forEach(function(button) {
                // Пропускаем кнопки, которые уже обработаны
                if (button.dataset.cardProcessed) return;
                button.dataset.cardProcessed = 'true';
                
                const productId = button.dataset.product_id || button.getAttribute('data-product_id');
                if (!productId) return;
                
                // Создаем скрытую форму для отправки данных
                const form = document.createElement('form');
                form.style.display = 'none';
                form.method = 'POST';
                form.action = button.href || window.location.href;
                
                // Добавляем скрытые поля
                const fields = [
                    { name: 'add-to-cart', value: productId },
                    { name: 'product_id', value: productId },
                    { name: 'card_purchase', value: '1' }
                ];
                
                fields.forEach(field => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = field.name;
                    input.value = field.value;
                    form.appendChild(input);
                });
                
                document.body.appendChild(form);
                
                // Заменяем обработчик клика
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Отправляем форму
                    form.submit();
                });
            });
        }
        
        // Обрабатываем кнопки сразу
        processButtons();
        
        // Обрабатываем кнопки после загрузки слайдеров (через небольшую задержку)
        setTimeout(processButtons, 1000);
        
        // Обрабатываем кнопки при изменении DOM (для динамических слайдеров)
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    processButtons();
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
    </script>
    <?php
});





// ===============================
// Исправление отображения в корзине
// ===============================

// Вспомогательная функция для склонения
function get_russian_plural_for_cart($n, $forms) {
    $n = abs($n);
    $n %= 100;
    if ($n > 10 && $n < 20) return $forms[2];
    $n %= 10;
    if ($n === 1) return $forms[0];
    if ($n >= 2 && $n <= 4) return $forms[1];
    return $forms[2];
}




// --- Изменяем отображение цены в корзине для наших товаров ---
add_filter('woocommerce_cart_item_price', function($price, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];
    $product_id = $product->get_id();
    
    // Проверяем, что это наш товар
    if (!is_in_target_categories($product_id)) {
        return $price;
    }
    
    // Если товар добавлен из карточки или через калькуляторы
    if (isset($cart_item['card_pack_purchase']) || 
        isset($cart_item['custom_area_calc']) || 
        isset($cart_item['custom_dimensions'])) {
        
        $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());
        $current_price = floatval($product->get_price());
        
        // Определяем тип товара для правильного отображения единицы
        $leaf_parent_id = 190;
        $leaf_children = [191, 127, 94];
        $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
        $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
        $unit_text = $is_leaf_category ? 'лист' : 'упаковка';
        
        // Показываем цену за упаковку/лист и базовую цену за м²
        return wc_price($current_price) . ' за ' . $unit_text . '<br>' .
               '<small style="color: #666;">' . wc_price($base_price_m2) . ' за м²</small>';
    }
    
    return $price;
}, 10, 3);

// --- Изменяем отображение итоговой цены в строке корзины ---
add_filter('woocommerce_cart_item_subtotal', function($subtotal, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];
    $product_id = $product->get_id();
    
    // Проверяем, что это наш товар
    if (!is_in_target_categories($product_id)) {
        return $subtotal;
    }
    
    // Если товар добавлен из карточки или через калькуляторы
    if (isset($cart_item['card_pack_purchase']) || 
        isset($cart_item['custom_area_calc']) || 
        isset($cart_item['custom_dimensions'])) {
        
        $quantity = $cart_item['quantity'];
        $current_price = floatval($product->get_price());
        $total = $current_price * $quantity;
        
        // Определяем тип товара
        $leaf_parent_id = 190;
        $leaf_children = [191, 127, 94];
        $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
        $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
        $unit_forms = $is_leaf_category ? ['лист', 'листа', 'листов'] : ['упаковка', 'упаковки', 'упаковок'];
        
        $plural = get_russian_plural_for_cart($quantity, $unit_forms);
        
        // Показываем итоговую сумму с указанием количества единиц
        return '<strong>' . wc_price($total) . '</strong><br>' .
               '<small style="color: #666;">' . $quantity . ' ' . $plural . '</small>';
    }
    
    return $subtotal;
}, 10, 3);

// --- Дополнительно: исправляем отображение в мини-корзине (виджет) ---
add_filter('woocommerce_widget_cart_item_quantity', function($quantity, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];
    $product_id = $product->get_id();
    
    // Проверяем, что это наш товар
    if (!is_in_target_categories($product_id)) {
        return $quantity;
    }
    
    // Если товар добавлен из карточки или через калькуляторы
    if (isset($cart_item['card_pack_purchase']) || 
        isset($cart_item['custom_area_calc']) || 
        isset($cart_item['custom_dimensions'])) {
        
        $qty = $cart_item['quantity'];
        $current_price = floatval($product->get_price());
        $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());
        
        // Определяем тип товара
        $leaf_parent_id = 190;
        $leaf_children = [191, 127, 94];
        $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
        $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
        $unit_forms = $is_leaf_category ? ['лист', 'листа', 'листов'] : ['упаковка', 'упаковки', 'упаковок'];
        
        $plural = get_russian_plural_for_cart($qty, $unit_forms);
        
        // Возвращаем количество с единицей измерения и ценой за м²
        return '<span class="quantity">' . $qty . ' ' . $plural . ' × ' . wc_price($current_price) . '</span><br>' .
               '<small style="color: #999; font-size: 0.9em;">(' . wc_price($base_price_m2) . ' за м²)</small>';
    }
    
    return $quantity;
}, 10, 3);

require_once get_stylesheet_directory() . '/inc/pm-paint-schemes.php';












// -----------------------
// код ЛК, полей, меню и корзины
// -----------------------
//parusweb
// Отключенеие цифровых товаров и удаление платежного адреса
add_filter( 'woocommerce_account_menu_items', 'remove_my_account_downloads', 999 );
function remove_my_account_downloads( $items ) {
    unset( $items['downloads'] );
    return $items;
}

// Переименовать пункт меню "Адреса" в "Адрес доставки"
add_filter( 'woocommerce_account_menu_items', function( $items ) {
    if ( isset( $items['edit-address'] ) ) {
        $items['edit-address'] = 'Адрес доставки';
    }
    return $items;
});

// Убрать заголовок "Платёжный адрес", оставить "Адрес доставки"
add_filter( 'woocommerce_my_account_my_address_title', function( $title, $address_type, $customer_id ) {
    if ( $address_type === 'billing' ) {
        return ''; 
    }
    if ( $address_type === 'shipping' ) {
        return 'Адрес доставки';
    }
    return $title;
}, 10, 3 );

// Скрыть блок платёжного адреса на странице "Адреса"
add_filter( 'woocommerce_my_account_get_addresses', function( $addresses, $customer_id ) {
    unset( $addresses['billing'] ); 
    return $addresses;
}, 10, 2 );

// Шорткод для количества товаров в категории
function wc_product_count_by_cat_id($atts) {
    $atts = shortcode_atts( array(
        'id' => 0,
    ), $atts, 'wc_cat_count_id' );

    $cat_id = intval($atts['id']);
    if ($cat_id <= 0) return '';

    $term = get_term($cat_id, 'product_cat');
    if (!$term || is_wp_error($term)) return '';

    $count = $term->count;

    return ($count > 0) ? $count : 'нет';
}
add_shortcode('wc_cat_count_id', 'wc_product_count_by_cat_id');

// -----------------------
// Кастомизация Личного Кабинета WooCommerce
// -----------------------
add_filter( 'woocommerce_account_menu_items', function( $items ) {
    if ( isset( $items['edit-account'] ) ) {
        $items['edit-account'] = 'Мои данные';
    }

    if ( isset( $items['orders'] ) ) {
        unset( $items['orders'] );
    }

    $items['cart'] = 'Корзина';
    $items['orders'] = 'Мои заказы';

    return $items;
});

// Регистрация endpoint "Корзина"
add_action( 'init', function() {
    add_rewrite_endpoint( 'cart', EP_ROOT | EP_PAGES );
});

// Вывод корзины во вкладке "Корзина"
add_action( 'woocommerce_account_cart_endpoint', function() {
    echo do_shortcode('[woocommerce_cart]');
});

// Поля типа клиента и реквизитов в ЛК
add_action( 'woocommerce_edit_account_form', function() {
    $user_id = get_current_user_id();
    $client_type = get_user_meta( $user_id, 'client_type', true );

    $fields = [
        'billing_full_name'      => 'Полное наименование (или ФИО предпринимателя)',
        'billing_short_name'     => 'Краткое наименование',
        'billing_legal_address'  => 'Юридический адрес',
        'billing_fact_address'   => 'Фактический адрес',
        'billing_inn'            => 'ИНН',
        'billing_kpp'            => 'КПП (только для юрлиц)',
        'billing_ogrn'           => 'ОГРН / ОГРНИП',
        'billing_director'       => 'Должность и ФИО руководителя',
        'billing_buh'            => 'ФИО главного бухгалтера',
        'billing_dover'          => 'Лицо по доверенности',
        'billing_bank'           => 'Наименование банка',
        'billing_bik'            => 'БИК',
        'billing_korr'           => 'Корреспондентский счёт',
        'billing_rs'             => 'Расчётный счёт',
    ];
    ?>
    <p class="form-row form-row-wide">
        <label for="client_type">Тип клиента</label>
        <select name="client_type" id="client_type">
            <option value="fiz" <?php selected( $client_type, 'fiz' ); ?>>Физическое лицо</option>
            <option value="jur" <?php selected( $client_type, 'jur' ); ?>>Юридическое лицо / ИП</option>
        </select>
    </p>

    <div id="jur-fields" style="<?php echo $client_type === 'jur' ? '' : 'display:none;'; ?>">
        <?php foreach ( $fields as $meta_key => $label ) : 
            $val = get_user_meta( $user_id, $meta_key, true );
        ?>
            <p class="form-row form-row-wide">
                <label for="<?php echo $meta_key; ?>"><?php echo esc_html( $label ); ?></label>
                <input type="text" name="<?php echo $meta_key; ?>" id="<?php echo $meta_key; ?>" value="<?php echo esc_attr( $val ); ?>"
                    <?php echo $meta_key === 'billing_inn' ? 'class="inn-lookup"' : ''; ?>>
            </p>
        <?php endforeach; ?>
        <button type="button" id="inn-lookup-btn">Заполнить по ИНН</button>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('client_type');
        const jurFields = document.getElementById('jur-fields');
        const innField = document.getElementById('billing_inn');
        const lookupBtn = document.getElementById('inn-lookup-btn');
        
        select.addEventListener('change', function() {
            if (this.value === 'jur') {
                jurFields.style.display = '';
            } else {
                jurFields.style.display = 'none';
            }
        });

        // Подтягивание данных по ИНН
        lookupBtn.addEventListener('click', function() {
            const inn = innField.value.trim();
            if (!inn) {
                alert('Введите ИНН');
                return;
            }

            lookupBtn.disabled = true;
            lookupBtn.textContent = 'Загрузка...';

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=inn_lookup&inn=' + encodeURIComponent(inn)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const info = data.data;
                    if (info.full_name) document.getElementById('billing_full_name').value = info.full_name;
                    if (info.short_name) document.getElementById('billing_short_name').value = info.short_name;
                    if (info.legal_address) document.getElementById('billing_legal_address').value = info.legal_address;
                    if (info.kpp) document.getElementById('billing_kpp').value = info.kpp;
                    if (info.ogrn) document.getElementById('billing_ogrn').value = info.ogrn;
                    if (info.director) document.getElementById('billing_director').value = info.director;
                } else {
                    alert('Ошибка получения данных: ' + (data.data || 'Неизвестная ошибка'));
                }
            })
            .catch(error => {
                alert('Ошибка запроса: ' + error.message);
            })
            .finally(() => {
                lookupBtn.disabled = false;
                lookupBtn.textContent = 'Заполнить по ИНН';
            });
        });
    });
    </script>
    <?php
});

// Сохранение полей в ЛК
add_action( 'woocommerce_save_account_details', function( $user_id ) {
    if ( isset( $_POST['client_type'] ) ) {
        update_user_meta( $user_id, 'client_type', sanitize_text_field( $_POST['client_type'] ) );
    }
    $fields = [
        'billing_full_name', 'billing_short_name', 'billing_legal_address', 'billing_fact_address',
        'billing_inn', 'billing_kpp', 'billing_ogrn',
        'billing_director', 'billing_buh', 'billing_dover', 'billing_bank', 'billing_bik',
        'billing_korr', 'billing_rs'
    ];
    foreach ( $fields as $field ) {
        if ( isset( $_POST[$field] ) ) {
            update_user_meta( $user_id, $field, sanitize_text_field( $_POST[$field] ) );
        }
    }
});

// Меню ЛК
add_filter( 'woocommerce_account_menu_items', function( $items ) {
    return [
        'dashboard'       => 'Панель управления',
        'orders'          => 'Заказы',
        'edit-account'    => 'Мои данные',
        'edit-address'    => 'Адрес доставки',
    ];
}, 20 );

// Панель управления — плитки
add_action( 'woocommerce_account_dashboard', function() {
    $orders_url = esc_url( wc_get_account_endpoint_url('orders') );
    $account_url = esc_url( wc_get_account_endpoint_url('edit-account') );
    $address_url = esc_url( wc_get_account_endpoint_url('edit-address') );
    ?>
    <br>
    <div class="lk-tiles">
        <a href="<?php echo $orders_url; ?>" class="lk-tile" aria-label="Заказы">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" aria-hidden="true" focusable="false"><path d="M411.883 127.629h-310.08c-18.313 0-33.227 14.921-33.227 33.26v190.095c0 18.332 14.914 33.253 33.227 33.253h310.08c18.32 0 33.24-14.921 33.24-33.253V160.889c-.002-18.34-14.92-33.26-33.24-33.26zM311.34 293.18h-110.67v-27.57h110.67v27.57zm86.11-67.097H115.83v-24.64h281.62v24.64z"/></svg>
            <br>Заказы
        </a>
        <a href="<?php echo $account_url; ?>" class="lk-tile" aria-label="Мои данные">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 12c2.7 0 4.85-2.15 4.85-4.85S14.7 2.3 12 2.3 7.15 4.45 7.15 7.15 9.3 12 12 12zm0 2.7c-3.15 0-9.45 1.6-9.45 4.85v2.15h18.9v-2.15c0-3.25-6.3-4.85-9.45-4.85z"/></svg>
            <br>Мои данные
        </a>
        <a href="<?php echo $address_url; ?>" class="lk-tile" aria-label="Адрес доставки">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5z"/></svg>
            <br>Адрес доставки
        </a>
    </div>
    <?php
});

// Страница заказов — мини-корзина + заказы
add_action( 'woocommerce_account_orders_endpoint', function() {
    echo do_shortcode('[woocommerce_cart]');
}, 5 );

// Поле телефона после фамилии в ЛК
add_action( 'woocommerce_edit_account_form', function() {
    $user_id = get_current_user_id();
    $phone = get_user_meta( $user_id, 'billing_phone', true );
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const lastNameField = document.querySelector('p.woocommerce-form-row.form-row-last');
        if (lastNameField) {
            const phoneField = document.createElement('p');
            phoneField.className = 'woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide';
            phoneField.innerHTML = `
                <label for="account_billing_phone"><?php echo esc_js( __( 'Телефон', 'woocommerce' ) ); ?></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_billing_phone" id="account_billing_phone" value="<?php echo esc_js( $phone ); ?>" />
            `;
            lastNameField.after(phoneField);
        }
    });
    </script>
    <?php
});

// Валидация телефона
add_action( 'woocommerce_save_account_details_errors', function( $args, $user ) {
    if ( isset( $_POST['account_billing_phone'] ) ) {
        $phone = trim( $_POST['account_billing_phone'] );
        if ( $phone === '' ) {
            $args->add( 'error', __( 'Пожалуйста, укажите телефон.', 'woocommerce' ) );
        } elseif ( ! preg_match( '/^[\d\+\-\(\) ]+$/', $phone ) ) {
            $args->add( 'error', __( 'Телефон должен содержать только цифры, +, -, пробелы и скобки.', 'woocommerce' ) );
        }
    }
}, 10, 2 );

// Сохранение телефона
add_action( 'woocommerce_save_account_details', function( $user_id ) {
    if ( isset( $_POST['account_billing_phone'] ) ) {
        update_user_meta( $user_id, 'billing_phone', sanitize_text_field( $_POST['account_billing_phone'] ) );
    }
});

// -----------------------
// Кастомизация регистрации
// -----------------------

// Добавление полей в форму регистрации
add_action( 'woocommerce_register_form_start', function() {
    ?>
    <p class="form-row form-row-wide">
        <label for="reg_client_type">Тип клиента <span class="required">*</span></label>
        <select name="client_type" id="reg_client_type" required>
            <option value="">Выберите тип клиента</option>
            <option value="fiz">Физическое лицо</option>
            <option value="jur">Юридическое лицо / ИП</option>
        </select>
    </p>

    <div id="reg-jur-fields" style="display:none;">
        <p class="form-row form-row-wide">
            <label for="reg_billing_inn">ИНН <span class="required">*</span></label>
            <input type="text" class="input-text" name="billing_inn" id="reg_billing_inn" />
        </p>
        <p class="form-row form-row-wide">
            <button type="button" id="reg-inn-lookup-btn">Заполнить по ИНН</button>
        </p>
        <p class="form-row form-row-wide">
            <label for="reg_billing_full_name">Полное наименование</label>
            <input type="text" class="input-text" name="billing_full_name" id="reg_billing_full_name" />
        </p>
        <p class="form-row form-row-wide">
            <label for="reg_billing_short_name">Краткое наименование</label>
            <input type="text" class="input-text" name="billing_short_name" id="reg_billing_short_name" />
        </p>
        <p class="form-row form-row-wide">
            <label for="reg_billing_legal_address">Юридический адрес</label>
            <input type="text" class="input-text" name="billing_legal_address" id="reg_billing_legal_address" />
        </p>
        <p class="form-row form-row-wide">
            <label for="reg_billing_kpp">КПП</label>
            <input type="text" class="input-text" name="billing_kpp" id="reg_billing_kpp" />
        </p>
        <p class="form-row form-row-wide">
            <label for="reg_billing_ogrn">ОГРН / ОГРНИП</label>
            <input type="text" class="input-text" name="billing_ogrn" id="reg_billing_ogrn" />
        </p>
        <p class="form-row form-row-wide">
            <label for="reg_billing_director">Должность и ФИО руководителя</label>
            <input type="text" class="input-text" name="billing_director" id="reg_billing_director" />
        </p>
    </div>
    <?php
});

// JavaScript для формы регистрации
add_action( 'woocommerce_register_form_end', function() {
    ?>
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const regSelect = document.getElementById('reg_client_type');
        const regJurFields = document.getElementById('reg-jur-fields');
        const regInnField = document.getElementById('reg_billing_inn');
        const regLookupBtn = document.getElementById('reg-inn-lookup-btn');
        
        if (!regSelect) return;
        
        regSelect.addEventListener('change', function() {
            if (this.value === 'jur') {
                regJurFields.style.display = 'block';
            } else {
                regJurFields.style.display = 'none';
            }
        });

        if (regLookupBtn) {
            regLookupBtn.addEventListener('click', function() {
                const inn = regInnField.value.trim();
                if (!inn) {
                    alert('Введите ИНН');
                    return;
                }

                regLookupBtn.disabled = true;
                regLookupBtn.textContent = 'Загрузка...';

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=inn_lookup&inn=' + encodeURIComponent(inn)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const info = data.data;
                        const fullNameField = document.getElementById('reg_billing_full_name');
                        const shortNameField = document.getElementById('reg_billing_short_name');
                        const legalAddressField = document.getElementById('reg_billing_legal_address');
                        const kppField = document.getElementById('reg_billing_kpp');
                        const ogrnField = document.getElementById('reg_billing_ogrn');
                        const directorField = document.getElementById('reg_billing_director');
                        
                        if (info.full_name && fullNameField) fullNameField.value = info.full_name;
                        if (info.short_name && shortNameField) shortNameField.value = info.short_name;
                        if (info.legal_address && legalAddressField) legalAddressField.value = info.legal_address;
                        if (info.kpp && kppField) kppField.value = info.kpp;
                        if (info.ogrn && ogrnField) ogrnField.value = info.ogrn;
                        if (info.director && directorField) directorField.value = info.director;
                        
                        alert('Данные успешно загружены');
                    } else {
                        alert('Ошибка получения данных: ' + (data.data || 'Неизвестная ошибка'));
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Ошибка запроса: ' + error.message);
                })
                .finally(() => {
                    regLookupBtn.disabled = false;
                    regLookupBtn.textContent = 'Заполнить по ИНН';
                });
            });
        }
    });
    </script>
    <?php
});

// Валидация при регистрации
add_filter( 'woocommerce_registration_errors', function( $errors, $username, $email ) {
    if ( empty( $_POST['client_type'] ) ) {
        $errors->add( 'client_type_error', __( 'Пожалуйста, выберите тип клиента.', 'woocommerce' ) );
    }
    
    if ( isset( $_POST['client_type'] ) && $_POST['client_type'] === 'jur' ) {
        if ( empty( $_POST['billing_inn'] ) ) {
            $errors->add( 'billing_inn_error', __( 'Для юридических лиц обязательно указание ИНН.', 'woocommerce' ) );
        } else {
            $inn = sanitize_text_field( $_POST['billing_inn'] );
            if ( !preg_match('/^\d{10}$|^\d{12}$/', $inn) ) {
                $errors->add( 'billing_inn_format_error', __( 'ИНН должен содержать 10 или 12 цифр.', 'woocommerce' ) );
            }
        }
    }
    
    return $errors;
}, 10, 3 );

// Сохранение данных при регистрации
add_action( 'woocommerce_created_customer', function( $customer_id ) {
    if ( isset( $_POST['client_type'] ) ) {
        update_user_meta( $customer_id, 'client_type', sanitize_text_field( $_POST['client_type'] ) );
    }

    $fields = array(
        'billing_inn', 
        'billing_full_name', 
        'billing_short_name', 
        'billing_legal_address', 
        'billing_kpp', 
        'billing_ogrn', 
        'billing_director'
    );

    foreach ( $fields as $field ) {
        if ( isset( $_POST[$field] ) && !empty( $_POST[$field] ) ) {
            update_user_meta( $customer_id, $field, sanitize_text_field( $_POST[$field] ) );
        }
    }
    
    error_log( 'WooCommerce registration: Customer ' . $customer_id . ' created with client_type: ' . ($_POST['client_type'] ?? 'not set') );
}, 10, 1 );

// -----------------------
// Кастомизация checkout (оформления заказа)
// -----------------------

// Удаление стандартных полей billing
add_filter( 'woocommerce_checkout_fields', function( $fields ) {
    //unset( $fields['billing']['billing_address_1'] );
    //unset( $fields['billing']['billing_address_2'] );
    //unset( $fields['billing']['billing_city'] );
    //unset( $fields['billing']['billing_postcode'] );
    unset( $fields['billing']['billing_country'] );
    //unset( $fields['billing']['billing_state'] );
    unset( $fields['billing']['billing_company'] );
    //unset( $fields['billing']['billing_phone'] );
    //unset( $fields['billing']['billing_email'] );

    return $fields;
});

// Добавление кастомных полей на checkout
add_action( 'woocommerce_after_checkout_billing_form', function( $checkout ) {
    $user_id = get_current_user_id();
    $client_type = '';
    
    if ( $user_id ) {
        $client_type = get_user_meta( $user_id, 'client_type', true );
    }
    
    ?>
    <div class="checkout-client-type">
        <h3>Тип плательщика</h3>
        
        <?php
        woocommerce_form_field( 'checkout_client_type', array(
            'type'          => 'select',
            'class'         => array('form-row-wide'),
            'label'         => __('Тип клиента'),
            'required'      => true,
            'options'       => array(
                ''     => 'Выберите тип клиента',
                'fiz'  => 'Физическое лицо',
                'jur'  => 'Юридическое лицо / ИП'
            )
        ), $checkout->get_value( 'checkout_client_type' ) ?: $client_type );
        ?>
        
        <div id="checkout-jur-fields" style="display:none;">
            <?php
            $jur_fields = array(
                'checkout_billing_inn' => array(
                    'label' => 'ИНН',
                    'required' => true,
                    'class' => array('form-row-wide inn-field')
                ),
                'checkout_billing_full_name' => array(
                    'label' => 'Полное наименование',
                    'class' => array('form-row-wide')
                ),
                'checkout_billing_short_name' => array(
                    'label' => 'Краткое наименование',
                    'class' => array('form-row-wide')
                ),
                'checkout_billing_legal_address' => array(
                    'label' => 'Юридический адрес',
                    'class' => array('form-row-wide')
                ),
                'checkout_billing_kpp' => array(
                    'label' => 'КПП',
                    'class' => array('form-row-first')
                ),
                'checkout_billing_ogrn' => array(
                    'label' => 'ОГРН / ОГРНИП',
                    'class' => array('form-row-last')
                ),
                'checkout_billing_director' => array(
                    'label' => 'Должность и ФИО руководителя',
                    'class' => array('form-row-wide')
                )
            );

            foreach ( $jur_fields as $key => $args ) {
                $value = '';
                if ( $user_id ) {
                    $meta_key = str_replace('checkout_', '', $key);
                    $value = get_user_meta( $user_id, $meta_key, true );
                }
                woocommerce_form_field( $key, $args, $checkout->get_value( $key ) ?: $value );
            }
            ?>
            <button type="button" id="checkout-inn-lookup-btn">Заполнить по ИНН</button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('checkout_client_type');
        const jurFields = document.getElementById('checkout-jur-fields');
        const innField = document.getElementById('checkout_billing_inn');
        const lookupBtn = document.getElementById('checkout-inn-lookup-btn');
        
        function toggleFields() {
            if (select && select.value === 'jur') {
                jurFields.style.display = 'block';
            } else {
                jurFields.style.display = 'none';
            }
        }
        
        toggleFields();
        
        if (select) {
            select.addEventListener('change', toggleFields);
        }

        if (lookupBtn && innField) {
            lookupBtn.addEventListener('click', function() {
                const inn = innField.value.trim();
                if (!inn) {
                    alert('Введите ИНН');
                    return;
                }

                lookupBtn.disabled = true;
                lookupBtn.textContent = 'Загрузка...';

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=inn_lookup&inn=' + encodeURIComponent(inn)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const info = data.data;
                        if (info.full_name) document.getElementById('checkout_billing_full_name').value = info.full_name;
                        if (info.short_name) document.getElementById('checkout_billing_short_name').value = info.short_name;
                        if (info.legal_address) document.getElementById('checkout_billing_legal_address').value = info.legal_address;
                        if (info.kpp) document.getElementById('checkout_billing_kpp').value = info.kpp;
                        if (info.ogrn) document.getElementById('checkout_billing_ogrn').value = info.ogrn;
                        if (info.director) document.getElementById('checkout_billing_director').value = info.director;
                    } else {
                        alert('Ошибка получения данных: ' + (data.data || 'Неизвестная ошибка'));
                    }
                })
                .catch(error => {
                    alert('Ошибка запроса: ' + error.message);
                })
                .finally(() => {
                    lookupBtn.disabled = false;
                    lookupBtn.textContent = 'Заполнить по ИНН';
                });
            });
        }
    });
    </script>
    <?php
});

// Валидация полей checkout
add_action( 'woocommerce_checkout_process', function() {
    if ( empty( $_POST['checkout_client_type'] ) ) {
        wc_add_notice( __( 'Пожалуйста, выберите тип клиента.' ), 'error' );
    }
    
    if ( isset( $_POST['checkout_client_type'] ) && $_POST['checkout_client_type'] === 'jur' ) {
        if ( empty( $_POST['checkout_billing_inn'] ) ) {
            wc_add_notice( __( 'Для юридических лиц обязательно указание ИНН.' ), 'error' );
        }
    }
});

// Сохранение данных checkout в мета заказа и пользователя
add_action( 'woocommerce_checkout_update_order_meta', function( $order_id ) {
    $checkout_fields = array(
        'checkout_client_type' => 'client_type',
        'checkout_billing_inn' => 'billing_inn',
        'checkout_billing_full_name' => 'billing_full_name',
        'checkout_billing_short_name' => 'billing_short_name',
        'checkout_billing_legal_address' => 'billing_legal_address',
        'checkout_billing_kpp' => 'billing_kpp',
        'checkout_billing_ogrn' => 'billing_ogrn',
        'checkout_billing_director' => 'billing_director'
    );

    $user_id = get_current_user_id();
    
    foreach ( $checkout_fields as $checkout_field => $meta_key ) {
        if ( ! empty( $_POST[$checkout_field] ) ) {
            $value = sanitize_text_field( $_POST[$checkout_field] );
            
            // Сохранение в мета заказа
            update_post_meta( $order_id, '_' . $meta_key, $value );
            
            // Сохранение в профиль пользователя (если авторизован)
            if ( $user_id ) {
                update_user_meta( $user_id, $meta_key, $value );
            }
        }
    }
});

// Отображение данных в админке заказа
add_action( 'woocommerce_admin_order_data_after_billing_address', function( $order ) {
    $client_type = get_post_meta( $order->get_id(), '_client_type', true );
    
    if ( $client_type === 'jur' ) {
        echo '<h3>Реквизиты юридического лица</h3>';
        
        $jur_fields = array(
            '_billing_inn' => 'ИНН',
            '_billing_full_name' => 'Полное наименование',
            '_billing_short_name' => 'Краткое наименование',
            '_billing_legal_address' => 'Юридический адрес',
            '_billing_kpp' => 'КПП',
            '_billing_ogrn' => 'ОГРН / ОГРНИП',
            '_billing_director' => 'Руководитель'
        );
        
        foreach ( $jur_fields as $meta_key => $label ) {
            $value = get_post_meta( $order->get_id(), $meta_key, true );
            if ( $value ) {
                echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>';
            }
        }
    }
});

// -----------------------
// AJAX обработчик для получения данных по ИНН
// -----------------------

add_action( 'wp_ajax_inn_lookup', 'handle_inn_lookup' );
add_action( 'wp_ajax_nopriv_inn_lookup', 'handle_inn_lookup' );

function handle_inn_lookup() {
    $inn = sanitize_text_field( $_POST['inn'] ?? '' );
    
    if ( empty( $inn ) ) {
        wp_send_json_error( 'ИНН не указан' );
    }
    
    // Используем предоставленные API ключи DaData
    $api_key = '903f6c9ee3c3fabd7b9ae599e3735b164f9f71d9';
    $secret_key = 'ea0595f2a66c84887976a56b8e57ec0aa329a9f7';
    
    // Реальный запрос к DaData
    $response = wp_remote_post( 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Token ' . $api_key,
            'X-Secret' => $secret_key
        ),
        'body' => json_encode( array( 'query' => $inn ) ),
        'timeout' => 30
    ));
    
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Ошибка запроса к API: ' . $response->get_error_message() );
    }
    
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );
    
    if ( empty( $data['suggestions'] ) ) {
        wp_send_json_error( 'Данные по указанному ИНН не найдены' );
    }
    
    $suggestion = $data['suggestions'][0];
    $company_data = $suggestion['data'];
    
$result = array(
    'full_name'     => $company_data['name']['full_with_opf'] ?? '',
    'short_name'    => $company_data['name']['short_with_opf'] ?? '',
    // берём адрес из data['address'], fallback на unrestricted_value если нет
    'legal_address' => $company_data['address']['value'] 
                       ?? $company_data['address']['unrestricted_value'] 
                       ?? $suggestion['unrestricted_value'] 
                       ?? '',
    'kpp'           => $company_data['kpp'] ?? '',
    'ogrn'          => $company_data['ogrn'] ?? '',
    'director'      => ''
);
    
    // Получение данных о руководителе
    if ( !empty( $company_data['management'] ) && !empty( $company_data['management']['name'] ) ) {
        $management = $company_data['management'];
        $director_name = $management['name'];
        $director_post = $management['post'] ?? 'Руководитель';
        $result['director'] = $director_post . ' ' . $director_name;
    }
    
    wp_send_json_success( $result );
}

// -----------------------
// Настройки для API ключей (добавить в админку)
// -----------------------

add_action( 'admin_menu', function() {
    add_options_page(
        'Настройки ИНН API',
        'ИНН API',
        'manage_options',
        'inn-api-settings',
        'inn_api_settings_page'
    );
});

function inn_api_settings_page() {
    if ( isset( $_POST['submit'] ) ) {
        update_option( 'dadata_api_key', sanitize_text_field( $_POST['dadata_api_key'] ) );
        update_option( 'dadata_secret_key', sanitize_text_field( $_POST['dadata_secret_key'] ) );
        echo '<div class="notice notice-success"><p>Настройки сохранены!</p></div>';
    }
    
    $api_key = get_option( 'dadata_api_key', '903f6c9ee3c3fabd7b9ae599e3735b164f9f71d9' );
    $secret_key = get_option( 'dadata_secret_key', 'ea0595f2a66c84887976a56b8e57ec0aa329a9f7' );
    ?>
    <div class="wrap">
        <h1>Настройки ИНН API</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">API ключ DaData</th>
                    <td><input type="text" name="dadata_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row">Secret ключ DaData</th>
                    <td><input type="text" name="dadata_secret_key" value="<?php echo esc_attr( $secret_key ); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <p><strong>Примечание:</strong> Для работы автозаполнения по ИНН нужно получить API ключи на сайте <a href="https://dadata.ru/" target="_blank">DaData.ru</a></p>
    </div>
    <?php
}

// -----------------------
// Стили для форм
// -----------------------

add_action( 'wp_head', function() {
    ?>
    <style>
    .checkout-client-type {
        margin-bottom: 20px;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 5px;
        background: #f9f9f9;
    }
    
    .checkout-client-type h3 {
        margin-top: 0;
        margin-bottom: 15px;
    }
    
    #checkout-inn-lookup-btn,
    #inn-lookup-btn,
    #reg-inn-lookup-btn {
        margin-top: 10px;
        padding: 8px 15px;
        background: #0073aa;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
    }
    
    #checkout-inn-lookup-btn:hover,
    #inn-lookup-btn:hover,
    #reg-inn-lookup-btn:hover {
        background: #005177;
    }
    
    #checkout-inn-lookup-btn:disabled,
    #inn-lookup-btn:disabled,
    #reg-inn-lookup-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
    }
    
    .lk-tiles {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }
    
    .lk-tile {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 30px 20px;
        text-decoration: none;
        color: #333;
        background: #f8f8f8;
        border-radius: 8px;
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .lk-tile:hover {
        background: #e8e8e8;
        transform: translateY(-2px);
        text-decoration: none;
        color: #000;
    }
    
    .lk-tile svg {
        width: 48px;
        height: 48px;
        fill: currentColor;
        margin-bottom: 10px;
    }
    </style>
    <?php
});











// Функция для доваления За литр и проверки, находится ли товар в категории 81 или её дочерних категориях
function is_in_liter_categories($product_id) {
    $product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
    
    if (is_wp_error($product_categories) || empty($product_categories)) {
        return false;
    }
    
    $target_categories = range(81, 86);
    
    foreach ($product_categories as $cat_id) {
        // Проверяем, является ли текущая категория одной из целевых категорий
        if (in_array($cat_id, $target_categories)) {
            return true;
        }
        
        // Проверяем, является ли одна из целевых категорий предком текущей категории
        foreach ($target_categories as $target_cat_id) {
            if (cat_is_ancestor_of($target_cat_id, $cat_id)) {
                return true;
            }
        }
    }
    
    return false;
}

// Изменяем отображение цены для товаров из категории 81 и дочерних
add_filter('woocommerce_get_price_html', function($price, $product) {
    $product_id = $product->get_id();
    
    // Проверяем, находится ли товар в нужных категориях
    if (!is_in_liter_categories($product_id)) {
        return $price;
    }
    
    // Проверяем, не добавлено ли уже "за литр" к цене
    if (strpos($price, 'за литр') === false) {
        // Если цена содержит HTML теги (например, <span>), добавляем "за литр" внутрь
        if (preg_match('/(.*)<\/span>(.*)$/i', $price, $matches)) {
            $price = $matches[1] . '/литр</span>' . $matches[2];
        } else {
            // Если цена простая, просто добавляем в конец
            $price .= ' за литр';
        }
    }
    
    return $price;
}, 10, 2);






add_action('wp_footer', function() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function addFacetTitles() {
            const facetMap = {
                'poroda': 'Порода',
                'sort_': 'Сорт',
                'profil': 'Профиль', 
                'dlina': 'Длина',
                'shirina': 'Ширина',
                'tolshina': 'Толщина',
                'proizvoditel': 'Производитель',
                'krepej': 'Крепёж',
                'tip': 'Тип',
                'brend': 'Бренд'
            };

            // Находим все фильтры
            const facets = document.querySelectorAll('.facetwp-facet');
            
            facets.forEach(facet => {
                const facetName = facet.getAttribute('data-name');
                const titleText = facetMap[facetName];
                
                if (titleText) {
                    // Проверяем, есть ли уже заголовок
                    const prevElement = facet.previousElementSibling;
                    const hasTitle = prevElement && 
                                   prevElement.classList.contains('facet-title-added');
                    
                    // Проверяем, есть ли внутри элементы (фильтр не пустой)
                    const hasContent = facet.querySelector('.facetwp-checkbox') || 
                                     facet.querySelector('.facetwp-search') ||
                                     facet.querySelector('.facetwp-slider') ||
                                     facet.innerHTML.trim() !== '';
                    
                    if (!hasTitle && hasContent) {
                        // Создаем заголовок
                        const title = document.createElement('div');
                        title.className = 'facet-title-added';
                        title.innerHTML = `<h4 style="margin: 20px 0 10px 0; padding: 8px 0 5px 0; font-size: 16px; font-weight: 600; color: #333; border-bottom: 2px solid #8bc34a; text-transform: uppercase; letter-spacing: 0.5px;">${titleText}</h4>`;
                        
                        // Вставляем перед фильтром
                        facet.parentNode.insertBefore(title, facet);
                    }
                    
                    // Удаляем заголовок если фильтр стал пустым
                    if (hasTitle && !hasContent) {
                        const titleElement = facet.previousElementSibling;
                        if (titleElement && titleElement.classList.contains('facet-title-added')) {
                            titleElement.remove();
                        }
                    }
                }
            });
        }

        // Запускаем сразу
        addFacetTitles();

        // Запускаем с интервалом на случай динамической загрузки
        const interval = setInterval(addFacetTitles, 300);
        
        // Останавливаем через 10 секунд
        setTimeout(() => clearInterval(interval), 10000);

        // Также на события FacetWP
        if (typeof FWP !== 'undefined') {
            document.addEventListener('facetwp-loaded', addFacetTitles);
            document.addEventListener('facetwp-refresh', addFacetTitles);
        }

        // Mutation Observer для отслеживания изменений DOM
        const observer = new MutationObserver(addFacetTitles);
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
    </script>
    <?php
});















// Своя тара для ЛКМ (по брендам)

// 1. Задаём доступные объёмы по брендам
function tara_by_brand() {
    return [
        'reiner'    => [1, 3, 5, 25],
        'renowood'  => [1, 3, 5, 9],
        'talatu'    => [1, 3, 5, 18],
        'tikkurila' => [1, 3, 5, 9, 18],
        'woodsol'   => [1, 3, 5, 18]
    ];
}

// Функция для получения бренда товара (универсальная)
function get_product_brand_for_tara($product_id) {
    // Список возможных таксономий для брендов
    $brand_taxonomies = [
        'product_brand',        // Официальный плагин WooCommerce Brands
        'yith_product_brand',   // YITH WooCommerce Brands
        'pa_brand',            // Атрибут товара "Бренд"
        'pa_brend',            // Атрибут товара "Бренд" (с опечаткой)
        'pwb-brand',           // Perfect WooCommerce Brands
        'brand'                // Другие плагины
    ];
    
    foreach ($brand_taxonomies as $taxonomy) {
        if (taxonomy_exists($taxonomy)) {
            $terms = wp_get_post_terms($product_id, $taxonomy);
            if (!empty($terms) && !is_wp_error($terms)) {
                return strtolower($terms[0]->slug); // Возвращаем slug в нижнем регистре
            }
        }
    }
    
    // Проверяем мета-поля как запасной вариант
    $meta_keys = ['_brand', 'brand', '_product_brand'];
    foreach ($meta_keys as $key) {
        $brand = get_post_meta($product_id, $key, true);
        if (!empty($brand)) {
            return strtolower(sanitize_title($brand));
        }
    }
    
    return false;
}

// Диагностическая функция для определения используемой таксономии
function debug_brand_taxonomy($product_id) {
    echo "<!-- Диагностика брендов для товара #$product_id -->\n";
    
    $brand_taxonomies = [
        'product_brand',
        'yith_product_brand', 
        'pa_brand',
        'pa_brend',
        'pwb-brand',
        'brand'
    ];
    
    foreach ($brand_taxonomies as $taxonomy) {
        if (taxonomy_exists($taxonomy)) {
            $terms = wp_get_post_terms($product_id, $taxonomy);
            if (!empty($terms) && !is_wp_error($terms)) {
                echo "<!-- Найден бренд в '$taxonomy': {$terms[0]->name} (slug: {$terms[0]->slug}) -->\n";
            } else {
                echo "<!-- Таксономия '$taxonomy' существует, но брендов нет -->\n";
            }
        } else {
            echo "<!-- Таксономия '$taxonomy' не существует -->\n";
        }
    }
}

// 2. Вывод селекта на странице товара
add_action('woocommerce_before_add_to_cart_button', function() {
    global $product;
    if (!$product->is_type('simple')) return;
    
    $product_id = $product->get_id();
    
    // Включаем диагностику (уберите после настройки)
    if (current_user_can('manage_options')) {
        debug_brand_taxonomy($product_id);
    }
    
    // Получаем бренд товара
    $brand_slug = get_product_brand_for_tara($product_id);
    
    if (!$brand_slug) {
        echo "<!-- Бренд не найден для товара #$product_id -->\n";
        return;
    }
    
    echo "<!-- Найден бренд: $brand_slug -->\n";
    
    $map = tara_by_brand();
    
    // Проверяем, есть ли этот бренд в нашем списке объёмов
    if (!empty($map[$brand_slug])) {
        $base_price = wc_get_price_to_display($product);
        ?>
        <style>
            #brxe-gkyfue .cart {
                align-items: flex-end;
            }
            .tara-select {

            }
            .tara-select label {
                display: inline-block;
                margin-right: 10px;
                font-weight: bold;
                white-space: nowrap;
            }
        </style>
        <div class="tara-select">
            <label for="tara">Объем (л): </label>
            <div class="tinv-wraper" style="padding:2.5px; width:80px; display:inline-block;">
                <select id="tara" name="tara" data-base-price="<?php echo esc_attr($base_price); ?>">
                    <?php foreach ($map[$brand_slug] as $volume): ?>
                        <option value="<?php echo esc_attr($volume); ?>"><?php echo esc_html($volume); ?> л</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php
    } else {
        echo "<!-- Бренд '$brand_slug' не найден в списке доступных объёмов -->\n";
        echo "<!-- Доступные бренды: " . implode(', ', array_keys($map)) . " -->\n";
    }
});

// 3. Добавляем объём в корзину
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id) {
    if (isset($_POST['tara'])) {
        $cart_item_data['tara'] = (float) $_POST['tara'];
    }
    return $cart_item_data;
}, 10, 3);

// 4. Показываем выбранный объём в корзине/заказе
add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (!empty($cart_item['tara'])) {
        $item_data[] = [
            'name'  => 'Объем',
            'value' => $cart_item['tara'] . ' л',
        ];
    }
    return $item_data;
}, 10, 2);

// 5. Пересчёт цены = цена * объем, скидка -10% при объеме >= 9
add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    foreach ($cart->get_cart() as $cart_item) {
        if (!empty($cart_item['tara'])) {
            $price_per_liter = (float) $cart_item['data']->get_price();
            $final_price = $price_per_liter * $cart_item['tara'];
            if ($cart_item['tara'] >= 9) {
                $final_price *= 0.9; // скидка 10%
            }
            $cart_item['data']->set_price($final_price);
        }
    }
});

// 6. JS для обновления цены на лету (со скидкой)
add_action('wp_footer', function() {
    if ( ! is_product() ) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let select = document.getElementById('tara');
        if (!select) return;

        let priceEl = document.querySelector('.woocommerce-Price-amount');
        let basePrice = parseFloat(select.dataset.basePrice);

        function updatePrice() {
            let multiplier = parseFloat(select.value) || 1;
            let newPrice = basePrice * multiplier;
            if (multiplier >= 9) {
                newPrice *= 0.9; // скидка 10%
            }
            if (priceEl) {
                priceEl.innerHTML = newPrice.toFixed(2).replace('.', ',') + ' ₽';
            }
        }

        select.addEventListener('change', updatePrice);
        updatePrice();
    });
    </script>
    <?php
});






//Замена в фильтре
function facetwp_custom_text_replacement() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Функция для замены текста
        function replaceFacetWPText() {
            // Ищем все элементы с классом facetwp-toggle
            const toggleElements = document.querySelectorAll('.facetwp-toggle');
            
            toggleElements.forEach(function(element) {
                // Регулярное выражение для поиска "Посмотреть X Подробнее"
                const regex = /Посмотреть\s+(\d+)\s+Подробнее/g;
                
                if (element.textContent && regex.test(element.textContent)) {
                    element.textContent = element.textContent.replace(regex, 'Развернуть (еще $1)');
                }
            });
            
            // Также проверяем другие возможные селекторы
            const otherElements = document.querySelectorAll('.facetwp-expand, .facetwp-collapse, [class*="facet"] a, [class*="facet"] span');
            
            otherElements.forEach(function(element) {
                const regex = /Посмотреть\s+(\d+)\s+Подробнее/g;
                
                if (element.textContent && regex.test(element.textContent)) {
                    element.textContent = element.textContent.replace(regex, 'Раскрыть $1');
                }
            });
        }
        
        // Запускаем замену при загрузке страницы
        replaceFacetWPText();
        
        // Запускаем замену после каждого обновления FacetWP
        document.addEventListener('facetwp-loaded', function() {
            setTimeout(replaceFacetWPText, 100);
        });
        
        // Дополнительно следим за изменениями DOM
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    replaceFacetWPText();
                }
            });
        });
        
        // Наблюдаем за контейнером с фильтрами
        const facetContainer = document.querySelector('.facetwp-template');
        if (facetContainer) {
            observer.observe(facetContainer, {
                childList: true,
                subtree: true
            });
        }
// === ADDED: height field listeners for running meter calculator ===
if (document.getElementById('rm_height1')) {
    document.getElementById('rm_height1').addEventListener('change', updateRunningMeterCalc);
}
if (document.getElementById('rm_height2')) {
    document.getElementById('rm_height2').addEventListener('change', updateRunningMeterCalc);
}
if (document.getElementById('rm_height')) {
    document.getElementById('rm_height').addEventListener('change', updateRunningMeterCalc);
}

// Delegated handler for dynamically created height fields
document.addEventListener('change', function(e) {
    if (!e || !e.target) return;
    if (e.target.id === 'rm_height' || e.target.id === 'rm_height1' || e.target.id === 'rm_height2') {
        try { console.log('Height changed:', e.target.id, '=', e.target.value); } catch(e) {}
        if (typeof updateRunningMeterCalc === 'function') updateRunningMeterCalc();
    }
});

    });
    </script>
    <?php
}
add_action('wp_footer', 'facetwp_custom_text_replacement');








/* ===== Mega Menu: атрибуты из JSON с подменой при наведении ===== */

add_action('wp_footer', function(){ ?>
<script>
jQuery(function($){
    let cache = null;

    // Загружаем JSON один раз
    $.getJSON('<?php echo home_url("/menu_attributes.json"); ?>', function(data){
        cache = data;

        // Рендерим для родительских категорий (если есть)
        $('.widget_layered_nav').each(function(){
            renderAttributes($(this));
        });
    });

    // Подмена при наведении на подкатегории
    $(document).on('mouseenter', '.mega-menu-item-type-taxonomy', function(){
        let href = $(this).find('a').attr('href');
        if (!href) return;

        // Достаём slug из ссылки категории
        let parts = href.split('/');
        let catSlug = parts.filter(Boolean).pop(); 

        $('.widget_layered_nav').each(function(){
            renderAttributes($(this), catSlug);
        });
    });

    function renderAttributes($widget, overrideCat){
        if (!cache) return;

        let attr = $widget.data('attribute');
        let cat = overrideCat || $widget.data('category');

        if (cat && attr && cache[cat] && cache[cat][attr]) {
            let $ul = $('<ul class="attribute-list"/>');
            cache[cat][attr].forEach(function(t){
                let base = '<?php echo home_url("/product-category/"); ?>' + cat + '/';
                let url = base + '?_' + attr.replace('pa_','') + '=' + t.slug;
                $ul.append('<li><a href="'+url+'">'+t.name+' <span class="count">('+t.count+')</span></a></li>');
            });
            $widget.html($ul);
        } else {
            $widget.html('<div class="no-attributes">Нет атрибутов</div>');
        }
    }
});
</script>
<?php });












//------------------Доставка-----------------






add_action('wp_enqueue_scripts', function() {
    if (is_checkout() || is_cart()) {
        $api_key = '81c72bf5-a635-4fb5-8939-e6b31aa52ffe';
        wp_enqueue_script('yandex-maps', "https://api-maps.yandex.ru/2.1/?apikey={$api_key}&lang=ru_RU", [], null, true);
        wp_enqueue_script('delivery-calc', get_stylesheet_directory_uri() . '/js/delivery-calc.js', ['jquery','yandex-maps'], '1.3', true);

        $cart_weight = WC()->cart ? WC()->cart->get_cart_contents_weight() : 0;
        wp_localize_script('delivery-calc', 'deliveryVars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'basePoint' => 'г. Санкт-Петербург, Выборгское шоссе 369к6',
            'rateLight' => 200, // руб/км для легких грузов (до 1500г)
            'rateHeavy' => 250, // руб/км для тяжелых грузов (свыше 1500г)
            'minLight' => 6000, // минимальная стоимость для легких грузов
            'minHeavy' => 7500, // минимальная стоимость для тяжелых грузов
            'minDistance' => 30, // минимальное расстояние для применения минималки (км)
            'cartWeight' => $cart_weight,
            'apiKey' => $api_key
        ]);
    }
});

// Ajax для сохранения стоимости доставки
add_action('wp_ajax_set_delivery_cost', 'set_delivery_cost');
add_action('wp_ajax_nopriv_set_delivery_cost', 'set_delivery_cost');
function set_delivery_cost() {
    if (isset($_POST['cost'])) {
        $cost = round(floatval($_POST['cost'])); // округляем до целых
        WC()->session->set('custom_delivery_cost', $cost);

        // сохраняем расстояние, если передано
        if (!empty($_POST['distance'])) {
            WC()->session->set('delivery_distance', floatval($_POST['distance']));
        }

        // лог для отладки
        error_log("Установлена стоимость доставки: {$cost} руб.");

        // очищаем уведомления
        if (function_exists('wc_clear_notices')) {
            wc_clear_notices();
        }

        // очищаем кэши WooCommerce
        wp_cache_flush();
        WC_Cache_Helper::get_transient_version('shipping', true);
        delete_transient('wc_shipping_method_count');

        // сбрасываем пользовательский кэш доставки
        $packages_hash = 'wc_ship_' . md5( 
            json_encode(WC()->cart->get_cart_for_session()) . 
            WC()->customer->get_shipping_country() . 
            WC()->customer->get_shipping_state() . 
            WC()->customer->get_shipping_postcode() . 
            WC()->customer->get_shipping_city()
        );
        wp_cache_delete($packages_hash, 'shipping_zones');

        // пересчет корзины
        if (WC()->cart) {
            WC()->cart->calculate_shipping();
            WC()->cart->calculate_totals();
        }

        wp_send_json_success([
            'cost'    => $cost,
            'message' => 'Стоимость доставки обновлена'
        ]);
    } else {
        wp_send_json_error('Не указана стоимость');
    }
    wp_die();
}


// Ajax для очистки стоимости доставки
add_action('wp_ajax_clear_delivery_cost', 'clear_delivery_cost');
add_action('wp_ajax_nopriv_clear_delivery_cost', 'clear_delivery_cost');
function clear_delivery_cost() {
    WC()->session->__unset('custom_delivery_cost');
    WC()->session->__unset('delivery_distance');
    
    // Очищаем кэши
    WC_Cache_Helper::get_transient_version('shipping', true);
    delete_transient('wc_shipping_method_count');
    
    if (WC()->cart) {
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
    }
    
    wp_send_json_success(['message' => 'Стоимость доставки очищена']);
    wp_die();
}

// ВАЖНО: Создаем кастомный метод доставки для отображения стоимости
add_action('woocommerce_shipping_init', 'init_custom_delivery_method');
function init_custom_delivery_method() {
    if (!class_exists('WC_Custom_Delivery_Method')) {
        class WC_Custom_Delivery_Method extends WC_Shipping_Method {
            public function __construct($instance_id = 0) {
                $this->id = 'custom_delivery';
                $this->instance_id = absint($instance_id);
                $this->method_title = __('Доставка по карте');
                $this->method_description = __('Расчет доставки по карте');
                $this->supports = array(
                    'shipping-zones',
                    'instance-settings',
                );
                $this->enabled = 'yes';
                $this->title = 'Доставка по карте';
                $this->init();
            }

            public function init() {
                $this->init_form_fields();
                $this->init_settings();
                $this->enabled = $this->get_option('enabled');
                $this->title = $this->get_option('title');
                
                // Добавляем хуки для сохранения настроек
                add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            }

            public function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Включить/Отключить'),
                        'type' => 'checkbox',
                        'description' => __('Включить этот метод доставки.'),
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title' => __('Название'),
                        'type' => 'text',
                        'description' => __('Название метода доставки.'),
                        'default' => __('Доставка по карте'),
                        'desc_tip' => true,
                    )
                );
            }

            public function calculate_shipping($package = array()) {
                $delivery_cost = WC()->session->get('custom_delivery_cost');
                
                if ($delivery_cost && $delivery_cost > 0) {
                    $rate = array(
                        'id' => $this->id . ':' . $this->instance_id,
                        'label' => $this->title,
                        'cost' => $delivery_cost,
                        'calc_tax' => 'per_item'
                    );
                    
                    $this->add_rate($rate);
                }
            }
        }
    }
}

// Добавляем метод доставки в список доступных
add_filter('woocommerce_shipping_methods', 'add_custom_delivery_method');
function add_custom_delivery_method($methods) {
    $methods['custom_delivery'] = 'WC_Custom_Delivery_Method';
    return $methods;
}

// Принудительно обновляем методы доставки при изменении стоимости
add_action('woocommerce_checkout_update_order_review', 'force_shipping_update');
function force_shipping_update($post_data) {
    if (WC()->session->get('custom_delivery_cost')) {
        // Очищаем кэш методов доставки
        WC_Cache_Helper::get_transient_version('shipping', true);
        
        // Пересчитываем доставку
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
    }
}

// Выводим интерфейс на странице checkout
add_action('woocommerce_before_checkout_billing_form', function() {
    ?>
    <style>
    .woocommerce-delivery-calc {
        background: #f8f9fa;
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }
    .woocommerce-delivery-calc h3 {
        margin: 0 0 15px 0;
        color: #495057;
        font-size: 18px;
    }
    #delivery-map {
        width: 100%;
        height: 400px;
        margin-bottom: 15px;
        border: 2px solid #dee2e6;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    #ymaps-address {
        width: 100%;
        padding: 12px;
        margin-bottom: 15px;
        box-sizing: border-box;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 14px;
    }
    #ymaps-address:focus {
        outline: none;
        border-color: #0066cc;
        box-shadow: 0 0 0 2px rgba(0,102,204,0.2);
    }
    
    /* Стили для автокомплита */
    .ymaps-suggest-container {
        position: absolute;
        background: white;
        border: 1px solid #ccc;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        width: 100%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-radius: 4px;
        margin-top: 1px;
    }
    
    .ymaps-suggest-item {
        padding: 10px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
        transition: background-color 0.2s;
    }
    
    .ymaps-suggest-item:last-child {
        border-bottom: none;
    }
    
    .ymaps-suggest-item:hover,
    .ymaps-suggest-item.active {
        background-color: #f5f5f5;
    }
    
    .ymaps-suggest-item.active {
        background-color: #007bff !important;
        color: white !important;
    }
    
    @media(max-width:768px) {
        #delivery-map { height: 300px; }
        .woocommerce-delivery-calc { padding: 15px; margin-bottom: 15px; }
    }
    #delivery-result {
        font-weight: normal;
        margin-top: 10px;
    }
    .delivery-instructions {
        background: #e7f3ff;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
        font-size: 13px;
        color: #0066cc;
    }
    </style>

    <div class="woocommerce-delivery-calc">
        <h3>📍 Расчет стоимости доставки</h3>
        <div class="delivery-instructions">
            💡 <strong>Как рассчитать доставку:</strong><br>
            1️⃣ Введите адрес в поле ниже и выберите из подсказок<br>
            2️⃣ Или просто кликните по нужной точке на карте<br>
            3️⃣ Стоимость рассчитается автоматически
        </div>
        <p>
            <label for="ymaps-address"><strong>🏠 Адрес доставки:</strong>
                <input type="text" id="ymaps-address" placeholder="Введите адрес доставки (например: Невский проспект, 1)">
            </label>
        </p>
        <div id="delivery-map"></div>
        <div id="delivery-result"></div>
    </div>
    <?php
});

// Добавляем информацию о доставке в заказ
add_action('woocommerce_checkout_update_order_meta', 'save_delivery_info_to_order');
function save_delivery_info_to_order($order_id) {
    $delivery_cost = WC()->session->get('custom_delivery_cost');
    $delivery_distance = WC()->session->get('delivery_distance');

    if ($delivery_cost) {
        update_post_meta($order_id, '_delivery_cost', $delivery_cost);
    }
    if ($delivery_distance) {
        update_post_meta($order_id, '_delivery_distance', $delivery_distance);
    }

    // Очищаем сессию после сохранения заказа
    WC()->session->__unset('custom_delivery_cost');
    WC()->session->__unset('delivery_distance');
}

// Отображаем информацию о доставке в админке заказов
add_action('woocommerce_admin_order_data_after_shipping_address', 'display_delivery_info_in_admin');
function display_delivery_info_in_admin($order) {
    $delivery_cost = get_post_meta($order->get_id(), '_delivery_cost', true);
    $delivery_distance = get_post_meta($order->get_id(), '_delivery_distance', true);

    if ($delivery_cost || $delivery_distance) {
        echo '<h3>Информация о доставке</h3>';
        if ($delivery_distance) {
            echo '<p><strong>Расстояние:</strong> ' . number_format($delivery_distance, 1) . ' км</p>';
        }
        if ($delivery_cost) {
            echo '<p><strong>Стоимость доставки:</strong> ' . number_format($delivery_cost, 0) . ' ₽</p>';
        }
    }
}

// Всегда показываем поля доставки
add_filter('woocommerce_checkout_show_ship_to_different_address', '__return_true');
add_filter('woocommerce_cart_needs_shipping_address', '__return_true');
add_filter('woocommerce_ship_to_different_address_checked', '__return_true');

// Убираем обязательность полей billing адреса
add_filter('woocommerce_billing_fields', 'remove_billing_required_fields');
function remove_billing_required_fields($fields) {
    foreach($fields as $key => &$field) {
        if ($key !== 'billing_email') {
            $field['required'] = false;
        }
    }
    return $fields;
}

// Скрипт для подстановки адреса из карты в поля checkout
add_action('wp_footer', function() {
    if (!is_checkout()) return;
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('=== ИНИЦИАЛИЗАЦИЯ WooCommerce ИНТЕГРАЦИИ ===');
        
        // Глобальная функция для подстановки адреса в поля WooCommerce
        window.updateWooCommerceAddress = function(address) {
            console.log('updateWooCommerceAddress вызвана с адресом:', address);
            
            // Ждем загрузки полей checkout
            setTimeout(function() {
                // Основные поля адреса доставки
                var $shippingAddress1 = $('input[name="shipping_address_1"]');
                var $shippingAddress2 = $('input[name="shipping_address_2"]');
                var $shippingCity = $('input[name="shipping_city"]');
                
                // Поля адреса плательщика
                var $billingAddress1 = $('input[name="billing_address_1"]');
                var $billingAddress2 = $('input[name="billing_address_2"]');  
                var $billingCity = $('input[name="billing_city"]');
                
                console.log('Найдено полей shipping_address_1:', $shippingAddress1.length);
                console.log('Найдено полей billing_address_1:', $billingAddress1.length);
                
                // Парсим адрес
                var parsedAddress = parseAddressForWooCommerce(address);
                
                // Заполняем поля доставки
                if ($shippingAddress1.length) {
                    $shippingAddress1.val(parsedAddress.address1);
                    $shippingAddress1.trigger('input').trigger('change').trigger('blur');
                    console.log('✓ shipping_address_1 обновлен:', parsedAddress.address1);
                }
                
                if ($shippingAddress2.length && parsedAddress.address2) {
                    $shippingAddress2.val(parsedAddress.address2);
                    $shippingAddress2.trigger('input').trigger('change').trigger('blur');
                    console.log('✓ shipping_address_2 обновлен:', parsedAddress.address2);
                }
                
                if ($shippingCity.length && parsedAddress.city) {
                    $shippingCity.val(parsedAddress.city);
                    $shippingCity.trigger('input').trigger('change').trigger('blur');
                    console.log('✓ shipping_city обновлен:', parsedAddress.city);
                }
                
                // Заполняем поля плательщика
                if ($billingAddress1.length) {
                    $billingAddress1.val(parsedAddress.address1);
                    $billingAddress1.trigger('input').trigger('change').trigger('blur');
                    console.log('✓ billing_address_1 обновлен:', parsedAddress.address1);
                }
                
                if ($billingAddress2.length && parsedAddress.address2) {
                    $billingAddress2.val(parsedAddress.address2);
                    $billingAddress2.trigger('input').trigger('change').trigger('blur');
                    console.log('✓ billing_address_2 обновлен:', parsedAddress.address2);
                }
                
                if ($billingCity.length && parsedAddress.city) {
                    $billingCity.val(parsedAddress.city);
                    $billingCity.trigger('input').trigger('change').trigger('blur');
                    console.log('✓ billing_city обновлен:', parsedAddress.city);
                }
                
                // Принудительное обновление checkout
                setTimeout(function() {
                    $('body').trigger('update_checkout');
                    console.log('🔄 Checkout обновлен после заполнения адреса');
                }, 200);
                
            }, 100);
        };
        
        // Функция парсинга адреса для WooCommerce
        function parseAddressForWooCommerce(fullAddress) {
            var city = '';
            var address1 = fullAddress;
            var address2 = '';
            
            // Паттерны для выделения города
            var cityPatterns = [
                /^([^,]+(?:область|край|республика|округ))[,\s]+(.+)/i,
                /^(г\.\s*[^,]+)[,\s]+(.+)/i,
                /^([^,]+(?:город|посёлок|село|деревня))[,\s]+(.+)/i,
                /^(Москва|Санкт-Петербург|СПб|Московская область|Ленинградская область)[,\s]+(.+)/i
            ];
            
            for (var i = 0; i < cityPatterns.length; i++) {
                var match = fullAddress.match(cityPatterns[i]);
                if (match) {
                    city = match[1].trim();
                    address1 = match[2].trim();
                    break;
                }
            }
            
            // Паттерны для выделения квартиры/офиса
            var apartmentPatterns = [
                /^(.+),\s*(кв\.?\s*\d+|квартира\s*\d+|оф\.?\s*\d+|офис\s*\d+)$/i,
                /^(.+),\s*(\d+[А-Я]?)$/i
            ];
            
            for (var j = 0; j < apartmentPatterns.length; j++) {
                var match2 = address1.match(apartmentPatterns[j]);
                if (match2) {
                    address1 = match2[1].trim();
                    address2 = match2[2].trim();
                    break;
                }
            }
            
            console.log('Парсинг адреса:', {
                original: fullAddress,
                city: city,
                address1: address1,
                address2: address2
            });
            
            return {
                city: city,
                address1: address1,
                address2: address2
            };
        }

        // Принудительно показываем блок доставки при загрузке
        function ensureShippingFieldsVisible() {
            $('.woocommerce-shipping-fields, .shipping_address').show();
            $('[name^="shipping_"]').closest('.form-row').show();
            $('#ship-to-different-address-checkbox').prop('checked', true);
            console.log('✓ Поля доставки принудительно показаны');
        }
        
        // Показываем поля сразу и через интервалы
        ensureShippingFieldsVisible();
        setTimeout(ensureShippingFieldsVisible, 500);
        setTimeout(ensureShippingFieldsVisible, 1000);

        // Обновляем методы доставки при изменении checkout
        $(document).on('updated_checkout', function() {
            console.log('=== CHECKOUT ОБНОВЛЕН ===');
            
            // Убеждаемся что поля доставки видны
            ensureShippingFieldsVisible();
            
            // Проверяем методы доставки
            var deliveryMethods = $('#shipping_method li label, .woocommerce-shipping-methods li label');
            console.log('Найдено методов доставки:', deliveryMethods.length);
            
            deliveryMethods.each(function(index) {
                console.log('Метод доставки ' + (index + 1) + ':', $(this).text().trim());
            });
            
            // Автоматически выбираем метод доставки по карте, если он есть и рассчитана стоимость
            var customDeliveryRadio = $('input[value*="custom_delivery"]');
            if (customDeliveryRadio.length && !$('input[name="shipping_method[0]"]:checked').length) {
                customDeliveryRadio.prop('checked', true).trigger('change');
                console.log('✓ Автоматически выбран метод доставки по карте');
            }
        });
        
        // Слушаем изменения в полях адреса для дополнительного обновления
        $(document).on('change input blur', 'input[name^="shipping_"], input[name^="billing_"]', function() {
            var fieldName = $(this).attr('name');
            var fieldValue = $(this).val();
            console.log('Поле изменено:', fieldName, '=', fieldValue);
        });
    });
    </script>
    <?php
});



add_filter('woocommerce_checkout_show_ship_to_different_address', '__return_false');
add_filter('woocommerce_cart_needs_shipping_address', '__return_false');

// Убираем надпись "(необязательно)" у всех полей checkout
add_filter('woocommerce_form_field', function($field, $key, $args, $value) {
    if (strpos($field, '(необязательно)') !== false) {
        $field = str_replace('(необязательно)', '', $field);
    }
    return $field;
}, 10, 4);








add_filter('woocommerce_account_menu_items', function($items) {
    unset($items['cart']); // для меню аккаунта
    return $items;
}, 999);

add_filter('wp_nav_menu_items', function($items, $args) {
    // убираем "Cart" из всех меню
    $items = preg_replace('/<li[^>]*><a[^>]*href="[^"]*cart[^"]*"[^>]*>.*?<\/a><\/li>/i', '', $items);
    return $items;
}, 10, 2);



// Добавляем мета-поля для форм фальшбалок (категория 266)
add_action('woocommerce_product_options_general_product_data', 'add_falsebalk_shapes_fields');
function add_falsebalk_shapes_fields() {
    global $post;
    
    // Проверяем, относится ли товар к категории 266
    if (!has_term(266, 'product_cat', $post->ID)) {
        return;
    }
    
    echo '<div class="options_group falsebalk_shapes_group">';
    echo '<h3 style="padding-left: 12px; color: #3aa655; border-bottom: 2px solid #3aa655; padding-bottom: 10px; margin-bottom: 15px;">⚙️ Настройки размеров фальшбалок</h3>';

    // Получаем сохраненные данные
    $shapes_data = get_post_meta($post->ID, '_falsebalk_shapes_data', true);
    if (!is_array($shapes_data)) {
        $shapes_data = [];
    }
    
    $shapes = [
        'g' => ['label' => 'Г-образная', 'icon' => '⌐'],
        'p' => ['label' => 'П-образная', 'icon' => '⊓'],
        'o' => ['label' => 'О-образная', 'icon' => '▢']
    ];
    
    foreach ($shapes as $shape_key => $shape_info) {
        $shape_label = $shape_info['label'];
        $shape_icon = $shape_info['icon'];
        
        echo '<div style="padding: 15px; margin: 12px; border: 2px solid #e0e0e0; border-radius: 8px; background: #f9f9f9;">';
        echo '<h4 style="margin-top: 0; color: #333; font-size: 15px;">' . $shape_icon . ' ' . $shape_label . '</h4>';
        
        $current_data = isset($shapes_data[$shape_key]) ? $shapes_data[$shape_key] : [];
        
        // Checkbox для включения/отключения формы
        $enabled = isset($current_data['enabled']) ? $current_data['enabled'] : false;
        
        woocommerce_wp_checkbox([
            'id' => '_shape_' . $shape_key . '_enabled',
            'label' => 'Активировать эту форму',
            'description' => 'Отметьте, чтобы форма отображалась в калькуляторе',
            'value' => $enabled ? 'yes' : 'no',
        ]);
        
        echo '<div class="shape-params-' . $shape_key . '" style="' . (!$enabled ? 'opacity: 0.5; pointer-events: none;' : '') . '">';
        
        // === ШИРИНА ===
        echo '<div style="background: #fff; padding: 12px; margin: 10px 0; border-radius: 5px; border-left: 3px solid #2196F3;">';
        echo '<h5 style="margin: 0 0 10px 0; color: #555;">Ширина (мм)</h5>';
        echo '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">';
        
        woocommerce_wp_text_input([
            'id' => '_shape_' . $shape_key . '_width_min',
            'label' => 'Минимум',
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '1'],
            'value' => isset($current_data['width_min']) ? $current_data['width_min'] : '',
            'placeholder' => '100',
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_shape_' . $shape_key . '_width_max',
            'label' => 'Максимум',
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '1'],
            'value' => isset($current_data['width_max']) ? $current_data['width_max'] : '',
            'placeholder' => '300',
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_shape_' . $shape_key . '_width_step',
            'label' => 'Шаг',
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '1'],
            'value' => isset($current_data['width_step']) ? $current_data['width_step'] : '50',
            'placeholder' => '50',
        ]);
        
        echo '</div></div>';
        
        // === ВЫСОТА (для П-образной - две высоты) ===
        if ($shape_key === 'p') {
            // Высота 1
            echo '<div style="background: #fff; padding: 12px; margin: 10px 0; border-radius: 5px; border-left: 3px solid #4CAF50;">';
            echo '<h5 style="margin: 0 0 10px 0; color: #555;">Высота 1 (мм) - левая сторона</h5>';
            echo '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">';
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height1_min',
                'label' => 'Минимум',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height1_min']) ? $current_data['height1_min'] : '',
                'placeholder' => '100',
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height1_max',
                'label' => 'Максимум',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height1_max']) ? $current_data['height1_max'] : '',
                'placeholder' => '300',
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height1_step',
                'label' => 'Шаг',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height1_step']) ? $current_data['height1_step'] : '50',
                'placeholder' => '50',
            ]);
            
            echo '</div></div>';
            
            // Высота 2
            echo '<div style="background: #fff; padding: 12px; margin: 10px 0; border-radius: 5px; border-left: 3px solid #8BC34A;">';
            echo '<h5 style="margin: 0 0 10px 0; color: #555;">Высота 2 (мм) - правая сторона</h5>';
            echo '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">';
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height2_min',
                'label' => 'Минимум',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height2_min']) ? $current_data['height2_min'] : '',
                'placeholder' => '100',
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height2_max',
                'label' => 'Максимум',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height2_max']) ? $current_data['height2_max'] : '',
                'placeholder' => '300',
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height2_step',
                'label' => 'Шаг',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height2_step']) ? $current_data['height2_step'] : '50',
                'placeholder' => '50',
            ]);
            
            echo '</div></div>';
        } else {
            // Обычная высота для Г и О форм
            echo '<div style="background: #fff; padding: 12px; margin: 10px 0; border-radius: 5px; border-left: 3px solid #4CAF50;">';
            echo '<h5 style="margin: 0 0 10px 0; color: #555;">Высота (мм)</h5>';
            echo '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">';
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height_min',
                'label' => 'Минимум',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height_min']) ? $current_data['height_min'] : '',
                'placeholder' => '100',
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height_max',
                'label' => 'Максимум',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height_max']) ? $current_data['height_max'] : '',
                'placeholder' => '300',
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height_step',
                'label' => 'Шаг',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height_step']) ? $current_data['height_step'] : '50',
                'placeholder' => '50',
            ]);
            
            echo '</div></div>';
        }
        
        // === ДЛИНА ===
        echo '<div style="background: #fff; padding: 12px; margin: 10px 0; border-radius: 5px; border-left: 3px solid #FF9800;">';
        echo '<h5 style="margin: 0 0 10px 0; color: #555;">Длина (м)</h5>';
        echo '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">';
        
        woocommerce_wp_text_input([
            'id' => '_shape_' . $shape_key . '_length_min',
            'label' => 'Минимум',
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0.1'],
            'value' => isset($current_data['length_min']) ? $current_data['length_min'] : '',
            'placeholder' => '1.0',
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_shape_' . $shape_key . '_length_max',
            'label' => 'Максимум',
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0.1'],
            'value' => isset($current_data['length_max']) ? $current_data['length_max'] : '',
            'placeholder' => '6.0',
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_shape_' . $shape_key . '_length_step',
            'label' => 'Шаг',
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0.01'],
            'value' => isset($current_data['length_step']) ? $current_data['length_step'] : '0.5',
            'placeholder' => '0.5',
        ]);
        
        echo '</div></div>';
        
        echo '</div>'; // .shape-params
        echo '</div>'; // блок формы
    }
    
    echo '</div>';
    
    // JavaScript для включения/отключения полей
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Обработка чекбоксов включения форм
        $('input[id^="_shape_"][id$="_enabled"]').on('change', function() {
            var shapeKey = $(this).attr('id').replace('_shape_', '').replace('_enabled', '');
            var paramsBlock = $('.shape-params-' + shapeKey);
            
            if ($(this).is(':checked')) {
                paramsBlock.css({
                    'opacity': '1',
                    'pointer-events': 'auto'
                });
            } else {
                paramsBlock.css({
                    'opacity': '0.5',
                    'pointer-events': 'none'
                });
            }
        });
        
        // Валидация: макс должен быть больше мин
        $('input[id*="_min"], input[id*="_max"]').on('blur', function() {
            var fieldId = $(this).attr('id');
            var isMin = fieldId.includes('_min');
            var baseId = fieldId.replace('_min', '').replace('_max', '');
            
            var minField = $('#' + baseId + '_min');
            var maxField = $('#' + baseId + '_max');
            
            var minVal = parseFloat(minField.val());
            var maxVal = parseFloat(maxField.val());
            
            if (minVal && maxVal && minVal >= maxVal) {
                alert('⚠️ Максимальное значение должно быть больше минимального!');
                if (isMin) {
                    minField.css('border-color', 'red');
                } else {
                    maxField.css('border-color', 'red');
                }
            } else {
                minField.css('border-color', '');
                maxField.css('border-color', '');
            }
        });
    });
    </script>
    <style>
    .falsebalk_shapes_group .form-field {
        padding: 8px 0 !important;
    }
    .falsebalk_shapes_group input[type="number"] {
        max-width: 100px;
    }
    </style>
    <?php
}

// Сохраняем мета-поля - ОБНОВЛЕННАЯ ВЕРСИЯ
add_action('woocommerce_process_product_meta', 'save_falsebalk_shapes_fields');
function save_falsebalk_shapes_fields($post_id) {
    if (!has_term(266, 'product_cat', $post_id)) {
        return;
    }
    
    $shapes_data = [];
    $shapes = ['g', 'p', 'o'];
    
    foreach ($shapes as $shape_key) {
        // Проверяем, активирована ли форма
        $enabled = isset($_POST['_shape_' . $shape_key . '_enabled']) && $_POST['_shape_' . $shape_key . '_enabled'] === 'yes';
        
        if (!$enabled) {
            continue;
        }
        
        // Общие параметры
        $width_min = isset($_POST['_shape_' . $shape_key . '_width_min']) ? floatval($_POST['_shape_' . $shape_key . '_width_min']) : 0;
        $width_max = isset($_POST['_shape_' . $shape_key . '_width_max']) ? floatval($_POST['_shape_' . $shape_key . '_width_max']) : 0;
        $width_step = isset($_POST['_shape_' . $shape_key . '_width_step']) ? floatval($_POST['_shape_' . $shape_key . '_width_step']) : 50;
        
        $length_min = isset($_POST['_shape_' . $shape_key . '_length_min']) ? floatval($_POST['_shape_' . $shape_key . '_length_min']) : 0;
        $length_max = isset($_POST['_shape_' . $shape_key . '_length_max']) ? floatval($_POST['_shape_' . $shape_key . '_length_max']) : 0;
        $length_step = isset($_POST['_shape_' . $shape_key . '_length_step']) ? floatval($_POST['_shape_' . $shape_key . '_length_step']) : 0.5;
        
        // Параметры высоты зависят от формы
        $shape_data = [
            'enabled' => true,
            'width_min' => $width_min,
            'width_max' => $width_max,
            'width_step' => $width_step > 0 ? $width_step : 50,
            'length_min' => $length_min,
            'length_max' => $length_max,
            'length_step' => $length_step > 0 ? $length_step : 0.5,
        ];
        
        if ($shape_key === 'p') {
            // Для П-образной - две высоты
            $shape_data['height1_min'] = isset($_POST['_shape_' . $shape_key . '_height1_min']) ? floatval($_POST['_shape_' . $shape_key . '_height1_min']) : 0;
            $shape_data['height1_max'] = isset($_POST['_shape_' . $shape_key . '_height1_max']) ? floatval($_POST['_shape_' . $shape_key . '_height1_max']) : 0;
            $shape_data['height1_step'] = isset($_POST['_shape_' . $shape_key . '_height1_step']) ? floatval($_POST['_shape_' . $shape_key . '_height1_step']) : 50;
            
            $shape_data['height2_min'] = isset($_POST['_shape_' . $shape_key . '_height2_min']) ? floatval($_POST['_shape_' . $shape_key . '_height2_min']) : 0;
            $shape_data['height2_max'] = isset($_POST['_shape_' . $shape_key . '_height2_max']) ? floatval($_POST['_shape_' . $shape_key . '_height2_max']) : 0;
            $shape_data['height2_step'] = isset($_POST['_shape_' . $shape_key . '_height2_step']) ? floatval($_POST['_shape_' . $shape_key . '_height2_step']) : 50;
        } else {
            // Для Г и О - одна высота
            $shape_data['height_min'] = isset($_POST['_shape_' . $shape_key . '_height_min']) ? floatval($_POST['_shape_' . $shape_key . '_height_min']) : 0;
            $shape_data['height_max'] = isset($_POST['_shape_' . $shape_key . '_height_max']) ? floatval($_POST['_shape_' . $shape_key . '_height_max']) : 0;
            $shape_data['height_step'] = isset($_POST['_shape_' . $shape_key . '_height_step']) ? floatval($_POST['_shape_' . $shape_key . '_height_step']) : 50;
        }
        
        // Сохраняем только если хотя бы один параметр заполнен
        if ($width_min > 0 || $length_min > 0) {
            $shapes_data[$shape_key] = $shape_data;
        }
    }
    
    if (!empty($shapes_data)) {
        update_post_meta($post_id, '_falsebalk_shapes_data', $shapes_data);
    } else {
        delete_post_meta($post_id, '_falsebalk_shapes_data');
    }
}


// === ADDED: remove price from painting scheme name in cart and orders ===
add_filter('woocommerce_get_item_data', 'remove_price_from_painting_scheme_name', 15, 2);
function remove_price_from_painting_scheme_name($item_data, $cart_item) {
    foreach ($item_data as &$data) {
        if ($data['name'] === 'Схема покраски' || $data['name'] === 'Услуга покраски') {
            $data['value'] = preg_replace('/\s*[\(\+]?\s*\d+[\s\.,]?\d*\s*₽\s*\/\s*м[²2]\s*\)?/u', '', $data['value']);
            $data['value'] = trim($data['value']);
        }
    }
    return $item_data;
}

add_filter('woocommerce_order_item_display_meta_value', 'remove_price_from_order_painting_scheme', 10, 3);
function remove_price_from_order_painting_scheme($display_value, $meta, $item) {
    if ($meta->key === 'Схема покраски' || $meta->key === 'Услуга покраски') {
        $display_value = preg_replace('/\s*[\(\+]?\s*\d+[\s\.,]?\d*\s*₽\s*\/\s*м[²2]\s*\)?/u', '', $display_value);
        $display_value = trim($display_value);
    }
    return $display_value;
}
// === END ADDED ===



// === ADDED: Adjust price HTML for running meter / carpentry products to show 'за м²' ===
add_filter('woocommerce_get_price_html', 'pm_adjust_running_meter_price_html', 20, 2);
function pm_adjust_running_meter_price_html($price_html, $product) {
    if (!is_object($product)) return $price_html;
    $product_id = $product->get_id();
    if (!function_exists('is_running_meter_category')) {
        // cannot determine category, return original
        return $price_html;
    }
    $is_running_meter = is_running_meter_category($product_id);
    // try to detect falsebalk
    $is_falsebalk = false;
    if (function_exists('product_in_category')) {
        $is_falsebalk = product_in_category($product_id, 266);
    }
    $show_falsebalk_calc = false;
    if ($is_falsebalk) {
        $shapes_data = get_post_meta($product_id, '_falsebalk_shapes_data', true);
        if (is_array($shapes_data)) {
            foreach ($shapes_data as $shape_info) {
                if (!empty($shape_info['enabled'])) {
                    $show_falsebalk_calc = true;
                    break;
                }
            }
        }
    }
if ($is_running_meter) {
    $base_price_per_m = floatval($product->get_regular_price() ?: $product->get_price());
    if ($base_price_per_m) {
        $min_width = 0;
        $min_length = 0;
        $multiplier = 1;

        // --- Для фальшбалок ---
        if ($is_falsebalk) {
            $shapes_data = get_post_meta($product_id, '_falsebalk_shapes_data', true);
            $min_variant = null;

            if (is_array($shapes_data)) {
                foreach ($shapes_data as $shape_key => $shape) {
                    if (empty($shape['enabled'])) continue;

                    $width = floatval($shape['width_min'] ?: 100);
                    $length = floatval($shape['length_min'] ?: 1);

                    // Высота
                    if (isset($shape['height_min'])) {
                        $height = floatval($shape['height_min']);
                    } elseif (isset($shape['height1_min'], $shape['height2_min'])) {
                        $height = floatval($shape['height1_min'] + $shape['height2_min']);
                    } else {
                        $height = $width;
                    }

                    // Находим минимальную площадь
                    $area = $width * $height;
                    if ($min_variant === null || $area < $min_variant['area']) {
                        $min_variant = [
                            'width' => $width,
                            'height' => $height,
                            'length' => $length,
                            'section_form' => $shape_key,
                            'area' => $area
                        ];
                    }
                }
            }

            // Если не найден вариант, fallback
            if ($min_variant) {
                $form_multipliers = ['g'=>2, 'p'=>3, 'o'=>4];
                $multiplier = $form_multipliers[$min_variant['section_form']] ?? 1;

                $min_width = $min_variant['width'];
                $min_length = $min_variant['length'];
            } else {
                $min_width = 70;
                $min_length = 0.2;
                $multiplier = 2;
            }
        } else {
            // Для остальных столярных изделий
            $min_width = floatval(get_post_meta($product_id, '_calc_width_min', true)) ?: 100;
            $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true)) ?: 1;
            $multiplier = function_exists('get_price_multiplier') ? get_price_multiplier($product_id) : 1;
        }

        $min_length = round($min_length, 2);
        $min_area = ($min_width / 1000) * $min_length * $multiplier;
        $min_price = $base_price_per_m * $min_area;

        // Вывод цены
        $should_hide_base_price = true;
        if (is_product()) {
            return '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт.</span>';
        } else {
            return '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
        }
    }
}

    return $price_html;
}
// === END ADDED ===
// 1. Добавление полей фаски при редактировании категории в админке
add_action('product_cat_edit_form_fields', 'add_category_faska_fields', 10, 2);
function add_category_faska_fields($term) {
    $term_id = $term->term_id;
    $faska_types = get_term_meta($term_id, 'faska_types', true);
    if (!$faska_types) {
        $faska_types = array();
    }
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label>Типы фасок</label></th>
        <td>
            <div id="faska_types_container">
                <?php for ($i = 1; $i <= 8; $i++): 
                    $faska = isset($faska_types[$i-1]) ? $faska_types[$i-1] : array('name' => '', 'image' => '');
                ?>
                <div style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd;">
                    <h4>Фаска <?php echo $i; ?></h4>
                    <p>
                        <label>Название: 
                            <input type="text" name="faska_types[<?php echo $i-1; ?>][name]" value="<?php echo esc_attr($faska['name']); ?>" style="width: 300px;" />
                        </label>
                    </p>
                    <p>
                        <label>URL изображения: 
                            <input type="text" name="faska_types[<?php echo $i-1; ?>][image]" value="<?php echo esc_url($faska['image']); ?>" style="width: 400px;" />
                            <button type="button" class="button upload_faska_image" data-index="<?php echo $i-1; ?>">Загрузить</button>
                        </label>
                    </p>
                    <?php if ($faska['image']): ?>
                    <p><img src="<?php echo esc_url($faska['image']); ?>" style="max-width: 100px; height: auto;" /></p>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
            <p class="description">Настройте до 8 типов фасок для этой категории</p>
            
            <script>
            jQuery(document).ready(function($) {
                $('.upload_faska_image').click(function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var index = button.data('index');
                    var custom_uploader = wp.media({
                        title: 'Выберите изображение фаски',
                        button: { text: 'Использовать это изображение' },
                        multiple: false
                    }).on('select', function() {
                        var attachment = custom_uploader.state().get('selection').first().toJSON();
                        button.prev('input').val(attachment.url);
                        // Добавляем превью
                        var imgContainer = button.closest('div').find('img');
                        if (imgContainer.length) {
                            imgContainer.attr('src', attachment.url);
                        } else {
                            button.closest('div').append('<p><img src="' + attachment.url + '" style="max-width: 100px; height: auto;" /></p>');
                        }
                    }).open();
                });
            });
            </script>
        </td>
    </tr>
    <?php
}

// 2. Сохранение полей фаски при сохранении категории
add_action('edited_product_cat', 'save_category_faska_fields', 10, 2);
function save_category_faska_fields($term_id) {
    if (isset($_POST['faska_types'])) {
        $faska_types = array();
        foreach ($_POST['faska_types'] as $faska) {
            if (!empty($faska['name']) || !empty($faska['image'])) {
                $faska_types[] = array(
                    'name' => sanitize_text_field($faska['name']),
                    'image' => esc_url_raw($faska['image'])
                );
            }
        }
        update_term_meta($term_id, 'faska_types', $faska_types);
    }
}

// 3. Сохранение выбранной фаски в данные корзины
add_filter('woocommerce_add_cart_item_data', 'add_faska_to_cart', 10, 3);
function add_faska_to_cart($cart_item_data, $product_id, $variation_id) {
    if (isset($_POST['selected_faska_type'])) {
        $cart_item_data['selected_faska'] = sanitize_text_field($_POST['selected_faska_type']);
    }
    return $cart_item_data;
}

// 4. Отображение фаски в корзине
add_filter('woocommerce_get_item_data', 'display_faska_in_cart', 10, 2);
function display_faska_in_cart($item_data, $cart_item) {
    if (isset($cart_item['selected_faska'])) {
        $item_data[] = array(
            'key' => 'Тип фаски',
            'value' => $cart_item['selected_faska']
        );
    }
    return $item_data;
}

// 5. Сохранение фаски в метаданные заказа
add_action('woocommerce_checkout_create_order_line_item', 'add_faska_to_order_items', 10, 4);
function add_faska_to_order_items($item, $cart_item_key, $values, $order) {
    if (isset($values['selected_faska'])) {
        $item->add_meta_data('Тип фаски', $values['selected_faska']);
    }
}

// 6. Правильное отображение названия в админке заказа
add_filter('woocommerce_order_item_display_meta_key', 'filter_order_item_displayed_meta_key', 10, 3);
function filter_order_item_displayed_meta_key($display_key, $meta, $item) {
    if ($meta->key === 'Тип фаски') {
        $display_key = 'Тип фаски';
    }
    return $display_key;
}

// 7. Отображение фаски в письмах о заказе
add_filter('woocommerce_order_item_display_meta_value', 'filter_order_item_displayed_meta_value', 10, 3);
function filter_order_item_displayed_meta_value($display_value, $meta, $item) {
    if ($meta->key === 'Тип фаски') {
        $display_value = '<strong>' . $meta->value . '</strong>';
    }
    return $display_value;
}


add_filter( 'gettext', function( $translated, $text, $domain ) {
    if ( $domain === 'woocommerce' && $text === 'Subtotal' ) {
        $translated = 'Стоимость';
    }
    return $translated;
}, 10, 3 );

$remove_archives_prefix = function( $title ) {
    return preg_replace('/^\s*Архивы[:\s\-\—]*/u', '', $title);
};
add_filter( 'wpseo_title', $remove_archives_prefix, 10 );