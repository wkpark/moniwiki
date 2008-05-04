<?php
// Copyright 2005-2006 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a KoreanIndexer class for the MoniWiki
//
// $Id$
//
// EXPERIMENTAL !!

class KoreanIndexer {
    function KoreanIndexer() {
        include_once(dirname(__FILE__).'/compat.php');
        include_once(dirname(__FILE__).'/unicode.php');
        $this->_eomiRule();
        $this->_josaRule();
        $this->_wordDic();
    }

    function _wordDic() {
        global $DBInfo;

        $lines=file(dirname(__FILE__).'/../data/dict/word.txt.utf-8');
        foreach ($lines as $l) $this->_word[]=trim($l);
        $this->_word_rule=implode('|',$this->_word);
        #print $this->_eomi_rule;
    }

    function _eomiRule() {
        global $DBInfo;

        #ㄱ,ㄴ,ㄹ,ㅁ
        #$jos=array('x3134','x3139','x3141','x3142');

        $lines=file(dirname(__FILE__).'/../data/dict/eomi.txt.utf-8');
        foreach ($lines as $l) {
            $l=strtr($l,"*","?");
            $l=preg_replace('/^(.*)\?/','(\\1)?',$l);
            $this->_eomi[]=trim($l);
            #$v=mb_encode_numericentity($v,$DBInfo->convmap,'utf-8');
            #$v=utf8_mb_encode(trim($l));
            #$n=strtok(substr($v,2),';');
            #if (in_array($n,$jos)) print '&#'.$n.';';
        }
        $this->_eomi_rule=implode('|',$this->_eomi);
        #print $this->_eomi_rule;
    }

    function _josaRule() {
        $lines=file(dirname(__FILE__).'/../data/dict/josa.txt.utf-8');
        foreach ($lines as $l) {
            $l=strtr($l,"*","?");
            $l=preg_replace('/^(.*\?)/','(\\1)',$l);
            $this->_josa[]=trim($l);
        }

        $this->_josa_rule=implode('|',$this->_josa);
        #print $this->_josa_rule;
    }

    function getWordRule($word,$lastchar=1) {
        $rule=$word;
        $val=utf8_to_unicode($word);
        $len=sizeof($val);
        #print $word.':'.$len;
        if ($len >= 1) { // make a regex using with the last char
            $ch=array_pop($val);
            if (($ch >=0xac00 and $ch <=0xd7a3) or ($ch >=0x3130 and $ch <=0x318f)) {
                $jamo=hangul_to_jamo(array($ch));

                $wlen=sizeof($jamo);
                if ($wlen >=3) {
                    if (in_array($jamo[2],array(0x11ab,0x11af,0x11b7,0x11b8,0x11bb)) ) {
                        $rule=unicode_to_utf8($val);
                        if ($lastchar == 1) {
                            $rule.=unicode_to_utf8(jamo_to_syllable(array($jamo[0],$jamo[1])));
                        } else {
                            $rule.=unicode_to_utf8(array(hangul_choseong_to_cjamo($jamo[0])));
                            $rule.=unicode_to_utf8(array(hangul_jungseong_to_cjamo($jamo[1])));
                        }
                        $rule.=unicode_to_utf8(array(hangul_jongseong_to_cjamo($jamo[2])));
                    }
                }
            }
        }
        return $rule;
    }

    function isWord($word) {
        // XXX
        preg_match('/^('.$this->_word_rule.')$/S',$word,$match);
        if ($match[1]) return true;
        return false;
    }

    function getStem($word,&$match,&$type) {
        // XXX
        $type=1;
        if ($this->isWord($word)) return $word;
        $stem=$this->getNoun($word,$match);
        if ($stem and $this->isWord($stem)) return $stem;
        $verb=$this->getVerb($word,$vmatch);
        if ($stem or $verb) {

            if (strlen($match[1]) <= strlen($vmatch[1])) {
                $type=2;
                $match=$vmatch;
                $stem=$verb;
            }
            return $stem;
        }
        $type=0;
        return false;
    }

    function getNoun($word,&$match) {
        // XXX
        # remove josa
        preg_match('/('.$this->_josa_rule.')$/S',$word,$match);
        if (!empty($match[1])) {
            $pword=substr($word,0,-strlen($match[1]));
            if ($pword and $this->isWord($pword)) return $pword;
            $pword=$this->getWordRule($pword).$match[1];
            preg_match('/('.$this->_josa_rule.')$/S',$pword,$nmatch);
        } else {
            $word=$this->getWordRule($word);
            preg_match('/('.$this->_josa_rule.')$/S',$word,$match);
        }
        if ($match[1] and $nmatch[1] and (strlen($match[1]) < strlen($nmatch[1]))) {
            $match=$nmatch;
            $word=$pword;
        }
        if ($match) {
            #print "<pre>";
            #print_r($match);
            #print "</pre>";
            $stem=substr($word,0,-strlen($match[1]));
            return $stem;
        }
        return false;
    }

    function isHangul($ch) {
        if (($ch >=0xac00 and $ch <=0xd7a3) or ($ch >=0x3130 and $ch <=0x318f))
            return true;
        return false;
    }

    function getVerb($word,&$match) {
        # remove eomi
        $save='';
        preg_match('/('.$this->_eomi_rule.')$/S',$word,$match);
        $word1=$this->getWordRule($word);
        preg_match('/('.$this->_eomi_rule.')$/S',$word1,$match1);
        if ($match[1] and $match1[1]) {
            if ((strlen($match[1]) <= strlen($match1[1])) ) {
                $match=$match1;
                $word=$word1;
            }
        } else if (!empty($match[1])) {
            $pword=substr($word,0,-strlen($match[1]));
            $pword=$this->getWordRule($pword).$match[1];
            preg_match('/('.$this->_eomi_rule.')$/S',$pword,$nmatch);

            if ($match[1] and $nmatch[1]) {
                if (strlen($match[1]) <= strlen($nmatch[1])) {
                    $match=$nmatch;
                    $word=$pword;
                }
            }
        } else if (!empty($match1[1])) {
            $match=$match1;
            $word=$word1;
        }
        if ($match) {
            #print $word."==".$match[1];
            $stem=substr($word,0,-strlen($match[1]));
        } else {
            $stem= $word;
        }

        return $this->verbIrr($stem,$match);
    }

    function verbIrr($stem,&$match) {
        # 각종 규칙 불규칙 처리
        $ustem= utf8_to_unicode($stem);
        $uend= utf8_to_unicode($match[1]);
        $ch= array_pop($ustem);
        $ed= $uend[0];

        if ($this->isHangul($ch)) {
            $j= hangul_to_jamo($ch);
            $ej= hangul_to_jamo($ed);

            $sj= sizeof($j);

            if ($sj == 3 and $j[2] == 0x11bb /* ㅆ */ ) {
                // 랐-다, 었-다, 겠-다, 였-다
                if (in_array($j[1],array(0x1161,0x1165,0x1166,0x1167)) /* ㅏ,ㅓ,ㅔ,ㅕ */ ) {
                    if ($j[0] == 0x1105 and in_array($j[1],array(0x1161,0x1165,0x1167)) ) {
                        // 랐,렀,렸
                        // 갈렸-다



                    } else if (in_array($j[0], array(0x1100,0x110b,0x110c)) ) {
                        # 겠,았
                        array_unshift($uend,$ch);
                        unset($ch);
                    } else if ($j[1] == 0x1167 /* ㅕ */
                        and in_array($j[0],array(0x1101,0x1102,0x1103,0x1105,0x1106,0x1107,
                                                 0x1109,0x110c,0x110e,0x110f,0x1110,0x1111,0x1112)) ) {
                        # 여 변환
                        // 혔 -> ㅎ+었 -> 히+었
                        $j[1]=0x1165;
                        $syll=jamo_to_syllable(array(0x110b,$j[1],$j[2]));
                        array_unshift($uend,$syll[0]);

                        /* 혔 -> 히+었, 폈 -> 피+었 */
                        $j[1]=0x1175;

                        $syll=jamo_to_syllable(array($j[0],$j[1]));
                        $ch=$syll[0];
                    } else if (in_array($j[0],array(0x1101,0x1104,0x110a,0x1111,0x1112)) ) {
                        # 우 불규칙
                        /* 떴 -> ㄸ + 었 */
                        $syll=jamo_to_syllable(array(0x110b,$j[1],$j[2]));
                        array_unshift($uend,$syll[0]);

                        /* ㄸ -> 뜨 */
                        $j[1]=0x1173; /* ㅡ */
                        if ($j[0]== 0x1111) $j[1]=0x116e; /* 펐 푸+었 */
                        jamo_to_syllable(array($j[0],$j[1])); /* 쓰 */
                        $ch=$syll[0];
                    } else if (in_array($j[0],array(0x1101,0x1104,0x110a,0x1111,0x1112)) ) {
                    }
                } else if ($j[0]==0x1112 /* ㅎ */ and in_array($j[1],array(0x1162)) /* ㅐ */ ) {
                    array_push($ustem, 0xd558); /* 하 */;
                    $syll=jamo_to_syllable(array(0x110b,0x1167,0x11bb));
                    array_unshift($uend,$syll[0]);
                    #$match[1]='여'.$match[1]; /* 해 -> 하 + 여 */
                    unset($ch);
                } else { /* ㅆ를 떼어낸다. */
                    #print '~~'.$stem.'~~';
                    $syll=jamo_to_syllable(array($j[0],$j[1]));
                    array_unshift($uend,$j[2]);
                    #array_unshift($uend,hangul_jongseong_to_cjamo($j[2]));
                    $ch=$syll[0];
                    unset($j[2]);
                    #unset($ch);
                }
                if (!$ch) {
                    $ch= array_pop($ustem);
                    $j= hangul_to_jamo($ch);
                }
                $ed= $uend[0];
                $ej= hangul_to_jamo($ed);
            } else if (in_array($j[2],array(0x11ab, 0x11af,0x11b8)) /* ㄴ,ㄹ,ㅂ */ ) {
                // 합-시다   갑-시다   갈-래
                // 하-ㅂ시다 가-ㅂ시다 가-ㄹ래
                //
                if ($j[2]== 0x11af and $ej[0]==0x1105) {
                //if ($j[1] == 0x1173 and $j[2]== 0x11af and $ej[0]==0x1105) {
                    // 르 불규칙
                    // 흘-러:흐르+러
                    unset($j[2]);
                    $syll=jamo_to_syllable($j);
                    array_push($ustem,$syll[0]); /* 흐 */
                    $j[0]=$ej[0];
                    $j[1]=0x1173;
                    $syll=jamo_to_syllable($j); /* 르 */
                    $ch=$syll[0];
                } else {
                    array_unshift($uend,$j[2]);
                    $syll=jamo_to_syllable(array($j[0],$j[1]));
                    $ch=$syll[0];
                    $ed=$j[2];
                    unset($j[2]);
                }
            }
            
            // ㄷ 불규칙
            // 들-어 -> 듣-다
            $sj=sizeof($j);
            if ($sj == 3 and $j[2] == 0x11af and in_array($ej[0],array(0x110b,0x1105) /* ㅇ,ㄹ*/)) {
                while (in_array($ej[1],array(0x1161,0x1165,0x1173)) /* ㅏㅓㅡ */ ) {
                    // 아어으
                    // 라러르
                    $se=sizeof($ej);
                    if ($se==3) {
                        if ($ej[1]==0x1173 and !in_array($ej[2],0x11ab,0x11af)) break;
                        // 은을
                    } else {
                        if ($j[2]==0x11af and sizeof($ej)==2 and $ej[0] == 0x1105) break;
                    }
                    $syll=jamo_to_syllable(array($j[0],$j[1],0x11ae));
                    $ch=$syll[0];
                    break;
                }
            }

            // ㅅ 불규칙
            // * 지-어:짓-어
            // * 이-어:잇-어
            if (sizeof($ej) ==2) {
                if ($ej[0]==0x110b /* ㅓ */) {
                    $j[2]=0x11ba;
                    $syll=jamo_to_syllable($j); /* +ㅅ */
                    $ch=$syll[0];
                    $sj=3;
                }
            }

            if ($sj == 2) {
                if (in_array($j[0],array(0x110c) /* ㅈ */ )
                    and in_array($j[1],array(0x116e,0x1175)) /* ㅜ,ㅣ */ ) {
                    /* 주, 지 */
                    array_unshift($uend,$ch);
                    unset($ch);
                    $ch= array_pop($ustem);
                    $j= hangul_to_jamo($ch);
                }
                if ($j[1]==0x1165 /* ㅓ */ and in_array($j[0],array(0x1101,0x1104,0x110a,0x1111)) ) {
                    /* 꺼,떠,써,퍼 */
                    $syll=jamo_to_syllable(array(0x110b,0x1165)); /* 어 */
                    array_unshift($uend,$syll[0]);
                    if ($j[0] == 0x1111)
                        $syll=jamo_to_syllable(array($j[0],0x116e)); /* 푸 */
                    else
                        $syll=jamo_to_syllable(array($j[0],0x1173)); /* 쓰 */
                    array_push($ustem,$syll[0]);
                    unset($ch);
                    $ch= array_pop($ustem);
                    $j= hangul_to_jamo($ch);
                }

                // 음운 축약
                if (in_array($j[0],array(0x1105, 0x1112)) and $j[1]==0x1162) {
                    // ㅎ 불규칙(어미) 파랗+아서 -> 파라+아서 -> 파래서
                    /* 파래-서 -> 파라-아서 */
                    $j[1]=0x1161;
                    $syll=jamo_to_syllable($j); /* 래 -> 라+ 아 */
                    $ch=$syll[0];
                    $syll=jamo_to_syllable(array(0x110b,0x1161)); /* 아 */
                    $ed=$syll[0];
                    array_unshift($uend,$ed);
                    $ej[0]=0x110b;
                    $ej[0]=0x1161;
                } else if ($j[0]==0x1112 /* ㅎ */ and in_array($j[1],array(0x1162)) /* ㅐ */ ) {
                    // 해-서 = 하-여서
                    $j[1]=0x1161;
                    $syll=jamo_to_syllable($j); /* 해 -> 하 + 여 */
                    $ch=$syll[0];
                    $syll=jamo_to_syllable(array(0x110b,0x1167)); /* 여 */
                    $ed=$syll[0];
                    array_unshift($uend,$ed);
                    $ej[0]=0x110b;
                    $ej[0]=0x1167;
                } else if (in_array($j[0],array(0x1105,0x1109)) /* ㄹ,ㅅ */
                    and in_array($j[1],array(0x1167)) /* ㅕ */ ) {
                        // 하셔-서 = 하시-어서
                        // 가려-서 = 가리-어서
                    $j[1]=0x1175; /* ㅣ */
                    $syll=jamo_to_syllable($j); /* ㅕ -> 이-어 */
                    $ch=$syll[0];
                    $syll=jamo_to_syllable(array(0x110b,0x1165)); /* 어 */
                    $ed=$syll[0];
                    array_unshift($uend,$ed);
                    $ej[0]=0x110b;
                    $ej[0]=0x1165;
                }

                if ($j[0]== 0x1109 and $j[1]==0x1175) { /* 시: 존칭처리 */
                    array_unshift($uend,$ch);
                    $ej= $j;
                    $ch= array_pop($ustem);
                    $j= hangul_to_jamo($ch);
                }

                // ㅎ 불규칙
                if (in_array($j[0],array(0x1105,0x1106) /* ㄹ,ㅁ */ )
                    and in_array($j[1],array(0x1161,0x1165)) /* 라,러 */ ) {
                    $syll=jamo_to_syllable(array($j[0],$j[1],0x11c2)); /* 랗,렇 */
                    array_push($ustem,$syll[0]);
                    unset($ch);
                    unset($j);
                }
            }

            while ($sj == 2 and $j[0] == 0x110b
                and in_array($j[1],array(0x116a,0x116e,0x116f)) and sizeof($ustem)>=1 ) {
                    // XXX
                // 그리워: 그리우+어 -> 그립+워
                # /* 와 우 워 */
                $ch1=array_pop($ustem);
                $jamo=hangul_to_jamo($ch1);
                if (sizeof($jamo)==2) {
                    if ($jamo[1] != 0x1175) {
                        $syll=jamo_to_syllable(array($jamo[0],$jamo[1],0x11b8));
                        array_push($ustem,$syll[0]);
                        /* add ㅂ */
                    } else {
                        array_push($ustem,$ch1);
                    }
                    array_unshift($uend,$ch);
                    unset($ch);
                } else {
                    array_push($ustem,$ch1);
                }

                break;
            }

            if ($ch) array_push($ustem,$ch);
            $match[1]= unicode_to_utf8($uend);
            return unicode_to_utf8($ustem);
        }

        $match[1]=$save.$match[1];
        return $stem;

        #print "<pre>";
        #print($word.'-'.$match[1]);
        #print_r($match);
    }
}

// vim:et:sts=4:sw=4:
