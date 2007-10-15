//
// Moniwiki Autocompleter using the scriptaculous or Mootools
// $Id$
//

function setGotoFormId(formid,id) {
    var inp= document.getElementById(formid);
    var ok=false;
    if (inp) {
        var val= inp.getElementsByTagName('input');
        if (!val) {
            return false;
        }
        for (var i=0;i<val.length;i++) {
            if (val[i].name== 'value') {
                val[i].setAttribute('id',id);
                return true;
            }
        }
    }
    return false;
}

if (Ajax.Autocompleter) { // for prototype.js
    Ajax.Autocompleter.prototype.onKeyPress = function(event) {
    if(this.active)
        switch(event.keyCode) {
        case Event.KEY_TAB:
        case Event.KEY_RETURN:
            this.selectEntry();
            Event.stop(event);
        case Event.KEY_ESC:
            this.hide();
            this.active = false;
            Event.stop(event);
            return;
        case Event.KEY_LEFT:
        case Event.KEY_RIGHT:
            return;
        case Event.KEY_UP:
            this.markPrevious();
            this.render();
            if(navigator.appVersion.indexOf('AppleWebKit')>0) Event.stop(event);
            return;
        case Event.KEY_DOWN:
            this.markNext();
            this.render();
            if(navigator.appVersion.indexOf('AppleWebKit')>0) Event.stop(event);
            return;
        }
        else
        if(event.keyCode==Event.KEY_TAB || event.keyCode==Event.KEY_RETURN ||
            (navigator.appVersion.indexOf('AppleWebKit') > 0 && event.keyCode == 0)) return;

        if (!document.all) { // mozilla hack to workaround keypress/keydown event problem with IME
            if (event.keyCode == 229) {
                var self=this;
                this.oldToken=this.getToken();
                if (this.mozFixKeydown == null)
                this.mozFixKeydown = setInterval(function()
                    { var newToken=self.getToken();
                        if (newToken != self.oldToken) {
                            self.active=false;self.getUpdatedChoices();self.oldToken=newToken;
                        } 
                    }, 100);
            } else {
                if (this.mozFixKeydown)
                clearInterval(this.mozFixKeydown);
                this.mozFixKeydown= null;
        }
    }

    this.changed = true;
    this.hasFocus = true;

    if(this.observer) clearTimeout(this.observer);
        this.observer =
            setTimeout(this.onObserverEvent.bind(this), this.options.frequency*1000);
    }
} else { // mootools r1.11
    Autocompleter.Ajax.Moni = Autocompleter.Ajax.Base.extend({
        options: {
            parseChoices: null
        },
    
        queryResponse: function(resp) {
            // workaround for moniwiki
            var tmp = new Element('ul');
            tmp.setHTML(resp);
            resp = tmp.getFirst().innerHTML;
            //
            this.parent(resp);
            if (!resp) return;
            this.choices.setHTML(resp).getChildren().each(this.options.parseChoices || this.parseChoices, this);
            this.showChoices();
        },

        parseChoices: function(el) {
            var value = el.innerHTML;
            el.inputValue = value;
            el.setHTML(this.markQueryValue(value));
        },

        onCommand: function(e, mouse) {
            if (mouse && this.focussed) this.prefetch();

            if (e.key && !document.all && e.code != 229) { // FIXME
                if (this.mozFixKeydown) {
                    clearInterval(this.mozFixKeydown);
                }
                this.mozFixKeydown= null;
            }

            if (e.key && !e.shift) switch (e.key) {
                case 'enter':
                    if (this.selected && this.visible) {
                        this.choiceSelect(this.selected);
                        e.stop();
                    } return;
                case 'up': case 'down':
                    if (this.queryValue === null) break;
                    else if (!this.visible) this.showChoices();
                    else {
                        if (this.observer.value != (this.value || this.queryValue)) {
                            this.prefetch();
                        }
                        this.choiceOver((e.key == 'up')
                            ? this.selected.getPrevious() || this.choices.getLast()
                            : this.selected.getNext() || this.choices.getFirst() );
                        this.setSelection();
                    }
                    e.stop(); return;
                case 'esc': this.hideChoices(); return;
            }

            if (e.key && !document.all) { // mozilla hack to workaround keypress/keydown event problem with IME
                if (e.code == 229) {
                    var self=this;
                    // FIXME
                    if (this.mozFixKeydown == null) {
                        this.mozFixKeydown = setInterval(function() {
                            self.prefetch();
                        }, 1000);
                    }
                } else {
                    if (this.mozFixKeydown) {
                        clearInterval(this.mozFixKeydown);
                    }
                    this.mozFixKeydown= null;
                }
            }
    
            this.value = false;
        },
        setSelection: function() {
            if (!this.options.useSelection) return;
            var startLength = this.queryValue.length;
            //if (this.element.value.indexOf(this.queryValue) != 0) return;
            var insert = this.selected.inputValue.substr(startLength);
            if (document.getSelection) {
                this.element.value = this.selected.inputValue;
                //this.element.value = this.queryValue + insert;
                this.element.selectionStart = startLength;
                this.element.selectionEnd = this.element.value.length;
            } else if (document.selection) {
                var sel = document.selection.createRange();
                sel.text = insert;
                sel.move("character", - insert.length);
                sel.findText(insert);
                sel.select();
            }

            this.value = this.observer.value = this.element.value;
        }
    });
}


function initGotoAutoCompleter(id,choiceid,action,method) {
    if (id == null || id == undefined)
        return false;

    if (Ajax.Autocompleter) { // for prototype.js
        if (typeof id == 'string')
            if (!document.getElementById(id)) return false;
        var div= document.createElement('div');
        div.setAttribute('id',choiceid);
        div.setAttribute('class','autocomplete');
        div.setAttribute('style','display: none');
        document.body.appendChild(div);

        var ac= new Ajax.Autocompleter(id, choiceid,
            action, {method: method,paramName: 'q',minChars: 1});
    } else { // mootools
       var ac = new Autocompleter.Ajax.Moni(id, action, {
            'postData': {html: 1},
            'postVar': 'q',
            'ajaxOptions': {method: method},
            'parseChoices': function(el) {
                var value = el.innerHTML;
                el.inputValue = value;
                // add mouseover events
                this.addChoiceEvents(el).setHTML(this.markQueryValue(value));
            }
        });
    }

    return true;
}

if (typeof window.onload != 'function') {
    function _oldOnload() {
    }
} else {
    var _oldOnload = window.onload;
}

window.onload = function() {
    _oldOnload();
    if (setGotoFormId('go','autocomplete_goto')) {
        initGotoAutoCompleter('autocomplete_goto','autocomplete_choices',"?action=titleindex",'get');
    }
}

// vim:et:sts=4:sw:
