/*
 * Builti-in section folding for MoniWiki
 * 
 */

(function() {

// Add a getElementsByClassName function if the browser doesn't have one
// Limitation: only works with one class name
// Copyright: Eike Send http://eike.se/nd
// License: MIT License

if (!document.getElementsByClassName) {
    getElementsByClassName = function(search) {
        var d = document, elements, pattern, i, results = [];
        if (d.querySelectorAll) { // IE8
            return d.querySelectorAll("." + search);
        }
        if (d.evaluate) { // IE6, IE7
            pattern = ".//*[contains(concat(' ', @class, ' '), ' " + search + " ')]";
            elements = d.evaluate(pattern, d, null, 0, null);
            while ((i = elements.iterateNext())) {
                results.push(i);
            }
        } else {
            elements = d.getElementsByTagName("*");
            pattern = new RegExp("(^|\\s)" + search + "(\\s|$)");
            for (i = 0; i < elements.length; i++) {
                if ( pattern.test(elements[i].className) ) {
                    results.push(elements[i]);
                }
            }
        }
        return results;
    }
} else {
    getElementsByClassName = function(q) { return document.getElementsByClassName(q) };
}

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
        icon=btn.getElementsByTagName('i')[0];
        if (!icon) {
            icon = document.createElement('i');
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
        if (name == 'icon-close') {
            icon.setAttribute('class','icon-open');
        } else {
            icon.setAttribute('class','icon-close');
        }
    }
}

function hideSections() {
    var content = document.getElementById('wikiContent');
    if (!content) return;
    var sects = getElementsByClassName('section');
    var is_mobile = /iphone|android/i.test(navigator.userAgent);

    if (is_mobile) hide = 'none';
    else hide = '';

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
        var icon = document.createElement('i');
        if (hide != '') {
            icon.setAttribute('class', 'icon-open');
        } else {
            icon.setAttribute('class', 'icon-close');
        }
        if (is_mobile)
            head.appendChild(icon);
        else
            head.insertBefore(icon, head.firstChild);

        div.style.display = hide;
    }
}

// onload
if (window.addEventListener) window.addEventListener("load",hideSections,false);
else if (window.attachEvent) window.attachEvent("onload",hideSections);
})();
// vim:et:sts=4:sw=4:
