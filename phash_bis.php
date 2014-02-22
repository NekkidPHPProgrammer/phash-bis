<?php
	// This class constructs quasi-sortable perceptual hash for an image
	// It is based on the basic DCT hash but by re-organizing the hash bits
	// the hash is more or less sortable, so building basically a perceptual index
	// This facilitates finding duplicates in a big mass of images
	//
	// DCT code initial author: Elliot Shepherd
	//
	// Sort-friendly transformation author: The Nekkid PHP Programmer
	// http://nekkidphpprogrammer.blogspot.com
	// nekkidphpprogrammer<at>gmail.com
	//
	// For more inforamtion on the transform, please see:
	// http://nekkidphpprogrammer.blogspot.fi/2014/01/not-all-bits-are-created-equal.html
	//
	// simple usage: 
	// 
	// require_once("phash_bis.php");
	// $hashing=new Phash_Bis();	
	// $hash=$hashing->hash("example.jpg");
	// 
	// returned $hash value is hexadecimal string of the hash value (default 64-bits=16 hexa digits)
	//
	// If you want to change the default values, you can use constructor with params 
	// 
	// $hashing=new Phash_Bis(64);
	// Initial re-size 64 bits as opposed to default 32
	// REMARK!
	//   The speed decreases very fast with ascending initial re-size (O(n^3) if I'm right)
	//   Unfortunately also the quality of hash increases simultaneously
	// 
	// $hashing=new Phash_Bis(64,4);
	// Initial re-resize as in previous example, the lower 4 x 4 frequencies only used for hash -> 4 hexa hash
	//
	
  class Phash_Bis {
    private $size=32;
    private $small_size=8;	
    private $c=array();
    private $reorder=array();
       
    private function InitCoefficients() {
    	$this->c[0]=1.0/sqrt(2);
    	for ($i=1; $i < $this->size ; $i++)
    		$this->c[$i]=1.0;       	 	    		
//
// here we intialize the transformation matrix for setting most significant frequencies (bits) 
// in the beginning of the hash
//
   		for ($l=0;$l<$this->small_size;$l++) {
				$stp=$l*$l;
				for ($u=0;$u<=$l;$u++) {
					$this->reorder[$stp]=array($u,$l);
					$stp+=2;
				}
				$stp=$l*$l+1;
				for ($v=0;$v<$l;$v++) {
					$indexer=$l*$this->small_size+$v;
					$this->reorder[$stp]=array($l,$v);
					$stp+=2;
				}
			}
			ksort($this->reorder);
    }
    
    private function blue($img,$x,$y) {
    	return imagecolorat($img,$x, $y) & 0xff;
    }
        
    public function __construct($my_size=32,$my_small_size=8) {
    	$this->size=$my_size;
    	$this->small_size=$my_small_size;
    	$this->InitCoefficients();
    }
    
    private function ApplyDCT($f) {
    	$n=$this->size;
    	$F=array();
    	for ($u=0;$u<$n;$u++) {
    		for ($v=0;$v<$n;$v++) {
    			$sum=0.0;
    			for ($i=0;$i<$n;$i++) {
    				for ($j=0;$j<$n;$j++) {
    					$sum+=cos(((2*$i+1)/(2.0*$n))*$u*M_PI)*cos(((2*$j+1)/(2.0*$n))*$v*M_PI)*($f[$i][$j]);
    				}
    			}
    			$sum*=(($this->c[$u]*$this->c[$v])/4.0);
    			$F[$u][$v]=$sum;
    		}
    	} 
    	return $F;
    }
    
    public function hash($image) {
			$timing=microtime(true);
			$hash="missing";
    	if (file_exists($image)) {
    		$size=$this->size;
    		$res=imagecreatefromstring(file_get_contents($image));
    		$img = imagecreatetruecolor($size, $size);
    		imagecopyresampled($img, $res, 0, 0, 0, 0, $size, $size, imagesx($res), imagesy($res));
				imagecopymergegray($img, $res, 0, 0, 0, 0, $size, $size, 0);
				$vals=array();
				for ($x=0;$x<$size;$x++) {
					for ($y=0;$y<$size;$y++) {
						$vals[$x][$y]=$this->blue($img,$x,$y);
					}
				}
				$dct_vals=$this->ApplyDCT($vals);
				$total=0.0;
				for ($x=0;$x<$this->small_size;$x++) {
					for ($y=0;$y<$this->small_size;$y++) {
						$total += $dct_vals[$x][$y];
					}
				}
				$total-=$dct_vals[0][0];
				$avg=$total/($this->small_size*$this->small_size-1);
				$hash=0;
//
// Transformed hash generation
//				
				foreach ($this->reorder as $ptr) {
					$hash = gmp_mul($hash, 2);
					if ($dct_vals[$ptr[0]][$ptr[1]]>$avg)
						$hash=gmp_add($hash, 1); ;
				}
//
//	Hash is returned by hexadecimal string, my preference
//				
				$hash_len=$this->small_size*$this->small_size/4;
				return substr(str_repeat("0",$hash_len) . gmp_strval($hash,$hash_len),-$hash_len);
				
				
	  	}
			return $hash;
    }
  }
?>