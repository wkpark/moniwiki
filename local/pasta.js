/**
 * Postioning and Scrolling in the TextArea(PaSTA) for MoniWiki
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
 * @modified 2015/05/11
 */

/**
 * try to find line-anchor to get the line number of wikitext from html
 *
 */
function get_src_line_num(e) {
    var node;
    e = e || window.event;

    // get double clicked target node
    if (e && e != true) {
        node = e.target || e.srcElement;
        if (node.nodeType == 3) {
            if (node.nextSibling) {
                node = node.nextSibling;
            } else
                node = node.parentNode;
        }
    } else {
        // get selected target node
        var sel = window.getSelection ? window.getSelection():
            (document.getSelection ? document.getSelection():
            document.selection.createRange());

        if (sel.focusNode) {
            node = sel.focusNode;
            if (node.nodeType == 3) {
                if (node.nextSibling) {
                    node = node.nextSibling;
                } else
                    node = node.parentNode;
            }
        }
    }

    if (!node || node.nodeType == node.DOCUMENT_NODE) {
        // no node found
        // check editor-textarea
        node = document.getElementById('editor-textarea');
    }

    if (!node)
        return null;

    if (node.tagName == 'TEXTAREA') {
        // check textarea
        var start = -1;
        if (node.selectionStart || node.selectionStart == '0') {
            start = node.selectionStart;
        } else if (document.selection) {
            var range = document.selection.createRange();
            if (range != null) {
                var dup = range.duplicate();
                dup.moveToElementText(node);
                dup.setEndPoint('EndToStart', range);
                start = dup.text.length;
            }
        }

        if (start >= 0) {
            if (!window.opera)
                var txt = node.value.replace(/\r/g, ''); // remove \r for IE
            else
                var txt = node.value;

            // find selected line
            var pos = 0;
            var n = 1;
            do {
                pos = 1 + txt.indexOf("\n", pos);
            } while (pos && start >= pos && ++n);
            return n + '';
        }
        return null;
    }

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

function PaSTA() {}

PaSTA.prototype = {
    // from http://wiki.sheep.art.pl/Textarea%20Scrolling
    // with some fixes by wkpark at kldp.org
    scrollTo: function(textarea, text, offset) {
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
    },

    edithandler: function(e) {
        e = e || window.event;
        var no = get_src_line_num(e);
        var txtarea = document.getElementById('editor-textarea');
        if (!no) {
            // get already selected line number
            if (this.no)
                no = this.no;
            else
                return true;
        }

        if (!txtarea && no) {
            // fixup href
            var node = e.target || e.srcElement;
            // webkit bug ?
            while (node.tagName != 'A' && node.nodeType != node.DOCUMENT_NODE)
                node = node.parentNode;

            var href = node.getAttribute('href');
            if (href) {
                var pos = href.lastIndexOf('#');
                if (pos > 0)
                    href = href.substring(0, pos);
                node.setAttribute('href', href + '#' + no);
            }
        }

        if (txtarea && no) {
            var ret = this.focusEditor(e, txtarea, no);
            if (ret && e) {
                if (e.stopPropagation) e.stopPropagation(); 
                e.cancelBubble = true;
                // reset this.no
                this.no = null;
            }
            return false;
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
    },

    // save selected line number
    mousehandler: function(e) {
        e = e || window.event;
        var no = get_src_line_num(e);
        if (no)
            this.no = no;
        return true;
    },

    _get_selected_text: function() {
        var sel = window.getSelection ? window.getSelection():
            (document.getSelection ? document.getSelection():
            document.selection.createRange().text);

        return sel.toString();
    },

    focusEditor: function(e, txtarea, lineno) {
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

        if (!window.opera)
            var txt = txtarea.value.replace(/\r/g, ''); // remove \r for IE
        else
            var txt = txtarea.value;

        var pos = 1; // ViTA trick.
        var startPos = 0, endPos = 0;

        // find selected line or words
        var n = no;
        while (pos && --n) pos = 1 + txt.indexOf("\n", pos);
        startPos = (pos) ? pos : 0;
        endPos = txt.indexOf("\n", startPos);

        // get selected text
        var myText = this._get_selected_text();

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

            var scroll = this.scrollTo(txtarea, txt.substr(0, startPos), 50);
            txtarea.scrollTop = scroll;
        } else if (document.selection) {
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
        if (txtarea.createTextRange && document.selection)
            txtarea.caretPos = document.selection.createRange().duplicate();

        return true;
    }
};

(function() {
    // onload
    var oldOnload = window.onload;
    var pasta = new PaSTA();
    window.pasta = pasta;
    window.onload = function(ev) {
        try { oldOnload(); } catch(e) {};
        pasta.focusEditor(ev);

        var icons = document.getElementById('wikiIcon');
        if (icons) {
            var els = icons.getElementsByTagName('a');
            for (var i = 0; i < els.length; i++) {
                if (els[i].getAttribute('href').indexOf('action=edit') > 0) {
                    els[i].onclick = function(e) { return pasta.edithandler(e); };
                    break;
                }
            }
        }

        var editform = document.getElementById('editform');
        if (editform) {
            var els = editform.getElementsByTagName('input');
            for (var i = 0; i < els.length; i++) {
                if (els[i].getAttribute('name') == 'button_preview') {
                    els[i].onclick = function(e) {
                        var no = get_src_line_num(e);
                        if (!no)
                            no = pasta.no;

                        if (no) {
                            var action = editform.getAttribute('action');
                            var pos = action.lastIndexOf('#');
                            if (pos > 0)
                                action = action.substring(0, pos);
                            editform.setAttribute('action', action + '#' + no);
                        }

                        return true;
                    };
                    break;
                }
            }
        }
    }

    var set_lineno = function(e) {
        return window.pasta.mousehandler(e);
    };

    // set selected line number
    if (window.addEventListener) window.addEventListener("mouseup", set_lineno, false);
    else if (window.attachEvent) window.attachEvent("onmouseup", set_lineno);
    // for mobile devices
    document.onselectionchange = set_lineno;

/*
    // double click handler
    var old_dblclick = document.ondblclick;
    document.ondblclick = function(ev) {
        try { old_dblclick(); } catch(e) {};
        var pasta = new PaSTA();
        pasta.edithandler(ev);
    }
*/
})();

// vim:et:sts=4:sw=4:
