/**
 * try to get the line-anchor to select the right position of the wikitext
 *
 * @author wkpark at kldp.org
 * @since  2010/09/16
 *
 * $Id$
 */

/**
 * try to find line-anchor to get the line number of wikitext
 *
 */
function edithandler(ev) {
    var e = ev ? ev : window.event; // for IE
    var sel = window.getSelection ? window.getSelection():
        (document.getSelection ? document.getSelection():
        document.selection.createRange());

    var node;
    if (sel.focusNode) {
        node = sel.focusNode;

        if (node.nodeType == 3)
            node = node.parentNode;
    } else {
        // IE6
        node = sel.parentElement();
    }

    var nc = node;
    
    if (!node.id || !node.id.match(/^line-/)) {
        // try to find line-anchor
        while(node) {
            nc = node.firstChild;

            while (nc) {
                var name = nc.tagName + '';
                if (name == 'SPAN' && nc.id.match(/^line-/)) break;
                if (name.match(/^H/) && nc.id.match(/^line-/)) break;
                nc = nc.nextSibling;
            }
            if (nc)
                break;
            node = node.nextSibling;
        }
    }

    // is it found ?
    if (nc) {
        var loc = location + '';
        var p;
        if (p = loc.indexOf('action=')) {
            loc = loc.substring(0, p - 1);
        } else if (p = loc.indexOf('#')) {
            loc = loc.substring(0, p);
        }
        location = loc + _ap + 'action=edit#' + nc.id;
    }

    // silently ignore.
    return false;
}

(function() {
    // from http://wiki.sheep.art.pl/Textarea%20Scrolling
    // with some fixes by wkpark at kldp.org
    function scrollTo(textarea, text, offset) {
        var style;
        try { style = window.getComputedStyle(textarea, ''); }
        catch(e) { return false; };

        // Calculate how far to scroll, by putting the text that is to be
        // above the fold in a DIV, and checking the DIV's height.
        var pre = document.createElement('pre');
        textarea.parentNode.appendChild(pre);

        pre.style.lineHeight = style.lineHeight;
        pre.style.fontFamily = style.fontFamily;
        pre.style.fontSize = style.fontSize;
        pre.style.padding = pre.style.padding;
        pre.style.margin = pre.style.margin;
        pre.style.letterSpacing = style.letterSpacing;
        pre.style.border = style.border;
        pre.style.outline = style.outline;
        pre.style.overflow = 'scroll-y';
        pre.style.height = 0; // set height to suppress flickering
        try { pre.style.whiteSpace = "-moz-pre-wrap" } catch(e) {};
        try { pre.style.whiteSpace = "-o-pre-wrap" } catch(e) {};
        try { pre.style.whiteSpace = "-pre-wrap" } catch(e) {};
        try { pre.style.whiteSpace = "pre-wrap" } catch(e) {};

        pre.textContent = text; // put your text here
        var scroll = pre.scrollHeight + 0;
        pre.textContent = "";
        if (scroll > offset) scroll -= offset;
        textarea.parentNode.removeChild(pre); // remove
        return scroll;
    }

    function focusEditor() {
        var txtarea = document.getElementById('wikicontent');
        if (txtarea) {
            txtarea.focus();

            var loc = location + '';
            var p = loc.indexOf('#line-', 1);
            var no = 0;
            if (p > 0)
                var no = loc.substr(p + 6);
            if (!no)
                return;

            var txt = txtarea.value.replace(/\r/g, ''); // remove \r for IE
            var pos = 1; // ViTA trick.
            var startPos = 0, endPos = 0;

            // find selected line
            var n = no;
            while (pos && --n) pos = 1 + txt.indexOf("\n", pos);
            startPos = (pos) ? pos : 0;
            // FIXME ? how can I select only selected words ?
            endPos = txt.indexOf("\n", startPos);

            if (txtarea.selectionStart || txtarea.selectionStart == '0') {
                // Mozilla
                var scrollTop = txtarea.scrollTop; // save

                // goto
                txtarea.selectionStart = startPos;
                txtarea.selectionEnd = endPos;

                var scroll = scrollTo(txtarea, txt.substr(0, startPos), 50);
                txtarea.scrollTop = scroll;
            } else if (document.selection && !is_gecko && !is_opera) {
                // IE
                txtarea.focus();
                var r = document.selection.createRange();
                var range = r.duplicate();

                range.moveStart('character', startPos);
                range.moveEnd('character', endPos - startPos);
                r.setEndPoint('StartToStart', range);
                range.select();
            }

        // reposition cursor if possible
        if (txtarea.createTextRange)
                txtarea.caretPos = document.selection.createRange().duplicate();
        }
    }

    // onload
    var oldOnload = window.onload;
    if (typeof window.onload != 'function') {
        window.onload = focusEditor;
    } else {
        window.onload = function() {
            oldOnload();
            focusEditor();
        }
    }
})();

// vim:et:sts=4:sw=4:
