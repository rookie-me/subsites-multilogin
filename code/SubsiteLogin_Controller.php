<?php

    /**
     * Class SubsiteLogin_Controller
     * Controller that handles login using UID (TempID) and Token (AutoLoginToken) to
     * offer _some_ degree of Security when logging in cross-domain
     *
     * @package subsites-multilogin
     */
    class SubsiteLogin_Controller extends Controller
    {

        private static $allowed_actions = [
            "index" => "->hasRequiredVars",
        ];

        private static $url_segment = "subsitelogin";

        /**
         * @return string
         */
        public function Link()
        {
            return $this->config()->get( "url_segment" ) . "/";
        }

        /**
         * @return bool
         */
        public function hasRequiredVars()
        {
            $request = $this->getRequest();
            $requiredVars = [ "UID", "Token", "Origin", "Domains", "Endpoint" ];
            $requestVarsArray = $request->requestVars();

            $intersect = array_intersect( $requiredVars, array_keys( $requestVarsArray ) );

            return count( $intersect ) === count( $requiredVars );
        }

        /**
         * @param SS_HTTPRequest $request
         *
         * @return bool
         */
        public function index( $request )
        {
            $requestVarsArray = $request->requestVars();

            $member = Member::get()->filter( [ "TempIDHash" => $requestVarsArray[ "UID" ] ] )->first();
            $success = ( $member ) ? $member->validateAutoLoginToken( $requestVarsArray[ "Token" ] ) : false;

            // No user found means *potentially* there's been some hackery
            if ( !$success ) {
                return $this->httpError( 500, "Error attempting multi-site login" );
            }

            // If there's not a user logged in already, attempt login
            if ( !Member::currentUser() ) {
                $member->logIn();

                // These fields have been updated, so update them
                $requestVarsArray[ "UID" ] = $member->TempIDHash;
                $requestVarsArray[ "Token" ] = $member->generateAutologinTokenAndStoreHash( 1 );

                // Add a hook to pass more data down the line
                $member->extend( "onAfterMultiLogin", $requestVarsArray );
            }

            $next = $this->nextLink( $requestVarsArray );

            return $this->redirect( $next );
        }

        /**
         * @param array $requestVarsArray
         *
         * @return string
         */
        protected function nextLink( $requestVarsArray )
        {
            $domainids = explode( ",", $requestVarsArray[ "Domains" ] );
            $nextDomain = false;
            $thisDomainID = Subsite::getSubsiteIDForDomain();

            do {
                // Get the ID of the next (or last) SubsiteDomain
                $nextDomainID = array_shift( $domainids );

                // Get the SubsiteDomain object by ID, but skip this domain id if it has somehow managed to find its way in to the query
                if ( $nextDomainID && $nextDomainID !== $thisDomainID ) {
                    $nextDomainObj = SubsiteDomain::get()->byID( (int)$nextDomainID );

                    // If the SubsiteDomain object exists, get the next domain
                    if ( $nextDomainObj ) {
                        $nextDomain = $nextDomainObj->getAbsoluteLink();
                    }

                }

            } while ( !$nextDomain && !empty( $domainids ) );

            // If we have reached the end of the line, redirect to the final page of this domain
            if ( !$nextDomain ) {
                return $this->endpointLink( $requestVarsArray );
            }

            // Update the domains on the request vars object to reflect the new list of domain ids
            $requestVarsArray[ "Domains" ] = implode( ",", $domainids );

            return Controller::join_links( $nextDomain, $this->config()->get( "url_segment" ) ) . "?" . http_build_query( $requestVarsArray );
        }

        /**
         * @param $requestVarsArray
         */
        protected function endpointLink( $requestVarsArray )
        {
            $lastDomainID = $requestVarsArray[ "Origin" ];
            $lastDomainObj = SubsiteDomain::get()->byID( (int)$lastDomainID );
            $lastDomain = $lastDomainObj->getAbsoluteLink();
            $endpoint = urldecode( $requestVarsArray[ "Endpoint" ] );

            return Controller::join_links( $lastDomain, $endpoint );
        }

        /**
         * @return SS_HTTPResponse
         * @throws Exception
         */
        public static function attemptMultisiteLogin( $endpoint )
        {
            $member = Member::currentUser();
            if ( !$member ) {
                throw new Exception( "Must be logged in to attempt multisite login." );
            }

            // Grab the current subsitedomain id
            $currentSubsiteDomainID = SubsiteDomainExtension::getSubsiteDomainIDForDomain();
            $currentSubsiteDomain = SubsiteDomain::get()->byID( $currentSubsiteDomainID );

            // If we're unable to match the current subsite domain, just redirect to the endpoint immediately
            if ( !$currentSubsiteDomain || !$currentSubsiteDomain ) {
                return Controller::curr()->redirect( $endpoint );
            }

            // Grab all the ids of the subsitedomains that we should try to log in with
            $otherSubsiteDomainIDs = $currentSubsiteDomain->LoginWith()->column( "ID" );

            // If we're not attempting a multi site login, just redirect to the endpoint immediately
            if ( empty( $otherSubsiteDomainIDs ) ) {
                return Controller::curr()->redirect( $endpoint );
            }

            // Create a tempid to represent the userid
            $member->regenerateTempID();

            // Create a login token to represent the password
            $token = $member->generateAutologinTokenAndStoreHash( 1 );

            // Save these changes
            $member->write();
            $requestVars = [
                "UID"      => $member->TempIDHash,
                "Token"    => $token,
                "Origin"   => $currentSubsiteDomainID,
                "Domains"  => implode( ",", $otherSubsiteDomainIDs ),
                "Endpoint" => Director::makeRelative( $endpoint ),
            ];

            // Build the subsitelogin endpoint url
            $thisURL = SubsiteLogin_Controller::singleton()->Link();

            try {
                return Controller::curr()->redirect( $thisURL . "?" . http_build_query( $requestVars ) );
            } catch ( Exception $e ) {
                SS_Log::log( "Error attempting subsite login. Error: {$e->getMessage()}", SS_Log::ERR );

                return Controller::curr()->redirect( $endpoint );
            }
        }
    }
