#silverstripe-subsites-multilogin

Extension for [silverstripe-subsites](https://github.com/silverstripe/silverstripe-subsites) to allow a single login process to propogate across multiple Subsite Domains. This is accomplished by injecting a new `MemberLoginForm` and processing a request on each domain using existing Silverstripe database columns (`TempID` and `AutoLoginHash`). This allows a seamless transition between subsite domains even across top-level domains (i.e. not restricted to subdomains of the same site).

## Requirements
 * SilverStripe ^3.6 (may work on earlier versions - haven't tested this)
 * Subsites
 * Multiple Subsitedomains
 
## Installation
Use composer to install this module

```composer require rookieme/subsites-multilogin```

After a `dev/build`, go to the CMS > Subsites > Domains. Here you'll be able to set the `LoginWith` domains, and see which domains are using this domain to log in.

It's also recommended (but not required) that you alter the `url_segment` for the `SubsiteLogin_Controller`. This will require the following:

```yaml
Director:
  rules:
    'fantasticnewendpoint' : 'SubsiteLogin_Controller'
    
SubsiteLogin_Controller:    
  url_segment: "fantasticnewendpoint"
```

## License
See [License](license.md)

## Maintainers
 * Tim Larsen <tim@rookieme.com>
 
##Notes:
- All domains should be https (generally speaking, but especially for any login functionality)
- The `logInUserAndRedirect` function has been overwritten, but _most_ of the original functionality should remain - the "Remember me" functionality _may_ be broken
- If a Member is already logged in on one of the domains, they are not logged out
- This is not a single session persisting across multiple domains; it is simply mimicking a user logging in to each domain individually through redirects
- The maximum number of redirects allowed by modern browsers is 16. On IE8, this limit is dropped to 10. See https://stackoverflow.com/a/36041063
- I'd recommend having a "Loading" screen that stops users from clicking when the Member Login form passes validation and is attempting login - the multisite login process can take a while!
- This should be tested _thoroughly_ before being used on production
 
## Bugtracker
Bugs are tracked in the issues section of this repository. Before submitting an issue please read over 
existing issues to ensure yours is unique. 
 
If the issue does look like a new bug:
 
 - Create a new issue
 - Describe the steps required to reproduce your issue, and the expected outcome. Unit tests, screenshots 
 and screencasts can help here.
 - Describe your environment as detailed as possible: SilverStripe version, Browser, PHP version, 
 Operating System, any installed SilverStripe modules.
 
Please report security issues to the module maintainers directly. Please don't file security issues in the bugtracker.
 
## Development and contribution
If you would like to make contributions to the module please ensure you raise a pull request and discuss with the module maintainers. I'm always on the silverstripe-users Slack channel if you've got any ideas on how to improve this module.

##TODO:
- Verify referrer on `SubsiteLogin_Controller`  
- Offer multiple methods of logging in
- Better error logging/handling
- Offer solutions that don't overwrite MemberLoginForm
- Log (in session?) which subsitedomain was the original entry point for login