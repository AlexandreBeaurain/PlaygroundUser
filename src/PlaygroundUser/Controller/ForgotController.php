<?php

namespace PlaygroundUser\Controller;

use Zend\Form\Form;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use PlaygroundUser\Service\Password as PasswordService;
use PlaygroundUser\Options\ForgotControllerOptionsInterface;

class ForgotController extends AbstractActionController
{
    /**
     * @var UserService
     */
    protected $userService;

    /**
     * @var PasswordService
     */
    protected $passwordService;

    /**
     * @var Form
     */
    protected $forgotForm;

    /**
     * @var Form
     */
    protected $resetForm;

    /**
     * @todo Make this dynamic / translation-friendly
     * @var string
     */
    protected $message = 'An e-mail with further instructions has been sent to you.';

    /**
     * @todo Make this dynamic / translation-friendly
     * @var string
     */
    protected $failedMessage = 'The e-mail address is not valid.';

    /**
     * @var ForgotControllerOptionsInterface
     */
    protected $options;

    /**
     * User page
     */
    public function indexAction()
    {
        //$this->getServiceLocator()->get('Zend\Log')->info('ForgotAction...');
        if ($this->zfcUserAuthentication()->hasIdentity()) {
            return $this->redirect()->toRoute('frontend/zfcuser');
        } else {
            return $this->redirect()->toRoute('frontend/zfcuser/forgotpassword');
        }
    }

    public function forgotAction()
    {
        $service = $this->getPasswordService();
        $service->cleanExpiredForgotRequests();

        $request = $this->getRequest();
        $form    = $this->getForgotForm();

        if ( $this->getRequest()->isPost() ) {
            $form->setData($this->getRequest()->getPost());
            if ( $form->isValid() ) {
                $userService = $this->getUserService();

                $email = $form->getData()['email'];

                return $this->redirect()->toRoute('frontend/zfcuser/sentpassword', array("email"=> $email,
                        'channel' => $this->getEvent()->getRouteMatch()->getParam('channel')));
            } else {
                $this->flashMessenger()->setNamespace('playgrounduser-forgot-form')->addMessage($this->failedMessage);

                return array(
                    'forgotForm' => $form,
                );
            }
        }

        // Render the form
        return array(
            'forgotForm' => $form,
        );
    }


    public function sentAction()
    {
        $email = $this->getEvent()->getRouteMatch()->getParam('email');
        $user = $this->getUserService()->getUserMapper()->findByEmail($email);

        $vm = new ViewModel();
        //only send request when email is found
        if ($user != null) {
            $this->getPasswordService()->sendProcessForgotRequest($user->getId(), $email);
            $vm->setVariables(array(
                'statusMail' => true,
                'email' => $email
            ));
        } else {
            $vm->setVariables(array(
                'statusMail' => false,
                'email' => $email
            ));
        }

        return $vm;
    }

    public function resetAction()
    {
        $service = $this->getPasswordService();
        $service->cleanExpiredForgotRequests();

        $request = $this->getRequest();
        $form    = $this->getResetForm();

        $userId    = $this->params()->fromRoute('userId', null);
        $token     = $this->params()->fromRoute('token', null);

        $password = $service->getPasswordMapper()->findByUserIdRequestKey($userId, $token);

        //no request for a new password found
        if ($password === null) {
            return $this->redirect()->toRoute('frontend/zfcuser/forgotpassword');
        }

        $userService = $this->getUserService();
        $user = $userService->getUserMapper()->findById($userId);

        if ( $this->getRequest()->isPost() ) {
            $form->setData($this->getRequest()->getPost());
            if ( $form->isValid() && $user !== null ) {
                $service->resetPassword($password, $user, $form->getData());
                return $this->redirect()->toRoute('frontend/zfcuser/changedpassword', array("userId"=> $user->getId(),
                        'channel' => $this->getEvent()->getRouteMatch()->getParam('channel')));
            }
        }

        // Render the form
        return array(
            'resetForm' => $form,
            'userId'    => $userId,
            'token'     => $token,
            'email'     => $user->getEmail(),
        );
    }

    public function passwordChangedAction()
    {
        $userId = $this->getEvent()->getRouteMatch()->getParam('userId');
        $user = $this->getUserService->getUserMapper()->findById($userId);

        return new ViewModel(array('email' => $user->getEmail()));
    }

    /**
     * Getters/setters for DI stuff
     */

    public function getUserService()
    {
        if (!$this->userService) {
            $this->userService = $this->getServiceLocator()->get('zfcuser_user_service');
        }

        return $this->userService;
    }

    public function setUserService(UserService $userService)
    {
        $this->userService = $userService;

        return $this;
    }

    public function getPasswordService()
    {
        if (!$this->passwordService) {
            $this->passwordService = $this->getServiceLocator()->get('playgrounduser_password_service');
        }

        return $this->passwordService;
    }

    public function setPasswordService(PasswordService $passwordService)
    {
        $this->passwordService = $passwordService;

        return $this;
    }

    public function getForgotForm()
    {
        if (!$this->forgotForm) {
            $this->setForgotForm($this->getServiceLocator()->get('playgrounduser_forgot_form'));
        }

        return $this->forgotForm;
    }

    public function setForgotForm(Form $forgotForm)
    {
        $this->forgotForm = $forgotForm;
    }

    public function getResetForm()
    {
        if (!$this->resetForm) {
            $this->setResetForm($this->getServiceLocator()->get('playgrounduser_reset_form'));
        }

        return $this->resetForm;
    }

    public function setResetForm(Form $resetForm)
    {
        $this->resetForm = $resetForm;
    }

    /**
     * set options
     *
     * @param  ForgotControllerOptionsInterface $options
     * @return ForgotController
     */
    public function setOptions(ForgotControllerOptionsInterface $options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * get options
     *
     * @return ForgotControllerOptionsInterface
     */
    public function getOptions()
    {
        if (!$this->options instanceof ForgotControllerOptionsInterface) {
            $this->setOptions($this->getServiceLocator()->get('playgrounduser_module_options'));
        }

        return $this->options;
    }
}
