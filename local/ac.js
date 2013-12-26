/**
 * MoniWiki Autocompleter
 *
 * @author  wkpark at gmail.com
 * @since   2013/12/26
 * @license GPLv2
 */

(function() {
function init_inputform(formid, id) {
    var inp = document.getElementById(formid);
    if (inp) {
        var val = inp.getElementsByTagName('input');
        if (!val) {
            return false;
        }
        for (var i=0; i <val.length; i++) {
            if (val[i].name == 'value') {
                val[i].setAttribute('id', id);
                return true;
            }
        }
    }
    return false;
}

function titleindex(request, response) {
    var q = request['term'];

    //if (q.substring(0,1) != '^') q = '^' + q;

    $.ajax({
        url: '?action=titleindex',
        data: { q: q },
        success: function(data) {
            response(data.split(/\n/));
        },
        error: function() {
            response([]);
        }
    });
}

$().ready(function() { if (init_inputform('go','ac_goto')) $('#ac_goto').autocomplete( { source: titleindex } );});
})();

// vim:et:sts=4:sw=4:
