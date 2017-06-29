<?php

/**
 * Class SubsiteDomainExtension
 * Add the ability to select multiple SubsiteDomains to login with when user logs in to this subsite domain
 *
 * @param SubsiteDomain $owner
 * @package subsites-multilogin
 */
class SubsiteDomainExtension extends DataExtension
{

    private static $many_many = [
        "LoginWith" => "SubsiteDomain",
    ];

    private static $belongs_many_many = [
        "LoginBy" => "SubsiteDomain",
    ];

    /**
     * Memory cache of subsite id for domains
     *
     * @var array
     */
    private static $_cache_subsite_for_domain = [];

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        parent::updateCMSFields($fields);
        $fields->removeByName("LoginBy");

        /**
         * @var $loginWith GridField
         */
        if ($this->owner->isInDB()) {
            $loginWithConfig = new GridFieldConfig_RelationEditor();
            $loginWithConfig->removeComponentsByType("GridFieldAddNewButton");

            if (class_exists("GridFieldAddExistingSearchButton")) {
                $loginWithConfig->removeComponentsByType("GridFieldAddExistingAutocompleter");
                $loginWithConfig->addComponent(new GridFieldAddExistingSearchButton());
            }

            $loginWith = new GridField("LoginWith", "Multi Login with", $this->owner->LoginWith(), $loginWithConfig);
            $loginWith->setDescription("These are the subsite domains that will be attempted to log in to after logging in to this domain. We do this to allow a single login to multiple domains.");

            $fields->push($loginWith);

            $loginWithConfigColumns = $loginWithConfig->getComponentByType("GridFieldDataColumns");

            if (class_exists("GridFieldAddExistingSearchButton")) {
                $loginWithConfigExisting = $loginWithConfig->getComponentByType("GridFieldAddExistingSearchButton");
            } else {
                $loginWithConfigExisting = $loginWithConfig->getComponentByType("GridFieldAddExistingAutocompleter");
            }

            $displayFields = $loginWithConfigColumns->getDisplayFields($loginWith);
            $loginWithConfigColumns->setDisplayFields($displayFields);

            $existingList = $loginWithConfigExisting->getSearchList() ?: SubsiteDomain::get()->subtract($this->owner->LoginWith());
            if ($existingList) {
                $existingList = $existingList->addFilter(["ID:not" => $this->owner->ID]);
                $loginWithConfigExisting->setSearchList($existingList);
            }

            $loginByConfig = new GridFieldConfig_RecordViewer();
            $loginBy = new GridField("LoginBy", "Multi Login By", $this->owner->LoginBy(), $loginByConfig);
            $loginBy->setDescription("These are the subsite domains that are logging in to this domain on login.");
            $fields->push($loginBy);
        }
    }

    /**
     * @param int $subsiteDomainID
     *
     * @return bool
     */
    public function isReciprocal($subsiteDomainID)
    {
        return $this->owner->LoginWith()->filter("ID", $subsiteDomainID)->exists();
    }

    /**
     * Get a matching subsite domain ID for the given host, or for the current HTTP_HOST.
     * Supports "fuzzy" matching of domains by placing an asterisk at the start of end of the string,
     * for example matching all subdomains on *.example.com with one subsite,
     * and all subdomains on *.example.org on another.
     *
     * @param $host The host to find the subsite for.  If not specified, $_SERVER['HTTP_HOST'] is used.
     *
     * @return int SubsiteDomain ID
     */
    public static function getSubsiteDomainIDForDomain($host = null, $checkPermissions = true)
    {
        if ($host == null && isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
        }

        $matchingDomains = null;
        $cacheKey = null;
        if ($host) {
            if (!Subsite::$strict_subdomain_matching) {
                $host = preg_replace('/^www\./', '', $host);
            }

            $cacheKey = implode('_', [$host, Member::currentUserID(), Subsite::$check_is_public]);
            if (isset(self::$_cache_subsite_for_domain[$cacheKey])) {
                return self::$_cache_subsite_for_domain[$cacheKey];
            }

            $SQL_host = Convert::raw2sql($host);
            $matchingDomains = DataObject::get(
                "SubsiteDomain",
                "'$SQL_host' LIKE replace(\"SubsiteDomain\".\"Domain\",'*','%')",
                "\"IsPrimary\" DESC"
            )->innerJoin('Subsite', "\"Subsite\".\"ID\" = \"SubsiteDomain\".\"SubsiteID\" AND \"Subsite\".\"IsPublic\"=1");
        }

        if ($matchingDomains && $matchingDomains->Count()) {
            $subsiteIDs = array_unique($matchingDomains->column('SubsiteID'));
            $subsiteDomains = array_unique($matchingDomains->column('Domain'));
            $subsiteDomainIDs = array_unique($matchingDomains->column('ID'));
            if (sizeof($subsiteIDs) > 1) {
                throw new UnexpectedValueException(sprintf(
                    "Multiple subsites match on '%s': %s",
                    $host,
                    implode(',', $subsiteDomains)
                ));
            }

            $subsiteDomainID = $subsiteDomainIDs[0];
        } elseif ($default = Subsite::get()->filter(["DefaultSite" => 1])->first()) {
            // Check for a 'default' subsite
            $subsiteDomainID = $default->Domain()->ID;
        } else {
            // Default subsite id = 0, the main site
            $subsiteDomainID = 0;
        }

        if ($cacheKey) {
            self::$_cache_subsite_for_domain[$cacheKey] = $subsiteDomainID;
        }

        return $subsiteDomainID;
    }


}