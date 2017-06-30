<?php

    /**
     * Class SubsiteMemberLoginForm
     */
    class SubsiteMemberLoginForm extends MemberLoginForm
    {

        /**
         * Send user to the right location after login
         *
         * @param array $data
         *
         * @return SS_HTTPResponse
         */
        protected function logInUserAndRedirect( $data )
        {
            Session::clear( 'SessionForms.MemberLoginForm.Email' );
            Session::clear( 'SessionForms.MemberLoginForm.Remember' );

            // Check password expiry
            if ( Member::currentUser()->isPasswordExpired() ) {
                // Redirect the user to the external password change form if necessary
                $url = 'Security/changepassword';
                //return $this->redirectToChangePassword();
            } elseif ( $url = Security::config()->default_login_dest ) {
                // If a default login dest has been set, redirect to that.
                $url = Controller::join_links( Director::absoluteBaseURL(), $url );
            } else {
                // Redirect the user to the page where they came from
                $member = Member::currentUser();
                if ( $member ) {
                    $firstname = Convert::raw2xml( $member->FirstName );
                    if ( !empty( $data[ 'Remember' ] ) ) {
                        Session::set( 'SessionForms.MemberLoginForm.Remember', '1' );
                        $member->logIn( true );
                    } else {
                        $member->logIn();
                    }

                    Session::set( 'Security.Message.message',
                        _t( 'Member.WELCOMEBACK', "Welcome Back, {firstname}", [ 'firstname' => $firstname ] )
                    );
                    Session::set( "Security.Message.type", "good" );
                }
                // @todo - remove reference to AccountPage, just set the link using an extension
                $accountPage = AccountPage::get()->first();
                $url = $accountPage ? $accountPage->Link() : Director::baseURL();
            }

            $this->extend( "updateLogInUserAndRedirect", $url, $data );

            return SubsiteLogin_Controller::attemptMultisiteLogin( $url );
        }

    }
