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
    var num = 30;

    //if (q.substring(0,1) != '^') q = '^' + q;

    $.ajax({
        url: '?action=titleindex',
        data: { q: q, limit: num },
        success: function(data) {
            if (data.length > 0)
                response(data.split(/\n/).slice(0, num));
            else
                response([]);
        },
        error: function() {
            response([]);
        }
    });
}

function search(e, ui) {
    if (ui.item.value)
        location.href = url_prefix + _qp + encodeURIComponent(ui.item.value).replace(/%2F/g,'/');
}

$().ready(function() {
    if (init_inputform('go','ac_goto'))
    $('#ac_goto').autocomplete({
        source: titleindex, delay: 100,
        select: function(e,ui) {
            if (ui.item.value)
                location.href = url_prefix + _qp + encodeURIComponent(ui.item.value).replace(/%2F/g,'/');
        }
    });
});
})();

// vim:et:sts=4:sw=4:
