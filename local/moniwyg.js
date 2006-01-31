//
// Wikiwyg for MoniWiki by wkpark at kldp.org 2006/01/27
//
// $Id$
//
_url_prefix="/wiki";

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
    var section='';
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
    '&datestamp=' + datestamp + '&section=' + section;
    var location = this.mylocation;

    var saved=self.div.innerHTML;
    self.div.innerHTML='<img src="'+_url_prefix+'/imgs/loading.gif" />';
    var form=HTTPPost(location,toSend);
    if (form.substring(0,4) == 'true') {
        // get section
        var toSend = 'action=markup&all=1&section=' + section;
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

Wikiwyg.prototype.editMode = function(form) {
    var self = this;
    var dom = document.createElement('div');
    dom.innerHTML = form;

    var form = dom.getElementsByTagName('form')[0];
    var wikitext = form.savetext.value;
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
    this.myinput=dom.getElementsByTagName('input');
}

proto = Wikiwyg.Wysiwyg.prototype;
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


proto = Wikiwyg.Wikitext.prototype;

proto.convert_html_to_wikitext = function(html) {
    this.copyhtml = html;
    var dom = document.createElement('div');
    html = html.replace(/<!-=-/g, '<!--').
                replace(/-=->/g, '-->');

    // for MoniWiki
    // remove perma icons
    html = html.replace(/<a class=.perma..*\/a>/g, '');
    // interwiki links
    // remove interwiki icons
    html =
        html.replace(/<a class=.interwiki.[^>]+><img [^>]+><\/a><a [^>]+title=(\'|\")([^\'\"]+)\1>[^<]+<\/a>/g, "$2");
    //html =
    //html.replace(/<a [^>]+title=(\'|\")([^\'\"]+)\1>[^<]+<\/a>/g, "**$2");
    html =
        html.replace(/<img class=.(url|externalLink).[^>]+>/g, '');
    // smiley/inline tex etc.
    html =
        html.replace(/<img [^>]*class=.(tex|interwiki|smiley).[^>]* alt=(.)([^\'\"]+)\2[^>]+>/g, "$3");
    // interwiki links
    html =
        html.replace(/<a [^>]+ alt=(.)([^\'\"]+)\1[^>]+>/g, "$2");
    // remove nonexists links
    html = html.replace(/<a class=.nonexistent.[^>]+>([^<]+)<\/a>/g, "$1");

    //
    dom.innerHTML = html;
    this.output = [];
    this.list_type = [];
    this.indent_level = 0;

    this.walk(dom);

    // add final whitespace
    this.assert_new_line();

    return this.join_output(this.output);
}

proto.format_tr = function(element) {
    this.walk(element);
    this.appendOutput('||');
    this.insert_new_line();
}

proto.format_br = function(element) {
// for plain br
    var string = this.output[this.output.length - 1];
    if (! string.whitespace && ! string.match(/\n$/))
        this.insert_new_line();
    this.insert_new_line();
}

// proto.format_pre FIXME

proto.assert_blank_line = function() {
    this.chomp();
    this.insert_new_line();
}

proto.format_td = function(element) {
    var colspan =element.getAttribute('colspan');
    if (colspan) {
        for (var i=0;i<colspan;i++)
            this.appendOutput('||');
    } else
        this.appendOutput('||');
    var rowspan =element.getAttribute('rowspan');
    if (rowspan)
        this.appendOutput('<|'+rowspan+'>');
    this.appendOutput('');
    this.walk(element);
    this.chomp(); // XXX
    this.appendOutput('');
}

proto = Wikiwyg.Toolbar.prototype;
proto.config.controlLayout = [
    'save', 'cancel', 'mode_selector', '/',
    'bold',
    'italic',
    'link',
    'h2',
    'ordered',
    'unordered',
    'math',
    'nowiki',
    'hr'
];

proto.config.controlLabels.math = 'Math';
proto.config.controlLabels.nowiki = 'As Is';

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
proto.config.markupRules.pre = ['bound_phrase','{{{','}}}'],
proto.config.markupRules.ordered = ['start_lines', ' 1.'];
proto.config.markupRules.unordered = ['start_lines', ' *'];
proto.config.markupRules.indent = ['start_lines', '>'];
proto.config.markupRules.hr = ['line_alone', '----'];
proto.config.markupRules.table = ['line_alone', '|| A || B || C ||\n||   ||   ||   ||\n||   ||   ||   ||'];

proto.do_math = Wikiwyg.Wikitext.make_do('math');
proto.do_nowiki = Wikiwyg.Wikitext.make_do('nowiki');

proto.collapse = function(string) {
    return string.replace(/\r\n/g, "\n"); // FIX
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
                if (! string.match(/^[\'\.\,\?\!\)]/)) {
                    this.assert_space_or_newline(); // FIX
                    string = this.trim(string); // FIX
                    //string = this.mytrim(string); // replace
                }
                this.appendOutput(this.collapse(string));
            }
        }
    }
}

proto.mytrim = function(string) {
    string = string.replace(/(\s|\r\n|\n|\r)+$/, '');
    return string.replace(/^(\r\n|\n|\r)+/, '');
}

proto.handle_bound_phrase = function(element, markup) {
    //this.assert_space_or_newline(); // FIX
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
    Wikiwyg.liveUpdate(
        'POST',
        self.location,
        postdata,
        func);
}

proto.convertWikitextToHtmlAll = function(wikitext, func) {
    var postdata = 'action=markup&all=1&value=' + encodeURIComponent(wikitext);
    Wikiwyg.liveUpdate(
        'POST',
        self.location,
        postdata,
        func);
}

// Inject Wikiwyg css into the head
var head = document.getElementsByTagName('head')[0];

var link = document.createElement('link');
link.setAttribute('rel', 'stylesheet');
link.setAttribute('type', 'text/css');
link.setAttribute('href', _url_prefix + '/local/Wikiwyg/css/wikiwyg.css');
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
    if (sect) {
        var sec=document.getElementById('sect-'+sect);

        var href=obj.href.replace(/=edit/,'=edit/ajax');
        var saved=obj.cloneNode(true);
        var loading=document.createElement('img');
        loading.setAttribute('border',0);
        loading.setAttribute('class','ajaxLoading');
        loading.src=_url_prefix + '/imgs/loading.gif';
        obj.blur();
        obj.parentNode.replaceChild(loading,obj);
        //alert('loading...');
        var form=HTTPGet(href);
        loading.parentNode.replaceChild(saved,loading);

        if (form.substring(0,5) != 'false') {
            var myConfig = {
                doubleClickToEdit: true,
                toolbar: {
                    imagesLocation:
                        _url_prefix + '/local/Wikiwyg/demo/moin/images/',
                imagesExtension: '.png'
            },
            wikitext: {
                supportCamelCaseLinks: true
            },
            modeClasses: [
                'Wikiwyg.Wikitext',
                'Wikiwyg.Wysiwyg',
                'Wikiwyg.HTML',
                'Wikiwyg.Preview',
            ]
        }
        //var div = document.createElement('div');
        //div.setAttribute('class', 'wikiwyg_area');

        //sec.parentNode.appendChild(div);

        var myWikiwyg = new Wikiwyg();
        //myWikiwyg.createWikiwygArea(div, myConfig);
        myWikiwyg.createWikiwygArea(sec.parentNode, myConfig);
        wikiwygs.push(myWikiwyg);
        myWikiwyg.editMode(form);
        //myWikiwyg.textarea.value = wikitext;
        //alert(sec.parentNode.innerHTML);

        //var f=document.createElement('div');
        //f.setAttribute('id','editSect-'+sect);
        }
    }
}

function savePage(obj) {
    obj.elements['action'].value+='/ajax';
    var sec=document.getElementById('sect-'+obj.section.value);
    var toSend = '';
    for (var i=0;i<obj.elements.length;i++) {
        if (obj.elements[i].name != '')  {
            toSend += (toSend ? '&' : '') + obj.elements[i].name + '='
                                  + escape(obj.elements[i].value);
            //alert(obj.elements[i].name+'='+obj.elements[i].value);
        }
    }
    var form=HTTPPost(self.location,toSend);
    if (form.substring(0,4) == 'true') {
        var ed=document.getElementById('editSect-'+obj.section.value);
        if (ed) { // toogle
            sec.parentNode.removeChild(sec.parentNode.lastChild);
            return false;
        }
    } else {
        var f=document.createElement('div');
        f.setAttribute('id','editSect-'+obj.section.value);
        // show error XXX
        f.innerHTML=form;
        sec.parentNode.appendChild(f);
    }
    return false;
}

// vim:et:sts=4:sw=4:
