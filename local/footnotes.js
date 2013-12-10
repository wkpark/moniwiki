/* MediaWiki like footnote notification by wkpark at gmail.com 2013/09/02
 * FootNotes.js */
(function() {

function init_footnotes()
{
    var note = document.getElementById('float-footnote');
    if (!note) {
        note = document.createElement('div');
        note.setAttribute('id', 'float-footnote');
        var btn = document.createElement('button');
        note.appendChild(btn);
        var desc = document.createElement('div');
        note.appendChild(desc);
        btn.onclick = function() { note.style.display = 'none' };
        note.style.display = 'none';
        window.onscroll = function() { note.style.display = 'none'; };

        document.body.appendChild(note);
    }

    var foots = document.getElementsByTagName('tt');
    for (var i = 0; i < foots.length; i++) {
        var foot = foots[i];
        var tag = foot.firstChild;
        if (tag && tag.tagName == 'A' && tag.id && tag.id.match(/^rfn(\d+)/)) {
            var footnote = document.getElementById(tag.id.substring(1));
            if (footnote) {
                tag.onclick = (function(obj) {
                    return function() {
                        note.style.display = 'block';
                        note.firstChild.nextSibling.innerHTML = obj.innerHTML;
                        return false;
                    };
                })(footnote);
            }
        }
    }
}

// onload
var oldOnload = window.onload;
window.onload = function(ev) {
    try { oldOnload(); } catch(e) {};
    init_footnotes();
}
})();
// vim:et:sts=4:sw=4:
