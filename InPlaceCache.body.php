<?php

/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 */
if( !defined( 'MEDIAWIKI' ) ) {
	echo( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
	die( -1 );
}

// Check a cache file to see if it exists
function InPlaceCache_checkCache($hash)
{
	global $gInPlaceCacheIP;
	
	$path[3] = $gInPlaceCacheIP . '/' . $hash[0];
	$path[2] = $path[3] . '/' . substr($hash,1,2);
	$path[1] = $path[2] . '/' . $hash;
	
	$path[0] = file_exists($path[1]);

	return $path;
}

// Read a cached ParserOutput object from a cache file
function InPlaceCache_readCache(/* $hash, */ $path)
{
//	$path = InPlaceCache_checkCache($hash);
	
	if($path[0] === false)
		return false;
		
	return unserialize(file_get_contents($path[1]));
}

// Write a ParserOutput object to a cache file
function InPlaceCache_writeCache(/* $hash, */ $path, &$data)
{
//	$path = InPlaceCache_checkCache($hash);
	
	if($path[0] === false)
	{
		if(!is_dir($path[3]))
			mkdir($path[3]);
		if(!is_dir($path[2]))
			mkdir($path[2]);
	}

	file_put_contents($path[1], serialize(&$data));
}

// Handle a cache miss
function InPlaceCache_cacheMiss($cachePath, $content, &$parser)
{
	global $gInPlaceCache_mergePostRender, $gInPlaceCacheEmitComments, $action;

	// create a copy of the parser to prevent side effects
	// to the main parser
	$local_parser = clone $parser;
	// create a new ParserOutput object
	// so that only the templates this section is dependent on
	// will be written to cache
	$local_parser->mOutput = new ParserOutput;
	// parse the content save the ParserOutput object
	$cacheOutput = $local_parser->parse($content, $parser->getTitle(), $parser->getOptions());
	// return the text output of the ParserOutput object
	$retv = $cacheOutput->mText;
  
	// Check if parsing caused caching to be disabled
	if($local_parser->mCacheTime == -1)
	{
		// disable caching since content is not cacheable
		$parser->disableCache();
		if($gInPlaceCacheEmitComments)
			$retv .= '<!-- InPlaceCache: parsing content caused caching to be disabled, cache not stored -->';
	}
  elseif($action == 'submit')
  {
    // Don't write cache for previews, also wierdly MW renders once for action=submit then again action=view after saving an article
    // this causes issues with <ti3>...</ti3> rendering as if it had <pre> infront of it
    $retv .= '<!-- InPlaceCache: action=submit cache write ignored -->';
    // If the cache exists:
    // remove the old cache file when action=submit since its bad
    // and cacheMiss isn't going to write to the cache
    if($cachePath[0])
      unlink($cachePath[1]);
  }
	// write the parser output cache to file since content is cacheable
	else 
    InPlaceCache_writeCache($cachePath, $cacheOutput);
	
	// perform the equivalent of a cache hit
	InPlaceCache_mergeParserOutputTo($parser->mOutput, $cacheOutput);
	
	return $retv; 
}

// used instead of array_merge since array_merge reorders numerical keys
function InPlaceCache_mergeTo(&$arrayDest, &$arraySrc)
{
	if(is_array($arraySrc))
		foreach($arraySrc as $k => $v)
		{
			if(is_array($v))
				InPlaceCache_mergeTo($arrayDest[$k],$v);
			else $arrayDest[$k] = $v;
		}
}

// Used to merge a cached ParserOutput object into the current ParserOutput object
function InPlaceCache_mergeParserOutputTo(&$dest, &$src)
{
	InPlaceCache_mergeTo($dest->mLanguageLinks, $src->mLanguageLinks);
	InPlaceCache_mergeTo($dest->mCategories, $src->mCategories);
	$dest->mContainsOldMagic = $dest->mContainsOldMagic || $src->mContainsOldMagic;
	InPlaceCache_mergeTo($dest->mLinks, $src->mLinks);
	InPlaceCache_mergeTo($dest->mTemplates, $src->mTemplates);
	InPlaceCache_mergeTo($dest->mImages, $src->mImages);
	InPlaceCache_mergeTo($dest->mExternalLinks, $src->mExternalLinks);
	InPlaceCache_mergeTo($dest->mHeadItems, $src->mHeadItems);
	InPlaceCache_mergeTo($dest->mTemplateIds, $src->mTemplateIds);
	InPlaceCache_mergeTo($dest->mOutputHooks, $src->mOutputHooks);
	$dest->mNoGallery = $dest->mNoGallery || $src->mNoGallery;
}

// Handle a cache hit
function InPlaceCache_cacheHit(&$cacheOutput, &$parser)
{
	/*
		Merge the stored ParserOutput with the current one to ensure things like template link dependencies get updated correctly
	*/
	InPlaceCache_mergeParserOutputTo($parser->mOutput, $cacheOutput);
	
	return $cacheOutput->getText();
}

// <cache> tag render
function InPlaceCache_TagCacheHook($content, $params, &$parser )
{
	global $gInPlaceCache_TagCacheHook_cacheCount, $action, $gTemplateRevId, $wgUser, $gInPlaceCacheEmitComments;
	
	$startTime = microtime(true);
	
	$id = $params['id'];
	
	if($id == null)
		$id = ++$gInPlaceCache_TagCacheHook_cacheCount;
		
  // add the current user's name to the cache content hash string
  // prevents getting a cache hit between a different users
  // say for a secure block or if the user is anon
  // they won't have edit links
  if($wgUser->isAnon())
    $inputHash = hash('md5', $content );
  else
    $inputHash = hash('md5', $content . $wgUser->getName());
    
	if($gInPlaceCacheEmitComments)
			$retv = "<!-- InPlaceCache: hash=$inputHash -->";
	
  $cachePath = InPlaceCache_checkCache($inputHash);

	if($action == 'purge' )
	{
		if($gInPlaceCacheEmitComments)
			$retv .= '<!-- InPlaceCache: cache purge -->';
		$retv .= InPlaceCache_cacheMiss($cachePath, $content, $parser);
		if($gInPlaceCacheEmitComments)
			$retv .= ('<!-- InPlaceCache: ' . (microtime(true) - $startTime) . ' seconds -->');
		return $retv;
	}
   
	$cacheOutput = InPlaceCache_readCache($cachePath);
  
  // cache file missing
	if($cacheOutput === false)
	{
		if($gInPlaceCacheEmitComments)
			$retv .= '<!-- InPlaceCache: cache missing -->';
		$retv .= InPlaceCache_cacheMiss($cachePath, $content, $parser);
		if($gInPlaceCacheEmitComments)
			$retv .= ('<!-- InPlaceCache: ' . (microtime(true) - $startTime) . ' seconds -->');
		return $retv;
	}
	
  // rendered with old version of parser
	if($cacheOutput->mVersion != $parser->mOutput->mVersion)
	{
		if($gInPlaceCacheEmitComments)
			$retv .= '<!-- InPlaceCache: cache parser version mismatch -->';
		$retv .= InPlaceCache_cacheMiss($cachePath, $content, $parser);
		if($gInPlaceCacheEmitComments)
			$retv .= ('<!-- InPlaceCache: ' . (microtime(true) - $startTime) . ' seconds -->');
		return $retv;
	}

	// check all the templates for dependencies
	foreach($cacheOutput->mTemplateIds as $array)
	{
		foreach($array as $revid)
		{
			// check if we have already loaded this revision's status already
			if(!isset($gTemplateRevId[$revid]))
			{
				$r = Revision::newFromId($revid);
				$gTemplateRevId[$revid] = is_object($r) ? $r->isCurrent() : false;
			};
			
			// if this revision is not the most current, declare a cache miss
			if($gTemplateRevId[$revid] == false)
			{
				if($gInPlaceCacheEmitComments)
					$retv .= '<!-- InPlaceCache: cache template dependency changed -->';
				$retv .= InPlaceCache_cacheMiss($cachePath, $content, $parser);
				if($gInPlaceCacheEmitComments)
					$retv .= ('<!-- InPlaceCache: ' . (microtime(true) - $startTime) . ' seconds -->');
				return $retv;
			}
		}
	}
	
	if($gInPlaceCacheEmitComments)
			$retv .= '<!-- InPlaceCache: cache hit -->'; 
	$retv .= InPlaceCache_cacheHit($cacheOutput, $parser);
	if($gInPlaceCacheEmitComments)
			$retv .= ('<!-- InPlaceCache: ' . (microtime(true) - $startTime) . ' seconds -->');
	
	return $retv;
};

/*
Used for debugging

function InPlaceCache_DumpParserOutputHook(&$parser )
{
	return '<pre>' . print_r($parser->mOutput,true) . '</pre>';
}
*/