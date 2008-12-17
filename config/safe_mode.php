; safe_mode.ini
; <?php exit()?>
; This file is used when $safe_mode property is true.
; On Template_'s safe mode, only functions listed here operate.
; Functions can be separated by whitespaces (space, tab, newline).
; Line comments by ';' are available.


; string
_
empty
isset
addcslashes
addslashes
bin2hex
chop
chr
chunk_split
convert_cyr_string
count_chars
crc32
crypt
hebrev
hebrevc
html_entity_decode
htmlentities
htmlspecialchars
implode
join
levenshtein
ltrim
md5
metaphone
money_format
nl_langinfo
nl2br
number_format
ord
quoted_printable_decode
quotemeta
rtrim
setlocale
sha1
similar_text
soundex
sprintf
sscanf
str_ireplace
str_pad
str_repeat
str_replace
str_rot13
str_shuffle
str_split
str_word_count
strcasecmp
strchr
strcmp
strcoll
strcspn
strip_tags
stripcslashes
stripos
stripslashes
stristr
strlen
strnatcasecmp
strnatcmp
strncasecmp
strncmp
strpos
strrchr
strrev
strripos
strrpos
strspn
strstr
strtok
strtolower
strtoupper
strtr
substr_count
substr_replace
substr
trim
ucfirst
ucwords
vsprintf
wordwrap

; date & time

checkdate
date
gmdate
gmmktime
gmstrftime
microtime
mktime
strftime
strtotime
time

; regexp

preg_match
preg_quote
preg_replace_callback
preg_replace
ereg_replace
;ereg
;eregi_replace
;eregi
;sql_regcase
