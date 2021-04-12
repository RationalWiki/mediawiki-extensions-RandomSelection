<?php
/*

 RandomSelection v2.1.3 -- 7/21/08

 This extension randomly displays one of the given options.

 Usage: <choose><option>A</option><option>B</option></choose>
 Optional parameter: <option weight="3"> == 3x weight given

 Author: Ross McClure [http://www.mediawiki.org/wiki/User:Algorithm]
*/

namespace MediaWiki\Extension\RandomSelection;

use Parser;
use PPFrame;

class RandomSelection {
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'choose', [ self::class, 'renderChosen' ] );
		$parser->setFunctionHook( 'choose', [ self::class, 'renderPF_obj' ], SFH_OBJECT_ARGS );
		return true;
	}

	public static function renderPF_obj( $parser, PPFrame $frame, $args ) {
		$options = array();
		$r = 0;

		//first one is not an object

		$arg = array_shift( $args );
		$parts = explode( '=', $arg, 2);
		if ( count($parts) == 2 ) {
			$options[] = array( intval(trim($parts[0])) , $parts[1] );
			$r += intval(trim($parts[0]));
		} elseif ( count($parts) == 1 ) {
			$options[] = array( 1 , $parts[0] );
			$r += 1;
		}

		/** @var \PPNode $arg */
		foreach ($args as $arg) {
			$bits = $arg->splitArg();
			$nameNode = $bits['name'];
			$index = $bits['index'];
			$valueNode = $bits['value'];
			if ( $index === '' ) {
				$name = trim($frame->expand( $nameNode ));
				$options[] = array( intval($name) , $valueNode );
				$r += intval($name);
			} else {
				$options[] = array( 1 , $valueNode );
				$r += 1;
			}
		}

		# Choose an option at random
		if($r <= 0) return '';
		$r = mt_rand(1,$r);
		for($i = 0; $i < count($options); $i++) {
			$r -= $options[$i][0];
			if($r <= 0) {
				$output = $options[$i][1];
				break;
			}
		}
		return trim($frame->expand($output));
	}

	public static function renderChosen( $input, $argv, Parser $parser, $frame ) {
		# Prevent caching
		if (isset($argv['uncached'])) {
			$parser->disableCache();
		}

		# Parse the options and calculate total weight
		$len = preg_match_all("/<option(?:(?:\\s[^>]*?)?\\sweight=[\"']?([^\\s>]+))?"
			. "(?:\\s[^>]*)?>([\\s\\S]*?)<\\/option>/", $input, $out);
		$r = 0;
		for($i = 0; $i < $len; $i++) {
			if(strlen($out[1][$i])==0) $out[1][$i] = 1;
			else $out[1][$i] = intval($out[1][$i]);
			$r += $out[1][$i];
		}

		# Choose an option at random
		if($r <= 0) return "";
		$r = mt_rand(1,$r);
		for($i = 0; $i < $len; $i++) {
			$r -= $out[1][$i];
			if($r <= 0) {
				$input = $out[2][$i];
				break;
			}
		}

		return $parser->recursiveTagParse($input, $frame);
	}
}
