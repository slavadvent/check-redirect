<?php
/**
 * Classic Editor
 *
 * Plugin Name: Check redirection
 * Plugin URI:
 * Description: Корректирорвка редиректов в БД
 * Version:     1.0
 * Author:      s2s
 *
 */

/**
 *  Скрипт чтения контента page и post и для проверки наличия редиректов в контенте для любого сайта на Wordpress
 *  1. Производим замену абсолютных ссылок на относительные.
 *  2. Проверяем все ссылки в контенте и если есть редирект заменяем на новый url (произведенные замены сохраняем в файле)
 *  3. Если есть ссылки с ошибкой (любая, если не 200 и не 300) - сохраняем в файле {имя сайта}-errore-link.csv все данные об ошибке
 *  4. Проверяем все ссылки в контенте на предмет наличия/отсутствия закрывающего слеша (в зависимости от параметров указанных в permalink_structure)
 *  5. ??? добавляем финальный '/' к ссылкам в custom_permalink если таковые есть в базе данных ???
 */


/** Проверяем IP с которого запросили выполнение скрипта */
//if($_SERVER['HTTP_X_REAL_IP'] != '127.0.0.1') die('Для вас недоступна работа скрипта');

/** Проверяем залогиненность в админке пользователя */
//if( !current_user_can('administrator') ) die('Для вас недоступна работа скрипта');


require_once __DIR__ . '/functions-redirect.php';
set_time_limit(500);

$siteurl = parse_url(site_url($path = ''));

define('CR_DIR_PLAGIN', 'check-redirect/');

/** На локальной версии проверить не получиться, потому ставим живой домен */
$siteurl = (($_SERVER['HTTP_X_REAL_IP'] == '127.0.0.1')) ? parse_url('https://ege-study.ru') : parse_url(site_url($path = ''));

define('CR_HOST', $siteurl['host']);
define('CR_PROTOCOL', $siteurl['scheme']);
define('CR_SITEURL', CR_PROTOCOL . '://' . CR_HOST);



/** проверяем структуру permalink. Пока не используем */
if (get_option('permalink_structure')) {
//    echo get_option('permalink_structure');
    $trailingslashit = (user_trailingslashit( '', 'page' ) == '/') ? '/' : '';
    define('CR_SLASHIT', $trailingslashit);

}

//require_once __DIR__ . '/function/function.php';
//require_once __DIR__ . '/function/shortcode.php';

// Срабатывает при регистрации плагина
register_activation_hook(__FILE__, 'check_redirection_activated');

//global $some_var;
//$some_var = 'hey';

/** Функция активации плагина */
function check_redirection_activated()
{
/** Выполняем функциии необходимые при регистрации плагина - создание таблиц/записей в БД, необходимых файлов и т.д. */

}


/** Подключение скрипта в админке */
add_action('admin_enqueue_scripts', 'check_redirection_scripts_admin');
function check_redirection_scripts_admin()
{
    wp_enqueue_style('check_redirection_style', plugins_url(CR_DIR_PLAGIN . 'check_redirection-style.css'));

    /** Отключил тут загрузку скрипта. Перенес загрузку в patt-load */
    //wp_enqueue_script( 'downloaders-pattern', plugins_url('downloaders-pattern/js/downloads-script.js'), ['jquery'], null, true);
    wp_enqueue_script('check-redirect-script', plugins_url(CR_DIR_PLAGIN . 'check-redirect-script.js'), ['jquery'], null, true);

    $data = [
        'upload_url' => admin_url('async-upload.php'),
        'ajax_url'  => admin_url('admin-ajax.php'),
        //'ID_post' => get_the_ID(),  // тестируем передачу ID поста в скрипт. Пока все работает
    ];

    wp_localize_script('check-redirect-script', 'cr_config', $data);

    /** Проверяем наличие созданной страницы для скачивания иначе запишем в переменную ошибку*/
    //if (!insert_page_down_post()) $_SESSION['down_message_error'] = "Ошибка создания страницы загрузки";
}



add_action('admin_menu', 'check_redirection_menu'); // регистрация

// manage_options - изменение настроек (права доступа администратора)
function check_redirection_menu() {
    add_options_page('Check redirections', 'Check 301', 'manage_options', 'check-redirect', 'check_redirection_options');
//    add_options_page('Check redirections', 'Check 301', 'manage_options', 'check-redirect/check-redirect-options.php' );
}

function check_redirection_options() {
    if (!current_user_can('manage_options'))  {
        wp_die( __('У вас нет прав доступа на эту страницу.') );
    }

    $args = ['public' => true];
    $output   = 'names'; // names or objects, note names is the default

    $posts_all_types = get_post_types($args, $output);
    foreach ($posts_all_types as $post_type) {
        if(!post_type_supports($post_type, 'editor')) {
           unset($posts_all_types[$post_type]);
        }
    }

    global $wpdb;

    ?>
    <div class="wrap">
        <h2>Настройка опций исправления редиректов в базе данных</h2>

        <h3>Зарегестрированные типы записей с контентом в базе данных</h3>
        <p>Выберите требующие проверки:</p>
        <div id='cr-flex-container'>

        <?php

//        unset($_SESSION['cr_redirection_links']);
//        unset($_SESSION['cr_check_redirections']);


        /** Создаем дирректорию для log файлов, если ее нет и создаем имена лог файлов с учетом даты */
        $today = date("Ymd_His");

        $dir_log = WP_PLUGIN_DIR . '/' . CR_DIR_PLAGIN . 'log';
        wp_mkdir_p($dir_log);

        /** Задаем имена для лог файлов и сохраняем в $_SESSION */
        $_SESSION['cr_check_redirections']['file_log_change'] = $dir_log . '/' . 'change_' . $today . '.log';
        $_SESSION['cr_check_redirections']['file_log_error'] = $dir_log . '/' . 'error_' . $today . '.log';








////        $pattern = '/<[a]{1}[^>]*[href]*=*["\'](?!mailto|tel|#.*)([^\'">]+)[^>]*>/imu';
////        $pattern = '/<[a]{1}[^>]*[h][r][e][f][^=]*=\s*["\'](?!mailto|tel|#.*)([^\'">]+)[^>]*>/imu';
////      $pattern = '/<[a]{1}[^>]*[h][r][e][f][^=]*=[ "\']*([^ \'">#]+)[^>]*>/ismu';
////      $pattern = '/<[a]{1}[^>]*[h][r][e][f][^=]*=\s*["\'](?!mailto|tel|#.*)([^\'">]+)[^>]*>/imu';
//        //$pattern = '/(?<=a href=")([^\"(?!mailto|tel|#)].*)?(?=")/iu';
//
//
////
//        //$pattern = '/<[img][^>]*[src] *= *["\']{0,1}([^\'">]*)/';
//        //$pattern = '/\< *[img][^\>]*[src] *= *[\"\']{0,1}([^\"\'\ >]*)/';
//        //$pattern = '/<img.*src="(.*)".*>/is';
//        //$pattern = '/<[img|iframe].*src=\s*["\'](.*?)["\'].*>/imu';
//        //$pattern = '/<[img|iframe][\s\S]+?src=\s*["\'](.*?)["\'].*>/imu';
//        $pattern = '/<[img|iframe][\s\S]+?[s][r][c]\s*=\s*["\'](.*?)["\'].*>/imu';
//        $text = '5 и 6 декабря. Двухдневный Мастер-класс Анны Малковой. БЕСПЛАТНО. Смотрите полную видеозапись!
//<div class="grid_8"><img width="560" height="315" src="https://www.youtube.com/embed/_6cSXW2gqzo" frameborder="0" allowfullscreen></img></div>
//<strong>5 декабря</strong>
//
//<div class="grid_8"><iframe width="560" height="315" src="https://www.youtube.com/embed/7-4_jtopiQA" frameborder="0" allowfullscreen></iframe></div>
//<strong>6 декабря</strong>
//<p align="center"><iframe width="560" height="315" src = "https://www.youtube.com/embed/ahc8M_Fh1f4" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></p>
//Вы можете записаться на курсы по любому из школьных предметов:
//<a href=
//"/kursy-podgotovki-k-ege-po-matematike/ege-po-matematike/?ааа=ккк&dffff=аааке2">математике</a>,
//<a HREF="/kursy-ege-po-obshhestvoznaniyu/">обществознанию</a>,
//<a href ="/kursy-ege-russkij-yazyk/">русскому языку</a>,
//<a href= "/kursy-ege-po-informatike/">информатике</a>,
//<A target="_blank" href="/ege-literatura/kursy-ege-po-literature/">литературе</A>,
//<a href="/ege-anglijskij/kursy-ege-po-anglijskomu-yazyku/">английскому языку</a>,
//<a href="/ege-fizika/kursy-ege-po-fizike/">физике</a>,
//<a href="/ege-ximiya/kursy-ege-po-ximii/">химии</a>,
//<a href="/ege-biologiya/kursy-ege-po-biologii/">биологии</a> и
//<a href="/ege-biologiya/kursy-ege-po-biologii/#biologii">биологии</a> и
//<a href="/ege-istoriya/">истории</a>. Мы подберем для вас удобное расписание. Наши учебные классы расположены в центре Москвы, в 5 минутах от метро Тверская, Пушкинская, Чеховская. Дополнительный учебный класс: 5 минут от м. Арбатская, Смоленская
//<a href="mailto:online@ege-study.ru">online@ege-study.ru</a>
//<a href="tel:8-928-25-458">online@ege-study.ru</a>
//<a href="/">английскому языку</a>,
//<a href="#">английскому языку</a>,
//Записи всех мастер-классов доступны в продаже в нашем интернет-магазине, <a href="http://malkova.ege-study.ru" target="_blank">жми сюда.</a>
//<strong>И как всегда, будут Чемпионы. Те, кто наберут максимальные баллы. Для Чемпионов дополнительный приз</strong> –
//<a href="http://stat.ege-study.ru/clicks.php?m=53252&amp;c=467410&amp;i=e65ae66c8496f492fcfbd3dd14b163b0&amp;u=5552">новая книга Анны Малковой по задачам ЕГЭ повышенной сложности</a>. Да, ее разобрали за один день. Надеемся, что в ближайшее время в магазин подвезут ещё.
//<h2 style="text-align: center;">Как получить от Пробного ЕГЭ максимальную пользу?</h2>
//<a href="online@ege-study.ru">online@ege-study.ru</a> и 11 декабря вы получите наш вариант первыми, вне очереди!</strong>
//<h2 style="text-align: center;">Проверка</h2>
//
//<img width="300"
//src="/wp-content/uploads/2012/06/banner.jpg" alt="" />
//Идет набор в группы подготовки к ЕГЭ на 2012 – 2013 учебный год. Звоните сейчас!
//Присылайте решения на почту <a href="mailto:online@ege-study.ru">online@ege-study.ru</a>
//<a href="/wp-content/uploads/2016/11/курсы.jpg"><img class="alignleft size-medium wp-image-1013" title="Курсы подготовки к ЕГЭ" src="/wp-content/uploads/2016/11/курсы.jpg" alt="" width="300" height="202" /></a>
//<a class="daria-goto-anchor" href="http://stat.ege-study.ru/clicks.php?m=53252&amp;c=420489&amp;i=0f932b965ed1daf67b9ceac28a33e843&amp;u=5552"
//target="_blank" rel="noopener noreferrer" data-vdir-href="https://mail.yandex.ru/re.jsx?uid=1130000024967149&amp;c=LIZA&amp;cv=14.13.504&amp;mid=166914661189420809&amp;h=a,1OccBHxna9LsuaQTX5Ne-g&amp;l=aHR0cDovL3N0YXQuZWdlLXN0dWR5LnJ1L2NsaWNrcy5waHA_bT01MzI1MiZjPTQyMDQ4OSZpPTBmOTMyYjk2NWVkMWRhZjY3YjljZWFjMjhhMzNlODQzJnU9NTU1Mg" data-orig-href="http://stat.ege-study.ru/clicks.php?m=53252&amp;c=420489&amp;i=0f932b965ed1daf67b9ceac28a33e843&amp;u=5552"><img src="https://resize.yandex.net/mailservice?url=http%3A%2F%2Fdev.lk.dashamail.com%2Fop%2Fege%2Fbt.png&amp;proxy=yes&amp;key=bb38f8e9fe00f3e097c0cca07cf73f02" alt="" width="140" height="40" border="0"></a>';
//
//        preg_match_all($pattern, $text, $matches_src);
//        //$test_pattern = '/[-a-zA-Z0-9@:%_\+.~#?&\/=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~#?&\/=]*)?/imu';
//        //$test_pattern = '/([a-z0-9_\-]+\.)*[a-z0-9_\-]+@([a-z0-9][a-z0-9\-]*[a-z0-9]\.)+[a-z]{2,6}/imu';
//        $test_urls = $matches_src[1];
//
////        foreach ($test_urls as $test_url) {
////            //print_r($test_url . '<br>');
////            if(!preg_match($test_pattern, $test_url)) {
////                print_r($test_url . '<br>');
////            }
////        }
//
//        $test_urls = array_filter($test_urls, function($url_search_mail) {
//            $mail_pattern = '/([a-z0-9_\-]+\.)*[a-z0-9_\-]+@([a-z0-9][a-z0-9\-]*[a-z0-9]\.)+[a-z]{2,6}/imu';
//            if(!preg_match($mail_pattern, $url_search_mail)) return $url_search_mail;
//        });
//
//        $test_urls = array_filter($test_urls, function($url_search_ignor) {
//            $url_s_parts = wp_parse_url($url_search_ignor);
//            if($url_s_parts['host'] !== 'resize.yandex.net') return $url_search_ignor;
//        });
//
//        print_r($test_urls);
//
//
////        foreach ($test_urls as $match) {
////            print_r($match);
////        }




//        /**
//         * Проверка адреса на русские символы
//         */
//        function checkToRuSymbols($url)
//        {
//            if (preg_match( '/[а-яА-ЯЁё]/imu', $url )) {
//                return true;
//            }
//            return false;
//        }
//
//        /**
//         * Кодирование в правильный формат url адреса, если есть РУ символы
//         */
//        function urlEncodeRuSymbols($url)
//        {
//            $url_symbols = preg_split('//u', $url, -1, PREG_SPLIT_NO_EMPTY);
//            $new_url_symbols = [];
//            foreach ($url_symbols as $symbol){
//                if(checkToRuSymbols($symbol)) {
//                    $symbol = urlencode($symbol);
//                }
//                $new_url_symbols[] = $symbol;
//
//            }
//            return implode($new_url_symbols);
//        }
//
//
////        $check_link = 'http://stat.ege-study.ru/clicks.php?m=53252&amp;c=468880&amp;i=6f4dddb2b004d77ab6bdb51b8195f95c&amp;u=5552';
//        $good_url = '/wp-content/uploads/2014/05/Видеокурс-Премиум-для-подготовки-к-ЕГЭ-по-математике.png';
//        $check_link = urlEncodeRuSymbols($good_url);
//
//        $check_link = absolute_link($check_link);
//        //$check_link = mb_convert_encoding($check_link, 'UTF-8', mb_detect_encoding($check_link));
//        //$check_link = urldecode($check_link);
//
//        var_dump($check_link);
//        $head = @get_headers($check_link, 1);
////        $head = wp_remote_get($check_link);
//        var_dump( $head );
////        var_dump(wp_remote_retrieve_response_code( $head ));
//
//        //$head = array_change_key_case($head); // Приводим все ключи к CASE_LOWER
//
//
//        preg_match('/([0-9]{3})/', $head[0], $matches);
//
//        /** Проверяем заголовок на наличе ошибок */
////        $result['verified'] = $check_link;
////        $result['response'] = (string)$matches[0];
//
//        var_dump((string)$matches[0]);






        foreach ($posts_all_types as $post_type) {
            $posts = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE post_type = '{$post_type}'");

            ?>
            <div>
                <label id="cr-label-checkbox-post-<?php echo $post_type; ?>">
                    <input type="checkbox" class="cr-checkbox-post" data-post-type="<?php echo $post_type; ?>" value="<?php echo count($posts); ?>" />
                    <?php echo $post_type . ' (всего: ' . count($posts) . ')'; ?><span></span>
                </label>


            </div>
            <?php
        }
        ?>
        </div>

        <div class="mt-1 mb-1">
            <input type="submit" name="submit" id="cr-submit-analysis" class="button button-primary" disabled value="Провести анализ ссылок в постах">
        </div>

        <div id="cr-progress-bar-block" class="mt-2">
            <div id="title-progress-bar"></div>
            <progress id="cr-progress-bar" value="" max="" ></progress>
            <div id="title-progress-bar"></div>
        </div>

        <div id="change-links">
            <h3>Выполнение замены</h3>
            <p>Выберите необходимые замены:</p>
            <div id='cr-flex-container-change'>
                <?php
                $checkboxs_change = ['rep-abs-rel' => ['replace abs/rel', 'Заменить абсолютные ссылки на относительные'],
                    'check-media' => ['check 404 media', 'Проверить медиа ссылки основного домена на ошибки'],
                    'check-redir' => ['check redirect', 'Проверить ссылки основного домена на редирект'],
                    'check-ext' => ['check 404 ext', 'Проверить ссылки внешних доменов на ошибки']];

                foreach ($checkboxs_change as $key => $value){
                    ?>
                    <div class="checkbox_change">
                        <label id="cr-label-checkbox-change-<?php echo $key; ?>" class="cr-label-checkbox-change">
                            <input type="checkbox" class="cr-checkbox-change" data-type-change="<?php echo $key; ?>" value="" disabled />
                            <span><?php echo $value[0]; ?><span><?php echo '<br>' . $value[1]; ?><p class="cr-checkbox-add-span"></p></span>
                        </label>
                    </div>

                    <?php
                }
                ?>
                <div class="mt-1 mb-1">
                    <input type="submit" name="submit" id="cr-submit-change" class="button button-primary" value="Выполнить выбранные исправления">
                </div>
            </div>
        </div>
    </div>
    <?php

}

/**
 * Регистрируем настройки.
 * Настройки будут храниться в массиве, а не одна настройка = одна опция.
 */
add_action('admin_init', 'plugin_settings');
function plugin_settings(){
    // параметры: $option_group, $option_name, $sanitize_callback
    register_setting( 'option_group', 'option_name', 'sanitize_callback' );

    // параметры: $id, $title, $callback, $page
    add_settings_section( 'section_id', 'Основные настройки', '', 'check-redirect' );

    // параметры: $id, $title, $callback, $page, $section, $args
    add_settings_field('primer_field1', 'Название опции', 'fill_primer_field1', 'check-redirect', 'section_id' );
    add_settings_field('primer_field2', 'Другая опция', 'fill_primer_field2', 'check-redirect', 'section_id' );
    //add_settings_field('primer_field3', '', 'fill_primer_field3', 'check-redirect', 'section_id' );
}

// Заполняем опцию 1
function fill_primer_field1(){
    $val = get_option('option_name');
    $val = $val ? $val['input'] : null;
    ?>
    <input type="text" name="option_name[input]" value="<?php echo esc_attr( $val ) ?>" />
    <?php
}

// Заполняем опцию 2
function fill_primer_field2(){
    $val = get_option('option_name');
    $val = $val ? $val['checkbox'] : null;
    ?>
    <label><input type="checkbox" name="option_name[checkbox]" value="1" <?php checked( 1, $val ) ?> /> отметить</label>
    <?php
}

// Заполняем опцию 3
function fill_primer_field3(){
//    $val = get_option('option_name');
//    $val = $val ? $val['checkbox'] : null;
    ?>
    <progress value="15" max="150">
        Текст
    </progress>

    <?php
}

## Очистка данных
function sanitize_callback( $options ) {
    // очищаем
    foreach( $options as $name => & $val ){
        if( $name == 'input' )
            $val = strip_tags( $val );

        if( $name == 'checkbox' )
            $val = intval( $val );
    }

    return $options;
}





/** Выдача сообщений информации в админке */
//add_action('admin_notices', 'downloads_error_notice');
//function downloads_error_notice()
//{
/** Злесь создаем сообщения в админке */
//}




/** Функция запроса к базе данных на считывание контента поста, распознавание ссылок, классификация и подсчет их */
add_action( 'wp_ajax_analysis_link_type_posts', 'analysis_link_type_posts' );
function analysis_link_type_posts() {

    $response = [];

    if (!empty($_POST)) {

        /** Запрос выключает только один post_type */
        $post_type = $_POST['post_type'];

        /** Делаем запрос к БД с выборкой публикаций определенного post_type*/
        global $wpdb;
        $posts = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_type = '{$post_type}'");

        $cal[] = 0;
        $analysis_links['name'] = $post_type;

        foreach ($posts as $post){
            $content = $post->post_content;
            $id_post = $post->ID;



            /** $pattern - шаблон поиска url в контенте между <а href="">
             * и ищем все вхождения $pattern в контенте */
//            $pattern = '/<[Aa][\s]{1}[^>]*[Hh][Rr][Ee][Ff][^=]*=[ "\'\s]*([^ \'">\s#]+)[^>]*>/iu';
            $pattern = '/<[a]{1}[^>]*[h][r][e][f][^=]*=\s*["\'](?!mailto|tel|#.*)([^\'">]+)[^>]*>/imu';
            /*    $pattern = '/[-a-zA-Z0-9@:%_\+.~#?&\/=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~#?&\/=]*)?/gi';*/

            preg_match_all($pattern, $content, $matches);
            $urls_search_href = $matches[1];

            /** Исключаем из url почту */
            $urls_search_href = array_filter($urls_search_href, function($url_search_mail) {
                $mail_pattern = '/([a-z0-9_\-]+\.)*[a-z0-9_\-]+@([a-z0-9][a-z0-9\-]*[a-z0-9]\.)+[a-z]{2,6}/imu';
                if(!preg_match($mail_pattern, $url_search_mail)) return $url_search_mail;
            });


            /** $pattern_src - шаблон поиска url в контенте между <img src=""> и ищем все вхождения $pattern_src в контенте */
            //$pattern_src = '/\< *[img][^\>]*[src] *= *[\"\']{0,1}([^\"\'\ >]*)/';
            //$pattern_src = '/<[img][^>]*[src] *= *["\']{0,1}([^\'">]*)/';
            $pattern_src = '/<[img|iframe][\s\S]+?[s][r][c]\s*=\s*["\'](.*?)["\'].*>/imu';
            preg_match_all($pattern_src, $content, $matches_src);
            $urls_search_src = $matches_src[1];

            /** Сливаем два массива */
            $urls_search = array_merge($urls_search_href, $urls_search_src);

            /** Удаляем игнорируемые домены */
            $urls_search = array_filter($urls_search, function($url_search_ignor) {
                $url_s_parts = wp_parse_url($url_search_ignor);
                $array_ignor_hosts = [
                    'resize.yandex.net',
                    'www.resize.yandex.net',
                    'www.instagram.com',
                    'instagram.com'];

                if(!in_array ($url_s_parts['host'], $array_ignor_hosts)) return $url_search_ignor;
            });


            /** Убираем повторяюзиеся значения в массиве */
            $urls_search = array_unique($urls_search);


            /** Проверяем массив сслок */
            if (empty($urls_search)) continue;

            /** Переберем все ссылки */
            $arr = [];
            foreach ($urls_search as $url_s) {

                $url_s = rtrim($url_s);
                $url_s_parts = wp_parse_url($url_s);

                /** Проверяем ссылки и фиксируем их в файле:
                 * -абсолютные, принадлежащие домену (ссылки на записи и отдельно ссылки на медио файлы)
                 * -относительные, принадлежащие домену (ссылки на записи и отдельно ссылки на медио файлы)
                 * -ссылки ведущие вне основного домена
                 */

                /** проверяем ссылку на принадлежность к медиаконтенту и ставим маркер*/
                $pattern = '/(.+?)\.(jpg|png|jpeg|gif|mp4|webp)/su';
                if(preg_match($pattern, $url_s_parts['path'], $matches)) $media_mark = true;
                else $media_mark = false;

                if(empty($url_s_parts['host'])) {
                    /** Относительная ссылка, принадлежащая домену - сохраняем ее */

                    if(!empty($url_s_parts['path']) && ($url_s_parts['path'] !== '/')) {

                        /** проверяем маркер на принадлежность к медиаконтенту */
                        if($media_mark) { /** Относительная медиа ссылка */
                            $string_type_link = 'relative_media';
                        }
                        else { /** Относительная ссылка */
                            $string_type_link = 'relative';
                        }
                    }else{ /** Относительная корневая ссылка */
                        $string_type_link = 'relative_root';
                    }

                } elseif((!empty($url_s_parts['host']) && $url_s_parts['host'] === CR_HOST)) {

                    /** Абсолютная ссылка, принадлежащая домену - сохраним ее */
                    //if(!empty($url_s_parts['path']) && ($url_s_parts['path'] !== '/')) {
                    if(!empty($url_s_parts['path'])) {

                        /** проверяем маркер на принадлежность к медиаконтенту */
                        if($media_mark) { /** Абсолютная медиа ссылка */
                            $string_type_link = 'absolute_media';
                        }
                        else { /** Абсолютная ссылка */
                            $string_type_link = 'absolute';
                        }
                    }else{ /** Абсолютная корневая ссылка */
                        $string_type_link = 'absolute_root';
                    }

                } else { /** Абсолютная ссылка, не принадлежащая домену - сохраним ее */
                    $string_type_link = 'z_other_domain';
                    if($url_s_parts['host'] === 'shop.' . CR_HOST || $url_s_parts['host'] === 'stat.' . CR_HOST) {
                        $string_type_link = 'z_sub_domain';
                    }
                }

                $arr[$string_type_link][] = $url_s;

                if(empty($col[$string_type_link])) $col[$string_type_link] = 0;
                $col[$string_type_link]++; /** Счетчик ссылок */

            }
            $analysis_links[$id_post] = $arr;
        }

        $analysis_links['col'] = $col;

        /** Сохраняем в массиве данные $_SESSION['cr_check_redirections'][$post_type] */
        $_SESSION['cr_check_redirections'][$post_type] = $analysis_links;

        $response['session'] = $_SESSION['cr_check_redirections'][$post_type];

        $response['col'] = $_SESSION['cr_check_redirections'][$post_type]['col'];
        $response['name'] = $_SESSION['cr_check_redirections'][$post_type]['name'];
        $response['error'] = false;


    }else{
        $response['error'] = true;
    }

    /** Сортировка массива для выстраивания ключей по алфавиту (английскому)
     * Это лучше делать на фронте, но пока сделал здесь - быстрее и проще. */
    ksort($response['col']);


    echo json_encode($response);
    wp_die();

}


/** Функция запроса к БД и считывание контента поста, проверку ссылок в нем на редиректы и сохранение изменений в БД */
add_action( 'wp_ajax_check_redirection_type_posts', 'check_redirection_type_posts' );
function check_redirection_type_posts() {

    /** Считываем имена файлов логов */
    $file_log_change = $_SESSION['cr_check_redirections']['file_log_change'];
    $file_log_error = $_SESSION['cr_check_redirections']['file_log_error'];

    /** Маркер изменения контента */
    $marker_change_content = false;

    /** Время начала исполнения скрипта (при достижении времени max_execution_time - 1 сек, завершаем выполнение скрипта) */
    $time_start = hrtime();
    /** $max_time указывем половину от возможного времени выполнения скрипта, на случай успеть сохранить данные ????
     * Странно но ini_get('max_execution_time') показывает 0 (играет роль где сделать запрос)*/
    //$max_time = ini_get('max_execution_time');
    $max_time = 15;

    $response['col'] = 0;

    if (!empty($_POST)) {


        /** массив необходимых действий */
        $check_type = explode(",", $_POST['type-change']);

        /** определяем необходимые действия */

        $check_redir = in_array('check-redir', $check_type, true);
        $check_media = in_array('check-media', $check_type, true);
        $check_ext = in_array('check-ext', $check_type, true);
        $rep_abs_rel = in_array('rep-abs-rel', $check_type, true);

        $post_type = $_POST['post_type'];
        $count_posts = $_POST['count_posts_types'];

        /** Здесь начинаем проверку */

        /** Правильно сделать следующее: проверенный ID убирать из массива - списка проверяемых - $_SESSION['cr_check_redirections'][$post_type];
          * Тогда на следующей итерации небудет производится проверка данного ID, что ускорит процесс и даст точные данные по количесвту проверенных ID
          * (конечно при условии последовательсности выполенения этих итераций) */

        $post_links_type = $_SESSION['cr_check_redirections'][$post_type];

//        var_dump($post_links_type);

        /** Если пустое количество ссылок в записи возвращаемся и уходим.
         * Теоретически сюда не может прийти запрост на проверку ссылок определенного типа записи,
         * если их - этих ссылок - нет. Но проверку оставил для безопасности. По сути это дублирование. */
        if(empty($post_links_type['col']) || $post_links_type['name'] != $post_type){
            $response['error_file'] = false;
            $response['item'] = 0;
            echo json_encode($response);
            wp_die();
        }


        foreach ($post_links_type as $name_key => $id_links){

            /** Исключаем из рассмотрения поля без ID  */
            if(($name_key === 'name') || ($name_key === 'col')) continue;
            $post_id = $name_key;

            $check_links = [];

            foreach ($id_links as $name_link => $links) {

                foreach ($links as $link) {

                    /** Проверяем на налчие метки 'проверка на редиректы' - check redirect */
                    if($check_redir) {
                        /** Если выбранно проерить на редиректы ссылки домена, тогда ищем такие ссылки,
                          * и вносим в массив для проверки, производим проверку и записываем в log файлы */

                        /** Проверяем ссылки домена  */
                        if (($name_link === 'absolute') || ($name_link === 'relative')) {

                            /** Сформируем массив ссылок подлежащие проверке для данного ID */
                            $check_links[] = check_redirection_all($link, 'check-redir', $name_link, $rep_abs_rel);
                        }

                        /** Проверяем ссылки корня домена  */
                        if (($name_link === 'absolute_root') || ($name_link === 'relative_root')) {

                            /** Проверим маркер относительные/абсолютные ссылки
                             *   и сформируем массив ссылок подлежащие проверке для данного ID */
                            $change_url = '000200';
                            $verified_url = $link;

                            if($rep_abs_rel && ($name_link !== 'relative_root')) {
                                $change_url = '100200';
                                $verified_url = '/';
                            }

                            $check_links[] = ['response' => '200', 'pattern' => $link, 'verified' => $verified_url, 'check_type' => 'check-redir', 'name_link' => $name_link, 'change_url' => $change_url];
                        }
                    }

                    /** Проверяем на налчие метки 'проверка на ошибки медиафайлов' - check 404 media */
                    if($check_media) {

                        /** Если выбранно проерить на редиректы ссылки домена, тогда ищем такие ссылки,
                        * и вносим в массив для проверки, производим проверку и записываем в log файлы */

                        /** Проверяем медиа ссылки домена  */

                        if (($name_link === 'absolute_media') || ($name_link === 'relative_media')) {

                            /** Сформируем массив ссылок подлежащие проверке для данного ID */
                            $check_links[] = check_redirection_all($link, 'check-media', $name_link, $rep_abs_rel);
                        }
                    }

                    /** Проверяем на налчие метки 'проверка на ошибки внешние ссылки' - check 404 ext */
                    if($check_ext) {

                        /** Если выбранно проерить на редиректы ссылки домена, тогда ищем такие ссылки,
                         * и вносим в массив для проверки, производим проверку и записываем в log файлы */

                        /** Проверяем ссылки с внешних доменов */
                        if ($name_link === 'z_other_domain') {

                            /** Сформируем массив ссылок подлежащие проверке для данного ID */
                            $check_links[] = check_redirection_all($link, 'check-ext', $name_link, $rep_abs_rel);
                        }
                    }

                }

            }

            /** Делаем запрос к БД с выборкой публикаций определенного post_type*/
            global $wpdb;

            /** получили контент из БД по ID */
//            $post_content = $wpdb->get_results("SELECT post_content FROM {$wpdb->posts} WHERE ID = '{$post_id}'");
            $post_content = $wpdb->get_var("SELECT post_content FROM {$wpdb->posts} WHERE ID = '{$post_id}'");

            if(!empty($check_links)){
                foreach ($check_links as $check_link){

                    $date_record = date("Y:m:d H:i:s");

                    /** Формируем поле произведенные замены с указанием кода ошибки для записи в файл */
                    $text_log = '[' . $date_record . ']; ID: ' . $post_id . '; HTTP: ' . $check_link['response'] . '; ';
                    $text_log .= $check_link['check_type'] . '; Doing: ' . $check_link['change_url'] . '; url_base: ' . $check_link['pattern'] . ';';

                    /** проверяем маркер подмены url на проверенные */
                    if( $check_link['change_url'] === '100200' || $check_link['change_url'] === '100300' ) {

                        /** Производим замену в БД */


                        /** Ставим маркер записи в БД */
                        $marker_change_content = true;


                        /** Проверить почему $post_content массив и как преобразовать его в строку!!!! */
//                        $post_content = mb_str_replace ($check_link['pattern'], $check_link['verified'], $post_content);
                        $post_content = str_replace ('"' . $check_link['pattern'] . '"', '"' . $check_link['verified'] . '"', $post_content);


                        /** Записываем в файл произведенные замены с указанием кода ошибки */
                        $text_log .= ' url_new: ' . $check_link['verified'] . ';' . PHP_EOL;

                        file_put_contents($file_log_change, $text_log, FILE_APPEND | LOCK_EX);


                    }elseif ( $check_link['change_url'] === '000400' ) {

                        /** Записываем в файл ошибки с указанием кода ошибки */
                        $text_log .= PHP_EOL;
                        file_put_contents($file_log_error, $text_log, FILE_APPEND | LOCK_EX);


                    }else{

                        /** Теоретически здесь только 200 код и отсутствие необходимости перезаписи */
                    }

                }

                /** Именно тут и сохраняем в БД
                  * Если не было изменений - не делаем update */
                if ($marker_change_content) {

                    $return_bd_update = $wpdb->update( $wpdb->posts, [ 'post_content' => $post_content], [ 'ID' => $post_id ]);

//                    $return_bd_update = 1;

                    $response['$return_bd_update'][] = $return_bd_update;

//                    $response['post_id'][] = $post_id;
//                    $response['content'][] = $post_content;

                    $marker_change_content = false;
                }

            }

            /** Здесь удаляем из $_SESSION['cr_check_redirections'][$post_type] проверенный ID
              * и схраняем в $response['post_id'][]  список ID которые проверили.
              * $response['col'] - передаем на фронт и там используем их для progressbar
              * Выше надо произвести замену в БД по соттветствующему ID и зафиксировать в log файле произведенное событие */

            unset($_SESSION['cr_check_redirections'][$post_type][$post_id]);


            $time_end = hrtime();
            $period = $time_end[0] - $time_start[0];
            //var_dump($period);
            $response['count_posts'] = $count_posts;

            $response['col'] += count($check_links);

            if( $period > $max_time ) {
                /** Метка продолжения цикла */
                //var_dump($max_time);
                $response['response'] = true;
                break;
            }
            else {
                $response['response'] = false;

            }

        }


        $response['error_file'] = false;

        /** Когда все выполнится после результатов работы надо стереть массивы в $_SESSION['cr_redirection_links'] $_SESSION['cr_check_redirections'] */
        echo json_encode($response);
        wp_die();


    }else{
        $response['error_file'] = true;
        $response['response'] = false;
    }

    echo json_encode($response);
    wp_die();

}
