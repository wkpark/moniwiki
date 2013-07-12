//
// Wikiwyg for MoniWiki by wkpark at kldp.org 2006/01/27
//
// $Id$
//
//_url_prefix="/wiki";
//

if ( typeof N_ == 'undefined') {
    N_ = function(msgid) {
        return msgid;
    };
}

if ( typeof _ == 'undefined') {
    _ = function(msgid) {
        return msgid;
    };
}

Wikiwyg.browserIsSupported = (
    Wikiwyg.is_gecko ||
    Wikiwyg.is_ie ||
    Wikiwyg.is_opera ||
    Wikiwyg.is_safari
);

// Wikiwyg fix for IE
if (Wikiwyg.is_ie) {
    // innerHTML hack :(
    Wikiwyg.prototype.fromHtml = function(html) {
        //html=html.replace(/<\/span>(\s+)/,"</span><span class=wikiMarkup><!-- wiki:\n$1\n-->$1</span>");
        this.div.innerHTML = '<br>' +html; // IE hack :(
        this.div.removeChild(this.div.firstChild);
        alert('Wikiwyg in #1='+html); // ???
    }

    Wikiwyg.Mode.prototype.create_dom = function(html) {
        var dom = document.createElement('div');
        dom.innerHTML = '<br>' + html; // IE hack :(
        dom.removeChild(dom.firstChild);
        return dom;
    }

    Wikiwyg.Wysiwyg.prototype.set_inner_html = function(html) {
        var body = this.get_edit_document().body;
        body.innerHTML = '<br>' + html; // IE hack :(
        body.removeChild(body.firstChild);
    }
}

Wikiwyg.Mode.prototype.execute_scripts = function(el,scripts) {
    var iframe=null;

    if (this.classname.match(/(Wysiwyg)/)) {
        iframe = this.edit_iframe;
        doc = iframe.contentDocument || iframe.contentWindow.document;
        el= this.get_edit_document().body; // XXX
    } else if (this.classname.match(/(Preview)/)) {
        doc = document;
    } else {
        return;
    }

    var head = document.getElementsByTagName("head")[0];

    if (!scripts) {
        if (el)
            scripts= el.getElementsByTagName('script');
        else
            scripts= this.div.getElementsByTagName('script');
    }

    for (var i=0;i<scripts.length;i++) {
        if (scripts[i].src) {
            var js=document.createElement('script');
            js.type='text/javascript';
            js.src=scripts[i].src;
            head.appendChild(js);
        } else {
            var js1=doc.createElement('script');
            js1.type='text/javascript';
            if (iframe) // hack XXX
                js1.text=scripts[i].text.replace(/document\./g,'doc.');
            else
                js1.text=scripts[i].text;
            eval(js1.text); // XXX
            //head.appendChild(js1);
        }
    }
}

Wikiwyg.Mode.prototype.get_edit_height = function() {
    var height = parseInt(
        this.wikiwyg.divHeight * 1.1
    );
    var min = 100;
    
    var min = this.config.editHeightMinimum;
    return height < min
        ? min
        : height;
}

// Returns if current position is in a wikimarkup block or not
Wikiwyg.Wysiwyg.prototype.get_wikimarkup_node = function() {
    var p=this.get_parent_node();

    while (p && (p.nodeType == 1) && (p.tagName.toLowerCase() != 'body')) {
        if (p.nodeName=='SPAN' && p.className &&
                p.className.match(/wikiMarkup/) &&
                p.firstChild.nodeType == 8 &&
                p.firstChild.data.match(/^\s*wiki/)) {
            return p;
            break;
        }
        p=p.parentNode;
    }
    return null;
}

Wikiwyg.Wysiwyg.prototype.check_parent_node = function() {
    var p=this.get_parent_node();
    while (p && (p.nodeType == 1) && (p.tagName.toLowerCase() != 'body')) {
        if (p.nodeName == 'TD' ||
                p.nodeName == 'PRE' ||
                p.nodeName.match(/^H[1-6]/)) {
            return p;
            break;
        } else if (p.nodeName == 'SPAN') {
            if (p.className &&
                    p.className.match(/wikiMarkup/) &&
                    p.firstChild.nodeType == 8 &&
                    p.firstChild.data.match(/^\s*wiki/)) {
                return p;
                break;
            }
        }
        p=p.parentNode;
    }
    return p;
}

Wikiwyg.Wysiwyg.prototype.set_focus = function(el,focus) {
    var sel = this.get_selection();
    var iframe = this.edit_iframe;
    var doc = iframe.contentDocument || iframe.contentWindow.document;

    flag=false;
    collapse=true;
    if (focus!=null) {
        if (focus & 1) flag= true;
        if (focus & 2) collapse= false;
    }
    // focus = 0:start, 1:end, 2:no collapse

    // from HTMLArea 3.0 selectNodeContents() function
    // http://www.dynarch.com/projects/htmlarea/ BSD-style
    if (!Wikiwyg.is_ie) {
        var range = doc.createRange();
        range.selectNodeContents(el);
        sel.removeAllRanges();
        if (collapse) range.collapse(flag);
        sel.addRange(range);
        el.focus();
    } else {
        range = doc.body.createTextRange();
        range.moveToElementText(el);
        if (collapse) range.collapse(flag);
        range.select();
        el.focus();
    }
}

Wikiwyg.Wysiwyg.prototype.update_wikimarkup = function(el,flag,focus) {
    var markup = el.firstChild;
    if (markup.nodeType != 8) return false;
    var self= this;
    var type;

    if (focus==null || focus == 'undefined')
        focus=0; // 0:start, 1:end, 2:no collapse

    if (el.className == 'wikiMarkup') {
        el.className = 'wikiMarkupEdit';
        if (el.style.display == 'inline') type='span';
        else type='pre';
        var edit=document.createElement(type);
        var div=document.createElement('div');

        var myWikitext=new Wikiwyg.Wikitext();
        markup = myWikitext.get_wiki_comment(el);
        var text = markup.data.replace(/^ wiki:(\s|\\n)+/, '')
                   .replace(/-=/g, '-')
                   .replace(/==/g, '=')
                   .replace(/&amp;/g,'&')
                   .replace(/(\r\n|\n|\r)+$/, '') //.replace(/\s$/, '') IE fix
                   .replace(/\{(\w+):\s*\}/, '{$1}');
        text=text.replace(/>/g,'&gt;');
        var newhtml='<!--'+markup.data+'--><'+type+'>'+text+'</'+type+'>';

        if (Wikiwyg.is_ie) {
            el.innerHTML = "<br>" +newhtml; // Bah.... IE hack :(
            el.removeChild(el.firstChild);
        } else {
            el.innerHTML = newhtml;
        }

        this.set_focus(el.firstChild.nextSibling,focus);
    } else if (flag && el.className == 'wikiMarkupEdit') {
        var edit=markup.nextSibling;
        if (edit==null) return;
        var myText=edit.innerHTML
                        .replace(/<br>/ig,"\n")
                        .replace(/&gt;/g,'>')
                        .replace(/&lt;/g,'<')
                        .replace(/&amp;/g,'&')
                        ;
        var postdata = 'action=markup/ajax&value=' + encodeURIComponent(myText);
        var myhtml= HTTPPost(top.location, postdata);

        // hack hack
        //var chunks = myhtml.split(/^\s*<div>/i);
        //myhtml = (chunks[1] ? chunks[1]:chunks[0])
        var chunks = myhtml.replace(/\s*<(div|p[^>]*)>/i,'');
        myhtml = chunks
            .replace(/^(.*)<(?:div|p[^>]*)>(\s|\n)*(<span)/i,'$4')
            .replace(/<\/span>(\s|\n)*<\/?(?:div|p)>(\s)*$/i,'</span>');

        var div=document.createElement('div');
        if (Wikiwyg.is_ie) {
            div.innerHTML='<br>'+fixup_markup_style(myhtml); // IE hack
            div.removeChild(div.firstChild);
        } else {
            div.innerHTML=fixup_markup_style(myhtml);
        }
        //alert(div.innerHTML);

        var scripts= div.getElementsByTagName('script');
        var n=div.firstChild;
        for (;n && n.nodeName!='SPAN';n=n.nextSibling);

        if (n && n.nodeName=='SPAN') {
            if (Wikiwyg.is_ie) {
                el.className='wikiMarkup';
                el.innerHTML='<br>'+n.innerHTML; // IE hack
                el.removeChild(el.firstChild);
            } else {
                //el.innerHTML=nn.innerHTML; // not work properly :(
                el.parentNode.insertBefore(n,el); // insert
                el.parentNode.removeChild(el);
                this.set_focus(n,focus);
                //this.set_focus(n.firstChild.nextSibling,focus);
            }
            this.execute_scripts(n,scripts);
        }
    }
    return true;
}


Wikiwyg.Wysiwyg.prototype.get_key_down_function = function() {
    var self = this;
    return function(e) {
        e = e || window.event;
        var ch = String.fromCharCode(Wikiwyg.is_ie ? e.keyCode : e.charCode);
        var key = String.fromCharCode(e.keyCode); // XXX

        var wm = self.get_wikimarkup_node();
        if (e.keyCode == 27 || (e.keyCode== 13 && wm && wm.style.display=='inline')) {            // ESC or RETURN
            if (wm) self.update_wikimarkup(wm,true);
            if (window.event) e.cancelBubble = true;
            else e.preventDefault(), e.stopPropagation();
        }
        if (e.keyCode == 35 || e.keyCode == 39) { // right arrow or end key.
            var sel = self.get_selection();
            var sf = sel.focusNode;
            var p = sf.parentNode;
            // XXX
            if ((p.nodeName=='A' && sf.nodeType==3) || (p.nodeName == 'SPAN' && p.className.match(/wikiMarkup/) )) { // XXX
                if (e.keyCode == 35 ||
                        (e.keyCode == 39 && sel.focusOffset == sf.nodeValue.length)) {
                    sel.removeAllRanges();
                    range=self.get_range();
                    if (p.nextSibling) {
                        range.selectNode(p.nextSibling);
                        range.setStart(p.nextSibling,0);
                        if (Wikiwyg.is_safari) {
                            range.setEnd(p.nextSibling,1); // XXX safari/chrome bug.
                        } else {
                            range.setEnd(p.nextSibling,0);
                        }
                        range.collapse(false); // not work ;;
                    } else {
                        if (Wikiwyg.is_safari) {
                            var txt = document.createTextNode(' ');
                            p.parentNode.appendChild(txt);
                            range.selectNode(txt);
                            range.setStart(txt,0);
                            range.setEnd(txt,1);
                        } else {
                            range.setStartAfter(p);
                            range.setEndAfter(p);
                        }
                        range.collapse(false);
                    }
                    sel.addRange(range);

                    if (Wikiwyg.is_ie) e.cancelBubble = true;
                    else e.preventDefault(), e.stopPropagation();
                    return true;
                }
            }
        }
        if (wm && wm.className.match(/wikiMarkup/)) {
            var focus=0;
            var stop=false;

            if (!e.ctrlKey) {
                if (key == 'I') focus|=1;
                else if (key == 'S') focus|=2;
                if (focus && wm.className == 'wikiMarkup') { // check arrowkey or not
                    self.update_wikimarkup(wm,false,focus);
                    stop=true;
                }
            } else {
                if (key == 'A' && wm.className == 'wikiMarkupEdit') { // select node
                    self.set_focus(wm,2);
                    stop=true;
                }
            }

            if (stop) {    
                if (window.event) e.cancelBubble = true;
                else e.preventDefault(), e.stopPropagation();
                return false;
            }
            return true;
        }

        return true;
    };
}

Wikiwyg.Wysiwyg.prototype.get_key_press_function = function() {
    var self = this;
    return function(e) {
        if (e) cc=e.keyCode||e.charCode;
        else e=window.event,cc=e.keyCode;
        var ch = String.fromCharCode(cc);

        if (! e.ctrlKey) {
            if (cc == 32 || cc == 13) { // space
                var sel=self.get_selection();
                if (!Wikiwyg.is_ie) {
                    var sf=sel.focusNode;
                    var wm=self.get_wikimarkup_node();
                    if (cc == 13
                            && sf.nodeType == 3 && sel.toString() == ''
                            && sf.parentNode.nodeName.match(/H\d/) && wm == null) {
                        // safari/chrome hack XXX :(
                        if (Wikiwyg.is_safari) {
                            var txt;
                            if (sf.parentNode.nextSibling) {
                                txt = document.createElement('br');
                                sf.parentNode.parentNode.insertBefore(txt,sf.parentNode.nextSibling);
                            } else {
                                txt = document.createElement('br');
                                sf.parentNode.parentNode.appendChild(txt);
                            }
                            var range = self.get_range();
                            sel.removeAllRanges();
                            range.selectNode(txt);
                            range.setStart(txt,0);
                            range.setEnd(txt,0);
                            range.collapse(false);
                            sel.addRange(range);

                            e.preventDefault();
                            e.stopPropagation();
                        }
                    } else if (sf.nodeType == 3 && sel.toString() == ''
                            && sf.parentNode.nodeName != 'A' && wm == null) {
                        // text node
                        var range=self.get_range();
                        var val = sf.nodeValue.substr(0,sel.focusOffset).replace(/\s+$/,'');
                        if (val) {
                            var m=[];
                            var p=val.lastIndexOf('=');
                            if (p == -1) {
                                var p=val.lastIndexOf(' ');
                                if (p == -1) m[1]='',m[2]=val;
                                else m[1]=val.substr(0,p+1),m[2]=val.substr(p+1);
                            } else {
                                var m=val.match(/(={2,6})(\s*.*\s*)\1$/); // FIXME
                                if (m) {
                                    m[1]=val.substr(0,val.length - m[0].length);
                                    m[2]=val.substr(val.length - m[0].length);
                                } else m = [null,'',val];
                            }
                            if (m[2].match(/^(http|https|ftp|nntp|news|irc|telnet):\/\//) ||
                                m[2].match(/^[A-Z]([A-Z]+[0-9a-z]|[a-z0-9]+[A-Z])[0-9a-zA-Z]*\b/) ||
                                m[2].match(/^(\[.*\]|(={2,6}).*\2)$/)) { // force link, macro

                                range.setStart(sf,m[1].length);
                                range.setEnd(sf,m[1].length+m[2].length);
                                sel.removeAllRanges(); // remove old ranges !
                                sel.addRange(range);

                                if (cc == 13) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                }

                                self.do_link(); // auto linking
                                nsel=self.get_selection();
                                if (nsel.focusNode.nodeType == 3) {
                                    var mynode=nsel.focusNode.parentNode;
                                    nsel.removeAllRanges();
                                    range=self.get_range();
                                    range.setStartAfter(mynode);
                                    range.setEndAfter(mynode);
                                    nsel.addRange(range);
                                }
                                return true;
                            }
                        }
                    }
                }
            }
            /* if (e.keyCode == 8) { // backspace
                var p = self.get_parent_node();
                if (p.childNodes.length == 0) {
                    alert('www');
                    p.parentNode.removeChild(p);
                }
            }
            */
            return;
        }
        var key = String.fromCharCode(e.charCode).toLowerCase();
        var command = '';
        switch (key) {
            case 'b': command = 'bold'; break;
            case 'i': command = 'italic'; break;
            case 'u': command = 'underline'; break;
            case 'd': command = 'strike'; break;
            case 'l': command = 'link'; break;
        };

        if (command) {
            if (Wikiwyg.is_ie) e.cancelBubble = true;
            else e.preventDefault(), e.stopPropagation();
            self.process_command(command);
        }
    };
}

// Moniwiki hack
Wikiwyg.prototype.saveChanges = function() {
    var self = this;
    var myWikiwyg = new Wikiwyg.Wikitext();
    var wikitext;
    var myhtml;

    if (this.current_mode.classname == 'Wikiwyg.Wikitext') {
        wikitext = this.current_mode.textarea.value;
    } else {
        if (this.current_mode.classname.match(/(Wysiwyg|Preview)/)) {
            this.current_mode.toHtml( function(html) { myhtml = html; });
        } else if (this.current_mode.classname=='Wikiwyg.HTML') {
            myhtml = this.current_mode.textarea.value;
        }
        wikitext = myWikiwyg.convert_html_to_wikitext(myhtml);
    }

    var datestamp='';
    var myaction='';
    var section=null;
    for (var i=0;i<this.myinput.length;i++) {
        if (this.myinput[i].name == 'datestamp')
            datestamp=this.myinput[i].value;
        else if (this.myinput[i].name == 'section')
            section=this.myinput[i].value;
        else if (this.myinput[i].name == 'action')
            myaction=this.myinput[i].value;
    }

    /*
    // for preview
    myWikiwyg.convertWikitextToHtmlAll(wikitext,
        function(new_html) {
            //self.div.innerHTML = new_html
            self.div.innerHTML = "<br>" + new_html; // Bah.... IE hack :(
            self.div.removeChild(self.div.firstChild);
        });

    // XXX using default form XXX
    */
    var area=document.getElementById('editor_area');
    if (area) {
        var textarea=area.getElementsByTagName('textarea')[0];
        var form=area.getElementsByTagName('form')[0];
        if (textarea) textarea.value=wikitext;

        // restore extra fields
        if (this.extra) {
            var extras=this.extra.getElementsByTagName('input');
            var myinputs=form.getElementsByTagName('input');
            for (var i=0;i < extras.length;i++) {
                if (myinputs[extras[i].name]) {
                    myinputs[extras[i].name].value=extras[i].value;
                }
            }
        }

        form.submit();
        return;
    }

    // save section
    var toSend = 'action=' + myaction + '/ajax' +
    '&savetext=' + encodeURIComponent(wikitext) +
    '&datestamp=' + datestamp;
    myaction=myaction.replace(/=edit\//,'=savepage/');

    if (section)
        toSend += '&section=' + section;
    var location = this.mylocation;

    var saved=self.div.innerHTML;
    self.div.innerHTML='<img src="'+_url_prefix+'/imgs/loading.gif" />';
    var form=HTTPPost(location,toSend);
    if (form.substring(0,4) == 'true') {
        // get section
        var toSend = 'action=markup/ajax&all=1';
        if (section)
            toSend += '&section=' + section;
        form=HTTPPost(location,toSend);
        self.div.innerHTML=form;

        this.displayMode();
        return;
    } else {
        //self.div.innerHTML=saved;
        self.div.innerHTML = "<br>" + saved; // Bah.... IE hack :(
        self.div.removeChild(self.div.firstChild);

        var f=document.createElement('div');
        f.setAttribute('class','errorLog');
        // show error XXX
        f.innerHTML=form;
        alert("Can't save."); // XXX
    }

    return;
}

Wikiwyg.prototype.cancelEdit = function() {
    var self = this;
    var myWikiwyg = new Wikiwyg.Wikitext();
    var wikitext;

    var area=document.getElementById('editor_area');
    if (area) {
        var textarea=area.getElementsByTagName('textarea')[0];

        var myhtml;
        this.current_mode.toHtml( function(html) { myhtml = html; });

        if (this.current_mode.classname.match(/(Wysiwyg|HTML|Preview)/)) {
            this.current_mode.fromHtml(myhtml);

            wikitext = myWikiwyg.convert_html_to_wikitext(myhtml);
        }
        else {
            wikitext = this.current_mode.textarea.value;
        }

        if (textarea && confirm('Continue to edit current text ?') )
            textarea.value=wikitext;
    }

    var toolbar=document.getElementById('toolbar');
    if (toolbar) { // show toolbar
        if (Wikiwyg.is_ie) toolbar.style.display='';
        else toolbar.setAttribute('style','');
    }
    this.displayMode();
}

Wikiwyg.prototype.switchMode = function(new_mode_key) {
    var new_mode = this.modeByName(new_mode_key);
    var old_mode = this.current_mode;
    var self = this;

    new_mode.enableStarted();
    old_mode.disableStarted();
    old_mode.toHtml(
        function(html) {
            self.previous_mode = old_mode;
            new_mode.fromHtml(fixup_markup_style(html,new_mode.classname));
            old_mode.disableThis();
            new_mode.enableThis();
            new_mode.enableFinished();
            old_mode.disableFinished();
            self.current_mode = new_mode;
            self.current_mode.execute_scripts(new_mode.div);
        }
    );
    if (typeof textAreaAutoAttach == 'function') // support resizable textarea
        textAreaAutoAttach();
}



Wikiwyg.prototype.editMode = function(form,text,mode) {
    var self = this;
    var dom = document.createElement('div');

    dom.innerHTML = form;

    var form = dom.getElementsByTagName('form')[0];
    var mytext = dom.getElementsByTagName('textarea')[0];
    var wikitext = text == null ? mytext.value:text;
    this.mylocation = form.getAttribute('action');

    if (typeof mode == 'undefined')
        this.current_mode = this.first_mode;
    else
        this.current_mode = this.modeByName('Wikiwyg.Wikitext');

    if (this.current_mode.classname.match(/(Wysiwyg|HTML|Preview)/)) {
        var myWikiwyg = new Wikiwyg.Wikitext();

        myWikiwyg.convertWikitextToHtml(wikitext,
            function(new_html) {
                self.current_mode.fromHtml(fixup_markup_style(new_html,self.current_mode.classname));
                self.current_mode.execute_scripts();
            });
    }
    else {
        this.current_mode.textarea.value = wikitext;
    }

    this.toolbarObject.resetModeSelector();
    this.current_mode.enableThis();
    //this.current_mode.enableThis(); // hack !!
    this.myinput=dom.getElementsByTagName('input');
    var divs=dom.getElementsByTagName('div');

    // save some needed fields
    for (var i=0;i < divs.length;i++) {
        if (divs[i].className == 'editor_area_extra') {
            this.extra_input=divs[i];
            break;
        }
    }
}

//
// change display style to 'block' for multiline markups
//
function fixup_markup_style(html,modename)
{
    var dom = document.createElement('div');

    if (!modename) modename='Wysiwyg';

    //alert('fixup_markup='+html);
    if (Wikiwyg.is_ie) {
        //html = html.replace(/(\r\n|\n|\r)/g,'\\n');
        dom.innerHTML = "<br>" +html; // Bah.... IE hack :(
        dom.removeChild(dom.firstChild);
    } else {
        dom.innerHTML = html;
    }
    //alert('fixup innerHTML='+dom.innerHTML);
    // fix for Mozilla
    // var embeds=dom.getElementsByTagName('embed');
    var objects=dom.getElementsByTagName('object');
    var loc = location.protocol + '//' + location.host;
    if (location.port) loc += ':' + location.port;

    if (objects.length) {
        for (var i=0;i<objects.length;i++) {
            var n=objects[i];
            var w=n.getAttribute('width') + 'px';
            var h=n.getAttribute('height') + 'px';
            var applet=null,embed=null;
            var img = new Image();
            img.style.width = w;
            img.style.height = h;
            img.src = loc + _url_prefix + '/imgs/misc/embed.png';

            n=n.firstChild;
            while (n) {
                if (n.tagName == 'IMG') break;
                if (n.tagName == 'APPLET') applet=n;
                else if (n.tagName == 'EMBED') embed=n;
                n=n.nextSibling;
            }

            if (n == null) {
                if (modename.match(/Wysiwyg/)) objects[i].appendChild(img);
            } else {
                w=n.style.width || n.getAttribute('width');
                h=n.style.height || n.getAttribute('height');

                if (modename.match(/Preview/)) objects[i].removeChild(n);

                objects[i].setAttribute('width',w);
                objects[i].setAttribute('height',h);
                if (applet) {
                    applet.setAttribute('width',w);
                    applet.setAttribute('height',h);
                } else if (embed) {
                    embed.setAttribute('width',w);
                    embed.setAttribute('height',h);
                }
            }
            if (applet) {
                if (modename.match(/Wysiwyg/)) w='1px', h='1px'; // mozilla hack for applet tags
                applet.setAttribute('width',w);
                applet.setAttribute('height',h);
            }
        }
    }

    var spans=dom.getElementsByTagName('span');
    var className= Wikiwyg.is_ie ? 'className':'class';

    if (spans.length) {
        for (var i=0;i<spans.length;i++) {
            var cname= spans[i].getAttribute(className) ;
            if (cname == 'wikiMarkup' && spans[i].innerHTML) {
                // check marcos
                //var len=spans[i].firstChild.data.length + 7;

                //if (len) {
                //    var test=spans[i].innerHTML.substr(len);
                //    if (test.indexOf("\n") != -1)
                //        spans[i].style.display='block';
                //}
                //
                if (spans[i].style.display == 'inline') continue;
                // inline markups

                for (var p= spans[i].firstChild; p; p= p.nextSibling) {
                    if (p.nodeType == 1 && p.nodeName != 'IMG' &&
                            p.innerHTML && p.innerHTML.indexOf("\n") != -1) {
                        spans[i].style.display='block';
                        if (false && Wikiwyg.is_ie) {
                            var sn=spans[i].nextSibling;
                            var newline = document.createElement("br");
                            if (sn && sn.nodeType == 3) {
                                sn.parentNode.insertBefore(newline,sn); // XXX IE
                            }
                        }
                        break;
                    }
                }
            }
        }
        return dom.innerHTML;
    } else {
        return html;
    }
}

proto = Wikiwyg.Wysiwyg.prototype;

proto.get_onclick_wikimarkup_function = function() {
    var self= this;
    return function(e) {
        e = e || window.event;
        var wm = self.get_wikimarkup_node();
        if (wm) self.update_wikimarkup(wm, true);
        if (window.event) e.cancelBubble = true;
        else e.preventDefault(), e.stopPropagation();
    };
}

proto.enable_edit_wikimarkup = function() {
    if (!this.onclick_wikimarkup) {
        this.onclick_wikimarkup = this.get_onclick_wikimarkup_function();
        if (window.addEventListener) {
            this.get_keybinding_area().addEventListener(
                'dblclick', this.onclick_wikimarkup, true
            );
        } else {
            this.get_keybinding_area().attachEvent(
                'ondblclick', this.onclick_wikimarkup
            );
        }
    }
}

proto.enable_keybindings = function() { // See IE
    if (!this.key_press_function) {
        this.key_press_function = this.get_key_press_function();
        if (window.addEventListener) {
            this.get_keybinding_area().addEventListener(
                'keypress', this.key_press_function, true
            );
        } else {
            this.get_keybinding_area().attachEvent(
                'onkeypress', this.key_press_function
            );
        }
    }

    if (!this.key_down_function) {
        this.key_down_function = this.get_key_down_function();
        if (window.addEventListener) {
            this.get_keybinding_area().addEventListener(
                'keydown', this.key_down_function, true
            );
        } else {
            this.get_keybinding_area().attachEvent(
                'onkeydown', this.key_down_function
            );
        }
    }
}

proto.initializeObject = function() {
    this.edit_iframe = this.get_edit_iframe();
    this.wrapper=document.createElement('div');
    this.wrapper.className='resizable wrapper';
    this.wrapper.appendChild(this.edit_iframe);
    this.div = this.wrapper;

    //this.div = this.edit_iframe;
    this.set_design_mode_early();
}

proto.get_edit_iframe = function() {
    var iframe=null;
    var body;
    if (this.config.iframeId)
        iframe = document.getElementById(this.config.iframeId);
    else if (this.config.iframeObject)
        iframe = this.config.iframeObject;
    if (iframe) {
        iframe.iframe_hack = true;
        return;
    }
    {
        // XXX iframe need to be a element of the body.
        if (Wikiwyg.is_ie) {
            // http://dojofindings.blogspot.com/2007/09/dynamically-creating-iframes-with.html
            iframe = document.createElement('<iframe onload="iframeHandler()" frameBorder="0">');
        } else {
            iframe = document.createElement('iframe');
        }
        body = document.getElementsByTagName('body')[0];
        body.appendChild(iframe);
        // You can't get 'frameBorder=no' if you appendChild at this line. :( IE bug.
    }

    var self=this;

    // from http://www.codingforums.com/archive/index.php?t-63511.html
    // mozilla and IE hack !!
    iframeHandler = function() {
        //Fx workaround: delay modifying editorDoc.body right after iframe onload event
        var w3c = iframe.contentDocument !== undefined ? true: false;
        var doc = w3c ? iframe.contentDocument:iframe.contentWindow.document;
        var head = doc.getElementsByTagName("head");
        if (Wikiwyg.is_ie) {
            iframe.setAttribute('style','padding:3px;border:1px solid gray;padding-right:0;padding-bottom:0;width:99%');
            iframe.style.border = '1px solid activeborder';
        }

        setTimeout(function() {
            // safari
            // http://code.google.com/p/phpwcms/source/browse/trunk/include/inc_ext/spaw2/js/safari/editor.js
            if (!head || head.length == 0) {
                head = doc.createElement("head");
                doc.childNodes[0].insertBefore(head, doc.body);
                iframe.setAttribute('style','padding:3px;border:1px solid gray;padding-right:0;padding-bottom:0;width:97%');
            } else {
                head = head[0];
            }
            doc.designMode = w3c ? 'on':'On';

            self.apply_stylesheets();
            var link = doc.createElement('link');
            link.setAttribute('rel', 'STYLESHEET');
            link.setAttribute('type', 'text/css');
            link.setAttribute('media', 'screen');
            var loc = location.protocol + '//' + location.host;
            if (location.port) loc += ':' + location.port;
            link.setAttribute('href',
                loc + _url_prefix + '/local/Wikiwyg/css/wysiwyg.css');
            head.appendChild(link);

            self.fix_up_relative_imgs();
            self.clear_inner_html();
            self.enable_keybindings();
            self.enable_edit_wikimarkup();

            if (typeof textArea == 'function')
                new textArea(self.edit_iframe,self.wrapper);
            /*
            iframe.onload='undefined';
            */
        }, 0);

        //editorDoc.onkeydown = editorDoc_onkeydown;
        //where editorDoc_onkeydown is the keydown event handler you defined earlier
        if (Wikiwyg.is_ie) iframe = null; //IE mem leak fix
    }

    iframe.onload=iframeHandler; // ignored by IE :(


    //body.appendChild(iframe);

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
    this.edit_iframe.style.border = '1px solid activeborder';
    //this.edit_iframe.style.backgroundColor = '#ffffff';
    //this.edit_iframe.setAttribute('style','1px solid ThreeDFace;background:#fff;');
    this.edit_iframe.setAttribute('style','padding:3px;border:1px solid activeborder;padding-right:0px');
    this.edit_iframe.width = '99%';
    //this.edit_iframe.style.display='block';
    this.edit_iframe.frameBorder='no';
    this.edit_iframe.border='0';
    this.setHeightOf(this.edit_iframe);
    //this.fix_up_relative_imgs();
    this.get_edit_document().designMode = 'on';

    // XXX - Doing stylesheets in initializeObject might get rid of blue flash
    //
    // this.edit_iframe.contentWindow;
    //this.apply_stylesheets();
/*
    var styles = document.styleSheets;
    var head   = this.get_edit_document().getElementsByTagName("head")[0];

    if (!head) return;

    for (var i = 0; i < styles.length; i++) {
        var style = styles[i];

        if (style.href == location.href)
            this.apply_inline_stylesheet(style, head);
        else
            if (this.should_link_stylesheet(style))
                this.apply_linked_stylesheet(style, head);
    }
    this.enable_keybindings();
    this.clear_inner_html();
*/
}

proto.process_command = function(command,elem) {
    if (this['do_' + command])
        this['do_' + command](command,elem);
    if (! Wikiwyg.is_ie && command != 'image' && command != 'media') // hack for open.window
        this.get_edit_window().focus();
}

proto.do_link = function() {
    var selection = this.get_link_selection_text();
    if (! selection) return;
    var url=null;
    var urltext=null;
    var m=null;
    if ((m=selection.match(/^(={1,6})(.*)\1$/))) { // headings
        var tag = 'h'+m[1].length;
        var myhtml = '<'+tag+'>' + m[2] + '</'+ tag +'>';
        this.exec_command('inserthtml', myhtml);
    } else if (selection.match(/^\[\[.*\]\]$/)) { // macro or links XXX FIXME
        var postdata = 'action=markup/ajax&value=' + encodeURIComponent(selection);
        var myhtml= HTTPPost(top.location, postdata);

        var myhtml = myhtml.replace(/^(.|\n)*<div>(\s|\n)*(<span)/i,'$3')
                .replace(/<\/span>(\s|\n)*<\/?div>\s*$/i,'</span>')
                .replace(/^<div>/i,'')
                .replace(/(\s|\n)*<\/div>\s*$/i,'');
        this.exec_command('inserthtml', fixup_markup_style('<!-- -->'+myhtml));
        urltext=null;
    } else
    {
        var match = selection.match(/(.*?)\b((?:http|https|ftp|nntp|telnet|irc):\/\/\S+)(.*)/);
        if (match) {
            if (match[1] || match[3]) return null;
            url = match[2];
        } else if (selection.match(/^\[.+\]$/)) {
            urltext = selection.substr(1,selection.length-2);
            url = '/' + escape(urltext);
        }
        else {
            url = '/' + escape(selection); 
        }
        this.exec_command('createlink', url);
    }
    if (!Wikiwyg.is_ie && urltext) {
        var p=this.get_selection();
        if (p.focusNode && p.focusNode.nodeType == 3) {
            p.focusNode.nodeValue=urltext; // change text
        }
    }
}

if (!Wikiwyg.is_ie) {
proto.get_selection = function() { // See IE, below
    return this.get_edit_window().getSelection();
}

proto.get_range = function() {
    return this.get_edit_document().createRange();
}

proto.get_parent_node = function() {
    var sel= this.get_edit_window().getSelection();
    var sf = sel.focusNode;
    if (sf.nodeName == 'SPAN' && sf.className && sf.className.match(/wikiMarkup/))
        return sf; // mozilla hack
    return sel.focusNode.parentNode;
}

} else {
proto.get_selection = function() {
    return this.get_edit_document().selection;
}

proto.get_parent_node = function() {
    var sel = this.get_edit_document().selection;
    var iframe = this.edit_iframe;
    var doc = iframe.contentDocument || iframe.contentWindow.document;
    if (sel == null) return doc.body;
    var range= sel.createRange();

    // from HTMLArea 3.0 parentElement() function
    switch (sel.type) {
    case "Text":
    case "None":
	// It seems that even for selection of type "None",
	// there _is_ a parent element and it's value is not
	// only correct, but very important to us.  MSIE is
	// certainly the buggiest browser in the world and I
	// wonder, God, how can Earth stand it?
	return range.parentElement();
    case "Control":
	return range.item(0);
    default:
	return doc.body;
    }
}
}


proto.do_indent = function() {
    var node=this.check_parent_node().nodeName;
    if (node && node == 'BODY')
        this.exec_command('indent');
}

proto.do_math_raw = function() {
    var node=this.check_parent_node().nodeName;
    if (node && node != 'BODY') return;

    var html =
        '<span class="wikiMarkupEdit" style="display:inline">' +
        "<!-- wiki:\n$ $\n-->" +
        '<span>$&nbsp;$</span></span>';
    this.insert_table(html);
}

proto.insert_rawmarkup = function(start, end, raw) {
    var node=this.check_parent_node().nodeName;
    if (node) {
        var wm = this.get_wikimarkup_node();
        if (wm == null) {
            var html =
                '<span class="wikiMarkupEdit" style="display:inline">' +
                "<!-- wiki:\n$ " + raw + " $\n-->" +
                '<span>$ ' + raw + ' $</span></span>';
            this.insert_table(html);
        } else {
            // Mozilla
            var sel = this.get_selection();
            var sf = sel.focusNode;

            var val = sf.nodeValue;
            if (!val) return;
            
            var st='', ed='', m, val0='',ret;
            // do we need to cleanup nodeValue ?
            // XXX
            // find tags
            if ((m = val.match(/^([^\$]*\$\s)(.*)(\s\$[^\$]*)$/))) {
                st = m[1];
                val = m[2];
                ed = m[3];
            } else {
                // XXX
            }
            if (sel.focusOffset < (st.length + val.length)) {
                val0 = val.substr(0,sel.focusOffset - st.length);
                val = val.substr(val0.length);
            } else {
                val0 = val;
                val = '';
            }

            ret = start + val0;
            var spos = ret.length + 1;
            ret += ' ' + raw;
            var epos = ret.length;
            if (val0) ret += ' ' + val;
            ret += end;

            sf.nodeValue= ret;

            var range=this.get_range();
            range.setStart(sf,spos);
            range.setEnd(sf,epos);

            sel.removeAllRanges(); // remove old ranges !
            sel.addRange(range);
        }
    }
}

proto.do_hr = function() {
    var node=this.check_parent_node().nodeName;
    if (node && node == 'BODY')
        this.exec_command('inserthorizontalrule');
}

proto.do_h2 = function(command) {
    var node=this.check_parent_node().nodeName;
    if (node && node == 'BODY')
        this.exec_command('formatblock','<' + command + '>');
}

proto.do_table = function(command) {
    var node=this.check_parent_node().nodeName;
    if (node && node != 'BODY') return;

    var html =
        '<table><tbody>' +
        '<tr><td>A</td>' +
            '<td>B</td>' +
            '<td>C</td></tr>' +
        '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>' +
        '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>' +
        '</tbody></table>';
    this.insert_table(html);
}

proto.do_ordered = function() {
    var node=this.check_parent_node().nodeName;
    if (node && node == 'BODY')
        this.exec_command('insertorderedlist');
}

proto.do_unordered = function() {
    var node=this.check_parent_node().nodeName;
    if (node && node == 'BODY')
        this.exec_command('insertunorderedlist');
}

proto.do_math = function(cmd,elm) {
    if (document.getElementById('mathChooser'))
        open_chooser('mathChooser',elm);
    else
        this.do_math_raw();

}

proto.do_smiley = function(cmd,elm) {
    if (document.getElementById('smileyChooser'))
        open_chooser('smileyChooser',elm,true);
    //else //
    //  this.insert_text_at_cursor(':)');
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

proto.config.editHeightMinimum = 20;

proto.enableThis = function() {
    Wikiwyg.Mode.prototype.enableThis.call(this);
    this.textarea.style.width = '99%';
    this.setHeightOfEditor();
    this.enable_keybindings();
}

proto.initialize_object = function() {
    this.div = document.createElement('div');
    if (this.config.textareaId)
        this.textarea = document.getElementById(this.config.textareaId);
    else
        this.textarea = document.createElement('textarea');
    this.textarea.setAttribute('id', 'wikiwyg_wikitext_textarea');
    this.textarea.setAttribute('class', 'resizable');
    this.div.appendChild(this.textarea);

    this.area = this.textarea;
    this.clear_inner_text();
}

proto.convert_html_to_wikitext = function(html) {
    this.copyhtml = html;
    var dom = document.createElement('div');

    //alert(html);

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
    html = html.replace(/<img class=.?(url|externalLink).?[^>]+>/ig, '');
    // remove upper icons
    html = html.replace(/<a[^>]+class=.?main.?[^>]+><img [^>]+><\/a>/ig, '');
    // smiley/inline tex etc.
    //html =
    //    html.replace(/<img [^>]*class=.?(tex|interwiki|smiley|external).?[^>]* alt=(\'|\")?([^\'\" ]+)\2?[^>]+>/ig, "$3");
    // interwiki links
    html =
        html.replace(/<a [^>]*alt=(.)?([^\'\"]+)\1?[^>]*>/igm, "$2");
    // remove nonexists links
    html = html.replace(/<a class=.?nonexistent.?[^>]+>([^<]+)<\/a>/igm, "$1");

    // remove toc number
    html = html.replace(/<span class=.?tocnumber.?>(.*)<\/span>/igm, '');

    // six single quotes for mozilla
    html =
        html.replace(/<span style=[^>]+bold[^>]*><\/span>/ig, "''''''");
    // remove javatag
    html =
        html.replace(/<a href=.javascript:[^>]+>(.*)<\/a>/ig, "$1");
    // unnamed externalLinks
    html =
        html.replace(/<a class=.externalLink unnamed. [^>]+>([^>]+)<\/a>/ig, "[$1]");
    // remove empty anchors
    html =
        html.replace(/<a class=.externalLink. [^>]+><\/a>/ig, "");
    // named externalLinks with a title
    html =
        html.replace(/<a class=.externalLink named. [^>]*title=(\'|\")?([^\'\"]+)\1?[^>]*>(.+)<\/a>/ig, "[$2 $3]");
    // named externalLinks
    html =
        html.replace(/<a class=.externalLink named. [^>]*href=(\'|\")?([^\'\"]+)\1?[^>]+>(.+)<\/a>/ig, "[$2 $3]");

    // inner links for IE
    var loc = location.protocol + '//' + location.host + (location.port ? ':'+location.port:'');
    this.loc_re=new RegExp('^' + loc.replace(/\//g,'\\/'),'ig');

    // escaped wiki markup blocks
    html =
        html.replace(/<tt class[^>]+>([^>]+)<\/tt>/ig, "{{{$1}}}");

    dom.innerHTML = "<br>" +html; // Bah.... IE hack :(
    dom.removeChild(dom.firstChild);

    this.output = [];
    this.list_type = [];
    this.ordered_type = [];
    this.indent_level = 0;

    this.walk_n(dom);

    // add final whitespace
    this.assert_new_line();

    //for (var i=0;i<this.output.length;i++) {
    //    if (this.output[i].length)
    //    this.output[i]=this.output[i].replace(/\\n/,"\n");
    //} XXX

    return this.join_output(this.output);
}

if (Wikiwyg.is_ie) {
proto.looks_like_a_url = function(string) {
    string = string.replace(this.loc_re, ''); // for IE
    return string.match(/^(http|https|ftp|irc|mailto|file):/);
}
}

proto.format_object = function(element) {
    var attr=['type','classid','codebase','align','data','width','height','id'];
    var attrs=[];
    for (var k in attr) {
        var v=element.getAttribute(attr[k]);
        if (v) attrs.push(attr[k]+'="'+ v + '"');
    }
    if (element.innerHTML) {
        var save_out=this.output;
        this.output=[];
        this.walk(element);
        var my=this.output.join('');
        this.output=save_out;
        this.appendOutput('[[HTML(<object '+attrs.join(' ')+'>'+ my +'</object>)]]');
    } else {
        this.appendOutput('[[HTML(<object '+attrs.join(' ')+'></object>)]]');
    }
}

proto.format_embed = function(element) {
    var attr=['src','type','data','width','height','id','wmode',
            'quality','align','allowScriptAccess','allowFullScreen','name','pluginspage'];
    var attrs=[];
    for (var k in attr) {
        var v=element.getAttribute(attr[k]);
        if (v) attrs.push(attr[k]+'="'+ v + '"');
    }
    if (element.parentNode.nodeName == 'OBJECT')
        this.appendOutput('<embed '+attrs.join(' ')+'>' + element.innerHTML +'</embed>');
    else
        this.appendOutput('[[HTML(<embed '+attrs.join(' ')+'>'+element.innerHTML+'</embed>)]]');
}

proto.format_param = function(element) {
    var attr=['name','value'];
    var attrs=[];
    for (var k in attr) {
        var v=element.getAttribute(attr[k]);
        if (v) attrs.push(attr[k]+'="'+ v + '"');
    }
    this.appendOutput('<param '+attrs.join(' ')+'></param>');
}

proto.format_img = function(element) {
    var uri='';
    uri = element.getAttribute('src');
    if (uri) {
        var style = element.getAttribute('style');
        var width = element.getAttribute('width');
        var height = element.getAttribute('height');
        var myclass = element.getAttribute('class') || element.getAttribute('className');
        if (myclass) {
            if (myclass.match(/(tex|interwiki|smiley|external)$/)) {
                if (this.output.length) {
                    var trail=this.output[this.output.length-1];
                    if (!trail.match(/\s$/)) this.appendOutput(' ');
                }
                var alt=element.getAttribute('alt');
                if (!alt.match(/attachment:/)) {
                    this.appendOutput(alt);
                    return;
                }
                uri=alt;
            }
        }

        this.assert_space_or_newline();
        if (uri.match(/^data:image\//))
            this.appendOutput('attachment:' + uri);
        else
            this.appendOutput(uri);

        var attr='';
        if (width) attr+='width='+width;
        if (height) attr+=(attr ? '&':'') + 'height='+height;

        if (style) {
            if (typeof style == 'object') style= style.cssText;
            var w = style.match(/width:\s*(\d+)px/i);
            var h = style.match(/height:\s*(\d+)px/i);
            if (w) attr+=(attr ? '&':'') + 'width='+w[1];
            if (h) attr+=(attr ? '&':'') + 'height='+h[1];
        }

        if (myclass) {
            var m = myclass.match(/img(Center|Left|Right)$/);
            if (m && m[1]) attr+=(attr ? '&':'') + 'align='+m[1].toLowerCase();
        }

        if (attr) this.appendOutput('?'+attr);
    }
}

proto.insert_new_line = function() {
    var fang = '';
    var indentChar = this.config.markupRules.indent[1];
    var newline = '\n';
    if (this.list_type.length > 0) {
        fang = indentChar.times(this.list_type.length);
        //if (fang.length) fang += ' ';
    }
    // XXX - ('\n' + fang) MUST be in the same element in this.output so that
    // it can be properly matched by chomp above.
    if (fang.length && this.first_indent_line) {
        this.first_indent_line = false;
    }
    if (this.output.length)
        this.appendOutput(newline + fang);
    else if (fang.length)
        this.appendOutput(fang);
}

proto.format_blockquote = function(element) {
    this.make_list(element,'quote');
    //this.make_list(element,'indent');
    this.chomp();
    //if (element.parentNode.tagName != 'TD') // XXX
    this.appendOutput("\n");
    return;

}

proto.format_strong = function(element) {
    var markup = this.config.markupRules['bold'];
    this.appendOutput(markup[1]);
    this.no_following_whitespace();
    this.walk(element);
    // assume that walk leaves no trailing whitespace.
    this.appendOutput(markup[2]);
}

proto.format_b = proto.format_strong;

proto.format_blockquote_old = function(element) {
    var indents = 0;
    if (element.className.toLowerCase() == 'indent')
        indents += 1;

    if (!this.indent_level)
        this.first_indent_line = true;
    this.indent_level += indents;

    this.output = defang_last_string(this.output);
    this.assert_new_line();

    this.walk(element);
    this.indent_level -= indents;

    if (! this.indent_level) {
        if (this.should_whitespace()) {
            this.chomp();
            this.appendOutput("\n");
        }
    } else {
        this.chomp();
        this.appendOutput("\n");
    }

    function defang_last_string(output) {
        function non_string(a) { return typeof(a) != 'string' }

        // Strategy: reverse the output list, take any non-strings off the
        // head (tail of the original output list), do the substitution on the
        // first item of the reversed head (this is the last string in the
        // original list), then join and reverse the result.
        //
        // Suppose the output list looks like this, where a digit is a string,
        // a letter is an object, and * is the substituted string: 01q234op.

        var rev = output.slice().reverse();                     // po432q10
        var rev_tail = takeWhile(non_string, rev);              // po
        var rev_head = dropWhile(non_string, rev);              // 432q10

        if (rev_head.length)
            rev_head[0].replace(/^[ ]+/, '');                     // *32q10
            //rev_head[0].replace(/^>+/, '');                     // *32q10

        // po*3210 -> 0123*op

        return rev_tail.concat(rev_head).reverse();             // 01q23*op
    }
}

proto.format_table = function(element) {
    this.assert_blank_line();
    var style =element.getAttribute('style');
    var cls =element.getAttribute('class') ||
        element.getAttribute('className') || '';
    var width =element.getAttribute('width');
    var color =element.getAttribute('bgcolor');
    var m;
    this.myattr='';

    if (m = cls.match(/(right|center)/)) {
        this.myattr+= '<tablealign="'+m[1]+'">';
    }

    if (width) {
        this.myattr+= '<tablewidth="'+width + 'px">';
    } else 
    if (style) {
        if (typeof style == 'object') style= style.cssText;
        var attr='';
        var m = style.match(/width:\s*(\d+)px;\s*height:\s*(\d+)px/i);
        if (m)
            attr='<tablewidth="'+m[1] + 'px" height="'+m[2]+'px">';
        
        if (attr != '') this.myattr+=attr;
    }
    this.walk(element);
    this.chomp();
    //this.appendOutput("\n");
    this.smart_trailing_space_n(element);
    //this.assert_blank_line();
}

proto.format_tr = function(element) {
    this.walk(element);
    this.appendOutput('||');
    this.insert_new_line();
}

proto.format_br = function(element) {
    var str1 = this.output[this.output.length - 1];
    var str2 = (this.output.length) > 2 ? this.output[this.output.length - 2]:null;

    var pn=element.parentNode.nodeName;
    if (pn && (pn == 'TD' || pn.match(/^H[1-6]$/))) {
        this.output.pop();
        this.appendOutput(str1 + "&\n");
        return;
    }

    if (str1 && ! str1.whitespace && !str1.match(/\n$/)) {
        this.output.pop();
        this.appendOutput(str1 + "\n");
    } else {
        this.appendOutput("\n");
        //this.insert_new_line();
    }
}

proto.format_tt = function(element) {
    this.appendOutput('`');
    this.walk(element);
    this.appendOutput('`');
}

proto.make_wikitext_link = function(label, href, element) {
    var before = this.config.markupRules.link[1];
    var after  = this.config.markupRules.link[2];

	// handle external links
	if (this.looks_like_a_url(href)) {
		before = this.config.markupRules.www[1];
		after = this.config.markupRules.www[2];
	}
	
    this.assert_space_or_newline();
    if (! href) {
        this.appendOutput(label);
    }
    else if (href == label) {
        this.appendOutput(href);
    }
    else if (this.href_is_wiki_link(href)) {
        var title = element.getAttribute('title');
        if (title && title != label) {
            this.appendOutput(before + ':' + title + ' ' + label + after);
        } else if (this.camel_case_link(label))
            this.appendOutput(label);
        else
            this.appendOutput(before + label + after);
    }
    else {
        this.appendOutput(before + href + ' ' + label + after);
    }
}

proto.format_span = function(element) {
    if (this.is_opaque(element)) {
        this.handle_opaque_phrase(element);
        return;
    }

    var style = element.getAttribute('style');
    if (!style) {
        this.pass(element);
        return;
    }

    if (   ! this.element_has_text_content(element)
        && ! this.element_has_only_image_content(element)) return;

    if (typeof style == 'object') style= style.cssText.toLowerCase();
    if (style.match(/font-size|color/i)) {
        this.appendOutput('{{{{'+style+'}');
        this.walk(element);
        this.appendOutput('}}}');
        this.smart_trailing_space_n(element);
        return;
    }
    var attributes = [ 'line-through', 'bold', 'italic', 'underline' ];
    for (var i = 0; i < attributes.length; i++)
        this.check_style_and_maybe_mark_up(style, attributes[i], 1);
    this.no_following_whitespace();
    this.walk(element);
    for (var i = attributes.length; i >= 0; i--)
        this.check_style_and_maybe_mark_up(style, attributes[i], 2);
}

proto.assert_blank_line = function() {
    if (! this.should_whitespace()) return;
    this.chomp_n(); // FIX
    var str = this.output[this.output.length - 1];
    if (str) {
        if (!str.match(/\|\|/)) // is it TD ? XXX FIXME
            this.insert_new_line();
    } else {
        this.insert_new_line();
    }
    //this.insert_new_line(); // FIX for line_alone (----)
}

proto.handle_line_alone = function (element, markup) {
    if (element.parentNode.tagName != 'TD')
        this.assert_blank_line();
    this.appendOutput(markup[1]);
    this.assert_blank_line();
}

proto.handle_bound_line = function(element,markup) {
    if (element.parentNode.tagName != 'TD')
        this.assert_blank_line();
    this.appendOutput(markup[1]);
    this.walk(element);
    this.appendOutput(markup[2]);
    this.assert_blank_line();
}

proto.format_td = function(element) {
    var colspan =element.getAttribute('colspan');
    var width =element.getAttribute('width');
    var color =element.getAttribute('bgcolor');
    var align =element.getAttribute('class') || element.getAttribute('className');
    var style =element.getAttribute('style');
    var attr= [];
    var i=0;

    colspan = colspan ? colspan:1;
    if (this.has_caption) i=1;
    this.has_caption=false;

    for (;i<colspan;i++) this.appendOutput('||');

    if (this.myattr)
        this.appendOutput(this.myattr);
    this.myattr='';
    //

    if (width)
        attr.push('width="'+width+'"');
    if (color)
        attr.push('bgcolor="'+color+'"');
    if (style) {
        if (typeof style == 'object') style= style.cssText;
        var m;
        m = style.match(/width:\s*(\d+)px/i);
        if (m) attr.push('width="'+m[1] + 'px"');
        m = style.match(/background-color:\s*([^;]+);?/i);
        if (m) attr.push('bgcolor="'+m[1]+'"');
    }

    var rowspan =element.getAttribute('rowspan');
    if (rowspan > 1)
        this.appendOutput('<|'+rowspan+'>');
    if (attr.length)
        this.appendOutput('<'+attr.join(' ')+'>');

    // to support the PmWiki style table alignment
    // firstChild have to be a text node
    // BUT IE ignore the firstChild if it is spaces!!
    // and you can't get raw spaces info from DOM elements
    //   see also http://www.javascriptkit.com/domref/nodetype.shtml
    // but there is a simple way to workaround this situation!
    // like as following line. Wow !! :>
    if (Wikiwyg.is_ie && align.match(/right|center/)) this.appendOutput(' ');
    this.walk_n(element);
    this.appendOutput('');
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
                var str = part.nodeValue.replace(/^\n/,'');
                if (this.no_collapse_text) {
                    this.appendOutput(str);
                }
                else {
                    this.appendOutput(this.collapse(str));
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
        var str = string.replace(/&?[\r\n]+/, '');
        if (str) this.appendOutput(str);
        //if (string != str) this.appendOutput("\n"); // FIXME !!!
    }
}

proto.format_caption = function(element) {
    this.appendOutput('|');
    this.walk(element);
    this.appendOutput('|');
    this.has_caption=true;
}

proto.format_div = function(element) {
    if (this.is_opaque(element)) {
        this.handle_opaque_block(element);
        return;
    }
    if (this.is_indented(element)) {
        //this.format_blockquote(element);
        var cls = element.getAttribute('class') ||element.getAttribute('className');
        if (cls && cls.match(/quote/))
            this.make_list(element,'quote');
        else
            this.make_list(element,'indent');
        this.chomp();
        if (element.parentNode.tagName != 'TD') // XXX
        this.appendOutput("\n");
        return;
    }
    this.walk(element);
}

proto.format_p = function(element) {
    if (this.is_indented(element)) {
        this.format_blockquote(element);
        return;
    }
    if (element.parentNode.tagName != 'TD') // XXX
        this.assert_blank_line();
    this.walk(element);
    this.assert_blank_line();
}

proto.make_list = function(element, list_type) { 
    //this.assert_new_line();

    if (! this.previous_was_newline_or_start()) {
        if (element.parentNode.tagName != 'TD') // XXX
            this.appendOutput("\n");
        // this.insert_new_line();
    }

    this.list_type.push(list_type);
    if (this.list_type.length) {
        this.div_tag = '';
        if (list_type == 'indent' || list_type == 'quote') {
            var id = element.getAttribute('id');
            if (id) {
                if (id) this.div_tag = '#' + id;
            } else {
                var cls = element.getAttribute('class') ||element.getAttribute('className');
                var tag = cls.replace(/quote|indent/g,'').replace(/(^\s*|\s*$)/g,'');
                if (tag) this.div_tag = '.' + tag;
            }
        }
        this.first_indent_line = true;
    }

    this.walk(element);
    this.first_indent_line=false;
    this.list_type.pop();
}


proto.format_ol = function(element) {
    var type = element.getAttribute('type');
    var start = element.getAttribute('start') || null;
    if (start == 1) start = null; // IE fix

    this.ordered_type.push(type);
    this.ordered_start=start;

    this.make_list(element, 'ordered');
    this.ordered_type.pop();
}

proto.format_li = function(element) {
    var level = this.list_type.length;
    if (!level) die("List error");
    var type = this.list_type[level - 1];
    var markup = this.config.markupRules[type][1];
    var ind = ' ';
    var start = '';

    if (type == 'ordered' && this.ordered_type[this.ordered_type.length - 1]) {
        markup = ' ' + this.ordered_type[this.ordered_type.length - 1] + '.';
    }

    if (this.ordered_start) {
        start = '#' + this.ordered_start;
        this.ordered_start = null;
    }
    
    this.appendOutput(ind.times(level-1) + markup + start + ' ');


    //alert(element.innerHTML);

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

    this.walk_li(element,markup,level);

    this.chomp();
    this.appendOutput("\n");
}

proto.walk_li = function(element,markup,level) {
    if (!element) return;

    var ind = ' ';
    var myind = ind.times(level-1) + ind.times(markup.length) + ' ';
    var myind0 = ind.times(level-1) + markup;
    var mre=new RegExp('^'+myind0);

    for (var part = element.firstChild; part; part = part.nextSibling) {
        if (part.nodeType == 1) {
            this.dispatch_formatter(part);
        }
        else if (part.nodeType == 3) {
            var item = part.nodeValue.replace(/\n$/,'');
            part.nodeValue=item;
            if (part.nodeValue == '') continue;
            if (Wikiwyg.is_ie) {
                if (this.output.length &&
                        !this.output[this.output.length - 1].match(mre))
                    item = myind + item;
                this.appendOutput(item);
            }
            else if (item.length > 0 && item.indexOf("\n") != -1) {
                item = item.replace(/\n/g,"\n" + myind);
                item = item.replace(/^\n/,'');
                this.appendOutput(item);
            }
            else if (part.nodeValue.match(/^[ ]*$/)) {
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

proto.do_indent = function() {
    this.selection_mangle(
        function(that) {
            if (that.sel == '') return false;
            if (that.sel.match(/^\s*(=+)\s+.*\s+\1\s?$/)) return false;
            that.sel = that.sel.replace(/^(\>\s)/gm, '$1$1');
            that.sel = that.sel.replace(/^/gm, ' '); // space indent
            that.sel = that.sel.replace(/^ (\>\s)/gm, '$1');
            return true;
        }
    )
}

proto.do_quote = function() {
    this.selection_mangle(
        function(that) {
            if (that.sel == '') return false;
            if (that.sel.match(/^\s*(=+)\s+.*\s+\1\s?$/)) return false;
            that.sel = that.sel.replace(/^/gm, '> ');
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

proto = Wikiwyg.HTML.prototype;

proto.initializeObject = function() {
    this.div = document.createElement('div');
    if (this.config.textareaId)
        this.textarea = document.getElementById(this.config.textareaId);
    else
        this.textarea = document.createElement('textarea');
    this.textarea.setAttribute('class','resizable');
    this.div.appendChild(this.textarea);
}

proto = Wikiwyg.Toolbar.prototype;

proto.make_button = function(type, label) {
    var base = this.config.imagesLocation;
    var ext = this.config.imagesExtension;
    return Wikiwyg.createElementWithAttrs(
        'img', {
            'class': 'wikiwyg_button',
            alt: _(label),
            title: _(label),
            src: base + type + ext
        }
    );
}

proto.addControlItem = function(text, method,arg) {
    var span = Wikiwyg.createElementWithAttrs(
        'span', { 'class': 'wikiwyg_control_link' }
    );

    var link = Wikiwyg.createElementWithAttrs(
        'button', {
            type: 'button',
            value: _(text)
        }
    );

    var btn = document.createElement('span');
    btn.appendChild(document.createTextNode(_(text)));
    link.appendChild(btn);
    span.appendChild(link);

    var self = this;
    if (arg) {
        method=method+'("'+arg+'")';
        this.controls=this.controls ? ','+arg:arg;
    } else method=method+'()';
    link.onclick = function() { eval('self.wikiwyg.' + method); return false; };

    this.div.appendChild(span);
}

proto.config.controlLayout = [
    'save', /* 'preview', */ 'cancel', 'mode_selector', '/',
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
    'media',
    'smiley'
];

proto.config.controlLabels = {
    save: N_("Save"),
    preview: N_("Preview"),
    cancel: N_("Cancel"),
    bold: N_("Bold (Ctrl+b)"),
    italic: N_("Italic (Ctrl+i)"),
    underline: N_("Underline (Ctrl+u)"),
    strike: N_("Strike Through (Ctrl+d)"),
    hr: N_("Horizontal Rule"),
    ordered: N_("Numbered List"),
    unordered: N_("Bulleted List"),
    indent: N_("More Indented"),
    outdent: N_("Less Indented"),
    help: N_("About Wikiwyg"),
    label: N_("[Style]"),
    p: N_("Normal Text"),
    pre: N_("Preformatted"),
    h1: N_("Heading 1"),
    h2: N_("Heading 2"),
    h3: N_("Heading 3"),
    h4: N_("Heading 4"),
    h5: N_("Heading 5"),
    h6: N_("Heading 6"),
    link: N_("Create Link"),
    smiley: N_("Smiley"),
    unlink: N_("Remove Linkedness"),
    table: N_("Create Table"),
    math: N_("Math"),
    nowiki: N_("As Is"),
    image: N_("Image"),
    media: N_("Media"),
    quote: N_("Quote")
};

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

proto.do_nowiki = Wikiwyg.Wikitext.make_do('nowiki');
if (Wikiwyg.Wikitext.make_format) // Wikiwyg-0.12
    proto.format_image = Wikiwyg.Wikitext.make_format('image');
else // Wikiwyg snapshot
    proto.format_image = Wikiwyg.Wikitext.make_formatter('image');
proto.do_image = Wikiwyg.Wikitext.make_do('image');
proto.do_media = Wikiwyg.Wikitext.make_do('media');
//proto.do_quote = Wikiwyg.Wikitext.make_do('quote');
//
proto.do_math_tag = Wikiwyg.Wikitext.make_do('math');
proto.do_math = function(cmd,elm) {
    if (document.getElementById('mathChooser'))
        open_chooser('mathChooser',elm);
    else
        this.do_math_tag(cmd,elm);
}

proto.do_smiley = function(cmd,elm) {
    if (document.getElementById('smileyChooser'))
        open_chooser('smileyChooser',elm,true);
    else
        this.insert_text_at_cursor(':)');
}

proto.collapse = function(string) {
    return string.replace(/\r\n|\r/g, ''); // FIX
    //return string.replace(/\r\n|\r/g, "\n"); // FIX
}

proto.get_macro_args = function(arg,attr) {
    var attrs=[];
    var vals=arg.split(/,/);
    for (var i=0;i < vals.length;i++) {
        var p;
        var v=vals[i].replace(/^\s+/,'').replace(/\s+$/,'');
        if ((p=v.indexOf('='))!= -1) {
            var k=v.substr(0,p);
            var vv=v.substr(p+1);
            attrs[k]=vv;
        } else
            attrs[v]='';
    }
    attrs['width']=attr['width'];
    attrs['height']=attr['height'];
    if (attr['align']) attrs['align']=attr['align'];

    var args=[];
    for (var key in attrs) {
        if (typeof attrs[key] == 'function') continue;
        var val=attrs[key];
        args.push(key + (val ? '=' + val:''));
    }
    return args.join(',');
}

proto.get_wiki_comment = function(element) {
    for (var node = element.firstChild; node; node = node.nextSibling) {
        if (node.nodeType == this.COMMENT_NODE_TYPE
            && node.data.match(/^\s*wiki/)) {
            // for <span class='imgAttach'><img src=...
            //var ele=node.nextSibling ? node.nextSibling.firstChild:null;
            // for <span class='imgAttach'><a href=''><img src=...
            //if (ele.tagName == 'A') ele=ele.firstChild;
            var ele=node.parentNode.getElementsByTagName('img')[0];
            var b;

            if (ele && (b=node.data.match(/\n(\[+)?attachment(:|\()/i)) && ele.tagName == 'IMG') {
                // check the attributes of the attached images
                var style = ele.getAttribute('style');
                var width = ele.getAttribute('width');
                var height = ele.getAttribute('height');
                var myclass = ele.getAttribute('class') || ele.getAttribute('className');
                var align = '';

                var attr={};

                if (width) attr["width"]=width;
                if (height) attr["height"]=height;
                ele.setAttribute('width','');
                ele.setAttribute('height','');

                if (style) {
                    if (typeof style == 'object') style = style.cssText;
                    var m = style.match(/width:\s*(\d+)px;\s*height:\s*(\d+)px/);
                    if (m) {
                        if (m[1]) attr["width"]=m[1];
                        if (m[2]) attr["height"]=m[2];
                        ele.setAttribute('style',null);
                    }
                }

                if (myclass) {
                    var m = myclass.match(/img(Center|Left|Right)$/);
                    if (m && m[1]) {
                        attr["align"]=m[1].toLowerCase();
                        align=attr["align"];
                    }
                }

                var newquery='';
                if (attr) {
                    var tattr=new Array();

                    for (var key in attr) {
                        if (typeof attr[key] == 'function') continue;
                        var value = key + '='+attr[key];
                        tattr.push(value);
                    }

                    newquery=tattr.join("&");

                    var p=node.data.indexOf("?");
                    var orig='';
                    var oldquery='';
                    if (p != -1) {
                        orig=node.data.substr(0,p);
                        oldquery=node.data.substr(p+1);
                        p=oldquery.indexOf(" ");
                        if (p != -1) oldquery=oldquery.substr(0,p);
                    }
                    if (oldquery) {
                        oldquery = oldquery.replace(/\n+$/,""); // strip \n
                        var oldattr=oldquery.split("&");
                        var newattr=new Array();
                        for (var j=0;j<oldattr.length;j++) {
                            var dum=oldattr[j].split("=");
                            if (!width && dum[0] == "width") {
                                newattr["width"]=dum[1];
                            } else if (!height && dum[0] == "height") {
                                newattr["height"]=dum[1];
                            } else if (!align && dum[0] == "align") {
                                newattr["align"]=dum[1];
                            }
                        }
                        if (newattr) {
                            var tattr=[];
                            for (var key in newattr) {
                                if (typeof newattr[key] == 'function') continue;
                                var value = key + '=' + newattr[key];
                                tattr.push(value);
                            }
                            var old=tattr.join("&");
                            newquery=newquery ? (old+'&'+newquery):old;
                        } else {
                            newquery=oldquery+'&'+newquery;
                        }
                        if (b[1]) {
                            if (b[1]=='[')
                                node.data= node.data.replace(/(attachment:[^\s\?]+)\?[^\s]+(\s)/,'$1?'+newquery+' ');
                            else {
                                var m= node.data.match(/Attachment\((.*)\)\]/);
                                if (m[1]) {
                                    var arg = this.get_macro_args(m[1],attr);
                                    node.data= node.data.replace(/Attachment\((.*)\)/,
                                            'Attachment(' + arg + ')');
                                }
                            }
                        }
                        else node.data=orig+'?'+newquery;
                    } else {
                        node.data = node.data.replace(/\n+$/,""); // strip \n
                        if (newquery) {
                            if (b[1]) {
                                if (b[1]=='[')
                                    node.data= node.data.replace(/(attachment:[^\s]+)(\s)/,'$1'+'?'+newquery+' ');
                                else {
                                    var m= node.data.match(/Attachment\((.*)\)\]/);
                                    if (m[1]) {
                                        var arg = this.get_macro_args(m[1],attr);
                                        node.data= node.data.replace(/Attachment\((.*)\)/,
                                            'Attachment(' + arg + ')');
                                    }
                                }
                            } else
                                node.data+='?'+newquery;
                        }
                    }

                    return node;
                }
            }
            else if (node.data.match(/&lt;object/i) || node.data.match(/ wiki:\n\{\{\{#\![^ ]+/)) {
                var n=node.nextSibling;
                if (n.tagName != 'IMG') {
                    var n=n.firstChild;
                    while (n) {
                        if (n.tagName == 'IMG') break;
                        n=n.nextSibling;
                    }
                }

                if (n && n.style) {
                    var width = n.style.width;
                    var height = n.style.height;
                    node.data = node.data.replace(/width=([^\s]+)/ig,'width="'+width+'"')
                        .replace(/height=([^\s]+)/ig,'height="'+height+'"');
                    //    .replace(/width:\s*([0-9]+px)/ig,'width:'+width)
                    //    .replace(/height:\s*([0-9]+px)/ig,'height:'+height);

                    var re=new RegExp('wiki:\n({{{#![^ \n]+)([^\n]*)\n');
                    var m = node.data.match(re);
                    if (m && width && height) {
                        var w = width.replace(/[^0-9]/g,'');
                        var h = height.replace(/[^0-9]/g,'');
                        var nm= w+'x'+h;
                        if (m[2]) {
                            if (m[2].match(/\b[0-9]+x[0-9]+\b/)) nm = m[2].replace(/[0-9]+x[0-9]+/,nm);
                            else nm = m[2] + ' ' + nm;
                        } else
                            nm = ' ' + nm;

                        if (m) node.data =
                            ' ' + 'wiki:\n'+ m[1]+nm +'\n' + node.data.substr(m[0].length+1);
                    }
                }
            }
            return node;
        }
    }
    return null;
}

proto.is_indented = function (element) {
    var cls = element.getAttribute('class') ||element.getAttribute('className');
    return cls && cls.match(/indent/);
}

proto.handle_opaque_phrase = function(element) {
    var comment = this.get_wiki_comment(element);
    if (comment) {
        var text = comment.data;
        text = text.replace(/^ wiki:(\s|\\n)+/, '')
                   .replace(/-=/g, '-')
                   .replace(/==/g, '=')
                   .replace(/&amp;/g,'&')
                   .replace(/(\r\n|\n|\r)+$/, '') //.replace(/\s$/, '') IE fix
                   .replace(/\{(\w+):\s*\}/, '{$1}');
        this.appendOutput(Wikiwyg.htmlUnescape(text));
        this.smart_trailing_space_n(element);
    }
}

proto.smart_trailing_space_n = function(element) {
    var next = element.nextSibling;
    if (! next) {
        // do nothing
    }
    else if (next.nodeType == 1) {
        if (next.nodeName == 'BR') {
            var nn = next.nextSibling;
            if (! (nn && nn.nodeType == 1 && nn.nodeName == 'SPAN') && nn.nodeType != 3) {
                this.appendOutput('\n');
                ////// XXX alert(nn.nodeName + nn.nodeType);
            }
        }
        else {
            if (next.nodeName != 'SPAN')
                this.appendOutput('\n'); // for comments and PIs FIXME
        }
    }
    else if (next.nodeType == 3) {
        if (Wikiwyg.is_ie && this.output.length) { // IE innerHTML space hack
            str = this.output[this.output.length - 1];
            if (str.length) {
                var str1 = str.length ? str.substr(str.length-1):''; // XXX
                var str2 = (str.length > 3 && str1 == '}') ? str.substr(str.length-3):''; // XXX
                if (str.indexOf("\n") != -1 && str2 == '}}}') {
                    //var save = this.output.pop();
                    //this.appendOutput("\n");
                    //this.appendOutput(save);
                    this.appendOutput("\n");
                }
            }
        }
        if (! next.nodeValue.match(/^\s/)) {
            this.no_following_whitespace();
        } else if (next.nodeValue.match(/\n/) && next.nodeValue.match(/^\s+$/)){
            this.appendOutput(next.nodeValue);
        }
    }
}

proto.walk = function(element) {
    if (!element) return;
    for (var part = element.firstChild; part; part = part.nextSibling) {
        if (part.nodeType == 1) {
            this.dispatch_formatter(part);
        }
        else if (part.nodeType == 3) {
            var level = this.list_type.length;

            if (part.nodeValue.match(/\S/)) {
                var str = part.nodeValue;
                //if (! string.match(/^[\'\.\,\?\!\)]/)) {
                    //this.assert_space_or_newline(); // FIX
                    //string = this.trim(string); // FIX
                    //string = this.mytrim(string); // replace
                //}
                // XXX do not auto insert/delete white spaces!!!
                //string = this.mytrim(string); // replace
                //this.appendOutput(this.collapse(string)); // FIX
                //this.appendOutput(string);
                if (level) {
                    var ind=' ';
                    var indent;
                    var type = this.list_type[level - 1];
                    var markup = this.config.markupRules[type][1];
                    if (type.match(/ordered/)) {
                        indent = ind.times(level)
                            + ind.times(markup.length) + ' ';
                    } else {
                        if (markup == '>') ind = markup + ind; // markup specific XXX
                        indent = ind.times(level);
                        if (this.first_indent_line) {
                            if (this.div_tag) {
                                var myindent=indent.substr(0,indent.length-1);
                                this.appendOutput(myindent + this.div_tag + ' ');
                            } else 
                                this.appendOutput(indent);
                            this.first_indent_line=false;
                        }
                    }

                    str = str.replace(/\n$/,''); // remove trailing \n
                    if (str.length > 0 && str.indexOf("\n") != -1) {
                        str = str.replace(/\n/g,"\n" + indent);
                        str = str.replace(/^\n/,'');
                        this.appendOutput(str);
                    } else if (str.length) {
                        this.appendOutput(str);
                    }
                } else if (str.length) {
                    this.appendOutput(str);
                }
            }
            else if (part.nodeValue.match(/^[ ]*$/)) {
                this.appendOutput(part.nodeValue);
            }
            else if (part.nodeValue.match(/[^\n]/)) {
                var str = part.nodeValue.replace(/^\n/,'');
                if (this.no_collapse_text) {
                    this.appendOutput(str);
                }
                else {
                    this.appendOutput(this.collapse(str));
                }
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
    this.assert_space_or_newline_n(); // FIX XXX
    this.appendOutput(markup[1]);
    this.no_following_whitespace();
    this.walk(element);
    // assume that walk leaves no trailing whitespace.
    this.appendOutput(markup[2]);
}

proto.assert_space_or_newline_n = function() {
    var string;
    if (! this.output.length) return;

    string = this.output[this.output.length - 1];
    if (string.length) {
        var str = string.length ? string.substr(string.length-1):''; // XXX
        var str2 = (string.length > 3 && str == '}') ? string.substr(string.length-3):''; // XXX
        if (Wikiwyg.is_ie && str2 == '}}}')
            this.appendOutput("\n");
        else
        if (! str.whitespace && ! str.match(/(\s+|[>\|\"\':])$/)) {
            //alert(str);
            this.appendOutput(' ');
        }
    }
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
    return label.match(/^[A-Z]([A-Z]+[0-9a-z]|[a-z0-9]+[A-Z])[0-9a-zA-Z]*\b/);
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
    var postdata = 'action=markup/ajax&value=' + encodeURIComponent(wikitext);

    HTTPPost(
        self.location,
        postdata,
        func);
}

proto.convertWikitextToHtmlAll = function(wikitext, func) {
    var postdata = 'action=markup/ajax&all=1&value=' + encodeURIComponent(wikitext);
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

    //alert(edit.innerHTML);
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

function sectionEdit(ev,obj,sect,mode) {
    var area;
    var text=null;
    var form=null;
    var area_id="WikiWygArea";
    area_id += '_' + wikiwygs.length;

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
        var textarea=area.getElementsByTagName('textarea')[0].value;
        form=area.innerHTML;

        if (obj == null && confirm('Continue to edit current text ?') )
            text=textarea;
        else if (obj == false) // continue to confirm :)
            text=textarea;
        // else // ignore all and restart

        var toolbar=document.getElementById('toolbar');
        if (toolbar) { // hide toolbar
            if (Wikiwyg.is_ie) toolbar.style.display='none';
            else toolbar.setAttribute('style','display:none');
        }
    }

    while (area && wikiwygs.length) {
        var x= area.previousSibling;
        while (x && x.nodeType != 1) {
            x = x.previousSibling;
        }
        if (!x) break;
       
        var mycheck= x.getAttribute('id');
        if (mycheck && mycheck.match(/WikiWygArea/)) {
            var tmp = mycheck.split(/_/); // get already loaded WikiWygArea XXX hack

            wikiwygs[tmp[1]].editMode(form,text,mode);
            return;
        }
        break;
    }

    if (form && form.substring(0,5) != 'false') {
            var myConfig = {
                doubleClickToEdit: true,
                wysiwyg: {
                    iframeId: 'default-iframe'
                },
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
            myWikiwyg.createWikiwygArea(area, myConfig, area_id);
            wikiwygs.push(myWikiwyg);
            myWikiwyg.editMode(form,text,mode);

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

function getPos(el) {
  var sLeft = 0, sTop = 0;
  var isDiv = /^div$/i.test(el.tagName);
  if (isDiv && el.scrollLeft) {
    sLeft = el.scrollLeft;
  }
  if (isDiv && el.scrollTop) {
    sTop = el.scrollTop;
  }
  var r = { x: el.offsetLeft - sLeft, y: el.offsetTop - sTop };
  if (el.offsetParent) {
    var tmp = absolutePosition(el.offsetParent);
    r.x += tmp.x;
    r.y += tmp.y;
  }
  return r;
}

function open_chooser(id,elm,once) {
    var base = location.href.replace(/(.*?:\/\/.*?\/).*/, '$1');

    var div = document.getElementById(id);
    if (!div) return;

    if (div.style.display == 'block') div.style.display='none';
    else div.style.display='block';
    if (div.style.position != 'absolute') {
        div.style.display='block';
        div.style.position='absolute';
    }

    var pos=getPos(elm);
/*
    div.style.top = elm.offsetTop + 21 + 'px';
    div.style.left = elm.offsetLeft + 'px';
*/
    div.style.top = pos.y + 21 + 'px';
    div.style.left = pos.x + 'px';
    div.style.width = '500px';
    if (once) div.onclick= function () { this.style.display='none'};
}

// vim:et:sts=4:sw=4:
