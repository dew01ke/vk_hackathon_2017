var BASE_AUTH_URL = 'https://oauth.vk.com/authorize?client_id=6227703&display=page&redirect_uri=https://oauth.vk.com/blank.html&scope=&response_type=token&v=5.52';
var USER_PROFILE = null;

function getUrlParams(url) {
    var urlParams = {};

    if (url.indexOf("#") >= 0) {
        var urlTail = url.substr(url.indexOf("#") + 1);
        urlTail.replace(
            new RegExp(
                "([^?=&]+)(=([^&#]*))?", "g"),
            function($0, $1, $2, $3) { urlParams[$1] = $3; }
        );
    }

    return urlParams;
}

function authUser(callback) {
    chrome.tabs.create({url: BASE_AUTH_URL, selected: true}, function(tab) {
        chrome.tabs.onUpdated.addListener(tabCreateHandler(tab.id, callback));
    });
}

function tabCreateHandler(authTabId, callback) {
    return function authTabCallback(tabId, changeInfo) {
        if (tabId === authTabId && /oauth.vk.com\/blank.html/.test(changeInfo.url) && changeInfo.status === "loading") {
            chrome.tabs.onUpdated.removeListener(authTabCallback);

            var urlParams = getUrlParams(changeInfo.url);
            if (urlParams.access_token && urlParams.user_id) {
                chrome.tabs.remove(tabId);

                if (callback) {
                    callback(urlParams);
                }

                USER_PROFILE = {
                    user_id: urlParams.user_id
                };

                chrome.tabs.create({
                    url: chrome.extension.getURL('main.html')
                });

                console.log(urlParams.access_token, urlParams.user_id);
            }
        }
    }
}

chrome.extension.onMessage.addListener(function(request, sender, sendResponse) {
    if (request && request.method) {
        switch(request.method) {
            case 'auth_user':
                authUser(function(data) {
                    sendResponse(data);
                });
                break;
            case 'get_auth_state':
                sendResponse(USER_PROFILE);
                break;
            default:
                console.log('onMessage', request);
        }
    }
});