//
// Wikiwyg for MoniWiki by wkpark at kldp.org 2006/01/27
//
// $Id$
//
//_url_prefix="/wiki";

Wikiwyg.browserIsSupported = (
    Wikiwyg.is_gecko ||
    Wikiwyg.is_ie ||
    Wikiwyg.is_opera
);

Wikiwyg.prototype.saveChanges = function() {
    var self = this;
    var myWikiwyg = new Wikiwyg.Wikitext();
    var wikitext;

    this.current_mode.toHtml( function(html) { self.fromHtml(html) });

    if (this.current_mode.classname.match(/(Wysiwyg|HTML|Preview)/)) {
        this.current_mode.fromHtml(this.div.innerHTML);

        wikitext = myWikiwyg.convert_html_to_wikitext(this.div.innerHTML);
    }
    else {
        wikitext = this.current_mode.textarea.value;
    }

    var datestamp='';
    var section=null;
    for (var i=0;i<this.myinput.length;i++) {
        if (this.myinput[i].name == 'datestamp')
            datestamp=this.myinput[i].value;
        if (this.myinput[i].name == 'section')
        section=this.myinput[i].value;
    }
    //alert(datestamp+'/'+section);

    myWikiwyg.convertWikitextToHtmlAll(wikitext,
        function(new_html) { self.div.innerHTML = new_html });

    // save
    var toSend = 'action=savepage/ajax' +
    '&savetext=' + encodeURIComponent(wikitext) +
    '&datestamp=' + datestamp;

    if (section)
        toSend += '&section=' + section;
    var location = this.mylocation;

    var saved=self.div.innerHTML;
    self.div.innerHTML='<img src="'+_url_prefix+'/imgs/loading.gif" />';
    var form=HTTPPost(location,toSend);
    if (form.substring(0,4) == 'true') {
        // get section
        var toSend = 'action=markup&all=1';
        if (section)
            toSend += '&section=' + section;
        form=HTTPPost(location,toSend);
        self.div.innerHTML=form;

        this.displayMode();
        return;
    } else {
        self.div.innerHTML=saved;
        var f=document.createElement('div');
        f.setAttribute('class','errorLog');
        // show error XXX
        f.innerHTML=form;
        self.parentNode.appendChild(f);
    }
    return;
}

Wikiwyg.prototype.editMode = function(form,text) {
    var self = this;
    var dom = document.createElement('div');
    dom.innerHTML = form;

    var form = dom.getElementsByTagName('form')[0];
    var mytext = dom.getElementsByTagName('textarea')[0];
    var wikitext = text == null ? mytext.value:text;
    this.mylocation = form.getAttribute('action');

    this.current_mode = this.first_mode;
    if (this.current_mode.classname.match(/(Wysiwyg|HTML|Preview)/)) {
        var myWikiwyg = new Wikiwyg.Wikitext();

        myWikiwyg.convertWikitextToHtml(wikitext,
            function(new_html) { self.current_mode.fromHtml(new_html); });
    }
    else {
        this.current_mode.textarea.value = wikitext;
    }

    this.toolbarObject.resetModeSelector();
    this.current_mode.enableThis();
    this.current_mode.enableThis(); // hack !!
    this.myinput=dom.getElementsByTagName('input');
}

proto = Wikiwyg.Wysiwyg.prototype;

proto.get_edit_iframe = function() {
    var iframe;
    if (this.config.iframeId) {
        iframe = document.getElementById(this.config.iframeId);
        iframe.iframe_hack = true;
    }
    else if (this.config.iframeObject) {
        iframe = this.config.iframeObject;
        iframe.iframe_hack = true;
    }
    else {
        // XXX iframe need to be a element of the body.
        iframe = document.createElement('iframe');
        var body = document.getElementsByTagName('body')[0];;
        body.appendChild(iframe);
    }
    return iframe;
}

proto.apply_inline_stylesheet = function(style, head) {
    var style_string = "";
    for ( var i = 0 ; i < style.cssRules.length ; i++ ) {
        if ( style.cssRules[i].type == 3 ) {
            // IMPORT_RULE

            /* It's pretty strange that this doesnt work.
               That's why Ajax.get() is used to retrive the css text.

            this.apply_linked_stylesheet({
                href: style.cssRules[i].href,
                type: 'text/css'
            }, head);
            */

            style_string += HTTPGet(style.cssRules[i].href);
        } else {
            style_string += style.cssRules[i].cssText + "\n";
        }
    }
    if (style_string.length > 0) {
        style_string += "\nbody { padding: 5px; }\n";
        this.append_inline_style_element(style_string, head);
    }
}

proto.enableThis = function() {
    Wikiwyg.Mode.prototype.enableThis.call(this);
    this.edit_iframe.style.border = '1px solid ActiveBorder';
    //this.edit_iframe.style.backgroundColor = '#ffffff';
    //this.edit_iframe.setAttribute('style','1px solid ThreeDFace;background:#fff;');
    this.edit_iframe.width = '99%';
    this.setHeightOf(this.edit_iframe);
    this.get_edit_document().designMode = 'on';
    this.enable_keybindings();
    // XXX - Doing stylesheets in initializeObject might get rid of blue flash
    //
    var doc    = this.get_edit_document();
    var head   = doc.getElementsByTagName("head")[0];
    if (head != null) {
        var styles = doc.styleSheets;
        if (styles.length == 0) {
            this.apply_stylesheets();
            var link = doc.createElement('link');
            link.setAttribute('rel', 'STYLESHEET');
            link.setAttribute('type', 'text/css');
            link.setAttribute('media', 'screen');
            var loc = location.protocol + '//' + location.host;
            if (location.port) loc += ':' + location.port;
            link.setAttribute('href',
                loc + _url_prefix + '/local/Wikiwyg/css/wysiwyg.css');
            head.appendChild(link);
        }
        this.fix_up_relative_imgs();
        this.clear_inner_html();
    }
}

proto.do_link = function() {
    var selection = this.get_link_selection_text();
    if (! selection) return;
    var url;
    var match = selection.match(/(.*?)\b((?:http|https|ftp|irc):\/\/\S+)(.*)/);
    if (match) {
        if (match[1] || match[3]) return null;
        url = match[2];
    }
    else {
        url = '/' + escape(selection); 
    }
    this.exec_command('createlink', url);
}

proto.do_image = function() {
    var base = location.href.replace(/(.*?:\/\/.*?\/).*/, '$1');

    var x=window.open("?action=uploadedfiles&tag=1&popup=1","MyWin",'toolbar=no,width=800,height=500,scrollbars=yes');
    if (x!=null) {
        x.focus();
    }
}

proto.do_media = proto.do_image;

proto = Wikiwyg.Wikitext.prototype;

proto.enableThis = function() {
    Wikiwyg.Mode.prototype.enableThis.call(this);
    this.textarea.style.width = '99%';
    this.setHeightOfEditor();
    this.enable_keybindings();
}

proto.convert_html_to_wikitext = function(html) {
    this.copyhtml = html;
    var dom = document.createElement('div');
    html = html.replace(/<!-=-/g, '<!--').
                replace(/-=->/g, '-->');

    // Opera note: opera internally use upper case tag names.
    //  e.g.) <A class=..></A> <IMG src=..
    // IE note: IE does not quote some attributes, class,title,etc.
    //
    // for MoniWiki
    // remove perma icons
    html = html.replace(/<a class=.?perma.?.*\/a>/g, '');
    // interwiki links
    // remove interwiki icons
    html =
        html.replace(/<a class=.?interwiki.?[^>]+><img [^>]+><\/a><a [^>]*title=(\'|\")?([^\'\" ]+)\1?[^>]*>[^<]+<\/a>/ig, "$2");
    html =
        html.replace(/<img class=.?(url|externalLink).?[^>]+>/ig, '');
    // smiley/inline tex etc.
    html =
        html.replace(/<img [^>]*class=.?(tex|interwiki|smiley).?[^>]* alt=(\'|\")?([^\'\" ]+)\2?[^>]+>/ig, "$3");
    // interwiki links
    html =
        html.replace(/<a [^>]*alt=(.)?([^\'\"]+)\1?[^>]*>/igm, "$2");
    // remove nonexists links
    html = html.replace(/<a class=.?nonexistent.?[^>]+>([^<]+)<\/a>/igm, "$1");

    // remove toc number
    html = html.replace(/<span class=.?tocnumber.?>(.*)<\/span>/igm, '');

    // remove javatag
    html =
        html.replace(/<a href=.javascript:[^>]+>(.*)<\/a>/ig, "$1");
    // remove all links XXX
    html =
        html.replace(/<a [^>]+>([^>]+)<\/a>/ig, "$1");

    // escaped wiki markup blocks
    html =
        html.replace(/<tt class[^>]+>([^>]+)<\/tt>/ig, "{{{$1}}}");

    dom.innerHTML = html;
    this.output = [];
    this.list_type = [];
    this.indent_level = 0;

    this.walk(dom);

    // add final whitespace
    this.assert_new_line();

    return this.join_output(this.output);
}

proto.format_img = function(element) {
    var uri='';
    uri = element.getAttribute('src');
    if (uri) {
        var style = element.getAttribute('style');
        var width = element.getAttribute('width');
        var height = element.getAttribute('height');
        var myclass = element.getAttribute('class');

        this.assert_space_or_newline();
        this.appendOutput(uri);
        var attr='';
        if (width) attr+='width='+width;
        if (height) attr+=(attr ? '&':'') + 'height='+height;

        if (style) {
            var m = style.match(/width:\s*(\d+)px;\s*height:\s*(\d+)px/);
            if (m) {
                if (m[1]) attr+=(attr ? '&':'') + 'width='+m[1];
                if (m[2]) attr+=(attr ? '&':'') + 'height='+m[2];
            }
        }

        if (myclass) {
            var m = myclass.match(/img(Center|Left|Right)$/);
            if (m[1]) attr+=(attr ? '&':'') + 'align='+m[1].toLowerCase();
        }

        if (attr) this.appendOutput('?'+attr);
    }
}


proto.format_table = function(element) {
    this.assert_blank_line();
    var style =element.getAttribute('style');
    var width =element.getAttribute('width');
    this.myattr=null;

    if (width) {
        this.myattr= '<tablewidth="'+width + 'px">';
    } else 
    if (style) {
        var attr='';
        var m = style.match(/width:\s*(\d+)px;\s*height:\s*(\d+)px/);
        if (m)
            attr='<tablewidth="'+m[1] + 'px" height="'+m[2]+'px">';
        
        if (attr != '') this.myattr=attr;
    }
    this.walk(element);
    this.assert_blank_line();
}

proto.format_tr = function(element) {
    this.walk(element);
    this.appendOutput('||');
    this.insert_new_line();
}

proto.format_br = function(element) {
    var str1 = this.output[this.output.length - 1];
    if (str1 && ! str1.whitespace && ! str1.match(/\n$/)) {
        this.insert_new_line();
        this.insert_new_line(); // two \n\n is rendered as <br />
    } else {
        this.insert_new_line(); // \n\n\n is rendered as <br /><br />
    }
}

proto.assert_blank_line = function() {
    if (! this.should_whitespace()) return;
    this.chomp();
    this.insert_new_line();
    //this.insert_new_line(); // FIX for line_alone (----)
}

proto.handle_line_alone = function (element, markup) {
    this.assert_blank_line();
    this.appendOutput(markup[1]);
    this.assert_blank_line();
}

proto.format_td = function(element) {
    var colspan =element.getAttribute('colspan');
    var align =element.getAttribute('class');
    if (colspan) {
        for (var i=0;i<colspan;i++)
            this.appendOutput('||');
    } else
        this.appendOutput('||');

    if (this.myattr)
        this.appendOutput(this.myattr);
    this.myattr=null;

    var rowspan =element.getAttribute('rowspan');
    if (rowspan)
        this.appendOutput('<|'+rowspan+'>');

    //if (align) this.appendOutput('<align='+align+'>');
    this.appendOutput('');
    this.walk_n(element);
    this.chomp_n(); // table specific chomp
    this.appendOutput('');
    this.chomp_n(); // chomp again
}

proto.walk_n = function(element) {
    if (!element) return;
    for (var part = element.firstChild; part; part = part.nextSibling) {
        if (part.nodeType == 1) {
            this.dispatch_formatter(part);
        }
        else if (part.nodeType == 3) {
            if (part.nodeValue.match(/^[ ]*$/)) {
                this.appendOutput(part.nodeValue);
            }
            else if (part.nodeValue.match(/[^\n]/)) {
                if (this.no_collapse_text) {
                    this.appendOutput(part.nodeValue);
                }
                else {
                    this.appendOutput(this.collapse(part.nodeValue));
                }
            }
        }
    }
    this.no_collapse_text = false;
}

proto.chomp_n = function() {
    var string;
    while (this.output.length) {
        string = this.output.pop();
        if (typeof(string) != 'string') {
            this.appendOutput(string);
            return;
        }
        if (! string.match(/^\n+>+ $/) && string.match(/(\S|\s)/)) {
            break;
        }
    }
    if (string) {
        string = string.replace(/[\r\n]+$/, '');
        this.appendOutput(string);
    }
}

proto.format_li = function(element) {
    var level = this.list_type.length;
    if (!level) die("List error");
    var type = this.list_type[level - 1];
    var markup = this.config.markupRules[type];
    var ind = ' ';
    this.appendOutput(ind.times(level-1) + markup[1] + ' ');

    // Nasty ie hack which I don't want to talk about.
    // But I will...
    // *Sometimes* when pulling html out of the designmode iframe it has
    // <LI> elements with no matching </LI> even though the </LI>s existed
    // going in. This needs to be delved into, and we need to see if
    // quirksmode and friends can/should be set somehow on the iframe
    // document for wikiwyg. Also research whether we need an iframe at all on
    // IE. Could we just use a div with contenteditable=true?
    if (Wikiwyg.is_ie &&
        element.firstChild &&
        element.firstChild.nextSibling &&
        element.firstChild.nextSibling.nodeName.match(/^[uo]l$/i))
    {
        try {
            element.firstChild.nodeValue =
              element.firstChild.nodeValue.replace(/ $/, '');
        }
        catch(e) { }
    }

    this.walk(element);

    this.chomp();
    this.insert_new_line();
}

proto.do_indent = function() {
    this.selection_mangle(
        function(that) {
            if (that.sel == '') return false;
            that.sel = that.sel.replace(/^(\>\s)/gm, '$1$1');
            that.sel = that.sel.replace(/^/gm, ' '); // space indent
            that.sel = that.sel.replace(/^ (\>\s)/gm, '$1');
            return true;
        }
    )
}

proto.do_outdent = function() {
    this.selection_mangle(
        function(that) {
            if (that.sel == '') return false;
            that.sel = that.sel.replace(/^(\s(?=\s+(\*|\d+\.))|\s(?!\*|\d+\.)|\> )/gm, '');
            return true;
        }
    )
}

Wikiwyg.Preview.prototype.initializeObject = function() {
    if (this.config.divId)
        this.div = document.getElementById(this.config.divId);
    else
        this.div = document.createElement('div');
    // XXX Make this a config option.
    this.div.setAttribute('style','background:lightyellow;padding:10px;');
}

proto = Wikiwyg.Toolbar.prototype;

proto.addControlItem = function(text, method,arg) {
    var span = Wikiwyg.createElementWithAttrs(
        'span', { 'class': 'wikiwyg_control_link' }
    );

    var link = Wikiwyg.createElementWithAttrs(
        'input', {
            type: 'button',
            value: text
        }
    );
    //link.appendChild(document.createTextNode(text));
    span.appendChild(link);

    var self = this;
    if (arg) {
        method=method+'("'+arg+'")';
        this.controls=this.controls ? ','+arg:arg;
    } else method=method+'()';
    link.onclick = function() { eval('self.wikiwyg.' + method); return false };

    this.div.appendChild(span);
}

proto.config.controlLayout = [
    'save', 'preview', 'cancel', 'mode_selector', '/',
    'bold',
    'italic',
    'link',
    'h2',
    'ordered',
    'unordered',
    'math',
    'nowiki',
    'hr',
    'table',
    'indent', 'outdent', '|',
    'quote', '|',
    'image',
    'media'
];

proto.config.controlLabels.math = 'Math';
proto.config.controlLabels.nowiki = 'As Is';
proto.config.controlLabels.image = 'Image';
proto.config.controlLabels.media = 'Media';
proto.config.controlLabels.quote = 'Quote';

proto = Wikiwyg.Wikitext.prototype;
proto.config.markupRules.bold = ['bound_phrase', "'''", "'''"];
proto.config.markupRules.italic = ['bound_phrase', "''", "''"];
proto.config.markupRules.underline = ['bound_phrase', '__', '__'];
proto.config.markupRules.strike = ['bound_phrase', '~~', '~~'];
proto.config.markupRules.link = ['bound_phrase', '[', ']'];
proto.config.markupRules.math = ['bound_phrase', '$ ', ' $'];
proto.config.markupRules.nowiki = ['bound_phrase', '{{{', '}}}'];
proto.config.markupRules.h1 = ['bound_line', '= ', ' ='],
proto.config.markupRules.h2 = ['bound_line', '== ', ' =='],
proto.config.markupRules.h3 = ['bound_line', '=== ', ' ==='],
proto.config.markupRules.h4 = ['bound_line', '==== ', ' ===='],
proto.config.markupRules.h5 = ['bound_line', '===== ', ' ====='],
proto.config.markupRules.h6 = ['bound_line', '====== ', ' ======'],
proto.config.markupRules.ordered = ['start_lines', ' 1.'];
proto.config.markupRules.unordered = ['start_lines', ' *'];
proto.config.markupRules.indent = ['start_lines', ' '];
proto.config.markupRules.quote = ['start_lines', '>'];
proto.config.markupRules.hr = ['line_alone', '----'];
proto.config.markupRules.image = ['bound_phrase', 'attachment:', '','sample.png'];
proto.config.markupRules.media = ['bound_phrase', '[[Media(', ')]]','sample.ogg'];
proto.config.markupRules.table = ['line_alone', '|| A || B || C ||\n||   ||   ||   ||\n||   ||   ||   ||'];

proto.do_math = Wikiwyg.Wikitext.make_do('math');
proto.do_nowiki = Wikiwyg.Wikitext.make_do('nowiki');
if (Wikiwyg.Wikitext.make_format) // Wikiwyg-0.12
    proto.format_image = Wikiwyg.Wikitext.make_format('image');
else // Wikiwyg snapshot
    proto.format_image = Wikiwyg.Wikitext.make_formatter('image');
proto.do_image = Wikiwyg.Wikitext.make_do('image');
proto.do_media = Wikiwyg.Wikitext.make_do('media');
proto.do_quote = Wikiwyg.Wikitext.make_do('quote');

proto.collapse = function(string) {
    return string.replace(/\r\n|\r/g, ''); // FIX
    //return string.replace(/\r\n|\r/g, "\n"); // FIX
}

proto.get_wiki_comment = function(element) {
    for (var node = element.firstChild; node; node = node.nextSibling) {
        if (node.nodeType == this.COMMENT_NODE_TYPE
            && node.data.match(/^\s*wiki/)) {
            var ele=node.nextSibling.firstChild;
            if (ele && node.data.match(/\nattachment:/) && ele.tagName && ele.tagName.toLowerCase() == 'img') {
                // check the attributes of the attached images
                var style = ele.getAttribute('style');
                var width = ele.getAttribute('width');
                var height = ele.getAttribute('height');
                var myclass = ele.getAttribute('class');
                var align = '';

                var attr=new Array();

                if (width) attr["width"]='width='+width;
                if (height) attr["height"]='height='+height;
                ele.setAttribute('width','');
                ele.setAttribute('height','');

                if (style && style.match) {
                    var m = style.match(/width:\s*(\d+)px;\s*height:\s*(\d+)px/);
                    if (m) {
                        if (m[1]) attr["width"]='width='+m[1];
                        if (m[2]) attr["height"]='height='+m[2];
                        ele.setAttribute('style','');
                    }
                }

                if (myclass) {
                    var m = myclass.match(/img(Center|Left|Right)$/);
                    if (m[1]) {
                        attr["align"]='align='+m[1].toLowerCase();
                        align=attr["align"];
                    }
                }

                var newquery='';
                if (attr) {
                    var tattr=new Array();

                    for (var key in attr) {
                        var value = attr[key];
                        if (typeof value == 'function') continue;
                        tattr.push(value);
                    }

                    newquery=tattr.join("&");

                    var p=node.data.indexOf("?");
                    var orig='';
                    var oldquery='';
                    if (p != -1) {
                        orig=node.data.substr(0,p);
                        oldquery=node.data.substr(p+1);
                    }
                    if (oldquery) {
                        oldquery = oldquery.replace(/\n+$/,""); // strip \n
                        var oldattr=oldquery.split("&");
                        var newattr=new Array();
                        for (var j=0;j<oldattr.length;j++) {
                            var dum=oldattr[j].split("=");
                            if (!width && dum[0] == "width") {
                                newattr["width"]=oldattr[j];
                            } else if (!height && dum[0] == "height") {
                                newattr["height"]=oldattr[j];
                            } else if (!align && dum[0] == "align") {
                                newattr["align"]=oldattr[j];
                            }
                        }
                        if (newattr) {
                            var tattr=[];
                            for (var key in newattr) {
                                var value = newattr[key];
                                if (typeof value == 'function') continue;
                                tattr.push(value);
                            }
                            var old=tattr.join("&");
                            newquery=newquery ? (old+'&'+newquery):old;
                        } else {
                            newquery=oldquery+'&'+newquery;
                        }
                        node.data=orig+'?'+newquery + " \n";
                    } else {
                        node.data = node.data.replace(/\n+$/,""); // strip \n
                        node.data+='?'+newquery + " \n";
                    }

                    return node;
                }
            }
            return node;
        }
    }
    return null;
}

proto.handle_opaque_phrase = function(element) {
    var comment = this.get_wiki_comment(element);
    if (comment) {
        var text = comment.data;
        text = text.replace(/^ wiki:\s+/, '')
                   .replace(/-=/g, '-')
                   .replace(/==/g, '=')
                   .replace(/\s$/, '')
                   .replace(/\{(\w+):\s*\}/, '{$1}');
        this.appendOutput(Wikiwyg.htmlUnescape(text))
        this.smart_trailing_space(element);
    }
}

proto.walk = function(element) {
    if (!element) return;
    for (var part = element.firstChild; part; part = part.nextSibling) {
        if (part.nodeType == 1) {
            this.dispatch_formatter(part);
        }
        else if (part.nodeType == 3) {
            if (part.nodeValue.match(/\S/)) {
                var string = part.nodeValue;
                //if (! string.match(/^[\'\.\,\?\!\)]/)) {
                    //this.assert_space_or_newline(); // FIX
                    //string = this.trim(string); // FIX
                    //string = this.mytrim(string); // replace
                //}
                // XXX do not auto insert/delete white spaces!!!
                //string = this.mytrim(string); // replace
                //this.appendOutput(this.collapse(string)); // FIX
                this.appendOutput(string);
                //this.appendOutput('^'+string+'_');
            }
        }
    }
}

proto.mytrim = function(string) {
    // remove only one leading newline
    return string.replace(/^(\r\n|\n|\r)/, '');
    //return string.replace(/^(\r\n|\n|\r)+/, '');
}

// XXX - A lot of this is hardcoded.
// customize for moniwiki
proto.add_markup_lines = function(markup_start) {
    var already_set_re = new RegExp( '^' + this.clean_regexp(markup_start), 'gm');
    //var other_markup_re = /^(\^+|\=+|\*+|#+|>+|    )/gm;
    var other_markup_re = /^(\s+\*|\s+\d+\.|(\>\s)+|\=+)/gm;

    var match;
    // if paragraph, reduce everything.
    if (! markup_start.length) {
        this.sel = this.sel.replace(other_markup_re, '');
        this.sel = this.sel.replace(/^\ +/gm, '');
    }
    // if pre and not all indented, indent
    else if ((markup_start == ' ') && this.sel.match(/^\S/m))
        this.sel = this.sel.replace(/^/gm, markup_start);
    // if not requesting heading and already this style, kill this style
    else if (
        (! markup_start.match(/[\=\^]/)) &&
        this.sel.match(already_set_re)
    ) {
        this.sel = this.sel.replace(already_set_re, '');
        if (markup_start != ' ')
            this.sel = this.sel.replace(/^ */gm, '');
    }
    // if some other style, switch to new style
    else if (match = this.sel.match(other_markup_re))
        // if pre, just indent
        if (markup_start == ' ') {
            this.sel = this.sel.replace(/^/gm, markup_start);
            alert(this.sel);
        }
        // if heading, just change it
        else if (markup_start.match(/[\=\^]/)) {
            this.sel = this.sel.replace(other_markup_re, markup_start);
        }
        // else try to change based on level
        else
            this.sel = this.sel.replace(
                other_markup_re,
                function(match) {
                    //return markup_start.times(match.length);
                    var lev = 0;
                    if (match.match(/\s+\d+\./))
                       lev = match.length - 3; 
                    else if (match.match(/\s+\*/))
                       lev = match.length - 2; 
                    else if (match.match(/\>\s/))
                       lev = match.length / 2 - 1; 

                    return (' ').times(lev) + markup_start;
                }
            );
    // if something selected, use this style
    else if (this.sel.length > 0)
        this.sel = this.sel.replace(/^(.*\S+)/gm, markup_start + ' $1');
    // just add the markup
    else
        this.sel = markup_start + ' ';

    var text = this.start + this.sel + this.finish;
    var start = this.selection_start;
    var end = this.selection_start + this.sel.length;
    this.set_text_and_selection(text, start, end);
    this.area.focus();
}

proto.markup_bound_phrase = function(markup_array) {
    var markup_start = markup_array[1];
    var markup_finish = markup_array[2];
    var markup_example = markup_array[3] || null;
    var scroll_top = this.area.scrollTop;
    if (markup_finish == 'undefined')
        markup_finish = markup_start;
    var multi = null;
    if (markup_array[1].match(/\{\{\{/)) multi = 1; // fix for pre block
    if (this.get_words(multi))
        this.add_markup_words(markup_start, markup_finish, markup_example);
    this.area.scrollTop = scroll_top;
}

proto.get_words = function(multi) {
    function is_insane(selection,multi) {
        if (multi)
            return selection.match(/\r?\n(\s+\*|\s\d\.)/); //FIX
        return selection.match(/(\r?\n|\*+ |\#+ |\=+ )/);
    }   

    t = this.area; // XXX needs "var"?
    var selection_start = t.selectionStart;
    var selection_end = t.selectionEnd;

    if (selection_start == null) {
        selection_start = selection_end;
        if (selection_start == null) {
            return false
        }
        selection_start = selection_end =
            t.value.substr(0, selection_start).replace(/\r/g, '').length;
    }

    var our_text = t.value.replace(/\r/g, '');
    selection = our_text.substr(selection_start,
        selection_end - selection_start);

    selection_start = this.find_right(our_text, selection_start, /(\S|\r?\n)/);
    if (selection_start > selection_end)
        selection_start = selection_end;
    selection_end = this.find_left(our_text, selection_end, /(\S|\r?\n)/);
    if (selection_end < selection_start)
        selection_end = selection_start;

    if (is_insane(selection,multi)) {
        this.alarm_on();
        return false;
    }

    this.selection_start =
        this.find_left(our_text, selection_start, Wikiwyg.Wikitext.phrase_end_re);
    this.selection_end =
        this.find_right(our_text, selection_end, Wikiwyg.Wikitext.phrase_end_re);

    t.setSelectionRange(this.selection_start, this.selection_end);
    t.focus();

    this.start = our_text.substr(0,this.selection_start);
    this.sel = our_text.substr(this.selection_start, this.selection_end -
        this.selection_start);
    this.finish = our_text.substr(this.selection_end, our_text.length);

    return true;
}

proto.markup_is_on = function(start, finish) {
    return (this.sel.match(start) && this.sel.match(finish));
}

proto.add_markup_words = function(markup_start, markup_finish, example) {
        if (this.sel.match(/\n/)) {
            markup_start += '\n';
            markup_finish = '\n' + markup_finish;
        }
    if (this.toggle_same_format(markup_start, markup_finish)) {
        this.selection_end = this.selection_end -
            (markup_start.length + markup_finish.length);
        markup_start = '';
        markup_finish = '';
    }
    if (this.sel.length == 0) {
        if (example)
            this.sel = example;
        var text = this.start + markup_start + this.sel +
            markup_finish + this.finish;
        var start = this.selection_start + markup_start.length;
        var end = this.selection_end + markup_start.length + this.sel.length;
        this.set_text_and_selection(text, start, end);
    } else {
        var text = this.start + markup_start + this.sel +
            markup_finish + this.finish;
        var start = this.selection_start;
        var end = this.selection_end + markup_start.length +
            markup_finish.length;
        this.set_text_and_selection(text, start, end);
    }
    this.area.focus();
}

proto.handle_bound_phrase = function(element, markup) {
    this.assert_space_or_newline(); // FIX
    this.appendOutput(markup[1]);
    this.no_following_whitespace();
    this.walk(element);
    // assume that walk leaves no trailing whitespace.
    this.appendOutput(markup[2]);
}

proto.assert_space_or_newline = function() {
    var string;
    if (! this.output.length) return;

    string = this.output[this.output.length - 1];
    if (! string.whitespace && ! string.match(/(\s+|[\"\':])$/))
        this.appendOutput(' ');
}

proto.camel_case_link = function(label) {
    if (! this.config.supportCamelCaseLinks)
        return false;
    return label.match(/^[A-Z]*[a-z]+/);
}

proto.href_is_wiki_link = function(href) {
    if (! this.looks_like_a_url(href))
        return true;
    if (! href.match(/\?/))
        return false;
    var no_arg_input   = href.split('?')[0];
    var no_arg_current = location.href.split('?')[0];
    return no_arg_input == no_arg_current;
}

proto.convertWikitextToHtml = function(wikitext, func) {
    var postdata = 'action=markup&value=' + encodeURIComponent(wikitext);
    HTTPPost(
        self.location,
        postdata,
        func);
}

proto.convertWikitextToHtmlAll = function(wikitext, func) {
    var postdata = 'action=markup&all=1&value=' + encodeURIComponent(wikitext);
    HTTPPost(
        self.location,
        postdata,
        func);
}

// Inject Wikiwyg css into the head
var head = document.getElementsByTagName('head')[0];

var link = document.createElement('link');
link.setAttribute('rel', 'stylesheet');
link.setAttribute('type', 'text/css');
link.setAttribute('href', _url_prefix + '/local/Wikiwyg/css/moniwyg.css');
head.appendChild(link);
//

wikiwyg_divs = [];
function createWikiwygDiv(elem, parent) {
    var div = document.createElement('div');
    div.setAttribute('class', 'wikiwyg_area');
    var insert = elem.previousSibling;
    var edit = elem;
    var elem = elem.nextSibling;
    var check = 0;

    alert(edit.innerHTML);
    while (elem) {
        for (i=0; i<elem.childNodes.length; i++) {
            if (elem.childNodes[i].className == 'sectionEdit') {
                check = 1;
                break;
            }
        }
        if (check == 1) break;
        //if (elem.className == 'sectionEdit')
        //    break;
        //if (elem.className == 'printfooter') {
        //    elem = null;
        //    break;
        //}
        var temp = elem.nextSibling;
        div.appendChild(elem);
        elem = temp;
    }
    wikiwyg_divs.push([edit, div]);
    parent.insertBefore(div, insert);
    parent.insertBefore(edit, div);
    return elem;
}

//
// dynamic section editing for MoniWiki
//

wikiwygs = [];

function sectionEdit(ev,obj,sect) {
    var area;
    var text=null;
    var form=null;
    if (sect) {
        var sec=document.getElementById('sect-'+sect);
        area=sec.parentNode;

        var href=obj.href.replace(/=edit/,'=edit/ajax');
        var saved=obj.cloneNode(true);
        var loading=document.createElement('img');
        loading.setAttribute('border',0);
        loading.setAttribute('class','ajaxLoading');
        loading.src=_url_prefix + '/imgs/loading.gif';
        obj.parentNode.replaceChild(loading,obj);
        form=HTTPGet(href);
        loading.parentNode.replaceChild(saved,loading);
    } else {
        area=document.getElementById('editor_area');
        text=area.getElementsByTagName('textarea')[0].value;
        form=area.innerHTML;
    }

    if (form && form.substring(0,5) != 'false') {
            var myConfig = {
                doubleClickToEdit: true,
                toolbar: {
                    imagesLocation:
                        _url_prefix + '/local/Wikiwyg/moni/images/',
                    imagesExtension: '.png'
                },
                wikitext: {
                    supportCamelCaseLinks: true,
                    javascriptLocation: _url_prefix + '/local/Wikiwyg/lib/'
                },
                modeClasses: [
                    'Wikiwyg.Wysiwyg',
                    'Wikiwyg.Wikitext',
                    'Wikiwyg.Preview',
                    'Wikiwyg.HTML'
                ]
            };

            var myWikiwyg = new Wikiwyg();
            myWikiwyg.createWikiwygArea(area, myConfig);
            wikiwygs.push(myWikiwyg);
            myWikiwyg.editMode(form,text);
    }
    return;
}

function savePage(obj) {
    obj.elements['action'].value+='/ajax';
    var sec=null;
    if (obj.section)
        sec=document.getElementById('sect-'+obj.section.value);
    var toSend = '';
    for (var i=0;i<obj.elements.length;i++) {
        if (obj.elements[i].name != '')  {
            toSend += (toSend ? '&' : '') + obj.elements[i].name + '='
                                  + escape(obj.elements[i].value);
        }
    }
    var form=HTTPPost(self.location,toSend);
    if (form.substring(0,4) == 'true') {
        if (sec) {
            var ed=document.getElementById('editSect-'+obj.section.value);
            if (ed) { // toogle
                sec.parentNode.removeChild(sec.parentNode.lastChild);
                return false;
            }
        }
    } else {
        var f=document.createElement('div');
        if (sec) {
            f.setAttribute('id','editSect-'+obj.section.value);
            // show error XXX
            f.innerHTML=form;
            sec.parentNode.appendChild(f);
        }
    }
    return false;
}

// vim:et:sts=4:sw=4:
