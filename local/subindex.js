/**
 Support subpage index for MoniWiki with prototype.js or mootools.
 by wkpark@kldp.org

*/
function toggleSubIndex(id)
{
    var subindex;
    if (typeof id == 'object') subindex=id;
    else subindex=document.getElementById(id);
    if (!subindex) return;
    var mode='',toggle='';

    var icon=subindex.getElementsByTagName('legend')[0];
    if (subindex) {
        var sub=subindex.getElementsByTagName('div')[0];
        if (sub) {
            mode= sub.getAttribute('style');
            if (typeof mode == 'object') { // for IE
                if (sub.style.display == 'none')
                    toggle=true;
                else
                    toggle=false;
            } else if (mode) {
                if (sub.style.display == 'none')
                    toggle = true;
                else
                    toggle = false;
            }
            if (typeof Effect != 'undefined') { // prototype.js
                if (toggle) {
	            new Effect.SlideDown(sub, { duration: 0.3, afterFinish: function() {Element.show(sub);} });
                } else {
	            new Effect.SlideUp(sub, { duration: 0.3, afterFinish: function() {Element.hide(sub);} });
                }
            } else if (typeof Fx.Slide == 'undefined') { // get sectpages for the first time.
                if (sub.style.display == 'none')
                    sub.style.display = 'block';
                else
                    sub.style.display = 'none';
            }
        } else { // get subpages for the first time.
            var sub=document.createElement('div');

            var href= self.location + "";
            var qp=href.indexOf("?") != -1 ? '&':'?';
            href=self.location + qp + 'action=pagelist/ajax&subdir=1';

            var form=HTTPGet(href);
            sub.innerHTML=form;
            subindex.appendChild(sub);

            if (typeof Effect != 'undefined') { // prototype.js
                sub.setAttribute('style','display:none');
	        new Effect.SlideDown(sub, { duration: 0.4,afterFinish: function() {Element.show(sub);} });
            } else if (typeof Fx.Slide != 'undefined') { // get sectpages for the first time.
                var mySlide = new Fx.Slide(sub);
                //mySlide.wrapper.setStyle('height',0);

                icon.addEvent('click',function(e) {
                    e = new Event(e);
                    mySlide.toggle();
                    e.stop();
                });
                mySlide.slideIn();
            }
            toggle=true;
        }
        if (icon) {
            var name=icon.getAttribute('class');
            if (name != 'close')
                icon.setAttribute('class','close');
            else
                icon.setAttribute('class','');
        }
    }
}

// vim:et:sts=4:sw:
