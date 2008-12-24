<?php
/*
 * This is a reduced FCKpackager file for Javascript Compression
 * for MoniWiki.
 *
 * $Id$
 *
 * ---------------------------------------------------------------------------
 * FCKpackager - JavaScript Packager and Compressor - http://www.fckeditor.net
 * Copyright (C) 2003-2008 Frederico Caldeira Knabben
 *
 * == BEGIN LICENSE ==
 *
 * Licensed under the terms of any of the following licenses at your
 * choice:
 *
 *  - GNU General Public License Version 2 or later (the "GPL")
 *    http://www.gnu.org/licenses/gpl.html
 *
 *  - GNU Lesser General Public License Version 2.1 or later (the "LGPL")
 *    http://www.gnu.org/licenses/lgpl.html
 *
 *  - Mozilla Public License Version 1.1 or later (the "MPL")
 *    http://www.mozilla.org/MPL/MPL-1.1.html
 *
 * == END LICENSE ==
 *
 * This is the main file of FCKpackager.
 *
 * You can call it through command line with "php fckpackager.php".
 */


class FCKConstantProcessor
{
    // Public properties.
    var $RemoveDeclaration ;
    var $HasConstants ;

    // Private properties.
    var $_Constants ;
    var $_ConstantsRegexPart ;

    function FCKConstantProcessor()
    {
        $this->RemoveDeclaration = TRUE ;
        $this->HasConstants = FALSE ;

        $this->_Constants = array() ;
        $this->_ConstantsRegexPart = '' ;
    }

    function AddConstant( $name, $value )
    {
        if ( strlen( $this->_ConstantsRegexPart ) > 0 )
            $this->_ConstantsRegexPart .= '|' ;

        $this->_ConstantsRegexPart .= $name ;

        $this->_Constants[ $name ] = $value ;

        $this->HasConstants = TRUE ;
    }

    function Process( $script )
    {
        if ( !$this->HasConstants )
            return $script;

        $output = $script ;

        if ( $this->RemoveDeclaration )
        {
            // /var\s+(?:BASIC_COLOR_RED|BASIC_COLOR_BLUE)\s*=.+?;/
            $output = preg_replace(
                '/var\\s+(?:' . $this->_ConstantsRegexPart . ')\\s*=.+?;/m',
                '', $output ) ;
        }

        $output = preg_replace_callback(
            '/(?<!(var |...\.))(?:' . $this->_ConstantsRegexPart . ')(?!(?:\s*=)|\w)/', // XXX
            array( &$this, '_Contant_Replace_Evaluator' ), $output ) ;

        return $output ;
    }

    function _Contant_Replace_Evaluator( $match )
    {
        $constantName = $match[0] ;

        if ( isset( $this->_Constants[ $constantName ] ) )
            return $this->_Constants[ $constantName ] ;
        else
            return $constantName ;
    }
}

class FCKFunctionProcessor
{
    var $_Function ;
    var $_Parameters ;

    var $_VarChars = array( 'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','W','X','Y','Z','a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','w','x','y','z' ) ;
    var $_VarCharsLastIndex ;

    var $_VarPrefix ;
    var $_LastCharIndex ;
    var $_NextPrefixIndex ;

    var $_IsGlobal ;

    function FCKFunctionProcessor( $function, $parameters, $isGlobal )
    {
        $this->_Function        = $function ;
        $this->_Parameters      = $isGlobal ? NULL : $parameters ;

        $this->_VarPrefix       = $isGlobal ? '_' : '' ;

        $this->_IsGlobal        = $isGlobal ;

        $this->_LastCharIndex   = 0;
        $this->_NextPrefixIndex = 0;

        $this->_VarCharsLastIndex   = count( $this->_VarChars ) - 1 ;
    }

    function Process()
    {
        $processed = $this->_Function ;

        if ( !$this->_IsGlobal )
            $processed = $this->_ProcessVars( $processed, $this->_Parameters ) ;

        // Match "var" declarations.
        $numVarMatches = preg_match_all( '/\bvar\b\s+((?:({(?:(?>[^{}]*)|(?2))*})|[^;])+?)(?=(?:\bin\b)|;)/', $processed, $varsMatches ) ;

        if ( $numVarMatches > 0 )
        {
            $vars = array() ;

            for ( $i = 0 ; $i < $numVarMatches ; $i++ )
            {
                $varsMatch = $varsMatches[1][$i];
                
                // Removed all (...), [...] and {...} blocks from the var
                // statement to avoid problems with commas inside them.
                $varsMatch = preg_replace( '/(\((?:(?>[^\(\)]*)|(?1))*\))+/', '', $varsMatch ) ;
                $varsMatch = preg_replace( '/(\[(?:(?>[^\[\]]*)|(?1))*\])+/', '', $varsMatch ) ;
                $varsMatch = preg_replace( '/({(?:(?>[^{}]*)|(?1))*})+/', '', $varsMatch ) ;
                $numVarNameMatches = preg_match_all( '/(?:^|,)\s*([^\s=,]+)/', $varsMatch, $varNameMatches ) ;
                
                for ( $j = 0 ; $j < $numVarNameMatches ; $j++ )
                {
                    $var = $varNameMatches[1][$j];
                    if (strlen($var) > 2) $vars[$var] = $var ;
                }
            }

            $processed = $this->_ProcessVars( $processed, $vars ) ;
        }

        return $processed ;
    }

    function _ProcessVars( $source, $vars )
    {
        $protected = array('self','event'); // workaround for eval()
        foreach ($protected as $p) unset ($vars[$p]);
        foreach ( $vars as $var )
        {
            $source = preg_replace( '/(?<!\w|\d|\.|\$)' . preg_quote( $var ) . '(?!\w|\d|:)/', $this->_GetVarName(), $source ) ;
        }

        return $source ;
    }

    function _GetVarName()
    {
        if ( $this->_LastCharIndex == $this->_VarCharsLastIndex )
        {
            $this->_RenewPrefix() ;
            $this->_LastCharIndex = 0 ;
        }

        $var = $this->_VarPrefix . $this->_VarChars[ $this->_LastCharIndex++ ] ;

        if ( preg_match( '/(?<!\w|\d|\.)' . preg_quote( $var ) . '(?!\w|\d)/', $this->_Function ) )
            return $this->_GetVarName() ;
        else
            return $var ;
    }

    function _RenewPrefix()
    {
        if ( strlen( $this->_VarPrefix) > 0 && $this->_VarPrefix != "_" )
        {
            if ( $this->_NextPrefixIndex > $this->_VarCharsLastIndex )
                $this->_NextPrefixIndex = 0 ;
            else
                $this->_VarPrefix = substr_replace( $this->_VarPrefix, '', strlen( $this->_VarPrefix ) - 1, 1 ) ;
        }

        $this->_VarPrefix .= $this->_VarChars[ $this->_NextPrefixIndex ] ;

        $this->_NextPrefixIndex++;
    }
}


class FCKJavaScriptCompressor
{
    function FCKJavaScriptCompressor()
    {}

    function Revision()
    {
        return trim(substr('$Revision$',10,-1));
    }

    // Call it statically. E.g.: FCKJavaScriptCompressor::Compress( ... )
    function Compress( $script, $constantsProcessor = null, $params=array('variable'=>1,'space'=>1) )
    {
        // detect cr and replace it with "\n"
        preg_match('/(\r\n|\r|\n)/s', $script, $cr);
        if (!empty($cr[0]) and $cr[0] != "\n") {
            $script = strtr($script, $cr[0], "\n");
            //$script = str_replace($cr[0],"\n",$script);
        }

        // Concatenates all string with escaping new lines strings (ending with \).
        $script = preg_replace(
            '/\\\\\n+/s',
            '\n', $script ) ;

        $stringsProc = new FCKStringsProcessor() ;

        // Protect the script strings.
        // buggy XXX
        $script = $stringsProc->ProtectStrings( $script ) ;

        // Remove "/* */" "//" comments
        $script = preg_replace(
            array('/(?<!\/)\/\*.*?\*\//s', '/\/\/.*$/m'),
            array('',''),
            $script ) ;
        
        // Remove spaces before the ";" at the end of the lines
        //$script = preg_replace(
        //    '/\s*(?=;\s*$)/m',
        //    '', $script ) ;

        // Remove spaces next to "="
        //$script = preg_replace(
        //    '/^([^"\'\r\n]*?)\s*=\s*/m',
        //    '$1=', $script ) ;

        // Remove spaces on "()": "( content )" = "(content)"
        //$script = preg_replace(
        //    '/^([^\r\n""\']*?\()\s+(.*?)\s+(?=\)[^\)]*$)/m',
        //    '$1$2', $script ) ;

        // Concatenate lines that doesn't end with [;{}] using a space
        if (!empty($params['space'])) {
            $script = preg_replace(
                '/(?<![;{}\n\s])\s*\n+\s*(?![\s\n{}])/s',
                ' ', $script ) ;
        }

        // Concatenate lines that end with "}" using a "\n", except for "else",
        // "while", "catch" and "finally" cases, or when followed by, "'", ";",
        // "}", ")" or "]".
        ///$script = preg_replace(
        ///    '/\s*}\s*\n+\s*(?!\s*(else|catch|finally|while|[\]}\),;]))/s',
        ///    "}\n", $script ) ;
        ## not needed ## XXX

	// Concat lines that end with ";" or "{"
        // $script = preg_replace('/\s*([;{])\s*[\n\r]+\s*/s','\\1', $script ) ;
        $script = preg_replace('/\s*([;{])\s*/s','\\1', $script ) ;
        ## $script = preg_replace('/\s*([;{}])\s*/s','\\1', $script ) ; // shorter 

        // Remove blank lines, spaces at the begining or the at the end and \n\r
        $script = preg_replace(
        #    #'/(^\s*$)|(^\s+)|(\s+$\n)/m',
            '/(^\s*$)|(^\s+)/m',
            '', $script ) ;

        // Remove the spaces between statements.
        //$script = FCKJavaScriptCompressor::_RemoveInnerSpaces( $script ) ;
        # error with mootools

        // Process constants.   // CHECK
        if ( is_object($constantsProcessor) and $constantsProcessor->HasConstants )
            $script = $constantsProcessor->Process( $script );

        // Replace "new Object()" "new Array()".
        $script = preg_replace(
            array('/new\s+Object\(\)/','/new\s+Array\(\)/'),
            array('{}','[]'), $script ) ;

        if (!empty($params['space'])) {
            $delim = '([+\-])|([:=?*\/&,;><|!{}\[\]\(\)])';
            // space + delim + space => delim, except a++ +b
            $script = preg_replace('/[ ]*('.$delim.')[ ]*(?(2)(?![+\-]))/', '\\1', $script);
        } else {
            // only some spaces are removed: ' : '=> ':', ' = '=> '='
            $script = preg_replace('/[ ]*([:=])[ ]*/', '\\1', $script);
        }
        // workaround for a = (b) ? c:d; case. c is not a object menber.
        $script = preg_replace('/(?<=\?)([^:]+):/', '\\1 :', $script);

        // Process function contents, renaming parameters and variables.
        if (!empty($params['variable']))
            $script = FCKJavaScriptCompressor::_ProcessFunctions( $script ) ;

        // Join consecutive string concatened with a "+".
        $script = $stringsProc->ConcatProtectedStrings( $script );

        // Restore the protected script strings.
        $script = $stringsProc->RestoreStrings( $script );

        return $script ;
    }

    function _RemoveInnerSpaces( $script )
    {
        // delim + space => delim
        return preg_replace('/([:=?+\-*\/&,;><|!{}\[\]\(\)])[ ]+(?=\S)/', '\\1', $script);
        #return preg_replace_callback(
        #    '/(?:\s*[=?:+\-*\/&,;><|!]\s*)|(?:[(\[]\s+)|(?:\s+[)\]])/',
        #    array( 'FCKJavaScriptCompressor', '_RemoveInnerSpacesMatch' ), $script ) ;
    }

    function _RemoveInnerSpacesMatch( $match )
    {
        return trim( $match[0] ) ;
    }

    function _ProcessFunctions( $script )
    {
        return preg_replace_callback(
            '/function(?:\s+\w+)?\s*\(\s*([^\)]*?)\s*\)\s*({(?:(?>[^{}]*)|(?2))*})+/',
            array( 'FCKJavaScriptCompressor', '_ProcessFunctionMatch' ), $script ) ;
    }

    function _ProcessFunctionMatch( $match )
    {
        // Creates an array with the parameters names ($match[1]).
        if ( strlen( trim( $match[1] ) ) == 0 )
            $parameters = array() ;
        else
            $parameters = preg_split( '/\s*,\s*/', trim( $match[1] ) ) ;

        $hasfuncProcessor = isset( $GLOBALS['funcProcessor'] ) ;

        if ( $hasfuncProcessor != TRUE )
            $GLOBALS['funcProcessor'] = new FCKFunctionProcessor( $match[0], $parameters, false ) ;
        else
        {
            $GLOBALS['funcProcessor']->_Function = $match[0];
            $GLOBALS['funcProcessor']->_Parameters = $parameters;
        }

        $processed = $GLOBALS['funcProcessor']->Process() ;
        
        $processed = substr_replace( $processed, '', 0, 8 ) ;

        $processed = FCKJavaScriptCompressor::_ProcessFunctions( $processed ) ;

        if ( $hasfuncProcessor != TRUE )
            unset( $GLOBALS['funcProcessor'] ) ;
        
        return 'function'. $processed ;
    }
}

class FCKStringsProcessor
{
    var $_ProtectedStrings ;

    function FCKStringsProcessor()
    {
        $_ProtectedStrings = array() ;
    }

    function ProtectStrings( $source )
    {
        // Catches string literals, regular expressions and conditional comments.
        return preg_replace_callback(
            // http://blog.stevenlevithan.com/archives/match-quoted-string
            // second regex term is buggy XXX
            #'/(?:("|\').*?(?<!\\\\)\1)|(?:(?<![\*\/\\\\])\/[^\/\*].*?(?<!\\\\)\/(?=([\.\w])|(\s*[,;}\)])))|(?s:\/\*@(?:cc_on|if|elif|else|end).*?@\*\/)/',
            '/(?:(["\'])(?:\\\\?+.)*?\1)|(?s:\/\*@(?:cc_on|if|elif|else|end).*?@\*\/)/',
            # old
            #'/(?:(["\'])(?:\\\\?+.)*?\1)|(?:(?<![\*\/\\\\])\/[^\/\*].*?(?<!\\\\)\/(?=([\.\w])|(\s*[,;}\)])))|(?s:\/\*@(?:cc_on|if|elif|else|end).*?@\*\/)/',
            array( &$this, '_ProtectStringsMatch' ), $source ) ;
    }

    function _ProtectStringsMatch( $match )
    {
        $this->_ProtectedStrings[] = $match[0] ;
        return '@' . ( count( $this->_ProtectedStrings ) - 1 ) . '@' ;
    }

    function ConcatProtectedStrings( $source )
    {
        return preg_replace_callback(
            '/@\d+@(?>@\d+@|\+)+@\d+@/',
            array( &$this, '_ConcatProtectedStringsMatch' ), $source ) ;
    }

    function _ConcatProtectedStringsMatch( $match )
    {
        // $match[0] is something like @2@+@3@+@4@+@5@

        $indexes = explode( '@+@', trim( $match[0], '@') ) ;

        $leftIndex  = (int)$indexes[0] ;
        $rightPosition = 1 ;

        $output = '@' . $leftIndex . '@' ;

        $sz = count( $indexes );

        while( $rightPosition < $sz )
        {
            $rightIndex = (int)$indexes[ $rightPosition ] ;

            $left   = $this->_ProtectedStrings[ $leftIndex ] ;
            $right  = $this->_ProtectedStrings[ $rightIndex ] ;

            if ( strncmp( $left, $right, 1 ) == 0 )
            {
                $left = substr_replace( $left, '', strlen( $left ) - 1, 1 ) ;
                $right = substr_replace( $right, '', 0, 1 ) ;

                $this->_ProtectedStrings[ $leftIndex ] = $left . $right ;
                $this->_ProtectedStrings[ $rightIndex ] = '' ;
            }
            else
            {
                $leftIndex = $rightIndex ;
                $output .= '+@' . $leftIndex . '@' ;
            }

            $rightPosition++ ;
        }

        return $output ;
    }

    function RestoreStrings( $source )
    {
        return preg_replace_callback(
            '/@(\d+)@/',
            array( &$this, '_RestoreStringsMatch' ), $source ) ;
    }

    function _RestoreStringsMatch( $match )
    {
        return $this->_ProtectedStrings[ (int)$match[1] ] ;
    }
}

// vim:et:sts=4:sw=4:
?>
