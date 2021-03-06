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
    var newsCache = {
        list_by_stages: {},
        user_profile: null
    };

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
			
			maintainLayout();
			
			window.setInterval(function() {
				maintainLayout();
			}, 1000);

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
            if (data && data.user_id && data.user_token) {
                cache.view_application.show();
                cache.view_auth_required.hide();

                api.user_id = data.user_id;
                api.user_token = data.user_token;

                requestStages();
            } else {
                cache.view_auth_required.show();
                cache.view_application.hide();
            }
        });

        router.onInit();

        var hash = router.getCurrentPath();
        var path = router.getPathStages(hash);
        var page = (path.page && path.section) ? path.page : 'index';
        $('.views').removeClass('views-active').filter('[data-view="' + page + '"]').addClass('views-active');
        $('.navs').removeClass('navs-active').filter('[data-view="' + page + '"]').addClass('navs-active');

        // var mainInterval = setInterval(function() {
        //     api.on('notifications:getList', function(e, response) {
        //         api.off('news:get', 'getNotificationsList');
        //
        //         console.log(response);
        //     }, 'getNotificationsList');
        //
        //     api.notifications.getList({ mark: 1, limit: 5, fresh_only: 1 }, 'getNotificationsList');
        // }, 5000);

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

                template += '<div class="article-active-corner"></div><div class="article-preview-time">';
                template += time.format('DD.MM') + ' в ' + time.format('HH:mm');
                if (article.source_url) template += '<span class="article-preview-sender"><a href="' + encodeURIComponent(article.source_url) + '" target="_blank">URL</a></span>';
                template += '<span class="article-preview-sender">' + renderUserProfile(article, true, 'источник: ') + '</span>';
                template += '</div>';

                template += '<div class="article-preview-title">' + article.title + '</div>';
                template += '<div class="article-preview-text">' + ((article.synopsis.length > 120 && _.isString(article.synopsis)) ? article.synopsis.substring(0, 119) + '...' : article.synopsis) + '</div>';

				template += '<div class="article-preview-controls">';
                template += '<button data-article-id="' + article.id + '" type="button" class="article-preview-remove-button btn btn-right btn-outline-secondary btn-sm"><span class="oi oi-trash"></span></button>';
                template += '<div class="article-preview-rating">' + (article.rating_up ? "<span class='article-preview-rating-down'>" + article.rating_down + "</span>" : "") + (article.rating_up ? "<span class='article-preview-rating-up'>" + article.rating_up + "</span>" : "") + '</div>';
				template += '</div>';

                template += '</li>';
            }
            template += '</ul>';
        } else {
            template = '<div class="content-empty"><p>Заявок в этой папке в настоящий момент нет.</p></div>';
        }

        return template;
    }

    function getArticleFullTemplate(article, user) {
        var template = '';

        if (article) {
            var time = moment(article.create_time * 1000);
            var touched = (article.touch_time && article.touch_time !== '0') ? moment(article.touch_time * 1000) : null;

            template += '<div class="article-editable-outer"><h4 class="article-full-title" contentEditable>' + ((article.title === '') ? '(Название)' : article.title) + '</h4><div class="edit-small"></div></div>';
            template += '<div class="article-editable-outer"><div class="article-full-text" contentEditable>' + ((article.synopsis === '') ? '(Текст)' : article.synopsis) + '</div><div class="edit-small"></div></div>';

            template += '<div class="article-full-time">' + renderUserProfile(article, true, 'Отправил: ') + ' ' + time.format('DD.MM') + ' в ' + time.format('HH:mm') + '</div>';

            if (touched) {
                template += '<div class="article-full-time">Последнее изменение: ' + touched.locale('ru').fromNow() + '</div>';
            }

            template += '<div class="article-full-controls">';
            template += '<button data-article-id="' + article.id + '" type="button" class="article-full-save-button btn btn-success btn-sm">Сохранить</button>';

            template += '<div class="btn-group">';
            template += '<button type="button" class="btn btn-info btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Переместить</button>';
            template += '<div class="dropdown-menu">';
            template += renderStagesToDropdown(stagesCache, article.id);
            template += '</div>';
            template += '</div>';

            template += '<button data-article-id="' + article.id + '" type="button" class="article-comment-button btn btn-comment btn-sm">&nbsp;</button>';

            template += '<div class="article-comment-form" style="display: none;">';
            template += '<textarea style="width: 100%; min-height: 50px; padding: 5px;" placeholder="Комментарий"></textarea>';

            template += '<div class="article-full-controls">';
            template += '<button data-article-id="' + article.id + '" type="button" class="article-full-comment-save-button btn btn-success btn-sm">Отправить комментарий</button>';
            template += '</div>';

            template += '</div>';

            var buttonUpvoteClass = 'btn-outline-success',
                buttonDownvoteClass = 'btn-outline-danger';
            if (article.rating_list && user && user.id) {
                var uid = parseInt(user.id);
                if (article.rating_list.hasOwnProperty(uid)) {
                    if (article.rating_list[uid] > 0) {
                        buttonUpvoteClass = 'btn-success';
                    } else {
                        buttonDownvoteClass = 'btn-danger';
                    }
                }
            }

            template += '<button data-stage-id="' + article.stage_id + '" data-article-id="' + article.id + '" data-article-rate="upvote" type="button" class="article-full-rate-button btn btn-right btn-sm ' + buttonUpvoteClass + '">+1</button>';
            template += '<button data-stage-id="' + article.stage_id + '" data-article-id="' + article.id + '" data-article-rate="downvote" type="button" class="article-full-rate-button btn btn-right btn-sm ' + buttonDownvoteClass + '">-1</button>';
            template += '</div>'; //controls
			
			if (article.pipeline) {
				for (var key in article.pipeline) {
					var action = article.pipeline[key];
					var pipelineHTML = "";
					var blockText = "";
					pipelineHTML += '<div class="pipeline-item">';
					pipelineHTML += '<div class="article-preview-time">';
					var time = moment(action.time * 1000);
					pipelineHTML += time.format('DD.MM') + ' в ' + time.format('HH:mm');
					if (action.user) {
						pipelineHTML += '<span class="article-preview-sender role' + action.user.role_id + '">' + action.user.first_name + ' ' + action.user.last_name + '</span>';
					}
					pipelineHTML += '</div>';
					if (action.type == 0) {
						blockText = '<b>Заявка на публикацию новости.</b>';
					} else if (action.type == 1) {
						blockText = 'Перенос новости в другую папку.</b>';
					} else {
						if (action.comment && action.comment != "") {
							blockText += '<div class="pipeline-comment">' + action.comment + '</div>';
						}
						if (action.files) {
							blockText += '<div class="pipeline-files-header">Прикрепленные файлы:</div>';
							for (var fileKey in action.files) {
								var file = action.files[fileKey];
								blockText += '<div class="pipeline-file">' + $('<div/>').text((file.name && file.name != '') ? file.name : file.origin_name).html() + '</div>';
							}
						}
					}
					pipelineHTML += blockText;
					pipelineHTML += '</div>';
				}
				template += pipelineHTML;
			}

        } else {
            template += '<p>Что-то пошло не так :(</p>';
        }

        return template;
    }

    function requestStage(stage, viewContainer) {
        api.on('news:get', function(e, news) {
            api.off('news:get', 'getStageContent' + stage.id);

            var content = '';
            content += '<div class="article-list">';
            content += getNewsListTemplate(news.news, stage.id);
            content += '</div>';

            var section = $(content);
            viewContainer.find('[data-section="stage' + stage.id + stage.name + '"]').find('.article-list').replaceWith(section);
            router.activeRoute.active_section = viewContainer.find('[data-section="stage' + stage.id + stage.name + '"]');
            maintainLayout();

            if (news.count_by_stage) {
                for (var key in news.count_by_stage) {
                    $(".stage-counter[data-stage=" + key + "]").text(news.count_by_stage[key]);
                }
            }

            newsCache.list_by_stages[stage.id] = news.news;
            newsCache.user_profile = news.user_profile;
        }, 'getStageContent' + stage.id);

        api.news.get({ stage_id: stage.id }, 'getStageContent' + stage.id);
    }

    function requestStages() {
        api.on('stages:get', function(e, stages) {
            api.off('stages:get', 'getStages');

            var html = '';
            var stagesContainer = $('.navs[data-view="index"]'), isStageFirst = !!router.activeRoute.path,
                viewContainer = $('.views[data-view="index"]'), isViewFirst = !!router.activeRoute.path;
            var hash = router.getCurrentPath();
            var path = router.getPathStages(hash);
            var currentPath = path.section;

            router.activeRoute.active_nav = stagesContainer.find('.nav-link.active');
            router.activeRoute.active_view = viewContainer;

            if (stages && stages.stages) {
                var collection =  _.sortBy(stages.stages, [function(o) { return parseInt(o.oid); }]);
                let stagesCount = 0;
                let stagePath = (currentPath) ? currentPath : 'stage' + collection[0].id + collection[0].name ;

                stagesCache = collection;

                for (let i in collection) {
                    let stage = collection[i];
                    let relatedPath = 'stage' + stage.id + stage.name;

                    html += '<li class="nav-item ' + ((stage.priority < 0) ? "nav-trash" : "") + '">';
                    if (isStageFirst && stagesCount === 0 || relatedPath === currentPath) {
                        isStageFirst = false;
                        html += '<a data-stage-id="' + stage.id + '" class="change-stage nav-link active" data-section="stage' + stage.id + stage.name + '" href="#index/stage' + stage.id + stage.name + '"><span class="stage-counter" data-stage="' + stage.id + '">0</span>' + stage.name + '</a>';
                    } else {
                        html += '<a data-stage-id="' + stage.id + '" class="change-stage nav-link" data-section="stage' + stage.id + stage.name + '" href="#index/stage' + stage.id + stage.name + '"><span class="stage-counter" data-stage="' + stage.id + '">0</span>' + stage.name + '</a>';
                    }
                    html += '</li>';

                    api.on('news:get', function(e, news) {
                        api.off('news:get', 'getStageContent' + stage.id);

                        var content = '';
                        if (isViewFirst && stagesCount === 0 || relatedPath === currentPath) {
                            isViewFirst = false;
                            content += '<div class="sections sections-active" data-section="stage' + stage.id + stage.name + '">';
                        } else {
                            content += '<div class="sections" data-section="stage' + stage.id + stage.name + '">';
                        }

                        content += '<div class="row article-list-row">';
                        content += '<div class="col article-list-col"><div class="article-list">';
                        content += getNewsListTemplate(news.news, stage.id);
                        content += '</div></div>';
                        content += '<div class="col article-full"><div class="workflow">';
                        content += '</div></div>'; //workflow
                        content += '</div>'; //row
                        content += '</div>'; //section

                        var section = $(content);
                        viewContainer.append(section);

                        //TODO: не очень оправданно так делать
                        if (!isViewFirst && stagesCount === 0 || relatedPath === currentPath) {
                            router.activeRoute.active_section = section;
                        }
						
						if (news.count_by_stage) {
							for (var key in news.count_by_stage) {
								$(".stage-counter[data-stage=" + key + "]").text(news.count_by_stage[key]);
							}
						}

                        newsCache.list_by_stages[stage.id] = news.news;
                        newsCache.user_profile = news.user_profile;

						var profileHTML = "<img src='/assets/profile.png' width='32'>&nbsp;&nbsp;&nbsp; <b>" + news.user_profile.first_name + " " + news.user_profile.last_name + "</b>";
						$(".header-profile").html(profileHTML);

						if (stagesCount === collection.length - 1 && !router.activeRoute.path) {
						    viewContainer.find('.sections').removeClass('sections-active').filter('[data-section^="' + stagePath + '"]').addClass('sections-active');
                        }
                        stagesCount++;
                    }, 'getStageContent' + stage.id);

                    api.news.get({ stage_id: stage.id }, 'getStageContent' + stage.id);
                }

                stagesContainer.html(html);

                console.log('router.activeRoute.path', router.activeRoute.path);

                if (!router.activeRoute.path) {
                    stagesContainer.find('.nav-link').removeClass('active').filter('[data-section="' + stagePath + '"]').addClass('active');
                }
            }
        }, 'getStages');
        api.stages.get({ params: {} }, 'getStages');
    }

    function renderStagesToDropdown(stages, articleID) {
        var template = '';

        for (var i in stages) {
            template += '<a data-article-id="' + articleID + '" data-stage-id="' + stages[i].id + '" class="dropdown-item article-change-stage" href="#">' + stages[i].name + '</a>';
        }

        return template;
    }

    function renderUserProfile(article, wrapByLink, beforeText) {
        var output = '';

        if (article.origin_user && article.origin_user.first_name && article.origin_user.last_name && article.origin_user.origin_channel && article.origin_user.origin_id) {
            var name = article.origin_user.first_name + ' ' + article.origin_user.last_name;

            switch(article.origin_user.origin_channel) {
                case 'vk':
                    if (wrapByLink) {
                        output = '<a target="blank" class="profile-link" href="https://vk.com/id' + article.origin_user.origin_id + '">' + name + '</a>';
                    } else {
                        output = name;
                    }
                    break;
                default:
                    output = name;
            }
        }

        if (beforeText && output !== '') {
            output = beforeText + output;
        }

        return output;
    }
	
	// Для приведения флексов основной части к полной высоте страницы (минус шапка)
	function maintainLayout() {
		var totalHeight = $(window).height();
		var headerHeight = 85
		var rowContainer = $(".article-list-row");
		var mainHeight = totalHeight - headerHeight;
		rowContainer.height(mainHeight);
		var articleList = $(".article-list");
		// articleList.height(mainHeight - 50);
		var workFlow = $(".workflow");
		// workFlow.height(mainHeight - 50);
	}
	
	function newSearch(s) {
		// Сделать так, чтобы все News::getList шли с search = s
	}

	$(".left-search").on('click', function(e) {
		$(".search-input").val("");
	});
	
	$("#searchForm").on('submit', function(e) {
		newSearch($(".search-input").val());
		e.stopPropagation();
		e.preventDefault();
		return false;
	});

    $(document).on('click', '.article-preview', function(e) {
        var that = $(this);
        var articleID = that.attr('data-article-id');
        var stageID = that.attr('data-stage-id');

        if (articleID) {
            api.on('news:getOne', function(e, response) {
                api.off('news:getOne', 'articleGetOne');

                if (response.item && response.user_profile) {
                    var html = getArticleFullTemplate(response.item, response.user_profile);
                    var container = (router.activeRoute && router.activeRoute.active_section) ? router.activeRoute.active_section.find('.workflow') : null;

                    if (container) {
                        container.html(html);
                    } else {
                        console.log('container for workflow not found', router.activeRoute);
                    }
					
					container.find("[contenteditable]").each(function() {
						$(this).on("focus", function() {
							$(this).closest(".article-editable-outer").find(".edit-small").hide();
						});
						$(this).on("blur", function() {
							$(this).closest(".article-editable-outer").find(".edit-small").show();
						});
					});
                }
            }, 'articleGetOne');

            api.news.getOne({ id: articleID }, 'articleGetOne');

            // if (newsCache.list_by_stages.hasOwnProperty(stageID)) {
            //     var article = newsCache.list_by_stages[stageID].filter(function(v) {
            //         return (v.id === articleID);
            //     }).pop();
            //     if (article) {
            //         var html = getArticleFullTemplate(article, newsCache.user_profile);
            //         var container = (router.activeRoute && router.activeRoute.active_section) ? router.activeRoute.active_section.find('.workflow') : null;
            //
            //         if (container) {
            //             container.html(html);
            //         } else {
            //             console.log('container for workflow not found', router.activeRoute);
            //         }
            //     } else {
            //         console.log('(1) article not found in article list cache', stageID, articleID, article, newsCache);
            //     }
            // } else {
            //     console.log('(2) article not found in article list cache', stageID, articleID, newsCache);
            // }
        }

        var visibleArticlePreview = $('.article-preview').filter(':visible').removeClass('article-preview-active');
        that.addClass('article-preview-active');
    });

    $(document).on('click', '.article-change-stage', function(e) {
        e.stopPropagation();

        var that = $(this);
        var articleID = that.attr('data-article-id');
        var stageID = that.attr('data-stage-id');

        if (articleID && stageID) {
            api.on('news:setStage', function(e, response) {
                api.off('news:setStage', 'setStage');

                if (response.success) {
                    var stage = stagesCache.filter(function(v) {
                        return (v.id === stageID);
                    }).pop();

                    if (stage && router.activeRoute && router.activeRoute.active_view) {
                        requestStage(stage, router.activeRoute.active_view);
                    }

                    alert('Успешно сменили стадию');
                } else {
                    alert('При смене стадии возникла ошибка');
                }
            }, 'setStage');

            api.news.setStage({ id: articleID, stage_id: stageID }, 'setStage');
        }
    });

    $(document).on('click', '.article-preview-remove-button', function(e) {
        e.stopPropagation();
        e.preventDefault();

        var that = $(this);
        var articleID = that.attr('data-article-id');

        if (articleID) {
            api.on('news:delete', function(e, response) {
                api.off('news:delete', 'articleRemove');

                if (response.success) {
                    var parent = that.closest('.article-preview');
                    parent.remove();
                } else {
                    alert('При удалении статьи возникла ошибка');
                }
            }, 'articleRemove');

            api.news.delete({ id: articleID }, 'articleRemove');
        }
    });

    $(document).on('click', '.article-full-rate-button', function(e) {
        e.stopPropagation();
        e.preventDefault();

        var that = $(this);
        var articleID = that.attr('data-article-id');
        var rateType = that.attr('data-article-rate');
        var rating = 0;

        if (articleID && rateType) {
            if (rateType === 'upvote') {
                rating = 1;
            } else {
                rating = -1;
            }

            if (rateType === 'upvote' && that.hasClass('btn-success') ||
                rateType === 'downvote' && that.hasClass('btn-danger')) {
                rating = 0;
            }

            api.on('news:rate', function(e, response) {
                api.off('news:rate', 'articleRate');

                if (response.success) {
                    if (rateType === 'upvote') {
                        that.toggleClass('btn-outline-success btn-success');
                        that.parent().find('.btn-danger').toggleClass('btn-outline-danger btn-danger');
                    } else {
                        that.toggleClass('btn-outline-danger btn-danger');
                        that.parent().find('.btn-success').toggleClass('btn-outline-success btn-success');
                    }

                    var stageID = that.attr('data-stage-id');
                    if (stageID) {
                        var stage = stagesCache.filter(function(v) {
                            return (v.id === stageID);
                        }).pop();

                        if (stage && router.activeRoute && router.activeRoute.active_view) {
                            requestStage(stage, router.activeRoute.active_view);
                        }
                    }
                } else {
                    alert('При оценивании статьи возникла ошибка');
                }
            }, 'articleRate');

            api.news.rate({ id: articleID, rating: rating }, 'articleRate');
        }
    });

    $(document).on('click', '.article-full-save-button', function(e) {
        e.stopPropagation();
        e.preventDefault();

        var that = $(this);
        var articleID = that.attr('data-article-id');

        if (articleID) {
            var parent = that.closest('.workflow');
            var articleTitle = parent.find('.article-full-title').text();
            var articleSynopsis = parent.find('.article-full-text').text();

            api.on('news:update', function(e, response) {
                api.off('news:update', 'articleUpdate');

                if (response.success) {
                    alert('Статья успешно сохранена');
                } else {
                    alert('При сохранении статьи возникла ошибка');
                }
            }, 'articleUpdate');

            api.news.update({ id: articleID, title: articleTitle, synopsis: articleSynopsis }, 'articleUpdate');
        }
    });

    $(document).on('click', '.change-stage', function(e) {
        e.stopPropagation();

        var that = $(this);
        var stageID = that.attr('data-stage-id');

        if (stageID) {
            var stage = stagesCache.filter(function(v) {
                return (v.id === stageID);
            }).pop();

            if (stage && router.activeRoute && router.activeRoute.active_view) {
                requestStage(stage, router.activeRoute.active_view);
            }
        }
    });

    $(document).on('click', '.article-full-comment-save-button', function(e) {
        e.stopPropagation();

        var that = $(this);
        var parent = that.closest('.article-comment-form');
        var articleID = that.attr('data-article-id');
        var text = parent.find('textarea').val();
        var comment = $.trim(text);

        if (comment === '') {
            alert('Похоже, что вы забыли ввести комментарий');
            return false;
        }

        if (articleID) {
            api.on('news:addComment', function(e, response) {
                api.off('news:addComment', 'articleComment');

                if (response.success) {
                    parent.find('.article-comment-form').slideToggle();

                    api.on('news:getOne', function(e, response) {
                        api.off('news:getOne', 'articleGetOne');

                        if (response.item && response.user_profile) {
                            var html = getArticleFullTemplate(response.item, response.user_profile);
                            var container = (router.activeRoute && router.activeRoute.active_section) ? router.activeRoute.active_section.find('.workflow') : null;

                            if (container) {
                                container.html(html);
                            } else {
                                console.log('container for workflow not found', router.activeRoute);
                            }

                            container.find("[contenteditable]").each(function() {
                                $(this).on("focus", function() {
                                    $(this).closest(".article-editable-outer").find(".edit-small").hide();
                                });
                                $(this).on("blur", function() {
                                    $(this).closest(".article-editable-outer").find(".edit-small").show();
                                });
                            });
                        }
                    }, 'articleGetOne');

                    api.news.getOne({ id: articleID }, 'articleGetOne');
                }
            }, 'articleComment');

            api.news.addComment({ id: articleID, comment: comment }, 'articleComment');
        }
    });

    $(document).on('click', '.article-comment-button', function(e) {
        e.stopPropagation();

        var that = $(this);
        var parent = that.closest('.workflow');
        var form = parent.find('.article-comment-form');

        form.slideToggle();

        // var articleID = that.attr('data-article-id');
        // var comment = $.trim('123');
        //
        // if (comment === '') {
        //     alert('Похоже, что вы забыли ввести комментарий');
        //     return false;
        // }
        //
        // if (articleID) {
        //     api.on('news:addComment', function(e, response) {
        //         api.off('news:addComment', 'articleComment');
        //
        //         console.log(response);
        //     }, 'articleComment');
        //
        //     api.news.addComment({ id: articleID, comment: comment }, 'articleComment');
        // }
    });

    onInit();
})();