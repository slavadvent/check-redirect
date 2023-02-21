jQuery(document).ready(function ($) {

    var value_progress_bar = 0;


    var value_analysis_posts = $('#cr-submit-analysis').val();
    $('#cr-submit-analysis').val(value_analysis_posts + '. Всего: 0 ' );


    /** Реагируем на изменение checkbox-post */
    $('.cr-checkbox-post').on('click', function(){

        let sum_posts = sum_all_posts_check ();

        $('#cr-submit-analysis').val(value_analysis_posts + '. Всего: ' + sum_posts );
        if(sum_posts > 0) $('#cr-submit-analysis').prop('disabled', false);
        else $('#cr-submit-analysis').prop('disabled', true);

    });


    /** Реагируем на нажатие кнопки анализа постов */
    $('#cr-submit-analysis').on('click', function () {
        let value_progress_bar = 0;
        let sum_check_posts = sum_all_posts_check();
        $('.cr-checkbox-post').prop('disabled', 'true');
        $('#cr-submit-analysis').prop('disabled', 'true')
        $('#cr-progress-bar-block').css({'visibility': 'visible'})
        $('#cr-progress-bar').attr({'value': value_progress_bar, 'max': sum_check_posts})

        /** Считываем все выбранные checkbox */
        let all_post_types = [];
        $('.cr-checkbox-post:checked').each(function(i, el) {
            all_post_types.push($(el).attr('data-post-type')) ;
        });

        let mark_change = false;
        let need_change_relative = need_change_ext_domain = need_change_media = need_change_absolute = 0;

        $.each(all_post_types, function(index, value){
            let data = new FormData();
            data.append('action', 'analysis_link_type_posts');
            data.append('post_type', value);
            //data.append('post_id', cr_config.ID_post);
            data.append('post_id', 123);
            url = cr_config.ajax_url;

            $.when(ajax_make_function(url, data)).then(function (response) {
                let col = response.col;
                console.log(response);

                need_change_absolute += col ? numder_ex(col.absolute) + numder_ex(col.absolute_media) + numder_ex(col.absolute_root) : 0;
                need_change_media += col ? numder_ex(col.absolute_media) + numder_ex(col.relative_media) : 0;
                need_change_ext_domain += col ? numder_ex(col.z_other_domain) : 0;
                need_change_relative += col ? numder_ex(col.relative) : 0;


                let text_label = $('#cr-label-checkbox-post-' + response.name);
                let text_label_span = text_label.find('span');
                let text_add = '';
                let checkbox_post = text_label.find('.cr-checkbox-post');
                let sum_link = 0;

                $.each(col, function(index, value){
                    name_response = name_response_links(index);
                    if(name_response !== '') {
                        text_add += '<br>' + name_response + ': ' + value;
                        sum_link += value;
                    }

                });

                /** Запишем количество ссылок необходимых для проверки */
                checkbox_post.attr('data-link-summ', sum_link);

                if(empty(text_add)) text_add += '<br>' + 'В данных публикациях нет ссылок';
                else mark_change = mark_change || true;

                text_label_span.css({'color':'blue'});
                text_label_span.html(text_add);

                value_progress_bar += Number(text_label.find('.cr-checkbox-post').val());
                $('#cr-progress-bar').attr({'value': value_progress_bar});

                if(value_progress_bar === sum_check_posts) setTimeout(function (){
                        $('#cr-progress-bar-block').css({'visibility': 'hidden'});
                        if(mark_change){
                            if(need_change_absolute > 0)  {
                                let change_rep_abs_rel = $('#cr-label-checkbox-change-rep-abs-rel').find('.cr-checkbox-change');
                                change_rep_abs_rel.val(need_change_absolute);
                                change_rep_abs_rel.removeAttr('disabled');
                                let change_check_redir = $('#cr-label-checkbox-change-check-redir').find('.cr-checkbox-change');
                                change_check_redir.val( need_change_absolute + need_change_relative);
                                change_check_redir.removeAttr('disabled');
                                    }
                            if(need_change_media > 0) {
                                let change_check_media = $('#cr-label-checkbox-change-check-media').find('.cr-checkbox-change');
                                change_check_media.val(need_change_media);
                                change_check_media.removeAttr('disabled');

                            }
                            if(need_change_ext_domain > 0)  {
                                let change_check_ext = $('#cr-label-checkbox-change-check-ext').find('.cr-checkbox-change');
                                change_check_ext.val(need_change_ext_domain);
                                change_check_ext.removeAttr('disabled');

                            }
                            // let button = '';
                            // $('#change-links').append(button);
                            $('#change-links').css({'visibility': 'visible'});
                            $('#cr-submit-change').css({'visibility': 'visible'});
                            $('#cr-submit-change').prop('disabled', true);
                        }

                    }, 1000);

                },

                function (error) {
                    console.log('Странная ошибка счетчика'); // текст ошибки
                }
            );

        })

    })


    /** При изменении checkbox сообщаем о количестве всего ссылок необходимых к проверке */
    $('.cr-checkbox-change').on('click', function(){
        let element = $(this);
        let label_span = element.next().find('.cr-checkbox-add-span');
        if ( element.is(':checked') ) {
            label_span.html('Проверить всего ссылок: ' + element.val());
        }else{
            label_span.html('');
        }
        if($('.cr-checkbox-change:checked').length > 0) $('#cr-submit-change').prop('disabled', false);
        else $('#cr-submit-change').prop('disabled', true);
    });


    /** Запускаем проверку и исправление */
    $('#cr-submit-change').on('click', function () {

        console.log('cr-submit-change');
        $('#cr-submit-change').prop('disabled', true);

        /** Считываем все выбранные checkbox post-type у котоорых ссылок для проверки больше 0 */
        let all_post_types = [];
        $('.cr-checkbox-post:checked').each(function(i, el) {
            if($(el).attr('data-link-summ') > 0) {
                all_post_types.push($(el).attr('data-post-type'));
            }
        });
        console.log(all_post_types);


        /** Считаем выбранные checkbox type-change */
        let all_type_change = [];
        $('.cr-checkbox-change:checked').each(function(i, el) {
            all_type_change.push($(el).attr('data-type-change')) ;
        });
        console.log(all_type_change);


        // var analises_link = function(){
        //
        //     analises_post_link_back( all_post_types.splice(0,1), function(){
        //         if (all_post_types.length > 0) {
        //             analises_link();
        //         }
        //     });
        //
        // }
        //
        // var analises_post_link_back = function(url, callback) {
        //     ajax_make_function(url, data).done(function(response){
        // typeof(callback) == 'function' ? callback(response) : null;
        // });
        //
        // }
        //
        // analises_link();



         $.each(all_post_types, function(index, value){
            let data = new FormData();
            data.append('action', 'check_redirection_type_posts');
            data.append('post_type', value);
            data.append('type-change', all_type_change);
            //data.append('post_id', cr_config.ID_post);
            data.append('post_id', 123);
            data.append('count_posts_types', all_post_types.length - index);
            url = cr_config.ajax_url;


            $.when(recurs_ajax_make_function(url, data)).then(function (response) {
                    // console.log('response');
                    // console.log(response);

                /** Не понятно пока как сюда вернутся. все данные возвращаются в рекурсивную функцию
                 * здесь реакция происходит асинхронно */


                },

                function (error) {
                    console.log('Странная ошибка счетчика'); // текст ошибки
                    // wait_check = false;
                })


            })




    })



    function name_response_links(name_response) {

        let result;
        switch (name_response) {
            case 'relative':
                result = '- Относительных ссылок домена: ';
                break;
            case 'relative_media':
                result = '- Относительных медиа ссылок домена: ';
                break;
            case 'relative_root':
                result = '- Относительных корневых ссылок домена: ';
                break;
            case 'absolute':
                result = '- Абсолютных ссылок домена: ';
                break;
            case 'absolute_media':
                result = '- Абсолюnных медиа ссылок домена: ';
                break;
            case 'absolute_root':
                result = '- Абсолюnных корневых ссылок домена: ';
                break;
            case 'z_other_domain':
                result = '- Ссылки на другие домены: ';
                break;
            case 'z_sub_domain':
                result = '- Ссылки на свои поддомены: ';
                break;
            case 'undefined':
                result = '';
                break;
            case 'error':
                result = '';
                break;
            case 'name':
                result = '';
                break;
            default:
                result = '- очень странная ошибка!';
                break;
        }
        return result;
    }


    function sum_all_posts_check () {
        let sum_posts = 0;
        $('.cr-checkbox-post:checked').each(function(i, el) {
            sum_posts += Number($(el).val());
        });
        return sum_posts;
    }


    /** общая ajax функция*/
    function ajax_make_function(url, data) {

        return jQuery.ajax({
            url: url,
            type: 'POST', // важно!
            data: data,
            cache: false,
            dataType: 'json',
            // отключаем обработку передаваемых данных, пусть передаются как есть
            processData: false,
            // отключаем установку заголовка типа запроса. Так jQuery скажет серверу что это строковой запрос
            contentType: false,
            // функция успешного ответа сервера
            success: function (response) {
                return response;

            },

            //функция ошибки ответа сервера
            error: function (jqXHR, status, errorThrown) {
                console.log('ОШИБКА AJAX запроса down: ' + status, jqXHR);
                //return false;
            }
        });

    }




    /** Рекурсивная ajax функция */

    function recurs_ajax_make_function(url, data){
        let sum_check_link = 0;
        $('.cr-checkbox-change:checked').each(function(){
            if( $(this).attr('data-type-change') === 'rep-abs-rel' ) return;
            sum_check_link += +$(this).val();
        });

        $('#cr-progress-bar-block').css({'visibility': 'visible'})
        $('#cr-progress-bar').attr({'value': value_progress_bar, 'max': sum_check_link})
        $.when(ajax_make_function(url, data)).then(function (response) {
                console.log(response);
                // console.log(response);
                // console.log('response: ' + response.col);

                value_progress_bar += response.col;
                // console.log('value_progress_bar: ' + value_progress_bar);

                $('#cr-progress-bar').attr({'value': value_progress_bar});

                if (response.response) {

                    recurs_ajax_make_function(url, data);
                }
                else {
                    if (value_progress_bar === sum_check_link){
                        setTimeout(function (){
                            $('#cr-progress-bar-block').css({'visibility': 'hidden'});
                        }, 1500);
                    }
                }




                //return response;

            },

            function (error) {
                console.log('Странная ошибка счетчика'); // текст ошибки
                // wait_check = false;
                //return error;
            })

        return value_progress_bar;
    }




})

/** Аналог функции empty */
function empty(mixed_var) {
    return (mixed_var === "" || mixed_var === 0 || mixed_var === "0" || mixed_var === null || mixed_var === false || typeof mixed_var === "undefined");
}

/** Аналог функции Numder - более расширенный */
function numder_ex(mixed_var) {
    mixed_var = mixed_var ? mixed_var : 0;
    return Number(mixed_var);
}

