<html>
  <head>
    <title>Лентач</title>
    <style type="text/css">
      html { padding:0;margin:0;border:0 none;max-height:500px;overflow-y:scroll}
      form { padding:0;margin:0;border:0 none;clear:both}
      fieldset { padding:0;margin:0;border:0 none}
      input[type=url], input[type=text], textarea { width:100%;box-sizing:border-box;}
      input[type=submit] { }
      form .item { margin-bottom:5px}
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
    <form action="vk-iframe.php/addNews" method="POST" id="news-item-form">
      <fieldset>
	<div class="item">
	  <input type="file" multiple="multiple" name="image" placeholder="Изображение к новости"/>
	</div>
	<div class="item">
	  <input type="url" name="url" placeholder="Ссылка на новость"/>
	</div>
	<div class="item">
	  <input type="text" name="title" placeholder="Заголовок новости"/>
	</div>
	<div class="item">
	  <textarea name="text" placeholder="Текст новости"></textarea>
	</div>
	<div class="item">
	  <label><input type="checkbox" name="anonymous"/> Отправить анонимно</label>
	</div>
	<div class="errors"></div>
	<div class="control">
	  <input type="submit" value="Отправить"/>
	</div>
      </fieldset>
    </form>
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
    
    <script type="text/javascript" src="/js/jquery-3.2.1.min.js"></script>
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

      $('a.post').click(function(e){
      e.preventDefault();
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
  </body>
</html>
