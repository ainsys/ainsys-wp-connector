// ////////////////////////////////////
jQuery(function($){

    let auto_update = false;

    $('.entities_field .properties_field').on('change', '.entiti_settings_value', function () {
        if (!auto_update) {
            let setting_id = '#' + $(this).parent().parent().attr('id');
            let new_value = $(this).val();

            if ( $(this).attr("type") == 'checkbox' ){
                new_value = $(this).val() == 1 ? 'On' : 'Off';
            }

            $( setting_id + ' i').addClass('active');
            $( setting_id ).attr('data-' + $(this).attr("id"), $(this).val()).data($(this).attr("id"), $(this).val());
            if ( $(this).attr("id") == 'api' ){
                $(this).parent().find('div').attr('class', '');
                $(this).parent().find('div').addClass('entiti_settings_value').addClass(new_value);
            } else {
                $(this).parent().find('div').html( new_value );
            }
            //$('#save_entiti_properties').attr('disable', false);
        }
    })

    setTimeout(() => {
        $('.ainsys-logo').css('opacity', 1)
    }, 500)


    $('.entities_field .properties_field input').on('click', function () {
        if ( $(this).attr("type") == 'checkbox' ){
            let val = $(this).val() == 1 ? 0 : 1;
            $(this).attr('value', val);
        }
    });

    //////// Ajax clear log ////////
    $('#setting_entities_section .entities_field').on('click', '.fa.active', function (e){

        let setting_id = '#' + $(this).parent().attr('id');
        $(setting_id).toggleClass('loading');
        let data = {
            action: "save_entiti_settings",
            nonce: ainsys_connector_params.nonce,
        };
        // let temp = $(setting_id).data();

        $.each($(setting_id).data(), function(key,value) {
            data[key] = value;
        })

        jQuery.post(ainsys_connector_params.ajax_url, data, function (value) {
            if(value){
                //console.log($(setting_id + ' .fa'));
                $(setting_id + ' .fa').removeClass('active');
                $(setting_id + ' #id').val(value);
                $(setting_id + ' #id').parent().find('div').html(value);
            }
            $(setting_id).toggleClass('loading');
        });
    });

    ////////  ////////
    $('#setting_entities_section').on('click', ' .entities_field', function (e){
        $('.entities_field.active').each(function(){
            $(this).removeClass('active');
        })

        let obj_id = $(this).attr("id");
        $('.properties_data #setting_name').html($(this).data('seting_name'));
        $('.properties_data #setting_name').attr('data-seting_name', $(this).data('seting_name')).attr('data-entiti', $(this).data('entiti'));

        auto_update = true;
        $.each($(this).data(), function(key,value) {
            let input_obj = $('.properties_data .properties_field #' + key );
            let input_type = $('.properties_data .properties_field #' + key ).attr("type");
            if (input_obj.is("select")) input_type = 'select';
            switch (input_type){
                case 'text':
                    $(input_obj).val(value);
                    break;
                case 'checkbox':
                    $(input_obj).attr('value', value);
                    $(input_obj).prop('checked', Boolean(value));
                    break;
                case 'select':
                    $(input_obj).val(value).change();
                default:
                    $(input_obj).val(value);
                    break;
            }
        });
        auto_update = false;
        $(this).addClass('active');
    });
    //////// expand entiti tab ////////
    $('#setting_entities_section').on('click', ' .expand_entiti_contaner', function (e){
        $(this).parent().parent().toggleClass('active');
        var text = $(this).text() == 'expand' ? 'collapse' : 'expand';
        $(this).text(text);
    });

    //////// Ajax clear log ////////
    $('#setting_section_log').on('click', '#clear_log', function (e){
        var data = {
            action: "clear_log",
            nonce: ainsys_connector_params.nonce
        };
        jQuery.post(ainsys_connector_params.ajax_url, data, function (value) {
            if(value){
                $('#connection_log').html(value);
            }
        });
    });

    //////// Ajax toggle loging controll, set log until time  ////////
    $('#setting_section_log').on('click', '.loging_controll', function (e){
        e.preventDefault();

        if ( $(this).hasClass("disabled") ){
            return;
        }
        var time = $( "#start_loging_timeinterval" ).val()
        var id = $(this).prop('id');
        $(this).addClass("disabled");
        var data = {
            action: "toggle_logging",
            command: id,
            time: time,
            nonce: ainsys_connector_params.nonce
        };
        jQuery.post(ainsys_connector_params.ajax_url, data, function (value) {
            $(value).removeClass("disabled");
            if (value == '#stop_loging') {
                $('#start_loging_timeinterval').addClass("disabled");
            } else {
                $('#start_loging_timeinterval').removeClass("disabled");
            }
        });
    });

    ////////  Ajax reload log HTML ////////
    $('#setting_section_log').on('click', '#reload_log', function (e){
        $.ajax({
            url: ainsys_connector_params.ajax_url,
            type: 'POST',
            data: {
                action: 'reload_log_html',
                // check
                nonce: ainsys_connector_params.nonce
            },
            success: function (msg) {
                if(msg){
                    $('#connection_log').html(msg);
                }
            }//,
            // error: function (jqXHR, exception) {
            //
            // }
        })
    });

    //////// Ajax remove ainsys integration ////////
    $('#setting_section_general').on('click', '#remove_ainsys_integration', function (e){
        var data = {
            action: "remove_ainsys_integration",
            nonce: ainsys_connector_params.nonce
        };
        jQuery.post(ainsys_connector_params.ajax_url, data, function (value) {
            location.reload();
        });
    });

    //////// Expand/collapse data contaner ////////
    $('#setting_section_log').on('click', '.expand_data_contaner', function (e){
        $(this).parent().toggleClass('expand_tab');

        var text = $(this).text() == 'less' ? 'more' : 'less';
        $(this).text(text);

    })

    /////////////////////////////////
    ////////////Settings tabs///////

    $('.ainsys_settings_wrap').on('click', '.nav-tab', function(event){

        event.preventDefault();

        var targ = $(this).data('target');

        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-target').removeClass('tab-target-active');
        $(this).addClass('nav-tab-active');
        $('#'+targ).addClass('tab-target-active');

        var ref = $('.ainsys_settings_wrap input[name="_wp_http_referer"]').val();
        var new_ref = ref;
        var query_string = {};
        var url_vars = ref.split("?");
        if (url_vars.length > 1){
            new_ref = url_vars[0] + '?';
            var url_pairs = url_vars[1].split("&");
            for (var i=0;i<url_pairs.length;i++){

                var pair = url_pairs[i].split("=");

                if (pair[0] != 'setting_tab'){
                    new_ref = new_ref + url_pairs[i] + '&';
                }
            }
        } else {
            new_ref = new_ref + '?';
        }
        new_ref = new_ref + 'setting_tab=' + targ;
        $('.ainsys_settings_wrap input[name="_wp_http_referer"]').val(new_ref);
    });
});
