<?php
/**
 * InPlaceCache - Render caching for sections of a page within <cache> tag for faster renders and edits.
 *
 * To activate this extension, add the following into your LocalSettings.php file:
 * require_once('$IP/extensions/InPlaceCache/InPlaceCache.php');
 *
 * @ingroup Extensions
 * @author Lance Gatlin <lance.gatlin@yahoo.com>
 * @version 0.9
 * @link http://www.ti3wiki.org/Extension:InPlaceCache
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
 
/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 */
if( !defined( 'MEDIAWIKI' ) ) {
	echo( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
	die( -1 );
}

require_once(dirname(__FILE__) . '/InPlaceCache.setup.php');
require_once(dirname(__FILE__) . '/InPlaceCache.body.php');
$wgExtensionMessagesFiles['InPlaceCache'] = dirname(__FILE__) . '/InPlaceCache.i18n.php';

?>