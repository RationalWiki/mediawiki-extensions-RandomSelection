<?php
/*
 
 RandomSelection v2.1.3 -- 7/21/08
 
 This extension randomly displays one of the given options.
 
 Usage: <choose><option>A</option><option>B</option></choose>
 Optional parameter: <option weight="3"> == 3x weight given
 
 Author: Ross McClure [http://www.mediawiki.org/wiki/User:Algorithm]
*/

//Avoid unstubbing $wgParser on setHook() too early on modern (1.12+) MW versions, as per r35980
if ( defined( 'MW_SUPPORTS_PARSERFIRSTCALLINIT' ) ) {
	$wgHooks['ParserFirstCallInit'][] = 'wfRandomSelection';
} else {
	$wgExtensionFunctions[] = 'wfRandomSelection';
}

$wgHooks['LanguageGetMagic'][] = 'efRandomSelection_Magic';

$wgExtensionCredits['parserhook'][] = array(
	'name' => 'RandomSelection',
	'url' => 'http://www.mediawiki.org/wiki/Extension:RandomSelection',
	'version' => '2.1.3',
	'author' => 'Ross McClure',
	'description' => 'Displays a random option from the given set.'
);

function efRandomSelection_Magic( &$magicWords, $langCode) {
	$magicWords['choose'] = array( 0, 'choose' );
	return true;
}

function wfRandomSelection() {
	global $wgParser;
	$wgParser->setHook( 'choose', 'renderChosen' );
	if ( defined( get_class( $wgParser ) . '::SFH_OBJECT_ARGS' ) ) {
		$wgParser->setFunctionHook( 'choose', 'efRandomSelection_RenderPF_obj', SFH_OBJECT_ARGS );
	} else {
		$wgParser->setFunctionHook( 'choose', 'efRandomSelection_RenderPF');
	}
	return true;
}

function efRandomSelection_RenderPF_obj(&$parser, $frame, $args ) {
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

function efRandomSelection_RenderPF( &$parser /*,...*/ ) {
	$args = func_get_args();
	array_shift($args);
	$parts = null;
	$options = array();
	$r = 0;
	foreach ($args as $arg) {
		//$parts = array_map( 'trim', explode( '=', $arg, 2));
		$parts = explode( '=', $arg, 2);
		if ( count($parts) == 2 ) {
			$options[] = array( intval(trim($parts[0])) , $parts[1] );
			$r += intval(trim($parts[0]));
		} elseif ( count($parts) == 1 ) {
			$options[] = array( 1 , $parts[0] );
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
	return $output;
}

function renderChosen( $input, $argv, $parser, $frame ) {
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
 
	# If running new parser, take the easy way out
	if( defined( 'Parser::VERSION' ) && version_compare( Parser::VERSION, '1.6.1', '>' ) ) {
	return $parser->recursiveTagParse($input, $frame);
	}
 
 
	# Otherwise, create new parser to handle rendering
	$localParser = new Parser();
 
	# Initialize defaults, then copy info from parent parser
	$localParser->clearState();
	$localParser->mTagHooks         = $parser->mTagHooks;
	$localParser->mTemplates        = $parser->mTemplates;
	$localParser->mTemplatePath     = $parser->mTemplatePath;
	$localParser->mFunctionHooks    = $parser->mFunctionHooks;
	$localParser->mFunctionSynonyms = $parser->mFunctionSynonyms;
 
	# Render the chosen option
	$output = $localParser->parse($input, $parser->mTitle,
					$parser->mOptions, false, false);
	return $output->getText();
	
}
