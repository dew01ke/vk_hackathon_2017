(function() {
    console.log('[log]: popup script init');

    function sendBackgroundRequest(method, data, callback) {
        var request = {};
        if (method) request.method = method;
        if (data) request.data = data;

        chrome.extension.sendMessage(request,
            function(response) {
                if (callback) { callback(response); }
            }
        );
    }

    var cache = {
        button_auth_wrapper: $('.button-auth-wrapper'),
        button_auth: $('.button-auth'),
        button_control_wrapper: $('.button-control-wrapper'),
        button_control: $('.button-control')
    };

    cache.button_auth.click(function(e) {
        cache.button_auth_wrapper.hide();

        sendBackgroundRequest('auth_user', null, function(data) {
            console.log('auth_user', data);
        });
    });

    function onInit() {
        sendBackgroundRequest('get_auth_state', null, function(data) {
            if (data && data.user_id) {
                cache.button_auth_wrapper.hide();
                cache.button_control_wrapper.show();
            } else {
                cache.button_auth_wrapper.show();
                cache.button_control_wrapper.hide();
            }
        });
    }

    onInit();
})();