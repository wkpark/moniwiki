/*
   MoinMoin Hotkeys

   Copyright(c) 2002 Byung-Chan Kim
   Copyright(c) 2003-2008 Won-kyu Park <wkpark at kldp.org>

   distributable under GPL

   $Id$

   CHANGES

   * 2002/09/06 : From http://linux.sarang.net/ and heavily modified by wkpark
   * 2003/04/16 : simlified by wkpark
   * 2003/06/01 : added patch by McKkabi
   * 2003/07/14 : fixed element indices
   * 2004/08/24 : no PATH_INFO support merged
   * 2004/10/03 : more intelligent behavior with search keys '?' '/'
   * 2007/11/09 : simplified and cleanup.
   * 2008/11/25 : do not assume the "go" form is always defined.
   * 2009/04/19 : changeable name of the default input form. use control-Enter to save.
   * 2009/09/16 : MetaKey patch by McKkabi [#305355]
*/

/*
 <form name="go" id="go" method="get" action='$url' onsubmit="return moin_submit(this);">
 <input type="text" name="value" size="20" />
 <input type="hidden" name="action" value="goto" />
 <input type="submit" name="status" value="Go" class="goto" />
 </form>
*/

/*
   A: ?action=randompage
   B: ?action=bookmark
   D: ?action=diff
   E/W: ?action=edit
   H: ?action=home (not supported action in the MoinMoin)
   I: ?action=info
   K: ?action=keywords
   L: ?action=LikePages
   P: ?action=print
   R: ?action=show
   U: ?action=UserPreferences

   C: RecentChanges
   F: FrontPage
   S/Q: FindPage
   T: TitleIndex

   <ESC>: goto the 'go' form
   /: FullSearch mode toggle
   ?: TitleSearch mode toggle

   ** mozilla only **
   F1: HelpContents
   F3: FindPage
*/

// uncomment bellow three lines and customize for your wiki.

//_script_name="/mywiki";
//_qp="/"; // query_prefix
//FrontPage= "FrontPage";
_script_name=url_prefix || _script_name; // url_prefix is depricated

RecentChanges= "RecentChanges"; 
FindPage= "FindPage"; 
TitleIndex= "TitleIndex"; 
HelpContents= "HelpContents";
UserPreferences= "UserPreferences";

// go form ID
_go= "go";
_govalue= (typeof _govalue != 'undefined') ? _govalue:"value"; // elements['value']
_ap = _qp == '/' ? '?':'&';
var is_safari = navigator.appVersion.toLowerCase().indexOf('safari') != -1;

function noBubble(e) {
	if (e.preventDefault) e.preventDefault();
	if (e.stopPropagation) e.stopPropagation();
	else e.cancelBubble = true;
}

function keydownhandler(e) {
	if (e && e.target) var f = e.target, nn=f.nodeName; // Mozilla
	else var e=window.event, f = e.srcElement, nn = f.tagName; // IE

	if (is_safari) {
		// safari/chrome
		var go=document.getElementById(_go);
		var goValue=null;
		if (go) goValue=go.elements[_govalue];

		if ( goValue && e.charCode != undefined && e.keyCode == 27) {
			// 'ESC' key
			if (goValue && nn != 'TEXTAREA' && nn != 'INPUT') {
				goValue.focus();
			} else {
				goValue.blur();
			}
			noBubble(e);
			return false;
		}
		if (e.altKey && e.keyCode == 90) { // Z
			if (nn != 'INPUT') {
				go ? goValue.focus():null;
				noBubble(e);
			} else {
				var bot=document.getElementById('bottom');
				if (bot) bot.focus(), noBubble(e);
			}
			return;
		}
	}

	if (e.ctrlKey && nn == 'TEXTAREA') {
		if (e.keyCode == 13) {
			// ctrl-Enter to submit
			var p = f.parentNode;
			while(p.tagName != 'FORM' && p.tagName != 'BODY') p = p.parentNode;
			if (p.tagName == 'FORM') {
				p.submit();
				return;
			}
		}
	}

	if (e.charCode == undefined && (e.keyCode==112 || e.keyCode==114)) {
		keypresshandler(e); // IE hack
		noBubble(e);
		return false;
	}
	if (e.charCode == undefined && e.keyCode==27 && (nn == 'TEXTAREA' || nn == 'INPUT')) return false;
	// IE ESC blocking for all vim lovers
	return true;
}

function keypresshandler(e) {
	if (window.event) var e = window.event, f = e.srcElement, nn = f.tagName;
	else  var f = e.target, nn = f.nodeName;
	var cc = e.charCode ? e.charCode : e.keyCode;
	ch = (cc >= 32 && cc <=126) ? String.fromCharCode(cc).toLowerCase():0;

	//alert(e.keyCode+','+e.charCode+','+e.which);
	var go, goValue, goAction, goStatus;
	var val, stat, act;
	go=document.getElementById(_go);
        if (go) {
		goValue=go.elements[_govalue];
		goAction=go.elements['action'] || null;
		goStatus=go.elements['status'] || null;
		val = goValue.value || "", act="goto";
		stat = goStatus ? goStatus.value:null;
        } else {
		val = "", act="goto";
		stat = "Go";
	}

	if (cc == 229 && nn != 'INPUT' && nn != 'TEXTAREA') { // for Mozilla
		go ? goValue.focus():null;
		noBubble(e);
		return;
	}

	var i=0;

	if (e.altKey && ch == 'z') {
		if (nn != 'INPUT') {
			go ? goValue.focus():null;
			noBubble(e);
		} else {
			var bot=document.getElementById('bottom');
			if (bot) bot.focus(), noBubble(e);
		}
		return;
	}
	if (e.altKey || e.metaKey) return true; // mozilla

	if (!e.keyCode && (cc == 112 || cc == 114)) ch=ch; // mozilla hack

	//alert(ch + ',' + cc);
	switch(ch || cc) {
	case 27: ch = 27;
	case '/':
	case '?':
		if (nn == 'INPUT') {
			if (val == "" || val == "/" || val =="?") {
				if (ch == '?') {
					if (stat == "?") { // toggle
						stat = "Go";
						window.status="GoTo";
					} else {
						act="titlesearch";
						stat="?";
						window.status="TitleSearch";
					}
				} else if (ch == '/') {
					if (stat == "/") { // toggle
						stat = "Go";
				 		window.status="GoTo";
					} else {
						act="fullsearch";
						stat="/";
						window.status="FullSearch";
					}
				} else if (ch == 27) {
					stat="Go";
					go ? goValue.blur():null;
				}
				if (val == "/" || val == "?") val=val.substr(0,val.length-1);
				go ? goValue.value=val:null;
				goAction ? goAction.value=act:null;
				go && goStatus ? goStatus.value=stat:null;
				return;
			}
		}
		break;
	}

	if (nn == 'INPUT' || nn == 'TEXTAREA' || e.ctrlKey) return;
	var loc=self.location+'';
	var pages={'c':RecentChanges,'f':FrontPage,'s':FindPage,'t':TitleIndex,'u':UserPreferences};
	var actions={'d':'diff', 'i':'info', 'b':'bookmark', 'h':'home', 'l':'likepages',
		'p':'print', 'a':'randompage', 'k':'keywords', ',':'backlinks'};

	switch(ch || cc) {
	case '?':
		// Title search as vi way
		goAction ? goAction.value="titlesearch":null;
		goStatus ? goStatus.value='?':null;
		goValue ? goValue.focus():null;
		break;
	case '/':
		// Contents search
		goAction ? goAction.value="fullsearch":null;
		goStatus ? goStatus.value='/':null;
		goValue ? goValue.focus():null;
		break;
	case 27: // 'ESC' key
		go ? goValue.focus():null;
		break;
	case 112: // 'F1' Help (Mozilla only)
		noBubble(e);
		self.location = _script_name + _qp + HelpContents;
		break;
	case 114: // 'F3' Find (Mozilla only)
		noBubble(e);
		self.location = _script_name + _qp + FindPage;
		break;
	case 229: // IME
		window.status="?/ or change IME status";
		break;
	case '`':
		var bot=document.getElementById('bottom');
		if (bot) bot.focus();
		break;
	case 'z':
		go ? goValue.focus():null;
		break;
	case 'a': case 'b': case 'd': case 'h': case 'i': case 'k': case 'l': case 'p': case ',':
		if ((i = loc.indexOf(_ap)) != -1) loc = loc.substr(0,i);
		if ((i = loc.indexOf('#')) != -1) loc = loc.substr(0,i);
		self.location=loc + _ap + 'action=' + actions[ch];
		break;
	case 'q': ch = 's';
	case 'c': case 'f': case 's': case 't': case 'u':
		self.location = _script_name + _qp + pages[ch];
		break;
	case 'e': case 'r': case 'w':
		// Edit/write or refresh
		var no = null;
		var target = '';
		if (typeof get_src_line_num == 'function') {
			no = get_src_line_num(true);
			if (no != null)
				target = '#' + no;
		}

		var txtarea = document.getElementById('editor-textarea');
		if (txtarea) {
			noBubble(e);
			pasta = new PaSTA();

			var ret = pasta.focusEditor(e, txtarea, no);
			return false;
		}
		if ((i=loc.indexOf(_ap)) != -1 && loc.substr(i+1,5) == "goto=") { // deprecated
			loc=loc.substr(i+6,loc.length-6);
			if ((i=loc.indexOf('&')) != -1) loc=loc.substring(0,i);
			if (ch == "e" || ch == "w")
				self.location=_script_name + _qp + loc + _ap +
					'action=edit' + target;
			if (ch == "r") {
				if ((i=loc.indexOf('#')) != -1)
					loc=loc.substring(0,i);
				self.location=_script_name + _qp + loc + _ap +
					'action=show';
			}
		} else {
			if (i != -1) loc=loc.substr(0,i);
			else if ((i=loc.indexOf('#')) != -1) loc=loc.substring(0,i);
			if (ch == "e" || ch == "w") self.location = loc + _ap + 'action=edit' + target;
			if (ch == "r") self.location = loc + _ap + 'action=show';
		}
		break;
	}
	return;
}

function moin_init() {
	if (document.addEventListener) {
		document.addEventListener('keypress', keypresshandler,false);
		document.addEventListener('keydown', keydownhandler,false);
	} else {
		document.attachEvent('onkeypress',keypresshandler);
		document.attachEvent('onkeydown',keydownhandler);
	}
	// check the editor_area
	var form = document.getElementById('editor_area');
	if (form) return;
	if (typeof _focus_on != 'undefined') {
		var go = document.getElementById(_go);
		// focus on to the input form
		if (go) go.elements[_govalue].focus();
	}
}

(function () {
if (window.addEventListener) window.addEventListener("load", moin_init, false);
else if (window.attachEvent) window.attachEvent("onload", moin_init);
})();

function moin_submit(form) {
	if (form == null) form=document.getElementById(_go);
	if (form == null) return true;
	if (form.elements[_govalue].value.replace(/\s+/,'') == "") return false;
	if (form.elements['action'].value =="goto") {
		go.elements[_govalue].name='goto';
		go.elements['action'].name='';
		form.action = _script_name;

		return true;
	}
}
