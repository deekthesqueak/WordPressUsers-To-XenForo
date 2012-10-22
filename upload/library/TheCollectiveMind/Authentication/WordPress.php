<?php
/**
 * This will mimic the the authentication scheme of WordPress. It will not create new hashes.
 * I suggest that you encourage your users to change their password after import as it will switch to XenForo_Authentication_Core
 *
 * @license https://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 * @copyright Copyright (c) 2012, Derek Bonner
 * @author Derek Bonner <derek@derekbonner.com>
 */
class TheCollectiveMind_Authentication_WordPress extends XenForo_Authentication_Abstract
{
    /**
     * Data blob from xf_user_authenticate.data
     * @var array
     */
    protected $_data = array();

    /**
     * Initialize data for the authentication object.
     *
     * @param string Binary data from the database
     */
    public function setData($data)
    {
        $this->_data = unserialize($data);
    }

    /**
     * Generate new authentication data for the given password
     *
     * @param string $password Password (plain text)
     *
     * @throws Prohibits creating new password using this Authentication scheme
     */
    public function generate($password)
    {
        throw new XenForo_Exception('Cannot generate authentication for this type.');
    }

    /**
     * Perform authentication against the given password
     *
     * @param integer $userId The user ID we're trying to authenticate as. This may not be needed, but can be used to "upgrade" auth schemes.
     * @param string $password Password (plain text)
     *
     * @return bool True if the authentication is successful
     */
    public function authenticate($userId, $password)
    {

        if (!is_string($password) || $password === '' || empty($this->_data)) {
            return false;
        }

        require_once('class-phpass.php');

        return PasswordHash::CheckPassword($password, $this->_data['hash']);
    }
}
