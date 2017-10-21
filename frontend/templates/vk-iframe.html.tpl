<html>
  <head>
    <title>Лентач</title>
    <script type="text/javascript" src="/js/jquery-3.2.1.min.js"></script>
    <style type="text/css">
      html { padding:0;margin:0;border:0 none;max-height:500px;overflow-y:scroll}
      body { font-family: -apple-system,BlinkMacSystemFont,Roboto,Open Sans,Helvetica Neue,sans-serif;font-size: 14px;}
      form { padding:0;margin:0;border:0 none;clear:both;}
      fieldset { padding:0;margin:0;border:0 none}
      input[type=submit], input[type=button], input[type=url], input[type=text], textarea { width:100%;box-sizing:border-box;padding:5px 8px;font-style:inherit;font-family:inherit;font-size:inherit;border-radius:3px;border:1px solid #6888ad}
      input[type=submit], input[type=button] { width:auto;border-color:#6888ad;background-color:#6888ad; color:#fff;padding-left:15px;padding-right:15px;}
      .form-group { margin-bottom:.5rem}
      .last, .top { margin-bottom: 10px}
      .last a { text-decoration:none}
      a { color:#6888ad}
    </style>
  </head>
  <body>
    <div class="container">
    <form enctype="multipart/form-data" action="vk-iframe.php/news/add" method="POST" id="news-item-form" style="margin-bottom:20px;">
      <fieldset>
	<div class="item form-group row" style="">
	  <input class="form-control form-control-sm" type="text" name="title" placeholder="Заголовок новости"/>
	</div>
	<div class="item form-group row">
	  <textarea rows="5" cols="60" class="form-control form-control-sm" name="text" placeholder="Текст новости"></textarea>
	</div>
	<div class="form-group row">
	  <div class="item form-group row" style="width:40%;float:left">
	    <input class="form-control form-control-sm" type="url" name="url" placeholder="Ссылка на новость" style="display:none;width:50%"/>
	    <input type="button" value="Прикрепить файлы" class="btn btn-primary btn-sm" onclick="this.form.elements.image.click();"/> <input class="form-control form-control-sm" type="file" multiple="multiple" name="image" placeholder="Изображение к новости" style="display:none;"/>
	  </div>
	  <div class="control form-group row" style="width:60%;float:left;text-align:right;">
	    <!--label class="form-check-label">анонимно <input class="form-check-input" type="checkbox" name="anonymous" style="vertical-align: middle;"/></label--> &nbsp;&nbsp;&nbsp;
	    <input type="submit" value="Отправить" class="btn btn-primary btn-sm"/>
	  </div>
	</div>
      </fieldset>
    </form>
    </div>
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
    <div class="my-last-news">
      {foreach from=$news item=item}
      <div class="last">
	
	<div>{$item.title|truncate:160|escape:html}</div>
	<div>{$item.synopsis|truncate:160|escape:html}</div>
	<div style="text-align:right"><span style="font-size:12px;color:#939393;">Отправлена {$item|date_format:"%d.%m в %H:%M"}</span> &nbsp;&nbsp; <a href="/vk-iframe.php/news/{$item.id}/delete" class="post">Отозвать</a></div>
      </div>
      {/foreach}
    </div>
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
    <!--div class="top-3">

      <div class="top">
	<div>{$top1.title|escape:html}</div>
	<div>{$top1.synopsis|truncate:160|escape:html}</div>
      </div>
      <div class="top">
	<div>{$top2.title|escape:html}</div>
	<div>{$top2.synopsis|truncate:160|escape:html}</div>
      </div>
      <div class="top">
	<div>{$top3.title|escape:html}</div>
	<div>{$top3.synopsis|truncate:160|escape:html}</div>
      </div>
      
    </div-->
    
  </body>
</html>
