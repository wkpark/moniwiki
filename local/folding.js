function foldingSection(btn,id)
{
    var sect;
    if (typeof id == 'object') sect=id;
    else sect=document.getElementById(id);
    if (!sect) return;
    var toggle=true;
    if (sect.style.display != 'none') {
        toggle=false;
    }

    var icon=null;
    if (btn)
        icon=btn.getElementsByTagName('img')[0];

    if (typeof Effect != 'undefined') { // prototype.js
        var dur = 0.5;
        if (toggle) {
          new Effect.SlideDown(sect, { duration: dur, afterFinish: function() {Element.show(sect);} });
        } else {
          new Effect.SlideUp(sect, { duration: dur, afterFinish: function() {Element.hide(sect);} });
        }
    } else { // get sectpages for the first time.
        var mySlide = new Fx.Slide(sect); // oops!!!
        if (sect.style.height != 0) {
            mySlide.slideIn();
        } else {
            mySlide.slideOut();
        }
    }
    if (icon) {
        var name=icon.getAttribute('class');
        if (name == 'close')
            icon.setAttribute('class','');
        else
            icon.setAttribute('class','close');
    }
}

// vim:et:sts=4:sw:
