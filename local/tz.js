// in [-]HH:MM format...
// won't yet work with non-even tzs
function fetchTimezone() {
  // FIXME: work around Safari bug
  var localclock = new Date();
  // returns negative offset from GMT in minutes
  var tzRaw = localclock.getTimezoneOffset();
  var tzHour = Math.floor( Math.abs(tzRaw) / 60);
  var tzMin = Math.abs(tzRaw) % 60;
  var tzString = ((tzRaw >= 0) ? "-" : "") + ((tzHour < 10) ? "0" : "")
    + tzHour + ":" + ((tzMin < 10) ? "0" : "") + tzMin;
  return tzString;
}

function setTimezone() {
  var tz=fetchTimezone();
  if (document.createTextNode) {
    // Uses DOM calls to avoid document.write + XHTML issues
    var form=document.getElementsByTagName('form');
    for (i=0;i<form.length;i++) {
       var opt=form[i].getElementsByTagName('option');
       if (opt.length > 0) {
          for (j=0;j<opt.length;j++) {
             if (opt[j].value == tz) {
               opt[j].selected='selected';
             } else {
               opt[j].selected='';
             }
          }
       }
    }
  }
}
