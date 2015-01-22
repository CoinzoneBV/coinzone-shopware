<?php


class Shopware_Plugins_Frontend_Coinzone_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    public function install()
    {
        $this->createForm();
        $this->createEvents();
        $this->createCoinzonePayment();

        return true;
    }

    public function uninstall()
    {
        return true;
    }

    public function update($oldVersion)
    {
        switch($oldVersion) {
            default:
                return true;
                break;
        }
    }

    public function enable()
    {
        try {
            $config = $this->Config();

            $clientCode = $config->get('clientCode');
            if (!isset($clientCode) || empty($clientCode)) {
                throw new Exception('Invalid Client Code');
            }
            $apiKey = $config->get('apiKey');
            if (!isset($apiKey) || empty($apiKey)) {
                throw new Exception('Invalid API Key');
            }

            $payment = $this->Payment();
            $payment->setActive(true);
            return true;
        } catch(Exception $e) {
            $this->log('Could not enable payment:' . $e->getMessage());
            throw new Exception('Could not enable payment:' . $e->getMessage());
        }
    }

    public function disable()
    {
        $payment = $this->Payment();
        if ($payment !== null) {
            $payment->setActive(false);
        }
        return true;
    }

    public function getInfo()
    {
        return array(
            'version' => $this->getVersion(),
            'author' => 'Coinzone BV',
            'label' => 'Coinzone',
            'description' => 'Add your Client Code and API Key below to configure Coinzone.' .
                'This can be found on the API tab of the Settings page in the <a href="https://merchant.coinzone.com/settings">Coinzone Control Panel</a>.<br/>' .
                'Have questions?  Please visit our <a href="http://support.coinzone.com/">Customer Support Site</a>.<br/>' .
                'Don\'t have a Coinzone account? <a href="https://merchant.coinzone.com/signup">Sign up for free.</a>',
            'copyright' => '©2015 Coinzone',
            'support' => 'support@coinzone.com',
            'link' => 'http://coinzone.com'
        );
    }

    public function getVersion()
    {
        return '1.0.0';
    }

    public function Payment()
    {
        return $this->Payments()->findOneBy(
            array(
                'name' => 'coinzone'
            )
        );
    }

    public function createCoinzonePayment()
    {
        try {
            $this->createPayment(
                array(
                    'name' => 'coinzone',
                    'description' => 'Coinzone',
                    'action' => 'coinzone',
                    'active' => 0,
                    'additionalDescription' => ''
                )
            );
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    protected function createForm()
    {
        $form = $this->Form();

        $form->setElement('text', 'clientCode',
            array(
                'label' => 'Client Code',
                'description' => 'Client Code',
                'required' => true
            )
        );
        $form->setElement('text', 'apiKey',
            array(
                'label' => 'API Key',
                'description' => 'API Key',
                'required' => true
            )
        );
    }

    protected function createEvents()
    {
        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Frontend_Coinzone',
            'onGetControllerPathFrontend'
        );
    }

    public function onGetControllerPathFrontend()
    {
        return Shopware()->Plugins()->Frontend()->Coinzone()->Path() . '/Controllers/frontend/Coinzone.php';
    }

    protected function log($text)
    {
        $logModel = new Shopware\Models\Log\Log;
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        if (empty($userAgent)) {
            $userAgent = 'Unknown';
        }
        $logModel->setText($text);
        $logModel->setDate(new \DateTime("now"));
        $logModel->setIpAddress(getenv("REMOTE_ADDR"));
        $logModel->setUserAgent($userAgent);
        Shopware()->Models()->persist($logModel);
        Shopware()->Models()->flush();
    }
}
