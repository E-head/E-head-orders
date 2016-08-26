<?php

/**
 * Default application conroller
 * @version $Id: IndexController.php 10173 2009-07-03 13:28:06Z uerter $
 */
class IndexController extends OSDN_Controller_Action
{
    /**
     * The main access point into application
     *
     */
    public function indexAction()
    {
        if (!OSDN_Accounts_Prototype::isAuthenticated()) $this->_redirect('/index/login');
    }

    public function getCountriesAction()
    {
        $callback = $this->_getParam('callback');
        $output = array();
        $countries = Zend_Locale::getCountryTranslationList(OSDN_Language::getDefaultLocale());
        if (is_array($countries)) {
        	sort($countries);
            foreach ($countries as $v) {
                array_push($output, array('name' => $v));
            }
        }
        if ($callback) {
            $this->disableRender(true);
            echo $callback.'('.Zend_Json::encode($output).')';
        } else {
            $this->view->countries = $output;
            $this->view->success = true;
        }
    }

    /**
     * User authentification.
     * Destroy current session and create new if authentification has been success.
     * @return void
     */
    public function loginAction()
    {
        Zend_Auth::getInstance()->clearIdentity();

        $do = trim($this->_getParam('do'));
        $login = trim($this->_getParam('login'));
        $password = trim($this->_getParam('password'));
        $errMes = 'ОШИБКА АВТОРИЗАЦИИ!';

        if (empty($do)) {
            $this->view->message = '';
            return;
        }

        if (empty($login) || empty($password)) {
            $this->view->message = $errMes;
            return;
        }

        $dbAdapter = OSDN_Db_Table_Abstract::getDefaultAdapter();
        $authAdapter = new Zend_Auth_Adapter_DbTable($dbAdapter);

        $authAdapter->setTableName(OSDN_Db_Table_Abstract::getDefaultPrefix() . 'accounts');
        $authAdapter->setIdentityColumn('login');
        $authAdapter->setCredentialColumn('password');

        $authAdapter->setIdentity($login);
        $authAdapter->setCredential(md5($password));

        $result = $authAdapter->authenticate();

        if (!$result->isValid()) {
            $this->view->message = $errMes;
            return;
        }
        
        // check is account active
        $userModel = new OSDN_Accounts();
        $userResponse = $userModel->fetchByLogin($login);
       
        if ($userResponse->hasNotSuccess()) {
            $this->view->message = $errMes;
            return;
        }
        
        $userData = $userResponse->getRow(); 
        
        if ('1' != $userData['active'] && '1' != $userData['id']) {
            $this->view->message = $errMes;
            return;
        }

        // instance of stdClass
        $data = $authAdapter->getResultRowObject(null, 'password');
        $roleId = $data->role_id;

        // try to create acl object and assign the permissions
        $acl = new OSDN_Acl();
        $permissions = new OSDN_Acl_Permission();
        $response = $permissions->fetchByRoleId($roleId);
        if ($response->isSuccess()) {
            $rowset = $response->getRowset();
            foreach ($rowset as $row) {
                $resourceId = $row['resource_id'];
                $acl->addResource($resourceId);
                $acl->allow($resourceId, $row['privilege_id']);
            }
        }

        /**
         * Store acl object into the standart auth storage
         * When user go to logout or session time is out
         * then acl will be destroyed with user's authentification settings
         */
        $data->acl = $acl;

        $auth = Zend_Auth::getInstance();
        $auth->getStorage()->write($data);

        //var_dump($auth->getStorage()); exit;

        header('Location: /');
    }

    /**
     */
    public function logoutAction()
    {
        $this->disableRender(true);
        if (Zend_Auth::getInstance()->hasIdentity()) {
            Zend_Auth::getInstance()->clearIdentity();
        }
        Zend_Session::destroy();

        $this->view->success = true;
        header('Location: /');
    }
}