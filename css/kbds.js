/*
   Copyright(c) 2002 by Byung-Chan Kim
   Copyright(c) 2003 by Won-kyu Park <wkpark @ kldp.org>

   distributable under GPL

   $Id$

   CHANGES

   * 2002/09/06 : From http://linux.sarang.net/ and heavily modified by wkpark
*/
// the GnomeKorea style simplified routine of kbd.js
//url_prefix="/mywiki";
//FrontPage="/FrontPage";
ime_state=0;
_dom=0;
strs="";

function keydownhandler(e) {
        if(document.all) e=window.event; // for IE
        if(_dom==3) var EventStatus = e.srcElement.tagName;
        else if(_dom==1) var EventStatus = e.target.nodeName; // for Mozilla

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

        if (strs !="" && ch==32) return false;

//      if (_dom!=3) return;
        if(EventStatus == 'INPUT' || EventStatus == 'TEXTAREA' ) {
          if (_dom==3 && cc==27 && EventStatus == 'TEXTAREA') return false;
          // ESC blocking for all vim lovers
                return;
        }
        if (cc==8) { // Backspace blocking
//              alert(e.keyCode);
                if( _dom==3 && strs.length > 0) {
                        strs=strs.substr(0,strs.length-1);
                        document.getElementById("status").innerHTML=strs;
                }
                return false;
        }
        return
}

function keypresshandler(e){
        if(document.all) e=window.event; // for IE
        if(_dom==3) var EventStatus = e.srcElement.tagName;
        else if(_dom==1) var EventStatus = e.target.nodeName; // for Mozilla

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
        if(EventStatus == 'INPUT' || EventStatus == 'TEXTAREA' || _dom == 2) {
                if (ch == '?' && EventStatus == 'INPUT') {
                        var my=""+document.go.elements[2].value;
                        document.getElementById("status").innerHTML="";
                        document.go.elements[2].blur();
			document.go.elements[0].checked=false;
			document.go.elements[1].checked=false;
        
                        document.go.elements[2].value=my.substr(0,my.length-1);
                } else if (cc== 27 && EventStatus == 'INPUT') {
                        document.getElementById("status").innerHTML="";
                        document.go.elements[2].blur();
			document.go.elements[0].checked=false;
			document.go.elements[1].checked=false;
        
                        document.go.elements[2].value='';
		}
                return;
        }
        if(e.altKey || e.ctrlKey) return;

        if(_dom != 3 && cc == 229 && ch == '') { // Mozilla
		strs='';
                document.getElementById("status").innerHTML="?/ or 한영전환";
//              }
        } else if(cc == 13) { // 'RETURN'
                if(strs.length > 0 )
                        self.location = url_prefix +'/?goto='+strs+'';
                else
                        strs = ""; // reset;
        } else if(_dom !=3 && cc == 112) { // 'F1' Help! (Mozilla only)
                self.location = url_prefix + '/HelpContents';
        } else if(_dom !=3 && cc == 114) { // 'F3' Find (Mozilla only)
                self.location = url_prefix + '/FindPage';
        } else if(cc == 9 || cc == 27) { // 'TAB','ESC' key
                if (cc == 27) {
                        document.go.elements[0].focus();
                }
                strs = "";
                document.getElementById("status").innerHTML="";
        } else if(ch == "/" || ch == "?") {
//		flag=document.go.elements[1].value[0];
		document.go.elements[0].checked=false;
		document.go.elements[1].checked=false;
                if (ch == "?") {
                // Title search as vi way
 //               document.getElementById("status").innerHTML="제목찾기";
                document.go.elements[2].focus();
                document.go.elements[2].name="value";
//              document.go.elements[2].value="";
                document.go.elements[3].name="action";
                document.go.elements[3].value="titlesearch";
                document.go.elements[4].value="제목찾기(?)";
                } else if ( ch == "/") {
                // Contents search
//                document.getElementById("status").innerHTML="본문찾기(/)";
                document.go.elements[2].focus();
                document.go.elements[2].name="value";
//              document.go.elements[2].value="";
                document.go.elements[3].name="action";
                document.go.elements[3].value="fullsearch";
                document.go.elements[4].value="본문찾기(/)";
                }
        } else if(ch == "c") {
                self.location = url_prefix + '/RecentChanges';
        } else if(ch == "d" || ch== "i" || ch=="b" || ch=="h") {
                var my=''+self.location;
                var idx=my.indexOf("?");
                if (idx != -1) {
                        my=my.substr(0,idx);
		}
		if (ch == "d")
                    my +='?action=diff';
		else if (ch == "i")
                    my +='?action=info';
		else if (ch == "b")
                    my +='?action=bookmark';
		else if (ch == "h")
                    my +='?action=home';
		self.location=my;
		
        } else if(ch == "f" || ch == 'h') { // frontpage
                self.location = url_prefix + FrontPage;
        } else if(ch == "s" || ch == 'q') { // findpage
                self.location = url_prefix + '/FindPage'
        } else if(ch == "t") { // frontpage
                self.location = url_prefix + '/TitleIndex'
        } else if(ch == "g") { // goto
                document.go.elements[2].focus();
                document.go.elements[2].name="value";
                document.go.elements[2].value="";
                document.go.elements[3].name="goto";
                document.go.elements[3].value="";
                document.go.elements[4].value="가기(S)";
        } else if(ch=="e" || ch=="w" || ch=="i" || ch=="r") { // Edit or reflash
                var my=''+self.location;
                var idx=my.indexOf("?");
                if (idx != -1 && my.substr(idx+1,5) == "goto=") {
                        my=my.substr(idx+6,my.length-6);
                        if ((idx=my.indexOf("&")) != -1)
                                my=my.substring(0,idx);
                        if (ch == "e" || ch == "w" || ch =="i")
                                self.location=url_prefix +'/'+my+'?action=edit';
                        if (ch == "r") {
                                if ((idx=my.indexOf("#")) != -1)
                                        my=my.substring(0,idx);
                                self.location=url_prefix+ '/'+my+'?action=show';                        }
                } else {
                        if (ch == "e" || ch == "w" || ch =="i")
                                self.location = '?action=edit';
//                      self.location = url_prefix + '/'+my+'?action=edit';
                        if (ch == "r") {
                                if ((idx=my.indexOf("#")) != -1) {
                                        my=my.substring(0,idx);
                                        self.location = my + '?action=show';
                                } else
                                        //self.location += '?action=show';
                                        self.location = self.location;
                        }
                }
        }
        return;
}

function input(){
        _dom=document.all ? 3 : (document.getElementById ? 1 : (document.layers ? 2 : 0));
        document.onkeypress = keypresshandler;
        document.onkeydown = keydownhandler;
}

function on_submit() {
        t = document.go.elements[0].value+''
	if ((!document.go.elements[0].checked && !document.go.elements[1].checked) && document.go.elements[3].name =="goto") {
	document.go.elements[2].name='goto';
	document.go.elements[2].value=document.go.elements[2].value;
		return true;
	}
//	document.go.elements[3].name;
}

input();
//-->
