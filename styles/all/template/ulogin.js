if ( (typeof jQuery === 'undefined') && !window.jQuery ) {
    document.write(unescape("%3Cscript type='text/javascript' src='//ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js'%3E%3C/script%3E%3Cscript type='text/javascript'%3EjQuery.noConflict();%3C/script%3E"));
} else {
    if((typeof jQuery === 'undefined') && window.jQuery) {
        jQuery = window.jQuery;
    } else if((typeof jQuery !== 'undefined') && !window.jQuery) {
        window.jQuery = jQuery;
    }
}

function uloginCallback(token){
    jQuery.ajax({
        url: '/ulogin/login',
        type: 'POST',
        dataType: 'json',
        cache: false,
        data: {token: token},
        success: function (data) {
            switch (data.type) {
                case 'error':
                    uloginMessage(data.title, data.msg, data.type);
                    break;
                case 'success':
                    if (jQuery('.ulogin_accounts').length > 0){
                        adduLoginNetworkBlock(data.networks, data.title, data.msg);
                    } else {
                        location.reload();
                    }
                    break;
            }

            if (data.script) {
                var token = data.script['token'],
                    identity = data.script['identity'];
                if  (token && identity) {
                    uLogin.mergeAccounts(token, identity);
                } else if (token) {
                    uLogin.mergeAccounts(token);
                }
            }
        }
    });
}


function uloginMessage(title, msg, type) {
    if (title == '' && msg == '') { return; }
    var ulogin_messages_box = jQuery('#ulogin-message-box');
    if (ulogin_messages_box.length == 0) { return; }

    var mess = (title != '') ? '<strong>' + title + '</strong><br>' : '';
    mess += (msg != '') ? msg : '';

    var class_msg = 'message_';
    if (jQuery.inArray(type, ['error','success']) >= 0) {
        class_msg += type;
    } else {
        class_msg += 'info';
    }

    ulogin_messages_box.removeClass('message_error message_success message_info').addClass(class_msg).html(mess).show();

    setTimeout(function () {
        ulogin_messages_box.hide();
    }, 5000);
}

function uloginDeleteAccount(network){
    jQuery.ajax({
        url: '/ulogin/delete_account',
        type: 'POST',
        dataType: 'json',
        cache: false,
        data: {network: network},
        error: function (data, textStatus, errorThrown) {
            alert('Не удалось выполнить запрос');
        },
        success: function (data) {
            switch (data.type) {
                case 'error':
                    uloginMessage(data.title, data.msg, 'error');
                    break;
                case 'success':
                    console.log(data);
                    var accounts = jQuery('.ulogin_accounts'),
                        nw = accounts.find('[data-ulogin-network='+network+']');
                    if (nw.length > 0) nw.hide();

                    uloginMessage(data.title, data.msg, 'success');
                    break;
            }
        }
    });
}


function adduLoginNetworkBlock(networks, title, msg) {
    var uAccounts = jQuery('.ulogin_accounts');

    console.log(networks);

    uAccounts.each(function(){
        for (var uid in networks) {
            var network = networks[uid],
                uNetwork = jQuery(this).find('[data-ulogin-network='+network+']');

            if (uNetwork.length == 0) {
                var onclick = '';
                if (jQuery(this).hasClass('can_delete')) {
                    onclick = ' onclick="uloginDeleteAccount(\'' + network + '\')"';
                }
                jQuery(this).append(
                    '<div data-ulogin-network="' + network + '" class="ulogin_provider big_provider ' + network + '_big"' + onclick + '></div>'
                );
                uloginMessage(title, msg, 'success');
            } else {
                if (uNetwork.is(':hidden')) {
                    uloginMessage(title, msg, 'success');
                }
                uNetwork.show();
            }
        }

    });
}