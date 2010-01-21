<?php
//
// Definition of eZStaticClass class
//
// Created on: <12-Jan-2005 10:29:21 dr>
//
// SOFTWARE NAME: eZ Publish
// SOFTWARE RELEASE: 4.1.3
// BUILD VERSION: 23650
// COPYRIGHT NOTICE: Copyright (C) 1999-2009 eZ Systems AS
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//

/*! \file
*/

/*!
  \class eZStaticCache ezstaticcache.php
  \brief Manages the static cache system.

  This class can be used to generate static cache files usable
  by the static cache system.

  Generating static cache is done by instatiating the class and then
  calling generateCache(). For example:
  \code
  $staticCache = new eZStaticCache();
  $staticCache->generateCache();
  \endcode

  To generate the URLs that must always be updated call generateAlwaysUpdatedCache()

*/

class eZStaticCache
{
    const USER_AGENT = 'eZ Publish static cache generator';
    
        /// \privatesection
    /// The name of the host to fetch HTML data from.
    public $HostName = '';
    /// The base path for the directory where static files are placed.
    public $StaticStorageDir = '';
    /// The maximum depth of URLs that will be cached.
    public $MaxCacheDepth = 0;
    /// Array of URLs to cache.
    public $CachedURLArray = array();
    /// An array with URLs that is to always be updated.
    public $AlwaysUpdate = array();
    /// Content must be compact
    static public $CompactHTML = false;
    
    protected $siteAccesses = array();
    
    protected $ini;
    
    protected $currentSite;

    protected $defaultSite = '';

    protected $defaultSettings = array();
    
    protected $multiSite = false;
    /*!
     Initialises the static cache object with settings from staticcache.ini.
    */
    public function __construct()
    {
    	$this->ini = eZINI::instance( 'staticcache.ini' );
    	$this->StaticStorageDir = $this->ini->variable( 'CacheSettings', 'StaticStorageDir' );
        $this->CachedSiteAccesses = array_unique( $this->ini->variable( 'CacheSettings', 'CachedSiteAccesses' ) );
		$this->defaultSite = $GLOBALS['eZCurrentAccess']['name'];
        $this->defaultSettings['HostName'] = $this->ini->variable( 'CacheSettings', 'HostName' );
        $this->defaultSettings['MaxCacheDepth'] = $this->ini->variable( 'CacheSettings', 'MaxCacheDepth' );
        $this->defaultSettings['CachedURLArray'] = array_unique( $this->ini->variable( 'CacheSettings', 'CachedURLArray' ) );
        $this->defaultSettings['AlwaysUpdate'] = array_unique( $this->ini->variable( 'CacheSettings', 'AlwaysUpdateArray' ) );
        $this->defaultSettings['CompactHTML'] = ( $this->ini->variable( 'CacheSettings', 'CompactHTML' ) == 'enabled' );
		$this->siteAccesses[$this->defaultSite] = $this->getConfig( $this->defaultSite );
    	$this->changeSite( $this->defaultSite );
        if ( count( $this->CachedSiteAccesses ) >= 1 )
        {
        	$this->multiSite = true;
        	foreach ( $this->CachedSiteAccesses as $site  )
        	{
	        	if ( $site != $this->defaultSite )
	        	{
	        		$this->siteAccesses[$site] = $this->getConfig( $site );
	        	}
        	}
        }
		else
		{
			$this->CachedSiteAccesses = array( $this->defaultSite );
		}
    }
    public function isMultiSite()
    {
    	return $this->multiSite;
    }
    public function changeSite( $name )
    {
        $this->currentSite = $name;
		$this->HostName = $this->siteAccesses[$name]['HostName'];
        $this->MaxCacheDepth = $this->siteAccesses[$name]['MaxCacheDepth'];
        $this->CachedURLArray = $this->siteAccesses[$name]['CachedURLArray'];
        $this->PathPrefix = $this->siteAccesses[$name]['PathPrefix'];
        $this->AlwaysUpdate = $this->siteAccesses[$name]['AlwaysUpdate'];
        self::$CompactHTML = $this->siteAccesses[$name]['CompactHTML'];  
    }   
    protected function getConfig( $site = '' )
    {
		$siteIni = eZINI::instance( 'site.ini' );
		$ini = $this->ini;
		$config = array();
        $config['HostName'] = $this->defaultSettings['HostName'];
        $config['MaxCacheDepth'] = $this->defaultSettings['MaxCacheDepth'];
        $config['CachedURLArray'] = $this->defaultSettings['CachedURLArray'];
        $config['AlwaysUpdate'] = $this->defaultSettings['AlwaysUpdate'];
        $config['PathPrefix'] = trim( $siteIni->variable( 'SiteAccessSettings', 'PathPrefix' ), '/' );
        $config['CompactHTML'] = $this->defaultSettings['CompactHTML'];
		if ( $this->defaultSite != $site )
		{
			$siteIni = eZINI::getSiteAccessIni( $site, 'site.ini' );
			$ini = eZINI::getSiteAccessIni( $site, 'staticcache.ini' );
			$config['PathPrefix'] = trim( $siteIni->variable( 'SiteAccessSettings', 'PathPrefix' ), '/' );
			if ( $ini->hasVariable( 'CacheSettings', 'HostName' ) )
	        {
	        	$config['HostName'] = $ini->variable( 'CacheSettings', 'HostName' );
	        }     
	    	if ( $ini->hasVariable( 'CacheSettings', 'MaxCacheDepth' ) )
	        {
	        	$config['MaxCacheDepth'] = $ini->variable( 'CacheSettings', 'MaxCacheDepth' );
	        }
	        if ( $ini->hasVariable( 'CacheSettings', 'CachedURLArray' ) )
	        {
	        	$config['CachedURLArray'] = array_unique( $ini->variable( 'CacheSettings', 'CachedURLArray' ) );
	        }
	        if ( $ini->hasVariable( 'CacheSettings', 'AlwaysUpdateArray' ) )
	        {
	        	$config['AlwaysUpdate'] = array_unique( $ini->variable( 'CacheSettings', 'AlwaysUpdateArray' ) );        	
	        }
	        if ( $ini->hasVariable( 'CacheSettings', 'CompactHTML' ) )
	        {
	        	$config['CompactHTML'] = ( $ini->variable( 'CacheSettings', 'CompactHTML' ) == 'enabled' );
	        }
		}
        return $config;
    }
    /*!
     \return The currently configured host-name.
    */
    function hostName()
    {
        return $this->HostName;
    }

    /*!
     \return The currently configured storage directory for the static cache.
    */
    function storageDirectory()
    {
        return $this->StaticStorageDir;
    }

    /*!
     \return The maximum depth in the url which will be cached.
    */
    function maxCacheDepth()
    {
        return $this->MaxCacheDepth;
    }

    /*!
     \return An array with site-access names that should be cached.
    */
    function cachedSiteAccesses()
    {
        return $this->CachedSiteAccesses;
    }
    /*!
     \return An array with URLs that is to be cached statically, the URLs may contain wildcards.
    */
    function cachedURLArray()
    {
        return $this->CachedURLArray;
    }

    /*!
     \return An array with URLs that is to always be updated.
     \note These URLs are configured with \c AlwaysUpdateArray in \c staticcache.ini.
     \sa generateAlwaysUpdatedCache()
    */
    function alwaysUpdateURLArray()
    {
        return $this->AlwaysUpdate;
    }

    /*!
     Generates the caches for all URLs that must always be generated.

     \sa alwaysUpdateURLArray().
    */
    function generateAlwaysUpdatedCache( $quiet = false, $cli = false, $delay = true )
    {
        foreach ( $this->CachedSiteAccesses as $site )
        {
        	$this->changeSite( $site );    	
	        $hostname = $this->HostName;
	        $staticStorageDir = $this->StaticStorageDir;
			if ( !$quiet and $cli )
                $cli->output( "Always update for domain: http://$hostname" );
	        foreach ( $this->AlwaysUpdate as $uri )
	        {
	            if ( !$quiet and $cli )
	                $cli->output( "$uri ", false );
	            $this->storeCache( $uri, $hostname, $staticStorageDir, array(), false, $delay );
	            if ( !$quiet and $cli )
	                $cli->output( "done" );
	        }
	    }
        $this->changeSite( $this->defaultSite );
    }

    /*!
     Generates caches for all the urls of nodes in $nodeList.
     $nodeList is an array with node entries, each entry is either the node ID or an associative array.
     The associative array must have on of these entries:
     - node_id - ID of the node
     - path_identification_string - The path_identification_string from the node table, is used to fetch the node ID  if node_id is missing.
     */
    function generateNodeListCache( $nodeList )
    {
        $db = eZDB::instance();
		foreach ( $this->CachedSiteAccesses as $site )
        {
			$this->changeSite( $site );
	        foreach ( $nodeList as $uri )
	        {
	            if ( is_array( $uri ) )
	            {
	                if ( !isset( $uri['node_id'] ) )
	                {
	                    eZDebug::writeError( "node_id is not set for uri entry " . var_export( $uri ) . ", will need to perform extra query to get node_id" );
	                    $node = eZContentObjectTreeNode::fetchByURLPath( $uri['path_identification_string'] );
	                    $nodeID = (int)$node->attribute( 'node_id' );
	                }
	                else
	                {
	                    $nodeID = (int)$uri['node_id'];
	                }
	            }
	            else
	            {
	                $nodeID = (int)$uri;
	            }
	            $elements = eZURLAliasML::fetchByAction( 'eznode', $nodeID, true, true, true );
	            foreach ( $elements as $element )
	            {
	                $path = $element->getPath();
	                $this->cacheURL( '/' . $path );
	            }
			}	
        }
    }
    protected function cleanPathPrefix( $url )
    {
		$regexPrefix = '#^\/?' . $this->PathPrefix .  '#';
        return ltrim( preg_replace( $regexPrefix , '', $url ), '/' );	
    }
    /*!
     Generates the static cache from the configured INI settings.

     \param $force If \c true then it will create all static caches even if it is not outdated.
     \param $quiet If \c true then the function will not output anything.
     \param $cli The eZCLI object or \c false if no output can be done.
    */
    function generateCache( $force = false, $quiet = false, $cli = false, $delay = true )
    {
        $db = eZDB::instance();
        foreach ( $this->CachedSiteAccesses as $site )
        {
        	$this->changeSite( $site );
	        $staticURLArray = $this->cachedURLArray();
	        $configSettingCount = count( $staticURLArray );
	        $currentSetting = 0;
	        $hostname = $this->HostName;
	
			if ( !$quiet and $cli )
                $cli->output( "Generate cache for domain: http://$hostname" );
	
	        // This contains parent elements which must checked to find new urls and put them in $generateList
	        // Each entry contains:
	        // - url - Url of parent
	        // - glob - A glob string to filter direct children based on name
	        // - org_url - The original url which was requested
	        // - parent_id - The element ID of the parent (optional)
	        // The parent_id will be used to quickly fetch the children, if not it will use the url
	        $parentList = array();
	        // A list of urls which must generated, each entry is a string with the url
	        $generateList = array();
	        foreach ( $staticURLArray as $url )
	        {
	            $currentSetting++;
	            if ( strpos( $url, '*') === false )
	            {
	                $generateList[] = $url;
	            }
	            else
	            {
	                $queryURL = ltrim( str_replace( '*', '', $url ), '/' );
	                $dir = dirname( $queryURL );
	                if ( $dir == '.' )
	                    $dir = '';
	                $glob = basename( $queryURL );
	                $parentList[] = array( 'url' => $dir,
	                                       'glob' => $glob,
	                                       'org_url' => $url );
	            }
	        }
	
	        // As long as we have urls to generate or parents to check we loop
	        while ( count( $generateList ) > 0 || count( $parentList ) > 0 )
	        {
	            // First generate single urls
	            foreach ( $generateList as $generateURL )
	            {
	                if ( !$quiet and $cli )
	                    $cli->output( "caching: $generateURL ", false );
	                $this->cacheURL( $generateURL, false, !$force, $delay );
	                if ( !$quiet and $cli )
	                    $cli->output( "done" );
	            }
	            $generateList = array();
	
	            // Then check for more data
	            $newParentList = array();
	            foreach ( $parentList as $parentURL )
	            {
	                if ( isset( $parentURL['parent_id'] ) )
	                {
	                    $elements = eZURLAliasML::fetchByParentID( $parentURL['parent_id'], true, true, false );
	                    foreach ( $elements as $element )
	                    {
	                        $path = $element->getPath();
					        $path = '/' . $this->cleanPathPrefix( $path );
	                        $generateList[] = $path;
	                        $newParentList[] = array( 'parent_id' => $element->attribute( 'id' ) );
	                    }
	                }
	                else
	                {
	                    if ( !$quiet and $cli and $parentURL['glob'] )
	                        $cli->output( "wildcard cache: " . $parentURL['url'] . '/' . $parentURL['glob'] . "*" );
	                    $elements = eZURLAliasML::fetchByPath( $parentURL['url'], $parentURL['glob'], true, true );
	                    if ( count( $elements ) == 0 )
	                    {
		                    if ( !$quiet and $cli and $parentURL['glob'] )
		                        $cli->output( "wildcard not found, trying with PathPrefix: " . '/' . $this->PathPrefix . $parentURL['url'] . '/' . $parentURL['glob'] . "*" );		
                            $elements = eZURLAliasML::fetchByPath( '/' . $this->PathPrefix . $parentURL['url'], $parentURL['glob'], true, true );	
                    	}
	                    foreach ( $elements as $element )
	                    {
		                     $path = '/' . $element->getPath();
	                         $generateList[] = $path;
		                     $newParentList[] = array( 'parent_id' => $element->attribute( 'id' ) );
	                    }
	                }
	            }
	            $parentList = $newParentList;
	        }        	
        }
        $this->changeSite( $this->defaultSite );
    }

    /*!
     \private
     Generates the caches for the url \a $url using the currently configured hostName() and storageDirectory().

     \param $url The URL to cache, e.g \c /news
     \param $nodeID The ID of the node to cache, if supplied it will also cache content/view/full/xxx.
     \param $skipExisting If \c true it will not unlink existing cache files.
    */
    function cacheURL( $url, $nodeID = false, $skipExisting = false, $delay = true )
    {
        // Set default hostname
        $hostname = $this->HostName;
        $staticStorageDir = $this->StaticStorageDir;
        // Check if URL should be cached
        if ( substr_count( $url, "/" ) >= $this->MaxCacheDepth )
            return false;
        $urlClean =  '/' . $this->cleanPathPrefix( $url );
        $doCacheURL = false;
        foreach ( $this->CachedURLArray as $cacheURL )
        {
            if ( $url == $cacheURL )
            {
                $doCacheURL = true;
                break;
            }
            else if ( strpos( $cacheURL, '*' ) !== false )
            {
                if ( strpos( $url, str_replace( '*', '', $cacheURL ) ) === 0 || strpos( $urlClean, str_replace( '*', '', $cacheURL ) ) === 0 )
                {
                    $doCacheURL = true;
                    break;
                }
            }
        }
        if ( $doCacheURL == false )
        {    
            return false;
        }
        $this->storeCache( $url, $hostname, $staticStorageDir, array(), $skipExisting, $delay );

        return true;
    }

    /*!
     \private
     Stores the static cache for \a $url and \a $hostname by fetching the web page using
     fopen() and storing the fetched HTML data.

     \param $url The URL to cache, e.g \c /news
     \param $hostname The name of the host which serves web pages dynamically, see hostName().
     \param $staticStorageDir The base directory for storing cache files, see storageDirectory().
     \param $alternativeStaticLocations An array with additional URLs that should also be cached.
     \param $skipUnlink If \c true it will not unlink existing cache files.
    */
    function storeCache( $url, $hostname, $staticStorageDir, $alternativeStaticLocations = array(), $skipUnlink = false, $delay = true )
    {        
        $http = eZHTTPTool::instance();
        $cacheFiles = array();
        $washedURL = '/' . $this->cleanPathPrefix( $url );
        $dir = ( $this->isMultiSite() ) ? '/' . $this->currentSite : '';
        $cacheFiles[] = $this->buildCacheFilename( $staticStorageDir . $dir , $washedURL );
        foreach ( $alternativeStaticLocations as $location )
        {
            $washedLocation = '/' . $this->cleanPathPrefix( $location );
            $cacheFiles[] = $this->buildCacheFilename( $staticStorageDir . $dir , $washedLocation );
        }
        /* Store new content */
        $content = false;
        foreach ( $cacheFiles as $file )
        {
            if ( !$skipUnlink || !file_exists( $file ) )
            {
                $hostname = $this->HostName;
                $fileName = "http://$hostname$url";
                if ( $delay )
                {
                    $this->addAction( 'store', array( $file, $fileName ) );
                }
                else
                {
                    /* Generate content, if required */
                    if ( $content === false )
                    {
                        $content = $http->getDataByURL( $fileName, false, eZStaticCache::USER_AGENT );
                    }
                    if ( $content === false )
                    {
                        eZDebug::writeNotice( "Could not grab content (from $fileName), is the hostname correct and Apache running?",
                                              'Static Cache' );
                    }
                    else
                    {
                        self::storeCachedFile( $file, $content );
                    }
                }
            }
        }
    }
    static public function cleanHtml( $c )
    {
        $docType= '';
        if (count(preg_match('/<!DOCTYPE[^>]*>/', $c, $docType)))
        {
            $c = preg_replace('/<!DOCTYPE[^>]*>/', '', $c);
            $docType = $docType[0];
        }
        $lines = explode("\n", $c);
        $script_clean = 0;
        $pre_clean = 0;
        $c = '';
        foreach ($lines as $line)
        {
            $script_beg = stristr($line, '<script');
            $script_end = stristr($line, '</script');
            if ($script_beg && !$script_end) $script_clean = 1;
            if ($script_end && $script_clean == 1) $script_clean = 0;
            // clean pre tags
            $pre_beg = stristr($line, '<pre');
            $pre_end = stristr($line, '</pre');
            if ($pre_beg && !$pre_end) $pre_clean = 1;
            if ($pre_end && $pre_clean == 1) $pre_clean = 0;
            $content = (($pre_clean || ($pre_beg && $pre_end))) ? $line . "\n" : str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    ','^ '), ' ', $line);
            if ($script_clean)
            {
                if (preg_match('/[^ \t\n\r\f\v]/', $line))
                $content .= "\n";
            }
            $c .= $content . ' ';
        }
        return $docType . $c;  
    }
    /*!
     \private
     \param $staticStorageDir The storage for cache files.
     \param $url The URL for the current item, e.g \c /news
     \return The full path to the cache file (index.html) based on the input parameters.
    */
    function buildCacheFilename( $staticStorageDir, $url )
    {
        $file = "{$staticStorageDir}{$url}/index.html";
        $file = preg_replace( '#//+#', '/', $file );
        return $file;
    }

    /*!
     \private
     \static
     Stores the cache file \a $file with contents \a $content.
     Takes care of setting proper permissions on the new file.
    */
    static function storeCachedFile( $file, $content )
    {
        if ( self::$CompactHTML )
    	{
    		$content = self::cleanHtml( $content );
    	}
        $dir = dirname( $file );
        if ( !is_dir( $dir ) )
        {
            eZDir::mkdir( $dir, false, true );
        }

        $oldumask = umask( 0 );

        $tmpFileName = $file . '.' . md5( $file. uniqid( "ezp". getmypid(), true ) );

        /* Remove files, this might be necessary for Windows */
        @unlink( $tmpFileName );

        /* Write the new cache file with the data attached */
        $fp = fopen( $tmpFileName, 'w' );
        if ( $fp )
        {
            fwrite( $fp, $content . '<!-- Generated: '. date( 'Y-m-d H:i:s' ). " -->\n\n" );
            fclose( $fp );
            eZFile::rename( $tmpFileName, $file );

            $perm = eZINI::instance()->variable( 'FileSettings', 'StorageFilePermissions' );
            chmod( $file, octdec( $perm ) );
        }

        umask( $oldumask );
    }

    /*!
     Removes the static cache file (index.html) and its directory if it exists.
     The directory path is based upon the URL \a $url and the configured static storage dir.
     \param $url The URL for the curren item, e.g \c /news
    */
    function removeURL( $url )
    {
        $dir = eZDir::path( array( $this->StaticStorageDir, $url ) );

        @unlink( $dir . "/index.html" );
        @rmdir( $dir );
    }

    /*!
     \private
     This function adds an action to the list that is used at the end of the
     request to remove and regenerate static cache files.
    */
    function addAction( $action, $parameters )
    {
        if (! isset( $GLOBALS['eZStaticCache-ActionList'] ) ) {
            $GLOBALS['eZStaticCache-ActionList'] = array();
        }
        $GLOBALS['eZStaticCache-ActionList'][] = array( $action, $parameters );
    }

    /*!
     \static
     This function goes over the list of recorded actions and excecutes them.
    */
    static function executeActions()
    {
        if (! isset( $GLOBALS['eZStaticCache-ActionList'] ) ) {
            return;
        }

        $fileContentCache = array();
        $doneDestList = array();

        $ini = eZINI::instance( 'staticcache.ini');
        $clearByCronjob = ( $ini->variable( 'CacheSettings', 'CronjobCacheClear' ) == 'enabled' );

        if ( $clearByCronjob )
        {
            $db = eZDB::instance();
        }

        $http = eZHTTPTool::instance();

        foreach ( $GLOBALS['eZStaticCache-ActionList'] as $action )
        {
            list( $action, $parameters ) = $action;

            switch( $action ) {
                case 'store':
                    list( $destination, $source ) = $parameters;

                    if ( isset( $doneDestList[$destination] ) )
                        continue 2;

                    if ( $clearByCronjob )
                    {
                        $param = $db->escapeString( $destination . ',' . $source );
                        $db->query( 'INSERT INTO ezpending_actions( action, param ) VALUES ( \'static_store\', \''. $param . '\' )' );
                        $doneDestList[$destination] = 1;
                    }
                    else
                    {
                        if ( !isset( $fileContentCache[$source] ) )
                        {
                            $fileContentCache[$source] = $http->getDataByURL( $source, false, eZStaticCache::USER_AGENT );
                        }
                        if ( $fileContentCache[$source] === false )
                        {
                            eZDebug::writeNotice( 'Could not grab content, is the hostname correct and Apache running?', 'Static Cache' );
                        }
                        else
                        {
                            self::storeCachedFile( $destination, $fileContentCache[$source] );
                            $doneDestList[$destination] = 1;
                        }
                    }
                    break;
            }
        }
        $GLOBALS['eZStaticCache-ActionList'] = array();
    }
}

?>