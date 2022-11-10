/**
 * Support subpage index for MoniWiki with prototype.js or jquery
 *
 * @author  wkpark at kldp.org
 * @since   2007/10/6
 * @license GPLv2
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
            } else {
                if (toggle) {
                    $(sub).slideDown();
                } else {
                    $(sub).slideUp();
                }
            }
        } else { // get subpages for the first time.
            var sub=document.createElement('div');
            sub.setAttribute('style','display:none');

            var href= self.location + "";
            var qp=href.indexOf("?") != -1 ? '&':'?';
            href=self.location + qp + 'action=pagelist/ajax&subdir=1';

            function pagelist(form) {
            sub.innerHTML=form;
            subindex.appendChild(sub);

            if (typeof Effect != 'undefined') { // prototype.js
                new Effect.SlideDown(sub, { duration: 0.4,afterFinish: function() {Element.show(sub);} });
            } else { // jquery
                $(sub).slideDown();
            }
            toggle=true;
            }

            if (typeof fetch == 'function') {
                fetch(href, { method: 'GET' })
                    .then(function(res) { return res.text(); })
                    .then(pagelist);
            } else {
                HTTPGet(href, pagelist);
            }
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
