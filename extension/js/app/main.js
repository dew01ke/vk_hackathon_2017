(function() {
    console.log('[log]: content script init');

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
        view_application: $('#application'),
        view_auth_required: $('#auth-required'),
        header_navigation: $('.nav-link[data-location]')
    };

    var router = {
        activeRoute: null,
        hashListener: null,
        navs: $('.navs'),
        views: $('.views'),
        getPathStages: function(location) {
            var stages = {
                page: null,
                section: null
            };

            if (location) {
                if (location.indexOf('/') >= 0) {
                    stages.page = location.split('/')[0];
                    stages.section = location.split('/')[1];
                } else {
                    stages.page = location;
                    stages.section = null;
                }
            }

            return stages;
        },
        onInit: function() {
            var self = this;

            this.hashListener = window.addEventListener("hashchange", function() {
                var hash = (window.location.hash.length) ? window.location.hash.substring(1) : "";
                var path = self.getPathStages(hash);

                if (path.page) {
                    var targetView = self.views.filter('[data-view="' + path.page + '"]');

                    if (targetView.length) {
                        this.activeRoute = path;

                        self.views.removeClass('views-active');
                        targetView.addClass('views-active');

                        var targetNav = self.navs.filter('[data-view="' + path.page + '"]');
                        if (targetNav.length) {
                            self.navs.removeClass('navs-active');
                            targetNav.addClass('navs-active');

                            if (path.section) {
                                var sections = targetView.find('.sections');
                                var targetSection = sections.filter('[data-section="' + path.section + '"]');

                                if (targetSection.length) {
                                    sections.removeClass('sections-active');
                                    targetSection.addClass('sections-active');

                                    targetNav.find('.nav-link').removeClass('active').filter('[href*="' + path.page + '/' +  path.section + '"]').addClass('active'); //omg wtf?
                                }
                            }
                        }
                    } else {
                        console.log('[log]: route not found');
                    }
                }
            }, false);
        }
    };
    var api = new Api();

    function onInit() {
        sendBackgroundRequest('get_auth_state', null, function(data) {
            if (data && data.user_id) {
                cache.view_application.show();
                cache.view_auth_required.hide();

                requestStages();
            } else {
                cache.view_auth_required.show();
                cache.view_application.hide();
            }
        });

        router.onInit();

        cache.header_navigation.click(function() {
            cache.header_navigation.removeClass('active');
            $(this).addClass('active');
        });
    }

    function getNewsListTemplate(news) {
        var template = '';

        if (news) {
            for (let i in news) {
                var article = news[i];
                template += '<p>' + article.title + '</p>';
            }
        } else {
            template = '<p>Здесь ничего нет</p>';
        }

        return template;
    }

    function requestStages() {
        api.on('stages:get', function(e, stages) {
            api.off('stages:get', 'getStages');

            var html = '';
            var stagesContainer = $('.navs[data-view="index"]'), isStageFirst = true,
                viewContainer = $('.views[data-view="index"]'), isViewFirst = true;

            if (stages && stages.stages) {
                for (let i in stages.stages) {
                    let stage = stages.stages[i];

                    html += '<li class="nav-item">';
                    if (isStageFirst) {
                        isStageFirst = false;
                        html += '<a class="nav-link active" href="#index/stage' + stage.id + stage.name + '">' + stage.name + '</a>';
                    } else {
                        html += '<a class="nav-link" href="#index/stage' + stage.id + stage.name + '">' + stage.name + '</a>';
                    }
                    html += '</li>';

                    api.on('news:get', function(e, news) {
                        api.off('news:get', 'getStageContent' + stage.id);

                        var content = '';
                        if (isViewFirst) {
                            isViewFirst = false;
                            content += '<div class="sections sections-active" data-section="stage' + stage.id + stage.name + '">';
                        } else {
                            content += '<div class="sections" data-section="stage' + stage.id + stage.name + '">';
                        }

                        content += getNewsListTemplate(news.news);
                        content += '</div>';
                        viewContainer.append(content);

                    }, 'getStageContent' + stage.id);

                    api.news.get({ stage_id: stage.id }, 'getStageContent' + stage.id);
                }

                stagesContainer.html(html);
            }
        }, 'getStages');
        api.stages.get({ params: {} }, 'getStages');
    }

    onInit();
})();