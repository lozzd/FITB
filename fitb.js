// pinched from stack overflow - returns the value of a GET param
function getParameterByName(name) {
    name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");

    var regexS = "[\\?&]" + name + "=([^&#]*)";
    var regex = new RegExp(regexS);
    var results = regex.exec(window.location.href);

    if(results == null)
        return "";
    else
        return decodeURIComponent(results[1].replace(/\+/g, " "));
}

// hacky function for expanding/collapsing the side nav
$(function(){
    var host = getParameterByName('host');

    // there must be a neater way to find the ul of the parent
    $('#navlinks>li>a').each( function() {
	var link_title = $(this).html();
	if (link_title == host) {
	   $(this).parent().find('ul').show();
	}
	else {
	   $(this).parent().find('ul').hide();
	}
    });
});
