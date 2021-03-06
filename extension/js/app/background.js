var BASE_AUTH_URL = 'https://oauth.vk.com/authorize?client_id=6227703&display=page&redirect_uri=https://oauth.vk.com/blank.html&scope=&response_type=token&v=5.52';
var USER_PROFILE = null;
var NOTIFICATION = null;

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
		window.tabCheckInterval = window.setInterval(function() {
			tabHandler(tab.id, callback);
		}, 200);
    });
}

function tabHandler(tabId, callback) {
	chrome.tabs.query({ active: true }, function(tabs) {
		for (var key in tabs) {
			var tab = tabs[key];
			if (tab.id === tabId && /oauth.vk.com\/blank.html/.test(tab.url) && tab.url.indexOf("access_token") > -1 && tab.status === "complete") {
				window.clearInterval(window.tabCheckInterval);
				var urlParams = getUrlParams(tab.url);
				if (urlParams.access_token && urlParams.user_id) {
					chrome.tabs.remove(tabId);

					if (callback) {
						callback(urlParams);
					}

					USER_PROFILE = {
						user_token: urlParams.access_token,
						user_id: urlParams.user_id
					};

					chrome.tabs.create({
						url: chrome.extension.getURL('main.html')
					});

					console.log(urlParams.access_token, urlParams.user_id);
				}
			}
		}
	});
}

function createNotification(id, message) {
    var nid = (id) ? id : Math.random().toString();
    chrome.notifications.create(nid, {
        type: 'basic',
        iconUrl: '/assets/icon_extension_120.png',
        title: 'HACKATHON DEV EXTENSION',
        message: message
    }, function(notificationID) {});
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
            case 'create_notification':
                createNotification(request.id, request.message);
                break;
            default:
                console.log('onMessage', request);
        }
    }
});