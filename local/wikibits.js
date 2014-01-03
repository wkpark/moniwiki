// from the MediaWiki
// simplified for the MoniWiki by wkpark
//
// $Id$
//
// Wikipedia JavaScript support functions
// if this is true, the toolbar will no longer overwrite the infobox when you move the mouse over individual items
var noOverwrite=false;
var alertText;
var clientPC = navigator.userAgent.toLowerCase(); // Get client info
var is_gecko = ((clientPC.indexOf('gecko')!=-1) && (clientPC.indexOf('spoofer')==-1)
                && (clientPC.indexOf('khtml') == -1) && (clientPC.indexOf('netscape/7.0')==-1));
var is_safari = ((clientPC.indexOf('applewebkit')!=-1) && (clientPC.indexOf('spoofer')==-1));
var is_khtml = (navigator.vendor == 'KDE' || ( document.childNodes && !document.all && !navigator.taintEnabled ));
if (clientPC.indexOf('opera')!=-1) {
    var is_opera = true;
    var is_opera_preseven = (window.opera && !document.childNodes);
    var is_opera_seven = (window.opera && document.childNodes);
}

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

// add any onload functions in this hook (please don't hard-code any events in the xhtml source)
function onloadhook () {
    // don't run anything below this for non-dom browsers
    if(!(document.getElementById && document.getElementsByTagName)) return;
    akeytt();
}
if (window.addEventListener) window.addEventListener("load",onloadhook,false);
else if (window.attachEvent) window.attachEvent("onload",onloadhook);



// document.write special stylesheet links
if(typeof stylepath != 'undefined' && typeof skin != 'undefined') {
    if (is_opera_preseven) {
        document.write('<link rel="stylesheet" type="text/css" href="'+stylepath+'/'+skin+'/Opera6Fixes.css">');
    } else if (is_opera_seven) {
        document.write('<link rel="stylesheet" type="text/css" href="'+stylepath+'/'+skin+'/Opera7Fixes.css">');
    } else if (is_khtml) {
        document.write('<link rel="stylesheet" type="text/css" href="'+stylepath+'/'+skin+'/KHTMLFixes.css">');
    }
}
// Un-trap us from framesets
if( window.top != window ) window.top.location = window.location;

// this function generates the actual toolbar buttons with localized text
// we use it to avoid creating the toolbar where javascript is not enabled
function addButton(imageFile, speedTip, tagOpen, tagClose, sampleText) {

	speedTip=escapeQuotes(_(speedTip));
	tagOpen=escapeQuotes(tagOpen);
	tagClose=escapeQuotes(tagClose);
	sampleText=escapeQuotes(_(sampleText));
	var mouseOver="";

	// we can't change the selection, so we show example texts
	// when moving the mouse instead, until the first button is clicked
	if(!document.selection && !is_gecko && !is_safari) {
		// filter backslashes so it can be shown in the infobox
		var re=new RegExp("\\\\n","g");
		tagOpen=tagOpen.replace(re,"");
		tagClose=tagClose.replace(re,"");
		mouseOver = "onMouseover=\"if(!noOverwrite){document.infoform.infobox.value='"+tagOpen+sampleText+tagClose+"'};\"";
	}

	document.write("<a href='#' onclick=\"javascript:insertTags");
	document.write("('"+tagOpen+"','"+tagClose+"','"+sampleText+"');return false;\">");

        document.write("<img src=\""+imageFile+"\" border=\"0\" alt=\""+speedTip+"\" title=\""+speedTip+"\""+mouseOver+" />");
	document.write("</a>");
	return;
}

function addLinkButton(imageFile,speedTip,tagOpen,tagClose, sampleText, id,once) {
	var off=once ? 'true':'false';
	speedTip=escapeQuotes(_(speedTip));
	document.write("<a href='#' onclick=\"javascript:openChooser(this,'"
		+ tagOpen+"','" + tagClose + "','" + sampleText + "','" + id + "'," + off + ");return false;\">");
        document.write("<img src=\""+imageFile+"\" border=\"0\" alt=\""+speedTip+"\" title=\""+speedTip+"\""+" />");
	document.write("</a>");
	return;
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

function openChooser(el, tagOpen, tagClose, sampleText, id,once) {
	var div = document.getElementById(id);
	if (!div) {
		insertTags(tagOpen, tagClose, sampleText);
		return;
	}

	if (div.style.display == 'block') div.style.display='none';
	else div.style.display='block';
	if (div.style.position != 'absolute') {
		div.style.display='block';
		div.style.position='absolute';
	}

	var pos = getPos(el);
	div.style.top = pos.y + 21 + 'px';
	div.style.left = pos.x + 'px';
	div.style.width = '500px';
	if (once) div.onclick= function () { this.style.display='none'};
}

function addInfobox(infoText,text_alert) {
	alertText=_(text_alert);
	var clientPC = navigator.userAgent.toLowerCase(); // Get client info

	var re=new RegExp("\\\\n","g");
	alertText=alertText.replace(re,"\n");

	// if no support for changing selection, add a small copy & paste field
	// document.selection is an IE-only property. The full toolbar works in IE and
	// Gecko-based browsers.
	if(!document.selection && !is_gecko && !is_safari) {
 		infoText=escapeQuotesHTML(_(infoText));
	 	document.write("<form name='infoform' id='infoform'>"+
			"<input size=80 id='infobox' name='infobox' value=\""+
			infoText+"\" READONLY></form>");
 	}

}

function escapeQuotes(text) {
	var re=new RegExp("'","g");
	text=text.replace(re,"\\'");
	re=new RegExp('"',"g");
	text=text.replace(re,'&quot;');
	re=new RegExp("\\n","g");
	text=text.replace(re,"\\n");
	return text;
}

function escapeQuotesHTML(text) {
	var re=new RegExp('"',"g");
	text=text.replace(re,"&quot;");
	return text;
}

// apply tagOpen/tagClose to selection in textarea,
// use sampleText instead of selection if there is none
// copied and adapted from phpBB
function insertTags(tagOpen, tagClose, sampleText,replace) {
	var is_ie = document.selection && document.all;
	var my = document.getElementById('editor_area');
	var ef = document.getElementById('editform');
	var doc = document;
	var txtarea;
	if (ef)
		txtarea = ef.savetext;
	else {
		// some alternate form? take the first one we can find
		var areas = doc.getElementsByTagName('textarea');
		if (areas.length > 0) {
			txtarea = areas[0];
		} else if (opener) {
			doc = opener.document;
			if (ef && ef.savetext) {
				txtarea = ef.savetext;
			} else {
				txtarea = doc.getElementsByTagName('textarea')[0];
			}
        		my = doc.getElementById('editor_area');
		}
	}

	while (my == null || my.style.display == 'none') { // wikiwyg hack
		// get iframe and check visibility.
		var myframe = doc.getElementsByTagName('iframe')[0];
		var mnew;
		if (! myframe) break;
		if (myframe.style.display == 'none' || myframe.parentNode.style.display == 'none') break;

		txtarea = doc.getElementById('wikiwyg_wikitext_textarea');

		if (tagOpen == '$ ' && tagClose == ' $') { // latex math
			var wikiwyg = wikiwygs[0]; // XXX
			//var gui = false;
			//if (wikiwyg.current_mode.classname.match(/WikiWyg/)) gui=true;
        		mnew= '<span class="wikiMarkupEdit" style="display:inline">' +
        			"<!-- wiki:\n$ " + sampleText + " $\n-->" +
        			'<span>$ ' + sampleText + ' $</span></span>';

			wikiwyg.current_mode.insert_rawmarkup(tagOpen, tagClose, sampleText);
			return false;
		} else {
			var postdata = 'action=markup/ajax&value=' + encodeURIComponent(tagOpen + sampleText + tagClose);
			var myhtml='';
			myhtml= HTTPPost(self.location, postdata);

			mnew = myhtml.replace(/^<div>/i,''); // strip div tag
			mnew = mnew.replace(/<\/div>\s*$/i,''); // strip div tag
		}

		if (is_ie) {
			var range = myframe.contentWindow.document.selection.createRange();
			if (range.boundingTop == 2 && range.boundingLeft == 2)
				return false;
			range.pasteHTML(mnew);
			range.collapse(false);
			range.select();
		} else {
			myframe.contentWindow.document.execCommand('inserthtml', false, mnew + ' ');
		}

		return;
	}

	// IE
	// http://www.bazon.net/mishoo/articles.epl?art_id=1292 (used by this script)
	// http://the-stickman.com/web-development/javascript/finding-selection-cursor-position-in-a-textarea-in-internet-explorer/
	if(doc.selection  && !is_gecko && !is_opera && !is_safari) {
		txtarea.focus();
		var r = doc.selection.createRange();
		var range = r.duplicate();
		var endText = '';

		var myText = range.text;
		if (myText) {
			if (myText.charAt(myText.length - 1) == " ") { // exclude ending space char, if any
				endText = ' ';
				myText = myText.substring(0, myText.length - 1);
			}
			subst=toggleSameFormat(tagOpen,tagClose,myText);
		} else {
			myText=sampleText;
			subst = tagOpen + myText + tagClose;
		}
		
		if (replace == 2 ) { // append
			subst=tagOpen + myText + sampleText + tagClose;
		} else if (replace && !myText.match(/== /) /* == Heading == */
				&& subst != sampleText && myText != sampleText) {
			subst=tagOpen + sampleText + tagClose;
		}

		range.text = subst + endText;

		if (replace == 3 ) {
			range.setEndPoint('StartToEnd', r);
		} else {
			range.setEndPoint('StartToStart', r);
		}
		txtarea.focus();
		if (replace != 3 )
			range.select();
	// Mozilla
	} else if(txtarea.selectionStart || txtarea.selectionStart == '0') {
		var startPos = txtarea.selectionStart;
		var endPos = txtarea.selectionEnd;
		var scrollTop = txtarea.scrollTop;
		var myText = (txtarea.value).substring(startPos, endPos);
		var subst;

		if (myText) {
			if (myText.charAt(myText.length - 1) == " ") { // exclude ending space char, if any
				endPos--;
				myText=myText.substr(0,myText.length-1);
			}
			subst=toggleSameFormat(tagOpen,tagClose,myText);
		} else {
			myText=sampleText;
			subst = tagOpen + myText + tagClose;
		}

		if (replace == 2 ) { // append
			var my = sampleText + tagClose;
    			my = my.replace(/([\^\$\*\+\.\?\[\]\{\}\(\)])/g, '\\$1');
    			var re = new RegExp(my + '$');
			if (myText != sampleText) {
				if (!myText.match(re)) {
					subst= tagOpen + subst + ' ' + sampleText + tagClose;
				} else {
					subst = myText; // do not alter
				}
			}
		} else if (replace && !myText.match(/== /) /* == Heading == */
				&& subst != sampleText && myText != sampleText) {
			subst=tagOpen + sampleText + tagClose;
		}

		txtarea.value = txtarea.value.substring(0, startPos) + subst +
			txtarea.value.substring(endPos, txtarea.value.length);
		txtarea.focus();
		//set new selection
		txtarea.selectionStart = startPos;
		if (replace == 3 ) { // append
			txtarea.selectionStart = startPos + subst.length;
		}
		txtarea.selectionEnd = startPos+subst.length;

		txtarea.scrollTop = scrollTop;
	// All others
	} else {
		var copy_alertText=alertText;
		var re1=new RegExp("\\$1","g");
		var re2=new RegExp("\\$2","g");
		copy_alertText=copy_alertText.replace(re1,sampleText);
		copy_alertText=copy_alertText.replace(re2,tagOpen+sampleText+tagClose);
		var text;
		if (sampleText) {
			text=prompt(copy_alertText);
		} else {
			text="";
		}
		if(!text) { text=sampleText;}
		text=tagOpen+text+tagClose;
		doc.infoform.infobox.value=text;
		// in Safari this causes scrolling
		if(!is_safari) {
			txtarea.focus();
		}
		noOverwrite=true;
	}
	// reposition cursor if possible
	if (txtarea.createTextRange)
		txtarea.caretPos = doc.selection.createRange().duplicate();
	return;
}

function toggleSameFormat(start, end, sel) {
    var nsel=sel;
    var start_re = start.replace(/([\^\$\*\+\.\?\[\]\{\}\(\)])/g, '\\$1')
        .replace(/\n/,"(\r\n|\n)?").replace(/==/,'={2,6}'); // for headings
    var end_re = end.replace(/([\^\$\*\+\.\?\[\]\{\}\(\)])/g, '\\$1')
        .replace(/\n/,"(\r\n|\n)?").replace(/==/,'={2,6}');

    start_re = new RegExp('^' + start_re);
    end_re = new RegExp(end_re + '$');
    if (sel.match(start_re) && sel.match(end_re)) {
	nsel = sel.replace(start_re,'').replace(end_re,'');

	var m;
	if (m=sel.match(/^(\r\n|\n)?(={1,6})/)) { // for headings
	    var tag='======'.slice(0, m[2].length);
	    start=start.replace(/=/,tag),end=end.replace(/=/,tag);
	    if (start.replace(/(\r\n|\n)/,'').length==8) start="\n== ",end=" ==\n"; // reset
	} else {
            return nsel;
	}
    }
    return start+nsel+end;
}

function akeytt() {
    if(typeof ta == "undefined" || !ta) return;
    pref = 'alt-';
    if(is_safari || navigator.userAgent.toLowerCase().indexOf( 'mac' ) + 1 ) pref = 'control-';
    if(is_opera) pref = 'shift-esc-';
    for(id in ta) {
        n = document.getElementById(id);
        if(n){
            a = n.childNodes[0];
            if(a){
                if(ta[id][0].length > 0) {
                    a.accessKey = ta[id][0];
                    ak = ' ['+pref+ta[id][0]+']';
                } else {
                    ak = '';
                }
                a.title = ta[id][1]+ak;
            } else {
                if(ta[id][0].length > 0) {
                    n.accessKey = ta[id][0];
                    ak = ' ['+pref+ta[id][0]+']';
                } else {
                    ak = '';
                }
                n.title = ta[id][1]+ak;
            }
        }
    }
}
