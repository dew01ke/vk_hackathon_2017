(function() {
    console.log('[log]: content script init');

    var cache = {
        header_nav: $('.nav-link[data-location]')
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

    function onInit() {
        router.onInit();
    }

    onInit();
})();