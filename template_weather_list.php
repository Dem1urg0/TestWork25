<?php
/**
 * Template Name: City Weather List
 */

get_header();
?>

    <div id="primary" class="content-area">
        <main id="main" class="site-main" role="main">
            <article class="page type-page status-publish hentry">
                <header class="entry-header">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                </header>

                <div class="entry-content">
                    <?php
                    global $wpdb;

                    // Получаем список городов и стран из базы данных
                    $results = $wpdb->get_results($wpdb->prepare(
                        "SELECT t.name AS country_name, p.ID AS city_id, p.post_title AS city_name
                         FROM {$wpdb->prefix}terms AS t
                         INNER JOIN {$wpdb->prefix}term_taxonomy AS tt ON t.term_id = tt.term_id
                         INNER JOIN {$wpdb->prefix}term_relationships AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                         INNER JOIN {$wpdb->prefix}posts AS p ON tr.object_id = p.ID
                         WHERE tt.taxonomy = %s AND p.post_type = %s AND p.post_status = %s
                         ORDER BY t.name ASC, p.post_title ASC", 'country', 'city', 'publish')
                    );

                    // получаем API
                    $api_key = defined('SFC_OPENWEATHERMAP_API_KEY') ? SFC_OPENWEATHERMAP_API_KEY : '';

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

                        // Вывод таблицы
                        if (!empty($countries_cities)) :

                            // ХУК для добавления действий перед таблицей
                            do_action('sfc_before_city_weather_table');

                            ?>
                            <div id="city-weather-table-wrapper">
                                <div id="sfc-search-form-wrapper">
                                    <form role="search" method="get" id="sfc-city-search-form" action="">
                                        <label for="sfc-city-search-input"><?php esc_html_e('Search City:', 'storefront-child'); ?></label>
                                        <input type="text" id="sfc-city-search-input" name="sfc_search_input_name" value="" placeholder="<?php esc_attr_e('Enter city name...', 'storefront-child'); ?>">
                                        <input type="submit" id="sfc-city-search-submit" value="<?php esc_attr_e('Search', 'storefront-child'); ?>">
                                        <?php wp_nonce_field('sfc_city_search_action_key', 'sfc_city_search_nonce_field_name'); ?>
                                    </form>
                                </div>
                                <div id="city-weather-table-container">
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
                                                            // Ошибка в данных ответа
                                                            $temperature_output = esc_html__('Error', 'storefront-child');
                                                        }
                                                    } else {
                                                        // Ошибка запроса к API
                                                        $temperature_output = esc_html__('Unavailable', 'storefront-child');
                                                    }
                                                } elseif (empty($api_key)) {
                                                    // API ключ не задан
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
                                </div>
                            </div>
                            <?php

                            // ХУК для добавления действий после таблицы
                            do_action('sfc_after_city_weather_table');

                        else :
                            echo '<p>' . esc_html__('No cities found assigned to countries.', 'storefront-child') . '</p>';
                        endif;

                    } else {
                        echo '<p>' . esc_html__('No data found.', 'storefront-child') . '</p>';
                    }
                    ?>
                </div>
            </article>
        </main>
    </div>

<?php
get_sidebar();
get_footer();
?>