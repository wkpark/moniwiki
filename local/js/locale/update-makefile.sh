#!/bin/sh
#
# $Id$
#
# This shell script is used to update the list of .po files and the
# dependencies for moniwiki.pot in the Makefile.
#
# Do not invoke this script directly, rather run:
#
#    make dep
#
# to update the Makefile.
#

test -f Makefile || cp -f Makefile.in Makefile

# Generate the head (manually-edited part) of the new Makefile
#
makefile_head () {
    sed '/^# DO NOT DELETE THIS LINE$/,$ d' Makefile && cat <<'EOF'
# DO NOT DELETE THIS LINE
#
# The remainder of this file is auto-generated
#
# (Run 'make dep' to regenerate this section.)
#
EOF
}

# Find all .po files in po/.
#
po_files () {
    find po -name "*.po" |
	sort |
	sed 's/^/po: /p;
             s|^.*/\(.*\)\.po$|js: \1/moniwiki.js|;'
}

# Find all php and html source code which should be scanned
# by xgettext() for localizeable strings.
# find ../lib fails on cygwin
pot_file_deps () {
    (cd ..; find ../JSUpload ../Wikiwyg \( -name "*.js" \)) |
	sed 's|^|${POT_FILE}: ../|;' |
	sort
}

# Generate the new Makefile
{ makefile_head &&
    po_files &&
    echo "#" &&
    pot_file_deps; } > Makefile.new || exit 1

if diff -q Makefile Makefile.new > /dev/null
then
    # Don't touch the Makefile if unchanged.
    # (This avoids updating the timestamp)
    rm Makefile.new
    echo "Makefile unchanged" 1>&2
    exit 0
fi

ls ../../*.js | sed 's|^|${POT_FILE}: |;' >> Makefile.new
cat >> Makefile.new <<'MAIN'
${POT_FILE}: dummy.js
MAIN

mv Makefile.new Makefile && echo "Makefile updated" 1>&2
