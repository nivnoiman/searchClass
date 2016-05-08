<?php
/**
 * Class :: Custom Search
 * Just Declare This Class and use the functions.
 */
class customSearch{
    /**
     * Initialize Custom Search
     */
    public $searchData;
    public $argsQuery                   = array(); // Array of all the Query Arguments in the spesific search.
    public $maxResult                   = 5; // Number of the max result per query.
    public $allSearchResult             = array(); // if we use pagination we need the resualt without posts_per_page at the begining ( to know how many pages we need ).
    public $pagination                  = false; // if true use main variable $allSearchResult.
    public $minCharForSearch            = 3; // Number of the max chars per search.
    public $logArray                    = array();
    public $excludeParameters           = array(); // Exclude this $_GET parameters from the search resualt.
    public $excludeAddingParameters     = array( 'search' ); // Exclude this $_GET parameters from addingParametersTo search resualt.
    public $excludeAddingParametersFrom = array(); // Spesific where to exclude the excludeAddingParameters.
    public $excludeRole                 = array(); // Exclude this member roles from members search resualt.
    public $searchArgs                  = array(); // Arguments for "craeteSearchArgs.
    public $include_empty               = false; // Flag if to show empty search string
    public $include_types               = array(); // If $include_empty true - select spesific, what to result allow the empty.


    /**
     * [__construct description]
     * The First Trigger
     * @method __construct
     * @param  array $args [ the inline args from the Declaration ]
     */
    public function __construct( $args = array() ){

        $this->inlineSettings( $args );


        if( empty( $this->searchData ) ){
            $searchData               = array();
            $searchData['parameters'] = array();
            $searchData['result']     = array();
        }

        // Get all URL Parameters and push to - parameters.
        if( !empty( $_GET ) ){
            foreach( $_GET as $getKey => $getValue ){
                if( !in_array( $getKey , $this->excludeParameters ) )
                    $searchData['parameters'][ sanitize_text_field( $getKey ) ] = sanitize_text_field( $getValue );
            }
            $this->insertToLog( 'success' , 'GET_FOUND');
        } else
            $this->insertToLog( 'warning' , 'GET_EXIST');

        // Update Class: searchData array with the new data.
        $this->searchData = $searchData;
        // Create search args for all querys.
        $this->createSearchArgs( $this->searchArgs );
    }

    /**
     * [inlineSettings description]
     * @method inlineSettings
     * Allow to change the settings that defined at the start of this class with inline arguments.
     * @param  [type] $inlineArgs [the inline argument - from the class declaration ]
     * @return [type][Update the main variables]
     */
    public function inlineSettings( $inlineArgs ){
        if( !empty( $inlineArgs ) && is_array( $inlineArgs ) ){
            $this->searchArgs = array_merge( $this->searchArgs , $inlineArgs );
            foreach( $inlineArgs as $inlineKey => $inlineValue ){
                if( isset( $this->$inlineKey ) ){
                    if( is_array( $this->$inlineKey ) )
                        $this->$inlineKey = array_merge( $this->$inlineKey , $this->searchArgs[$inlineKey] );
                    else
                        $this->$inlineKey = $this->searchArgs[$inlineKey];
                }
            }
        }
    }

    /**
     * [getParameters description]
     * @method getParameters
     * @param  string $return [Which parameter to return | default : ALL ]
     * @return [type][Return the parameters.]
     */
    public function getParameters( $return = 'all' ){
        $returnData = $this->searchData['parameters'];
        if( $return != 'all'){
            if( !empty( $returnData[$return] ) )
                $returnData = $returnData[$return];
            else
                $returnData ='';
        }
        return $returnData;
    }

    /**
     * [getResult description]
     * @method getResult
     * @param  string $return [Which resualt to return | deafult : ALL ]
     * @return [type] [Return the search resault]
     */
    public function getResult( $return = 'all' ){

        // Execute the search with the args.
        $this->initResult();

        $searchResult = $this->searchData['result'];
        $returnData   = array();

        switch ( $return ) {
            case 'all':
                    $returnData = $searchResult;
                break;

            default:
                if( !empty( $searchResult[ $return ] ) )
                    $returnData = $searchResult[ $return ];
                break;
        }
        return $returnData;
    }

    /**
     * [allowToSearch description]
     * @method allowToSearch
     * @param  [type]        $case   [the parameter of the args query we want to check]
     * @param  [type]        $string [the string we want to check]
     * @return [type]                [TUEE/FALSE - we can create the query arguments for this parameter.]
     */
    public function allowToSearch( $case = NULL , $string ){

        $return = false; // Default Return.
        switch ( $case ) {
            case 'members':
                if( !empty( $string ) && ( ( !is_numeric( $string ) && strlen( $string ) >= $this->minCharForSearch ) || is_numeric( $string ) ) )
                    $return = true;
                break;

            case 'posts':
                if( !empty( $string ) && !is_numeric( $string ) && strlen( $string ) >= $this->minCharForSearch )
                    $return = true;
                break;

            case 'pages':
                if( !empty( $string ) && !is_numeric( $string ) && strlen( $string ) >= $this->minCharForSearch )
                    $return = true;
                break;

            default:
                $return = $return;
                break;
        }
        if( $this->checkIncludeType( $case ) )
            $return = true;

        return $return;
    }

    /**
     * [getUsersByRoles description]
     * @method getUsersByRoles
     * @param  [type]             $roles [array or one role name]
     * @return [type] [IDs of users with the specific role]
     */
    public function getUsersByRoles( $roles = NULL ) {
        $getUsers = array();

        if( !empty( $roles ) ){
            if( is_array( $roles ) ){
                $rolesIndex = 0;
                foreach( $roles as $role ){
                    if( $rolesIndex == 0)
                        $getUsers = implode(',',get_users('role='.$role.'&fields=ID'));
                    else
                        $getUsers = $getUsers.','.implode(',',get_users('role='.$role.'&fields=ID'));

                    $rolesIndex++;
                }
            } else{
                $getUsers = implode(',',get_users('role='.$roles.'&fields=ID'));
            }
        }
        return $getUsers;
    }

    /**
     * [checkIncludeType description]
     * @method checkIncludeType
     * If main variable - include_empty - is true check if there any include_types to include / exclude.
     * @param  [type] $type [type of search query we want to include]
     * @return [type][return bool ( true / false ) ]
     */
    public function checkIncludeType( $type ){
        if( !empty( $this->searchArgs['include_empty'] ) ){
            if( !empty( $this->searchArgs['include_types'] ) && is_array( $this->searchArgs['include_types'] ) ){
                if( in_array( $type, $this->searchArgs['include_types'] ) )
                    return true;
                else
                    return false;
            } else
                return true;
        }
        return false;
    }

    /**
     * [createSearchArgs description]
     * Craetion of the query arguments for this search.
     * @method createSearchArgs
     * @return [type][Save the query args in the main variable "argsQuery"]
     */
    public function createSearchArgs( $args = array() ){
        $searchParameters = $this->getParameters();
        $argsQuery        = array();
        // Check search parameters and add to $argsQuery
        if( !empty( $searchParameters ) ){
            $searchParameter = !empty( $searchParameters['search'] ) ? $searchParameters['search'] : '';
            if( $searchParameter || ( !empty( $args['include_empty'] ) && $args['include_empty'] == true ) ){
                // Members Args
                if( $this->allowToSearch( 'members' , $searchParameter ) ){
                    $argsQuery['members'] = array(
                        'number' => $this->maxResult,
                        'search' => '*'.$searchParameter.'*',
                        'exclude' => $this->getUsersByRoles( $this->excludeRole )
                    );
                }
                // Posts Args
                if( $this->allowToSearch( 'posts' , $searchParameter ) ){
                    $argsQuery['posts'] = array(
                        'posts_per_page' => $this->maxResult,
                        's'              => $searchParameter
                    );
                }
                // Pages Args
                if( $this->allowToSearch( 'pages' , $searchParameter ) ){
                    $argsQuery['pages'] = array(
                        'posts_per_page' => $this->maxResult,
                        'post_type'      => 'page',
                        's'              => $searchParameter
                    );
                }
            }
            $argsQuery = $this->addingParametersTo( $argsQuery );
            return $this->argsQuery = $argsQuery;
        }
    }

    /**
     * [addingParametersTo description]
     * @method addingParametersTo
     * Adding extra $_GET parameter to the query args.
     * @param  array $args [Current query args]
     * @return [type][Array - merge of the args]
     */
    public function addingParametersTo( $args = array() ){
        $searchParameters = $this->getParameters();

        if( !empty( $args ) && is_array( $args ) ){
            $taxQueries = array();
            foreach ($args as $argKey => $argValue) {
                if( !empty( $argValue ) && is_array( $argValue ) ){
                    foreach( $searchParameters as $searchKey => $searchValue ){
                        if(  !in_array( $searchKey, $this->excludeAddingParameters ) || ( !empty( $this->excludeAddingParametersFrom ) && in_array( $searchKey, $this->excludeAddingParameters ) && !in_array( $argKey, $this->excludeAddingParametersFrom ) ) ){
                            $searchValue = ( strpos( $searchValue, ',' ) !== FALSE ) ? explode( ',', $searchValue ) : array( $searchValue );
                            foreach( $searchValue as $searcVal ){
                                $taxQueries[] = array(
                                                'taxonomy' => $searchKey,
                                                'field' => 'term_id',
                                                'terms' => $searcVal
                                            );
                            }
                            $newArgsQuery[$argKey] = array(
                                    'relation'  => 'AND',
                                    'tax_query' => $taxQueries
                                );
                            $args[$argKey] = array_merge( $args[$argKey], $newArgsQuery[$argKey] );
                        }
                    }
                }
            }
        }
        return $args;
    }

    /**
     * [setPage description]
     * set the page for the pagination
     * @method setPage
     */
    public function setPage(){
        if( !empty( $this->searchArgs['paged'] ) ){
            if( !empty( $this->argsQuery ) && is_array( $this->argsQuery ) ){
                foreach( $this->argsQuery as $argQueryKey => $argQueryValueArray ){
                    $this->setQuery( $argQueryKey , array( 'paged' => $this->searchArgs['paged'] ) );
                }
            }
            return $this->searchArgs['paged'];
        }
        return 1;
    }

    /**
     * [showPagination description]
     * @method showPagination
     * @param  [type] $resultKey [the name of the result key from the view page ( the loop - like posts , members , companies ) ]
     * @return [type] [description]
     */
    public function showPagination( $resultKey = NULL ){
        $searchResult    = $this->searchData['result'];
        $searchAllResult = $this->allSearchResult;
        if( !empty( $resultKey ) && !empty( $searchResult[$resultKey] ) && !empty( $searchAllResult[$resultKey] ) ){
            if( !empty( $searchResult[$resultKey]['posts_per_page'] ) )
                $maxResult = $this->argsQuery[$resultKey]['posts_per_page'];
            elseif( !empty( $searchResult[$resultKey]['number'] ) )
                $maxResult = $this->argsQuery[$resultKey]['number'];
            else
                $maxResult = $this->maxResult;

            $pageTotal  = ceil( sizeof( $searchAllResult[$resultKey] ) / $maxResult );
            $big        = 999999999;
            $pagination = paginate_links( array(
                                	'base'         => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
                                	'format'       => '?paged=%#%',
                                	'current'      => max( 1, $this->searchArgs['paged'] ),
                                	'prev_text'    => __('&#60;&#60;'),
                                	'next_text'    => __('&#62;&#62;'),
                                	'total'        => $pageTotal) );
            if( !empty( $pagination ) )
			         echo '<div class="pagination">' . $pagination . '</div>';
        }
    }

    /**
     * [allSearchArgs description]
     * @method allSearchArgs
     * Remove posts_pser_page from args.
     * @param  [type] $args [Current Args]
     * @return [type][the new array of arguments]
     */
    public function allSearchArgs( $args ){
        $unSetArray = array( 'posts_per_page' , 'number' ,'paged');
        foreach( $unSetArray as $unSetKey ){
            if( isset( $args[$unSetKey] ) )
                unset( $args[$unSetKey] );
        }
        return $args;
    }

    /**
     * [initResult description]
     * Execute the search with all the parameters and the args.
     * @method initResult
     * @return [type][Update the main variable "searchData['resualt']"]
     */
    public function initResult(){
        $this->setPage();
        $argsQuery    = $this->argsQuery;
        $searchResult = array();

        // Create array of all the results
        if( !empty( $argsQuery['members'] ) ){
            $searchResult['members'] = get_users( $argsQuery['members'] );
            if( $this->pagination ){
                $tmpArgs = $this->allSearchArgs( $argsQuery['members'] );
                $this->allSearchResult['members'] = get_users( $tmpArgs );
            }
        } else{
            $searchResult['members'] = '';
            $this->insertToLog( 'error' , 'CANT_GET_USERS');
        }

        if( !empty( $argsQuery['posts'] ) ){
            $searchResult['posts'] = get_posts( $argsQuery['posts'] );
            if( $this->pagination ){
                $tmpArgs = $this->allSearchArgs( $argsQuery['posts'] );
                $this->allSearchResult['posts'] = get_posts( $tmpArgs );
            }
        } else{
            $searchResult['posts'] = '';
            $this->insertToLog( 'error' , 'CANT_GET_POSTS');
        }

        if( !empty( $argsQuery['pages'] ) ){
            $searchResult['pages'] = get_posts( $argsQuery['pages'] );
            if( $this->pagination ){
                $tmpArgs = $this->allSearchArgs( $argsQuery['pages'] );
                $this->allSearchResult['pages'] = get_posts( $tmpArgs );
            }
        } else{
            $searchResult['pages'] = '';
            $this->insertToLog( 'error' , 'CANT_GET_PAGES');
        }

        $this->searchData['result'] = $searchResult;
        return $searchResult;
    }

        /**
         * [lastSearch description]
         * @method lastSearch
         * @param  string     $return [What to return in the lastSearch function.]
         * @return [type]             [The string of the last search parameter.]
         */
        public function lastSearch( $return = 'string' ){
            $returnData = '';

            switch ($return) {

                case 'string':
                default:
                        $returnData = $this->getParameters('search');
                        if( !empty( $returnData['search'] ) )
                            $returnData = $returnData['search'];
                    break;
            }
            return $returnData;
        }

        /**
         * [setQuery description]
         * @method setQuery
         * Allow to change the query arguments after class declaration.
         * @param  [type]   $querySet  [where to chagne the new query]
         * @param  array    $newValues [array of the new parameters]
         * @param  array    $return [ array | bool ]
         * @return [type] [If $return is array - return the new array after the changes , ELSE - return true or false.]
         */
        public function setQuery( $querySet = NULL , $newValues = array() , $return = 'bool'  ){
            $argsQuery = $this->argsQuery;
            $return    = false;

            if( !empty( $querySet ) && !empty( $newValues ) && is_array( $newValues ) ){
                foreach( $newValues as $newKey => $newVal )
                    $argsQuery[$querySet][$newKey] = $newVal;

                $this->argsQuery = $argsQuery;
                $return          = true;
            }
            if( $return == 'array' || $return == 'args' )
                $return = $argsQuery;

            return $return;
        }

        /**
         * [insertToLog description]
         * @method insertToLog
         * @param  [type]      $logType [type of log ( error , success , warning ) ]
         * @param  [type]      $logCase [case is the build in message]
         * @param  [type]      $logMsg  [custom message]
         * @return [type]               [update the main logArray variable]
         */
        public function insertToLog( $logType = NULL , $logCase = NULL , $logMsg = NULL ){
            if( !empty( $logType ) && !empty( $logCase ) ){
                    $returnData = array();
                if( empty( $logMsg ) ){
                    $returnMsg = '';
                    switch ( $logCase ) {
                        case 'GET_EXIST':
                            $returnMsg = __('No $_GET variables founds.');
                            break;

                        case 'GET_FOUND':
                            $returnMsg = __('$_GET variables founds.');
                            break;

                        case 'CANT_GET_POSTS':
                            $returnMsg = __('No Post');
                            break;

                        case 'CANT_GET_USERS':
                            $returnMsg = __('No Users');
                            break;

                        case 'CANT_GET_PAGES':
                            $returnMsg = __('No Pages');
                            break;

                        case 'NO_RESULT':
                            $returnMsg = __('No Result');
                            break;

                        default:
                            break;
                    }
                } else
                    $returnMsg = $logMsg;

                if( $logType == 'success' || $logType == 'warning' || $logType == 'error' ){
                    $returnData[$logType][$logCase] = $returnMsg;
                    $this->logArray = $returnData;
                }

            }
            return;
        }

        /**
         * [printLog description]
         * @method printLog
         * @param  [type]   $type [type of log to return ( error , success , warning ) ]
         * @return [type]         [return array by type]
         */
        public function printLog( $type = NULL ){
            if( empty( $this->getResult('members') ) && empty( $this->getResult('posts') ) && empty( $this->getResult('pages') ) )
                $this->insertToLog( 'error' , 'NO_RESULT' );

            if( empty( $type ) )
                return $this->logArray;
            else
                return $this->logArray[$type];
        }
}

/**
 * Extend the class customSearch with the spesific parameters for ADIF PORTAL.
 */
class adifCustomSearch extends customSearch {

    public function __construct( $args = array() ) {
        parent::__construct( $args );
        $this->createSearchArgs( $this->searchArgs );
    }

    /**
     * [allowToSearch description]
     * @method allowToSearch
     * @param  [type]        $case   [the parameter of the args query we want to check]
     * @param  [type]        $string [the string we want to check]
     * @return [type]                [TUEE/FALSE - we can create the query arguments for this parameter.]
     */
     public function allowToSearch( $case = NULL , $string ){
         $return = false;
         switch ( $case ) {
             case 'companies':
                 if( !empty( $string ) && ( ( !is_numeric( $string ) && strlen( $string ) >= $this->minCharForSearch ) || is_numeric( $string ) ) )
                     $return = true;
                 break;

             default:
                 $return = parent::allowToSearch( $case , $string );
                 break;
         }

         if( $this->checkIncludeType( $case ) )
             $return = true;

         return $return;
     }

    /**
    * [createSearchArgs description]
    * Craetion of the query arguments for this search.
    * @method createSearchArgs
    * @return [type][Save the query args in the main variable "argsQuery"]
    */
    public function createSearchArgs( $args = array() ){
        $parentSearchArgs = parent::createSearchArgs( $args );
        $argsQuery        = $newArgsQuery = array();

        $searchParameters = $this->getParameters();
        // Check search parameters and add to $argsQuery
        if( !empty( $searchParameters ) ){
            $searchParameter = !empty( $searchParameters['search'] ) ? $searchParameters['search'] : '';
            if( $searchParameter || ( !empty( $args['include_empty'] ) && $args['include_empty'] == true ) ){

                // Adding Args to member
                if( !empty( $parentSearchArgs ) && is_array( $parentSearchArgs ) ){
                    if( !empty( $parentSearchArgs['members'] ) && is_array( $parentSearchArgs['members'] ) ){
                        $argsQuery['members'] = array(
                            'exclude' => $this->getUsersByRoles( array( 'administrator' , 'company') )
                        );
                        $parentSearchArgs['members'] = array_merge( $parentSearchArgs['members'], $argsQuery['members'] );
                    }
                }

                // Company Args
                if( $this->allowToSearch( 'companies' , $searchParameter ) ){
                    $newArgsQuery['companies'] = array(
                        'number' => $this->maxResult,
                        'search' => '*'.$searchParameter.'*',
                        'role'   => 'company'

                    );
                }

                $parentSearchArgs = array_merge( $parentSearchArgs, $newArgsQuery );
            }
        }

        $this->argsQuery = $parentSearchArgs;

        if( !empty( $searchParameters['role'] ) ){
            $tmpArgs = array(
                'number' => $this->maxResult,
                'include' => $this->getUsersByRoles( $searchParameters['role'] )
            );
            parent::setQuery( 'members' , $tmpArgs );
        }
    }

    /**
     * [initResult description]
     * Execute the search with all the parameters and the args.
     * @method initResult
     * @return [type][Update the main variable "searchData['resualt']"]
     */
    public function initResult(){
        $argsQuery    = $this->argsQuery;
        $searchResult = array();

        // Create array of all the results
        if( !empty( $argsQuery['companies'] ) ){
            $searchResult['companies'] = get_users( $argsQuery['companies'] );
            if( $this->pagination ){
                $tmpArgs = $this->allSearchArgs( $argsQuery['companies'] );
                $this->allSearchResult['companies'] = get_users( $tmpArgs );
            }
        } else{
            $searchResult['companies'] = '';
            $this->insertToLog( 'error' , 'CANT_GET_COMPANIES');
        }
        $parentResult = parent::initResult();
        $searchResult = array_merge( $parentResult , $searchResult );

        $this->searchData['result'] = $searchResult;
    }

}
