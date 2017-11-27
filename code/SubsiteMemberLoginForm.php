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
    protected function logInUserAndRedirect($data)
    {
        Session::clear('SessionForms.MemberLoginForm.Email');
        Session::clear('SessionForms.MemberLoginForm.Remember');

        $url = false;

        // Check password expiry or specific back url
        if (Member::currentUser()->isPasswordExpired()) {
            // Redirect the user to the external password change form if necessary
            $url = 'Security/changepassword';
            //return $this->redirectToChangePassword();
        } elseif (!empty($_REQUEST['BackURL'])) {
            if (Director::is_site_url($_REQUEST['BackURL'])) {
                $url = Director::absoluteURL($_REQUEST['BackURL']);
            }
        }

        // If no back url has been provided, try to ascertain one from config OR default to something welse
        if (!$url) {
            if ($url = Security::config()->default_login_dest) {
                // If a default login dest has been set, redirect to that.
                $url = Director::absoluteURL($url);

            } else {
                // Redirect the user to the page where they came from
                $member = Member::currentUser();
                if ($member) {
                    $firstname = Convert::raw2xml($member->FirstName);
                    if (!empty($data['Remember'])) {
                        Session::set('SessionForms.MemberLoginForm.Remember', '1');
                        $member->logIn(true);
                    } else {
                        $member->logIn();
                    }

                    Session::set('Security.Message.message',
                        _t('Member.WELCOMEBACK', "Welcome Back, {firstname}", ['firstname' => $firstname])
                    );
                    Session::set("Security.Message.type", "good");
                }

                if (class_exists("AccountPage")) {
                    $defaultPage = AccountPage::get()->first();
                } elseif (class_exists("HomePage")) {
                    $defaultPage = HomePage::get()->first();
                }

                $url = isset($defaultPage) && $defaultPage ? $defaultPage->Link() : Director::baseURL();
            }
        }

        $this->extend("updateLogInUserAndRedirect", $url, $data);

        return SubsiteLogin_Controller::attemptMultisiteLogin($url);
    }

}
