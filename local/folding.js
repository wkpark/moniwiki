/*
 * Builti-in section folding for MoniWiki
 * 
 * $Id$
 */

function foldingSection(btn, id)
{
    var sect;
    if (typeof id == 'object') sect = id;
    else sect=document.getElementById(id);
    if (!sect) return;
    var toggle = true;
    if (sect.style.display != 'none') {
        toggle = false;
    }

    var icon=null;
    if (btn) {
        icon=btn.getElementsByTagName('img')[0];
        if (!icon) {
            icon = new Image();
            icon.src = _url_prefix + '/imgs/plugin/arrdown.png';
            icon.style.width = '12px';
            btn.insertBefore(icon, btn.firstChild);
            //btn.appendChild(icon);
        }
    }

    if (typeof Effect != 'undefined') { // prototype.js
        var dur = 0.5;
        if (toggle) {
          new Effect.SlideDown(sect, { duration: dur, afterFinish: function() {Element.show(sect);} });
        } else {
          new Effect.SlideUp(sect, { duration: dur, afterFinish: function() {Element.hide(sect);} });
        }
    } else { // get sectpages for the first time.
        var mySlide = new Fx.Slide(sect); // mootools
        mySlide.toggle();
    }
    if (icon) {
        var name=icon.getAttribute('class');
        if (name == 'close') {
            icon.src = _url_prefix + '/imgs/plugin/arrup.png';
            icon.setAttribute('class','');
        } else {
            icon.src = _url_prefix + '/imgs/plugin/arrdown.png';
            icon.setAttribute('class','close');
        }
    }
}

// vim:et:sts=4:sw=4:
