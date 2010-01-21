<?php
/*
 * Output filter with static cache
 * 
 */
class cmsxStaticCacheFilter
{
    static function filter( $output )
    {
    	$ini = eZINI::instance( 'site.ini' );
    	if ( eZSys::isSSLNow() || $ini->variable( 'ContentSettings', 'StaticCache' ) != 'enabled' || 
    	     $ini->variable( 'DebugSettings', 'DebugOutput' ) == 'enabled'  || 
    	     eZUser::currentUser()->isLoggedIn() || !empty( $_GET ) || !empty( $_POST ) || 
    	     !method_exists( 'eZStaticCache', 'isMultiSite' ) || 
    	     $_SERVER['HTTP_USER_AGENT'] == eZStaticCache::USER_AGENT )
    	{
    		return $output;
    	}
        eZDebug::createAccumulatorGroup( 'outputfilter_total', 'Outputfilter Static Cache Total' );
        eZDebug::accumulatorStart( 'outputfilter', 'outputfilter_total', 'Output Filtering' );
		
		$userParameters = $GLOBALS['userParameters'];
		$currentURI = $GLOBALS['currentURI'] == '' ? '/' : $GLOBALS['currentURI'] ;
		$staticCache = new eZStaticCache();
		// current siteaccess
		$siteAccess = $GLOBALS['eZCurrentAccess']['name'];
		$staticCache->changeSite( $siteAccess );
		
		$staticURLArray = $staticCache->cachedURLArray();
		$cacheURI = false;
    	foreach ( $staticURLArray as $url )
        {
            if ( strpos( $url, '*' ) === false )
            {
            	if ( $currentURI == $url )
            	{
            		$cacheURI = true;
            	}
            }
            else
            {
            	if ( strncmp( str_replace( '*', '', $url ), $currentURI, strlen( $url ) -1 ) == 0 )
            	{
            		$cacheURI = true;       		
            	}
            }
        }
        if ( $cacheURI )
        {
        	$storage = $staticCache->storageDirectory() . ( $staticCache->isMultiSite() ? '/' . $siteAccess : '' );
        	$file = $staticCache->buildCacheFilename( $storage , $currentURI );
            eZStaticCache::storeCachedFile( $file, str_replace( '<!--DEBUG_REPORT-->', '', $output ) );
        }
    	eZDebug::accumulatorStop( 'outputfilter' );

    	return $output;
    }
}
?>