<?php
/** Основной скрипт считывния контента в БД и проверки редиректов
 * Скрипт вызывается отдельно для каждой записи в БД
 */


/** Время начала исполнения скрипта (при достижении времени max_execution_time - 1 сек, завершаем выполнение скрипта) */
$time_start = hrtime();
//$max_time = ini_get("max_execution_time") - 1;
$max_time = 10;


//require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-config.php');
//require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
require_once __DIR__ . '/functions-redirect.php';

/** Проверяем залогиненность в админке пользователя */
if( !current_user_can('administrator') ) die('Для вас недоступна работа скрипта');

var_dump($post_type);

die();


global $wpdb;
$page = $wpdb->get_results("SELECT *	FROM $wpdb->posts WHERE post_status = 'publish'	AND (post_type = 'page' OR post_type = 'post')");

$content = $page->post_content;

/** $pattern - шаблон поиска url в контенте между <а href="">
 * и ищем все вхождения $pattern в контенте */
$pattern = '/<[Aa][\s]{1}[^>]*[Hh][Rr][Ee][Ff][^=]*=[ "\'\s]*([^ \'">\s#]+)[^>]*>/';
/*    $pattern = '/<a.*?href=["\'](.*?)["\'].*?>/i';*/
preg_match_all($pattern, $content, $matches);
$urls_search = $matches[1];

/** Переберем все ссылки */
foreach ($urls_search as $url_s){

    $url_s_parts = wp_parse_url( $url_s );
    print_r($url_s . '<br>');

    /** Проверяем ссылку, если она абсолютная и принадлежит домену ее надо проверить и подменяем на коректную относительную */
    if((!empty($url_s_parts['host']) && $url_s_parts['host'] == $host) || empty($url_s_parts['host'])){
        $url_new_s = user_trailingslashit($url_s_parts['path']) . $url_s_parts['query'] . $url_s_parts['fragment'];

        $url_check = $protocol . '://' . $host . $url_new_s;

        /** Проверяем заголовок на предмет редиректа и ошибок */
        $head = @get_headers($url_check, 1);
        //var_dump($head);
        if(!empty($head) && $head !== false) {
            $head = array_change_key_case($head); // Приводим все ключи к CASE_LOWER

            preg_match('/([0-9]{3})/', $head[0], $matches);

            /** Проверяем на наличе ошибок */
            if ($matches[0] != '200') {
                if ($matches[0] == ('301' || '302' || '307' || '308')) {

                    /** Если есть редирект проверяем куда он ведет и подменяем в контенте */
                    if (!empty($head['location']) && $head['location'] !== $siteurl) {
                        $url_check = $head['location'];

                        /** Формируем относительную ссылку и добавляем/убираем финальный "/" в завтисимости от настроек permalink_structure */
                        $url_check_relative = user_trailingslashit(wp_parse_url( $url_check, PHP_URL_PATH ));
                        $content = s2s_str_replace_once($url_s, $url_check_relative, $content);
                        $col++;

//                        var_dump($content);
//                        var_dump('<br><br>');
                    }

                } else {
                    /** Если любая ошибка необходимо записаь всю информацию в лог файл */

                }

            }else{
                if($url_s_parts['host'] == $host){
                    $content = s2s_str_replace_once($url_s, $url_new_s, $content);
                    $col++;
                }

            }
        }
    }
}


//    var_dump($col);
//    var_dump('<br><br>');


/** Здесь производим сохранение в БД изменений в $content */
$result = $wpdb->update( $wpdb->posts, ['post_content' => $content], ['ID' => $page->ID]);
print_r('result: ' . $result . '<br>');
if($result){
    echo 'Зменили ' . $col . ' ссылок';
}

/** все работает, только проверки проходят очень медленно, надо продумать как и когда запускать
 * Надо продумать лог файл замен и фиксации ошибок.
 * Нужна фиксация отсутствия '/' в custom_permalink публикаций
 */



$col = 0;

$time_end = hrtime();
$period = $time_end[0] - $time_start[0];

print_r('period: ' . $period . '<br>');

$n++;

//if($period >= $max_time){
//    break;
//}
