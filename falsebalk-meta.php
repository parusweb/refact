<?php
/**
 * Модуль: Метаданные фальшбалок
 * Описание: Управление параметрами фальшбалок (формы сечения, размеры)
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Добавление метабокса для фальшбалок
 */
add_action('add_meta_boxes', 'add_falsebalk_metabox');
function add_falsebalk_metabox() {
    add_meta_box(
        'falsebalk_shapes',
        'Настройки фальшбалок',
        'render_falsebalk_metabox',
        'product',
        'normal',
        'high'
    );
}

/**
 * Отрисовка метабокса
 */
function render_falsebalk_metabox($post) {
    wp_nonce_field('falsebalk_meta_nonce', 'falsebalk_meta_nonce');
    
    $shapes_data = get_post_meta($post->ID, '_falsebalk_shapes_data', true);
    if (!is_array($shapes_data)) {
        $shapes_data = [
            'g' => ['enabled' => false],
            'p' => ['enabled' => false],
            'o' => ['enabled' => false]
        ];
    }
    
    $shape_labels = [
        'g' => 'Г-образная',
        'p' => 'П-образная',
        'o' => 'О-образная'
    ];
    
    ?>
    <div class="falsebalk-settings">
        <?php foreach (['g', 'p', 'o'] as $shape_key): ?>
            <?php
            $shape_data = isset($shapes_data[$shape_key]) ? $shapes_data[$shape_key] : [];
            $enabled = !empty($shape_data['enabled']);
            ?>
            
            <div class="shape-section" style="margin-bottom: 30px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                <h3>
                    <label>
                        <input type="checkbox" 
                               name="falsebalk_shapes[<?php echo $shape_key; ?>][enabled]" 
                               value="1"
                               <?php checked($enabled); ?>
                               class="shape-toggle">
                        <?php echo $shape_labels[$shape_key]; ?>
                    </label>
                </h3>
                
                <div class="shape-params" style="<?php echo $enabled ? '' : 'display:none;'; ?>">
                    
                    <!-- Ширина -->
                    <div class="param-group" style="margin: 15px 0;">
                        <h4>Ширина (мм)</h4>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                            <label>
                                Мин:
                                <input type="number" 
                                       name="falsebalk_shapes[<?php echo $shape_key; ?>][width_min]"
                                       value="<?php echo esc_attr($shape_data['width_min'] ?? ''); ?>"
                                       step="100"
                                       style="width: 100%;">
                            </label>
                            <label>
                                Макс:
                                <input type="number" 
                                       name="falsebalk_shapes[<?php echo $shape_key; ?>][width_max]"
                                       value="<?php echo esc_attr($shape_data['width_max'] ?? ''); ?>"
                                       step="100"
                                       style="width: 100%;">
                            </label>
                            <label>
                                Шаг:
                                <input type="number" 
                                       name="falsebalk_shapes[<?php echo $shape_key; ?>][width_step]"
                                       value="<?php echo esc_attr($shape_data['width_step'] ?? 100); ?>"
                                       step="50"
                                       style="width: 100%;">
                            </label>
                        </div>
                    </div>
                    
                    <!-- Высота (для П-образной - две высоты) -->
                    <?php if ($shape_key === 'p'): ?>
                        <div class="param-group" style="margin: 15px 0;">
                            <h4>Высота 1 (мм)</h4>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                                <label>
                                    Мин:
                                    <input type="number" 
                                           name="falsebalk_shapes[<?php echo $shape_key; ?>][height1_min]"
                                           value="<?php echo esc_attr($shape_data['height1_min'] ?? ''); ?>"
                                           step="10"
                                           style="width: 100%;">
                                </label>
                                <label>
                                    Макс:
                                    <input type="number" 
                                           name="falsebalk_shapes[<?php echo $shape_key; ?>][height1_max]"
                                           value="<?php echo esc_attr($shape_data['height1_max'] ?? ''); ?>"
                                           step="10"
                                           style="width: 100%;">
                                </label>
                                <label>
                                    Шаг:
                                    <input type="number" 
                                           name="falsebalk_shapes[<?php echo $shape_key; ?>][height1_step]"
                                           value="<?php echo esc_attr($shape_data['height1_step'] ?? 10); ?>"
                                           step="5"
                                           style="width: 100%;">
                                </label>
                            </div>
                        </div>
                        
                        <div class="param-group" style="margin: 15px 0;">
                            <h4>Высота 2 (мм)</h4>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                                <label>
                                    Мин:
                                    <input type="number" 
                                           name="falsebalk_shapes[<?php echo $shape_key; ?>][height2_min]"
                                           value="<?php echo esc_attr($shape_data['height2_min'] ?? ''); ?>"
                                           step="10"
                                           style="width: 100%;">
                                </label>
                                <label>
                                    Макс:
                                    <input type="number" 
                                           name="falsebalk_shapes[<?php echo $shape_key; ?>][height2_max]"
                                           value="<?php echo esc_attr($shape_data['height2_max'] ?? ''); ?>"
                                           step="10"
                                           style="width: 100%;">
                                </label>
                                <label>
                                    Шаг:
                                    <input type="number" 
                                           name="falsebalk_shapes[<?php echo $shape_key; ?>][height2_step]"
                                           value="<?php echo esc_attr($shape_data['height2_step'] ?? 10); ?>"
                                           step="5"
                                           style="width: 100%;">
                                </label>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="param-group" style="margin: 15px 0;">
                            <h4>Высота (мм)</h4>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                                <label>
                                    Мин:
                                    <input type="number" 
                                           name="falsebalk_shapes[<?php echo $shape_key; ?>][height_min]"
                                           value="<?php echo esc_attr($shape_data['height_min'] ?? ''); ?>"
                                           step="10"
                                           style="width: 100%;">
                                </label>
                                <label>
                                    Макс:
                                    <input type="number" 
                                           name="falsebalk_shapes[<?php echo $shape_key; ?>][height_max]"
                                           value="<?php echo esc_attr($shape_data['height_max'] ?? ''); ?>"
                                           step="10"
                                           style="width: 100%;">
                                </label>
                                <label>
                                    Шаг:
                                    <input type="number" 
                                           name="falsebalk_shapes[<?php echo $shape_key; ?>][height_step]"
                                           value="<?php echo esc_attr($shape_data['height_step'] ?? 10); ?>"
                                           step="5"
                                           style="width: 100%;">
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Длина -->
                    <div class="param-group" style="margin: 15px 0;">
                        <h4>Длина (м)</h4>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                            <label>
                                Мин:
                                <input type="number" 
                                       name="falsebalk_shapes[<?php echo $shape_key; ?>][length_min]"
                                       value="<?php echo esc_attr($shape_data['length_min'] ?? ''); ?>"
                                       step="0.1"
                                       style="width: 100%;">
                            </label>
                            <label>
                                Макс:
                                <input type="number" 
                                       name="falsebalk_shapes[<?php echo $shape_key; ?>][length_max]"
                                       value="<?php echo esc_attr($shape_data['length_max'] ?? ''); ?>"
                                       step="0.1"
                                       style="width: 100%;">
                            </label>
                            <label>
                                Шаг:
                                <input type="number" 
                                       name="falsebalk_shapes[<?php echo $shape_key; ?>][length_step]"
                                       value="<?php echo esc_attr($shape_data['length_step'] ?? 0.1); ?>"
                                       step="0.1"
                                       style="width: 100%;">
                            </label>
                        </div>
                    </div>
                    
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('.shape-toggle').on('change', function() {
            $(this).closest('.shape-section').find('.shape-params').toggle(this.checked);
        });
    });
    </script>
    <?php
}

/**
 * Сохранение метаданных фальшбалок
 */
add_action('save_post_product', 'save_falsebalk_metabox');
function save_falsebalk_metabox($post_id) {
    if (!isset($_POST['falsebalk_meta_nonce']) || 
        !wp_verify_nonce($_POST['falsebalk_meta_nonce'], 'falsebalk_meta_nonce')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    if (isset($_POST['falsebalk_shapes'])) {
        $shapes_data = [];
        
        foreach ($_POST['falsebalk_shapes'] as $shape_key => $shape_data) {
            $shapes_data[$shape_key] = [
                'enabled' => !empty($shape_data['enabled']),
                'width_min' => !empty($shape_data['width_min']) ? floatval($shape_data['width_min']) : 0,
                'width_max' => !empty($shape_data['width_max']) ? floatval($shape_data['width_max']) : 0,
                'width_step' => !empty($shape_data['width_step']) ? floatval($shape_data['width_step']) : 100,
                'height_min' => !empty($shape_data['height_min']) ? floatval($shape_data['height_min']) : 0,
                'height_max' => !empty($shape_data['height_max']) ? floatval($shape_data['height_max']) : 0,
                'height_step' => !empty($shape_data['height_step']) ? floatval($shape_data['height_step']) : 10,
                'length_min' => !empty($shape_data['length_min']) ? floatval($shape_data['length_min']) : 0,
                'length_max' => !empty($shape_data['length_max']) ? floatval($shape_data['length_max']) : 0,
                'length_step' => !empty($shape_data['length_step']) ? floatval($shape_data['length_step']) : 0.1,
            ];
            
            // Для П-образной добавляем вторую высоту
            if ($shape_key === 'p') {
                $shapes_data[$shape_key]['height1_min'] = !empty($shape_data['height1_min']) ? floatval($shape_data['height1_min']) : 0;
                $shapes_data[$shape_key]['height1_max'] = !empty($shape_data['height1_max']) ? floatval($shape_data['height1_max']) : 0;
                $shapes_data[$shape_key]['height1_step'] = !empty($shape_data['height1_step']) ? floatval($shape_data['height1_step']) : 10;
                $shapes_data[$shape_key]['height2_min'] = !empty($shape_data['height2_min']) ? floatval($shape_data['height2_min']) : 0;
                $shapes_data[$shape_key]['height2_max'] = !empty($shape_data['height2_max']) ? floatval($shape_data['height2_max']) : 0;
                $shapes_data[$shape_key]['height2_step'] = !empty($shape_data['height2_step']) ? floatval($shape_data['height2_step']) : 10;
            }
        }
        
        update_post_meta($post_id, '_falsebalk_shapes_data', $shapes_data);
    }
}

/**
 * Добавление полей фаски для категорий
 */
add_action('product_cat_add_form_fields', 'add_faska_types_field');
function add_faska_types_field() {
    ?>
    <div class="form-field">
        <label>Типы фасок</label>
        <div id="faska-types-container">
            <!-- Здесь будут добавляться поля через JS -->
        </div>
        <button type="button" id="add-faska-type" class="button">Добавить тип фаски</button>
        <p class="description">Настройка доступных типов фасок для товаров этой категории</p>
    </div>
    <?php
}

/**
 * Редактирование полей фаски для категорий
 */
add_action('product_cat_edit_form_fields', 'edit_faska_types_field', 10, 1);
function edit_faska_types_field($term) {
    $faska_types = get_term_meta($term->term_id, 'faska_types', true);
    if (!is_array($faska_types)) {
        $faska_types = [];
    }
    ?>
    <tr class="form-field">
        <th scope="row">
            <label>Типы фасок</label>
        </th>
        <td>
            <div id="faska-types-container">
                <?php foreach ($faska_types as $index => $faska): ?>
                    <div class="faska-type-row" style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                        <input type="text" 
                               name="faska_types[<?php echo $index; ?>][name]" 
                               value="<?php echo esc_attr($faska['name']); ?>"
                               placeholder="Название типа фаски"
                               style="width: 200px; margin-right: 10px;">
                        <input type="text" 
                               name="faska_types[<?php echo $index; ?>][image]" 
                               value="<?php echo esc_attr($faska['image']); ?>"
                               placeholder="URL изображения"
                               style="width: 300px; margin-right: 10px;">
                        <button type="button" class="button remove-faska-type">Удалить</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add-faska-type" class="button">Добавить тип фаски</button>
            <p class="description">Настройка доступных типов фасок для товаров этой категории</p>
        </td>
    </tr>
    
    <script>
    jQuery(document).ready(function($) {
        let faskaIndex = <?php echo count($faska_types); ?>;
        
        $('#add-faska-type').on('click', function() {
            const html = `
                <div class="faska-type-row" style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                    <input type="text" 
                           name="faska_types[${faskaIndex}][name]" 
                           placeholder="Название типа фаски"
                           style="width: 200px; margin-right: 10px;">
                    <input type="text" 
                           name="faska_types[${faskaIndex}][image]" 
                           placeholder="URL изображения"
                           style="width: 300px; margin-right: 10px;">
                    <button type="button" class="button remove-faska-type">Удалить</button>
                </div>
            `;
            $('#faska-types-container').append(html);
            faskaIndex++;
        });
        
        $(document).on('click', '.remove-faska-type', function() {
            $(this).closest('.faska-type-row').remove();
        });
    });
    </script>
    <?php
}

/**
 * Сохранение типов фасок для категории
 */
add_action('created_product_cat', 'save_faska_types_field', 10, 1);
add_action('edited_product_cat', 'save_faska_types_field', 10, 1);
function save_faska_types_field($term_id) {
    if (isset($_POST['faska_types'])) {
        $faska_types = [];
        foreach ($_POST['faska_types'] as $faska) {
            if (!empty($faska['name'])) {
                $faska_types[] = [
                    'name' => sanitize_text_field($faska['name']),
                    'image' => esc_url_raw($faska['image'])
                ];
            }
        }
        update_term_meta($term_id, 'faska_types', $faska_types);
    }
}