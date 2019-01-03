<?php

class ControllerExtensionPaymentBeyonic extends Controller {

    public $beyonic_api_version = 'v1';

    public function index() {
        $this->load->model('checkout/order');
        $data['text_loading'] = 'Loading...';
        $data['description'] = $this->config->get('payment_beyonic_desc2');
        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);
        $data['telephone'] = $order_info['telephone'];
        return $this->load->view('extension/payment/beyonic', $data);
    }

    public function send() {
        $this->load->library('Beyonic');
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/beyonic');
        $this->load->language('checkout/success');

        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);

        $beyond_payment_ammount = number_format((float) $order_info['total'], 2, '.', '');
        if ($this->customer->isLogged()) {
            $error_redirect = '<b><a target="_blank" href="' . $this->url->link('account/edit', '', 'SSL') . '">Your Account</a></b>';
        } else {
            $error_redirect = "";
        }

        // Phone number validation
        if (!preg_match('/^\+\d{6,12}$/', $order_info['telephone'])) {
            $this->session->data['error'] = 'Please make sure your phone number is in international format, starting with a + sign in ' . $error_redirect;
            $json['redirect'] = $this->url->link('checkout/checkout', '', 'SSL');
        }

        $this->authorize_beyonic_gw();
        if (!isset($json['redirect'])) {
            //get saved data
            $beyonic_ipn_url = $this->config->get('payment_beyonic_ipn');
            $url = str_replace("http:", "https:", $beyonic_ipn_url);
            try {
                $received_webhook_id = $this->config->get('payment_beyonic_received_webhook_id');
                if (empty($received_webhook_id)) {
                    $hooks = Beyonic_Webhook::create(array(
                                "event" => "collection.received",
                                "target" => "$url"
                    ));
                    $this->model_extension_payment_beyonic->editSetting('payment_beyonic', 'payment_beyonic_received_webhook_id', $hooks->id);
                }
            } catch (Exception $exc) {
                $notice = json_decode($exc->responseBody);
                if (isset($notice->detail)) {
                    $error = $notice->detail;
                } elseif (isset($notice->target)) {
                    $error = $notice->target[0];
                } else {
                    $error = $notice;
                }
                $this->session->data['error'] = $error;
                $json['redirect'] = $this->url->link('checkout/checkout', '', 'SSL');
            }
        }

        if (!isset($json['redirect'])) {
            try {

                $request = Beyonic_Collection_Request::create(array(
                            "phonenumber" => $order_info['telephone'],
                            "first_name" => $order_info['payment_firstname'],
                            "last_name" => $order_info['payment_lastname'],
                            "email" => $order_info['email'],
                            "amount" => $beyond_payment_ammount,
                            "success_message" => 'Thank you for your payment!',
                            "send_instructions" => true,
                            "currency" => $order_info['currency_code'],
                            "metadata" => array("order_id" => $order_id)
                ));

                $beyonic_collection_id = intval($request->id);
                $note = 'Order payment pending.';
                $order_info['payment_method'] = "Beyonic Mobile Payments";

                $beyonic_order_id = $this->model_extension_payment_beyonic->addOrder($order_info, $beyonic_collection_id);
                $this->model_extension_payment_beyonic->addTransaction($beyonic_order_id, $beyonic_collection_id, $order_info, 'pending', $note);
                $order_status_id = 1;
                $this->model_checkout_order->addOrderHistory($order_id, $order_status_id, $note);
                $json['redirect'] = $this->url->link('extension/payment/beyonic/success', '', 'SSL');
            } catch (Exception $exc) {
                $notice = json_decode($exc->responseBody);
                foreach ($notice as $key => $value) {
                    $notice = $value[0];
                    break;
                }
                $this->session->data['error'] = $notice;
                $json['redirect'] = $this->url->link('checkout/checkout', '', 'SSL');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function authorize_beyonic_gw() {
        Beyonic::setApiVersion($this->beyonic_api_version);
        Beyonic::setApiKey($this->config->get('payment_beyonic_api_key'));
    }

    public function ipn() {

        $this->load->library('Beyonic');
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/beyonic');
        $responce = json_decode(file_get_contents("php://input"));

        if (!empty($responce)) {

            $data = $responce->data;
            $hook = $responce->hook;
            $event = $hook->event;

            if ($event == 'collection.received') {
                //get order id from collection request
                $this->authorize_beyonic_gw();
                $collection_request = Beyonic_Collection_Request::get($data->collection_request);
                $order_id = intval($collection_request->metadata->order_id);
                $status = $data->status;
                if ($status == "successful") {
                    $note = "Order payment Successful.";
                    $order_status_id = 2;
                    $this->model_checkout_order->addOrderHistory($order_id, $order_status_id, $note);
                    $this->model_extension_payment_beyonic->updateTransaction($order_id, 'success', $note);
                } else {
                    $note = "Order payment Cancelled.";
                    $order_status_id = 7;
                    $this->model_checkout_order->addOrderHistory($order_id, $order_status_id, $note);
                    $this->model_extension_payment_beyonic->updateTransaction($order_id, 'cancelled', $note);
                }
            }
        }
    }

    public function success() {
        $this->load->language('checkout/success');
        $this->load->model('checkout/order');
        $order_id = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);
        $phone = $order_info['telephone'];
        if (isset($this->session->data['order_id'])) {
            $this->cart->clear();

            unset($this->session->data['shipping_method']);
            unset($this->session->data['shipping_methods']);
            unset($this->session->data['payment_method']);
            unset($this->session->data['payment_methods']);
            unset($this->session->data['guest']);
            unset($this->session->data['comment']);
            unset($this->session->data['order_id']);
            unset($this->session->data['coupon']);
            unset($this->session->data['reward']);
            unset($this->session->data['voucher']);
            unset($this->session->data['vouchers']);
            unset($this->session->data['totals']);
        }

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_basket'),
            'href' => $this->url->link('checkout/cart')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_checkout'),
            'href' => $this->url->link('checkout/checkout', '', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_success'),
            'href' => $this->url->link('checkout/success')
        );
        $message = '<p style="color:red; font-weight:bold;">Note: Payment instructions have been sent to your phone ' . $phone . '. Please check your phone to complete the payment.<br>Your order cannot be delivered until you complete the payment on your phone.</p><p>Thanks for shopping with us online!</p>';
        if ($this->customer->isLogged()) {
            $data['text_message'] = sprintf($message, $this->url->link('account/account', '', true), $this->url->link('account/order', '', true), $this->url->link('account/download', '', true), $this->url->link('information/contact'));
        } else {
            $data['text_message'] = sprintf($message, $this->url->link('information/contact'));
        }

        $data['continue'] = $this->url->link('common/home');

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('common/success', $data));
    }

    public function callback() {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($this->request->get));
    }

}
