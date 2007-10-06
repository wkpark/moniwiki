//
// Moniwiki Autocompleter using the scriptaculous
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
                return;
            }
        }
    }
    return false;
}


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

function initGotoAutoCompleter(id,choiceid,action,method) {
    if (id == null || id == undefined)
        return false;

    if (typeof id == 'string')
        if (!document.getElementById(id)) return false;
    var div= document.createElement('div');
    div.setAttribute('id',choiceid);
    div.setAttribute('class','autocomplete');
    div.setAttribute('style','display: none');
    document.body.appendChild(div);

    var ac= new Ajax.Autocompleter(id, choiceid,
        action, {method: method,paramName: 'q',minChars: 1});

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
    setGotoFormId('go','autocomplete_goto');
    initGotoAutoCompleter('autocomplete_goto','autocomplete_choices',"?action=titleindex",'get');
}

// vim:et:sts=4:sw:
