function LentachButton($el) {
    try {
	$el.click(function(e) {
	    e.preventDefault();
	    var contact = prompt("Оставьте ваш контакт \nв одной из социальных сетей. \nЕсли ваша новость нам понравится, \nмы обязательно свяжемся с вами.");
	    $.get('https://ourApi/news/add', {url:window.location.href + window.location.hash}).done(function(r){
		if ('errors' in data && data.errors.length)
		    alert(data.errors.join("\n"));
		else if ('messages' in data && data.messages.length)
		    alert(data.messages.join("\n"));
	    }).fail(function(){
		alert('Что-то пошло не так');
	    });
	});
    } catch (){};
}
