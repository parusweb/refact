<?php
/**
 * Plugin Name: ParusWeb Functions
 * Plugin URI: https://parusweb.ru
 * Description: Модульный плагин для расширения функционала WooCommerce
 * Version: 1.0.1
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
    exit; // Прямой доступ запрещен
}

// ВАЖНО: Этот плагин содержит только функционал WooCommerce
// Специфичные для темы функции (Bricks, меню, виджеты) должны быть в functions.php темы

// Константы плагина
define('PARUSWEB_VERSION', '1.0.0');
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
            'product-calculations' => array(
                'name' => 'Расчеты для товаров',
                'description' => 'Расчет площади, цен и множителей для товаров',
                'file' => 'product-calculations.php',
                'dependencies' => array(),
                'admin_only' => false
            ),
            'price-display' => array(
                'name' => 'Отображение цен',
                'description' => 'Форматирование цен для разных типов товаров (м², м.п., листы)',
                'file' => 'price-display.php',
                'dependencies' => array('product-calculations'),
                'admin_only' => false
            ),
            'legacy-javascript' => array(
                'name' => 'Legacy JavaScript',
                'description' => 'Весь JavaScript из исходного файла (калькуляторы, покраска, фаски, фильтры)',
                'file' => 'legacy-javascript.php',
                'dependencies' => array('product-calculations'),
                'admin_only' => false
            ),
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
            'cart-functionality' => array(
                'name' => 'Функционал корзины',
                'description' => 'Логика работы корзины, добавление товаров с расчетами',
                'file' => 'cart-functionality.php',
                'dependencies' => array('product-calculations'),
                'admin_only' => false
            ),
            'frontend-display' => array(
                'name' => 'Калькуляторы на фронтенде',
                'description' => 'Калькуляторы площади и погонных метров',
                'file' => 'frontend-display.php',
                'dependencies' => array('product-calculations'),
                'admin_only' => false
            ),
            'order-processing' => array(
                'name' => 'Обработка заказов',
                'description' => 'Создание и обработка заказов',
                'file' => 'order-processing.php',
                'dependencies' => array('cart-functionality'),
                'admin_only' => false
            ),
            'account-customization' => array(
                'name' => 'Настройка личного кабинета',
                'description' => 'Кастомизация меню и страниц личного кабинета WooCommerce',
                'file' => 'account-customization.php',
                'dependencies' => array(),
                'admin_only' => false
            ),
            'ajax-handlers' => array(
                'name' => 'AJAX обработчики',
                'description' => 'Обработчики AJAX запросов',
                'file' => 'ajax-handlers.php',
                'dependencies' => array('product-calculations'),
                'admin_only' => false
            ),
            'shortcodes' => array(
                'name' => 'Шорткоды',
                'description' => 'Пользовательские шорткоды',
                'file' => 'shortcodes.php',
                'dependencies' => array(),
                'admin_only' => false
            ),
            'misc-functions' => array(
                'name' => 'Прочие функции',
                'description' => 'Различные вспомогательные функции',
                'file' => 'misc-functions.php',
                'dependencies' => array(),
                'admin_only' => false
            ),
            'acf-integration' => array(
                'name' => 'Интеграция ACF',
                'description' => 'Настройки полей ACF и опций темы',
                'file' => 'acf-integration.php',
                'dependencies' => array(),
                'admin_only' => false
            ),
            'falsebalk-meta' => array(
                'name' => 'Фальшбалки',
                'description' => 'Логика фальшбалок',
                'file' => 'falsebalk-meta.php',
                'dependencies' => array(),
                'admin_only' => false
            ),
            'pm-paint-schemes' => array(
            'name' => 'Покраска',
            'description' => 'Настройки вывода схем покраски',
            'file' => 'pm-paint-schemes.php',
            'dependencies' => array(),
            'admin_only' => false
            )
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
     * Добавление меню в админке
     */
    public function add_admin_menu() {
        add_options_page(
            'Главный плагин',
            'Модули плагина',
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
            echo '<div class="notice notice-success"><p>Настройки сохранены!</p></div>';
        }
        
        $enabled_modules = get_option('parusweb_enabled_modules', array_keys($this->available_modules));
        
        ?>
        <div class="wrap parusweb-modules-page">
            <h1>ParusWeb Functions - Управление модулями</h1>
            
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
                            ?>
                            <tr data-module="<?php echo esc_attr($module_id); ?>"
                                data-dependencies="<?php echo esc_attr(json_encode($module['dependencies'])); ?>"
                                data-dependents="<?php echo esc_attr(json_encode($dependents)); ?>">
                                <td>
                                    <input type="checkbox" 
                                           name="parusweb_modules[]" 
                                           value="<?php echo esc_attr($module_id); ?>"
                                           <?php checked($is_enabled); ?>
                                           class="module-checkbox">
                                </td>
                                <td>
                                    <strong><?php echo esc_html($module['name']); ?></strong>
                                    <br><?php echo esc_html($module['file']); ?>
                                    <?php if ($module['admin_only']): ?>
                                        <span class="dashicons dashicons-admin-tools" title="Только для админки"></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($module['description']); ?></td>
                                <td>
                                    <?php if (!empty($module['dependencies'])): ?>
                                        <?php foreach ($module['dependencies'] as $dep): ?>
                                            <span class="dependency-badge">
                                                <?php echo esc_html($this->available_modules[$dep]['name']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="no-deps">Нет</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_loaded): ?>
                                        <span class="status-loaded">✓ Загружен</span>
                                    <?php elseif ($is_enabled && !$deps_met): ?>
                                        <span class="status-error">⚠ Нет зависимостей</span>
                                    <?php elseif ($is_enabled): ?>
                                        <span class="status-pending">○ Будет загружен</span>
                                    <?php else: ?>
                                        <span class="status-disabled">− Отключен</span>
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
            
            <div class="parusweb-info">
                <h3>Информация о зависимостях</h3>
                <p>При отключении модуля, от которого зависят другие модули, зависимые модули также будут отключены автоматически.</p>
                <p>Модули с пометкой <span class="dashicons dashicons-admin-tools"></span> загружаются только в админ-панели.</p>
            </div>
        </div>
        <?php
    }
}

// Инициализация плагина
function parusweb_functions_init() {
    return ParusWeb_Functions::instance();
}

// Запуск после загрузки всех плагинов
add_action('plugins_loaded', 'parusweb_functions_init');