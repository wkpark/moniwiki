<?php
// Copyright 2005-2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a unicode module for the MoniWiki
//
// $Id$
//
// from http://www.randomchaos.com/document.php?source=php_and_unicode
// with some modifications
//

function utf8_to_unicode( $str ) {
    $unicode= array();        
    $values= array();
    $lookingFor= 1;
    
    for ($i= 0; $i < strlen( $str ); $i++ ) {
        $thisValue = ord( $str[ $i ] );
        if ( $thisValue < 128 ) $unicode[] = $thisValue;
        else {
            if (count( $values ) == 0)
                $lookingFor = ($thisValue < 224) ? 2 : 3;
            $values[] = $thisValue;
            if (count($values) == $lookingFor ) {
                $number= ($lookingFor == 3) ?
                    (($values[0]%16)*4096)+(($values[1]%64)*64)+($values[2]%64):
                	(($values[0]%32)*64)+($values[1]%64);
                $unicode[]= $number;
                $values= array();
                $lookingFor= 1;
            }
        }
    }
    return $unicode;
}

function unicode_to_entities( $unicode ) {
    $entities = '';
    foreach( $unicode as $value ) {
	$v=sprintf("%0x",$value);
	$entities .= '&#' . $v . ';';
    }
    return $entities;
}

function unicode_to_entities_preserving_ascii( $unicode ) {
    $entities = '';
    foreach( $unicode as $value ) {
        $entities .= ( $value > 127 ) ? '&#' . $value . ';' : chr( $value );
    }
    return $entities;
}

function strpos_unicode( $haystack , $needle , $offset = 0 ) {
    $position = $offset;
    $found = FALSE;
    
    while( (! $found ) && ( $position < count( $haystack ) ) ) {
        if ( $needle[0] == $haystack[$position] ) {
            for ($i = 1; $i < count( $needle ); $i++ ) {
                if ( $needle[$i] != $haystack[ $position + $i ] ) break;
            }
            
            if ( $i == count( $needle ) ) {
                $found = TRUE;
                $position--;
            }
        }
        $position++;
    }
    
    return ( $found == TRUE ) ? $position : FALSE;
}

#$position = strpos_unicode( $unicode , utf8_to_unicode( '42' ) );

function unicode_to_utf8( $str ) {
    $utf8 = '';
    if (!is_array($str)) $str= array($str);
    foreach( $str as $unicode ) {
        if ( $unicode < 128 ) {
            $utf8.= chr( $unicode );
        } elseif ( $unicode < 2048 ) {
            $utf8.= chr( 192 +  ( ( $unicode - ( $unicode % 64 ) ) / 64 ) );
            $utf8.= chr( 128 + ( $unicode % 64 ) );
        } else {
            $utf8.= chr( 224 + ( ( $unicode - ( $unicode % 4096 ) ) / 4096 ) );
            $utf8.= chr( 128 + ( ( ( $unicode % 4096 ) - ( $unicode % 64 ) ) / 64 ) );
            $utf8.= chr( 128 + ( $unicode % 64 ) );
        }
    }
    return $utf8;
}

// for Hangul
function hangul_to_jamo($unicode) {
    static $j2c=array(
        0x3131=>0x1100,
        0x3132=>0x1101,
        0x3133=>0x11aa, // fcon
        0x3134=>0x1102,
        0x3135=>0x11ac, // fcon
        0x3136=>0x11ad, // fcon
        0x3137=>0x1103,
        0x3138=>0x1104,
        0x3139=>0x1105,
        0x313a=>0x11b0, // fcon
        0x313b=>0x11b1, // fcon
        0x313c=>0x11b2, // fcon
        0x313d=>0x11b3, // fcon
        0x313e=>0x11b4, // fcon
        0x313f=>0x11b5, // fcon
        0x3140=>0x11b6, // fcon
        0x3141=>0x1106,
        0x3142=>0x1107,
        0x3143=>0x1108,
        0x3144=>0x11b9, // fcon
        0x3145=>0x1109,
        0x3146=>0x110a,
        0x3147=>0x110b,
        0x3148=>0x110c,
        0x3149=>0x110d,
        0x314a=>0x110e,
        0x314b=>0x110f,
        0x314c=>0x1110,
        0x314d=>0x1111,
        0x314e=>0x1112,

        0x314f=>0x1161,
        0x3150=>0x1162,
        0x3151=>0x1163,
        0x3152=>0x1164,
        0x3153=>0x1165,
        0x3154=>0x1166,
        0x3155=>0x1167,
        0x3156=>0x1168,
        0x3157=>0x1169,
        0x3158=>0x116a,
        0x3159=>0x116b,
        0x315a=>0x116c,
        0x315b=>0x116d,
        0x315c=>0x116e,
        0x315d=>0x116f,
        0x315e=>0x1170,
        0x315f=>0x1171,
        0x3160=>0x1172,
        0x3161=>0x1173,
        0x3162=>0x1174,
        0x3163=>0x1175,
    );
    $jamo=array();
    if (!is_array($unicode)) $unicode=array($unicode);
    //$unicode=utf8_to_unicode($str);
    foreach ($unicode as $u) {
        if ($u >= 0xac00 and $u <=0xd7af) {
            $dummy=$u - 0xac00;
            $T= $dummy % 28 + 0x11a7;
            $dummy=(int)($dummy/28);
            $V= $dummy % 21 + 0x1161;
            $dummy=(int)($dummy/21);
            $L= $dummy + 0x1100;
            $jamo[]=$L;$jamo[]=$V;
            if ($T >=0x11a8) $jamo[]=$T;
        } else if ($u >=0x3130 and $u <=0x318f) {
            $jamo[]=$j2c[$u];
            //print sprintf("0x%04x",$j2c[$u]);
        } else {
            $jamo[]=$u;
        }
    }
    return $jamo;
}

function utf8_hangul_to_jamo($str) {
    $unicode=utf8_to_unicode($str);
    return hangul_to_jamo($unicode);
}

define('hangul_base', 0xac00);
define('choseong_base', 0x1100);
define('jungseong_base', 0x1161);
define('jongseong_base', 0x11a7);
define('njungseong', 21);
define('njongseong', 28);

function jamo_to_syllable($jamo) {
    if (sizeof($jamo)<=3) {
        $choseong=$jamo[0];
        $jungseong=$jamo[1];
        $jongseong=isset($jamo[2]) ? $jamo[2]:0;
    }

    /* we use 0x11a7 like a Jongseong filler */
    if ($jongseong == 0)
    $jongseong = 0x11a7; /* Jongseong filler */

    if (!($choseong  >= 0x1100 && $choseong  <= 0x1112))
    return 0;
    if (!($jungseong >= 0x1161 && $jungseong <= 0x1175))
    return 0;
    if (!($jongseong >= 0x11a7 && $jongseong <= 0x11c2))
    return 0;

    $choseong  -= choseong_base;
    $jungseong -= jungseong_base;
    $jongseong -= jongseong_base;
    // php hack XXX
    $choseong = sprintf("%d",$choseong);
    $jungseong = sprintf("%d",$jungseong);
    $jongseong = sprintf("%d",$jongseong);

    $ch[0] = (($choseong * njungseong) + $jungseong) * njongseong + $jongseong
    + hangul_base;
    return $ch;
}

function hangul_jamo_to_cjamo($jamo) {
    if ($jamo >= 0x1100 && $jamo <= 0x1112)
        return hangul_choseong_to_cjamo($jamo);
    if ($jamo >= 0x1161 && $jamo <= 0x1175)
        return hangul_jungseong_to_cjamo($jamo);
    if ($jamo >= 0x11a7 && $jamo <= 0x11c2)
        return hangul_jongseong_to_cjamo($jamo);
    // else
    return $jamo;
}

function hangul_choseong_to_cjamo($ch)
{
    static $table = array(
    0x3131,     /* 0x1100 */
    0x3132,     /* 0x1101 */
    0x3134,     /* 0x1102 */
    0x3137,     /* 0x1103 */
    0x3138,     /* 0x1104 */
    0x3139,     /* 0x1105 */
    0x3141,     /* 0x1106 */
    0x3142,     /* 0x1107 */
    0x3143,     /* 0x1108 */
    0x3145,     /* 0x1109 */
    0x3146,     /* 0x110a */
    0x3147,     /* 0x110b */
    0x3148,     /* 0x110c */
    0x3149,     /* 0x110d */
    0x314a,     /* 0x110e */
    0x314b,     /* 0x110f */
    0x314c,     /* 0x1110 */
    0x314d,     /* 0x1111 */
    0x314e      /* 0x1112 */
    );
    if ($ch < 0x1100 || $ch > 0x1112)
    {
        if ($ch == 0x1140)
            return 0x317f;
        else if ($ch == 0x114C)
            return 0x3181;
        else if ($ch == 0x1159)
            return 0x3186;
    return 0;
    }
    return $table[$ch - 0x1100];
}


function hangul_jungseong_to_cjamo($ch)
{
    static $table = array(
    0x314f,     /* 0x1161 */
    0x3150,     /* 0x1162 */
    0x3151,     /* 0x1163 */
    0x3152,     /* 0x1164 */
    0x3153,     /* 0x1165 */
    0x3154,     /* 0x1166 */
    0x3155,     /* 0x1167 */
    0x3156,     /* 0x1168 */
    0x3157,     /* 0x1169 */
    0x3158,     /* 0x116a */
    0x3159,     /* 0x116b */
    0x315a,     /* 0x116c */
    0x315b,     /* 0x116d */
    0x315c,     /* 0x116e */
    0x315d,     /* 0x116f */
    0x315e,     /* 0x1170 */
    0x315f,     /* 0x1171 */
    0x3160,     /* 0x1172 */
    0x3161,     /* 0x1173 */
    0x3162,     /* 0x1174 */
    0x3163      /* 0x1175 */
    );
    if ($ch < 0x1161 || $ch > 0x1175)
    {
        if ($ch == 0x119E)
            return 0x318D;
    return 0;
    }
    return $table[$ch - 0x1161];
}

function hangul_jongseong_to_cjamo($ch)
{
    static $table = array(
    0x3131,     /* 0x11a8 */
    0x3132,     /* 0x11a9 */
    0x3133,     /* 0x11aa */
    0x3134,     /* 0x11ab */
    0x3135,     /* 0x11ac */
    0x3136,     /* 0x11ad */
    0x3137,     /* 0x11ae */
    0x3139,     /* 0x11af */
    0x313a,     /* 0x11b0 */
    0x313b,     /* 0x11b1 */
    0x313c,     /* 0x11b2 */
    0x313d,     /* 0x11b3 */
    0x313e,     /* 0x11b4 */
    0x313f,     /* 0x11b5 */
    0x3140,     /* 0x11b6 */
    0x3141,     /* 0x11b7 */
    0x3142,     /* 0x11b8 */
    0x3144,     /* 0x11b9 */
    0x3145,     /* 0x11ba */
    0x3146,     /* 0x11bb */
    0x3147,     /* 0x11bc */
    0x3148,     /* 0x11bd */
    0x314a,     /* 0x11be */
    0x314b,     /* 0x11bf */
    0x314c,     /* 0x11c0 */
    0x314d,     /* 0x11c1 */
    0x314e      /* 0x11c2 */
    );
    if ($ch < 0x11a8 || $ch > 0x11c2) {
        if ($ch == 0x11EB)
            return 0x317f;
        else if ($ch == 0x11F0)
            return 0x3181;
        else if ($ch == 0x11F9)
            return 0x3186;
        return 0;
    }
    return $table[$ch - 0x11a8];
}


function hangul_choseong_to_jongseong($ch)
{
    static $table = array(
      0x11a8,  /* choseong kiyeok      -> jongseong kiyeok      */
      0x11a9,  /* choseong ssangkiyeok -> jongseong ssangkiyeok */
      0x11ab,  /* choseong nieun       -> jongseong nieun       */
      0x11ae,  /* choseong tikeut      -> jongseong tikeut      */
      0x0,     /* choseong ssangtikeut -> jongseong tikeut      */
      0x11af,  /* choseong rieul       -> jongseong rieul       */
      0x11b7,  /* choseong mieum       -> jongseong mieum       */
      0x11b8,  /* choseong pieup       -> jongseong pieup       */
      0x0,     /* choseong ssangpieup  -> jongseong pieup       */
      0x11ba,  /* choseong sios        -> jongseong sios        */
      0x11bb,  /* choseong ssangsios   -> jongseong ssangsios   */
      0x11bc,  /* choseong ieung       -> jongseong ieung       */
      0x11bd,  /* choseong cieuc       -> jongseong cieuc       */
      0x0,     /* choseong ssangcieuc  -> jongseong cieuc       */
      0x11be,  /* choseong chieuch     -> jongseong chieuch     */
      0x11bf,  /* choseong khieukh     -> jongseong khieukh     */
      0x11c0,  /* choseong thieuth     -> jongseong thieuth     */
      0x11c1,  /* choseong phieuph     -> jongseong phieuph     */
      0x11c2   /* choseong hieuh       -> jongseong hieuh       */
    );
    if ($ch < 0x1100 || $ch > 0x1112)
    return 0;
    return $table[$ch - 0x1100];
}

// make a UTF-8 regular expression for Hangul
function utf8_hangul_getSearchRule($str,$lastchar=1, $use_unicode = true) {
    $rule='';

    $val=utf8_to_unicode($str);
    $len=sizeof($val);
    if ($lastchar and $len > 1) { // make a regex using with the last char
        $last=array_pop($val);
        $rule=unicode_to_utf8($val);
        $val=array($last);
        $len=sizeof($val);
    }

    for ($i=0;$i<$len;$i++) {
        $ch=$val[$i];

        $wch=array();
        $ustart=array();
        $uend=array();
        if (($ch >=0xac00 and $ch <=0xd7a3) or ($ch >=0x3130 and $ch <=0x318f)) {
            $wch=hangul_to_jamo(array($ch));
        } else {
            $rule.=unicode_to_utf8(array($ch));
            continue;
        }

        $wlen=sizeof($wch);
        $ket = '';
        if ($wlen>=3) {
            // 종각 => 종(각|가[가-깋])
            $mrule = array();
            $mrule[] = unicode_to_utf8(array($ch));

            $save = $wch[2];
            unset($wch[2]);
            $tmp = jamo_to_syllable($wch);
            $mrule[] = unicode_to_utf8($tmp);
            $save = hangul_jongseong_to_cjamo($save);
            $wch = hangul_to_jamo($save);
            $wlen = sizeof($wch);
            $rule.= '('.implode('|', $mrule);
            $ket = ')';
            if ($wlen > 1) {
                $rule.= ')';
                continue;
            }
        }
        if ($wlen==1) {
            if ($wch[0] >=0x1100 and $wch[0] <=0x1112) {
                $wch[1]=0x1161;
                $start=jamo_to_syllable($wch);
                $ustart=unicode_to_utf8($start);

                $wch[1]=0x1175;
                $wch[2]=0x11c2;
                $end=jamo_to_syllable($wch);
                $uend=unicode_to_utf8($end);
            } else {
                $rule.=unicode_to_utf8($wch).$ket;
                continue;
            }
        } else if ($wlen==2) {
            if ($wch[0] >=0x1100 and $wch[0] <=0x1112) {
                $start=jamo_to_syllable($wch);
                $ustart=unicode_to_utf8($start);

                $wch[2]=0x11c2;
                $end=jamo_to_syllable($wch);
                $uend=unicode_to_utf8($end);
            } else {
                $rule.=unicode_to_utf8($wch);
                continue;
            }
        }

        if ($use_unicode) {
            $crule = '['.$ustart.'-'.$uend.']';
        } else {
        $rule.= sprintf("\x%02X",ord($ustart[0]));
        $crule='';
        if ($ustart[1]==$uend[1]) {
            $crule.=sprintf("\x%02X",ord($ustart[1]));
            $crule.=sprintf("[\x%02X-\x%02X]",ord($ustart[2]),ord($uend[2]));
        } else {
            $sch=ord($ustart[1]);
            $ech=ord($uend[1]);

            $subrule=array();

            $subrule[]=sprintf("\x%02X[\x%02X-\\xBF]",$sch,ord($ustart[2]));
            if (($sch+1) == ($ech-1))
                $subrule[]=sprintf("\x%02X[\\x80-\\xBF]",($sch+1));
            else if (($sch+1) != $ech)
                $subrule[]=sprintf("[\x%02X-\x%02X][\\x80-\\xBF]",($sch+1),($ech-1));
            $subrule[]=sprintf("\x%02X[\\x80-\\x%02X]",ord($uend[1]),ord($uend[2]));
            $crule.='('.implode('|',$subrule).')';
        }
        }

        $rule.=$crule.$ket;
    }
    return $rule;
}

// vim:et:sw:sts=4:
?>
