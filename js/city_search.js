/**
 * Ajax запрос для поиска городов в таблице погоды.
 */
jQuery(document).ready(function($) {

    // Обработчик события
    $('#sfc-city-search-form').on('submit', function(event) {
        event.preventDefault(); // Отмена обновления страницы

        // Получение значения из формы и nonce
        var searchQuery = $('#sfc-city-search-input').val();
        var nonceValue = $('input[name="sfc_city_search_nonce_field_name"]').val();

        // Ставим индикатор загрузки
        $('#city-weather-table-container').html('<p class="loading-text">' + sfc_ajax_params.texts.loading + '</p>');

        // Ajax запрос к серверу
        $.ajax({
            url: sfc_ajax_params.ajax_url, // url из php
            type: 'POST',
            data: {
                action: 'sfc_search_cities', // Имя действия для обработки в php
                search_query: searchQuery,      // Значение поиска из формы
                nonce: nonceValue
            },
            success: function(response) {
                // Обработка успешного ответа
                if (response.success) {
                    $('#city-weather-table-container').html(response.data);
                } else {
                    // Если есть ошибка, выводим сообщение
                    var errorMessage = response.data || sfc_ajax_params.texts.error_unknown;
                    $('#city-weather-table-container').html('<p class="error-text">' + sfc_ajax_params.texts.error_prefix + ' ' + errorMessage + '</p>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Обработка ошибки запроса
                console.error("AJAX Request Failed:", textStatus, errorThrown, jqXHR.responseText);
                $('#city-weather-table-container').html('<p class="error-text">' + sfc_ajax_params.texts.error_ajax_failed + '</p>');
            }
        });
    });

    // Обработка очищения поля ввода
    $('#sfc-city-search-input').on('keyup', function() {
        if ($(this).val() === '') {
            // Пустой поиск
            $('#sfc-city-search-form').trigger('submit');
        }
    });

});