function toggleSubIndex(id)
{
    var subindex;
    if (typeof id == 'object') subindex=id;
    else subindex=document.getElementById(id);
    if (!subindex) return;
    var mode='';

    if (subindex) {
        var sub=subindex.getElementsByTagName('div')[0];
        if (sub) {
            mode= sub.getAttribute('style');
            if (mode) {
	        new Effect.SlideDown(sub, { duration: 0.3, afterFinish: function() {Element.show(sub);} });
            } else {
	        new Effect.SlideUp(sub, { duration: 0.3, afterFinish: function() {Element.hide(sub);} });
            }
        } else { // get subpages for the first time.
            var sub=document.createElement('div');

            var href= self.location + "";
            var qp=href.indexOf("?") != -1 ? '&':'?';
            href=self.location + qp + 'action=pagelist/ajax&subdir=1';

            var form=HTTPGet(href);
            sub.innerHTML=form;
            sub.setAttribute('style','display:none');
            subindex.appendChild(sub);

	    new Effect.SlideDown(sub, { duration: 0.4,afterFinish: function() {Element.show(sub);} });
            mode=true;
        }
        var icon=subindex.getElementsByTagName('legend')[0];
        if (icon) {
            if (mode)
                icon.setAttribute('class','close');
            else
                icon.setAttribute('class','');
        }
    }
}

// vim:et:sts=4:sw:
