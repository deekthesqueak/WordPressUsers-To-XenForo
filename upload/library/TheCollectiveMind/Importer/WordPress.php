<?php
/**
 * This is the main file that contains the logic steps for importing users from WordPress 3.x into XenForo
 *
 * @license https://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 * @copyright Copyright (c) 2012, Derek Bonner
 * @author Derek Bonner <derek@derekbonner.com>
 */
class TheCollectiveMind_Importer_WordPress extends XenForo_Importer_Abstract
{
    /**
     * Source database connection
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_sourceDb;

    /**
     * Table prefix
     * @var string
     */
    protected $_prefix;

    /**
     * Character set
     * @var string
     */
    //protected $_charset = 'windows-1252';

    /**
     * Configuration values
     * @var string
     */
    protected $_config;

    /**
     * Timezone of the WordPress install
     * @var string
     */
    protected $_wpTimezone;

    /**
     * User defiened WordPress roles
     * @var array
     */
    protected $_wpUserRoles;

    /**
     * Sets the name used in the dropdown in the Import External Data tool
     *
     * @return string Description of importer
     */
    public static function getName()
    {
        return 'Wordpress 3.x (Users Only)';
    }

    /**
     * Checks for configuration varibles used when connecting to the DB
     * @param XenForo_ControllerAdmin_Abstract $controller
     * @param array $config Settings passed from import_wordpress_config form
     *
     * @return bool|XenForo_ControllerResponse_View
     */
    public function configure(XenForo_ControllerAdmin_Abstract $controller, array &$config)
    {
        // If the configuraiton variables are set validate them.
        if ($config) {
            //Do not retain keys from Wordpress
            $config['retain_keys'] = false;
            $errors = $this->validateConfiguration($config);
            if($errors)
            {
                return $controller->responseError($errors);
            }

            $this->_bootstrap($config);

            return true;
        }
        // No configuration variables are set. Dispaly the form template to get them from the user
        else {
            return $controller->responseView('XenForo_ViewAdmin_Import_WordPress_Config','import_wordpress_config');
        }
    }

    /**
     * Validates DB configuration variables
     * @param array $config Settings passed from import_wordpress_config form
     *
     * @return array Array of error strings
     */
    public function validateConfiguration(array &$config)
    {
        $errors = array();

        $config['db']['prefix'] = preg_replace('/[^a-z0-9_]/i','',$config['db']['prefix']);

        try {
            $db = Zend_Db::factory('mysqli',
                array(
                    'host' => $config['db']['host'],
                    'port' => $config['db']['port'],
                    'username' => $config['db']['username'],
                    'password' => $config['db']['password'],
                    'dbname' => $config['db']['dbname']
                )
            );
            $db->getConnection();
        }
        catch (Zend_Db_Exception $e) {
            $errors[] = new XenForo_Phrase('source_database_connection_details_not_correct_x', array('error' => $e->getMessage()));
        }

        if ($errors) {
            return $errors;
        }

        try {
            $db->query('
                SELECT user_login
                FROM ' . $config['db']['prefix'] . 'users
                LIMIT 1
            ');
        }
        catch (Zend_Db_Exception $e) {
            if ($config['db']['dbname'] === '')
            {
                $errors[] = new XenForo_Prase('please_enter_database_name');
            }
            else
            {
                $errors[] = new XenForo_Phrase('table_prefix_or_database_name_is_not_correct');
            }
        }

        // Remove comment if character set is ever needed
        //if(!$errors)
        //{
        //    $defaultCharset = $db->fetchOne("
        //        SELECT option_value
        //        FROM {$config['db']['prefix']}options
        //        WHERE option_nam = 'blog_charset'
        //    ");
        //    $config['charset'] = strtolower($defaultCharset);
        //}

        return $errors;
    }

    /**
     * This function is lists all the possible steps that can take place during the import.
     * Dependencies can bet set in each set to require certian actions to be done first.
     *
     * @return array List of all the steps a user can take and their dependencies
     */
    public function getSteps()
    {
        return array(
            'userGroups' => array(
                'title' => new XenForo_Phrase('map_wordpress_roles')
            ),
            'users' => array(
                'title' => new XenForo_Phrase('import_users'),
                'depends' => array('userGroups')
            )
        );
    }

    /**
     * Sets up the source DB object to be used in each step.
     * Function is run every time this class is called.
     * If the source DB object has already been created no action is perfomed
     *
     * @param array $config Settings passed from import_wordpress_config form
     */
    protected function _bootstrap(array $config)
    {

        if ($this->_sourceDb) {
            // already run
            return;
        }

        //Sets time limit on execution to infinity
        @set_time_limit(0);

        $this->_config = $config;

        $this->_sourceDb = Zend_Db::factory('mysqli',
            array(
                'host' => $config['db']['host'],
                'port' => $config['db']['port'],
                'username' => $config['db']['username'],
                'password' => $config['db']['password'],
                'dbname' => $config['db']['dbname']
            )
        );

        $this->_prefix = preg_replace('/[^a-z0-9_]/i', '', $config['db']['prefix']);

        //if(!empty($config['charset']))
        //{
        //    $this->_charset = $config['charset'];
        //}
    }

    /**
     * @param array $options User options for setting up user groups
     *
     * @return bool|XenForo_ControllerResponse_View
     */
    public function configStepUserGroups(array $options)
    {
        if ($options) {
            return false;
        }

        $config = $this->_session->getConfig();

        $userGroup = XenForo_Model::create('XenForo_Model_UserGroup');
        $viewParams = array('default' => array (
            'subscriber'    => $userGroup->getUserGroupOptions(XenForo_Model_User::$defaultRegisteredGroupId),
            'administrator' => $userGroup->getUserGroupOptions(XenForo_Model_User::$defaultAdminGroupId),
            'editor'        => $userGroup->getUserGroupOptions(XenForo_Model_User::$defaultModeratorGroupId),
            'author'        => $userGroup->getUserGroupOptions(XenForo_Model_User::$defaultModeratorGroupId),
            'contributor'   => $userGroup->getUserGroupOptions(XenForo_Model_User::$defaultRegisteredGroupId)
        ));

        if (!$this->_sourceDb) {
            $this->_sourceDb = Zend_Db::factory('mysqli',
                array(
                    'host' => $config['db']['host'],
                    'port' => $config['db']['port'],
                    'username' => $config['db']['username'],
                    'password' => $config['db']['password'],
                    'dbname' => $config['db']['dbname']
                )
            );
        }

        if (!$this->_prefix) {
            $this->_prefix = $config['db']['prefix'];
        }

        $this->_wpUserRoles = $this->_getWordpressUserRoles();

        if (!empty($this->_wpUserRoles)) {
            $viewParams['extraRles'] = true;
            foreach($this->_wpUserRoles as $role) {
                $viewParams['userRoles'][] = array('options' => $userGroup->getUserGroupOptions(XenForo_Model_User::$defaultRegisteredGroupId),
                                                   'name'    => $role['name'],
                                                   'label'   => $role['label']);
            }
        }

        return $this->_controller->responseView('XenForo_ViewAdmin_Import_WordPress_ConfigUserGroups', 'import_wordpress_map_roles', $viewParams);
    }

    /**
     * @param array $options User options for setting up users
     *
     * @return bool|XenForo_ControllerResponse_View If options have already been submitted return false, else return the config_users form
     */
    public function configStepUsers(array $options)
    {
        if ($options) {
            return false;
        }

        return $this->_controller->responseView('XenForo_ViewAdmin_Import_WordPress_ConfigUsers', 'import_config_users');
    }

    /**
     * @param string $start Not used in function
     * @param array $options Settings passed from import_wordpress_config_usergroups form
     *
     * @return bool
     */
    public function stepUserGroups($start, array $options)
    {
        //Save the submitted roles to groups mapping in session to be used by stepUsers
        $this->_session->setExtraData('rolesToGroups', $options);

        return true;
    }

    /**
     * @param string $start Starting user ID for batch
     * @param array $options Settings passed from import_config_users form
     *
     * @return array
     */
    public function stepUsers($start, array $options)
    {

        $options = array_merge(array(
            'limit'         => 100,
            'max'           => false,
            'mergeEmail'    => false,
            'mergeName'     => false,
            'gravatar'      => false,
            'rolesToGroups' => null
        ), $options);

        $sDb = $this->_sourceDb;
        $prefix = $this->_prefix;

        $this->_wpTimezone = $sDb->fetchOne('
            SELECT option_value
            FROM ' . $prefix . 'options
            WHERE option_name = \'timezone_string\'
        ');

        //TODO Add config option to pull timezone from WordPress
        //Set default timezone
        if($this->_wpTimezone) {
            date_default_timezone_set($this->_wpTimezone);
        }

        if (!$options['rolesToGroups']) {
            $options['rolesToGroups'] = $this->_session->getExtraData('rolesToGroups');
        }

        if ($options['max'] === false) {
            $options['max'] = $sDb->fetchOne('
                SELECT MAX(ID)
                FROM ' . $prefix . 'users
            ');
        }

        $users = $sDb->fetchAll(
            $sDb->limit($this->_getSelectUserSql('users.id > ' . $sDb->quote($start)), $options['limit'])
        );

        if (!$users) {
            return $this->_getNextUserStep();
        }

        XenForo_Db::beginTransaction();

        $next = 0;
        $total = 0;
        foreach ($users AS $user) {
            $next = $user['ID'];

            $imported = $this->_importOrMergeUser($user, $options);
            if ($imported) {
                $total++;
            }
        }

        XenForo_Db::commit();

        $this->_session->incrementStepImportTotal($total);

        return array($next, $options, $this->_getProgressOutput($next, $options['max']));
    }

    /**
     * This will handle cases when there is a conflict with an existing users and they were not resolved automatically
     * @param string $start Not used in this function
     * @param array $options Options passed from the import_merge_users form
     *
     * @return bool|string|XenForo_ControllerResponse_View
     */
    public function stepUsersMerge($start, array $options)
    {
        $sDb = $this->_sourceDb;

        $manual = $this->_session->getExtraData('userMerge');

        if ($manual) {
            $merge = $sDb->fetchAll($this->_getSelectUserSql('users.id IN (' . $sDb->quote(array_keys($manual)) . ')'));

            $resolve = $this->_controller->getInput()->filterSingle('resolve', XenForo_Input::ARRAY_SIMPLE);
            if ($resolve && !empty($options['shownForm'])) {
                $this->_session->unsetExtraData('userMerge');
                $this->_resolveUserConfights($merge, $resolve);
            }
            else {
                $options['shownForm'] = true;
                $this->_session->setStepInfo(0, $options);

                $users = array();
                foreach ($merge AS $user) {
                    $users[$user['ID']] = array(
                        'username' => $this->_convertToUtf8($user['user_login'], true),
                        'email' => $this->_convertToUtf8($user['user_email']),
                        'register_date' => strtotime($user['user_registered']),
                        'conflict' => $manual[$user['ID']]
                    );
                }

                return $this->_controller->responseView('XenForo_ViewAdmin_Import_MergeUsers', 'import_merge_users', array('users' => $users));
            }
        }

        return $this->_getNextUserStep();
    }

    /**
     * Takes user input from UsersMerged setp and resolves them
     *
     * @param array $users WordPress user data
     * @param array $resolve Resolved users
     */
    protected function _resolveUserConfights(array $users, array $resolve)
    {
        $total = 0;

        XenForo_Db::beginTransaction();

        foreach ($users AS $user) {
            if (empty($resolve[$user['ID']])) {
                continue;
            }

            $info = $resolve[$user['ID']];

            if (empty($info['action']) || $info['action'] == 'change') {
                if (isset($info['email'])) {
                    $user['user_email'] = $info['email'];
                }
                if (isset($info['username'])) {
                    $user['user_login'] = $info['username'];
                }

                $imported = $this->_importOrMergeUser($user);
                if ($imported) {
                    $total++;
                }
            }
            else if ($info['action'] == 'merge') {
                if ($match = $this->_importModel->getUserIdByEmail($this->_convertToUtf8($user['user_email']))) {
                    $this->_mergeUser($user, $match);
                }
                else if ($match = $this->_importModel->getUserIdByUserName($this->_convertToUtf8($user['user_login'], true))) {
                    $this->_mergeUser($user, $math);
                }

                $total++;
            }
        }

        XenForo_Db::commit();

        $this->_session->incrementStepImportTotal($total, 'users');
    }

    /**
     * Returns SQL query for select batch of users from Wordpress to be processed
     * @param string $where Where statement for selecting differnt sets of users
     *
     * @return string SQL query to select all columns needed to import a user
     */
    protected function _getSelectUserSql($where)
    {
        $sql = "SELECT users.*, usermeta.meta_value "
             . "FROM {$this->_prefix}users AS users "
             . "LEFT JOIN {$this->_prefix}usermeta AS usermeta ON users.id=usermeta.user_id "
             . "WHERE {$where} "
             . "AND users.id > 1 "
             . "AND usermeta.meta_key = 'wp_capabilities' "
             . "ORDER BY users.id";

        return $sql;
    }

    /**
     * Merge imported WordPress user with existing XenForo user
     *
     * @param array $user WordPress user data
     * @param string $targetUserId Target XenForo user Id
     *
     * @return string Target XenForo user Id
     */
    protected function _mergeUser(array $user, $targetUserId)
    {
        $user['user_registered'] = max(0, strtotime($user['user_registered']));

        $this->_db->query('
           UPDATE xf_user SET
               register_date = IF(register_date > ?, ?, register_date)
            WHERE user_id = ?
        ', array($user['user_registered'], $user['user_registered'], $targetUserId));

        $this->_importModel->logImportData('user', $user['ID'], $targetUserId);

        return $targetUserId;
    }

    /**
     * Checks session to see if any users need to be merged manually
     *
     * @return string|bool usersMerge|true
     */
    protected function _getNextUserStep()
    {
        if ($this->_session->getExtraData('userMerge')) {
            return 'usersMerge';
        }

        return true;
    }

    /**
     * Determines if user can be directly imported or needs to be merged
     *
     * @param array $user WordPress user data
     * @param array $options
     *
     * @return string|bool Return user ID on successful import/merge, false on failure
     */
    protected function _importOrMergeUser(array $user, array $options = array())
    {
        //Merge based on email
        if ($user['user_email'] && $emailMatch = $this->_importModel->getUserIdByEmail($this->_convertToUtf8($user['user_email']))) {
            //If option to automatically merge on email was set
            if (!empty($options['mergeEmail'])) {
                return $this->_mergeUser($user, $emailMatch);
            }
            //Else add to items to be manually resolved
            else {
                if ($this->_importModel->getUserIdByUserName($this->_convertToUtf8($user['user_login'], true))) {
                    $this->_session->setExtraData('userMerge', $user['ID'], 'both');
                }
                else {
                    $this->_session->setExtraData('userMerge', $user['ID'], 'email');
                }
                return false;
            }
        }

        //Merge based on username
        if ($nameMatch = $this->_importModel->getUserIdByUserName($this->_convertToUtf8($user['user_login'], true))) {
            //If option to automatically merge username was set
            if(!empty($options['mergeName'])) {
                return $this->_mergeUser($user, $nameMatch);
            }
            //Else add to items to be manually resolved
            else {
                $this->_session->setExtraData('userMerge', $user['ID'], 'name');
                return false;
            }
        }

        //Import new user
        return $this->_importUser($user, $options);
    }

    /**
     * Imports new user
     *
     * @param array $user WordPress user data
     * @param array $options Options to be used when importing users
     *
     * @return string XenForo user ID of imported user
     */
    protected function _importUser(array $user, array $options)
    {
        $import = array(
           'username' => $this->_convertToUtf8($user['user_login'], true),
           'email' => $this->_convertToUtf8($user['user_email']),
           'user_group_id' => '1',
           'authentication' => array(
                'scheme_class' => 'TheCollectiveMind_Authentication_WordPress',
                'data' => array(
                    'hash' => $user['user_pass'],
                    'salt' =>''
                )
           ),
           'homepage' => $user['user_url'],
           'register_date' => strtotime($user['user_registered']),
        );

        // If the user has a valid WordPress role use the mappings defined in stepUserGroups to assign the primary user group
        if ($user['meta_value']) {
            $role = key(unserialize($user['meta_value']));
            if (isset($options['rolesToGroups'][$role])) {
                $import['user_group_id'] = $options['rolesToGroups'][$role];
            }
        }
        // If gravatar was selected during configuration set their email
        if ($options['gravatar']) {
            $import['gravatar'] = $this->_convertToUtf8($user['user_email']);
        }

        // Set the timezone of the user to that of the WordPress install
        $import['timezone'] = $this->_wpTimezone;

        $importedUserId = $this->_importModel->importUser($user['ID'], $import, $failedKey);

        return $importedUserId;
    }

    /**
     * Gets all the custom WordPress roles
     */
    protected function _getWordpressUserRoles()
    {
        $systemRoles = array('administrator',
                             'editor',
                             'author',
                             'contributor',
                             'subscriber');

        $rawUserRoles = $this->_sourceDb->fetchOne('
            SELECT option_value 
            FROM ' . $this->_prefix . 'options 
            WHERE option_name = \'' . $this->_prefix . 'user_roles\'
        ');

        $rawUserRoles = unserialize($rawUserRoles);
        $userRoles = array();
        foreach ($rawUserRoles as $key => $role) {
            if (!in_array($key, $systemRoles)) {
                $userRoles[] = array('name'  => $key,
                                     'label' => $role['name']);
            }
        }

        return $userRoles;
    }
}
