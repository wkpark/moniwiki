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

	speedTip=escapeQuotes(speedTip);
	tagOpen=escapeQuotes(tagOpen);
	tagClose=escapeQuotes(tagClose);
	sampleText=escapeQuotes(sampleText);
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

	document.write("<a href=\"javascript:insertTags");
	document.write("('"+tagOpen+"','"+tagClose+"','"+sampleText+"');\">");

        document.write("<img width=\"23\" height=\"22\" src=\""+imageFile+"\" border=\"0\" alt=\""+speedTip+"\" title=\""+speedTip+"\""+mouseOver+">");
	document.write("</a>");
	return;
}

function addInfobox(infoText,text_alert) {
	alertText=text_alert;
	var clientPC = navigator.userAgent.toLowerCase(); // Get client info

	var re=new RegExp("\\\\n","g");
	alertText=alertText.replace(re,"\n");

	// if no support for changing selection, add a small copy & paste field
	// document.selection is an IE-only property. The full toolbar works in IE and
	// Gecko-based browsers.
	if(!document.selection && !is_gecko && !is_safari) {
 		infoText=escapeQuotesHTML(infoText);
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
function insertTags(tagOpen, tagClose, sampleText) {
	if (document.editform)
		var txtarea = document.editform.savetext;
	else {
		// some alternate form? take the first one we can find
		var areas = document.getElementsByTagName('textarea');
		var txtarea = areas[0];
	}

	// IE
	// http://www.bazon.net/mishoo/articles.epl?art_id=1292 (used by this script)
	// http://the-stickman.com/web-development/javascript/finding-selection-cursor-position-in-a-textarea-in-internet-explorer/
	if(document.selection  && !is_gecko) {
		txtarea.focus();
		var r = document.selection.createRange();
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

		range.text = subst + endText;

		range.setEndPoint('StartToStart', r);
		txtarea.focus();
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

		txtarea.value = txtarea.value.substring(0, startPos) + subst +
			txtarea.value.substring(endPos, txtarea.value.length);
		txtarea.focus();
		//set new selection
		txtarea.selectionStart = startPos;
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
		document.infoform.infobox.value=text;
		// in Safari this causes scrolling
		if(!is_safari) {
			txtarea.focus();
		}
		noOverwrite=true;
	}
	// reposition cursor if possible
	if (txtarea.createTextRange)
		txtarea.caretPos = document.selection.createRange().duplicate();
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
	    var tag='='.times(m[2].length);
	    start=start.replace(/=/,tag),end=end.replace(/=/,tag)
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
