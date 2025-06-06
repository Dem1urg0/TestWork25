<?php

/**
 * Функции и определения дочерней темы Storefront Child.
 */

/**
 * Подключаем стили родительской и дочерней темы.
 */
function storefront_child_enqueue_styles()
{
    // Подключаем стиль родительской темы
    wp_enqueue_style('storefront-parent-style', get_template_directory_uri() . '/style.css');

    // Подключаем стиль дочерней темы
    wp_enqueue_style('storefront-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        ['storefront-parent-style'],
        wp_get_theme()->get('Version')
    );
}

add_action('wp_enqueue_scripts', 'storefront_child_enqueue_styles');


/**
 * Задание
 */

/**
 * Регистрация CPT "Cities".
 */
function sfc_register_city_post_type()
{
    $labels = [
        'name' => _x('Cities', 'Post type general name', 'storefront-child'),
        'singular_name' => _x('City', 'Post type singular name', 'storefront-child'),
        'menu_name' => _x('Cities', 'Admin Menu text', 'storefront-child'),
        'add_new' => __('Add New', 'storefront-child'),
        'add_new_item' => __('Add New City', 'storefront-child'),

    ];

    $args = [
        'labels' => $labels,
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-location-alt',
        'supports' => ['title', 'editor', 'thumbnail'],
        'rewrite' => ['slug' => 'cities'],
        'has_archive' => true,
    ];

    register_post_type('city', $args);
}

add_action('init', 'sfc_register_city_post_type');

/**
 * Генерация HTML для метабокса City Coordinates.
 *
 * @param WP_Post $post Объект текущей записи (города).
 */
function sfc_city_coordinates_meta_box_html($post)
{

    // Получаем широту и долготу из метаданных записи, если они существуют
    $latitude = get_post_meta($post->ID, '_sfc_city_latitude', true);
    $longitude = get_post_meta($post->ID, '_sfc_city_longitude', true);

    // Генерируем nonce
    wp_nonce_field('sfc_city_coordinates_action', 'sfc_city_coordinates_nonce_field');
    ?>
    <p>
        <label for="sfc_city_latitude_field"><?php esc_html_e('Latitude:', 'storefront-child'); ?></label><br>
        <input type="text" id="sfc_city_latitude_field" name="sfc_city_latitude_input"
               value="<?php echo esc_attr($latitude); ?>" class="widefat">
    </p>
    <p>
        <label for="sfc_city_longitude_field"><?php esc_html_e('Longitude:', 'storefront-child'); ?></label><br>
        <input type="text" id="sfc_city_longitude_field" name="sfc_city_longitude_input"
               value="<?php echo esc_attr($longitude); ?>" class="widefat">
    </p>
    <?php
}

/**
 * Добавляет метабокс City Coordinates на страницу редактирования Города.
 * @return void
 */
function sfc_add_city_coordinates_meta_box()
{
    add_meta_box(
        'sfc_city_coordinates_id',
        'City Coordinates',
        'sfc_city_coordinates_meta_box_html',
        'city',
        'side',
        'default'
    );
}

add_action('add_meta_boxes_city', 'sfc_add_city_coordinates_meta_box');

/**
 * Сохраняет координаты города при сохранении записи.
 */
function sfc_city_coordinates_meta_box_save($post_id)
{
    // Проверка nonce
    if (!isset($_POST['sfc_city_coordinates_nonce_field']) ||
        !wp_verify_nonce($_POST['sfc_city_coordinates_nonce_field'], 'sfc_city_coordinates_action')) {
        return;
    }

    // Проверка на авто-сохранение
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Проверка прав пользователя
    if (isset($_POST['post_type']) && 'city' == $_POST['post_type']) {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }

    // Сохранение координат
    if (isset($_POST['sfc_city_latitude_input'])) {
        $latitude = sanitize_text_field($_POST['sfc_city_latitude_input']);
        update_post_meta($post_id, '_sfc_city_latitude', $latitude);
    }

    if (isset($_POST['sfc_city_longitude_input'])) {
        $longitude = sanitize_text_field($_POST['sfc_city_longitude_input']);
        update_post_meta($post_id, '_sfc_city_longitude', $longitude);
    }

}

add_action('save_post_city', 'sfc_city_coordinates_meta_box_save');

/**
 * Регистрация таксономии Country для CPT City.
 */
function sfc_register_country_taxonomy()
{
    $labels = [
        'name' => _x('Countries', 'Taxonomy General Name', 'storefront-child'),
        'singular_name' => _x('Country', 'Taxonomy Singular Name', 'storefront-child'),
        'search_items' => __('Search Countries', 'storefront-child'),
        'all_items' => __('All Countries', 'storefront-child'),
        'edit_item' => __('Edit Country', 'storefront-child'),
        'update_item' => __('Update Country', 'storefront-child'),
        'add_new_item' => __('Add New Country', 'storefront-child'),
        'new_item_name' => __('New Country Name', 'storefront-child'),
        'menu_name' => __('Countries', 'storefront-child'),
    ];

    $arg = [
        'labels' => $labels,
        'hierarchical' => false, // не иерархическая таксономия
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'query_var' => true, //url
        'rewrite' => ['slug' => 'country'],
        'show_rest' => true, // для REST API
    ];

    register_taxonomy('country', 'city', $arg);
}

add_action('init', 'sfc_register_country_taxonomy');


/*
 * Класс Виджета погоды
 */

class SFC_City_Weather_Widget extends WP_Widget
{

    /**
     * Конструктор с инициализацией виджета.
     * Задает ID, название и описание виджета.
     */
    public function __construct()
    {
        parent::__construct(
            'sfc_city_weather_widget',
            'City Weather Widget',
            [
                'description' => __('Widget shows weather of selected city', 'storefront-child'),
            ]
        );
    }

    /**
     * Вывод виджета на фронтенде.
     * @params $args Массив аргументов виджета, $instance Массив настроек виджета.
     */
    public function widget($args, $instance)
    {
        echo $args['before_widget'];

        $title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title'], $instance, $this->id_base);
        if (!empty($title)) {
            echo $args['before_title'] . $title . $args['after_title'];
        }

        $selected_city_id = !empty($instance['selected_city_id']) ? absint($instance['selected_city_id']) : 0;

        if (!$selected_city_id) {
            echo '<p>' . esc_html__('Please select a city', 'storefront-child') . '</p>';
            echo $args['after_widget'];
            return;
        }

        $city_post = get_post($selected_city_id);

        if (!$city_post || $city_post->post_type !== 'city') {
            echo '<p>' . esc_html__('Selected city not found', 'storefront-child') . '</p>';
            echo $args['after_widget'];
            return;
        }

        $city_name = get_the_title($city_post);
        $latitude = get_post_meta($selected_city_id, '_sfc_city_latitude', true);
        $longitude = get_post_meta($selected_city_id, '_sfc_city_longitude', true);

        if (empty($latitude) || empty($longitude)) {
            echo '<p>' . sprintf(
                    esc_html__('Coordinates not set for %s.', 'storefront-child'),
                    esc_html($city_name)
                ) . '</p>';
            echo $args['after_widget'];
            return;
        }

        // Получение api ключа из конфига
        $api_key = defined('SFC_OPENWEATHERMAP_API_KEY') ? SFC_OPENWEATHERMAP_API_KEY : '';

        if (empty($api_key)) {
            echo '<p>' . esc_html($city_name) . ': ' . esc_html__('API key not found.', 'storefront-child') . '</p>';
            echo $args['after_widget'];
            return;
        }

        // Формируем url для запроса к OpenWeatherMap API
        $api_url = sprintf(
            'https://api.openweathermap.org/data/2.5/weather?lat=%s&lon=%s&appid=%s&units=metric',
            esc_attr($latitude),
            esc_attr($longitude),
            esc_attr($api_key)
        );

        // Выполняем запрос к API
        $response = wp_remote_get($api_url, ['timeout' => 10]);

        // Обработка ошибки
        if ( is_wp_error($response) ) {
            echo '<p>' . esc_html($city_name) . ': ' . esc_html__('Data unavailable (request error).', 'storefront-child') . '</p>';
            echo $args['after_widget'];
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $weather_data = json_decode($body, true); // true для ассоциативного массива

        // Проверка полученных данных
        if ( $weather_data && isset($weather_data['main']['temp']) && $weather_data['cod'] == 200 ) {
            $temperature = round($weather_data['main']['temp']); // Округляем

            // Выводим информацию о погоде
            echo '<p class="city-weather-info">';
            echo esc_html($city_name) . ': ' . esc_html($temperature) . '°C';
            echo '</p>';

        } else {
            echo '<p>' . esc_html($city_name) . ': ' . esc_html__('Weather data unavailable.', 'storefront-child') . '</p>';
        }

        echo $args['after_widget'];
    }

    /**
     * Форма настройки виджета в админке.
     * @params $instance Массив настроек виджета.
     */
    public
    function form($instance)
    {
        $title = !empty($instance['title']) ? $instance['title'] : __('City Weather', 'storefront-child');
        $selected_city_id = !empty($instance['selected_city_id']) ? $instance['selected_city_id'] : '';

        // Получаем все города из CPT City
        $cities_query = new WP_Query([
            'post_type' => 'city',      // Наш CPT City
            'posts_per_page' => -1,          // Получить все
            'orderby' => 'title',     // Сортировать по названию
            'order' => 'ASC',       // По возрастанию
            'post_status' => 'publish',   // Только опубликованные
        ]);
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title:', 'storefront-child'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('selected_city_id')); ?>"><?php esc_html_e('Select City:', 'storefront-child'); ?></label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('selected_city_id')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('selected_city_id')); ?>">
                <option value=""><?php esc_html_e('-- Select a City --', 'storefront-child'); ?></option>
                <?php if ($cities_query->have_posts()) : ?>
                    <?php while ($cities_query->have_posts()) : $cities_query->the_post(); ?>
                        <option value="<?php echo esc_attr(get_the_ID()); ?>" <?php selected($selected_city_id, get_the_ID()); ?>>
                            <?php echo esc_html(get_the_title()); ?>
                        </option>
                    <?php endwhile; ?>
                    <?php wp_reset_postdata(); // Важно для сброса глобального $post?>
                <?php else : ?>
                    <option value="" disabled><?php esc_html_e('No cities found.', 'storefront-child'); ?></option>
                <?php endif; ?>
            </select>
        </p>
        <?php
    }

    /**
     * Обновление настроек виджета.
     * @params $new_instance Массив новых настроек, $old_instance Массив старых настроек.
     * @return array Возвращает обновленный массив настроек виджета.
     */
    public
    function update($new_instance, $old_instance)
    {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';

        //Обновляем ID выбранного города
        if (!empty($new_instance['selected_city_id'])) {
            $instance['selected_city_id'] = absint($new_instance['selected_city_id']);
        } else {
            $instance['selected_city_id'] = '';
        }

        return $instance;
    }
}


/**
 * Регистрация виджета погоды.
 */
function sfc_register_city_weather_widget()
{
    register_widget('SFC_City_Weather_Widget');
}

add_action('widgets_init', 'sfc_register_city_weather_widget');

/**
 * Подключение скрипта city_search.js.
 * Для поиска городов ajax.
 */
function sfc_weather_search_scripts() {
    if (is_page_template('template_weather_list.php')) {
        wp_enqueue_script(
            'sfc-city-search-script',
            get_stylesheet_directory_uri() . '/js/city_search.js',
            ['jquery'],
            '1.0',
            true                      // footer
        );

        // Передаем данные в JS
        wp_localize_script(
            'sfc-city-search-script',
            'sfc_ajax_params',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'texts'    => [
                    'loading'           => esc_html__('Loading', 'storefront-child'),
                    'error_prefix'      => esc_html__('Error:', 'storefront-child'),
                    'error_unknown'     => esc_html__('Unknown error', 'storefront-child'),
                    'error_ajax_failed' => esc_html__('Ajax failed', 'storefront-child'),
                ],
            ]
        );
    }
}

add_action( 'wp_enqueue_scripts', 'sfc_weather_search_scripts' );

/**
 * Обработчик Ajax запроса для поиска городов.
 */
function sfc_ajax_search_cities_handler() {

    check_ajax_referer('sfc_city_search_action_key', 'nonce');

    // Получаем поисковый запрос
    $search_query = isset($_POST['search_query']) ? sanitize_text_field($_POST['search_query']) : '';

    // Sql запрос для формирования списка городов
    global $wpdb;

    // API ключ для OpenWeatherMap
    $api_key = defined('SFC_OPENWEATHERMAP_API_KEY') ? SFC_OPENWEATHERMAP_API_KEY : '';

    // Создание условия для поиска
    $search_condition = '';
    if (!empty($search_query)) {
        $like_pattern = '%' . $wpdb->esc_like($search_query) . '%';
        $search_condition = $wpdb->prepare("AND (p.post_title LIKE %s OR t.name LIKE %s)", $like_pattern, $like_pattern);
    }

    // SQL запрос для получения списка городов и стран
    $query = $wpdb->prepare("SELECT t.name AS country_name, p.ID AS city_id, p.post_title AS city_name
              FROM {$wpdb->prefix}terms AS t
              INNER JOIN {$wpdb->prefix}term_taxonomy AS tt ON t.term_id = tt.term_id
              INNER JOIN {$wpdb->prefix}term_relationships AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
              INNER JOIN {$wpdb->prefix}posts AS p ON tr.object_id = p.ID
              WHERE tt.taxonomy = %s AND p.post_type = %s AND p.post_status = %s
              {$search_condition}
              ORDER BY t.name ASC, p.post_title ASC",
              'country', 'city', 'publish');

    // Выполняем запрос к базе данных
    $results = $wpdb->get_results($query);

    // Буферизуем вывод
    ob_start();

    if ($results) {
        // Группируем города по странам
        $countries_cities = [];
        foreach ($results as $row) {
            if (!isset($countries_cities[$row->country_name])) {
                $countries_cities[$row->country_name] = [
                    'cities' => []
                ];
            }
            $countries_cities[$row->country_name]['cities'][] = [
                'id' => $row->city_id,
                'name' => $row->city_name
            ];
        }

        if (!empty($countries_cities)) {
            // Только таблица без формы поиска
            ?>
            <table class="city-weather-table">
                <thead>
                <tr>
                    <th><?php esc_html_e('Country', 'storefront-child'); ?></th>
                    <th><?php esc_html_e('City', 'storefront-child'); ?></th>
                    <th><?php esc_html_e('Temperature', 'storefront-child'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($countries_cities as $country_name => $country_data) : ?>
                    <?php foreach ($country_data['cities'] as $city) : ?>
                        <?php
                        // Получаем координаты города
                        $latitude = get_post_meta($city['id'], '_sfc_city_latitude', true);
                        $longitude = get_post_meta($city['id'], '_sfc_city_longitude', true);
                        $temperature_output = esc_html__('N/A', 'storefront-child');

                        if (!empty($latitude) && !empty($longitude) && !empty($api_key)) {
                            // Api запрос
                            $api_url = sprintf(
                                'https://api.openweathermap.org/data/2.5/weather?lat=%s&lon=%s&appid=%s&units=metric',
                                esc_attr($latitude),
                                esc_attr($longitude),
                                esc_attr($api_key)
                            );

                            $response = wp_remote_get($api_url, ['timeout' => 5]);

                            // Проверяем ответ от API
                            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                                $body = wp_remote_retrieve_body($response);
                                $weather_data = json_decode($body, true);

                                if ($weather_data && isset($weather_data['main']['temp'])) {
                                    $temperature_output = round($weather_data['main']['temp']) . '°C';
                                } else {
                                    $temperature_output = esc_html__('Error', 'storefront-child');
                                }
                            } else {
                                $temperature_output = esc_html__('Unavailable', 'storefront-child');
                            }
                        } elseif (empty($api_key)) {
                            $temperature_output = esc_html__('API Key Missing', 'storefront-child');
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html($country_name); ?></td>
                            <td><?php echo esc_html($city['name']); ?></td>
                            <td><?php echo $temperature_output; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        } else {
            echo '<p>' . esc_html__('No cities found matching your criteria.', 'storefront-child') . '</p>';
        }
    } else {
        echo '<p>' . esc_html__('No cities found matching your criteria.', 'storefront-child') . '</p>';
    }

    // Получаем содержимое буфера
    $html_result = ob_get_clean();

    // Отправляем ответ
    wp_send_json_success($html_result);
}

// Регистрируем Ajax обработчик
add_action('wp_ajax_sfc_search_cities', 'sfc_ajax_search_cities_handler');
add_action('wp_ajax_nopriv_sfc_search_cities', 'sfc_ajax_search_cities_handler');