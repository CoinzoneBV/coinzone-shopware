<?php

class Shopware_Controllers_Frontend_Coinzone extends Shopware_Controllers_Frontend_Payment
{

    public function indexAction()
    {
        $router = $this->Front()->Router();
        $checkOutUrl = $router->assemble(
            array('action' => 'payment', 'sTarget' => 'checkout','sViewport'   => 'account', 'appendSession' => true,'forceSecure' => true)
        );

        $coinzoneUrl = $this->getPaymentUrl();
        if ($coinzoneUrl === false) {
            $this->redirect($checkOutUrl . '?coinzone_error=1');
            return;
        }
        $this->redirect($coinzoneUrl);
    }

    public function notifyAction()
    {
        $requestUrl = $this->Request()->getScheme() . '://' . $this->Request()->getHttpHost() . $this->Request()->getRequestUri();
        $timestamp = $this->Request()->getHeader('timestamp');

        /* request type : json | http_post */
        $content = file_get_contents('php://input');
        $input = json_decode($content, 1);
        if (empty($content) || json_last_error() !== JSON_ERROR_NONE) {
            $input = $this->Request()->getPost();
            $content = http_build_query($input);
        }

        /** check signature */
        $coinzoneConfig = Shopware()->Plugins()->Frontend()->Coinzone()->Config();

        $apiKey = $coinzoneConfig->get('apiKey');

        $stringToSign = $content . $requestUrl . $timestamp;

        $signature = hash_hmac('sha256', $stringToSign, $apiKey);
        if ($signature !== $this->Request()->getHeader('signature')) {
            $this->Response()->setHeader('HTTP/1.1', '400 Bad Request');
            $this->Response()->sendResponse();
            exit('Invalid Signature');
        }

        switch ($input->status) {
            case "PAID":
            case "COMPLETE":
                $this->savePaymentStatus($input->refNo, $input->merchantReference, 12);
                exit('OK_PAID');
                break;
            default:
                exit('NO_ACTION');
                break;
        }
    }

    protected function getPaymentUrl()
    {
        $router = $this->Front()->Router();
        $notifyUrl = $router->assemble(array('action' => 'notify', 'forceSecure' => true));

        $user = Shopware()->Session()->sOrderVariables['sUserData'];
        $paymentId = $this->createPaymentUniqueId();

        $coinzoneConfig = Shopware()->Plugins()->Frontend()->Coinzone()->Config();
        $clientCode = $coinzoneConfig->get('clientCode');
        $apiKey = $coinzoneConfig->get('apiKey');

        if (is_null($clientCode) || is_null($apiKey)) {
            return false;
        }

        /* add products ordered to API request */
        $items = $this->getBasket();
        $displayItems = array();
        foreach ($items['content'] as $item) {
            $displayItems[] = array(
                'name' => $item['articlename'],
                'quantity' => (int)$item['quantity'],
                'unitPrice' => (int)$item['price'],
                'shortDescription' => $item['additional_details']['description'],
                'imageUrl' => !empty($item['image']['src']['original']) ? $item['image']['src']['original'] : ''
            );
        }

        /* create payload array */
        $payload = array(
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrencyShortName(),
            'merchantReference' => $paymentId,
            'email' => $user['additional']['user']['email'],
            'notificationUrl' => $notifyUrl,
            'displayOrderInformation' => array(
                'items' => $displayItems,
                'shippingCost' => $this->getBasket()['sShippingcosts']
            ),
        );
        $coinzone = new Shopware_Plugins_Frontend_Coinzone_Components_CoinzoneApi($clientCode, $apiKey);
        $response = $coinzone->callApi('transaction', $payload);

        if ($response->status->code !== 201) {
            return false;
        }

        $this->saveOrder($response->response->refNo, $paymentId);

        return $response->response->url;
    }

}
