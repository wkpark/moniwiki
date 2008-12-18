/**
 *
 * A Simple Javascript module to support I18n for the MoniWiki.
 *
 * @author: Won-Kyu Park <wkpark@kldp.org>
 * @date: 2008-12-18
 * @name: a simple I18n javascript module.
 * @Description: a Simple Javascript module to support I18n.
 * @url: MoniWiki:JavascriptI18n
 * @version: $Revision$
 * @license: GPL
 *
 * _translations = {
 *  "Continue to ?": "Hello World",
 * }
 *
 */

readLanguage = function(domain) {
    var supported = { 'ko': 1 };
    var lang = navigator.language || navigator.userLanguage;

    if (supported[lang.substr(0,2)]) {
        //
        // read a main language file
        //
        var head = document.getElementsByTagName("head")[0];
        var js = document.createElement('script');
        js.type = 'text/javascript';
        js.src = _url_prefix + '/local/js/locale/' + lang.substr(0,2) + '/' + domain + '.js';
        head.appendChild(js);
    }
}

_ = function(msgid) {
    if ( typeof _translations == "undefined") 
        return msgid;

    var msgstr = _translations[msgid];
    if (!msgstr)
        msgstr = msgid;

    return msgstr;
}

//
// override the alert/confirm method.
//

var oldalert = window.alert;
var oldconfirm = window.confirm;

function setAlert() {
    if (document.getElementById) {
        window.alert = function(txt) {
            oldalert(_(txt));
        }
        window.confirm = function(txt) {
            oldconfirm(_(txt));
        }
    }
}

readLanguage('moniwiki');
setAlert();

// vim:et:sts=4:sw=4:
