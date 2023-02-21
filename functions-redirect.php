<?php


/** Функция единичной замены str_replace */
function s2s_str_replace_once($search, $replace, $subject)
{
    $posishion = strpos($subject, $search);
    return $posishion !== false ? substr_replace($subject, $replace, $posishion, strlen($search)) : $subject;
}

/** Функция записи в файл и сохранения ссыдок */
function cr_save_all_link($content) {

    file_put_contents($file, $person, FILE_APPEND | LOCK_EX);

}

/** Функция проверки редиректов */
function check_redirection_all($link, $check_type, $name_link, $rep_abs_rel) {

    $result['check_type'] = $check_type;
    $result['name_link'] = $name_link;
    $result['pattern'] = $link;
    $result['verified'] = '';
    $result['change_url'] = '';


    if(empty($result['pattern'])) {
        $result['response'] = '000';
        return $result;
    }

    /** Преобразуем ссылку до абсолютной */
    $check_link = absolute_link($result['pattern']);

    /** Здесь проверяем наличие в $_SESSION ранее проверенные ссылки если они есть - считываем */
    /** Отключаем временно для тестирования, т.к. работает как кеш */
    if(isset($_SESSION['cr_redirection_links'][$check_link])){
        //list($result['response'], $result['verified'], $result['check_type']) = $_SESSION['cr_redirection_links'][$check_link];
        $result = $_SESSION['cr_redirection_links'][$check_link];
        return $result;
    }


    /** Проверяем заголовок на предмет редиректа и ошибок */
    $head = @get_headers(urlEncodeRuSymbols($check_link), 1);

    $head = array_change_key_case($head); // Приводим все ключи к CASE_LOWER

//    if($result['pattern'] === '/wp-content/uploads/2014/05/Видеокурс-Премиум-для-подготовки-к-ЕГЭ-по-математике.png') {
//        $head = @get_headers($check_link, 1);
//        var_dump($head);
//    }

    /** Если пустой заголовок генерируем свою ошибку */
    if(empty($head) || !$head) {
        $result['verified'] = '';
        $result['response'] = '001';
        return $result;
    }

    preg_match('/([0-9]{3})/', $head[0], $matches);

    /** Проверяем заголовок на наличе ошибок */
    $result['verified'] = $check_link;
    $result['response'] = (string)$matches[0];

    if ( !($result['response'] === '200' || $result['response'] === '304') ) {

        if ( $result['response'] === '301' || $result['response'] === '302' || $result['response'] === '303' || $result['response'] === '307' || $result['response'] === '308' ) {

            /** Если есть редирект проверяем куда он ведет */
            $result['verified'] = is_array($head['location']) ? $head['location'][count($head['location'])-1] : $head['location'];

            if($rep_abs_rel) {
                $result['verified'] =  relative_link($result['verified']);
            }

            /** код сообщающий о необходимости произвести замену старых url на проверенные */
            $result['change_url'] = '100300';

            //if ($result['verified'] === '') var_dump($head['location']);

        }else {
            /** Если любая ошибка необходимо зафиксировать. Пока не понятно зачем, как это использовать
             *  Присвоим код что подмена адреса не производится, а только происходит сохранение в файл ошибок */
            $result['change_url'] = '000400';
            $result['verified'] = '';

        }

    }else {

        /** Если ответ 200 проверим требование заменить на относительную ссылку и на несовпадение url */
        if($rep_abs_rel) {
            /** Приводим к относительной ссылке */
            $result['verified'] =  relative_link($result['verified']);

            /** Если не равны 'pattern' и 'verified' укажем код замены старых url на скорректированные, иначе код отсутствия такого действия */
            $result['change_url'] = ($result['pattern'] !== $result['verified']) ? '100200' : '000200';
        }else{
            /** код сообщающий об отсутствии необходимости замены */
            $result['change_url'] = '000200';
        }

    }

    /** Сохраняем в $_SESSION массив ссылок, который используем для уменьшения количествао проверок */
    $_SESSION['cr_redirection_links'][$check_link] = $result;

    return $result;
}


/** Функция приведения относительной ссылки к абсолютной */
function absolute_link($link) {
    $link = rtrim($link);
    $link_parse = wp_parse_url($link);

    if(empty($link_parse['host']) || $link_parse['host'] === CR_HOST ) {
        $link = CR_SITEURL . $link_parse['path'] . CR_SLASHIT . $link_parse['query'] . $link_parse['fragment'];
    }elseif(!empty($link_parse['host']) && $link_parse['host'] !== CR_HOST) {
        /** Если пустая схема не подставляем */
        $link = !empty($link_parse['scheme']) ? $link_parse['scheme'] . '://' : '';
        $link .= $link_parse['host'] . $link_parse['path'] . CR_SLASHIT . $link_parse['query'] . $link_parse['fragment'];
    }

    return $link;
}


/** Функция приведения абсолютной ссылки к относительной */
function relative_link($link) {
    $link = rtrim($link);
    $link_parse = wp_parse_url($link);

    if(empty($link_parse['host']) || $link_parse['host'] === CR_HOST ) {
        $link = $link_parse['path'] . CR_SLASHIT . $link_parse['query'] . $link_parse['fragment'];
    }

    return $link;
}


/** Функция замены кирилических подстрок в тексте */
function mb_str_replace($search, $replace, $string)
{
    $charset = mb_detect_encoding($string);
    $unicodeString = iconv($charset, "UTF-8", $string);

    return str_replace($search, $replace, $unicodeString);
}

/** Проверка адреса на русские символы */
function checkToRuSymbols($url)
{
    if (preg_match( '/[а-яА-ЯЁё]/imu', $url )) {
        return true;
    }
    return false;
}

/** Кодирование в правильный формат url адреса, если есть РУ символы */
function urlEncodeRuSymbols($url)
{
    $url_symbols = preg_split('//u', $url, -1, PREG_SPLIT_NO_EMPTY);
    $new_url_symbols = [];
    foreach ($url_symbols as $symbol){
        if(checkToRuSymbols($symbol)) {
            $symbol = urlencode($symbol);
        }
        $new_url_symbols[] = $symbol;

    }
    return implode($new_url_symbols);
}