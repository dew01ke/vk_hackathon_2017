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
    var stagesCache = {};
    var newsCache = {};

    var router = {
        activeRoute: {
            path: null,
            active_nav: null,
            active_view: null,
            active_section: null
        },
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
        getCurrentPath: function() {
            return (window.location.hash.length) ? window.location.hash.substring(1) : "";
        },
        onInit: function() {
            var self = this;

            this.hashListener = window.addEventListener("hashchange", function() {
                var hash = self.getCurrentPath();
                var path = self.getPathStages(hash);

                if (path.page) {
                    var targetView = self.views.filter('[data-view="' + path.page + '"]');

                    if (targetView.length) {
                        self.activeRoute.path = path;

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

                                    self.activeRoute.active_section = targetSection;
                                } else {
                                    self.activeRoute.active_section = null;
                                }
                            }

                            self.activeRoute.active_nav = targetNav;
                        } else {
                            self.activeRoute.active_nav = null;
                        }

                        self.activeRoute.active_view = targetView;
                    } else {
                        self.activeRoute.active_view = null;
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

    function getNewsListTemplate(news, stageID) {
        var template = '';

        if (news) {
            template += '<ul class="list-group">';
            for (let i in news) {
                var article = news[i];
                var time = moment(article.create_time * 1000);

                template += '<li class="list-group-item article-preview" data-article-id="' + article.id + '" data-stage-id="' + stageID + '">';

                template += '<div class="article-preview-time">' + time.format('DD.MM') + ' в ' + time.format('HH:mm') + '</div>';
                template += '<div class="article-preview-title">' + article.title + '</div>';
                template += '<div class="article-preview-text">' + article.synopsis + '</div>';

                template += '</li>';
            }
            template += '</ul>';
        } else {
            template = '<p>Здесь ничего нет</p>';
        }

        return template;
    }

    function getArticleFullTemplate(article) {
        var template = '';

        if (article) {
            var time = moment(article.create_time * 1000);
            var touched = (article.touch_time && article.touch_time !== '0') ? moment(article.touch_time * 1000) : null;

            template += '<h4 class="article-full-title" contentEditable>' + ((article.title === '') ? '(Название)' : article.title) + '</h4>';
            template += '<div class="article-full-time">' + time.format('DD.MM') + ' в ' + time.format('HH:mm') + '</div>';
            template += '<div class="article-full-text" contentEditable>' + ((article.synopsis === '') ? '(Текст)' : article.synopsis) + '</div>';

            if (article.origin_user && article.origin_user.first_name && article.origin_user.last_name && article.origin_user.origin_channel && article.origin_user.origin_id) {
                var name = article.origin_user.first_name + ' ' + article.origin_user.last_name;
                var link = name;

                switch(article.origin_user.origin_channel) {
                    case 'vk':
                        link = '<a target="blank" href="https://vk.com/id' + article.origin_user.origin_id + '">' + name + '</a>';
                        break;
                    default:
                        link = name;
                }

                template += '<div class="article-full-time">Отправил: ' + link + '</div>';
            }

            if (touched) {
                template += '<div class="article-full-time">Последнее изменение: ' + touched.locale('ru').fromNow() + '</div>';
            }

            template += '<div class="article-full-controls">';
            template += '<button type="button" class="btn btn-success btn-sm">Сохранить</button>';


            template += '<div class="btn-group">';
            template += '<button type="button" class="btn btn-info btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Переместить</button>';
            template += '<div class="dropdown-menu">';
            template += renderStagesToDropdown(stagesCache, article.id);
            template += '</div>';
            template += '</div>';


            template += '<button type="button" class="btn btn-right btn-outline-success btn-sm">+1</button>';
            template += '<button type="button" class="btn btn-right btn-outline-danger btn-sm">-1</button>';
            template += '</div>'; //controls

        } else {
            template += '<p>Что-то пошло не так :(</p>';
        }

        return template;
    }

    function requestStages() {
        api.on('stages:get', function(e, stages) {
            api.off('stages:get', 'getStages');

            var html = '';
            var stagesContainer = $('.navs[data-view="index"]'), isStageFirst = true,
                viewContainer = $('.views[data-view="index"]'), isViewFirst = true;

            router.activeRoute.active_nav = stagesContainer.find('.nav-link.active');
            router.activeRoute.active_view = viewContainer;

            if (stages && stages.stages) {
                var collection =  _.sortBy(stages.stages, [function(o) { return parseInt(o.oid); }]);
                let stagesCount = 0;

                stagesCache = collection;

                for (let i in collection) {
                    let stage = collection[i];

                    html += '<li class="nav-item">';
                    if (isStageFirst && stagesCount === 0) {
                        isStageFirst = false;
                        html += '<a class="nav-link active" href="#index/stage' + stage.id + stage.name + '">' + stage.name + '</a>';
                    } else {
                        html += '<a class="nav-link" href="#index/stage' + stage.id + stage.name + '">' + stage.name + '</a>';
                    }
                    html += '</li>';

                    api.on('news:get', function(e, news) {
                        api.off('news:get', 'getStageContent' + stage.id);

                        var content = '';
                        if (isViewFirst && stagesCount === 0) {
                            isViewFirst = false;
                            content += '<div class="sections sections-active" data-section="stage' + stage.id + stage.name + '">';
                        } else {
                            content += '<div class="sections" data-section="stage' + stage.id + stage.name + '">';
                        }

                        content += '<div class="row">';
                        content += '<div class="col">';
                        content += getNewsListTemplate(news.news, stage.id);
                        content += '</div>';
                        content += '<div class="col workflow article-full">';
                        content += '</div>'; //workflow
                        content += '</div>'; //row
                        content += '</div>'; //section

                        var section = $(content);
                        viewContainer.append(section);

                        //TODO: не очень оправданно так делать
                        if (!isViewFirst && stagesCount === 0) {
                            router.activeRoute.active_section = section;
                        }

                        newsCache[stage.id] = news.news;
                        stagesCount++;
                    }, 'getStageContent' + stage.id);

                    api.news.get({ stage_id: stage.id }, 'getStageContent' + stage.id);
                }

                stagesContainer.html(html);
            }
        }, 'getStages');
        api.stages.get({ params: {} }, 'getStages');
    }

    function renderStagesToDropdown(stages, articleID) {
        var template = '';

        for (var i in stages) {
            template += '<a data-article-id="' + articleID + '" data-stage-id="' + stages[i].id + '" class="dropdown-item" href="#">' + stages[i].name + '</a>';
        }

        return template;
    }

    $(document).on('click', '.article-preview', function(e) {
        var that = $(this);
        var articleID = that.attr('data-article-id');
        var stageID = that.attr('data-stage-id');

        if (articleID && stageID) {
            if (newsCache.hasOwnProperty(stageID)) {
                var article = newsCache[stageID].filter(function(v) {
                    return (v.id === articleID);
                }).pop();
                if (article) {
                    var html = getArticleFullTemplate(article);
                    var container = (router.activeRoute && router.activeRoute.active_section) ? router.activeRoute.active_section.find('.workflow') : null;

                    if (container) {
                        container.html(html);
                    } else {
                        console.log('container for workflow not found', router.activeRoute);
                    }
                } else {
                    console.log('(1) article not found in article list cache', stageID, articleID, article, newsCache);
                }
            } else {
                console.log('(2) article not found in article list cache', stageID, articleID, newsCache);
            }
        }

        var visibleArticlePreview = $('.article-preview').filter(':visible').removeClass('article-preview-active');
        that.addClass('article-preview-active');
    });

    onInit();
})();