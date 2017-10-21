'use strict';

function Api() {
    var _this = this;
    var base = 'https://hackathon.andrey-volkov.ru/';
    var events = {
        'error':{e:[]},
        'users:getOne':{e:[]},
        'news:getOne':{e:[]},
        'news:get':{e:[]},
        'news:add':{e:[]},
        'news:update':{e:[]},
        'news:stage:change':{e:[]},
        'news:setStage':{e:[]},
        'news:delete':{e:[]},
        'news:rate':{e:[]},
        'stages:getOne':{e:[]},
        'stages:get':{e:[]},
        'flags:getOne':{e:[]},
        'flags:get':{e:[]},
        'flags:add':{e:[]},
        'files:add':{e:[]}
    };

    _this.user_id = null;
    _this.user_token = null;
    
    _this.triggerEvent = function(event, data, target) {
        for (let i = 0; i < events[event].e.length; i++) {
            if (events[event].e[i].target === target)
                events[event].e[i].handler(events[event], data);
        }
    };

    _this.on = function(event, handler, target) {
        events[event].e.push({target:target,handler:handler});
    }

    _this.off = function(event, target) {
        for (let i = 0; i < events[event].e.length; i++) {
            if (events[event].e[i].target === target) {
                events[event].e.splice(i, 1);
                return;
            }
        }
    }

//    _this.users = {
//	getOne: function({id}, target) {
//	    $.post('/users/' + id, {}).done(function(r) {
//		if ('error' in r && r.error)
//		    _this.triggerEvent('error', {error:r.error}, target);
//		else
//		    _this.triggerEvent('users:getOne', {user:r.user}, target);
//	    });
//	}
//    };
    _this.news = {
        getOne: function(id, target) {
            $.get(base + 'api/news/get', { origin_id: _this.user_id, token: _this.user_token, id: id }).done(function(r) {
            if ('error' in r && r.error)
                _this.triggerEvent('error', {error:r.error}, target);
            else
                _this.triggerEvent('news:getOne', {newsItem:r.newsItem}, target);
            });
        },
        get: function(params, target) {
            params.origin_id =_this.user_id;
            params.token = _this.user_token;
            $.post(base + 'api/news/getList', params).done(function(r) {
                if ('error' in r && r.error)
                    _this.triggerEvent('error', {error:r.error}, target);
                else
                    _this.triggerEvent('news:get', {news:r.list}, target);
            });
        },
        setStage: function(params, target) {
            params.origin_id =_this.user_id;
            params.token = _this.user_token;
            $.post(base + 'api/news/setStage', params).done(function(r) {
                if ('error' in r && r.error)
                    _this.triggerEvent('error', {error:r.error}, target);
                else
                    _this.triggerEvent('news:setStage', { success: (r.status === 100 && r.statusMessage === 'OK') }, target);
            });
        },
        delete: function(params, target) {
            params.origin_id =_this.user_id;
            params.token = _this.user_token;
            $.post(base + 'api/news/delete', params).done(function(r) {
                if ('error' in r && r.error)
                    _this.triggerEvent('error', {error:r.error}, target);
                else
                    _this.triggerEvent('news:delete', { success: (r.status === 100 && r.statusMessage === 'OK') }, target);
            });
        },
        rate: function(params, target) {
            params.origin_id =_this.user_id;
            params.token = _this.user_token;
            $.post(base + 'api/news/rate', params).done(function(r) {
                if ('error' in r && r.error)
                    _this.triggerEvent('error', {error:r.error}, target);
                else
                    _this.triggerEvent('news:rate', { success: (r.status === 100 && r.statusMessage === 'OK') }, target);
            });
        }
//	add: function({params}, target) {
//	    $.post('/news/create', {params:params}).done(function(r) {
//		if ('error' in r && r.error)
//		    _this.triggerEvent('error', {error:r.error}, target);
//		else
//		    _this.triggerEvent('news:add', {newsItem:{r.id}}, target);
//	    });
//	},
//	update: function({id, params}, target) {
//	    $.post('/news/update', {id:id, params:params}).done(function(r) {
//		if ('error' in r && r.error)
//		    _this.triggerEvent('error', {error:r.error}, target);
//		else
//		    _this.triggerEvent('news:update', {newsItem:r.newsItem}, target);
//	    });
//	}
    };
    _this.stages = {
//	getOne: function({id}, target) {
//	    $.post('/stages/' + id, {}).done(function(r) {
//		if ('error' in r && r.error)
//		    _this.triggerEvent('error', {error:r.error}, target);
//		else
//		    _this.triggerEvent('stages:getOne', {stage:r.stage}, target);
//	    });
//	},
        get: function(params, target) {
            $.get(base + 'api/stages/getAll', { origin_id:_this.user_id, token: _this.user_token, params: params.params }).done(function(r) {
                if ('error' in r && r.error)
                    _this.triggerEvent('error', {error:r.error}, target);
                else
                    _this.triggerEvent('stages:get', {stages:r.list}, target);
            });
        }
    };
//    _this.flags = {
//	getOne: function({id}, target) {
//	    $.post('/flags/' + id, {}).done(function(r) {
//		if ('error' in r && r.error)
//		    _this.triggerEvent('error', {error:r.error}, target);
//		else
//		    _this.triggerEvent('flags:getOne', {flag:r.flag}, target);
//	    });
//	},
//	get: function({id, params}, target) {
//	    $.post('/flags', {params:params}).done(function(r) {
//		if ('error' in r && r.error)
//		    _this.triggerEvent('error', {error:r.error}, target);
//		else
//		    _this.triggerEvent('flags:get', {flags:r.flags}, target);
//	    });
//	},
//	add: function({params}, target) {
//	    $.post('/flags/add', {params:params}).done(function(r) {
//		if ('error' in r && r.error)
//		    _this.triggerEvent('error', {error:r.error}, target);
//		else
//		    _this.triggerEvent('flags:add', {flag:r.flag}, target);
//	    });
//	}
//    };
//    _this.files = {
//	add: function({params}, target) {
//	    $.post('/files/add', {params:params}).done(function(r) {
//		if ('error' in r && r.error)
//		    _this.triggerEvent('error', {error:r.error}, target);
//		else
//		    _this.triggerEvent('files:add', {file:r.file}, target);
//	    });
//	}
//
//    };
}

// var api = new Api();
// api.on('error', function(e, data) {
//     alert(data.error);
// });
//
// function BigTableNewsView(news, stage) {console.log(stage);
//     for (let i in news) {
//         console.log(news[i]);
//     }
// }
//
// function BigTableView() {
//     api.on('stages:get', function(e, data) {
//         api.off('stages:get', 'fillMainTable');
//         for (let i in data.stages) {
//             let stage = data.stages[i];
//             api.on('news:get', function(e, data){
//                 api.off('news:get', 'fillMainTableNewsList' + i);
//                 new BigTableNewsView(data.news, stage);
//             }, 'fillMainTableNewsList' + i);
//             console.log(stage.id);
//             api.news.get({stage_id:stage.id}, 'fillMainTableNewsList' + i);
//         }
//     }, 'fillMainTable');
//     api.stages.get({params:{}}, 'fillMainTable');
// }
//
// BigTableView();
// console.log('OK');