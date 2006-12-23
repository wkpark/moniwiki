/*
   MoinMoin Hotkeys

   Copyright(c) 2002 Byung-Chan Kim
   Copyright(c) 2003-2004 Won-kyu Park <wkpark at kldp.org>

   distributable under GPL

   $Id$

   CHANGES

   * 2002/09/06 : From http://linux.sarang.net/ and heavily modified by wkpark
   * 2003/04/16 : simlified by wkpark
   * 2003/06/01 : added patch by Kkabi
   * 2003/07/14 : fixed element indices
   * 2004/08/24 : no PATH_INFO support merged
   * 2004/10/03 : more intelligent behavior with search keys '?' '/'
*/

/*
 <form name="go" id="go" method="get" action='$url' onsubmit="return moin_submit();">
 <input type="text" name="value" size="20" />
 <input type="hidden" name="action" value="goto" />
 <input type="submit" name="status" value="Go" class="goto" />
 </form>
*/

/*
   D: ?action=diff
   I: ?action=info
   E/W: ?action=edit
   F: FrontPage
   C: RecentChanges
   T: TitleIndex
   H: ?action=home (not supported in the MoinMoin)
   L: ?action=LikePages
   U: ?action=UserPreferences

   <ESC>: goto the 'go' form
   /: FullSearch mode
   ?: TitleSearch mode

   F1: HelpContents
   F3: FindPage
*/

// uncomment bellow three lines and customize for your wiki.

//url_prefix="/mywiki";
//_qp="/"; // query_prefix
//FrontPage= "FrontPage";
RecentChanges= "RecentChanges"; 
FindPage= "FindPage"; 
TitleIndex= "TitleIndex"; 
HelpContents= "HelpContents";
UserPreferences= "UserPreferences";

// go form ID
_go= "go";

if (_qp == '/') { _ap='?'; }
else { _ap='&'; }

_dom=0;

function keydownhandler(ev) {
	e=ev ? ev:window.event; // for IE
	if(_dom==3) var EventStatus= e.srcElement.tagName;
	else if(_dom==1) var EventStatus= e.target.nodeName; // for Mozilla

	var cc = '';
	var ch = '';

	if(_dom==3) { // for IE
		if(e.keyCode>0) {
			ch=String.fromCharCode(e.keyCode);
			cc=e.keyCode;
		}
	} else { // for Mozilla
		cc=e.keyCode;
		if(e.charCode>0) {
			ch=String.fromCharCode(e.charCode);
		}
	}

//	if (_dom!=3) return;
	if(EventStatus == 'INPUT' || EventStatus == 'TEXTAREA' ) {
		if (_dom==3 && cc==27 && EventStatus == 'TEXTAREA')
			return false;
		// ESC blocking for all vim lovers
		return true;
	}
//	if (cc==8) { // Backspace blocking
//		alert(e.keyCode);
//		if( _dom==3 && strs.length > 0) {
//			//strs=strs.substr(0,strs.length-1);
//			//document.getElementById("status").innerHTML=strs;
//		}
//		return false;
//	}
	return true;
}

function keypresshandler(ev){
	e=ev ? ev:window.event; // for IE
	if(_dom==3) var EventStatus= e.srcElement.tagName;
	else if(_dom==1) var EventStatus= e.target.nodeName; // for Mozilla

	var cc = '';
	var ch = '';

	if(window.event) { // for IE
		if(e.keyCode>0) {
			ch=String.fromCharCode(e.keyCode);
			cc=e.keyCode;
		}
	} else { // for Mozilla
		cc=e.keyCode;
		if(e.charCode>0) {
			ch=String.fromCharCode(e.charCode);
		}
	}

	ch = ch.toLowerCase();
	var go=document.getElementById(_go);
	if(e.altKey || e.ctrlKey) {
		if(ch == "z" && e.altKey) {
			if (EventStatus != 'INPUT') {
				go.elements['value'].focus();
				return false;
			} else {
				var bot=document.getElementById('bottom');
				if (bot) bot.focus();
				return false;
			}
		}
		return;
	}
	if(EventStatus == 'INPUT' || EventStatus == 'TEXTAREA' || _dom == 2) {
		if ((ch == '?' || ch== '/') && EventStatus == 'INPUT') {
			var my=""+go.elements['value'].value;
			if (ch == '?' && (my == "/" || my =="?" || my=="")) {
				if (go.elements['status'].value == '?') {
					go.elements['action'].value="goto";
					go.elements['status'].value="Go";
					window.status="GoTo";
				} else {
					go.elements['action'].value="titlesearch";
					go.elements['status'].value="?";
					window.status="TitleSearch";
				}
			} else if (ch == '/' && (my == "/" || my =="?" || my=="")) {
				if (go.elements['status'].value == '/') {
			 		go.elements['action'].value="goto";
			 		go.elements['status'].value="Go";
			 		window.status="GoTo";
				} else {
					go.elements['action'].value="fullsearch";
					go.elements['status'].value="/";
					window.status="FullSearch";
				}
			}
			if (my == '/' || my == '?')
			go.elements['value'].value=my.substr(0,my.length-1);
		} else if (cc== 27 && EventStatus == 'INPUT') {
			go.elements['value'].blur();
			go.elements['value'].value='';
			go.elements['action'].value="goto";
			go.elements['status'].value="Go";
			window.status="GoTo"+window.defaultStatus;
		}
		return;
	}

	if(_dom != 3 && cc == 229 && ch == '') { // Mozilla
		window.status="?/ or change IME status";
	} else if(_dom !=3 && cc == 112) { // 'F1' Help! (Mozilla only)
		self.location = url_prefix + _qp + HelpContents;
	} else if(_dom !=3 && cc == 114) { // 'F3' Find (Mozilla only)
		self.location = url_prefix + _qp + FindPage;
	} else if(cc == 9 || cc == 27) { // 'TAB','ESC' key
		if (cc == 27) {
			go.elements['value'].focus();
		}
	} else if(ch == "`") {
		var bot=document.getElementById('bottom');
		if (bot) bot.focus();
	} else if(ch == "z") {
		go.elements['value'].focus();
	} else if(ch == "/" || ch == "?") {
		var my=go.elements['value'].value + "";
		if (ch == "?" && (my == "?" || my =="/" || my=="")) {
			// Title search as vi way
			go.elements['value'].focus();
			go.elements['action'].value="titlesearch";
			go.elements['status'].value="?";
		} else
		if (ch == "/" && (my == "?" || my =="/" || my=="")) {
			// Contents search
			go.elements['value'].focus();
			go.elements['action'].value="fullsearch";
			go.elements['status'].value="/";
		}
	} else if(ch == "c") {
		self.location = url_prefix + _qp + RecentChanges;
	} else if(ch == "d" || ch== "i" || ch=="b" || ch=="l" || ch=="h" || ch=="p" || ch=="a" || ch=="k") {
		var my=''+self.location;
		var idx = my.indexOf(_ap);
		if (idx != -1) {
			my=my.substr(0,idx);
		}
		if (ch == "d")
			my +=_ap + 'action=diff';
		else if (ch == "i")
			my +=_ap + 'action=info';
		else if (ch == "b")
			my +=_ap + 'action=bookmark';
		else if (ch == "h")
			my +=_ap + 'action=home';
		else if (ch == "l")
			my +=_ap + 'action=LikePages';
		else if (ch == "p")
			my +=_ap + 'action=print';
		else if (ch == "a")
			my +=_ap + 'action=randompage';
		else if (ch == "k")
			my +=_ap + 'action=keywords';
		self.location=my;
		
	} else if(ch == "f") { // frontpage
		self.location = url_prefix + _qp + FrontPage;
	} else if(ch == "s" || ch == 'q') { // findpage
		self.location = url_prefix + _qp + FindPage
	} else if(ch == "t") { // titleindex
		self.location = url_prefix + _qp + TitleIndex
	} else if(ch == "u") { // userpreferences
		self.location = url_prefix + _qp + UserPreferences;
	} else if(ch=="e" || ch=="w" || ch=="r") { // Edit or refresh
		var my=''+self.location;
		var idx=my.indexOf(_ap);
		if (idx != -1 && my.substr(idx+1,5) == "goto=") {
			my=my.substr(idx+6,my.length-6);
			if ((idx=my.indexOf("&")) != -1)
				my=my.substring(0,idx);
			if (ch == "e" || ch == "w")
				self.location=url_prefix + _qp + my + _ap +
					'action=edit';
			if (ch == "r") {
				if ((idx=my.indexOf("#")) != -1)
					my=my.substring(0,idx);
				self.location=url_prefix + _qp + my + _ap +
					'action=show';
			}
		} else {
			if (idx != -1) {
				my=my.substr(0,idx);
			} else if ((idx=my.indexOf("#")) != -1) {
				my=my.substring(0,idx);
			}
			if (ch == "e" || ch == "w")
				self.location = my + _ap + 'action=edit';
			if (ch == "r")
				self.location = my + _ap + 'action=show';
		}
	}
	return;
}

function input(){
	_dom=document.all ? 3 : (document.getElementById ? 1 : (document.layers ? 2 : 0));
	document.onkeypress = keypresshandler;
	document.onkeydown = keydownhandler;
}

function moin_submit() {
	var go=document.getElementById(_go);
	if (go.elements['value'].value.replace(/\s+/,'') =="")
		return false;
	if (go.elements['action'].value =="goto") {
		go.elements['value'].name='goto';
		go.elements['action'].name='';
		return true;
	}
}

input();
