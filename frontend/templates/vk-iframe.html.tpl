<html>
  <head>
    <title>Лентач</title>
    <link rel="stylesheet" href="/css/bootstrap.min.css"/>
    <style type="text/css">
      html { padding:0;margin:0;border:0 none;max-height:500px;overflow-y:scroll}
      form { padding:0;margin:0;border:0 none;clear:both}
      fieldset { padding:0;margin:0;border:0 none}
      input[type=url], input[type=text], textarea { width:100%;box-sizing:border-box;}
      input[type=submit] { }
      .last, .top { width:33.3%;float:left}
    </style>
  </head>
  <body>
    <div class="my-last-news">
      {foreach from=$news item=item}
      <div class="last">
	<a href="/vk-iframe.php/news/{$item.id}/delete" class="post">Отозвать</a>
	<div>{$item.title|escape:html}</div>
	<div>{$item.synopsis|escape:html}</div>
      </div>
      {/foreach}
    </div>
    <script type="text/javascript" src="/js/jquery-3.2.1.min.js"></script>
    <script type="text/javascript">
      $('a.post').click(function(e){
      e.preventDefault();
      var ok = confirm('Действительно безвозвратно удалить запись?');
      if (!ok)
      return;
      $.post(this.href, {}).done(function(data){
      if ('errors' in data && data.errors.length) {
      alert(data.errors.join("\n"));
      } else {
      alert(data.messages.join("\n"));
      window.location.reload();
      }
      }).fail(function(){
      alert('Что-то пошло не так');
      });
      });
    </script>
    <form action="vk-iframe.php/news/add" method="POST" id="news-item-form">
      <fieldset>
	<div class="item form-group">
	  <input class="form-control" type="file" multiple="multiple" name="image" placeholder="Изображение к новости"/>
	</div>
	<div class="item form-group">
	  <input class="form-control" type="url" name="url" placeholder="Ссылка на новость"/>
	</div>
	<div class="item form-group">
	  <input class="form-control" type="text" name="title" placeholder="Заголовок новости"/>
	</div>
	<div class="item form-group">
	  <textarea class="form-control" name="text" placeholder="Текст новости"></textarea>
	</div>
	<div class="item form-group">
	  <div class="form-check">
	    <label class="form-check-label"><input class="form-check-input" type="checkbox" name="anonymous"/> Отправить анонимно</label>
	  </div>
	</div>
	<div class="control form-group">
	  <input type="submit" value="Отправить" class="btn btn-primary"/>
	</div>
      </fieldset>
    </form>
    <script type="text/javascript">
      var form = $('#news-item-form');
      form.submit(function(e) {
      e.preventDefault();
      $.post('/vk-iframe.php/news/add', form.serialize()).done(function(data){
      if ('errors' in data && data.errors.length)alert(data.errors.join("\n"));
      else window.location.reload();
      }).fail(function(){
      alert('Что-то пошло не так');
      });
      });
    </script>
    <div class="top-3">

      <div class="top">
	<div>{$top1.title|escape:html}</div>
	<div>{$top1.synopsis|escape:html}</div>
      </div>
      <div class="top">
	<div>{$top2.title|escape:html}</div>
	<div>{$top2.synopsis|escape:html}</div>
      </div>
      <div class="top">
	<div>{$top3.title|escape:html}</div>
	<div>{$top3.synopsis|escape:html}</div>
      </div>
      
    </div>
    
  </body>
</html>
