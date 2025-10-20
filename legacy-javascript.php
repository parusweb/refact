<?php
/**
 * Модуль: Legacy JavaScript
 * Описание: Весь JavaScript код из оригинального functions.php
 * Автоматически извлечено: 2025-10-17 12:16:23
 */

if (!defined('ABSPATH')) {
    exit;
}

// ========================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ========================================

// Функция get_available_painting_services_by_material
function get_available_painting_services_by_material($product_id) {
    $material_term = wp_get_post_terms($product_id, 'pa_material', ['fields' => 'names']);
    $material = !empty($material_term) && !is_wp_error($material_term) ? $material_term[0] : '';
    
    $schemes_file = get_stylesheet_directory() . '/inc/pm-paint-schemes.php';
    
    if (!file_exists($schemes_file)) {
        return [];
    }
    
    require_once $schemes_file;
    
    global $pm_paint_schemes;
    
    if (empty($pm_paint_schemes) || !is_array($pm_paint_schemes)) {
        return [];
    }
    
    $available_services = [];
    
    foreach ($pm_paint_schemes as $key => $scheme) {
        if (isset($scheme['materials']) && is_array($scheme['materials'])) {
            if (in_array($material, $scheme['materials']) || empty($scheme['materials'])) {
                $available_services[$key] = $scheme;
            }
        } else {
            $available_services[$key] = $scheme;
        }
    }
    
    return $available_services;
}

// Проверка категорий для покраски
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

// Проверка категорий 265-268
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

// Извлечение размеров
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

// ========================================
// Основной калькулятор с покраской
// ========================================

add_action('wp_footer', function () {
    if (!is_product()) return;
    
    global $product;
    $product_id = $product->get_id();

    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'all']);
    
    $is_target = is_in_target_categories($product->get_id());
    $is_multiplier = is_in_multiplier_categories($product->get_id());
    $is_square_meter = is_square_meter_category($product->get_id());
    $is_running_meter = is_running_meter_category($product->get_id());
    
    $show_falsebalk_calc = false;
    $is_falsebalk = false;
    $shapes_data = array();
    
    if ($is_square_meter) {
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
        
        if ($is_falsebalk) {
            $shapes_data = get_post_meta($product->get_id(), '_falsebalk_shapes_data', true);
            
            if (is_array($shapes_data)) {
                foreach ($shapes_data as $shape_key => $shape_info) {
                    if (is_array($shape_info)) {
                        $enabled = !empty($shape_info['enabled']);
                        
                        if ($enabled) {
                            $has_width = !empty($shape_info['width_min']) || !empty($shape_info['width_max']);
                            
                            $has_height = false;
                            if ($shape_key === 'p') {
                                $has_height = !empty($shape_info['height1_min']) || !empty($shape_info['height1_max']) ||
                                             !empty($shape_info['height2_min']) || !empty($shape_info['height2_max']);
                            } else {
                                $has_height = !empty($shape_info['height_min']) || !empty($shape_info['height_max']);
                            }
                            
                            $has_length = !empty($shape_info['length_min']) || !empty($shape_info['length_max']);
                            
                            $has_old_format = !empty($shape_info['widths']) || 
                                             !empty($shape_info['heights']) || 
                                             !empty($shape_info['lengths']);
                            
                            if ($has_width || $has_height || $has_length || $has_old_format) {
                                $show_falsebalk_calc = true;
                                break;
                            }
                        }
                    }
                }
            }
        }
    }
    
    if (!$is_target && !$is_multiplier) {
        return;
    }
    
    $title = $product->get_name();
    $pack_area = extract_area_with_qty($title, $product->get_id());
    $dims = extract_dimensions_from_title($title);
    
    $painting_services = get_available_painting_services_by_material($product->get_id());
    $price_multiplier = get_price_multiplier($product->get_id());
    
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
    
    $product_id = $product->get_id();
    $leaf_parent_id = 190;
    $leaf_children = [191, 127, 94];
    $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
    $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
    $unit_text = $is_leaf_category ? 'лист' : 'упаковку';
    $unit_forms = $is_leaf_category ? ['лист', 'листа', 'листов'] : ['упаковка', 'упаковки', 'упаковок'];
    $is_square_meter = has_term([270, 267, 268], 'product_cat', $product->get_id());
    $is_running_meter = has_term([266, 271], 'product_cat', $product->get_id());
    
    $product_cats = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
    $show_faska = false;
    $faska_types = array();

    if ($product_cats && !is_wp_error($product_cats)) {
        foreach ($product_cats as $cat_id) {
            if (in_array($cat_id, array(268, 270))) {
                $show_faska = true;
                $faska_types = get_term_meta($cat_id, 'faska_types', true);
                if ($faska_types) break;
            }
        }
    }
    ?>

    <script>
    const isSquareMeter = <?php echo $is_square_meter ? 'true' : 'false'; ?>;
    const isRunningMeter = false;
    const paintingServices = <?php echo json_encode($painting_services); ?>;
    const priceMultiplier = <?php echo $price_multiplier; ?>;
    const isMultiplierCategory = <?php echo $is_multiplier ? 'true' : 'false'; ?>;
    const calcSettings = <?php echo $calc_settings ? json_encode($calc_settings) : 'null'; ?>;

    document.addEventListener('DOMContentLoaded', function() {
        let form = document.querySelector('form.cart') || 
                  document.querySelector('form[action*="add-to-cart"]');
        let singleButton = document.querySelector('.single_add_to_cart_button');
        if (!form && singleButton) {
            form = singleButton.closest('form');
        }
        if (!form) return;

        let quantityInput = document.querySelector('input[name="quantity"]') ||
                           document.querySelector('.qty') ||
                           document.querySelector('.input-text.qty');

        const resultBlock = document.createElement('div');
        resultBlock.id = 'custom-calc-block';
        resultBlock.className = 'calc-result-container';
        resultBlock.style.marginTop = '20px';
        resultBlock.style.marginBottom = '20px';
        form.insertAdjacentElement('afterend', resultBlock);

        let isAutoUpdate = false;

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

        function createPaintingServicesBlock(currentCategoryId) {
            if (Object.keys(paintingServices).length === 0) return null;

            const paintingBlock = document.createElement('div');
            paintingBlock.id = 'painting-services-block';

            let optionsHTML = '<option value="" selected>Без покраски</option>';
            Object.entries(paintingServices).forEach(([key, service]) => {
                let optionText = service.name;
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

        <?php if($is_multiplier && !$show_falsebalk_calc): ?>
        const multiplierCalc = document.createElement('div');
        multiplierCalc.id = 'calc-multiplier';

        let calcHTML = '<br><h4>Калькулятор стоимости</h4>';
        calcHTML += '<div style="display:flex;gap:20px;flex-wrap:wrap;align-items: center;">';

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

        if (calcSettings && calcSettings.length_min > 0 && calcSettings.length_max > 0) {
            calcHTML += `<label>Длина (м): 
                <select id="mult_length" style="margin-left:10px;background:#fff;">
                    <option value="">Выберите...</option>`;
            const lengthMin = calcSettings.length_min;
            const lengthMax = calcSettings.length_max;
            const lengthStep = calcSettings.length_step;
            const stepsCount = Math.round((lengthMax - lengthMin) / lengthStep) + 1;
            
            for (let i = 0; i < stepsCount; i++) {
                const value = lengthMin + (i * lengthStep);
                const displayValue = value.toFixed(2);
                calcHTML += `<option value="${displayValue}">${displayValue}</option>`;
            }
            calcHTML += `</select></label>`;
        } else {
            calcHTML += `<label>Длина (м): 
                <input type="number" id="mult_length" min="0.01" step="0.01" placeholder="0.01" style="width:100px; margin-left:10px;background:#fff">
            </label>`;
        }

        calcHTML += `<label style="display:none">Количество (шт): <span id="mult_quantity_display" style="display:none">1</span></label>`;
        calcHTML += '</div>';

        <?php if ($show_faska && !empty($faska_types)): ?>
        calcHTML += `<div id="faska_selection" style="margin-top: 10px; display: none;">
            <h5>Выберите тип фаски:</h5>
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
        <?php endif; ?>

        calcHTML += '<div id="calc_mult_result" style="margin-top:10px; font-size:1.3em"></div>';
        multiplierCalc.innerHTML = calcHTML;
        resultBlock.appendChild(multiplierCalc);

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

            const quantity = (quantityInput && !isNaN(parseInt(quantityInput.value))) ? parseInt(quantityInput.value) : 1;
            multQuantityDisplay.textContent = quantity;

            <?php if ($show_faska): ?>
            const faskaSelection = document.getElementById('faska_selection');
            if (faskaSelection) {
                if (widthValue > 0 && lengthValue > 0) {
                    faskaSelection.style.display = 'block';
                } else {
                    faskaSelection.style.display = 'none';
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
            const selectedFaska = document.querySelector('input[name="faska_type"]:checked');
            if (selectedFaska) {
                createHiddenField('selected_faska_type', selectedFaska.value);
            } else {
                removeHiddenFields('selected_faska_');
            }
            <?php endif; ?>
        }

        multWidthEl.addEventListener('change', updateMultiplierCalc);
        multLengthEl.addEventListener('change', updateMultiplierCalc);

        <?php if ($show_faska): ?>
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
                        faskaGrid.style.display = 'none';
                        faskaSelected.style.display = 'block';
                        
                        faskaSelectedName.textContent = this.value;
                        faskaSelectedImage.src = this.dataset.image;
                        faskaSelectedImage.alt = this.value;
                    }
                    updateMultiplierCalc();
                });
            });
            
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

        if (quantityInput) {
            quantityInput.addEventListener('input', function() {
                if (!isAutoUpdate) {
                    const mainQty = parseInt(this.value);
                    if (mainQty > 0 && multWidthEl.value && multLengthEl.value) {
                        updateMultiplierCalc();
                    }
                }
            });
        }
        <?php endif; ?>

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

        if (paintingBlock) {
            const serviceSelect = document.getElementById('painting_service_select');
            if (serviceSelect) {
                serviceSelect.addEventListener('change', function() {
                    const areaInput = document.getElementById('calc_area_input');
                    const widthEl = document.getElementById('custom_width');
                    const lengthEl = document.getElementById('custom_length');
                    const multWidthEl = document.getElementById('mult_width');
                    const multLengthEl = document.getElementById('mult_length');

                    if (areaInput && areaInput.value) {
                        updateAreaCalc();
                        return;
                    }

                    if (widthEl && lengthEl) {
                        const width = parseFloat(widthEl.value);
                        const length = parseFloat(lengthEl.value);
                        if (width > 0 && length > 0) {
                            updateDimCalc(true);
                            return;
                        }
                    }

                    if (multWidthEl && multLengthEl) {
                        const width = parseFloat(multWidthEl.value);
                        const length = parseFloat(multLengthEl.value);
                        if (width > 0 && length > 0) {
                            updateMultiplierCalc();
                            return;
                        }
                    }

                    const rmLengthEl = document.getElementById('rm_length');
                    if (rmLengthEl && rmLengthEl.value) {
                        updateRunningMeterCalc();
                        return;
                    }

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
        
        document.addEventListener('change', function(e) {
            if (e.target.name === 'pm_selected_color') {
                const areaInput = document.getElementById('calc_area_input');
                const widthEl = document.getElementById('custom_width');
                const lengthEl = document.getElementById('custom_length');
                const multWidthEl = document.getElementById('mult_width');
                const multLengthEl = document.getElementById('mult_length');
                const rmLengthEl = document.getElementById('rm_length');
                const sqWidthEl = document.getElementById('sq_width');
                const sqLengthEl = document.getElementById('sq_length');
                
                if (areaInput && areaInput.value) {
                    updateAreaCalc();
                    return;
                }
                
                if (widthEl && lengthEl && widthEl.value && lengthEl.value) {
                    updateDimCalc(true);
                    return;
                }
                
                if (multWidthEl && multLengthEl && multWidthEl.value && multLengthEl.value) {
                    updateMultiplierCalc();
                    return;
                }
                
                if (rmLengthEl && rmLengthEl.value) {
                    updateRunningMeterCalc();
                    return;
                }
                
                if (sqWidthEl && sqLengthEl && sqWidthEl.value && sqLengthEl.value) {
                    updateSquareMeterCalc();
                    return;
                }
            }
        });

        <?php if($is_running_meter): ?>
            <?php 
            $is_falsebalk = product_in_category($product->get_id(), 266);
            $shapes_data = array();
            $show_falsebalk_calculator = $show_falsebalk_calc;
            
            if ($show_falsebalk_calculator) {
                $shapes_data = get_post_meta($product->get_id(), '_falsebalk_shapes_data', true);
                if (!is_array($shapes_data)) {
                    $shapes_data = array();
                }
            }
            ?>
            
            <?php if ($show_falsebalk_calculator): ?>
                if (resultBlock) {
                    resultBlock.innerHTML = '';
                }
            <?php endif; ?>
            
            const runningMeterCalc = document.createElement('div');
            runningMeterCalc.id = 'calc-running-meter';

            let rmCalcHTML = '<br><h4>Калькулятор стоимости</h4>';

        <?php if ($show_falsebalk_calculator): ?>
            const shapesData = <?php echo json_encode($shapes_data); ?>;

            <?php 
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

            rmCalcHTML += '<div style="margin-bottom:20px; border:2px solid #e0e0e0; padding:15px; border-radius:8px; background:#f9f9f9;">';
            rmCalcHTML += '<label style="display:block; margin-bottom:15px; font-weight:600; font-size:1.1em;">Шаг 1: Выберите форму сечения фальшбалки</label>';
            rmCalcHTML += '<div style="display:flex; gap:15px; flex-wrap:wrap;">';
            rmCalcHTML += <?php echo json_encode($shapes_buttons_html); ?>;
            rmCalcHTML += '</div></div>';

            rmCalcHTML += '<div id="falsebalk_params" style="display:none; margin-bottom:20px; border:2px solid #e0e0e0; padding:15px; border-radius:8px; background:#f9f9f9;">';
            rmCalcHTML += '<label style="display:block; margin-bottom:15px; font-weight:600; font-size:1.1em;">Шаг 2: Выберите размеры</label>';
            rmCalcHTML += '<div style="display:flex; gap:20px; flex-wrap:wrap; align-items:center;">';

            rmCalcHTML += `<label style="display:flex; flex-direction:column; gap:5px;">
                <span style="font-weight:500;">Ширина (мм):</span>
                <select id="rm_width" style="background:#fff; padding:8px 12px; border:1px solid #ddd; border-radius:4px; min-width:150px;">
                    <option value="">Сначала выберите форму</option>
                </select>
            </label>`;

            rmCalcHTML += `<div id="height_container" style="display:contents"></div>`;

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

            rmCalcHTML += '<div id="calc_rm_result" style="margin-top:15px;"></div>';

        <?php else: ?>
            rmCalcHTML += '<div style="display:flex;gap:20px;flex-wrap:wrap;align-items: center;">';

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

            runningMeterCalc.innerHTML = rmCalcHTML;
            resultBlock.appendChild(runningMeterCalc);

            if (paintingBlock) {
                runningMeterCalc.appendChild(paintingBlock);
            }

        <?php if ($show_falsebalk_calculator): ?>
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
                
                if (!shapeData || !shapeData.enabled) {
                    return;
                }
                
                falsebalkaParams.style.display = 'block';
                
                const oldWidthFormat = parseOldFormat(shapeData.widths);
                if (oldWidthFormat) {
                    rmWidthEl.innerHTML = '<option value="">Выберите...</option>' + oldWidthFormat;
                } else {
                    rmWidthEl.innerHTML = generateOptions(shapeData.width_min, shapeData.width_max, shapeData.width_step, 'мм');
                }
                
                heightContainer.innerHTML = '';
                if (selectedShape === 'p') {
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
        <?php endif; ?>

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

                const totalLength = lengthValue * quantity;

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
                            paintingArea = 2 * (width_m + height_m) * totalLength;
                        } else {
                            paintingArea = width_m * totalLength;
                        }
                    } else {
                        paintingArea = width_m * totalLength;
                    }
                }

                const materialPrice = paintingArea * basePriceRM * priceMultiplier;
                const pricePerItem = (quantity > 0) ? (materialPrice / quantity) : 0;
                const paintingCost = updatePaintingServiceCost(paintingArea);
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
        <?php endif; ?>

        <?php if($is_square_meter && !$is_running_meter): ?>
        const sqMeterCalc = document.createElement('div');
        sqMeterCalc.id = 'calc-square-meter';

        let sqCalcHTML = '<br><h4>Калькулятор стоимости</h4>';

        sqCalcHTML += '<div style="display:flex;gap:20px;flex-wrap:wrap;align-items: center;">';

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

        if (calcSettings && calcSettings.length_min > 0 && calcSettings.length_max > 0) {
            sqCalcHTML += `<label>Длина (м): 
                <select id="sq_length" style="margin-left:10px;background:#fff;">
                    <option value="">Выберите...</option>`;
            const lengthMin = calcSettings.length_min;
            const lengthMax = calcSettings.length_max;
            const lengthStep = calcSettings.length_step;
            const stepsCount = Math.round((lengthMax - lengthMin) / lengthStep) + 1;
            
            for (let i = 0; i < stepsCount; i++) {
                const value = lengthMin + (i * lengthStep);
                const displayValue = value.toFixed(2);
                sqCalcHTML += `<option value="${displayValue}">${displayValue}</option>`;
            }
            sqCalcHTML += `</select></label>`;
        } else {
            sqCalcHTML += `<label>Длина (м): 
                <input type="number" id="sq_length" min="0.01" step="0.01" placeholder="0.01" style="width:100px; margin-left:10px;background:#fff">
            </label>`;
        }

        sqCalcHTML += '</div><div id="calc_sq_result" style="margin-top:10px; font-size:1.3em"></div>';
        sqMeterCalc.innerHTML = sqCalcHTML;
        resultBlock.appendChild(sqMeterCalc);

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

        if (quantityInput) {
            quantityInput.addEventListener('input', function() {
                if (!isAutoUpdate && sqWidthEl.value && sqLengthEl.value) {
                    updateSquareMeterCalc();
                }
            });
        }
        <?php endif; ?>
    });
    </script>
    <?php
}, 20);

// Добавляем выбранные данные в корзину
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id){
    if (!is_in_target_categories($product_id)) {
        return $cart_item_data;
    }

    $product = wc_get_product($product_id);
    if (!$product) return $cart_item_data;

    $title = $product->get_name();
    $pack_area = extract_area_with_qty($title, $product_id);
    $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());

    $leaf_parent_id = 190;
    $leaf_children = [191, 127, 94];
    $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
    $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);

    $painting_service = null;
    if (!empty($_POST['painting_service_key'])) {
        $painting_service = [
            'key' => sanitize_text_field($_POST['painting_service_key']),
            'name' => sanitize_text_field($_POST['painting_service_name']),
            'price_per_m2' => floatval($_POST['painting_service_price_per_m2']),
            'area' => floatval($_POST['painting_service_area']),
            'total_cost' => floatval($_POST['painting_service_total_cost'])
        ];

        if (!empty($_POST['pm_selected_color_filename'])) {
            $color_filename = sanitize_text_field($_POST['pm_selected_color_filename']);
            $painting_service['color_filename'] = $color_filename;
            $painting_service['name_with_color'] = $painting_service['name'] . ' (' . $color_filename . ')';
        }
    }
    
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

    if (!empty($_POST['custom_mult_width']) && !empty($_POST['custom_mult_length'])) {
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
        
        return $cart_item_data;
    }

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
        
        if (!empty($_POST['custom_rm_shape'])) {
            $rm_data['shape'] = sanitize_text_field($_POST['custom_rm_shape']);
            $rm_data['shape_label'] = sanitize_text_field($_POST['custom_rm_shape_label']);
            $rm_data['height'] = floatval($_POST['custom_rm_height'] ?? 0);
        }
        
        $cart_item_data['custom_running_meter_calc'] = $rm_data;
        return $cart_item_data;
    }

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

// Отображаем выбранные размеры/площадь в корзине и заказе
add_filter('woocommerce_get_item_data', function($item_data, $cart_item){
    if (!empty($cart_item['pm_selected_scheme_name'])) {
        $item_data[] = [
            'name' => 'Схема покраски',
            'value' => $cart_item['pm_selected_scheme_name']
        ];
    }
    if (!empty($cart_item['pm_selected_color'])) {
        $color_display = $cart_item['pm_selected_color'];
        
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
            $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
            $display_text .= '<br>+ ' . $painting_name . ' — ' . number_format($painting['total_cost'], 2, '.', ' ') . ' ₽';
        }
        
        $item_data[] = [
            'name'=>'Размеры',
            'value'=> $display_text
        ];
    }
    
    if(isset($cart_item['custom_running_meter_calc'])){
        $rm_calc = $cart_item['custom_running_meter_calc'];
        
        $display_text = '';
        
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

    if(isset($cart_item['card_pack_purchase'])){
        $pack_data = $cart_item['card_pack_purchase'];
        $display_text = 'Площадь: ' . $pack_data['area'] . ' м² — ' . number_format($pack_data['total_price'], 2, '.', ' ') . ' ₽';
        
        if (isset($pack_data['painting_service']) && $pack_data['painting_service']) {
            $painting = $pack_data['painting_service'];
            $painting_name = isset($painting['name_with_color']) ? $painting['name_with_color'] : $painting['name'];
            $display_text .= '<br>+ ' . $painting_name . ' — ' . number_format($painting['total_cost'], 2, '.', ' ') . ' ₽';
        }
        
        $item_data[] = [
            'name' => 'В корзине ' . $pack_data['unit_type'],
            'value' => $display_text
        ];
    }
    
    if(isset($cart_item['standard_pack_purchase'])){
        $pack_data = $cart_item['standard_pack_purchase'];
        $display_text = 'Площадь: ' . $pack_data['area'] . ' м² — ' . number_format($pack_data['total_price'], 2, '.', ' ') . ' ₽';
        
        if (isset($pack_data['painting_service']) && $pack_data['painting_service']) {
            $painting = $pack_data['painting_service'];
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

// Установка правильного количества при добавлении в корзину
add_filter('woocommerce_add_to_cart_quantity', function($quantity, $product_id) {
    if (!is_in_target_categories($product_id)) return $quantity;

    if (isset($_POST['custom_area_packs']) && !empty($_POST['custom_area_packs']) && 
        isset($_POST['custom_area_area_value']) && !empty($_POST['custom_area_area_value'])) {
        return intval($_POST['custom_area_packs']);
    }

    if (isset($_POST['custom_width_val']) && !empty($_POST['custom_width_val']) && 
        isset($_POST['custom_length_val']) && !empty($_POST['custom_length_val'])) {
        return 1;
    }
    
    return $quantity;
}, 10, 2);

// Дополнительно корректируем количество в корзине
add_action('woocommerce_add_to_cart', function($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    if (!is_in_target_categories($product_id)) return;
    
    if (isset($cart_item_data['custom_area_calc'])) {
        $packs = intval($cart_item_data['custom_area_calc']['packs']);
        if ($packs > 0 && $quantity !== $packs) {
            WC()->cart->set_quantity($cart_item_key, $packs);
        }
    }
}, 10, 6);

// Пересчёт цены в корзине
add_action('woocommerce_before_calculate_totals', function($cart){
    if(is_admin() && !defined('DOING_AJAX')) return;
    foreach($cart->get_cart() as $cart_item){
        $product = $cart_item['data'];
        
        if (!is_object($product) || !method_exists($product, 'get_id')) {
            continue;
        }
        
        if(isset($cart_item['custom_area_calc'])){
            $area_calc = $cart_item['custom_area_calc'];
            $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());
            $pack_area = extract_area_with_qty($product->get_name(), $product->get_id());
            if($pack_area > 0) {
                $price_per_pack = $base_price_m2 * $pack_area;
                
                if (isset($area_calc['painting_service']) && $area_calc['painting_service']) {
                    $painting = $area_calc['painting_service'];
                    $painting_cost_per_pack = $painting['total_cost'] / $area_calc['packs'];
                    $price_per_pack += $painting_cost_per_pack;
                }
                
                $product->set_price($price_per_pack);
            }
        } 
        elseif(isset($cart_item['custom_dimensions'])){
            $dims = $cart_item['custom_dimensions'];
            $total_price = $dims['price'];
            
            if (isset($dims['painting_service']) && $dims['painting_service']) {
                $total_price += $dims['painting_service']['total_cost'];
            }
            
            $product->set_price($total_price);
        }
        elseif(isset($cart_item['custom_multiplier_calc'])){
            $mult_calc = $cart_item['custom_multiplier_calc'];
            $total_price = $mult_calc['price'];
            
            if (isset($mult_calc['painting_service']) && $mult_calc['painting_service']) {
                $total_price += $mult_calc['painting_service']['total_cost'];
            }
            
            $product->set_price($total_price);
        }
        elseif(isset($cart_item['custom_running_meter_calc'])){
            $rm_calc = $cart_item['custom_running_meter_calc'];
            $total_price = $rm_calc['price'];
            
            if (isset($rm_calc['painting_service']) && $rm_calc['painting_service']) {
                $total_price += $rm_calc['painting_service']['total_cost'];
            }
            
            $product->set_price($total_price);
        }
        elseif(isset($cart_item['custom_square_meter_calc'])){
            $sq_calc = $cart_item['custom_square_meter_calc'];
            $total_price = $sq_calc['price'];
            
            if (isset($sq_calc['painting_service']) && $sq_calc['painting_service']) {
                $total_price += $sq_calc['painting_service']['total_cost'];
            }
            
            $product->set_price($total_price);
        }
        elseif(isset($cart_item['card_pack_purchase'])){
            $pack_data = $cart_item['card_pack_purchase'];
            $total_price = $pack_data['total_price'];
            
            if (isset($pack_data['painting_service']) && $pack_data['painting_service']) {
                $total_price += $pack_data['painting_service']['total_cost'];
            }
            
            $product->set_price($total_price);
        }
        elseif(isset($cart_item['standard_pack_purchase'])){
            $pack_data = $cart_item['standard_pack_purchase'];
            $total_price = $pack_data['total_price'];
            
            if (isset($pack_data['painting_service']) && $pack_data['painting_service']) {
                $total_price += $pack_data['painting_service']['total_cost'];
            }
            
            $product->set_price($total_price);
        }
    }
}, 10, 1);

// JavaScript для карточек товаров
add_action('wp_footer', function() {
    if (is_product()) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function processButtons() {
            const buyButtons = document.querySelectorAll('a.add_to_cart_button:not(.product_type_variable), .add_to_cart_button:not(.product_type_variable), a[data-product_id]:not(.product_type_variable)');
            
            buyButtons.forEach(function(button) {
                if (button.dataset.cardProcessed) return;
                button.dataset.cardProcessed = 'true';
                
                const productId = button.dataset.product_id || button.getAttribute('data-product_id');
                if (!productId) return;
                
                const form = document.createElement('form');
                form.style.display = 'none';
                form.method = 'POST';
                form.action = button.href || window.location.href;
                
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
                
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    form.submit();
                });
            });
        }
        
        processButtons();
        
        setTimeout(processButtons, 1000);
        
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

// FacetWP заголовки
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

            const facets = document.querySelectorAll('.facetwp-facet');
            
            facets.forEach(facet => {
                const facetName = facet.getAttribute('data-name');
                const titleText = facetMap[facetName];
                
                if (titleText) {
                    const prevElement = facet.previousElementSibling;
                    const hasTitle = prevElement && 
                                   prevElement.classList.contains('facet-title-added');
                    
                    const hasContent = facet.querySelector('.facetwp-checkbox') || 
                                     facet.querySelector('.facetwp-search') ||
                                     facet.querySelector('.facetwp-slider') ||
                                     facet.innerHTML.trim() !== '';
                    
                    if (!hasTitle && hasContent) {
                        const title = document.createElement('div');
                        title.className = 'facet-title-added';
                        title.innerHTML = `<h4 style="margin: 20px 0 10px 0; padding: 8px 0 5px 0; font-size: 16px; font-weight: 600; color: #333; border-bottom: 2px solid #8bc34a; text-transform: uppercase; letter-spacing: 0.5px;">${titleText}</h4>`;
                        
                        facet.parentNode.insertBefore(title, facet);
                    }
                    
                    if (hasTitle && !hasContent) {
                        const titleElement = facet.previousElementSibling;
                        if (titleElement && titleElement.classList.contains('facet-title-added')) {
                            titleElement.remove();
                        }
                    }
                }
            });
        }

        addFacetTitles();

        const interval = setInterval(addFacetTitles, 300);
        
        setTimeout(() => clearInterval(interval), 10000);

        if (typeof FWP !== 'undefined') {
            document.addEventListener('facetwp-loaded', addFacetTitles);
            document.addEventListener('facetwp-refresh', addFacetTitles);
        }

        const observer = new MutationObserver(addFacetTitles);
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
    </script>
    <?php
});

// JS для обновления цены на лету (со скидкой)
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
                newPrice *= 0.9;
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

// Мега-меню атрибуты
add_action('wp_footer', function(){ ?>
<script>
jQuery(function($){
    let cache = null;

    $.getJSON('<?php echo home_url("/menu_attributes.json"); ?>', function(data){
        cache = data;

        $('.widget_layered_nav').each(function(){
            renderAttributes($(this));
        });
    });

    $(document).on('mouseenter', '.mega-menu-item-type-taxonomy', function(){
        let href = $(this).find('a').attr('href');
        if (!href) return;

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








// Доставка
add_action('wp_enqueue_scripts', function() {
    if (is_checkout() || is_cart()) {
        $api_key = '81c72bf5-a635-4fb5-8939-e6b31aa52ffe';
        wp_enqueue_script('yandex-maps', "https://api-maps.yandex.ru/2.1/?apikey={$api_key}&lang=ru_RU", [], null, true);
        wp_enqueue_script('delivery-calc', get_stylesheet_directory_uri() . '/js/delivery-calc.js', ['jquery','yandex-maps'], '1.3', true);

        $cart_weight = WC()->cart ? WC()->cart->get_cart_contents_weight() : 0;
        wp_localize_script('delivery-calc', 'deliveryVars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'basePoint' => 'г. Санкт-Петербург, Выборгское шоссе 369к6',
            'rateLight' => 200,
            'rateHeavy' => 250,
            'minLight' => 6000,
            'minHeavy' => 7500,
            'minDistance' => 30,
            'cartWeight' => $cart_weight,
            'apiKey' => $api_key
        ]);
    }
});

add_action('wp_ajax_set_delivery_cost', 'set_delivery_cost');
add_action('wp_ajax_nopriv_set_delivery_cost', 'set_delivery_cost');
function set_delivery_cost() {
    if (isset($_POST['cost'])) {
        $cost = round(floatval($_POST['cost']));
        WC()->session->set('custom_delivery_cost', $cost);

        if (!empty($_POST['distance'])) {
            WC()->session->set('delivery_distance', floatval($_POST['distance']));
        }

        if (function_exists('wc_clear_notices')) {
            wc_clear_notices();
        }

        wp_cache_flush();
        WC_Cache_Helper::get_transient_version('shipping', true);
        delete_transient('wc_shipping_method_count');

        $packages_hash = 'wc_ship_' . md5( 
            json_encode(WC()->cart->get_cart_for_session()) . 
            WC()->customer->get_shipping_country() . 
            WC()->customer->get_shipping_state() . 
            WC()->customer->get_shipping_postcode() . 
            WC()->customer->get_shipping_city()
        );
        wp_cache_delete($packages_hash, 'shipping_zones');

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

add_action('wp_ajax_clear_delivery_cost', 'clear_delivery_cost');
add_action('wp_ajax_nopriv_clear_delivery_cost', 'clear_delivery_cost');
function clear_delivery_cost() {
    WC()->session->__unset('custom_delivery_cost');
    WC()->session->__unset('delivery_distance');
    
    WC_Cache_Helper::get_transient_version('shipping', true);
    delete_transient('wc_shipping_method_count');
    
    if (WC()->cart) {
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
    }
    
    wp_send_json_success(['message' => 'Стоимость доставки очищена']);
    wp_die();
}

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

add_filter('woocommerce_shipping_methods', 'add_custom_delivery_method');
function add_custom_delivery_method($methods) {
    $methods['custom_delivery'] = 'WC_Custom_Delivery_Method';
    return $methods;
}

add_action('woocommerce_checkout_update_order_review', 'force_shipping_update');
function force_shipping_update($post_data) {
    if (WC()->session->get('custom_delivery_cost')) {
        WC_Cache_Helper::get_transient_version('shipping', true);
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
    }
}

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

    WC()->session->__unset('custom_delivery_cost');
    WC()->session->__unset('delivery_distance');
}

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

add_filter('woocommerce_checkout_show_ship_to_different_address', '__return_true');
add_filter('woocommerce_cart_needs_shipping_address', '__return_true');
add_filter('woocommerce_ship_to_different_address_checked', '__return_true');

add_filter('woocommerce_billing_fields', 'remove_billing_required_fields');
function remove_billing_required_fields($fields) {
    foreach($fields as $key => &$field) {
        if ($key !== 'billing_email') {
            $field['required'] = false;
        }
    }
    return $fields;
}

// WooCommerce адрес интеграция
add_action('wp_footer', function() {
    if (!is_checkout()) return;
    ?>
    <script>
    jQuery(document).ready(function($) {
        
        window.updateWooCommerceAddress = function(address) {
            setTimeout(function() {
                var $shippingAddress1 = $('input[name="shipping_address_1"]');
                var $shippingAddress2 = $('input[name="shipping_address_2"]');
                var $shippingCity = $('input[name="shipping_city"]');
                
                var $billingAddress1 = $('input[name="billing_address_1"]');
                var $billingAddress2 = $('input[name="billing_address_2"]');  
                var $billingCity = $('input[name="billing_city"]');
                
                var parsedAddress = parseAddressForWooCommerce(address);
                
                if ($shippingAddress1.length) {
                    $shippingAddress1.val(parsedAddress.address1);
                    $shippingAddress1.trigger('input').trigger('change').trigger('blur');
                }
                
                if ($shippingAddress2.length && parsedAddress.address2) {
                    $shippingAddress2.val(parsedAddress.address2);
                    $shippingAddress2.trigger('input').trigger('change').trigger('blur');
                }
                
                if ($shippingCity.length && parsedAddress.city) {
                    $shippingCity.val(parsedAddress.city);
                    $shippingCity.trigger('input').trigger('change').trigger('blur');
                }
                
                if ($billingAddress1.length) {
                    $billingAddress1.val(parsedAddress.address1);
                    $billingAddress1.trigger('input').trigger('change').trigger('blur');
                }
                
                if ($billingAddress2.length && parsedAddress.address2) {
                    $billingAddress2.val(parsedAddress.address2);
                    $billingAddress2.trigger('input').trigger('change').trigger('blur');
                }
                
                if ($billingCity.length && parsedAddress.city) {
                    $billingCity.val(parsedAddress.city);
                    $billingCity.trigger('input').trigger('change').trigger('blur');
                }
                
                setTimeout(function() {
                    $('body').trigger('update_checkout');
                }, 200);
                
            }, 100);
        };
        
        function parseAddressForWooCommerce(fullAddress) {
            var city = '';
            var address1 = fullAddress;
            var address2 = '';
            
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
            
            return {
                city: city,
                address1: address1,
                address2: address2
            };
        }

        function ensureShippingFieldsVisible() {
            $('.woocommerce-shipping-fields, .shipping_address').show();
            $('[name^="shipping_"]').closest('.form-row').show();
            $('#ship-to-different-address-checkbox').prop('checked', true);
        }
        
        ensureShippingFieldsVisible();
        setTimeout(ensureShippingFieldsVisible, 500);
        setTimeout(ensureShippingFieldsVisible, 1000);

        $(document).on('updated_checkout', function() {
            ensureShippingFieldsVisible();
            
            var deliveryMethods = $('#shipping_method li label, .woocommerce-shipping-methods li label');
            
            var customDeliveryRadio = $('input[value*="custom_delivery"]');
            if (customDeliveryRadio.length && !$('input[name="shipping_method[0]"]:checked').length) {
                customDeliveryRadio.prop('checked', true).trigger('change');
            }
        });
        
        $(document).on('change input blur', 'input[name^="shipping_"], input[name^="billing_"]', function() {
        });
    });
    </script>
    <?php
});

// FacetWP замена текста
function facetwp_custom_text_replacement() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function replaceFacetWPText() {
            const toggleElements = document.querySelectorAll('.facetwp-toggle');
            
            toggleElements.forEach(function(element) {
                const regex = /Посмотреть\s+(\d+)\s+Подробнее/g;
                
                if (element.textContent && regex.test(element.textContent)) {
                    element.textContent = element.textContent.replace(regex, 'Развернуть (еще $1)');
                }
            });
            
            const otherElements = document.querySelectorAll('.facetwp-expand, .facetwp-collapse, [class*="facet"] a, [class*="facet"] span');
            
            otherElements.forEach(function(element) {
                const regex = /Посмотреть\s+(\d+)\s+Подробнее/g;
                
                if (element.textContent && regex.test(element.textContent)) {
                    element.textContent = element.textContent.replace(regex, 'Раскрыть $1');
                }
            });
        }
        
        replaceFacetWPText();
        
        document.addEventListener('facetwp-loaded', function() {
            setTimeout(replaceFacetWPText, 100);
        });
        
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    replaceFacetWPText();
                }
            });
        });
        
        const facetContainer = document.querySelector('.facetwp-template');
        if (facetContainer) {
            observer.observe(facetContainer, {
                childList: true,
                subtree: true
            });
        }

        if (document.getElementById('rm_height1')) {
            document.getElementById('rm_height1').addEventListener('change', updateRunningMeterCalc);
        }
        if (document.getElementById('rm_height2')) {
            document.getElementById('rm_height2').addEventListener('change', updateRunningMeterCalc);
        }
        if (document.getElementById('rm_height')) {
            document.getElementById('rm_height').addEventListener('change', updateRunningMeterCalc);
        }

        document.addEventListener('change', function(e) {
            if (!e || !e.target) return;
            if (e.target.id === 'rm_height' || e.target.id === 'rm_height1' || e.target.id === 'rm_height2') {
                if (typeof updateRunningMeterCalc === 'function') updateRunningMeterCalc();
            }
        });

    });
    </script>
    <?php
}

add_action('wp_footer', 'facetwp_custom_text_replacement');
?>