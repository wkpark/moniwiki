<?php
// Copyright 2008-2010 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a HTML to PDF plugin using the TCPDF for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2008-12-01
// Name: TCPDF plugin
// Description: a HTML2PDF Plugin using the TCPDF
// URL: MoniWiki:Html2PdfPlugin
// Version: $Revision: 1.5 $
// License: GPL
// Usage: ?action=tcpdf
//
// $Id: html2pdf.php,v 1.5 2010/04/19 11:26:46 wkpark Exp $

function do_html2pdf($formatter,$options) {
    global $DBInfo,$Config;

    $conf=_load_php_vars("config/html2pdf.php");

    $libdir=!empty($conf['tcpdf_dir']) ? $conf['tcpdf_dir']:'tcpdf';
    $k_path_install = 'lib/'.$libdir.'/'; # required for config/tcpdf.php
    @require_once('config/tcpdf.php');

    @require_once('lib/'.$libdir.'/config/lang/eng.php');
    @require_once('lib/'.$libdir.'/tcpdf.php');

    if (!class_exists('TCPDF')) {
        $options['title']=_("The TCPDF class not found!");
        return do_invalid($formatter,$options);
    }

    if (!class_exists('XTCPDF')) {
        class XTCPDF extends TCPDF {
            var $toc = array();
            var $fontalias = array();

            function setFontAlias($alias) {
                $this->fontalias = array();
                foreach($alias as $k=>$v) {
                    array_push($this->fontlist,$k);
                }
                $this->fontalias = $alias;
            }

            function AddFont($family, $style='', $fontfile='') {
                $family=trim($family);
                if (!empty($family) and array_key_exists($family,$this->fontalias)) {
		    $family = strtolower($family);
                    $fontfile = $family;
                    $name = $this->fontalias[$family];
                    $fontfile = $this->fontalias[$family].'.php';
                    $fontdata = parent::AddFont($family, $style,$fontfile);
                    $key = $fontdata['fontkey'];
                    if ($this->fonts[$key]['type']=='core')
                        $this->fonts[$key]['name']=$this->CoreFonts[$name];
                    return $fontdata;
                }
                return parent::AddFont($family, $style,$fontfile);
            }

            function getHtmlDomArray($html) {
		$html = preg_replace('@<title>.*</title>@','',$html);
		$html = preg_replace('@<head>.*</head>@s','',$html);
		$html = preg_replace('@&quot;@','"',$html);
		#$html = preg_replace('@>\s+<@',"><",$html);
		$html = preg_replace('@>\n@',">",$html);
		$html = preg_replace('@/\*<\!\[CDATA\[.*\]\]>\*/\n?@Us','',$html);
		$html = preg_replace('@<pre[^>]*>@','<pre style="background-color:black;color:white">',$html);
                $dom = &parent::getHtmlDomArray($html);
                $sz = count($dom);
                for ($i=0; $i<$sz;$i++) {
                    $tag=&$dom[$i];
                    if (!empty($tag['opening']) and $tag['value']=='table') {
                        #$tag['attribute']['border']=1;
                        #$tag['attribute']['bgcolor']=array(200,200,200);
                        #$tag['bgcolor']=array(200,200,200);
                    #} else if (!empty($tag['opening']) and $tag['value']=='pre') {
                    #    $tag['bgcolor']=array(0,0,0);
                    #    $tag['fgcolor']=array(255,255,255);
                    #    $tag['fontname']='courier';
                    #} else if (!empty($tag['opening']) and $tag['value']=='div') {
                    #    $tag['bgcolor']=array(100,100,100);
                    }
                }
                #print "<pre>";
                #print_r($dom);
                #print "</pre>";
                return $dom;
            }

	    function closeHTMLTagHandler(&$dom, $key, $cell=false) {

		$tag = $dom[$key];
                switch ($tag['value']) {
                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                case 'h6':
                    $i = $key;
                    $txt = '';
                    while ($dom[--$i]['value'] != $tag['value'] and $i > 0) {
                        if (!isset($dom[$i]['opening']))
                            $txt = $dom[$i]['value'].$txt;
                    }

                    $num = key($this->toc);
                    $dep = count(explode('.',$num));
                    $this->Bookmark($num.' '.$this->toc[$num],$dep,$this->y);
                    next($this->toc);
                }
                parent::closeHTMLTagHandler($dom, $key, $cell);
            }
        }
    }

    // define the share directory to create img
    define('X_PATH_SHARE_IMG', $DBInfo->cache_public_dir.'/html2pdf/');

    if (!file_exists(X_PATH_SHARE_IMG)) _mkdir_p(X_PATH_SHARE_IMG,0777); // XXX

    $formatter->nonexists='always';
    $formatter->section_edit=0;
    $formatter->perma_icon='';

    ob_start();
    $formatter->send_header();
    $formatter->send_page('',array('fixpath'=>1));
    print '</body></html>';
    $html=ob_get_contents();
    ob_end_clean();

    # begin
    $pdf = new XTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, $DBInfo->charset);
    include_once('function/toc.php');
    $toc = function_toc($formatter);
    $pdf->toc = $toc;
    $pdf->setFontAlias(array('monospace'=>'courier'));
    #$pdf->setLIsymbol(chr(42));
    #$pdf->setLIsymbol('a');

    // set default header data
    // $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
    $pdf->SetHeaderData($DBInfo->logo_img, 20, $formatter->page->name);
    $pdf->SetTitle($formatter->page->name);
    # $pdf->SetAuthor('Your name');
    $pdf->SetCreator('TCPDF/MoniWiki');
    $pdf->SetSubject($formatter->page->name);
    if (!empty($formatter->pi['#keywords']))
        $pdf->SetKeywords($keywords=$formatter->pi['#keywords']);

    // load default font
    $pdf->AddFont($conf['default_unifont']);
    $pdf->SetFont($conf['default_font']);

    // set header and footer fonts
    // $pdf->setHeaderFont(Array($conf['default_unifont'], '', PDF_FONT_SIZE_MAIN));
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    //set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    //set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    //set some language-dependent strings
    $pdf->setLanguageArray($l); 
    //set image scale factor
    //$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    # initialize document
    $pdf->AliasNbPages();

    # add a page
    $pdf->AddPage();
    $pdf->Bookmark($formatter->page->name,0,0);
    #
    $pdf->writeHTML($html, true, 0, false, false);

    # output
    $pdf->output(date("Ymd", time()).'.pdf', 'I');

    return;
}

// vim:et:sts=4:
?>
