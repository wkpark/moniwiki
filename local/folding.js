/*
 * Builti-in section folding for MoniWiki
 * 
 */

(function() {
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
            icon.src = _url_prefix + '/imgs/misc/open.png';
            btn.insertBefore(icon, btn.firstChild);
            //btn.appendChild(icon);
        }
    }

    if (sect.style.display == 'none') {
        sect.style.display = 'block';
    } else {
        sect.style.display = 'none';
    }
    if (icon) {
        var name=icon.getAttribute('class');
        if (name == 'close') {
            icon.src = _url_prefix + '/imgs/misc/close.png';
            icon.setAttribute('class','');
        } else {
            icon.src = _url_prefix + '/imgs/misc/open.png';
            icon.setAttribute('class','close');
        }
    }
}

function hideSections(hide) {
    var content = document.getElementById('wikiContent');
    if (!content) return;
    var sects = content.getElementsByClassName('section');
    var is_mobile = /iphone|android/i.test(navigator.userAgent);

    if (typeof hide == 'undefined') {
        if (is_mobile) hide = 'none';
        else hide = '';
    }

    for (var i = 0; i < sects.length; i++) {
        var head;
        for (head = sects[i].firstChild; head; head = head.nextSibling) {
            if (head.nodeType != 3 && head.tagName.match(/^H/)) break;
        }
        if (head == null) continue; // ignore
        var div;
        for (div = head.nextSibling; div; div = div.nextSibling) {
            if (div.nodeType != 3 && div.tagName == 'DIV') break;
        }

        if (div.childNodes.length == 0) continue;
        if (typeof div.firstChild == 'undefined') continue;
        if (div.childNodes.length == 1 && div.firstChild.nodeType == 3)
            continue;

        head.onclick = (function(obj, id) { return function() { foldingSection(obj, id); }; })(head, div);
        var icon = new Image();
        if (hide != '') {
            icon.src = _url_prefix + '/imgs/misc/open.png';
            icon.setAttribute('class', 'close');
        } else {
            icon.src = _url_prefix + '/imgs/misc/close.png';
        }
        if (is_mobile)
            head.appendChild(icon);
        else
            head.insertBefore(icon, head.firstChild);

        div.style.display = hide;
    }
}

// onload
var oldOnload = window.onload;
window.onload = function(ev) {
    try { oldOnload(); } catch(e) {};
    hideSections();
}
})();
// vim:et:sts=4:sw=4:
