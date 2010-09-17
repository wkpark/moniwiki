/**
 * Postioning and Scrolling in the TextArea for MoniWiki
 *
 * Try to get the line-no of the raw wikitext from rendered html and
 * set the position of the caret and scrollbar in the TextArea
 *
 * http://wiki.sheep.art.pl/Textarea%20Scrolling
 * http://moinmo.in/FeatureRequests/AutoScrollingTheEditorTextArea
 * http://master19.moinmo.in/HelpOnEditing#Open_Editor_On_Double_Click
 *
 * @author wkpark at kldp.org
 * @since  2010/09/16
 *
 * $Id$
 */

/**
 * try to find line-anchor to get the line number of wikitext from html
 *
 */
function get_src_line(e) {
    e = e || window.event;

    var node = e.target || e.srcElement;
    if (node.nodeType == 3) {
        alert(node.nodeValue);
        if (node.nextSibling) {
            node = node.nextSibling;
        } else
            node = node.parentNode;
    }

    if (!node || node.tagName.toLowerCase() == 'textarea') return null;

    // try to find the line-no of the nextsibling
    var ns = node;
    while (ns) {
        if (ns.id && ns.id.match(/line-\d+/)) break;

        // try to find the line-no of the childs
        var nc = ns.firstChild;
        while (nc) {
            if (nc.id && nc.id.match(/line-\d+/)) break;
            nc = nc.nextSibling;
        }
        if (nc) {
            ns = nc;
            break;
        }
        ns = ns.nextSibling;
    }
    if (ns) node = ns;

    // try to find the line-no of the parent node
    var np = node;
    while (np) {
        if (np.id && np.id.match(/line-\d+/)) break;
        np = np.parentNode;
    }
    if (np) node = np;

    // is it found ?
    var no = null;
    if (node) {
        var p = 1 + node.id.indexOf('line-');
        if (p) no = node.id.substr(p + 4);
    }
    // silently ignore.
    if (!no || !no.match(/\d+/)) return null;

    return no;
}

function get_selected_text() {
    var sel = window.getSelection ? window.getSelection():
        (document.getSelection ? document.getSelection():
        document.selection.createRange().text);

    return sel.toString();
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

    function edithandler(e) {
        e = e || window.event;
        var no = get_src_line(e);
        if (!no) return false;

        var txtarea = document.getElementById('editor-textarea');
        if (txtarea) {
            var ret = focusEditor(e, txtarea, no);
            if (ret && e) {
                if (e.stopPropagation) e.stopPropagation(); 
                e.cancelBubble = true;
            }
            return ret;
        }
        var loc = location + '';
        if (p = loc.indexOf('action=')) {
            loc = loc.substring(0, p - 1);
        } else if (p = loc.indexOf('#')) {
            loc = loc.substring(0, p);
        }
        if (p)
            location = loc + _ap + 'action=edit#' + no;
        return true;
    }

    function focusEditor(e, txtarea, lineno) {
        e = e || window.event;
        if (txtarea == undefined) {
            txtarea = document.getElementById('editor-textarea');
            if (!txtarea) return false;
            txtarea.focus();
        }

        var no = null;
        if (lineno == undefined) {
            // get lineno from location
            var loc = location + '';
            var p = 1 + loc.indexOf('#');
            if (p) no = loc.substr(p);
        } else {
            no = lineno;
        }
        if (!no || !no.match(/\d+/)) return false;

        var txt = txtarea.value.replace(/\r/g, ''); // remove \r for IE
        var pos = 1; // ViTA trick.
        var startPos = 0, endPos = 0;

        // find selected line or words
        var n = no;
        while (pos && --n) pos = 1 + txt.indexOf("\n", pos);
        startPos = (pos) ? pos : 0;
        endPos = txt.indexOf("\n", startPos);

        // get selected text
        var myText = get_selected_text();

        if (myText != '') {
            var str = txt.substring(startPos, endPos);

            var p = 1 + str.indexOf(myText);
            if (p) {
                startPos+= p - 1;
                endPos = startPos + myText.length;
            }
        }

        window.scroll(0, 0);
        if (txtarea.selectionStart || txtarea.selectionStart == '0') {
            // Mozilla
            txtarea.focus();
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

        return true;
    }

    // onload
    var oldOnload = window.onload;
    window.onload = function(ev) {
        try { oldOnload(); } catch(e) {};
        focusEditor(ev);
    }

    var old_dblclick = document.ondblclick;
    document.ondblclick = function(ev) {
        try { old_dblclick(); } catch(e) {};
        edithandler(ev);
    }
})();

// vim:et:sts=4:sw=4:
