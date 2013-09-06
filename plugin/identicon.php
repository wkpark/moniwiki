<?php
/**
 * Generates persistent specific geometric icons for each user
 * based on the ideas of Don Park's visual-security-9-block-ip-identification.
 *
 * modified verion for MoniWiki by wkpark at kldp.org
 *
 *
 * Plugin Name: WP_Identicon
 * Version: 1.02_mw
 * Plugin URI: http://scott.sherrillmix.com/blog/blogger/wp_identicon/
 * Description: This plugin generates persistent specific geometric icons for each user.
 * Author: Scott Sherrill-Mix
 * Author URI: http://scott.sherrillmix.com/blog/
 * License: GPLv2 (http://wordpress.org/about/license/)
 * Revision: $Id: identicon.php,v 1.1 2010/09/11 13:19:08 wkpark Exp $
*/

class identicon {
	var $identicon_options;
	var $blocks;
	var $shapes;
	var $rotatable;
	var $square;
	var $im;
	var $colors;
	var $size;
	var $blocksize;
	var $quarter;
	var $half;
	var $diagonal;
	var $halfdiag;
	var $transparent=false;
	var $centers;
	var $shapes_mat;
	var $symmetric_num;
	var $rot_mat;
	var $invert_mat;
	var $rotations;

	//constructor
	function identicon($blocks='') {
		$this->identicon_options=identicon_get_options();
		if ($blocks) $this->blocks=$blocks; 
		else $this->blocks=$this->identicon_options['squares'];
		$this->blocksize=80;
		$this->size=$this->blocks*$this->blocksize;
		$this->quarter=$this->blocksize/4;
		$this->half=$this->blocksize/2;
		$this->diagonal=sqrt($this->half*$this->half+$this->half*$this->half);
		$this->halfdiag=$this->diagonal/2;
		$this->shapes=array(
			array(array(array(90,$this->half),array(135,$this->diagonal),array(225,$this->diagonal),array(270,$this->half))),//0 rectangular half block
			array(array(array(45,$this->diagonal),array(135,$this->diagonal),array(225,$this->diagonal),array(315,$this->diagonal))),//1 full block
			array(array(array(45,$this->diagonal),array(135,$this->diagonal),array(225,$this->diagonal))),//2 diagonal half block
			array(array(array(90,$this->half),array(225,$this->diagonal),array(315,$this->diagonal))),//3 triangle
			array(array(array(0,$this->half),array(90,$this->half),array(180,$this->half),array(270,$this->half))),//4 diamond
			array(array(array(0,$this->half),array(135,$this->diagonal),array(270,$this->half),array(315,$this->diagonal))),//5 stretched diamond
			array(array(array(0,$this->quarter),array(90,$this->half),array(180,$this->quarter)), array(array(0,$this->quarter),array(315,$this->diagonal),array(270,$this->half)), array(array(270,$this->half),array(180,$this->quarter),array(225,$this->diagonal))),// 6 triple triangle
			array(array(array(0,$this->half),array(135,$this->diagonal),array(270,$this->half))),//7 pointer
			array(array(array(45,$this->halfdiag),array(135,$this->halfdiag),array(225,$this->halfdiag),array(315,$this->halfdiag))),//9 center square
			array(array(array(180,$this->half),array(225,$this->diagonal),array(0,0)), array(array(45,$this->diagonal),array(90,$this->half),array(0,0))),//9 double triangle diagonal
			array(array(array(90,$this->half),array(135,$this->diagonal),array(180,$this->half),array(0,0))),//10 diagonal square
			array(array(array(0,$this->half),array(180,$this->half),array(270,$this->half))),//11 quarter triangle out
			array(array(array(315,$this->diagonal),array(225,$this->diagonal),array(0,0))),//12quarter triangle in
			array(array(array(90,$this->half),array(180,$this->half),array(0,0))),//13 eighth triangle in
			array(array(array(90,$this->half),array(135,$this->diagonal),array(180,$this->half))),//14 eighth triangle out
			array(array(array(90,$this->half),array(135,$this->diagonal),array(180,$this->half),array(0,0)), array(array(0,$this->half),array(315,$this->diagonal),array(270,$this->half),array(0,0))),//15 double corner square
			array(array(array(315,$this->diagonal),array(225,$this->diagonal),array(0,0)), array(array(45,$this->diagonal),array(135,$this->diagonal),array(0,0))),//16 double quarter triangle in
			array(array(array(90,$this->half),array(135,$this->diagonal),array(225,$this->diagonal))),//17 tall quarter triangle
			array(array(array(90,$this->half),array(135,$this->diagonal),array(225,$this->diagonal)), array(array(45,$this->diagonal),array(90,$this->half),array(270,$this->half))),//18 double tall quarter triangle
			array(array(array(90,$this->half),array(135,$this->diagonal),array(225,$this->diagonal)), array(array(45,$this->diagonal),array(90,$this->half),array(0,0))),//19 tall quarter + eighth triangles
			array(array(array(135,$this->diagonal),array(270,$this->half),array(315,$this->diagonal))),//20 tipped over tall triangle
			array(array(array(180,$this->half),array(225,$this->diagonal),array(0,0)), array(array(45,$this->diagonal),array(90,$this->half),array(0,0)), array(array(0,$this->half),array(0,0),array(270,$this->half))),//21 triple triangle diagonal
			array(array(array(0,$this->quarter),array(315,$this->diagonal),array(270,$this->half)), array(array(270,$this->half),array(180,$this->quarter),array(225,$this->diagonal))),//22 double triangle flat
			array(array(array(0,$this->quarter),array(45,$this->diagonal),array(315,$this->diagonal)), array(array(180,$this->quarter),array(135,$this->diagonal),array(225,$this->diagonal))),//23 opposite 8th triangles
			array(array(array(0,$this->quarter),array(45,$this->diagonal),array(315,$this->diagonal)), array(array(180,$this->quarter),array(135,$this->diagonal),array(225,$this->diagonal)), array(array(180,$this->quarter),array(90,$this->half),array(0,$this->quarter),array(270,$this->half))),//24 opposite 8th triangles + diamond
			array(array(array(0,$this->quarter),array(90,$this->quarter),array(180,$this->quarter),array(270,$this->quarter))),//25 small diamond
			array(array(array(0,$this->quarter),array(45,$this->diagonal),array(315,$this->diagonal)), array(array(180,$this->quarter),array(135,$this->diagonal),array(225,$this->diagonal)), array(array(270,$this->quarter),array(225,$this->diagonal),array(315,$this->diagonal)),array(array(90,$this->quarter),array(135,$this->diagonal),array(45,$this->diagonal))),//26 4 opposite 8th triangles
			array(array(array(315,$this->diagonal),array(225,$this->diagonal),array(0,0)), array(array(0,$this->half),array(90,$this->half),array(180,$this->half))),//27 double quarter triangle parallel
			array(array(array(135,$this->diagonal),array(270,$this->half),array(315,$this->diagonal)), array(array(225,$this->diagonal),array(90,$this->half),array(45,$this->diagonal))),//28 double overlapping tipped over tall triangle
			array(array(array(90,$this->half),array(135,$this->diagonal),array(225,$this->diagonal)), array(array(315,$this->diagonal),array(45,$this->diagonal),array(270,$this->half))),//29 opposite double tall quarter triangle
			array(array(array(0,$this->quarter),array(45,$this->diagonal),array(315,$this->diagonal)), array(array(180,$this->quarter),array(135,$this->diagonal),array(225,$this->diagonal)), array(array(270,$this->quarter),array(225,$this->diagonal),array(315,$this->diagonal)),array(array(90,$this->quarter),array(135,$this->diagonal),array(45,$this->diagonal)),array(array(0,$this->quarter),array(90,$this->quarter),array(180,$this->quarter),array(270,$this->quarter))),//30 4 opposite 8th triangles+tiny diamond
			array(array(array(0,$this->half),array(90,$this->half),array(180,$this->half),array(270,$this->half), array(270,$this->quarter),array(180,$this->quarter),array(90,$this->quarter),array(0,$this->quarter))),//31 diamond C
			array(array(array(0,$this->quarter),array(90,$this->half),array(180,$this->quarter),array(270,$this->half))),//32 narrow diamond
			array(array(array(180,$this->half),array(225,$this->diagonal),array(0,0)), array(array(45,$this->diagonal),array(90,$this->half),array(0,0)), array(array(0,$this->half),array(0,0),array(270,$this->half)), array(array(90,$this->half),array(135,$this->diagonal),array(180,$this->half))),//33 quadruple triangle diagonal
			array(array(array(0,$this->half),array(90,$this->half),array(180,$this->half),array(270,$this->half),array(0,$this->half), array(0,$this->quarter),array(270,$this->quarter),array(180,$this->quarter),array(90,$this->quarter),array(0,$this->quarter))),//34 diamond donut
			array(array(array(90,$this->half),array(45,$this->diagonal),array(0,$this->quarter)), array(array(0,$this->half),array(315,$this->diagonal),array(270,$this->quarter)), array(array(270,$this->half),array(225,$this->diagonal),array(180,$this->quarter))),//35 triple turning triangle
			array(array(array(90,$this->half),array(45,$this->diagonal),array(0,$this->quarter)), array(array(0,$this->half),array(315,$this->diagonal),array(270,$this->quarter))),//36 double turning triangle
			array(array(array(90,$this->half),array(45,$this->diagonal),array(0,$this->quarter)), array(array(270,$this->half),array(225,$this->diagonal),array(180,$this->quarter))),//37 diagonal opposite inward double triangle
			array(array(array(90,$this->half),array(225,$this->diagonal),array(0,0),array(315,$this->diagonal))),//38 star fleet
			array(array(array(90,$this->half),array(225,$this->diagonal),array(0,0),array(315,$this->halfdiag),array(225,$this->halfdiag), array(225,$this->diagonal),array(315,$this->diagonal))),//39 hollow half triangle
			array(array(array(90,$this->half),array(135,$this->diagonal),array(180,$this->half)), array(array(270,$this->half),array(315,$this->diagonal),array(0,$this->half))),//40 double eighth triangle out
			array(array(array(90,$this->half),array(135,$this->diagonal),array(180,$this->half),array(180,$this->quarter)), array(array(270,$this->half),array(315,$this->diagonal),array(0,$this->half),array(0,$this->quarter))),//42 double slanted square
			array(array(array(0,$this->half),array(45,$this->halfdiag), array(0,0),array(315,$this->halfdiag)), array(array(180,$this->half),array(135,$this->halfdiag), array(0,0),array(225,$this->halfdiag))),//43 double diamond
			array(array(array(0,$this->half),array(45,$this->diagonal), array(0,0),array(315,$this->halfdiag)), array(array(180,$this->half),array(135,$this->halfdiag), array(0,0),array(225,$this->diagonal))),//44 double pointer
		);
		$this->rotatable=array(1,4,8,25,26,30,34);
		$this->square=$this->shapes[1][0];	
		$this->symmetric_num=ceil($this->blocks*$this->blocks/4);
		for ($i=0;$i<$this->blocks;$i++) {
			for ($j=0;$j<$this->blocks;$j++) {
				$this->centers[$i][$j]=array($this->half+$this->blocksize*$j,$this->half+$this->blocksize*$i);
				$this->shapes_mat[$this->xy2symmetric($i,$j)]=1;
				$this->rot_mat[$this->xy2symmetric($i,$j)]=0;
				$this->invert_mat[$this->xy2symmetric($i,$j)]=0;
				if (floor(($this->blocks-1)/2-$i)>=0&floor(($this->blocks-1)/2-$j)>=0&($j>=$i|$this->blocks%2==0)) {
					$inversei=$this->blocks-1-$i;
					$inversej=$this->blocks-1-$j;
					$symmetrics=array(array($i,$j),array($inversej,$i),array($inversei,$inversej),array($j,$inversei));
					$fill=array(0,270,180,90);
					for ($k=0;$k<count($symmetrics);$k++) {
						$this->rotations[$symmetrics[$k][0]][$symmetrics[$k][1]]=$fill[$k];
					}
				}
			}
		}
	}
	
	function xy2symmetric($x,$y) {
		$index=array(floor(abs(($this->blocks-1)/2-$x)),floor(abs(($this->blocks-1)/2-$y)));
		sort($index);
		$index[1]*=ceil($this->blocks/2);
		$index=array_sum($index);
		return $index;
	}
	


	//convert array(array(heading1,distance1),array(heading1,distance1)) to array(x1,y1,x2,y2)
	function identicon_calc_x_y($array,$centers,$rotation=0) {
		$output=array();
		$centerx=$centers[0];
		$centery=$centers[1];
		while($thispoint=array_pop($array)) {
			$y=round($centery+sin(deg2rad($thispoint[0]+$rotation))*$thispoint[1]);
			$x=round($centerx+cos(deg2rad($thispoint[0]+$rotation))*$thispoint[1]);
			array_push($output,$x,$y);
		}
		return $output;
	}

	//draw filled polygon based on an array of (x1,y1,x2,y2,..)
	function identicon_draw_shape($x,$y) {
		#print $x.'/'.$y.'<br/>';
		$index=$this->xy2symmetric($x,$y);
		$shape=$this->shapes[$this->shapes_mat[$index]];
		$invert=$this->invert_mat[$index];
		$rotation=$this->rot_mat[$index];
		$centers=$this->centers[$x][$y];
		$invert2=abs($invert-1);
		$points=$this->identicon_calc_x_y($this->square,$centers,0);
		$num = count($points) / 2;
		imagefilledpolygon($this->im, $points, $num, $this->colors[$invert2]);
		foreach($shape as $subshape) {
			$points=$this->identicon_calc_x_y($subshape,$centers,$rotation+$this->rotations[$x][$y]);
			$num = count($points) / 2;
			imagefilledpolygon($this->im, $points, $num,$this->colors[$invert]);
		}
	}

	//use a seed value to determine shape, rotation, and color
	function identicon_set_randomness($seed="") {
		//set seed
		foreach ($this->rot_mat as $key => $value) {
			$this->rot_mat[$key]=mt_rand(0,3)*90;
			$this->invert_mat[$key]=mt_rand(0,1);
			#&$this->blocks%2
			if ($key==0) $this->shapes_mat[$key]=$this->rotatable[mt_rand(0, count($this->rotatable) - 1)];
			else $this->shapes_mat[$key]=mt_rand(0, count($this->shapes)-1);
		}
		$forecolors=array(mt_rand($this->identicon_options['forer'][0],$this->identicon_options['forer'][1]), mt_rand($this->identicon_options['foreg'][0],$this->identicon_options['foreg'][1]), mt_rand($this->identicon_options['foreb'][0],$this->identicon_options['foreb'][1]));
		$this->colors[1]=imagecolorallocate($this->im, $forecolors[0],$forecolors[1],$forecolors[2]);
		if (array_sum($this->identicon_options['backr']) + array_sum($this->identicon_options['backg']) + array_sum($this->identicon_options['backb'])==0) {
			$this->colors[0]=imagecolorallocatealpha($this->im,0,0,0,127);
			$this->transparent=true;
			imagealphablending ($this->im,false);
			imagesavealpha($this->im,true);
		} else {
			$backcolors=array(mt_rand($this->identicon_options['backr'][0],$this->identicon_options['backr'][1]), mt_rand($this->identicon_options['backg'][0],$this->identicon_options['backg'][1]), mt_rand($this->identicon_options['backb'][0],$this->identicon_options['backb'][1]));
			$this->colors[0]=imagecolorallocate($this->im, $backcolors[0],$backcolors[1],$backcolors[2]);
		}
		if($this->identicon_options['grey']) {
			$this->colors[1]=imagecolorallocate($this->im, $forecolors[0],$forecolors[0],$forecolors[0]);
			if(!$this->transparent) $this->colors[0]=imagecolorallocate($this->im, $backcolors[0],$backcolors[0],$backcolors[0]);
		}
		return true;
	}

	function identicon_build($seed='',$img=true,$outsize='',$random=true,$displaysize='') {
		//make an identicon and return the filepath or if write=false return picture directly
		if (function_exists("gd_info")) {
			// init random seed
			if ($random) {
				$id=substr(sha1($seed),0,10);
				mt_srand(hexdec($id));
			}
			else $id=$seed;
			if ($outsize=='') $outsize=$this->identicon_options['size'];
			if($displaysize=='') $displaysize=$outsize;

			// HTTP Conditional get.
			$mtime = filemtime(__FILE__);
			$lastmod = substr(gmdate('r', $mtime), 0, -5).'GMT';
			$etag = $mtime . $displaysize . $outsize . $id;
			$etag = md5($etag);
			$need = http_need_cond_request($mtime, $lastmod, $etag);
			$maxage = 60*60*24*7;

			header('Last-Modified: '.$lastmod);
			header('ETag: "' .$etag. '"');
    			header('Cache-Control: private, max-age='.$maxage);
			header('Pragma: cache');
			if (!$need) {
				header('HTTP/1.0 304 Not Modified');
				@ob_end_clean();
				return true;
			}
			//

			$this->im = imagecreatetruecolor($this->size,$this->size);	
			$this->colors = array(imagecolorallocate($this->im, 255,255,255));
			if ($random) $this->identicon_set_randomness($id);
			else {$this->colors = array(imagecolorallocate($this->im, 255,255,255),imagecolorallocate($this->im, 0,0,0));$this->transparent=false;};
			imagefill($this->im,0,0,$this->colors[0]);
			for ($i=0;$i<$this->blocks;$i++) {
				for ($j=0;$j<$this->blocks;$j++) {
					$this->identicon_draw_shape($i,$j);
				}
			}

			$out = @imagecreatetruecolor($outsize,$outsize);
			imagesavealpha($out,true);
			imagealphablending($out,false);
			imagecopyresampled($out,$this->im,0,0,0,0,$outsize,$outsize,$this->size,$this->size);
			imagedestroy($this->im);
			header ("Content-type: image/png");
			imagepng($out);
			imagedestroy($out);
			return true;
		} else { //php GD image manipulation is required
			return false; //php GD image isn't installed but don't want to mess up blog layout
		}
	}

	function identicon_display_parts() {
		$this->identicon(1);
		for ($i=0;$i<count($this->shapes);$i++) {
			$this->shapes_mat=array($i);
			$this->invert_mat=array(1);
			$output.=$this->identicon_build($seed='example'.$i,$img=true,$outsize=30,$random=false);
			$counter++;
		}
		$this->identicon();
		return $output;
	}
}


function identicon_get_options() {
	//Set Default Values Here
	$default_array = array(
		'size'=>35,
		'backr'=>array(255,255),
		'backg'=>array(255,255),
		'backb'=>array(255,255),
		'forer'=>array(1,255),
		'foreg'=>array(1,255),
		'foreb'=>array(1,255),
		'squares'=>3,
		'autoadd'=>1,
		'grey'=>0,
	);

	$identicon_array = $default_array;
	return($identicon_array);
}

function do_identicon($formatter = null, $params) {
	//create identicon for later use
	static $identicon = null;

	if (!$identicon) $identicon = new identicon;

	if (!empty($params['seed']))
		$seed = $params['seed'];
	else
		$seed = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);

	$img = true;
	$outsize = '';
	$random = true;
	$displaysize = '';

	return $identicon->identicon_build($seed, $img, $outsize, $random, $displaysize);
}
