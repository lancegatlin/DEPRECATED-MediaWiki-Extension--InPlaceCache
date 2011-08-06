<?php
/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 */
if( !defined( 'MEDIAWIKI' ) ) {
	echo( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
	die( -1 );
}

//Avoid unstubbing $wgParser on setHook() too early on modern (1.12+) MW versions, as per r35980
if ( defined( 'MW_SUPPORTS_PARSERFIRSTCALLINIT' ) ) {
	$wgHooks['ParserFirstCallInit'][] = 'wfInitInPlaceCache';
} else { // Otherwise do things the old fashioned way
	$wgExtensionFunctions[] = 'wfInitInPlaceCache';
}


$gInPlaceCacheIP = $IP . '/cache/InPlaceCache'; // Global to control where InPlaceCache places the cache files
$gInPlaceCacheEmitComments = true; // Controls if emitting html comments is ok. If true, emits profiling and informative comments about cache block processing results

/* register parser hook */
$wgExtensionCredits['parserhook'][] = array(
    'name' => 'InPlaceCache',
    'author' => 'Lance Gatlin',
    'version' => '0.9',
	'url' => 'http://ti3wiki.org/index.php?title=Extensions:InPlaceCache',
	'description' => 'Render caching for sections of a page for faster renders and edits.',
);

/*
Commented out: Dump parser output for debugging

$wgHooks['LanguageGetMagic'][]       = 'InPlaceCache_Function_Magic';
function InPlaceCache_Function_Magic( &$magicWords, $langCode ) {
        # Add the magic word
        # The first array element is case sensitive, in this case it is not case sensitive
        # All remaining elements are synonyms for our parser function
        $magicWords['dump_parser_output'] = array( 0, 'dump_parser_output' );
        # unless we return true, other parser functions extensions won't get loaded.
        return true;
}
*/

function wfInitInPlaceCache() {
    global $wgParser;

	// Hook to for rending caching content
	$wgParser->setHook( 'cache', 'InPlaceCache_TagCacheHook' );

	// Used for debugging...
//	$wgParser->setFunctionHook( 'dump_parser_output', 'InPlaceCache_DumpParserOutputHook' );
}

?>