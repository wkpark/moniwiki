;jQuery(function(){
	if(typeof jQuery.cookie("show-nav") == 'undefined')
		jQuery.cookie('show-nav', '0');

    var menu = {
        show: function(){
            jQuery(".entry-nav-menu").show();
            jQuery(".menu-hide").show();
            jQuery(".menu-show").hide();
            jQuery.cookie('show-nav', '1');
            return false;
        },
        hide: function(){
            jQuery(".entry-nav-menu").hide();
            jQuery(".menu-hide").hide();
            jQuery(".menu-show").show();
            jQuery.cookie('show-nav', '0');
            return false;
        }
    };

	if(jQuery.cookie("show-nav") == '1'){
        menu.show();
	}else{
        menu.hide();
	}
    jQuery(".menu-hide").click(menu.hide);
    jQuery(".menu-show").click(menu.show);
});