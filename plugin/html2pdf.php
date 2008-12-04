<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a HTML to PDF plugin using the TCPDF for the MoniWiki
//
// Author: Won-Kyu Park <wkpark@kldp.org>
// Date: 2008-12-01
// Name: TCPDF plugin
// Description: a HTML2PDF Plugin using the TCPDF
// URL: MoniWiki:Html2PdfPlugin
// Version: $Revision$
// License: GPL
// Usage: ?action=tcpdf
//
// $Id$


function do_html2pdf($formatter,$options) {
    global $DBInfo;

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

    // define the share directory to create img
    define('X_PATH_SHARE_IMG', $DBInfo->cache_public_dir.'/html2pdf/');

    if (!file_exists(X_PATH_SHARE_IMG)) _mkdir_p(X_PATH_SHARE_IMG,0777); // XXX

    $formatter->nonexists='always';
    $formatter->section_edit=0;

    ob_start();
    $formatter->send_header();
    $formatter->send_page('',array('fixpath'=>1));
    print '</body></html>';
    $html=ob_get_contents();
    ob_end_clean();

    # begin
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, $DBInfo->charset);

    // set default header data
    //$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
    $pdf->SetHeaderData(PDF_HEADER_LOGO, 20, $formatter->page->name);
    $pdf->SetTitle($formatter->page->name);
    # $pdf->SetAuthor('Your name');
    $pdf->SetCreator('TCPDF/MoniWiki');
    $pdf->SetSubject($formatter->page->name);
    if ($formatter->pi['#keywords'])
        $pdf->SetKeywords($keywords=$formatter->pi['#keywords']);

    // load default font
    $pdf->AddFont($conf['default_unifont']);
    $pdf->SetFont($conf['default_font']);

    // set header and footer fonts
    $pdf->setHeaderFont(Array($conf['default_unifont'], '', PDF_FONT_SIZE_MAIN));
    //$pdf->setHeaderFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    //set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    //set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    //set image scale factor
    //$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    # initialize document
    $pdf->AliasNbPages();

    # add a page
    $pdf->AddPage();
    #
    $pdf->writeHTML($html, true, 0, true, true);

    # output
    $pdf->output(date("Ymd", time()).'.pdf', 'I');

    return;
}

// vim:et:sts=4:
?>
