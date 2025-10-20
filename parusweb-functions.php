<?php
/**
 * Plugin Name: ParusWeb Functions
 * Plugin URI: https://parusweb.ru
 * Description: Модульный плагин для расширения функционала WooCommerce
 * Version: 1.0.2
 * Author: ParusWeb
 * Author URI: https://parusweb.ru
 * Text Domain: parusweb-functions
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Константы плагина
define('PARUSWEB_VERSION', '1.0.2');
define('PARUSWEB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PARUSWEB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PARUSWEB_MODULES_DIR', PARUSWEB_PLUGIN_DIR . 'modules/');

/**
 * Основной класс плагина
 */
class ParusWeb_Functions {
    
    private static $instance = null;
    private $active_modules = array();
    private $available_modules = array();
    
    /**
     * Singleton
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Конструктор
     */
    private function __construct() {
        $this->define_modules();
        $this->load_active_modules();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Определение доступных модулей
     */
    private function define_modules() {
        $this->available_modules = array(
            // КРИТИЧЕСКИЙ МОДУЛЬ - Проверка категорий
            'category-helpers' => array(
                'name' => 'Функции категорий',
                'description' => 'Проверка категорий и определение типов калькуляторов (КРИТИЧЕСКИЙ)',
                'file' => 'category-helpers.php',
                'dependencies' => array(),
                'admin_only' => false
            ),
            // Расчеты и цены
            'product-calculations' => array(
                'name' => 'Расчеты для товаров',
                'description' => 'Расчет площади, цен и множителей',
                'file' => 'product-calculations.php',
                'dependencies' => array('category-helpers'),
                'admin_only' => false
            ),
            'price-display' => array(
                'name' => 'Отображение цен',
                'description' => 'Форматирование цен для разных типов товаров',
                'file' => 'price-display.php',
                'dependencies' => array('product-calculations', 'category-helpers'),
                'admin_only' => false
            ),
            // JavaScript и frontend
            'legacy-javascript' => array(
                'name' => 'Legacy JavaScript',
                'description' => 'JavaScript код (калькуляторы, покраска, фаски, фильтры)',
                'file' => 'legacy-javascript.php',
                'dependencies' => array('product-calculations', 'category-helpers'),
                'admin_only' => false
            ),
            'frontend-display' => array(
                'name' => 'Калькуляторы на фронтенде',
                'description' => 'Вывод калькуляторов площади и погонных метров',
                'file' => 'frontend-display.php',
                'dependencies' => array('product-calculations', 'category-helpers'),
                'admin_only' => false
            ),
            // Корзина и заказы
            'cart-functionality' => array(
                'name' => 'Функционал корзины',
                'description' => 'Логика корзины, добавление товаров с расчетами',
                'file' => 'cart-functionality.php',
                'dependencies' => array('product-calculations', 'category-helpers'),
                'admin_only' => false
            ),
            'order-processing' => array(
                'name' => 'Обработка заказов',
                'description' => 'Создание и обработка заказов',
                'file' => 'order-processing.php',
                'dependencies' => array('cart-functionality'),
                'admin_only' => false
            ),
            // Метаданные
            'product-meta' => array(
                'name' => 'Метаданные товаров',
                'description' => 'Кастомные поля товаров в админке',
                'file' => 'product-meta.php',
                'dependencies' => array(),
                'admin_only' => true
            ),
            'category-meta' => array(
                'name' => 'Метаданные категорий',
                'description' => 'Кастомные поля категорий товаров',
                'file' => 'category-meta.php',
                'dependencies' => array(),
                'admin_only' => true
            ),
            // ACF и специфичные функции
            'acf-integration' => array(
                'name' => 'Интеграция ACF',
                'description' => 'Настройки полей ACF и опций темы',
                'file' => 'acf-integration.php',
                'dependencies' => array(),
                'admin_only' => false
            ),
            'falsebalk-meta' => array(
                'name' => 'Фальшбалки',
                'description' => 'Логика фальшбалок и метабокс',
                'file' => 'falsebalk-meta.php',
                'dependencies' => array('category-helpers'),
                'admin_only' => false
            ),
            'pm-paint-schemes' => array(
                'name' => 'Схемы покраски',
                'description' => 'Настройки и вывод схем покраски',
                'file' => 'pm-paint-schemes.php',
                'dependencies' => array('category-helpers', 'acf-integration'),
                'admin_only' => false
            ),
            'menu-attributes' => array(
                'name' => 'Атрибуты в меню',
                'description' => 'Фильтры атрибутов товаров в меню и категориях',
                'file' => 'menu-attributes.php',
                'dependencies' => array(),
                'admin_only' => false
            ),
            // AJAX и прочее
            'ajax-handlers' => array(
                'name' => 'AJAX обработчики',
                'description' => 'Обработчики AJAX запросов',
                'file' => 'ajax-handlers.php',
                'dependencies' => array('product-calculations', 'category-helpers'),
                'admin_only' => false
            ),
            'shortcodes' => array(
                'name' => 'Шорткоды',
                'description' => 'Пользовательские шорткоды',
                'file' => 'shortcodes.php',
                'dependencies' => array('category-helpers'),
                'admin_only' => false
            ),
            'misc-functions' => array(
                'name' => 'Прочие функции',
                'description' => 'Различные вспомогательные функции',
                'file' => 'misc-functions.php',
                'dependencies' => array(),
                'admin_only' => false
            ),
            'account-customization' => array(
                'name' => 'Настройка личного кабинета',
                'description' => 'Кастомизация меню и страниц ЛК WooCommerce',
                'file' => 'account-customization.php',
                'dependencies' => array(),
                'admin_only' => false
            ),
        );
    }
    
    /**
     * Загрузка активных модулей
     */
    private function load_active_modules() {
        $enabled_modules = get_option('parusweb_enabled_modules', array_keys($this->available_modules));
        
        foreach ($enabled_modules as $module_id) {
            if (!isset($this->available_modules[$module_id])) {
                continue;
            }
            
            $module = $this->available_modules[$module_id];
            
            // Проверка зависимостей
            if (!$this->check_dependencies($module_id)) {
                continue;
            }
            
            // Проверка admin_only
            if ($module['admin_only'] && !is_admin()) {
                continue;
            }
            
            // Загрузка модуля
            $module_file = PARUSWEB_MODULES_DIR . $module['file'];
            if (file_exists($module_file)) {
                require_once $module_file;
                $this->active_modules[] = $module_id;
            }
        }
    }
    
    /**
     * Проверка зависимостей модуля
     */
    private function check_dependencies($module_id) {
        $module = $this->available_modules[$module_id];
        $enabled_modules = get_option('parusweb_enabled_modules', array_keys($this->available_modules));
        
        foreach ($module['dependencies'] as $dependency) {
            if (!in_array($dependency, $enabled_modules)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Получение зависимых модулей
     */
    private function get_dependent_modules($module_id) {
        $dependents = array();
        
        foreach ($this->available_modules as $id => $module) {
            if (in_array($module_id, $module['dependencies'])) {
                $dependents[] = $id;
            }
        }
        
        return $dependents;
    }
    
    /**
     * Получение активных модулей (для отладки)
     */
    public function get_active_modules() {
        return $this->active_modules;
    }
    
    /**
     * Добавление меню в админке
     */
    public function add_admin_menu() {
        add_options_page(
            'ParusWeb Модули',
            'ParusWeb Модули',
            'manage_options',
            'parusweb-modules',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Регистрация настроек
     */
    public function register_settings() {
        register_setting('parusweb_modules', 'parusweb_enabled_modules');
    }
    
    /**
     * Подключение скриптов админки
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_parusweb-modules') {
            return;
        }
        
        wp_enqueue_style('parusweb-admin', PARUSWEB_PLUGIN_URL . 'assets/css/admin.css', array(), PARUSWEB_VERSION);
        wp_enqueue_script('parusweb-admin', PARUSWEB_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), PARUSWEB_VERSION, true);
        
        wp_localize_script('parusweb-admin', 'paruswebModules', array(
            'dependencies' => $this->get_all_dependencies(),
            'dependents' => $this->get_all_dependents()
        ));
    }
    
    /**
     * Получение всех зависимостей
     */
    private function get_all_dependencies() {
        $deps = array();
        foreach ($this->available_modules as $id => $module) {
            $deps[$id] = $module['dependencies'];
        }
        return $deps;
    }
    
    /**
     * Получение всех зависимых модулей
     */
    private function get_all_dependents() {
        $deps = array();
        foreach ($this->available_modules as $id => $module) {
            $deps[$id] = $this->get_dependent_modules($id);
        }
        return $deps;
    }
    
    /**
     * Отрисовка страницы настроек
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Сохранение настроек
        if (isset($_POST['parusweb_save_modules']) && check_admin_referer('parusweb_modules_save')) {
            $enabled = isset($_POST['parusweb_modules']) ? array_map('sanitize_text_field', $_POST['parusweb_modules']) : array();
            update_option('parusweb_enabled_modules', $enabled);
            echo '<div class="notice notice-success"><p>Настройки сохранены! Обновите страницу чтобы увидеть изменения.</p></div>';
        }
        
        $enabled_modules = get_option('parusweb_enabled_modules', array_keys($this->available_modules));
        
        ?>
        <div class="wrap parusweb-modules-page">
            <h1>ParusWeb Functions - Управление модулями</h1>
            
            <div class="notice notice-info">
                <p><strong>⚠️ ВАЖНО:</strong> Модуль <code>category-helpers</code> критически важен! Без него не работают калькуляторы, покраска и отображение цен.</p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('parusweb_modules_save'); ?>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="50">Вкл.</th>
                            <th>Модуль</th>
                            <th>Описание</th>
                            <th>Зависимости</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->available_modules as $module_id => $module): ?>
                            <?php
                            $is_enabled = in_array($module_id, $enabled_modules);
                            $deps_met = $this->check_dependencies($module_id);
                            $dependents = $this->get_dependent_modules($module_id);
                            $is_loaded = in_array($module_id, $this->active_modules);
                            $is_critical = $module_id === 'category-helpers';
                            ?>
                            <tr data-module="<?php echo esc_attr($module_id); ?>"
                                data-dependencies="<?php echo esc_attr(json_encode($module['dependencies'])); ?>"
                                data-dependents="<?php echo esc_attr(json_encode($dependents)); ?>"
                                <?php if ($is_critical) echo 'style="background:#fff3cd;"'; ?>>
                                <td>
                                    <input type="checkbox" 
                                           name="parusweb_modules[]" 
                                           value="<?php echo esc_attr($module_id); ?>"
                                           <?php checked($is_enabled); ?>
                                           <?php if ($is_critical) echo 'disabled'; ?>
                                           class="module-checkbox">
                                    <?php if ($is_critical): ?>
                                        <input type="hidden" name="parusweb_modules[]" value="<?php echo esc_attr($module_id); ?>">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($module['name']); ?></strong>
                                    <?php if ($is_critical): ?>
                                        <span style="color:#856404; font-weight:bold;"> [КРИТИЧЕСКИЙ]</span>
                                    <?php endif; ?>
                                    <br><code><?php echo esc_html($module['file']); ?></code>
                                    <?php if ($module['admin_only']): ?>
                                        <span class="dashicons dashicons-admin-tools" title="Только для админки"></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($module['description']); ?></td>
                                <td>
                                    <?php if (!empty($module['dependencies'])): ?>
                                        <?php foreach ($module['dependencies'] as $dep): ?>
                                            <span class="dependency-badge" style="display:inline-block; padding:2px 6px; margin:2px; background:#e7f3ff; border-radius:3px; font-size:11px;">
                                                <?php echo esc_html($this->available_modules[$dep]['name']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color:#999;">Нет</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_loaded): ?>
                                        <span style="color:#46b450;">✓ Загружен</span>
                                    <?php elseif ($is_enabled && !$deps_met): ?>
                                        <span style="color:#dc3232;">⚠ Нет зависимостей</span>
                                    <?php elseif ($is_enabled): ?>
                                        <span style="color:#00a0d2;">○ Будет загружен</span>
                                    <?php else: ?>
                                        <span style="color:#999;">− Отключен</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" name="parusweb_save_modules" class="button button-primary" value="Сохранить изменения">
                </p>
            </form>
            
            <div class="card">
                <h3>Информация о зависимостях</h3>
                <ul>
                    <li>При отключении модуля, от которого зависят другие модули, зависимые модули также будут отключены автоматически.</li>
                    <li>Модули с пометкой <span class="dashicons dashicons-admin-tools"></span> загружаются только в админ-панели.</li>
                    <li><strong>Критические модули</strong> нельзя отключить - они необходимы для работы плагина.</li>
                </ul>
            </div>
            
            <div class="card">
                <h3>Текущий статус</h3>
                <p><strong>Загружено модулей:</strong> <?php echo count($this->active_modules); ?> из <?php echo count($enabled_modules); ?> включенных</p>
                <p><strong>Активные модули:</strong></p>
                <ul style="columns: 2;">
                    <?php foreach ($this->active_modules as $module_id): ?>
                        <li><code><?php echo esc_html($module_id); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <style>
        .parusweb-modules-page .card {
            padding: 15px;
            margin-top: 15px;
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .parusweb-modules-page .card h3 {
            margin-top: 0;
        }
        </style>
        <?php
    }
}

// Инициализация плагина
function parusweb_functions_init() {
    return ParusWeb_Functions::instance();
}

// Запуск после загрузки всех плагинов
add_action('plugins_loaded', 'parusweb_functions_init');
