/*
   MoinMoin Hotkeys

   Copyright(c) 2002 Byung-Chan Kim
   Copyright(c) 2003-2007 Won-kyu Park <wkpark at kldp.org>

   distributable under GPL

   $Id$

   CHANGES

   * 2002/09/06 : From http://linux.sarang.net/ and heavily modified by wkpark
   * 2003/04/16 : simlified by wkpark
   * 2003/06/01 : added patch by Kkabi
   * 2003/07/14 : fixed element indices
   * 2004/08/24 : no PATH_INFO support merged
   * 2004/10/03 : more intelligent behavior with search keys '?' '/'
   * 2007/11/09 : simplified and cleanup.
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
_ap = _qp == '/' ? '?':'&';

function noBubble(e) {
	if (e.preventDefault) e.preventDefault();
	else e.cancelBubble = true;
}

function keydownhandler(e) {
	if (e && e.target) var f = e.target, nn=f.nodeName; // Mozilla
	else var e=window.event, f = e.srcElement, nn = f.tagName; // IE

	if (window.event && e.keyCode==27 && (nn == 'TEXTAREA' || nn == 'INPUT')) return false;
	// IE ESC blocking for all vim lovers
	return true;
}

function keypresshandler(e) {
	if (window.event) var e = window.event, f = e.srcElement, nn = f.tagName;
	else  var f = e.target, nn = f.nodeName;
	var cc = e.charCode ? e.charCode : e.keyCode;
	ch = (cc >= 32 && cc <=126) ? String.fromCharCode(cc).toLowerCase():0;

	//alert(e.keyCode+','+e.charCode+','+e.which);
	var go=document.getElementById(_go);
	var goValue=go.elements['value'];
	var goAction=go.elements['action'];
	var goStatus=go.elements['status'];

	if (cc == 229 && nn != 'INPUT' && nn != 'TEXTAREA') { // for Mozilla
		goValue.focus();
		noBubble(e);
		return;
	}

	var val = goValue.value || "", act="goto";
	var stat = goStatus.value || "Go";
	var i=0;

	if (e.altKey && ch == 'z') {
		if (nn != 'INPUT') {
			goValue.focus();
			noBubble(e);
		} else {
			var bot=document.getElementById('bottom');
			if (bot) bot.focus(), noBubble(e);
		}
		return;
	}

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
					goValue.blur();
				}
				if (val == "/" || val == "?") val=val.substr(0,val.length-1);
				goValue.value=val;
				goAction.value=act;
				goStatus.value=stat;
				return;
			}
		}
		break;
	}

	if (nn == 'INPUT' || nn == 'TEXTAREA' || e.ctrlKey) return;
	var loc=self.location+'';
	var pages={'c':RecentChanges,'f':FrontPage,'s':FindPage,'t':TitleIndex,'u':UserPreferences};
	var actions={'d':'diff', 'i':'info', 'b':'bookmark', 'h':'home', 'l':'likepages',
		'p':'print', 'a':'randompage', 'k':'keywords'};

	switch(ch || cc) {
	case '?':
		// Title search as vi way
		goAction.value="titlesearch";
		goStatus.value='?';
		goValue.focus();
		break;
	case '/':
		// Contents search
		goAction.value="fullsearch";
		goStatus.value='/';
		goValue.focus();
		break;
	case 27: // 'ESC' key
		goValue.focus();
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
		goValue.focus();
		break;
	case 'a': case 'b': case 'd': case 'h': case 'i': case 'k': case 'l': case 'p':
		if ((i = loc.indexOf(_ap)) != -1) loc = loc.substr(0,i);
		self.location=loc + _ap + 'action=' + actions[ch];
		break;
	case 'q': ch = 's';
	case 'c': case 'f': case 's': case 't': case 'u':
		self.location = _script_name + _qp + pages[ch];
		break;
	case 'e': case 'r': case 'w':
		// Edit/write or refresh
		if ((i=loc.indexOf(_ap)) != -1 && loc.substr(i+1,5) == "goto=") { // deprecated
			loc=loc.substr(i+6,loc.length-6);
			if ((i=loc.indexOf('&')) != -1) loc=loc.substring(0,i);
			if (ch == "e" || ch == "w")
				self.location=_script_name + _qp + loc + _ap +
					'action=edit';
			if (ch == "r") {
				if ((i=loc.indexOf('#')) != -1)
					loc=loc.substring(0,i);
				self.location=_script_name + _qp + loc + _ap +
					'action=show';
			}
		} else {
			if (i != -1) loc=loc.substr(0,i);
			else if ((i=loc.indexOf('#')) != -1) loc=loc.substring(0,i);
			if (ch == "e" || ch == "w") self.location = loc + _ap + 'action=edit';
			if (ch == "r") self.location = loc + _ap + 'action=show';
		}
		break;
	}
	return;
}

function moin_init() {
	if (document.addEventListener) {
		document.addEventListener('keypress',keypresshandler,false);
		document.addEventListener('keypress',keydownhandler,false);
	} else {
		document.attachEvent('onkeypress',keypresshandler);
		document.attachEvent('onkeydown',keydownhandler);
	}
}

function moin_submit(form) {
	if (form == null) form=document.getElementById(_go);
	if (form == null) return true;
	if (form.elements['value'].value.replace(/\s+/,'') == "") return false;
	if (form.elements['action'].value =="goto") {
		go.elements['value'].name='goto';
		go.elements['action'].name='';
		return true;
	}
}

moin_init();
