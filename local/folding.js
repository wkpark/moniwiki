/**
 * section folding script for MoniWiki
 *
 * @author   Won-Kyu Park <wkpark at gmail.com>
 * @since    2007/10/30
 * @modified 2015/11/30
 * @license  GPLv2
 * @depends  jQuery
 */

(function() {
function foldingSection(head, id)
{
    var sect;
    if (typeof id == 'object') sect = id;
    else sect = document.getElementById(id);
    if (!sect) return;

    if ($(head).hasClass("closed")) {
        $(head).removeClass("closed");
        $(head).addClass("opened");
        $(sect).removeClass("closed");
    } else {
        $(head).removeClass("opened");
        $(head).addClass("closed");
        $(sect).addClass("closed");
    }
}

function attachFolding() {
    var sects = $(".section.styling");

    for (var i = 0; i < sects.length; i++) {
        var head;
        // try to find first heading
        for (head = sects[i].firstChild; head; head = head.nextSibling) {
            if (head.nodeType != 3 && head.tagName.match(/^H/)) break;
        }
        if (head == null) continue; // ignore
        var div;
        // try to find first div
        for (div = head.nextSibling; div; div = div.nextSibling) {
            if (div.nodeType != 3 && div.tagName == 'DIV') break;
        }

        if (div.childNodes.length == 0) continue;
        if (typeof div.firstChild == 'undefined') continue;
        if (div.childNodes.length == 1 && div.firstChild.nodeType == 3)
            continue;

        head.onclick = (function(id) { return function() { foldingSection(this,id); }; })(div);
    }
}

// onload
if (window.addEventListener) window.addEventListener("load",attachFolding,false);
else if (window.attachEvent) window.attachEvent("onload",attachFolding);
})();
// vim:et:sts=4:sw=4:
